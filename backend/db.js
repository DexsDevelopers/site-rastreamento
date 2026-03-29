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

        // --- VERIFICAR E ADICIONAR COLUNAS FALTANTES ---
        const [columns] = await pool.query('SHOW COLUMNS FROM rastreios_status');
        const columnNames = columns.map(c => c.Field);

        const upgrades = [
            { name: 'taxa_valor', type: 'DECIMAL(10,2)' },
            { name: 'taxa_pix', type: 'TEXT' },
            { name: 'tipo_entrega', type: "ENUM('NORMAL', 'EXPRESS') DEFAULT 'NORMAL'" },
            { name: 'taxa_paga', type: 'BOOLEAN DEFAULT FALSE' },
            { name: 'data_entrega_prevista', type: 'VARCHAR(100)' },
            { name: 'prioridade', type: 'INT DEFAULT 0' },
            { name: 'codigo_indicador', type: 'VARCHAR(50)' },
            { name: 'cliente_nome', type: 'VARCHAR(255)' },
            { name: 'cliente_whatsapp', type: 'VARCHAR(50)' }
        ];

        for (const col of upgrades) {
            if (!columnNames.includes(col.name)) {
                console.log(`[DB UPGRADE] Adicionando coluna faltante: ${col.name}`);
                await pool.query(`ALTER TABLE rastreios_status ADD COLUMN ${col.name} ${col.type}`);
            }
        }

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

        // Verificar coluna whatsapp na tabela users
        const [userCols] = await pool.query('SHOW COLUMNS FROM users');
        if (!userCols.map(c => c.Field).includes('whatsapp')) {
            await pool.query('ALTER TABLE users ADD COLUMN whatsapp VARCHAR(50)');
        }

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

        // Tabela de pedidos pendentes
        await pool.query(`
            CREATE TABLE IF NOT EXISTS pedidos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255),
                email VARCHAR(255),
                telefone VARCHAR(50),
                cpf VARCHAR(20),
                cep VARCHAR(10),
                rua VARCHAR(255),
                numero VARCHAR(20),
                bairro VARCHAR(255),
                cidade VARCHAR(255),
                estado VARCHAR(5),
                complemento VARCHAR(255),
                observacoes TEXT,
                status VARCHAR(50) DEFAULT 'pendente',
                codigo_rastreio VARCHAR(50),
                data_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        `);

        // Verificar e adicionar colunas faltantes na tabela pedidos
        const [pedidoCols] = await pool.query('SHOW COLUMNS FROM pedidos');
        const pedidoColNames = pedidoCols.map(c => c.Field);
        if (!pedidoColNames.includes('complemento')) {
            console.log('[DB UPGRADE] Adicionando coluna faltante em pedidos: complemento');
            await pool.query('ALTER TABLE pedidos ADD COLUMN complemento VARCHAR(255)');
        }
        if (!pedidoColNames.includes('observacoes')) {
            console.log('[DB UPGRADE] Adicionando coluna faltante em pedidos: observacoes');
            await pool.query('ALTER TABLE pedidos ADD COLUMN observacoes TEXT');
        }

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
