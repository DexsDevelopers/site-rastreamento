const express = require('express');
const router = express.Router();
const { getDB } = require('../db');
const QRCodeImg = require('qrcode');
const fs = require('fs');
const path = require('path');

// ===== BOT WHATSAPP (acesso direto ao processo integrado) =====

// Status do bot
router.get('/bot/status', (req, res) => {
    const bot = global._bot;
    if (!bot) {
        return res.json({ success: false, status: { connected: false, message: 'Bot ainda não inicializado. Aguarde ~5s após reiniciar o servidor.' } });
    }
    const isReady = bot.isReady === true;
    const hasQR = !!bot.lastQR;
    res.json({
        success: true,
        status: {
            connected: isReady,
            uptime: null,
            number: null,
            pushname: null,
            platform: null,
            message: isReady ? 'Conectado' : (hasQR ? 'Aguardando scan do QR Code' : 'Conectando ao WhatsApp...')
        }
    });
});

// QR Code do bot
router.get('/bot/qr', async (req, res) => {
    const bot = global._bot;
    if (!bot || !bot.lastQR) {
        return res.json({ success: false, message: bot ? 'QR não disponível — bot já conectado ou inicializando' : 'Bot não inicializado' });
    }
    try {
        const dataUrl = await QRCodeImg.toDataURL(bot.lastQR, { scale: 8, margin: 1 });
        res.json({ success: true, qr: dataUrl });
    } catch (e) {
        res.json({ success: false, message: 'Erro ao gerar imagem do QR Code' });
    }
});

// Vincular por número de telefone (pairing code — sem câmera)
router.post('/bot/pair', async (req, res) => {
    const bot = global._bot;
    if (!bot) return res.json({ success: false, message: 'Bot não inicializado' });

    let { phone } = req.body;
    if (!phone) return res.json({ success: false, message: 'Número de telefone obrigatório' });

    // Formatar: só dígitos, adicionar 55 se necessário
    phone = String(phone).replace(/\D/g, '');
    // Se já tem 55 no início e tem 13 dígitos (55 + DDD + 9 dígitos), está correto
    // Se tem 11 dígitos (DDD + número), adicionar 55
    if (phone.startsWith('55') && phone.length >= 12) {
        // já tem código do país
    } else {
        phone = '55' + phone;
    }

    // Verificar se socket existe e está em estado de não-autenticado (pronto para pairing)
    const sock = bot.sock;
    if (!sock) {
        return res.json({ success: false, message: 'Socket não disponível. Aguarde o bot inicializar (pode levar 10-15s após reiniciar).' });
    }

    // Bloquear reconexão automática por 2 minutos para manter o socket estável
    if (bot.lockForPairing) {
        bot.lockForPairing();
    }

    try {
        const code = await sock.requestPairingCode(phone);
        if (!code) throw new Error('Código vazio retornado');
        // Formatar o código com hífen para facilitar leitura (XXXX-XXXX)
        const formatted = code.length === 8 ? code.slice(0, 4) + '-' + code.slice(4) : code;
        res.json({ success: true, code: formatted, phone: phone });
    } catch (err) {
        const msg = err.message || '';
        let userMsg = 'Erro ao gerar código: ' + msg;
        if (msg.includes('not-authorized') || msg.includes('401')) {
            userMsg = 'Sessão inválida. Clique em "Forçar Novo QR Code" para reiniciar e tente novamente.';
        } else if (msg.includes('already') || msg.includes('registered')) {
            userMsg = 'Este número já está vinculado a uma sessão ativa.';
        }
        res.json({ success: false, message: userMsg });
    }
});

// Reiniciar/reconectar bot
router.post('/bot/restart', async (req, res) => {
    const bot = global._bot;
    if (!bot) {
        return res.json({ success: false, message: 'Bot não inicializado no servidor' });
    }
    try {
        if (bot.sock) {
            try { bot.sock.end(); } catch (e) {}
        }
        const { getDB } = require('../db');
        await bot.initWhatsAppBot(null, getDB());
        res.json({ success: true, message: 'Bot reiniciando — aguarde o novo QR Code' });
    } catch (err) {
        res.json({ success: false, message: 'Erro ao reiniciar: ' + err.message });
    }
});

// Limpar sessão corrompida e reiniciar do zero (novo QR)
router.post('/bot/reset', async (req, res) => {
    const bot = global._bot;
    try {
        // Parar socket atual
        if (bot?.sock) {
            try { bot.sock.end(); } catch (e) {}
        }

        // Determinar pasta auth
        const authDir = bot?.authPath || path.resolve(process.cwd(), 'whatsapp-bot', 'auth');

        // Apagar arquivos de sessão (manter só a pasta)
        if (fs.existsSync(authDir)) {
            const files = fs.readdirSync(authDir);
            for (const file of files) {
                try { fs.rmSync(path.join(authDir, file), { recursive: true, force: true }); } catch (e) {}
            }
            console.log(`[BOT RESET] Sessão limpa em: ${authDir} (${files.length} arquivo(s) removidos)`);
        }

        // Reiniciar bot
        if (bot?.initWhatsAppBot) {
            const { getDB } = require('../db');
            setTimeout(() => bot.initWhatsAppBot(null, getDB()), 1500);
        }

        res.json({ success: true, message: 'Sessão apagada! O bot vai gerar um novo QR em segundos.' });
    } catch (err) {
        res.json({ success: false, message: 'Erro ao limpar sessão: ' + err.message });
    }
});

// Health Check do Banco de Dados
router.get('/db-health', async (req, res) => {
    const db = getDB();
    try {
        if (!db) {
            return res.status(200).json({
                connected: false,
                error: 'Instância do banco não iniciada. Verifique os logs do servidor.'
            });
        }

        // Testar conexão
        await db.query('SELECT 1');

        // Verificar tabelas
        const [tables] = await db.query('SHOW TABLES');
        const dbName = process.env.DB_NAME;
        const tableNames = tables.map(t => Object.values(t)[0]);

        const requiredTables = ['rastreios_status', 'users', 'entregadores'];
        const tableStatus = await Promise.all(requiredTables.map(async name => {
            const exists = tableNames.includes(name);
            let count = 0;
            if (exists) {
                const [rows] = await db.query(`SELECT COUNT(*) as count FROM \`${name}\``);
                count = rows[0].count;
            }
            return { name, exists, count };
        }));

        res.json({
            connected: true,
            database: dbName,
            tables: tableStatus
        });
    } catch (error) {
        res.status(200).json({
            connected: false,
            error: error.message
        });
    }
});

// Setup Initial Tables (se o sistema de migração automática falhar)
router.post('/db-setup', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('DB not connected');

        // Simplesmente aciona o runMigrations que já existe no db.js ou recria aqui
        // No db.js as migrações já rodam no connectDB, mas vamos reforçar aqui se necessário.

        await db.query(`
            CREATE TABLE IF NOT EXISTS rastreios_status (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(50) NOT NULL,
                cidade VARCHAR(255),
                status_atual VARCHAR(255),
                titulo VARCHAR(255),
                subtitulo VARCHAR(255),
                data DATETIME DEFAULT CURRENT_TIMESTAMP,
                cor VARCHAR(20),
                taxa_valor DECIMAL(10,2),
                taxa_pix TEXT,
                data_entrega_prevista VARCHAR(100),
                prioridade INT DEFAULT 0,
                tipo_entrega ENUM('NORMAL', 'EXPRESS') DEFAULT 'NORMAL',
                taxa_paga BOOLEAN DEFAULT FALSE
            )
        `);

        res.json({ success: true, message: 'Tabelas verificadas/criadas com sucesso.' });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// ===== ENDPOINTS QUE O FRONTEND ESPERA =====

// /api/orders — Retorna rastreios como "pedidos"
router.get('/orders', async (req, res) => {
    const db = getDB();
    try {
        if (!db) return res.json([]);
        const [rows] = await db.query('SELECT * FROM rastreios_status ORDER BY data DESC');
        res.json(rows);
    } catch (error) {
        res.json([]);
    }
});

// /api/clients — Retorna lista de clientes (por enquanto baseado em rastreios únicos)
router.get('/clients', async (req, res) => {
    const db = getDB();
    try {
        if (!db) return res.json([]);
        const [rows] = await db.query('SELECT DISTINCT codigo, cidade, MAX(data) as ultima_data FROM rastreios_status GROUP BY codigo, cidade ORDER BY ultima_data DESC');

        // Mapear para o formato esperado pelo frontend em Clients.tsx
        const clients = rows.map((r, index) => ({
            id: index + 1,
            codigo: r.codigo,
            nome: `Cliente (${r.codigo})`,
            email: null,
            telefone: 'Não informado',
            cidade: r.cidade || 'Não informada',
            total_indicacoes: 0,
            total_compras: 1
        }));

        res.json(clients);
    } catch (error) {
        res.json([]);
    }
});

// /api/admin/pedidos-pendentes — Pedidos pendentes de aprovação
router.get('/pedidos-pendentes', async (req, res) => {
    const db = getDB();
    try {
        if (!db) return res.json([]);
        const [rows] = await db.query("SELECT * FROM pedidos WHERE status = 'pendente' ORDER BY data_pedido DESC");
        res.json(rows);
    } catch (error) {
        res.json([]);
    }
});

// Aprovar pedido
router.post('/pedidos-pendentes/:id/aprovar', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('DB não conectado');
        const { id } = req.params;
        const { codigo_rastreio } = req.body;
        if (!codigo_rastreio) return res.status(400).json({ error: 'Código de rastreio é obrigatório' });

        // Buscar dados do pedido antes de aprovar
        const [rows] = await db.query("SELECT * FROM pedidos WHERE id = ?", [id]);
        if (rows.length === 0) return res.status(404).json({ error: 'Pedido não encontrado' });
        const pedido = rows[0];

        await db.query("UPDATE pedidos SET status = 'aprovado', codigo_rastreio = ? WHERE id = ?", [codigo_rastreio, id]);
        res.json({ success: true, message: 'Pedido aprovado!' });

        // Enviar WhatsApp ao cliente com código e link de rastreamento
        try {
            const bot = global._bot;
            const phone = String(pedido.telefone).replace(/\D/g, '');
            if (bot && bot.isReady && bot.sendWhatsAppMessage && phone.length >= 10) {
                const primeiroNome = (pedido.nome || 'Cliente').split(' ')[0];
                const siteUrl = process.env.SITE_URL || 'https://loggiexpress.site';
                const linkRastreio = `${siteUrl}/#/rastreio/${codigo_rastreio}`;

                const msg =
                    `Olá, *${primeiroNome}*! 🎉\n\n` +
                    `Ótimas notícias! Seu pedido foi *aprovado* e já está em processamento.\n\n` +
                    `━━━━━━━━━━━━━━━━━━\n` +
                    `📦 *CÓDIGO DE RASTREIO*\n` +
                    `━━━━━━━━━━━━━━━━━━\n` +
                    `*${codigo_rastreio}*\n\n` +
                    `🔍 *Acompanhe sua entrega em tempo real:*\n` +
                    `${linkRastreio}\n\n` +
                    `━━━━━━━━━━━━━━━━━━\n` +
                    `📍 *Endereço de entrega:*\n` +
                    `${pedido.rua}, ${pedido.numero}${pedido.complemento ? ' - ' + pedido.complemento : ''}\n` +
                    `${pedido.bairro}, ${pedido.cidade}/${pedido.estado}\n\n` +
                    `Qualquer dúvida, fale com nosso atendimento:\n` +
                    `📲 *WhatsApp: (51) 99614-8568*\n\n` +
                    `_Loggi — Rastreamento Inteligente_ 🚚`;

                await bot.sendWhatsAppMessage(phone, msg);
                console.log(`[APROVAR WA] Mensagem enviada para ${phone} (Pedido #${id}, código: ${codigo_rastreio})`);
            }
        } catch (waErr) {
            console.error('[APROVAR WA ERROR]', waErr.message);
        }
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Rejeitar pedido
router.post('/pedidos-pendentes/:id/rejeitar', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('DB não conectado');
        const { id } = req.params;
        await db.query("UPDATE pedidos SET status = 'rejeitado' WHERE id = ?", [id]);
        res.json({ success: true, message: 'Pedido rejeitado.' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Cobrar via WhatsApp
router.post('/pedidos-pendentes/:id/cobrar', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('DB não conectado');
        const { id } = req.params;
        const [rows] = await db.query("SELECT * FROM pedidos WHERE id = ?", [id]);
        if (rows.length === 0) return res.status(404).json({ success: false, message: 'Pedido não encontrado' });
        const pedido = rows[0];

        const phone = String(pedido.telefone).replace(/\D/g, '');
        const primeiroNome = (pedido.nome || 'Cliente').split(' ')[0];
        const msg =
            `Olá, *${primeiroNome}*! 📦

` +
            `Vimos que você fez um pedido conosco e ainda está aguardando aprovação.

` +
            `Nossa equipe está analisando tudo com atenção e em breve você receberá o código de rastreio. 🚚

` +
            `Qualquer dúvida, é só falar:
` +
            `📲 *WhatsApp: (51) 99614-8568*

` +
            `_Loggi — Rastreamento Inteligente_ 🚚`;

        // Enviar via bot
        const bot = global._bot;
        if (!bot || !bot.isReady || !bot.sendWhatsAppMessage) {
            return res.json({ success: false, message: 'Bot desconectado. Acesse Bot WhatsApp no menu e conecte o número primeiro.' });
        }
        if (phone.length < 10) {
            return res.json({ success: false, message: 'Número de telefone inválido para este pedido.' });
        }
        await bot.sendWhatsAppMessage(phone, msg);
        console.log(`[COBRAR WA] Mensagem enviada para ${phone} (Pedido #${id})`);
        res.json({ success: true, message: `✅ Mensagem enviada para ${pedido.nome}!` });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// /api/admin/whatsapp-templates — Templates de mensagens WhatsApp
router.get('/whatsapp-templates', async (req, res) => {
    try {
        res.json([
            { id: 1, nome: 'Objeto Postado', mensagem: 'Olá! Seu pedido foi postado com o código {codigo}. Acompanhe em: {link}', ativo: true },
            { id: 2, nome: 'Em Trânsito', mensagem: 'Atualização: Seu pedido {codigo} está em trânsito para {cidade}.', ativo: true },
            { id: 3, nome: 'Saiu para Entrega', mensagem: '🚚 Seu pedido {codigo} saiu para entrega! Fique atento.', ativo: true },
            { id: 4, nome: 'Entregue', mensagem: '✅ Pedido {codigo} entregue com sucesso! Obrigado pela confiança.', ativo: true },
            { id: 5, nome: 'Taxa Pendente', mensagem: 'Atenção: Seu pedido {codigo} tem uma taxa de R${taxa} pendente. Pague via PIX para liberar.', ativo: true }
        ]);
    } catch (error) {
        res.json([]);
    }
});

// /api/admin/whatsapp-templates — Salvar templates
router.put('/whatsapp-templates', async (req, res) => {
    try {
        // Aceita o save e retorna sucesso (templates ficam no frontend por enquanto)
        res.json({ success: true, message: 'Templates salvos com sucesso.' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Criar novo pedido (chamado pelo frontend React em /api/pedidos)
router.post('/pedidos', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { nome, cpf, telefone, email, cep, estado, cidade, bairro, rua, numero, complemento, observacoes } = req.body;

        // Validação básica
        if (!nome || !cpf || !telefone || !cep || !estado || !cidade || !bairro || !rua || !numero) {
            return res.status(400).json({ success: false, message: 'Dados obrigatórios faltando' });
        }

        const [result] = await db.query(
            `INSERT INTO pedidos 
            (nome, cpf, telefone, email, cep, estado, cidade, bairro, rua, numero, complemento, observacoes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')`,
            [nome, cpf, telefone, email, cep, estado, cidade, bairro, rua, numero, complemento, observacoes]
        );

        res.json({ success: true, message: 'Pedido enviado com sucesso!', id: result.insertId });

        // Auto-enviar mensagem WhatsApp ao cliente após salvar pedido
        try {
            const bot = global._bot;
            const phone = String(telefone).replace(/\D/g, '');
            if (bot && bot.isReady && bot.sendWhatsAppMessage && phone.length >= 10) {
                const primeiroNome = nome.split(' ')[0];
                const enderecoFormatado = `${rua}, ${numero}${complemento ? ' - ' + complemento : ''} — ${bairro}, ${cidade}/${estado}`;
                const msg =
                    `Olá, *${primeiroNome}*! 👋\n\n` +
                    `Recebemos seu pedido com sucesso e nossa equipe já está analisando tudo com atenção.\n\n` +
                    `━━━━━━━━━━━━━━━━━━\n` +
                    `📦 *RESUMO DO PEDIDO #${result.insertId}*\n` +
                    `━━━━━━━━━━━━━━━━━━\n` +
                    `👤 *Nome:* ${nome}\n` +
                    `📍 *Endereço de entrega:*\n${enderecoFormatado}${observacoes ? '\n\n📝 *Obs:* ' + observacoes : ''}\n` +
                    `━━━━━━━━━━━━━━━━━━\n\n` +
                    `⏱️ *Próximos passos:*\n` +
                    `1️⃣ Nossa equipe confirmará seu pedido em breve\n` +
                    `2️⃣ Você receberá o código de rastreio aqui\n` +
                    `3️⃣ Acompanhe cada etapa da entrega em tempo real\n\n` +
                    `Qualquer dúvida, fale direto com nosso atendimento:\n` +
                    `📲 *WhatsApp:* (51) 99614-8568\n\n` +
                    `Estamos aqui para ajudar! 😊\n\n` +
                    `_Loggi — Rastreamento Inteligente_ 🚚`;

                await bot.sendWhatsAppMessage(phone, msg);
                console.log(`[PEDIDO WA] Mensagem enviada para ${phone} (Pedido #${result.insertId})`);
            }
        } catch (waErr) {
            console.error('[PEDIDO WA ERROR]', waErr.message);
        }
    } catch (error) {
        console.error('[ERRO CRIAR PEDIDO]', error.message);
        res.status(500).json({ success: false, message: 'Erro interno ao processar pedido' });
    }
});

module.exports = router;

