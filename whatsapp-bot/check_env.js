import dotenv from 'dotenv';
dotenv.config();

console.log('--- ENV CHECK ---');
console.log('RASTREAMENTO_API_URL:', process.env.RASTREAMENTO_API_URL);
console.log('FINANCEIRO_API_URL:', process.env.FINANCEIRO_API_URL);
console.log('RASTREAMENTO_TOKEN:', process.env.RASTREAMENTO_TOKEN);
console.log('API_TOKEN:', process.env.API_TOKEN);
console.log('-----------------');

if (process.env.FINANCEIRO_API_URL && process.env.FINANCEIRO_API_URL.includes('seu_projeto')) {
    console.warn('⚠️  WARNING: FINANCEIRO_API_URL contains "seu_projeto". Please update it in .env!');
}
