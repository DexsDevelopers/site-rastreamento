const express = require('express');
const router = express.Router();
const { getDB } = require('../db');
const QRCodeImg = require('qrcode');

// ===== BOT WHATSAPP (acesso direto ao processo integrado) =====

// Status do bot
router.get('/bot/status', (req, res) => {
    const bot = global._bot;
    if (!bot) {
        return res.json({ success: false, status: { connected: false, message: 'Bot ainda não inicializado. Aguarde ~5s após reiniciar o servidor.' } });
    }
    const isReady = bot.isReady === true;
    const hasQR = !!bot.lastQR;
    res.json({
        success: true,
        status: {
            connected: isReady,
            uptime: null,
            number: null,
            pushname: null,
            platform: null,
            message: isReady ? 'Conectado' : (hasQR ? 'Aguardando scan do QR Code' : 'Conectando ao WhatsApp...')
        }
    });
});

// QR Code do bot
router.get('/bot/qr', async (req, res) => {
    const bot = global._bot;
    if (!bot || !bot.lastQR) {
        return res.json({ success: false, message: bot ? 'QR não disponível — bot já conectado ou inicializando' : 'Bot não inicializado' });
    }
    try {
        const dataUrl = await QRCodeImg.toDataURL(bot.lastQR, { scale: 8, margin: 1 });
        res.json({ success: true, qr: dataUrl });
    } catch (e) {
        res.json({ success: false, message: 'Erro ao gerar imagem do QR Code' });
    }
});

// Reiniciar/reconectar bot
router.post('/bot/restart', async (req, res) => {
    const bot = global._bot;
    if (!bot) {
        return res.json({ success: false, message: 'Bot não inicializado no servidor' });
    }
    try {
        if (bot.sock) {
            try { bot.sock.end(); } catch (e) {}
        }
        const { getDB } = require('../db');
        await bot.initWhatsAppBot(null, getDB());
        res.json({ success: true, message: 'Bot reiniciando — aguarde o novo QR Code' });
    } catch (err) {
        res.json({ success: false, message: 'Erro ao reiniciar: ' + err.message });
    }
});

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

// ===== ENDPOINTS QUE O FRONTEND ESPERA =====

// /api/orders — Retorna rastreios como "pedidos"
router.get('/orders', async (req, res) => {
    const db = getDB();
    try {
        if (!db) return res.json([]);
        const [rows] = await db.query('SELECT * FROM rastreios_status ORDER BY data DESC');
        res.json(rows);
    } catch (error) {
        res.json([]);
    }
});

// /api/clients — Retorna lista de clientes (por enquanto baseado em rastreios únicos)
router.get('/clients', async (req, res) => {
    const db = getDB();
    try {
        if (!db) return res.json([]);
        const [rows] = await db.query('SELECT DISTINCT codigo, cidade, MAX(data) as ultima_data FROM rastreios_status GROUP BY codigo, cidade ORDER BY ultima_data DESC');

        // Mapear para o formato esperado pelo frontend em Clients.tsx
        const clients = rows.map((r, index) => ({
            id: index + 1,
            codigo: r.codigo,
            nome: `Cliente (${r.codigo})`,
            email: null,
            telefone: 'Não informado',
            cidade: r.cidade || 'Não informada',
            total_indicacoes: 0,
            total_compras: 1
        }));

        res.json(clients);
    } catch (error) {
        res.json([]);
    }
});

// /api/admin/pedidos-pendentes — Pedidos pendentes de aprovação
router.get('/pedidos-pendentes', async (req, res) => {
    const db = getDB();
    try {
        if (!db) return res.json([]);
        const [rows] = await db.query("SELECT * FROM pedidos WHERE status = 'pendente' ORDER BY data_pedido DESC");
        res.json(rows);
    } catch (error) {
        res.json([]);
    }
});

// Aprovar pedido
router.post('/pedidos-pendentes/:id/aprovar', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('DB não conectado');
        const { id } = req.params;
        const { codigo_rastreio } = req.body;
        if (!codigo_rastreio) return res.status(400).json({ error: 'Código de rastreio é obrigatório' });

        await db.query("UPDATE pedidos SET status = 'aprovado', codigo_rastreio = ? WHERE id = ?", [codigo_rastreio, id]);
        res.json({ success: true, message: 'Pedido aprovado!' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Rejeitar pedido
router.post('/pedidos-pendentes/:id/rejeitar', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('DB não conectado');
        const { id } = req.params;
        await db.query("UPDATE pedidos SET status = 'rejeitado' WHERE id = ?", [id]);
        res.json({ success: true, message: 'Pedido rejeitado.' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Cobrar via WhatsApp
router.post('/pedidos-pendentes/:id/cobrar', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('DB não conectado');
        const { id } = req.params;
        const [rows] = await db.query("SELECT * FROM pedidos WHERE id = ?", [id]);
        if (rows.length === 0) return res.status(404).json({ success: false, message: 'Pedido não encontrado' });
        const pedido = rows[0];
        const link = `https://wa.me/55${pedido.telefone?.replace(/\D/g, '')}?text=${encodeURIComponent(`Olá ${pedido.nome}, seu pedido está pendente. Entre em contato para finalizar!`)}`;
        res.json({ success: true, message: 'Link de cobrança gerado!', link });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// /api/admin/whatsapp-templates — Templates de mensagens WhatsApp
router.get('/whatsapp-templates', async (req, res) => {
    try {
        res.json([
            { id: 1, nome: 'Objeto Postado', mensagem: 'Olá! Seu pedido foi postado com o código {codigo}. Acompanhe em: {link}', ativo: true },
            { id: 2, nome: 'Em Trânsito', mensagem: 'Atualização: Seu pedido {codigo} está em trânsito para {cidade}.', ativo: true },
            { id: 3, nome: 'Saiu para Entrega', mensagem: '🚚 Seu pedido {codigo} saiu para entrega! Fique atento.', ativo: true },
            { id: 4, nome: 'Entregue', mensagem: '✅ Pedido {codigo} entregue com sucesso! Obrigado pela confiança.', ativo: true },
            { id: 5, nome: 'Taxa Pendente', mensagem: 'Atenção: Seu pedido {codigo} tem uma taxa de R${taxa} pendente. Pague via PIX para liberar.', ativo: true }
        ]);
    } catch (error) {
        res.json([]);
    }
});

// /api/admin/whatsapp-templates — Salvar templates
router.put('/whatsapp-templates', async (req, res) => {
    try {
        // Aceita o save e retorna sucesso (templates ficam no frontend por enquanto)
        res.json({ success: true, message: 'Templates salvos com sucesso.' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Criar novo pedido (chamado pelo frontend React em /api/pedidos)
router.post('/pedidos', async (req, res) => {
    const db = getDB();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { nome, cpf, telefone, email, cep, estado, cidade, bairro, rua, numero, complemento, observacoes } = req.body;

        // Validação básica
        if (!nome || !cpf || !telefone || !cep || !estado || !cidade || !bairro || !rua || !numero) {
            return res.status(400).json({ success: false, message: 'Dados obrigatórios faltando' });
        }

        const [result] = await db.query(
            `INSERT INTO pedidos 
            (nome, cpf, telefone, email, cep, estado, cidade, bairro, rua, numero, complemento, observacoes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')`,
            [nome, cpf, telefone, email, cep, estado, cidade, bairro, rua, numero, complemento, observacoes]
        );

        res.json({ success: true, message: 'Pedido enviado com sucesso!', id: result.insertId });
    } catch (error) {
        console.error('[ERRO CRIAR PEDIDO]', error.message);
        res.status(500).json({ success: false, message: 'Erro interno ao processar pedido' });
    }
});

module.exports = router;

