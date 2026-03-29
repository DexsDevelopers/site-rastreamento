const express = require('express');
const cors = require('cors');
const dotenv = require('dotenv');
const path = require('path');
const { connectDB } = require('./db');

dotenv.config();
dotenv.config({ path: path.join(__dirname, '.env') });

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Conexão com Banco de Dados
connectDB();

// Rotas Modulares
const authRoutes = require('./routes/auth');
const trackingRoutes = require('./routes/tracking');
const driverRoutes = require('./routes/drivers');
const pixRoutes = require('./routes/pix');
const adminRoutes = require('./routes/admin');

app.use('/api/auth', authRoutes);
app.use('/api/admin', adminRoutes); // Status e Setup
app.use('/api/admin', trackingRoutes); // Rastreios (Admin) e Stats
app.use('/api', trackingRoutes); // Rastreio Público e Consultas
app.use('/api', adminRoutes); // /api/orders, /api/clients
app.use('/api/drivers', driverRoutes);
app.use('/api/pix', pixRoutes);

// Health Check
app.get('/api/health', (req, res) => {
    res.json({ status: 'ok', time: new Date() });
});

// Fallback para API inexistente
app.use('/api/*', (req, res) => {
    res.status(404).json({ error: `Rota de API não encontrada: ${req.originalUrl}` });
});

// Arquivos Estáticos (SPA)
app.use(express.static(path.join(__dirname, '../webapp/dist')));
app.get('*', (req, res) => {
    res.sendFile(path.join(__dirname, '../webapp/dist/index.html'));
});

// Disponibilizar para o Bridge
global._expressApp = app;

// Integrar WhatsApp Bot no mesmo processo (Hostinger só permite 1 processo)
global._bot = null;
async function initBot() {
    try {
        const { getDB } = require('./db');
        // Aguardar DB estar pronto
        let db = getDB();
        let retries = 0;
        while (!db && retries < 15) {
            await new Promise(r => setTimeout(r, 2000));
            db = getDB();
            retries++;
        }

        console.log('[BOT] Carregando módulo WhatsApp Bot...');
        const bot = await import('../whatsapp-bot/index.js');
        global._bot = bot;
        console.log('[BOT] Módulo carregado. Iniciando conexão...');
        await bot.initWhatsAppBot(null, db);
        console.log('✅ Bot WhatsApp integrado no processo principal!');
    } catch (err) {
        console.error('❌ Erro ao integrar bot:', err.message);
    }
}
// Delay de 5s para não sobrecarregar na inicialização
setTimeout(initBot, 5000);

// [E] Relatório diário automático para admin às 08:00
function agendarRelatorioDiario() {
    const agora = new Date();
    const proximasOito = new Date();
    proximasOito.setHours(8, 0, 0, 0);
    if (proximasOito <= agora) proximasOito.setDate(proximasOito.getDate() + 1);
    const msAteOito = proximasOito.getTime() - agora.getTime();

    setTimeout(async () => {
        await enviarRelatorioDiario();
        setInterval(enviarRelatorioDiario, 24 * 60 * 60 * 1000); // repetir todo dia
    }, msAteOito);

    console.log(`[RELATORIO] Próximo relatório agendado em ${Math.round(msAteOito / 60000)} minutos.`);
}

async function enviarRelatorioDiario() {
    try {
        const bot = global._bot;
        const adminPhone = process.env.ADMIN_WHATSAPP;
        if (!bot || !bot.isReady || !bot.sendWhatsAppMessage || !adminPhone) return;

        const { getDB } = require('./db');
        const db = getDB();
        if (!db) return;

        const [[stats]] = await db.query(`
            SELECT
                COUNT(DISTINCT codigo) AS total,
                SUM(CASE WHEN taxa_valor > 0 AND taxa_paga = 0 THEN 1 ELSE 0 END) AS retidos,
                SUM(CASE WHEN taxa_paga = 1 THEN 1 ELSE 0 END) AS pagos,
                SUM(CASE WHEN status_atual LIKE '%Entregue%' THEN 1 ELSE 0 END) AS entregues,
                SUM(CASE WHEN taxa_valor > 0 AND taxa_paga = 0 THEN taxa_valor ELSE 0 END) AS receita_pendente
            FROM (
                SELECT codigo, MAX(id) as max_id FROM rastreios_status GROUP BY codigo
            ) t1
            JOIN rastreios_status t2 ON t1.max_id = t2.id
        `);

        const hoje = new Date().toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
        const phone = String(adminPhone).replace(/\D/g, '');

        await bot.sendWhatsAppMessage(phone,
            `📊 *Relatório Diário — Loggi*\n` +
            `_${hoje}_\n\n` +
            `📦 *Total de rastreios:* ${stats.total || 0}\n` +
            `⚠️ *Objetos retidos:* ${stats.retidos || 0}\n` +
            `✅ *Pagamentos confirmados:* ${stats.pagos || 0}\n` +
            `🏠 *Entregues:* ${stats.entregues || 0}\n\n` +
            `💰 *Receita pendente:* R$ ${Number(stats.receita_pendente || 0).toFixed(2)}\n\n` +
            `_Gerado automaticamente às 08:00_`
        );
        console.log('[RELATORIO] Relatório diário enviado para admin:', phone);
    } catch (err) {
        console.error('[RELATORIO ERROR]', err.message);
    }
}

// Iniciar agendamento do relatório após 10s (bot precisa estar pronto)
setTimeout(agendarRelatorioDiario, 10000);

// [SCHEDULER] Processar automações de todos os códigos ativos a cada 1 hora
async function rodarAutomacoesTodos() {
    try {
        const { getDB } = require('./db');
        const db = getDB();
        if (!db) return;

        const { processAutomation } = require('./routes/tracking');
        const [rows] = await db.query(
            `SELECT DISTINCT codigo FROM rastreios_status
             WHERE taxa_paga = 0
             AND codigo NOT IN (
                 SELECT DISTINCT codigo FROM rastreios_status
                 WHERE status_atual LIKE '%Entregue%'
             )`
        );

        console.log(`[SCHEDULER] Processando automação para ${rows.length} código(s) ativos...`);
        for (const row of rows) {
            try {
                await processAutomation(row.codigo, db);
            } catch (e) {
                console.error(`[SCHEDULER] Erro em ${row.codigo}:`, e.message);
            }
        }
        console.log('[SCHEDULER] Automações concluídas.');
    } catch (err) {
        console.error('[SCHEDULER ERROR]', err.message);
    }
}

// Primeira execução após 2 minutos (aguardar DB e bot), depois a cada 1 hora
setTimeout(() => {
    rodarAutomacoesTodos();
    setInterval(rodarAutomacoesTodos, 60 * 60 * 1000);
}, 2 * 60 * 1000);

if (process.env.NODE_ENV !== 'production') {
    app.listen(PORT, () => {
        console.log(`Backend local rodando na porta ${PORT}`);
    });
}

module.exports = app;
