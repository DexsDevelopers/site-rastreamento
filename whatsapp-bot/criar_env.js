// Script para criar o arquivo .env corretamente
import fs from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const envContent = `API_PORT=3001
API_TOKEN=lucastav8012
`;

const envPath = join(__dirname, '.env');

fs.writeFileSync(envPath, envContent, 'utf8');

console.log('✅ Arquivo .env criado com sucesso!');
console.log('📁 Caminho:', envPath);
console.log('📄 Conteúdo:');
console.log(fs.readFileSync(envPath, 'utf8'));
console.log('');
console.log('Agora execute: npm run dev');
