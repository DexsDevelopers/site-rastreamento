const express = require('express');
const router = express.Router();
const { getDB } = require('../db');

// Health Check do Banco de Dados
router.get('/db-health', async (req, res) => {
    const db = getDB();
    try {
        if (!db) {
            return res.status(200).json({
                connected: false,
                error: 'Instância do banco não iniciada. Verifique os logs do servidor.'
            });
        }

        // Testar conexão
        await db.query('SELECT 1');

        // Verificar tabelas
        const [tables] = await db.query('SHOW TABLES');
        const dbName = process.env.DB_NAME;
        const tableNames = tables.map(t => Object.values(t)[0]);

        const requiredTables = ['rastreios_status', 'users', 'entregadores'];
        const tableStatus = await Promise.all(requiredTables.map(async name => {
            const exists = tableNames.includes(name);
            let count = 0;
            if (exists) {
                const [rows] = await db.query(`SELECT COUNT(*) as count FROM \`${name}\``);
                count = rows[0].count;
            }
            return { name, exists, count };
        }));

        res.json({
            connected: true,
            database: dbName,
            tables: tableStatus
        });
    } catch (error) {
        res.status(200).json({
            connected: false,
            error: error.message
        });
    }
});

// Setup Initial Tables (se o sistema de migração automática falhar)
router.post('/db-setup', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('DB not connected');

        // Simplesmente aciona o runMigrations que já existe no db.js ou recria aqui
        // No db.js as migrações já rodam no connectDB, mas vamos reforçar aqui se necessário.

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

        res.json({ success: true, message: 'Tabelas verificadas/criadas com sucesso.' });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

module.exports = router;
