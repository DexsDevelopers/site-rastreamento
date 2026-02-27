/**
 * Bridge de Inicialização para Hostinger
 * Este arquivo redireciona o processo para o core do backend
 */
console.log('--- INICIANDO BRIDGE HOSTINGER v2 ---');
console.log('Diretório Atual:', __dirname);

try {
    require('./backend/index.js');
    console.log('✅ Backend carregado com sucesso via bridge.');
} catch (err) {
    console.error('❌ ERRO CRÍTICO NA BRIDGE:', err.message);
    console.error(err.stack);
    process.exit(1);
}
