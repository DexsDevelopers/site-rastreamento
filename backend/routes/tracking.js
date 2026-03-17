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

        // Formato esperado pelo frontend
        const latest = rows[0];
        res.json({
            codigo: latest.codigo,
            cidade: latest.cidade,
            data_inicial: latest.data,
            taxa_valor: latest.taxa_valor,
            taxa_pix: latest.taxa_pix,
            etapas: rows.map(r => r.status_atual),
            cliente_nome: null, // Campos não presentes na tabela atual
            cliente_whatsapp: null,
            cliente_notificar: false
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Criar/Atualizar
router.post('/admin/rastreios', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { codigo, cidade, status_atual, taxa_valor, taxa_pix } = req.body;

        await db.query(
            'INSERT INTO rastreios_status (codigo, cidade, status_atual, taxa_valor, taxa_pix) VALUES (?, ?, ?, ?, ?)',
            [codigo, cidade, status_atual, taxa_valor, taxa_pix]
        );
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

// Public Search (Home & Tracking)
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

        const lastStatus = rows[rows.length - 1];
        const taxaRow = rows.find(r => r.taxa_valor && r.taxa_valor !== '0' && r.taxa_valor !== '0.00') || lastStatus;

        res.json({
            success: true,
            codigo: lastStatus.codigo,
            status_atual: lastStatus.status_atual,
            etapas: rows.map(r => ({
                titulo: r.titulo || r.status_atual,
                subtitulo: r.subtitulo || '',
                data: r.data,
                status_atual: r.status_atual
            })),
            taxa_valor: taxaRow.taxa_valor,
            taxa_pix: taxaRow.taxa_pix
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

module.exports = router;
