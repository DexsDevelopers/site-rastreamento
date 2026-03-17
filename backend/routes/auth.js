const express = require('express');
const router = express.Router();
const { getDB } = require('../db');

// ===== AUTH API =====

router.post('/register', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { nome, email, senha, whatsapp } = req.body;

        if (!nome || !email || !senha) {
            return res.status(400).json({ success: false, message: 'Campos obrigatórios faltando' });
        }

        // Verificar se já existe
        const [rows] = await db.query('SELECT id FROM users WHERE email = ?', [email]);
        if (rows.length > 0) {
            return res.status(400).json({ success: false, message: 'Este e-mail já está cadastrado' });
        }

        await db.query(
            'INSERT INTO users (nome, email, senha, whatsapp) VALUES (?, ?, ?, ?)',
            [nome, email, senha, whatsapp || null]
        );

        res.json({ success: true, message: 'Cadastro realizado com sucesso!' });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message });
    }
});

router.post('/login', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { email, senha } = req.body;

        const [rows] = await db.query('SELECT * FROM users WHERE email = ? AND senha = ?', [email, senha]);

        if (rows.length === 0) {
            return res.status(401).json({ success: false, message: 'E-mail ou senha incorretos' });
        }

        const user = rows[0];
        delete user.senha;

        res.json({
            success: true,
            user
        });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message });
    }
});

module.exports = router;
