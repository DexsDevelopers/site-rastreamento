// Script para criar o arquivo .env corretamente
import fs from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const envContent = `# Bot WhatsApp Unificado - Dois Projetos
API_PORT=3001

# URLs das APIs
RASTREAMENTO_API_URL=https://cornflowerblue-fly-883408.hostingersite.com
FINANCEIRO_API_URL=https://gold-quail-250128.hostingersite.com/seu_projeto

# Tokens por projeto
RASTREAMENTO_TOKEN=lucastav8012
FINANCEIRO_TOKEN=site-financeiro-token-2024

# Token padrão (compatibilidade)
API_TOKEN=lucastav8012

# Configurações opcionais
AUTO_REPLY=false
`;

const envPath = join(__dirname, '.env');

fs.writeFileSync(envPath, envContent, 'utf8');

console.log('✅ Arquivo .env criado com sucesso!');
console.log('📁 Caminho:', envPath);
console.log('📄 Conteúdo:');
console.log(fs.readFileSync(envPath, 'utf8'));
console.log('');
console.log('🔧 CONFIGURAÇÃO:');
console.log('   - Comandos com / → API do Rastreamento');
console.log('   - Comandos com ! → API do Financeiro');
console.log('');
console.log('Agora execute: npm run dev');
