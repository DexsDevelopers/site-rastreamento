const express = require('express');
const router = express.Router();
const { getDB } = require('../db');

// ===== TRACKING API =====

// Listar todos (Admin)
router.get('/admin/rastreios', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [rows] = await db.query('SELECT * FROM rastreios_status ORDER BY data DESC');
        res.json(rows);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Detalhes (Admin)
router.get('/admin/rastreios/:codigo/detalhes', async (req, res) => {
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
router.post('/admin/rastreios', async (req, res) => {
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
router.put('/admin/rastreios/:codigo', async (req, res) => {
    const db = getDB();
    const { codigo } = req.params;
    const { cidade, taxa_valor, taxa_pix, tipo_entrega, etapas } = req.body;
    try {
        if (!db) throw new Error('Banco de dados não disponível');

        // Atualizar campos básicos em todas as entradas desse código
        await db.query(
            'UPDATE rastreios_status SET cidade = ?, taxa_valor = ?, taxa_pix = ?, tipo_entrega = ? WHERE codigo = ?',
            [cidade, taxa_valor, taxa_pix, tipo_entrega, codigo]
        );

        // Se etapas foi enviado, sincronizar (simplificando: deletar antigas e reinserir preservando a data se possível ou apenas as que mudaram)
        // Para este projeto, o usuário quer "gerenciar etapas" clicando nos botões.
        // Vamos apenas garantir que se uma etapa não existe, ela seja criada.
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
            // Opcional: remover etapas que foram desmarcadas (exceto a inicial se necessário)
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
router.delete('/admin/rastreios/:codigo', async (req, res) => {
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
router.get('/admin/stats', async (req, res) => {
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
router.post(['/publico', '/consulta'], async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        let { codigo } = req.body;
        if (!codigo) return res.status(400).json({ success: false, message: 'Código é obrigatório' });

        codigo = codigo.toUpperCase().trim();
        const [rows] = await db.query(
            'SELECT * FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data ASC',
            [codigo]
        );

        if (rows.length === 0) {
            return res.status(404).json({ success: false, message: 'Código de rastreio não encontrado.' });
        }

        let packageData = rows[0];
        const lastStatus = rows[rows.length - 1];

        // --- LÓGICA DE AUTOMAÇÃO DE ETAPAS ---
        const firstStageDate = new Date(rows[0].data);
        const now = new Date();
        const diffDays = Math.floor((now - firstStageDate) / (1000 * 60 * 60 * 24));
        const city = packageData.cidade;

        const automationStages = [
            { day: 1, status: '🚚 Em trânsito', title: '🚚 Em trânsito', sub: 'Seu objeto está sendo transportado entre unidades.' },
            { day: 2, status: '🏢 No centro de distribuição', title: '🏢 No centro de distribuição', sub: 'O objeto chegou na unidade de tratamento da sua região.' }
        ];

        // Adicionar etapas automáticas de trânsito
        for (const stage of automationStages) {
            if (diffDays >= stage.day && !rows.some(r => r.status_atual === stage.status)) {
                await db.query(
                    'INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, tipo_entrega, taxa_paga) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [codigo, city, stage.status, stage.title, stage.sub, packageData.tipo_entrega, packageData.taxa_paga]
                );
            }
        }

        // Se for NORMAL e passaram 3 dias e não está pago -> Taxa no Dia 4 (ou 3.5...)
        // Vamos usar 3 dias para aparecer a taxa logo.
        if (packageData.tipo_entrega === 'NORMAL' && diffDays >= 3 && !packageData.taxa_paga) {
            const taxaStatus = '⚠️ Objeto retido - Aguardando regularização fiscal';
            if (!rows.some(r => r.status_atual === taxaStatus)) {
                await db.query(
                    'INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, taxa_valor, tipo_entrega) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [codigo, city, taxaStatus, taxaStatus, 'Seu objeto está sujeito a retenção por irregularidade fiscal. Regularize para liberar.', 29.90, 'NORMAL']
                );
            }
        }

        // Recarregar rows se algo foi inserido
        const [updatedRows] = await db.query('SELECT * FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data ASC', [codigo]);
        const currentRows = updatedRows;
        lastStatus = currentRows[currentRows.length - 1];
        packageData = currentRows[0];

        const taxaRow = currentRows.find(r => r.taxa_valor && r.taxa_valor !== '0' && r.taxa_valor !== '0.00') || lastStatus;

        res.json({
            success: true,
            codigo: lastStatus.codigo,
            status_atual: lastStatus.status_atual,
            etapas: currentRows.map(r => ({
                titulo: r.titulo || r.status_atual,
                subtitulo: r.subtitulo || '',
                data: r.data,
                status_atual: r.status_atual
            })),
            taxa_valor: packageData.taxa_paga ? null : taxaRow.taxa_valor,
            taxa_pix: taxaRow.taxa_pix,
            tipo_entrega: packageData.tipo_entrega,
            taxa_paga: packageData.taxa_paga
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

module.exports = router;
