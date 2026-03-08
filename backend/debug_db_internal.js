require('dotenv').config({ path: './backend/.env' });
const db = require('./db');

async function listRecentOrders() {
    try {
        console.log("Variáveis de ambiente (process.env.DB_HOST):", process.env.DB_HOST);
        console.log("=== VERIFICANDO CONEXÃO E TABELA ===");
        const [tables] = await db.query("SHOW TABLES LIKE 'pedidos_pendentes'");
        if (tables.length === 0) {
            console.log("ERRO: Tabela 'pedidos_pendentes' não encontrada!");
            process.exit(1);
        }

        console.log("=== ÚLTIMOS 20 PEDIDOS NA TABELA pedidos_pendentes ===");
        const [rows] = await db.query("SELECT id, nome, status, data_pedido, telefone FROM pedidos_pendentes ORDER BY id DESC LIMIT 20");

        if (rows.length === 0) {
            console.log("Nenhum pedido encontrado.");
        } else {
            rows.forEach(r => {
                console.log(`ID: ${r.id} | Nome: ${r.nome} | Status: ${r.status} | Data: ${r.data_pedido} | Tel: ${r.telefone}`);
            });
        }

        const [[{ total }]] = await db.query("SELECT COUNT(*) as total FROM pedidos_pendentes");
        console.log(`\nTotal de pedidos na tabela: ${total}`);

        const [byStatus] = await db.query("SELECT status, COUNT(*) as count FROM pedidos_pendentes GROUP BY status");
        console.log("\nContagem por Status:");
        byStatus.forEach(s => console.log(`${s.status}: ${s.count}`));

        process.exit(0);
    } catch (error) {
        console.error("ERRO COMPLETO:", error);
        process.exit(1);
    }
}

listRecentOrders();
