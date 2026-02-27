const express = require('express');
const cors = require('cors');
const dotenv = require('dotenv');
const path = require('path');

// Carrega .env da raiz ou da pasta backend
dotenv.config();
dotenv.config({ path: path.join(__dirname, '.env') });

const app = express();
const PORT = process.env.PORT || 3000;

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Rota de Teste de Vida Urgente
app.get('/api/test', (req, res) => {
    res.json({
        status: 'online',
        time: new Date().toISOString(),
        env: {
            db_host: process.env.DB_HOST ? 'Configurado' : 'Não encontrado',
            node_env: process.env.NODE_ENV
        }
    });
});

// Tenta carregar o DB de forma segura
let db;
try {
    db = require('./db');
    console.log('✅ Módulo de banco de dados carregado');
} catch (e) {
    console.error('⚠️ Falha ao carregar módulo de banco:', e.message);
}

// Rota de Teste de Banco
app.get('/api/db-check', async (req, res) => {
    if (!db) return res.status(500).json({ status: 'error', message: 'Módulo DB não carregado' });
    try {
        const [rows] = await db.query('SELECT 1 + 1 AS solution');
        res.json({ status: 'success', database: 'conectado', result: rows[0].solution });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

// Servir o Frontend
const distPath = path.join(__dirname, '..', 'webapp', 'dist');
app.use(express.static(distPath));

app.get('*', (req, res) => {
    const indexPath = path.join(distPath, 'index.html');
    res.sendFile(indexPath, (err) => {
        if (err) {
            res.status(500).send(`
                <h1>Erro de Inicialização</h1>
                <p>O servidor Node está rodando, mas não encontrou o site (webapp/dist).</p>
                <p>Caminho tentado: ${indexPath}</p>
                <hr>
                <p>Verifique se você rodou o comando de build ou se a pasta webapp/dist existe no FTP.</p>
            `);
        }
    });
});

app.listen(PORT, () => {
    console.log(`🚀 Servidor rodando na porta ${PORT}`);
});


