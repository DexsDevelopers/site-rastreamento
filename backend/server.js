const express = require('express');
const cors = require('cors');
const dotenv = require('dotenv');
const db = require('./db');

dotenv.config();

const app = express();
const PORT = process.env.PORT || 3000;

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Rota de Teste de Vida e Integração Hostinger
app.get('/api/health', (req, res) => {
    res.json({
        status: 'ok',
        message: 'API Node.js rodando perfeitamente! Pronto para a Hostinger.'
    });
});

// Exemplo de rota buscando do seu banco de dados atual
app.get('/api/config', async (req, res) => {
    try {
        const [rows] = await db.query('SELECT 1 + 1 AS solution');
        res.json({ status: 'success', database_connected: true, result: rows[0].solution });
    } catch (error) {
        console.error(error);
        res.status(500).json({ status: 'error', message: 'Erro ao conectar no banco' });
    }
});

// Iniciando o servidor
app.listen(PORT, () => {
    console.log(`🚀 Servidor Node.js (Backend Backend) rodando na porta ${PORT}`);
    console.log(`✅ Conexão com banco preparada para: ${process.env.DB_HOST}`);
});
