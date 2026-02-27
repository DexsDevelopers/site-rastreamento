const express = require('express');
const cors = require('cors');
const dotenv = require('dotenv');
const path = require('path');

// Carrega .env da raiz ou da pasta backend
dotenv.config();
dotenv.config({ path: path.join(__dirname, '.env') });

const app = express();
const PORT = process.env.PORT || 3000;

// Configuração do Banco de Dados
const mysql = require('mysql2/promise');

let db;
async function connectDB() {
    try {
        db = await mysql.createPool({
            host: process.env.DB_HOST,
            user: process.env.DB_USER,
            password: process.env.DB_PASSWORD,
            database: process.env.DB_NAME,
            waitForConnections: true,
            connectionLimit: 10,
            queueLimit: 0
        });
        console.log('✅ Banco de dados conectado com sucesso!');
    } catch (err) {
        console.error('❌ Erro ao conectar no banco de dados:', err.message);
    }
}
connectDB();

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ===== API ENDPOINTS =====

// 1. Pedidos (Rastreios)
app.get('/api/orders', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [rows] = await db.query('SELECT * FROM rastreios_status ORDER BY data DESC LIMIT 100');
        res.json(rows);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Detalhes de um pedido específico
app.get('/api/orders/:codigo', async (req, res) => {
    const { codigo } = req.params;
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [rows] = await db.query('SELECT * FROM rastreios_status WHERE codigo = ?', [codigo]);
        if (rows.length === 0) return res.status(404).json({ error: 'Pedido não encontrado' });
        res.json(rows[0]);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 2. Clientes
app.get('/api/clients', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [rows] = await db.query('SELECT * FROM clientes ORDER BY data_cadastro DESC');
        res.json(rows);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 3. Entregadores (Simulando ou criando do banco se existir)
app.get('/api/drivers', async (req, res) => {
    // Por enquanto retornamos uma lista de teste se a tabela não existir
    res.json([
        { id: 1, nome: 'Carlos Motoboy', status: 'disponivel', veiculo: 'Moto' },
        { id: 2, nome: 'Fernanda Loggi', status: 'em_rota', veiculo: 'Carro' }
    ]);
});

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
const distPath = path.join(__dirname, 'dist');
app.use(express.static(distPath));

app.get('*', (req, res) => {
    const indexPath = path.join(distPath, 'index.html');
    res.sendFile(indexPath, (err) => {
        if (err) {
            res.status(500).send(`
                <h1>Erro de Inicialização v2</h1>
                <p>O servidor está ONLINE, mas não achou o arquivo index.html no novo local.</p>
                <p>Caminho tentado: ${indexPath}</p>
                <hr>
                <p>Por favor, verifique se a pasta 'backend/dist' existe no seu Gerenciador de Arquivos.</p>
            `);
        }
    });
});


app.listen(PORT, () => {
    console.log(`🚀 Servidor rodando na porta ${PORT}`);
});


