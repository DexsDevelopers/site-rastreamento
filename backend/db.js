const mysql = require('mysql2/promise');
const dotenv = require('dotenv');
const path = require('path');

dotenv.config({ path: path.join(__dirname, '.env') });

let db;

async function runMigrations() {
    if (!db) return;
    try {
        console.log('--- EXECUTANDO MIGRAÇÕES ---');

        // Criar tabela de rastreios se não existir
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

        // Tabela de usuários para o Admin
        await db.query(`
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255),
                email VARCHAR(255) UNIQUE,
                senha VARCHAR(255),
                whatsapp VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        `);

        // Tabela de entregadores
        await db.query(`
            CREATE TABLE IF NOT EXISTS entregadores (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255),
                telefone VARCHAR(50),
                veiculo VARCHAR(100),
                status VARCHAR(50) DEFAULT 'Ativo'
            )
        `);

        console.log('✅ Migrações concluídas.');
    } catch (err) {
        console.error('❌ Erro nas migrações:', err.message);
    }
}

async function connectDB() {
    try {
        console.log(`Conectando ao banco de dados: ${process.env.DB_HOST} | User: ${process.env.DB_USER} | DB: ${process.env.DB_NAME}`);
        db = await mysql.createConnection({
            host: process.env.DB_HOST,
            user: process.env.DB_USER,
            password: process.env.DB_PASSWORD,
            database: process.env.DB_NAME,
            timezone: 'Z'
        });

        console.log('✅ Banco de dados conectado!');
        await runMigrations();
        return db;
    } catch (error) {
        console.error('❌ Erro ao conectar ao banco:', error.message);
        // Tenta reconectar em 5 segundos se falhar
        setTimeout(connectDB, 5000);
    }
}

module.exports = {
    connectDB,
    getDB: () => db
};
