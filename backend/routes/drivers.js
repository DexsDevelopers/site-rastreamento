const express = require('express');
const router = express.Router();
const { getDB } = require('../db');

// ===== DRIVERS API =====

router.get('/', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [rows] = await db.query('SELECT * FROM entregadores ORDER BY nome ASC');
        res.json(rows);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

router.post('/', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { nome, telefone, veiculo, status } = req.body;
        await db.query(
            'INSERT INTO entregadores (nome, telefone, veiculo, status) VALUES (?, ?, ?, ?)',
            [nome, telefone, veiculo, status || 'Ativo']
        );
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

router.delete('/:id', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        await db.query('DELETE FROM entregadores WHERE id = ?', [req.params.id]);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

module.exports = router;
