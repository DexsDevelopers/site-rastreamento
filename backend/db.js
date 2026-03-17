const mysql = require('mysql2/promise');
const dotenv = require('dotenv');
const path = require('path');

dotenv.config({ path: path.join(__dirname, '.env') });

let pool;

async function runMigrations() {
    if (!pool) return;
    try {
        console.log('--- EXECUTANDO MIGRAÇÕES ---');

        // Criar tabela de rastreios se não existir
        await pool.query(`
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
                taxa_paga BOOLEAN DEFAULT FALSE,
                codigo_indicador VARCHAR(50)
            )
        `);

        // Tabela de usuários para o Admin
        await pool.query(`
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
        await pool.query(`
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
    const hosts = [process.env.DB_HOST || 'localhost', '127.0.0.1'];
    let lastError;

    for (const host of hosts) {
        try {
            console.log(`Tentando conexão pool: ${host} | User: ${process.env.DB_USER} | DB: ${process.env.DB_NAME}`);

            // createPool mantém conexões vivas e reconecta automaticamente
            pool = mysql.createPool({
                host: host,
                user: process.env.DB_USER,
                password: process.env.DB_PASSWORD,
                database: process.env.DB_NAME,
                timezone: 'Z',
                waitForConnections: true,
                connectionLimit: 10,
                queueLimit: 0,
                enableKeepAlive: true,
                keepAliveInitialDelay: 10000
            });

            // Testar se o pool funciona com uma query simples
            await pool.query('SELECT 1');

            console.log(`✅ Pool de banco de dados conectado via ${host}!`);
            await runMigrations();
            return pool;
        } catch (error) {
            console.warn(`⚠️ Falha ao conectar pool em ${host}:`, error.message);
            lastError = error;
            pool = null;
        }
    }

    console.error('❌ Erro fatal ao conectar ao banco em todos os hosts:', lastError?.message);
    // Tenta reconectar em 10 segundos se falhar completamente
    setTimeout(connectDB, 10000);
}

module.exports = {
    connectDB,
    getDB: () => pool
};
