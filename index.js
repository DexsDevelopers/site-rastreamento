/**
 * Bridge de Inicialização para Hostinger
 * Este arquivo redireciona o processo para o core do backend
 */
console.log('--- INICIANDO BRIDGE HOSTINGER v3 ---');
console.log('Diretório Atual:', __dirname);
console.log('Node Version:', process.version);
console.log('PORT:', process.env.PORT);

try {
    require('./backend/index.js');
    console.log('✅ Backend carregado com sucesso via bridge.');
} catch (err) {
    console.error('❌ ERRO CRÍTICO NA BRIDGE:', err.message);
    console.error(err.stack);

    // Mesmo com erro, sobe um servidor básico pra não dar 503
    const express = require('express');
    const app = express();
    const PORT = process.env.PORT || 3000;
    app.get('*', (req, res) => {
        res.status(500).json({
            error: 'Servidor em modo de emergência',
            details: err.message,
            stack: err.stack
        });
    });
    app.listen(PORT, () => {
        console.log(`🚨 Servidor de emergência na porta ${PORT}`);
    });
}
