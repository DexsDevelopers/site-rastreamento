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
app.use('/api/tracking', trackingRoutes);
app.use('/api/admin', adminRoutes); // Novas rotas de status/config
app.use('/api/admin/rastreios', trackingRoutes); // Alias para legibilidade admin
app.use('/api/drivers', driverRoutes);
app.use('/api/pix', pixRoutes);

// Health Check
app.get('/api/health', (req, res) => {
    res.json({ status: 'ok', time: new Date() });
});

// Arquivos Estáticos (SPA)
app.use(express.static(path.join(__dirname, '../dist')));
app.get('*', (req, res) => {
    res.sendFile(path.join(__dirname, '../dist/index.html'));
});

// Disponibilizar para o Bridge
global._expressApp = app;

if (process.env.NODE_ENV !== 'production') {
    app.listen(PORT, () => {
        console.log(`Backend local rodando na porta ${PORT}`);
    });
}

module.exports = app;
