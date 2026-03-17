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

if (process.env.NODE_ENV !== 'production') {
    app.listen(PORT, () => {
        console.log(`Backend local rodando na porta ${PORT}`);
    });
}

module.exports = app;
