/**
 * Bridge de Inicialização para Hostinger v4
 * Servidor HTTP puro - sem NENHUMA dependência externa
 */
var http = require('http');
var PORT = process.env.PORT || 3000;

console.log('=== BRIDGE v4 ===');
console.log('PORT:', PORT);
console.log('NODE:', process.version);
console.log('CWD:', process.cwd());
console.log('DIR:', __dirname);

// Primeiro sobe o servidor HTTP puro (sem express, sem nada)
var server = http.createServer(function (req, res) {
    // Se chegou aqui, o servidor está vivo
    if (req.url === '/health-check') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ alive: true, port: PORT, node: process.version }));
        return;
    }
    // Delega para o backend se possível
    if (global._expressApp) {
        global._expressApp(req, res);
    } else {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end('<h1>Servidor online - Backend carregando...</h1><p>Aguarde e recarregue.</p>');
    }
});

server.listen(PORT, function () {
    console.log('✅ Servidor HTTP puro rodando na porta ' + PORT);

    // Agora tenta carregar o backend Express
    try {
        require('./backend/index.js');
        console.log('✅ Backend Express carregado!');
    } catch (err) {
        console.error('❌ ERRO ao carregar backend:', err.message);
        console.error(err.stack);
    }
});
