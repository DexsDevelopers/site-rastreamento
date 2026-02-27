const express = require('express');
const cors = require('cors');
const dotenv = require('dotenv');
const path = require('path');
const db = require('./db');

dotenv.config();

const app = express();
// Na Hostinger, a porta é passada automaticamente, mas forçamos 3000 caso não venha
const PORT = process.env.PORT || 3000;

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Rota de Teste Simples (Se isso abrir, o 503 some)
app.get('/api/test', (req, res) => {
    res.send('O Backend está ONLINE na Hostinger! 🚀');
});

// Servir o Frontend (Tenta caminhos diferentes por segurança)
const distPath = path.join(__dirname, '..', 'webapp', 'dist');
app.use(express.static(distPath));

// Rota coringa para o React
app.get('*', (req, res) => {
    res.sendFile(path.join(distPath, 'index.html'), (err) => {
        if (err) {
            res.status(500).send("O Frontend (webapp/dist) ainda não foi encontrado no servidor. Verifique se o build foi enviado.");
        }
    });
});

// Tratamento de erro global para o site não cair
process.on('uncaughtException', (err) => {
    console.error('Erro Crítico:', err);
});

app.listen(PORT, '0.0.0.0', () => {
    console.log(`🚀 Servidor rodando na porta ${PORT}`);
});

