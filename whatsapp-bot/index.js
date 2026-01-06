/* WhatsApp Bot Centralizado - Baileys + Express
 * - Bot único para dois projetos: Rastreamento (/) e Financeiro (!)
 * - Exibe QR no console para logar
 * - Sistema de reconexão automática
 * - Heartbeat para manter conexão ativa
 * - Sistema de polls (enquetes) para o projeto financeiro
 * - Endpoints:
 *   GET  /status
 *   GET  /qr
 *   POST /send  { to: "55DDDNUMERO", text: "mensagem" }  Header: x-api-token
 *   POST /check { to: "55DDDNUMERO" } Header: x-api-token
 *   POST /send-poll { to: "55DDDNUMERO", question: "...", options: [...] }  Header: x-api-token
 */
import { default as makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers, downloadMediaMessage, proto } from '@whiskeysockets/baileys';
import { decryptPollVote } from '@whiskeysockets/baileys/lib/Utils/process-message.js';
import { jidNormalizedUser } from '@whiskeysockets/baileys/lib/WABinary/jid-utils.js';
import crypto from 'crypto';
import fs from 'fs';
import qrcode from 'qrcode-terminal';
import QRCodeImg from 'qrcode';
import express from 'express';
import cors from 'cors';
import pino from 'pino';
import dotenv from 'dotenv';
import axios from 'axios';
import FormData from 'form-data';
dotenv.config();

// Formata número brasileiro para WhatsApp
function formatBrazilNumber(raw) {
  let digits = String(raw).replace(/\D+/g, '');
  if (digits.startsWith('0')) digits = digits.slice(1);
  if (!digits.startsWith('55')) digits = '55' + digits;
  return digits;
}

const app = express();
app.use(cors());
app.use(express.json());

const PORT = Number(process.env.API_PORT || 3000);

// DEBUG: Ver porta configurada
console.log('🔌 DEBUG - API_PORT do .env:', process.env.API_PORT || 'não definido (usando 3000)');
console.log('🔌 DEBUG - Porta final:', PORT);

// DEBUG: Ver exatamente o que está no .env
const rawEnvToken = process.env.API_TOKEN;
console.log('🔍 DEBUG - Token do .env (raw):', rawEnvToken ? `"${rawEnvToken}"` : 'undefined');
console.log('🔍 DEBUG - Comprimento raw:', rawEnvToken ? rawEnvToken.length : 0);
if (rawEnvToken) {
  const bytes = Buffer.from(rawEnvToken, 'utf8');
  console.log('🔍 DEBUG - Bytes hex:', bytes.toString('hex'));
}

// Limpar token completamente - remover espaços e caracteres invisíveis
let rawToken = process.env.API_TOKEN || 'troque-este-token';
// Remover todos os espaços e caracteres não alfanuméricos
rawToken = String(rawToken).trim().replace(/\s+/g, '');
// Manter apenas letras e números ASCII
rawToken = rawToken.replace(/[^a-zA-Z0-9]/g, '');

// CORREÇÃO: Se o token esperado é "lucastav8012", forçar esse valor
// para evitar problemas com caracteres invisíveis no .env
if (rawToken.startsWith('lucastav8012')) {
  console.log('🔧 Forçando token correto: lucastav8012');
  rawToken = 'lucastav8012';
}

const API_TOKEN = rawToken;

// Log do token carregado (mascarado por segurança)
console.log('🔑 API_TOKEN carregado:', API_TOKEN ? `${API_TOKEN.substring(0, 4)}***${API_TOKEN.length > 8 ? API_TOKEN.substring(API_TOKEN.length - 4) : ''} (${API_TOKEN.length} chars)` : 'NÃO DEFINIDO');
if (API_TOKEN === 'troque-este-token') {
  console.warn('⚠️  AVISO: API_TOKEN ainda está no valor padrão! Configure no arquivo .env');
}

const AUTO_REPLY = String(process.env.AUTO_REPLY || 'false').toLowerCase() === 'true';
const AUTO_REPLY_WINDOW_MS = Number(process.env.AUTO_REPLY_WINDOW_MS || 3600000); // 1h

// URLs das APIs - DOIS PROJETOS
const RASTREAMENTO_API_URL = process.env.RASTREAMENTO_API_URL || 'https://cornflowerblue-fly-883408.hostingersite.com';
const FINANCEIRO_API_URL = process.env.FINANCEIRO_API_URL || 'https://gold-quail-250128.hostingersite.com/seu_projeto';

// Tokens por projeto
const RASTREAMENTO_TOKEN = process.env.RASTREAMENTO_TOKEN || process.env.API_TOKEN || 'lucastav8012';
const FINANCEIRO_TOKEN = process.env.FINANCEIRO_TOKEN || 'site-financeiro-token-2024';

const ADMIN_API_URL = RASTREAMENTO_API_URL; // Compatibilidade
const ADMIN_NUMBERS = (process.env.ADMIN_NUMBERS || '').split(',').map(n => formatBrazilNumber(n)).filter(Boolean);

// ===== SISTEMA DE LICENÇAS DE GRUPOS =====
const LICENSE_CHECK_ENABLED = String(process.env.LICENSE_CHECK_ENABLED || 'true').toLowerCase() === 'true';
const LICENSE_CACHE_TTL = 300000; // 5 minutos de cache
const groupLicenseCache = new Map(); // key: groupJid, value: { valid: boolean, expires: timestamp, data: object }

// ===== SISTEMA DE IA PARA CHAT PRIVADO =====
const IA_ENABLED = String(process.env.IA_ENABLED || 'true').toLowerCase() === 'true';
const IA_ONLY_PRIVATE = String(process.env.IA_ONLY_PRIVATE || 'true').toLowerCase() === 'true'; // Só responde no privado

console.log('📡 APIs configuradas:');
console.log('   Rastreamento:', RASTREAMENTO_API_URL, '(token:', RASTREAMENTO_TOKEN.substring(0,4) + '***)');
console.log('   Financeiro:', FINANCEIRO_API_URL, '(token:', FINANCEIRO_TOKEN.substring(0,4) + '***)');
console.log('   Verificação de licença:', LICENSE_CHECK_ENABLED ? 'ATIVADA' : 'DESATIVADA');
console.log('   IA Chat:', IA_ENABLED ? 'ATIVADA' : 'DESATIVADA', IA_ONLY_PRIVATE ? '(só privado)' : '(todos)');

// ===== CONFIGURAÇÕES DE ESTABILIDADE =====
const RECONNECT_DELAY_MIN = 5000;       // 5 segundos mínimo
const RECONNECT_DELAY_MAX = 120000;     // 2 minutos máximo
const HEARTBEAT_INTERVAL = 20000;       // 20 segundos (mais frequente)
const CONNECTION_TIMEOUT = 180000;      // 3 minutos timeout (mais tolerante)
const MAX_RECONNECT_ATTEMPTS = 10;      // Máximo antes de parar e pedir QR
const MEMORY_CHECK_INTERVAL = 300000;   // 5 minutos
const LOOP_DETECTION_WINDOW = 60000;    // 1 minuto para detectar loop
const MAX_DISCONNECTS_IN_WINDOW = 5;    // 5 desconexões em 1 min = loop
const PING_INTERVAL = 60000;            // 1 minuto - ping para manter conexão

let sock;
let isReady = false;
let lastQR = null;
let reconnectAttempts = 0;
let reconnectTimer = null;
let heartbeatTimer = null;
let pingTimer = null;
let lastHeartbeat = Date.now();
let connectionStartTime = null;
let disconnectTimestamps = [];  // Para detectar loop de desconexão
let isInLoopState = false;      // Flag de loop detectado
let isReconnecting = false;     // Flag para evitar reconexões simultâneas

// ===== CUSTOM SIMPLE STORE (Correção para Node 22) =====
const simpleStore = {
    messages: {},
    
    bind(ev) {
        ev.on('messages.upsert', ({ messages: newMessages }) => {
            for (const msg of newMessages) {
                if (!msg.message) continue;
                const jid = msg.key.remoteJid;
                const id = msg.key.id;
                
                if (!this.messages[jid]) this.messages[jid] = {};
                this.messages[jid][id] = msg;
                
                // Limitar memória: manter apenas últimas 100 mensagens por chat
                const keys = Object.keys(this.messages[jid]);
                if (keys.length > 100) {
                    delete this.messages[jid][keys[0]];
                }
            }
        });
    },
    
    async loadMessage(jid, id) {
        return this.messages[jid]?.[id];
    },
    
    writeToFile(path) {
        try {
            fs.writeFileSync(path, JSON.stringify(this.messages));
        } catch (e) { 
            console.error('Erro ao salvar store:', e.message); 
        }
    },
    
    readFromFile(path) {
        try {
            if (fs.existsSync(path)) {
                this.messages = JSON.parse(fs.readFileSync(path, 'utf-8'));
                console.log('📦 Store carregado do arquivo');
            }
        } catch (e) { 
            console.log('📦 Novo store iniciado'); 
        }
    }
};

// Inicializar Store
const store = simpleStore;
store.readFromFile('./baileys_store.json');

// Salvar periodicamente
setInterval(() => {
    store.writeToFile('./baileys_store.json');
}, 10_000);

// Controle simples para evitar auto-resposta repetida
const lastReplyAt = new Map(); // key: jid, value: timestamp
// Controle de comandos aguardando foto
const waitingPhoto = new Map(); // key: jid, value: { codigo: string, timestamp: number, isFinanceiro?: boolean, transactionId?: string }
// Configuração de anti-link por grupo
const antilinkGroups = new Map(); // key: groupJid, value: { enabled: boolean, allowAdmins: boolean }
// Grupos com automações desativadas
const disabledAutomationGroups = new Set(); // key: groupJid
// Flag para saber se as configurações de grupo já foram carregadas
let groupSettingsLoaded = false;

// ===== SISTEMA DE AUTOMAÇÕES =====
let automationsCache = []; // Cache das automações
let automationsSettings = {}; // Configurações do bot
let lastAutomationsLoad = 0; // Timestamp do último carregamento
const AUTOMATIONS_CACHE_TTL = 60000; // 1 minuto de cache
const automationCooldowns = new Map(); // key: `${automationId}-${jid}`, value: timestamp
// State management: amarrar messageId da poll com contexto
const pollContext = new Map(); // key: messageId, value: { type: string, jid: string, options: array, commandMap: object, timestamp: number }
// Anti-loop: evitar processar o mesmo voto duas vezes
const processedVotes = new Map(); // key: `${messageId}-${selectedIndex}-${jid}`, value: timestamp
// Votos pendentes aguardando descriptografia
const pendingPollVotes = new Map(); // key: messageId, value: { jid: string, pollCtx: object, timestamp: number }

// ===== HELPER PARA NORMALIZAÇÃO DE JID (FALLBACK) =====
function normalizeJidHelper(jid) {
  if (!jid) return jid;
  const [user, domain] = jid.split('@');
  if (!user || !domain) return jid;
  const userWithoutDevice = user.split(':')[0];
  return `${userWithoutDevice}@${domain}`;
}

// ===== SISTEMA DE VERIFICAÇÃO DE LICENÇA DE GRUPOS =====
async function checkGroupLicense(groupJid) {
  if (!LICENSE_CHECK_ENABLED) {
    return { valid: true, unlimited: true };
  }
  
  // Verificar cache
  const cached = groupLicenseCache.get(groupJid);
  if (cached && Date.now() < cached.expires) {
    return cached.data;
  }
  
  try {
    const response = await axios.post(`${RASTREAMENTO_API_URL}/api_check_license.php`, {
      action: 'check',
      group_jid: groupJid
    }, {
      headers: {
        'x-api-token': RASTREAMENTO_TOKEN,
        'Content-Type': 'application/json'
      },
      timeout: 5000
    });
    
    const result = response.data;
    
    // Cachear resultado
    groupLicenseCache.set(groupJid, {
      expires: Date.now() + LICENSE_CACHE_TTL,
      data: result
    });
    
    return result;
  } catch (error) {
    console.error('[LICENSE] Erro ao verificar licença:', error.message);
    // Em caso de erro de conexão, permitir uso (fail-open)
    return { valid: true, error: true, message: 'Erro ao verificar licença' };
  }
}

async function activateGroupLicense(groupJid, groupName, licenseKey) {
  try {
    const response = await axios.post(`${RASTREAMENTO_API_URL}/api_check_license.php`, {
      action: 'activate',
      group_jid: groupJid,
      group_name: groupName,
      license_key: licenseKey
    }, {
      headers: {
        'x-api-token': RASTREAMENTO_TOKEN,
        'Content-Type': 'application/json'
      },
      timeout: 10000
    });
    
    // Limpar cache do grupo para forçar nova verificação
    groupLicenseCache.delete(groupJid);
    
    return response.data;
  } catch (error) {
    console.error('[LICENSE] Erro ao ativar licença:', error.message);
    return { success: false, message: 'Erro ao conectar com servidor de licenças' };
  }
}

async function getLicenseStatus(groupJid) {
  try {
    const response = await axios.post(`${RASTREAMENTO_API_URL}/api_check_license.php`, {
      action: 'status',
      group_jid: groupJid
    }, {
      headers: {
        'x-api-token': RASTREAMENTO_TOKEN,
        'Content-Type': 'application/json'
      },
      timeout: 5000
    });
    
    return response.data;
  } catch (error) {
    console.error('[LICENSE] Erro ao obter status:', error.message);
    return { success: false, message: 'Erro ao conectar com servidor' };
  }
}

async function getLicenseInfo() {
  try {
    const response = await axios.post(`${RASTREAMENTO_API_URL}/api_check_license.php`, {
      action: 'info'
    }, {
      headers: {
        'x-api-token': RASTREAMENTO_TOKEN,
        'Content-Type': 'application/json'
      },
      timeout: 5000
    });
    
    return response.data;
  } catch (error) {
    return { 
      success: true, 
      message: '🔑 *SISTEMA DE LICENÇAS*\n\nPara usar o bot neste grupo, é necessário uma licença.\n\nUse: `$licenca SUA-CHAVE` para ativar.' 
    };
  }
}

// ===== SISTEMA DE IA - CHAT INTELIGENTE =====
async function processIAChat(remoteJid, text, senderNumber) {
  if (!IA_ENABLED) {
    return null;
  }
  
  const isGroup = remoteJid.includes('@g.us');
  
  // Se configurado para só privado e for grupo, ignorar
  if (IA_ONLY_PRIVATE && isGroup) {
    return null;
  }
  
  try {
    const messageTimestamp = Date.now();
    log.info(`[IA] Processando mensagem de ${senderNumber}: "${text.substring(0, 50)}..." | Timestamp: ${new Date(messageTimestamp).toISOString()}`);
    
    const response = await axios.post(`${RASTREAMENTO_API_URL}/api_bot_ia.php`, {
      action: 'chat',
      message: text,
      phone: senderNumber
    }, {
      headers: {
        'x-api-token': RASTREAMENTO_TOKEN,
        'Content-Type': 'application/json'
      },
      timeout: 30000
    });
    
    if (response.data && response.data.success && response.data.response) {
      const source = response.data.source || 'unknown';
      const error = response.data.error;
      
      if (source === 'fallback') {
        if (response.data.needs_config) {
          log.warn(`[IA] API Key não configurada - usando fallback`);
        } else if (error) {
          log.warn(`[IA] Erro na IA (${error}) - usando fallback`);
        } else {
          log.warn(`[IA] Usando resposta de fallback`);
        }
      } else {
        log.success(`[IA] Resposta obtida (fonte: ${source})`);
      }
      
      return {
        success: true,
        response: response.data.response,
        source: source,
        error: error
      };
    }
    
    log.warn(`[IA] Resposta inválida: ${JSON.stringify(response.data)}`);
    return null;
  } catch (error) {
    log.error(`[IA] Erro ao processar: ${error.message}`);
    if (error.response) {
      log.error(`[IA] Status: ${error.response.status}, Data: ${JSON.stringify(error.response.data)}`);
    }
    return null;
  }
}

// ===== LOGS COLORIDOS =====
const log = {
  info: (msg) => console.log(`\x1b[36m[INFO]\x1b[0m ${new Date().toISOString()} - ${msg}`),
  success: (msg) => console.log(`\x1b[32m[OK]\x1b[0m ${new Date().toISOString()} - ${msg}`),
  warn: (msg) => console.log(`\x1b[33m[WARN]\x1b[0m ${new Date().toISOString()} - ${msg}`),
  error: (msg) => console.log(`\x1b[31m[ERROR]\x1b[0m ${new Date().toISOString()} - ${msg}`),
  heartbeat: (msg) => console.log(`\x1b[35m[💓]\x1b[0m ${new Date().toISOString()} - ${msg}`)
};

// ===== HEARTBEAT SYSTEM =====
function startHeartbeat() {
  if (heartbeatTimer) clearInterval(heartbeatTimer);
  
  heartbeatTimer = setInterval(async () => {
    if (!sock || !isReady) {
      return; // Silencioso quando não está pronto
    }
    
    try {
      // Verificação mais robusta da conexão
      // No Baileys, sock.user existe quando autenticado e conectado
      const isAuthenticated = sock.user && sock.user.id;
      
      // Verificar WebSocket apenas se disponível (pode ser undefined em algumas versões)
      let wsState = null;
      if (sock.ws) {
        wsState = sock.ws.readyState;
      }
      
      // Se não está autenticado E WebSocket está fechado, reconectar
      // Mas dar um tempo antes de reconectar (pode ser temporário)
      if (!isAuthenticated && wsState === 3) {
        log.warn(`Heartbeat: Não autenticado e WebSocket fechado, reconectando...`);
        await reconnect('Heartbeat detectou falta de autenticação');
        return;
      }
      
      // Se WebSocket está explicitamente fechado (3 = CLOSED), reconectar
      // Mas ignorar se wsState for undefined (normal em algumas versões do Baileys)
      if (wsState !== null && wsState === 3) {
        log.warn(`Heartbeat: WebSocket fechado (state: ${wsState}), reconectando...`);
        await reconnect('Heartbeat detectou WebSocket fechado');
        return;
      }
      
      // Verificar tempo desde última atividade (mais tolerante)
      const timeSinceLastBeat = Date.now() - lastHeartbeat;
      if (timeSinceLastBeat > CONNECTION_TIMEOUT) {
        log.warn(`Heartbeat: Conexão parece travada (${Math.round(timeSinceLastBeat/1000)}s sem atividade)`);
        await reconnect('Timeout de conexão detectado');
        return;
      }
      
      // Atualizar timestamp
      lastHeartbeat = Date.now();
      
      // Calcular uptime
      const uptime = connectionStartTime ? Math.round((Date.now() - connectionStartTime) / 1000 / 60) : 0;
      
      // Log a cada 5 minutos (15 heartbeats com intervalo de 20s)
      if (Math.random() < 0.067) {
        log.heartbeat(`Conexão ativa há ${uptime} minutos | Tentativas reconexão: ${reconnectAttempts}`);
      }
      
    } catch (error) {
      // Se o erro indica que o socket não existe mais, reconectar
      if (error.message?.includes('socket') || error.message?.includes('connection') || error.message?.includes('Cannot read')) {
        log.warn(`Heartbeat: Erro ao verificar conexão (${error.message}), tentando reconectar...`);
        await reconnect('Erro no heartbeat');
        return;
      }
      // Ignorar erros menores
    }
  }, HEARTBEAT_INTERVAL);
  
  log.info('Sistema de heartbeat iniciado');
}

function stopHeartbeat() {
  if (heartbeatTimer) {
    clearInterval(heartbeatTimer);
    heartbeatTimer = null;
  }
  if (pingTimer) {
    clearInterval(pingTimer);
    pingTimer = null;
  }
}

// ===== PING SYSTEM =====
// Envia um ping periódico para manter a conexão ativa
function startPing() {
  if (pingTimer) clearInterval(pingTimer);
  
  pingTimer = setInterval(async () => {
    if (!sock || !isReady) {
      return;
    }
    
    try {
      // Tentar uma operação leve para manter conexão ativa
      // Verificar se o socket ainda responde
      if (sock.user && sock.user.id) {
        // Atualizar heartbeat quando ping é bem-sucedido
        lastHeartbeat = Date.now();
      }
    } catch (error) {
      // Se ping falhar, pode indicar problema de conexão
      log.warn(`Ping falhou: ${error.message}`);
    }
  }, PING_INTERVAL);
}

// ===== SISTEMA DE RECONEXÃO =====
function calculateReconnectDelay() {
  // Exponential backoff com jitter
  const baseDelay = Math.min(
    RECONNECT_DELAY_MIN * Math.pow(1.5, reconnectAttempts),
    RECONNECT_DELAY_MAX
  );
  // Adicionar jitter (variação aleatória) para evitar thundering herd
  const jitter = Math.random() * 1000;
  return Math.round(baseDelay + jitter);
}

async function reconnect(reason = 'Desconhecido') {
  // Evitar reconexões simultâneas
  if (isReconnecting) {
    log.warn(`Reconexão já em andamento, ignorando nova solicitação: ${reason}`);
    return;
  }
  
  if (reconnectTimer) {
    clearTimeout(reconnectTimer);
    reconnectTimer = null;
  }
  
  // Registrar timestamp de desconexão
  const now = Date.now();
  disconnectTimestamps.push(now);
  
  // Limpar timestamps antigos (fora da janela)
  disconnectTimestamps = disconnectTimestamps.filter(ts => now - ts < LOOP_DETECTION_WINDOW);
  
  // Detectar loop de desconexão
  if (disconnectTimestamps.length >= MAX_DISCONNECTS_IN_WINDOW) {
    isInLoopState = true;
    log.error('🔴 LOOP DE DESCONEXÃO DETECTADO!');
    log.error(`${disconnectTimestamps.length} desconexões em ${LOOP_DETECTION_WINDOW/1000} segundos`);
    log.error('');
    log.error('╔══════════════════════════════════════════════════════════╗');
    log.error('║  AÇÃO NECESSÁRIA: Sessão inválida ou corrompida          ║');
    log.error('║                                                          ║');
    log.error('║  1. Pare o bot (Ctrl+C)                                  ║');
    log.error('║  2. Delete a pasta: whatsapp-bot/auth                    ║');
    log.error('║  3. Reinicie: npm run dev                                ║');
    log.error('║  4. Escaneie o QR Code novamente                         ║');
    log.error('╚══════════════════════════════════════════════════════════╝');
    log.error('');
    log.error('Bot pausado. Aguardando intervenção manual...');
    
    // Parar de tentar reconectar
    stopHeartbeat();
    isReconnecting = false;
    return;
  }
  
  reconnectAttempts++;
  
  if (reconnectAttempts > MAX_RECONNECT_ATTEMPTS) {
    log.error(`Máximo de tentativas (${MAX_RECONNECT_ATTEMPTS}) atingido.`);
    log.error('Provavelmente a sessão expirou. Delete a pasta ./auth e escaneie QR novamente.');
    isInLoopState = true;
    isReconnecting = false;
    return;
  }
  
  const delay = calculateReconnectDelay();
  log.warn(`Reconexão #${reconnectAttempts} em ${Math.round(delay/1000)}s. Motivo: ${reason}`);
  
  reconnectTimer = setTimeout(async () => {
    isReconnecting = true;
    try {
      stopHeartbeat();
      if (sock) {
        try { sock.end(); } catch (e) {}
      }
      await start();
      isReconnecting = false;
    } catch (error) {
      log.error(`Falha na reconexão: ${error.message}`);
      isReconnecting = false;
      await reconnect('Erro na tentativa de reconexão');
    }
  }, delay);
}

// ===== FUNÇÃO PARA PROCESSAR VOTO DE POLL =====
async function processPollVote(messageId, jid, selectedOptionIndex, pollCtx) {
  try {
    const phoneNumber = jid.split('@')[0];
    
    // Validar índice selecionado
    if (typeof selectedOptionIndex !== 'number' || selectedOptionIndex < 0 || selectedOptionIndex > 11) {
      log.warn(`[POLL] Índice de voto inválido: ${selectedOptionIndex}`);
      return;
    }
    
    // ANTI-LOOP: Verificar se já processamos este voto
    const voteKey = `${messageId}-${selectedOptionIndex}-${jid}`;
    if (processedVotes.has(voteKey)) {
      log.info(`[POLL] Voto já processado, ignorando duplicado: ${voteKey}`);
      return;
    }
    
    // ANTI-LOOP: Marcar voto como processado
    processedVotes.set(voteKey, Date.now());
    
    log.info(`[POLL] ✅ Usuário ${phoneNumber} votou na opção ${selectedOptionIndex} (poll: ${pollCtx.type})`);
    
    // Mapear opção para comando usando o contexto
    const command = pollCtx.commandMap && pollCtx.commandMap[selectedOptionIndex];
    if (!command) {
      log.warn(`[POLL] Comando não encontrado para índice ${selectedOptionIndex} no contexto ${pollCtx.type}`);
      return;
    }
    
    log.info(`[POLL] Executando comando: ${command} (contexto: ${pollCtx.type})`);
    
    // Mapeamento de mensagens personalizadas para comandos que precisam de argumentos
    const commandsWithArgs = {
      '!receita': {
        title: '💰 Registrar Receita',
        message: `✅ Você escolheu registrar uma receita!\n\n` +
                 `📝 *Como usar:*\n` +
                 `Digite: *!receita VALOR DESCRIÇÃO*\n\n` +
                 `💡 *Exemplos:*\n` +
                 `• \`!receita 1500 Salário\`\n` +
                 `• \`!receita 500 Venda de produtos\`\n` +
                 `• \`recebi 1200 Freelance\`\n\n` +
                 `Digite o comando acima para registrar sua receita.`
      },
      '!despesa': {
        title: '💸 Registrar Despesa',
        message: `✅ Você escolheu registrar uma despesa!\n\n` +
                 `📝 *Como usar:*\n` +
                 `Digite: *!despesa VALOR DESCRIÇÃO*\n\n` +
                 `💡 *Exemplos:*\n` +
                 `• \`!despesa 200 Supermercado\`\n` +
                 `• \`!despesa 50 Combustível\`\n` +
                 `• \`gastei 30 Almoço\`\n\n` +
                 `Digite o comando acima para registrar sua despesa.`
      }
    };
    
    // Verificar se o comando precisa de argumentos
    if (commandsWithArgs[command]) {
      const cmdInfo = commandsWithArgs[command];
      try {
        await sock.sendMessage(jid, { text: cmdInfo.message });
        log.success(`[POLL] ✅ Mensagem personalizada enviada para comando ${command}`);
        return; // Não chamar a API para comandos que precisam de argumentos
      } catch (sendError) {
        log.error(`[POLL] Erro ao enviar mensagem personalizada: ${sendError.message}`);
        // Continuar para tentar a API como fallback
      }
    }
    
    // Processar comando automaticamente (para comandos que não precisam de argumentos)
    try {
      const apiUrl = `${FINANCEIRO_API_URL}/admin_bot_api.php`;
      log.info(`[POLL] Enviando requisição para: ${apiUrl}`);
      
      // Preparar payload da requisição
      const requestPayload = {
        phone: phoneNumber,
        command: command,
        args: [],
        message: command,
        source: 'poll',
        pollContext: pollCtx.type
      };
      
      // Se for comando de tarefas, incluir flag para retornar subtarefas
      if (command === '!tarefas' || command === 'tarefas') {
        requestPayload.include_subtasks = true;
        log.info(`[POLL] Comando tarefas detectado - solicitando subtarefas`);
      }
      
      const apiResponse = await axios.post(apiUrl, requestPayload, {
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${FINANCEIRO_TOKEN}`
        },
        timeout: 30000
      });
      
      log.info(`[POLL] Resposta da API recebida: ${JSON.stringify(apiResponse.data).substring(0, 200)}`);
      
      if (apiResponse && apiResponse.data && apiResponse.data.message) {
        await sock.sendMessage(jid, { text: apiResponse.data.message });
        log.success(`[POLL] ✅ Comando ${command} executado via poll (${pollCtx.type})`);
      } else {
        log.warn(`[POLL] API não retornou mensagem na resposta`);
      }
    } catch (apiError) {
      log.error(`[POLL] Erro ao processar comando da poll: ${apiError.message}`);
      if (apiError.response) {
        log.error(`[POLL] Resposta de erro: ${JSON.stringify(apiError.response.data)}`);
      }
      try {
        await sock.sendMessage(jid, { 
          text: `❌ Erro ao processar sua escolha. Digite ${command} manualmente.` 
        });
      } catch (sendError) {
        log.error(`[POLL] Erro ao enviar mensagem de erro: ${sendError.message}`);
      }
    }
  } catch (error) {
    log.error(`[POLL] Erro ao processar voto: ${error.message}`);
  }
}

// ===== SISTEMA DE POLLS (ENQUETES) =====
// Função helper para criar e enviar poll (enquete) usando formato oficial do Baileys
// context: { type: string, commandMap: object } - tipo da poll e mapeamento de comandos
async function sendPoll(sock, jid, question, options, context = {}) {
  try {
    // Validações obrigatórias
    if (!sock) {
      throw new Error('Socket não está disponível');
    }
    
    if (!isReady) {
      throw new Error('Bot não está pronto (não conectado)');
    }
    
    if (!options || !Array.isArray(options)) {
      throw new Error('Opções devem ser um array');
    }
    
    if (options.length < 2 || options.length > 12) {
      throw new Error('Poll deve ter entre 2 e 12 opções');
    }

    if (!question || typeof question !== 'string' || question.trim() === '') {
      throw new Error('Pergunta da poll é obrigatória');
    }

    log.info(`[POLL] Preparando poll: "${question}" com ${options.length} opções`);
    log.info(`[POLL] Opções: ${options.join(', ')}`);
    log.info(`[POLL] JID destino: ${jid}`);
    log.info(`[POLL] Contexto: ${context.type || 'default'}`);

    // Formato oficial do Baileys para polls
    const pollMessage = {
      poll: {
        name: String(question),
        values: options.map(opt => String(opt)),
        selectableCount: 1
      }
    };

    log.info(`[POLL] Enviando poll para ${jid}...`);
    
    // Enviar poll usando formato oficial
    const sent = await sock.sendMessage(jid, pollMessage);
    
    if (!sent || !sent.key || !sent.key.id) {
      throw new Error('Resposta inválida ao enviar poll');
    }
    
    const messageId = sent.key.id;
    
    // Obter pollEncKey da mensagem enviada (necessário para descriptografar votos)
    // Segundo o código do Baileys, a chave está em messageContextInfo.messageSecret
    let pollEncKey = null;
    try {
      // Debug: ver o que realmente está na resposta
      log.info(`[POLL] DEBUG - Estrutura de sent.message: ${JSON.stringify(Object.keys(sent.message || {})).substring(0, 200)}`);
      
      // Tentar obter da resposta imediata
      // A chave pode estar em messageContextInfo.messageSecret (conforme código do Baileys)
      if (sent.message?.messageContextInfo?.messageSecret) {
        pollEncKey = Buffer.from(sent.message.messageContextInfo.messageSecret);
        log.info(`[POLL] ✅ pollEncKey obtida de messageContextInfo.messageSecret: ${pollEncKey.toString('hex').substring(0, 32)}...`);
      } else if (sent.message?.pollCreationMessage?.encKey) {
        // Fallback: tentar pollCreationMessage.encKey
        pollEncKey = Buffer.from(sent.message.pollCreationMessage.encKey);
        log.info(`[POLL] ✅ pollEncKey obtida de pollCreationMessage.encKey: ${pollEncKey.toString('hex').substring(0, 32)}...`);
      } else {
        log.warn(`[POLL] pollEncKey não encontrada na resposta imediata, tentando buscar do store...`);
        
        // Tentar buscar do store do Baileys após um pequeno delay
        (async () => {
          await new Promise(resolve => setTimeout(resolve, 1500)); // Aguardar 1.5 segundos
          try {
            const fullMessage = await sock.loadMessage(jid, messageId);
            log.info(`[POLL] DEBUG - Mensagem do store: ${JSON.stringify(Object.keys(fullMessage?.message || {})).substring(0, 200)}`);
            
            // Tentar messageContextInfo.messageSecret primeiro
            if (fullMessage?.message?.messageContextInfo?.messageSecret) {
              const foundKey = Buffer.from(fullMessage.message.messageContextInfo.messageSecret);
              const existingCtx = pollContext.get(messageId);
              if (existingCtx) {
                existingCtx.pollEncKey = foundKey;
                pollContext.set(messageId, existingCtx);
                log.info(`[POLL] ✅ pollEncKey obtida do store (messageSecret): ${foundKey.toString('hex').substring(0, 32)}...`);
              }
            } else if (fullMessage?.message?.pollCreationMessage?.encKey) {
              // Fallback
              const foundKey = Buffer.from(fullMessage.message.pollCreationMessage.encKey);
              const existingCtx = pollContext.get(messageId);
              if (existingCtx) {
                existingCtx.pollEncKey = foundKey;
                pollContext.set(messageId, existingCtx);
                log.info(`[POLL] ✅ pollEncKey obtida do store (encKey): ${foundKey.toString('hex').substring(0, 32)}...`);
              }
            } else {
              log.warn(`[POLL] pollEncKey não encontrada no store também`);
            }
          } catch (storeError) {
            log.warn(`[POLL] Erro ao buscar pollEncKey do store: ${storeError.message}`);
          }
        })();
      }
    } catch (keyError) {
      log.warn(`[POLL] Erro ao obter pollEncKey: ${keyError.message}`);
    }
    
    // Armazenar contexto da poll para processar votos depois
    pollContext.set(messageId, {
      type: context.type || 'default',
      jid: jid,
      options: options,
      commandMap: context.commandMap || {},
      timestamp: Date.now(),
      pollEncKey: pollEncKey, // Chave para descriptografar votos
      pollCreatorJid: sock.user?.id || jid, // JID do criador da poll (bot)
      pollMsgId: messageId
    });
    
    if (pollEncKey) {
      log.success(`[POLL] ✅ Enquete enviada com sucesso! Message ID: ${messageId}, pollEncKey: ${pollEncKey.toString('hex').substring(0, 32)}...`);
    } else {
      log.warn(`[POLL] ⚠️ Enquete enviada, mas pollEncKey não foi encontrada imediatamente. Será buscada do store quando necessário. Message ID: ${messageId}`);
    }
    return { success: true, messageId: messageId };
    
  } catch (error) {
    log.error(`[POLL] ❌ Erro ao enviar enquete: ${error.message}`);
    if (error.stack) {
      log.error(`[POLL] Stack trace: ${error.stack}`);
    }
    throw error;
  }
}

// ===== MONITORAMENTO DE MEMÓRIA =====
function checkMemory() {
  const used = process.memoryUsage();
  const heapUsedMB = Math.round(used.heapUsed / 1024 / 1024);
  const heapTotalMB = Math.round(used.heapTotal / 1024 / 1024);
  
  const now = Date.now();
  const oneHourAgo = now - (60 * 60 * 1000);
  
  // Limpar caches antigos sempre (não só quando memória alta)
  for (const [key, value] of lastReplyAt.entries()) {
    if (now - value > AUTO_REPLY_WINDOW_MS * 2) {
      lastReplyAt.delete(key);
    }
  }
  for (const [key, value] of waitingPhoto.entries()) {
    if (now - value.timestamp > 10 * 60 * 1000) {
      waitingPhoto.delete(key);
    }
  }
  // Limpar contextos de polls antigos
  for (const [key, value] of pollContext.entries()) {
    if (value.timestamp < oneHourAgo) {
      pollContext.delete(key);
    }
  }
  // Limpar votos processados antigos
  for (const [key, timestamp] of processedVotes.entries()) {
    if (timestamp < oneHourAgo) {
      processedVotes.delete(key);
    }
  }
  
  if (heapUsedMB > 500) {
    log.warn(`Memória alta: ${heapUsedMB}MB / ${heapTotalMB}MB`);
    
    // Forçar garbage collection se disponível
    if (global.gc) {
      log.info('Forçando garbage collection...');
      global.gc();
    }
  }
}

// ===== SISTEMA DE CONFIGURAÇÕES DE GRUPO =====

// Carregar configurações de todos os grupos do servidor
async function loadGroupSettings() {
  try {
    const apiUrl = `${RASTREAMENTO_API_URL}/api_bot_automations.php?action=get_all_group_settings`;
    const response = await axios.get(apiUrl, {
      headers: {
        'x-api-token': RASTREAMENTO_TOKEN
      },
      timeout: 10000
    });
    
    if (response.data && response.data.success) {
      const settings = response.data.data || [];
      
      // Limpar configurações antigas
      antilinkGroups.clear();
      disabledAutomationGroups.clear();
      
      // Carregar configurações
      for (const s of settings) {
        if (s.antilink_enabled == 1) {
          antilinkGroups.set(s.grupo_jid, { 
            enabled: true, 
            allowAdmins: s.antilink_allow_admins == 1 
          });
        }
        if (s.automations_enabled == 0) {
          disabledAutomationGroups.add(s.grupo_jid);
        }
      }
      
      groupSettingsLoaded = true;
      log.info(`[GROUP SETTINGS] ${settings.length} configurações de grupo carregadas`);
      log.info(`[GROUP SETTINGS] Antilink ativo em ${antilinkGroups.size} grupos`);
      log.info(`[GROUP SETTINGS] Automações desativadas em ${disabledAutomationGroups.size} grupos`);
    }
  } catch (error) {
    log.warn(`[GROUP SETTINGS] Erro ao carregar: ${error.message}`);
  }
}

// Salvar configuração de grupo no servidor
async function saveGroupSettings(grupoJid, grupoNome, config) {
  try {
    const apiUrl = `${RASTREAMENTO_API_URL}/api_bot_automations.php?action=save_group_settings`;
    await axios.post(apiUrl, {
      grupo_jid: grupoJid,
      grupo_nome: grupoNome,
      antilink_enabled: config.antilinkEnabled ? 1 : 0,
      antilink_allow_admins: config.antilinkAllowAdmins !== false ? 1 : 0,
      automations_enabled: config.automationsEnabled !== false ? 1 : 0
    }, {
      headers: {
        'x-api-token': RASTREAMENTO_TOKEN,
        'Content-Type': 'application/json'
      },
      timeout: 10000
    });
    log.info(`[GROUP SETTINGS] Configurações salvas para ${grupoNome || grupoJid}`);
  } catch (error) {
    log.error(`[GROUP SETTINGS] Erro ao salvar: ${error.message}`);
  }
}

// ===== SISTEMA DE AUTOMAÇÕES - FUNÇÕES =====

// Carregar automações do servidor
async function loadAutomations() {
  try {
    // Verificar cache
    if (Date.now() - lastAutomationsLoad < AUTOMATIONS_CACHE_TTL && automationsCache.length > 0) {
      return automationsCache;
    }
    
    const apiUrl = `${RASTREAMENTO_API_URL}/api_bot_automations.php?action=get_automations`;
    const response = await axios.get(apiUrl, {
      headers: {
        'x-api-token': RASTREAMENTO_TOKEN
      },
      timeout: 10000
    });
    
    if (response.data && response.data.success) {
      automationsCache = response.data.automations || [];
      lastAutomationsLoad = Date.now();
      log.info(`[AUTOMATIONS] ${automationsCache.length} automações carregadas`);
    }
    
    return automationsCache;
  } catch (error) {
    log.warn(`[AUTOMATIONS] Erro ao carregar automações: ${error.message}`);
    return automationsCache; // Retornar cache antigo em caso de erro
  }
}

// Carregar configurações do bot
async function loadBotSettings() {
  try {
    const apiUrl = `${RASTREAMENTO_API_URL}/api_bot_automations.php?action=get_settings`;
    const response = await axios.get(apiUrl, {
      headers: {
        'x-api-token': RASTREAMENTO_TOKEN
      },
      timeout: 10000
    });
    
    if (response.data && response.data.success) {
      automationsSettings = response.data.settings || {};
      log.info(`[AUTOMATIONS] Configurações carregadas`);
    }
    
    return automationsSettings;
  } catch (error) {
    log.warn(`[AUTOMATIONS] Erro ao carregar configurações: ${error.message}`);
    return automationsSettings;
  }
}

// Verificar se mensagem corresponde a uma automação
function matchAutomation(text, automation) {
  if (!text || !automation.gatilho) return false;
  
  const lowerText = text.toLowerCase().trim();
  const gatilho = automation.gatilho.toLowerCase();
  
  switch (automation.tipo) {
    case 'mensagem_especifica':
      // Match exato
      return lowerText === gatilho;
      
    case 'palavra_chave':
      // Match com palavras-chave separadas por |
      const keywords = gatilho.split('|').map(k => k.trim());
      return keywords.some(keyword => {
        // Verifica se a palavra-chave está presente na mensagem
        return lowerText.includes(keyword) || lowerText === keyword;
      });
      
    case 'regex':
      // Match com expressão regular
      try {
        const regex = new RegExp(automation.gatilho, 'i');
        return regex.test(text);
      } catch (e) {
        log.warn(`[AUTOMATIONS] Regex inválido: ${automation.gatilho}`);
        return false;
      }
      
    default:
      return false;
  }
}

// Verificar cooldown
function checkCooldown(automationId, jid, cooldownSeconds) {
  if (!cooldownSeconds || cooldownSeconds <= 0) return false;
  
  const key = `${automationId}-${jid}`;
  const lastUse = automationCooldowns.get(key);
  
  if (!lastUse) return false;
  
  const elapsed = (Date.now() - lastUse) / 1000;
  return elapsed < cooldownSeconds;
}

// Registrar uso de automação
function registerAutomationUse(automationId, jid) {
  const key = `${automationId}-${jid}`;
  automationCooldowns.set(key, Date.now());
  
  // Limpar cooldowns antigos (mais de 1 hora)
  const oneHourAgo = Date.now() - 3600000;
  for (const [k, v] of automationCooldowns.entries()) {
    if (v < oneHourAgo) automationCooldowns.delete(k);
  }
}

// Registrar log de execução na API
async function logAutomationExecution(automation, jid, message, response, grupoId, grupoNome) {
  try {
    if (!automationsSettings.log_automations) return;
    
    const numero = jid.split('@')[0];
    
    await axios.post(
      `${RASTREAMENTO_API_URL}/api_bot_automations.php?action=log_execution`,
      {
        automation_id: automation.id,
        jid_origem: jid,
        numero_origem: numero,
        mensagem_recebida: message,
        resposta_enviada: response,
        grupo_id: grupoId,
        grupo_nome: grupoNome
      },
      {
        headers: {
          'x-api-token': RASTREAMENTO_TOKEN,
          'Content-Type': 'application/json'
        },
        timeout: 5000
      }
    );
    
    // Incrementar contador
    await axios.post(
      `${RASTREAMENTO_API_URL}/api_bot_automations.php?action=increment_usage`,
      { automation_id: automation.id },
      {
        headers: {
          'x-api-token': RASTREAMENTO_TOKEN,
          'Content-Type': 'application/json'
        },
        timeout: 5000
      }
    );
  } catch (error) {
    // Silencioso - não falhar por causa de log
  }
}

// Processar automações para uma mensagem
async function processAutomations(remoteJid, text, msg) {
  try {
    log.info(`[AUTOMATIONS] Processando: "${text}" de ${remoteJid.split('@')[0]}`);
    
    // Verificar se automações estão desativadas para este grupo específico
    if (disabledAutomationGroups.has(remoteJid)) {
      log.info(`[AUTOMATIONS] Automações desativadas para este grupo`);
      return false;
    }
    
    // Verificar se automações estão habilitadas globalmente
    if (!automationsSettings.automations_enabled) {
      log.warn(`[AUTOMATIONS] automations_enabled = false`);
      return false;
    }
    if (!automationsSettings.bot_enabled) {
      log.warn(`[AUTOMATIONS] bot_enabled = false`);
      return false;
    }
    
    // Carregar automações (do cache ou API)
    const automations = await loadAutomations();
    if (!automations || automations.length === 0) {
      log.warn(`[AUTOMATIONS] Nenhuma automação carregada`);
      return false;
    }
    
    log.info(`[AUTOMATIONS] ${automations.length} automações disponíveis`);
    
    const isGroup = remoteJid.includes('@g.us');
    const grupoId = isGroup ? remoteJid : null;
    
    // Verificar licença do grupo (se for grupo)
    if (isGroup && LICENSE_CHECK_ENABLED) {
      const license = await checkGroupLicense(remoteJid);
      if (!license.valid && !license.unlimited && !license.error) {
        log.warn(`[LICENSE] Grupo sem licença válida: ${remoteJid}`);
        // Não responder automações em grupos sem licença
        return false;
      }
    }
    let grupoNome = null;
    
    // Tentar obter nome do grupo
    if (isGroup && sock) {
      try {
        const groupMetadata = await sock.groupMetadata(remoteJid);
        grupoNome = groupMetadata?.subject || null;
      } catch (e) {
        // Ignorar erro ao obter metadata
      }
    }
    
    // Verificar cada automação por ordem de prioridade
    for (const automation of automations) {
      // Verificar se é para grupo/privado
      if (automation.apenas_privado == 1 && isGroup) continue;
      if (automation.apenas_grupo == 1 && !isGroup) continue;
      
      // Verificar se é para grupo específico
      if (automation.grupo_id && automation.grupo_id !== remoteJid) continue;
      
      // Verificar match
      if (!matchAutomation(text, automation)) continue;
      
      // Verificar cooldown
      if (checkCooldown(automation.id, remoteJid, automation.cooldown_segundos)) {
        log.info(`[AUTOMATIONS] Cooldown ativo para automação ${automation.id} e JID ${remoteJid}`);
        continue;
      }
      
      // Match encontrado! Enviar resposta
      log.success(`[AUTOMATIONS] ✅ Match: "${automation.nome}" para "${text.substring(0, 50)}..."`);
      
      // Aplicar delay se configurado
      if (automation.delay_ms && automation.delay_ms > 0) {
        await new Promise(resolve => setTimeout(resolve, automation.delay_ms));
      }
      
      // Enviar resposta
      try {
        // Verificar se tem imagem configurada
        if (automation.imagem_url && automation.imagem_url.trim()) {
          log.info(`[AUTOMATIONS] Enviando com imagem: ${automation.imagem_url}`);
          
          // Enviar imagem com caption (texto)
          await sock.sendMessage(remoteJid, {
            image: { url: automation.imagem_url },
            caption: automation.resposta
          });
        } else {
          // Enviar apenas texto
          await sock.sendMessage(remoteJid, { text: automation.resposta });
        }
        
        // Registrar uso
        registerAutomationUse(automation.id, remoteJid);
        
        // Log na API
        logAutomationExecution(automation, remoteJid, text, automation.resposta, grupoId, grupoNome);
        
        return true; // Automação executada
      } catch (sendError) {
        log.error(`[AUTOMATIONS] Erro ao enviar resposta: ${sendError.message}`);
        
        // Se falhar com imagem, tentar só texto
        if (automation.imagem_url) {
          try {
            log.warn(`[AUTOMATIONS] Tentando enviar apenas texto após falha de imagem`);
            await sock.sendMessage(remoteJid, { text: automation.resposta });
            registerAutomationUse(automation.id, remoteJid);
            return true;
          } catch (textError) {
            log.error(`[AUTOMATIONS] Também falhou só texto: ${textError.message}`);
          }
        }
      }
    }
    
    return false; // Nenhuma automação correspondeu
  } catch (error) {
    log.error(`[AUTOMATIONS] Erro ao processar: ${error.message}`);
    return false;
  }
}

// Salvar informações de grupo
async function saveGroupInfo(jid, nome, descricao, participantes) {
  try {
    await axios.post(
      `${RASTREAMENTO_API_URL}/api_bot_automations.php?action=save_grupo`,
      { jid, nome, descricao, participantes },
      {
        headers: {
          'x-api-token': RASTREAMENTO_TOKEN,
          'Content-Type': 'application/json'
        },
        timeout: 5000
      }
    );
  } catch (error) {
    // Silencioso
  }
}

// ===== COMANDOS DE ADMIN DO GRUPO =====
// Comandos como /ban, /kick, /promote, /demote
async function processGroupAdminCommand(remoteJid, text, msg) {
  try {
    const command = text.split(' ')[0].toLowerCase();
    const senderJid = msg.key.participant || msg.key.remoteJid;
    
    log.info(`[GROUP ADMIN] Comando extraído: "${command}" | Texto completo: "${text}"`);
    log.info(`[GROUP ADMIN] RemoteJid: ${remoteJid}`);
    
    // Comando $menu - mostrar menu de comandos (também aceita $help e $ajuda)
    // Este comando funciona em grupos e também em chat privado (para testes)
    if (command === '$menu' || command === '$help' || command === '$ajuda') {
      log.info(`[GROUP ADMIN] Comando $menu detectado!`);
      const menuText = `🤖 *MENU DE COMANDOS DO GRUPO*\n\n` +
                      `*Comandos de Administração:*\n` +
                      `• \`$ban @pessoa\` - Banir membro do grupo\n` +
                      `• \`$kick @pessoa\` - Remover membro do grupo\n` +
                      `• \`$promote @pessoa\` - Promover a admin\n` +
                      `• \`$demote @pessoa\` - Remover admin\n` +
                      `• \`$todos\` ou \`$all\` - Marcar todos os membros\n` +
                      `• \`$link\` - Obter link do grupo\n` +
                      `• \`$fechar\` - Fechar grupo (só admins podem falar)\n` +
                      `• \`$abrir\` - Abrir grupo (todos podem falar)\n\n` +
                      `*Configurações do Bot:*\n` +
                      `• \`$antilink on\` - Ativar anti-link\n` +
                      `• \`$antilink off\` - Desativar anti-link\n` +
                      `• \`$antilink status\` - Ver status do anti-link\n` +
                      `• \`$automacao on\` - Ativar automações\n` +
                      `• \`$automacao off\` - Desativar automações\n` +
                      `• \`$automacao status\` - Ver status das automações\n\n` +
                      `*Como usar:*\n` +
                      `• Marque a pessoa ou responda a mensagem dela\n` +
                      `• Ou mencione: \`$ban @pessoa\`\n` +
                      `• Ou digite o número: \`$ban 5511999999999\`\n\n` +
                      `⚠️ *Atenção:* O bot precisa ser admin do grupo para executar comandos de administração.`;
      
      log.info(`[GROUP ADMIN] Retornando menu com ${menuText.length} caracteres`);
      return { success: true, message: menuText };
    }
    
    const isGroup = remoteJid.includes('@g.us');
    if (!isGroup) {
      return { success: false, message: '❌ Este comando só funciona em grupos.' };
    }
    
    // Verificar se a mensagem é uma resposta ou está marcada (para identificar o alvo)
    const extendedText = msg.message?.extendedTextMessage;
    const contextInfo = extendedText?.contextInfo;
    const quotedMessage = contextInfo?.quotedMessage;
    const quotedParticipant = contextInfo?.participant || quotedMessage?.participant;
    
    // Verificar se há menções na mensagem (@pessoa)
    const mentionedJids = contextInfo?.mentionedJid || [];
    
    // Log detalhado para debug
    log.info(`[GROUP ADMIN] Command: ${command}`);
    log.info(`[GROUP ADMIN] ExtendedText existe: ${!!extendedText}`);
    log.info(`[GROUP ADMIN] ContextInfo existe: ${!!contextInfo}`);
    log.info(`[GROUP ADMIN] ContextInfo completo: ${JSON.stringify(contextInfo ? {
      participant: contextInfo.participant,
      stanzaId: contextInfo.stanzaId,
      remoteJid: contextInfo.remoteJid,
      hasQuotedMessage: !!quotedMessage
    } : 'null')}`);
    log.info(`[GROUP ADMIN] QuotedParticipant: ${quotedParticipant || 'não encontrado'}`);
    log.info(`[GROUP ADMIN] Mentions: ${mentionedJids.length} (${JSON.stringify(mentionedJids)})`);
    
    // Prioridade: 1. Mensagem marcada/respondida (participant do contextInfo), 2. Menção @, 3. Número digitado
    let targetJid = quotedParticipant;
    
    if (!targetJid && mentionedJids.length > 0) {
      targetJid = mentionedJids[0]; // Pegar a primeira menção
      log.info(`[GROUP ADMIN] Usando menção: ${targetJid}`);
    }
    
    // Se não tem resposta nem menção, verificar se digitou um número
    if (!targetJid) {
      const args = text.split(' ').slice(1);
      if (args.length > 0) {
        // Limpar o número (remover @, +, -, espaços)
        let numero = args[0].replace(/[@+\-\s]/g, '');
        // Se parecer um número de telefone
        if (/^\d{10,15}$/.test(numero)) {
          targetJid = numero + '@s.whatsapp.net';
          log.info(`[GROUP ADMIN] Usando número digitado: ${targetJid}`);
        }
      }
    }
    
    log.info(`[GROUP ADMIN] TargetJid final: ${targetJid || 'não encontrado'}`);
    
    // Obter metadata do grupo
    let groupMetadata;
    try {
      groupMetadata = await sock.groupMetadata(remoteJid);
    } catch (e) {
      log.error(`[GROUP ADMIN] Erro ao obter metadata: ${e.message}`);
      return { success: false, message: '❌ Erro ao obter informações do grupo.' };
    }
    
    log.info(`[GROUP ADMIN] Grupo: ${groupMetadata.subject}, Total participantes: ${groupMetadata.participants.length}`);
    
    // Verificar se quem enviou é admin
    // Como o WhatsApp usa LIDs, vamos verificar pelo participant da mensagem diretamente
    const senderIsAdmin = groupMetadata.participants.some(p => {
      // Comparar diretamente o JID do sender com os participantes
      const match = p.id === senderJid || 
                    p.id.split('@')[0] === senderJid.split('@')[0] ||
                    p.id.includes(senderJid.split('@')[0].split(':')[0]);
      return match && (p.admin === 'admin' || p.admin === 'superadmin');
    });
    
    log.info(`[GROUP ADMIN] Sender JID: ${senderJid}, é admin: ${senderIsAdmin}`);
    
    if (!senderIsAdmin) {
      return { success: false, message: '❌ Apenas admins do grupo podem usar este comando.' };
    }
    
    // Nota: A verificação se o BOT é admin será feita pela própria ação
    // Se o bot não for admin, o WhatsApp retornará erro que será capturado
    
    switch (command) {
      case '$ban':
      case '$kick':
      case '$remover': {
        if (!targetJid) {
          return { 
            success: false, 
            message: '❌ *Como usar o $ban:*\n\n• Marque a mensagem da pessoa e digite: $ban\n• Ou mencione: $ban @pessoa\n• Ou digite: $ban 5511999999999\n\n_💡 Dica: Marque a mensagem da pessoa e digite apenas $ban_'
          };
        }
        
        // Não permitir banir admin
        const targetNumber = targetJid.split('@')[0].split(':')[0];
        const targetIsAdmin = groupMetadata.participants.some(p => {
          const participantNumber = p.id.split('@')[0].split(':')[0];
          return participantNumber === targetNumber && (p.admin === 'admin' || p.admin === 'superadmin');
        });
        
        if (targetIsAdmin) {
          return { success: false, message: '❌ Não é possível banir um admin do grupo.' };
        }
        
        // Não permitir banir a si mesmo
        if (targetJid === senderJid) {
          return { success: false, message: '❌ Você não pode se banir.' };
        }
        
        try {
          await sock.groupParticipantsUpdate(remoteJid, [targetJid], 'remove');
          log.success(`[GROUP] Usuário ${targetNumber} banido do grupo ${groupMetadata.subject}`);
          
          // Deletar a mensagem marcada se existir
          if (contextInfo && contextInfo.stanzaId) {
            try {
              log.info(`[GROUP] Tentando deletar mensagem: ${contextInfo.stanzaId}, participant: ${contextInfo.participant}`);
              
              // Para mensagens em grupo, precisamos do participant
              const deleteKey = {
                remoteJid: remoteJid,
                fromMe: false,
                id: contextInfo.stanzaId,
                participant: contextInfo.participant || targetJid
              };
              
              log.info(`[GROUP] Delete key: ${JSON.stringify(deleteKey)}`);
              
              await sock.sendMessage(remoteJid, {
                delete: deleteKey
              });
              
              log.success(`[GROUP] Mensagem deletada com sucesso: ${contextInfo.stanzaId}`);
            } catch (deleteError) {
              // Não falhar o ban se a deleção falhar, apenas logar
              log.error(`[GROUP] Erro ao deletar mensagem: ${deleteError.message}`);
              log.error(`[GROUP] Stack: ${deleteError.stack}`);
            }
          } else {
            log.info(`[GROUP] Mensagem não será deletada - contextInfo: ${!!contextInfo}, stanzaId: ${contextInfo?.stanzaId}`);
          }
          
          return { 
            success: true, 
            message: `✅ Usuário @${targetNumber} foi removido do grupo.`,
            mentions: [targetJid]
          };
        } catch (e) {
          log.error(`[GROUP] Erro ao banir: ${e.message}`);
          if (e.message.includes('not-authorized') || e.message.includes('403') || e.message.includes('admin')) {
            return { success: false, message: '❌ O bot precisa ser admin do grupo para banir membros.' };
          }
          return { success: false, message: '❌ Erro ao remover usuário: ' + e.message };
        }
      }
      
      case '$promote':
      case '$promover': {
        if (!targetJid) {
          return { 
            success: false, 
            message: '❌ *Como usar o $promote:*\n\n• Responda a mensagem da pessoa\n• Ou marque: $promote @pessoa\n• Ou digite: $promote 5511999999999' 
          };
        }
        
        try {
          await sock.groupParticipantsUpdate(remoteJid, [targetJid], 'promote');
          const promoteNumber = targetJid.split('@')[0];
          log.success(`[GROUP] Usuário ${promoteNumber} promovido a admin`);
          return { 
            success: true, 
            message: `✅ @${promoteNumber} agora é admin do grupo!`,
            mentions: [targetJid]
          };
        } catch (e) {
          if (e.message.includes('not-authorized') || e.message.includes('403') || e.message.includes('admin')) {
            return { success: false, message: '❌ O bot precisa ser admin do grupo para promover membros.' };
          }
          return { success: false, message: '❌ Erro ao promover: ' + e.message };
        }
      }
      
      case '$demote':
      case '$rebaixar': {
        if (!targetJid) {
          return { 
            success: false, 
            message: '❌ *Como usar o $demote:*\n\n• Responda a mensagem do admin\n• Ou marque: $demote @pessoa\n• Ou digite: $demote 5511999999999' 
          };
        }
        
        try {
          await sock.groupParticipantsUpdate(remoteJid, [targetJid], 'demote');
          const demoteNumber = targetJid.split('@')[0];
          log.success(`[GROUP] Admin ${demoteNumber} rebaixado`);
          return { 
            success: true, 
            message: `✅ @${demoteNumber} não é mais admin.`,
            mentions: [targetJid]
          };
        } catch (e) {
          if (e.message.includes('not-authorized') || e.message.includes('403') || e.message.includes('admin')) {
            return { success: false, message: '❌ O bot precisa ser admin do grupo para rebaixar membros.' };
          }
          return { success: false, message: '❌ Erro ao rebaixar: ' + e.message };
        }
      }
      
      case '$licenca':
      case '$license':
      case '$key': {
        // Sistema de licenças de grupos
        const args = text.split(' ').slice(1);
        const subCommand = args[0]?.toLowerCase() || 'status';
        
        if (subCommand === 'info' || subCommand === 'ajuda' || subCommand === 'help') {
          const info = await getLicenseInfo();
          return { success: true, message: info.message };
        }
        
        if (subCommand === 'status') {
          const status = await getLicenseStatus(remoteJid);
          return { success: true, message: status.message };
        }
        
        // Tentar ativar licença com a chave fornecida
        const licenseKey = subCommand.toUpperCase();
        if (licenseKey && licenseKey.length >= 10) {
          let groupName = '';
          try {
            const metadata = await sock.groupMetadata(remoteJid);
            groupName = metadata?.subject || '';
          } catch (e) {}
          
          const result = await activateGroupLicense(remoteJid, groupName, licenseKey);
          return { success: result.success, message: result.message };
        }
        
        return { 
          success: false, 
          message: '🔑 *COMANDOS DE LICENÇA*\n\n' +
                   '`$licenca` - Ver status atual\n' +
                   '`$licenca SUA-CHAVE` - Ativar licença\n' +
                   '`$licenca info` - Mais informações\n\n' +
                   '_Adquira sua licença com o administrador._'
        };
      }
      
      case '$todos':
      case '$all':
      case '$marcar': {
        // Marcar todos do grupo
        const mentions = groupMetadata.participants.map(p => p.id);
        const mentionText = groupMetadata.participants
          .map(p => `@${p.id.split('@')[0]}`)
          .join(' ');
        
        // Texto adicional após o comando
        const extraText = text.replace(/^\$(todos|all|marcar)\s*/i, '').trim();
        const finalText = extraText 
          ? `📢 *${extraText}*\n\n${mentionText}`
          : `📢 *Atenção todos!*\n\n${mentionText}`;
        
        return { 
          success: true, 
          message: finalText,
          mentions: mentions
        };
      }
      
      case '$link': {
        // Obter link do grupo
        try {
          const inviteCode = await sock.groupInviteCode(remoteJid);
          return { 
            success: true, 
            message: `🔗 *Link do Grupo*\n\nhttps://chat.whatsapp.com/${inviteCode}` 
          };
        } catch (e) {
          return { success: false, message: '❌ Erro ao obter link: ' + e.message };
        }
      }
      
      case '$fechar':
      case '$close': {
        // Fechar grupo (só admins podem enviar)
        try {
          await sock.groupSettingUpdate(remoteJid, 'announcement');
          return { success: true, message: '🔒 Grupo fechado! Apenas admins podem enviar mensagens.' };
        } catch (e) {
          if (e.message.includes('not-authorized') || e.message.includes('403') || e.message.includes('admin')) {
            return { success: false, message: '❌ O bot precisa ser admin do grupo para alterar configurações.' };
          }
          return { success: false, message: '❌ Erro ao fechar grupo: ' + e.message };
        }
      }
      
      case '$abrir':
      case '$open': {
        // Abrir grupo (todos podem enviar)
        try {
          await sock.groupSettingUpdate(remoteJid, 'not_announcement');
          return { success: true, message: '🔓 Grupo aberto! Todos podem enviar mensagens.' };
        } catch (e) {
          if (e.message.includes('not-authorized') || e.message.includes('403') || e.message.includes('admin')) {
            return { success: false, message: '❌ O bot precisa ser admin do grupo para alterar configurações.' };
          }
          return { success: false, message: '❌ Erro ao abrir grupo: ' + e.message };
        }
      }
      
      case '$antilink': {
        // Configurar anti-link no grupo
        const args = text.split(' ').slice(1);
        const action = args[0]?.toLowerCase();
        
        if (!action || !['on', 'off', 'status'].includes(action)) {
          const currentStatus = antilinkGroups.get(remoteJid);
          return { 
            success: false, 
            message: `🔗 *Anti-Link*\n\n` +
                     `Status atual: ${currentStatus?.enabled ? '✅ Ativado' : '❌ Desativado'}\n\n` +
                     `*Como usar:*\n` +
                     `• $antilink on - Ativar\n` +
                     `• $antilink off - Desativar\n` +
                     `• $antilink status - Ver status`
          };
        }
        
        if (action === 'status') {
          const config = antilinkGroups.get(remoteJid);
          return { 
            success: true, 
            message: `🔗 *Status Anti-Link*\n\n` +
                     `Grupo: ${groupMetadata.subject}\n` +
                     `Status: ${config?.enabled ? '✅ Ativado' : '❌ Desativado'}\n\n` +
                     `_Quando ativado, membros que enviarem links serão removidos automaticamente._`
          };
        }
        
        if (action === 'on') {
          antilinkGroups.set(remoteJid, { enabled: true, allowAdmins: true });
          // Salvar no banco de dados
          const isAutomationDisabled = disabledAutomationGroups.has(remoteJid);
          saveGroupSettings(remoteJid, groupMetadata.subject, {
            antilinkEnabled: true,
            antilinkAllowAdmins: true,
            automationsEnabled: !isAutomationDisabled
          });
          log.success(`[ANTILINK] Ativado no grupo ${groupMetadata.subject}`);
          return { 
            success: true, 
            message: `✅ *Anti-Link Ativado!*\n\n` +
                     `Membros que enviarem links serão removidos automaticamente.\n\n` +
                     `⚠️ _Admins podem enviar links normalmente._\n\n` +
                     `💾 _Configuração salva permanentemente._`
          };
        }
        
        if (action === 'off') {
          antilinkGroups.set(remoteJid, { enabled: false, allowAdmins: true });
          // Salvar no banco de dados
          const isAutomationDisabled = disabledAutomationGroups.has(remoteJid);
          saveGroupSettings(remoteJid, groupMetadata.subject, {
            antilinkEnabled: false,
            antilinkAllowAdmins: true,
            automationsEnabled: !isAutomationDisabled
          });
          log.success(`[ANTILINK] Desativado no grupo ${groupMetadata.subject}`);
          return { 
            success: true, 
            message: `❌ *Anti-Link Desativado!*\n\n` +
                     `Membros podem enviar links normalmente.\n\n` +
                     `💾 _Configuração salva permanentemente._`
          };
        }
        
        return null;
      }
      
      case '$automacao':
      case '$automacoes': {
        // Ativar/desativar automações no grupo
        const argsAuto = text.split(' ').slice(1);
        const actionAuto = argsAuto[0]?.toLowerCase();
        
        if (!actionAuto || !['on', 'off', 'status'].includes(actionAuto)) {
          const isDisabled = disabledAutomationGroups.has(remoteJid);
          return { 
            success: false, 
            message: `🤖 *Automações do Grupo*\n\n` +
                     `Status atual: ${isDisabled ? '❌ Desativadas' : '✅ Ativadas'}\n\n` +
                     `*Como usar:*\n` +
                     `• $automacao on - Ativar automações\n` +
                     `• $automacao off - Desativar automações\n` +
                     `• $automacao status - Ver status`
          };
        }
        
        if (actionAuto === 'status') {
          const isDisabled = disabledAutomationGroups.has(remoteJid);
          return { 
            success: true, 
            message: `🤖 *Status das Automações*\n\n` +
                     `Grupo: ${groupMetadata.subject}\n` +
                     `Status: ${isDisabled ? '❌ Desativadas' : '✅ Ativadas'}\n\n` +
                     `_Quando desativadas, o bot não responde automaticamente neste grupo._`
          };
        }
        
        if (actionAuto === 'on') {
          disabledAutomationGroups.delete(remoteJid);
          // Salvar no banco de dados
          const isAntilinkEnabled = antilinkGroups.get(remoteJid)?.enabled || false;
          saveGroupSettings(remoteJid, groupMetadata.subject, {
            antilinkEnabled: isAntilinkEnabled,
            antilinkAllowAdmins: true,
            automationsEnabled: true
          });
          log.success(`[AUTOMACAO] Ativadas no grupo ${groupMetadata.subject}`);
          return { 
            success: true, 
            message: `✅ *Automações Ativadas!*\n\n` +
                     `O bot agora responderá às automações configuradas neste grupo.\n\n` +
                     `💾 _Configuração salva permanentemente._`
          };
        }
        
        if (actionAuto === 'off') {
          disabledAutomationGroups.add(remoteJid);
          // Salvar no banco de dados
          const isAntilinkEnabled = antilinkGroups.get(remoteJid)?.enabled || false;
          saveGroupSettings(remoteJid, groupMetadata.subject, {
            antilinkEnabled: isAntilinkEnabled,
            antilinkAllowAdmins: true,
            automationsEnabled: false
          });
          log.success(`[AUTOMACAO] Desativadas no grupo ${groupMetadata.subject}`);
          return { 
            success: true, 
            message: `❌ *Automações Desativadas!*\n\n` +
                     `O bot não responderá mais automaticamente neste grupo.\n\n` +
                     `_Comandos ($ban, $antilink, etc) continuam funcionando._\n\n` +
                     `💾 _Configuração salva permanentemente._`
          };
        }
        
        return null;
      }
      
      default:
        return null; // Não é um comando de admin de grupo
    }
  } catch (error) {
    log.error(`[GROUP ADMIN] Erro: ${error.message}`);
    return { success: false, message: '❌ Erro ao processar comando: ' + error.message };
  }
}

// ===== PROCESSAMENTO DE COMANDOS =====
// Aceita comandos com / (rastreamento) ou ! (financeiro)
async function processAdminCommand(from, text, msg = null) {
  try {
    const fromNumber = from.replace('@s.whatsapp.net', '').replace('@lid', '').replace(/:.+$/, '');
    
    // Detectar qual projeto pelo prefixo
    const prefix = text.charAt(0);
    const isFinanceiro = prefix === '!';
    const isRastreamento = prefix === '/';
    
    // Verificar se é comando de admin de grupo primeiro (prefixo $)
    const groupAdminCommands = ['$ban', '$kick', '$remover', '$promote', '$promover', '$demote', '$rebaixar', '$todos', '$all', '$marcar', '$link', '$fechar', '$close', '$abrir', '$open', '$antilink', '$automacao', '$automacoes', '$menu', '$help', '$ajuda', '$licenca', '$license', '$key'];
    const commandLower = text.split(' ')[0].toLowerCase();
    
    if (msg && groupAdminCommands.includes(commandLower)) {
      log.info(`[GROUP ADMIN] Comando de grupo detectado: ${commandLower}`);
      const result = await processGroupAdminCommand(from, text, msg);
      if (result) {
        return result;
      }
    }
    
    const apiUrl = isFinanceiro ? FINANCEIRO_API_URL : RASTREAMENTO_API_URL;
    const apiToken = isFinanceiro ? FINANCEIRO_TOKEN : RASTREAMENTO_TOKEN;
    const projectName = isFinanceiro ? 'Financeiro' : 'Rastreamento';
    
    log.info(`[${projectName}] Comando de ${fromNumber}: ${text}`);
    log.info(`[${projectName}] Usando token: ${apiToken.substring(0,4)}***`);
    
    const parts = text.trim().split(/\s+/);
    const commandWithPrefix = parts[0].toLowerCase(); // Manter o prefixo ! ou /
    const commandWithoutPrefix = parts[0].substring(1).toLowerCase(); // Sem prefixo
    const params = parts.slice(1);
    
    // Site-financeiro espera COM prefixo (!menu)
    // Site-rastreamento espera SEM prefixo (menu)
    const commandToSend = isFinanceiro ? commandWithPrefix : commandWithoutPrefix;
    
    // Se for comando !menu do financeiro, enviar poll interativa (com fallback)
    if (isFinanceiro && commandWithPrefix === '!menu') {
      try {
        if (!sock || !isReady) {
          log.warn(`[${projectName}] Bot não está pronto para enviar poll, usando fallback`);
          // Fallback: enviar para API normalmente
        } else {
          const pollQuestion = '👋 Olá! Como posso ajudar você hoje?';
          const pollOptions = [
            '📊 Ver saldo',
            '💰 Registrar receita',
            '💸 Registrar despesa',
            '📋 Ver tarefas',
            '❓ Ver menu completo'
          ];
          
          // Mapeamento de comandos para o contexto
          const commandMap = {
            0: '!saldo',
            1: '!receita',
            2: '!despesa',
            3: '!tarefas',
            4: '!menu'
          };
          
          log.info(`[${projectName}] Tentando enviar poll para ${from}`);
          
          try {
            const pollResult = await sendPoll(sock, from, pollQuestion, pollOptions, {
              type: 'menu_principal',
              commandMap: commandMap
            });
            log.success(`[${projectName}] Poll enviada via !menu com sucesso: ${pollResult.messageId}`);
            // Retornar sem message para não enviar texto adicional
            return { success: true, pollSent: true, messageId: pollResult.messageId };
          } catch (pollError) {
            // FALLBACK: Se poll falhar (WhatsApp antigo), usar menu textual
            log.warn(`[${projectName}] Poll falhou (${pollError.message}), usando fallback textual`);
            // Continuar para enviar para API normalmente (menu textual)
          }
        }
      } catch (pollError) {
        log.error(`[${projectName}] Erro ao tentar poll: ${pollError.message}`);
        // Fallback: enviar para API normalmente
      }
    }
    
    // Se for comando !comprovante do financeiro, aguardar foto
    if (isFinanceiro && commandWithPrefix === '!comprovante' && params.length > 0) {
      const transactionId = params[0];
      waitingPhoto.set(from, {
        transactionId,
        isFinanceiro: true,
        timestamp: Date.now()
      });
      return { 
        success: true, 
        message: '📸 Envie o comprovante agora (foto ou documento)',
        waiting_photo: true,
        photo_transaction_id: transactionId
      };
    }
    
    // Preparar payload da requisição
    const requestPayload = { 
      command: commandToSend,
      params, 
      args: params, // Compatibilidade com site-financeiro
      from: fromNumber,
      phone: fromNumber, // Compatibilidade com site-financeiro
      message: text // Compatibilidade com site-financeiro
    };
    
    // Se for comando de tarefas, incluir flag para retornar subtarefas
    if (commandToSend === '!tarefas' || commandToSend === 'tarefas') {
      requestPayload.include_subtasks = true;
      log.info(`[${projectName}] Comando tarefas detectado - solicitando subtarefas`);
      log.info(`[${projectName}] DEBUG - Payload completo: ${JSON.stringify(requestPayload).substring(0, 300)}`);
    }
    
    const response = await axios.post(
      `${apiUrl}/admin_bot_api.php`,
      requestPayload,
      {
        headers: {
          'Authorization': `Bearer ${apiToken}`,
          'Content-Type': 'application/json'
        },
        timeout: 30000
      }
    );
    
    const result = response.data;
    
    // Suporte tanto para rastreamento (photo_codigo) quanto financeiro (transaction_id)
    if (result.waiting_photo) {
      if (result.photo_codigo) {
        // Rastreamento
        waitingPhoto.set(from, {
          codigo: result.photo_codigo,
          isFinanceiro: false,
          timestamp: Date.now()
        });
      } else if (result.photo_transaction_id || result.transaction_id) {
        // Financeiro
        waitingPhoto.set(from, {
          transactionId: result.photo_transaction_id || result.transaction_id,
          isFinanceiro: true,
          timestamp: Date.now()
        });
      }
      
      setTimeout(() => {
        waitingPhoto.delete(from);
      }, 5 * 60 * 1000);
    }
    
    // Atualizar heartbeat
    lastHeartbeat = Date.now();
    
    return result;
  } catch (error) {
    log.error(`Erro comando: ${error.message}`);
    if (error.response) {
      log.error(`Resposta da API: ${JSON.stringify(error.response.data)}`);
    }
    return {
      success: false,
      message: '❌ Erro ao processar comando.\n' + (error.response?.data?.message || error.response?.data?.error || error.message)
    };
  }
}

async function processPhotoUpload(from, msg) {
  try {
    const waiting = waitingPhoto.get(from);
    if (!waiting) return false;
    
    if (Date.now() - waiting.timestamp > 5 * 60 * 1000) {
      waitingPhoto.delete(from);
      return false;
    }
    
    const imageMessage = msg.message.imageMessage || msg.message.documentMessage;
    if (!imageMessage) return false;
    
    // Download da mídia usando downloadMediaMessage
    const stream = await downloadMediaMessage(msg, 'buffer', {}, { logger: pino({ level: 'silent' }) });
    const chunks = [];
    for await (const chunk of stream) {
      chunks.push(chunk);
    }
    const buffer = Buffer.concat(chunks);
    
    const fromNumber = from.replace('@s.whatsapp.net', '').replace('@lid', '').replace(/:.+$/, '');
    const form = new FormData();
    
    // Determinar qual formato usar (rastreamento ou financeiro)
    if (waiting.isFinanceiro && waiting.transactionId) {
      // Formato financeiro
      const apiToken = FINANCEIRO_TOKEN;
      form.append('photo', buffer, {
        filename: `comprovante_${waiting.transactionId}_${Date.now()}.jpg`,
        contentType: 'image/jpeg'
      });
      form.append('transaction_id', waiting.transactionId);
      form.append('phone', fromNumber);
      
      const response = await axios.post(
        `${FINANCEIRO_API_URL}/admin_bot_photo.php`,
        form,
        {
          headers: {
            ...form.getHeaders(),
            'Authorization': `Bearer ${apiToken}`
          },
          timeout: 30000
        }
      );
      
      waitingPhoto.delete(from);
      
      if (response.data.success) {
        await sock.sendMessage(from, { 
          text: `✅ Comprovante anexado ao ID #${waiting.transactionId}`
        });
      } else {
        await sock.sendMessage(from, { 
          text: `❌ Erro ao anexar comprovante: ${response.data.error || 'Erro desconhecido'}`
        });
      }
    } else if (waiting.codigo) {
      // Formato rastreamento
      form.append('foto_pedido', buffer, {
        filename: `${waiting.codigo}.jpg`,
        contentType: 'image/jpeg'
      });
      form.append('codigo', waiting.codigo);
      form.append('from', fromNumber);
      form.append('token', RASTREAMENTO_TOKEN);
      
      const response = await axios.post(
        `${RASTREAMENTO_API_URL}/admin_bot_photo.php`,
        form,
        {
          headers: {
            ...form.getHeaders(),
            'Authorization': `Bearer ${RASTREAMENTO_TOKEN}`
          },
          timeout: 30000
        }
      );
      
      waitingPhoto.delete(from);
      
      await sock.sendMessage(from, { 
        text: response.data.message || '✅ Foto recebida e anexada ao pedido!'
      });
    } else {
      waitingPhoto.delete(from);
      await sock.sendMessage(from, { 
        text: '❌ Erro: formato de upload não reconhecido'
      });
      return true;
    }
    
    lastHeartbeat = Date.now();
    return true;
  } catch (error) {
    log.error(`Erro foto: ${error.message}`);
    waitingPhoto.delete(from);
    
    await sock.sendMessage(from, { 
      text: '❌ Erro ao processar a foto. Tente novamente.'
    });
    
    return true;
  }
}

// ===== FUNÇÃO PRINCIPAL DE CONEXÃO =====
async function start() {
  try {
    log.info('Iniciando conexão com WhatsApp...');
    
    const { version, isLatest } = await fetchLatestBaileysVersion();
    log.info(`WhatsApp Web version: ${version?.join('.')} (latest=${isLatest})`);

    const { state, saveCreds } = await useMultiFileAuthState('./auth');
    
    // Logger personalizado que silencia TUDO do Baileys
    const silentLogger = pino({
      level: 'silent',
      enabled: false
    });
    silentLogger.child = () => silentLogger;
    silentLogger.trace = () => {};
    silentLogger.debug = () => {};
    silentLogger.info = () => {};
    silentLogger.warn = () => {};
    silentLogger.error = () => {};
    silentLogger.fatal = () => {};
    
    sock = makeWASocket({
      auth: state,
      logger: silentLogger,
      version,
      browser: Browsers.appropriate('Desktop'),
      connectTimeoutMs: 60000,
      keepAliveIntervalMs: 20000,  // Keep-alive mais frequente (20s)
      retryRequestDelayMs: 500,
      defaultQueryTimeoutMs: 60000,
      emitOwnEvents: false,
      markOnlineOnConnect: true,
      syncFullHistory: false,
      printQRInTerminal: false, // Desativa QR duplicado
      getMessage: async (key) => {
        if (store) {
          const msg = await store.loadMessage(key.remoteJid, key.id);
          return msg?.message || undefined;
        }
        return { conversation: 'hello' };
      },
      shouldReconnectMessage: () => true,  // Sempre tentar reconectar
      shouldIgnoreJid: () => false
    });

    // Bind do store aos eventos do socket para manter sincronização
    store.bind(sock.ev);

    sock.ev.on('creds.update', saveCreds);

    // Listener para capturar eventos de poll em messages.upsert (QUANDO USUÁRIO VOTA)
    sock.ev.on('messages.upsert', async (m) => {
      // DEBUG: Log de todas as mensagens no primeiro handler
      const allMsgs = m.messages || [];
      for (const dbgMsg of allMsgs) {
        const dbgJid = dbgMsg.key?.remoteJid || 'unknown';
        const msgTypes = Object.keys(dbgMsg.message || {}).join(', ') || 'vazio';
        const dbgText = dbgMsg.message?.conversation || dbgMsg.message?.extendedTextMessage?.text || `[tipos: ${msgTypes}]`;
        log.info(`🔵 [HANDLER-POLL] Msg de ${dbgJid.split('@')[0]}: "${dbgText.substring(0, 50)}" | fromMe=${dbgMsg.key?.fromMe}`);
      }
      
      if (!isReady || !sock) return;
      
      try {
        const messages = m.messages || [];
        for (const msg of messages) {
          // Verificar se é uma atualização de poll (voto)
          if (msg.message?.pollUpdateMessage) {
            const pollUpdate = msg.message.pollUpdateMessage;
            const pollMessage = pollUpdate.pollCreationMessageKey;
            
            if (!pollMessage || !pollMessage.id) {
              continue;
            }
            
            const messageId = pollMessage.id;
            const pollJid = pollMessage.remoteJid || msg.key?.remoteJid; // JID do destino da poll
            const voterJid = msg.key?.remoteJid; // JID de quem votou (quem enviou o voto)
            
            if (!pollJid || typeof pollJid !== 'string' || pollJid.includes('@g.us')) {
              continue; // Ignorar grupos
            }
            
            if (!voterJid || typeof voterJid !== 'string' || voterJid.includes('@g.us')) {
              continue; // Ignorar grupos
            }
            
            const phoneNumber = voterJid.split('@')[0];
            if (!phoneNumber || phoneNumber.length < 10) {
              continue;
            }
            
            log.info(`[POLL] ✅ Voto detectado! messageId: ${messageId}, pollJid: ${pollJid}, voterJid: ${voterJid}`);
            
            // Buscar contexto da poll
            let pollCtx = pollContext.get(messageId);
            if (!pollCtx) {
              log.warn(`[POLL] Contexto não encontrado para messageId: ${messageId}, usando fallback`);
              pollCtx = {
                type: 'menu_principal',
                jid: pollJid,
                commandMap: {
                  0: '!saldo',
                  1: '!receita',
                  2: '!despesa',
                  3: '!tarefas',
                  4: '!menu'
                }
              };
            }
            
            // O voto está criptografado (encPayload, encIv)
            // Descriptografar manualmente usando decryptPollVote do Baileys
            try {
              const vote = pollUpdate.vote;
              if (!vote || !vote.encPayload || !vote.encIv) {
                log.warn(`[POLL] Voto não contém dados de criptografia necessários`);
                continue;
              }
              
              // Verificar se temos a chave de criptografia da poll
              if (!pollCtx.pollEncKey) {
                log.warn(`[POLL] pollEncKey não encontrada no contexto, tentando buscar da mensagem...`);
                // Tentar buscar a mensagem completa do store para obter pollEncKey
                try {
                  const fullMessage = await sock.loadMessage(pollJid, messageId);
                  log.info(`[POLL] DEBUG - Buscando pollEncKey da mensagem do store...`);
                  
                  // Tentar messageContextInfo.messageSecret primeiro (conforme código do Baileys)
                  if (fullMessage?.message?.messageContextInfo?.messageSecret) {
                    pollCtx.pollEncKey = Buffer.from(fullMessage.message.messageContextInfo.messageSecret);
                    pollContext.set(messageId, pollCtx);
                    log.info(`[POLL] ✅ pollEncKey obtida do store (messageSecret): ${pollCtx.pollEncKey.toString('hex').substring(0, 32)}...`);
                  } else if (fullMessage?.message?.pollCreationMessage?.encKey) {
                    // Fallback: tentar pollCreationMessage.encKey
                    pollCtx.pollEncKey = Buffer.from(fullMessage.message.pollCreationMessage.encKey);
                    pollContext.set(messageId, pollCtx);
                    log.info(`[POLL] ✅ pollEncKey obtida do store (encKey): ${pollCtx.pollEncKey.toString('hex').substring(0, 32)}...`);
                  } else {
                    log.error(`[POLL] ❌ pollEncKey não encontrada na mensagem do store`);
                    log.info(`[POLL] DEBUG - Estrutura da mensagem: ${JSON.stringify(Object.keys(fullMessage?.message || {})).substring(0, 300)}`);
                    continue;
                  }
                } catch (fetchError) {
                  log.error(`[POLL] Erro ao buscar mensagem do store: ${fetchError.message}`);
                  if (fetchError.stack) {
                    log.error(`[POLL] Stack: ${fetchError.stack}`);
                  }
                  continue;
                }
              }
              
              log.info(`[POLL] Tentando descriptografar voto...`);
              
              // Helper para forçar conversão para Buffer (mesmo vindo de JSON ou objetos genéricos)
              const forceBuffer = (data) => {
                if (!data) return undefined;
                if (Buffer.isBuffer(data)) return data;
                if (data.type === 'Buffer' && Array.isArray(data.data)) return Buffer.from(data.data); // Formato JSON do Buffer
                if (Array.isArray(data)) return Buffer.from(data);
                // Tenta converter objeto indexado {0: x, 1: y} para array
                if (typeof data === 'object') {
                  return Buffer.from(Object.values(data));
                }
                return Buffer.from(data);
              };
              
              // Log dos parâmetros antes da descriptografia
              log.info(`[POLL] DEBUG - Parâmetros:`);
              log.info(`[POLL]   pollMsgId: ${messageId}`);
              log.info(`[POLL]   pollCreatorJid: ${pollCtx.pollCreatorJid || sock.user?.id || pollJid}`);
              log.info(`[POLL]   voterJid: ${voterJid}`);
              log.info(`[POLL]   pollEncKey length: ${pollCtx.pollEncKey?.length || 'N/A'}`);
              log.info(`[POLL]   encPayload type: ${typeof vote.encPayload}, isBuffer: ${Buffer.isBuffer(vote.encPayload)}`);
              log.info(`[POLL]   encIv type: ${typeof vote.encIv}, isBuffer: ${Buffer.isBuffer(vote.encIv)}`);
              
              // --- FORÇAR CONVERSÃO PARA BUFFER (CORREÇÃO CRÍTICA) ---
              const rawEncPayload = vote.encPayload;
              const rawEncIv = vote.encIv;
              const rawPollKey = pollCtx.pollEncKey;

              const finalEncPayload = forceBuffer(rawEncPayload);
              const finalEncIv = forceBuffer(rawEncIv);
              const finalPollKey = forceBuffer(rawPollKey);
              
              console.log(`[POLL] DEBUG CONVERSÃO: Payload é Buffer? ${Buffer.isBuffer(finalEncPayload)}, IV é Buffer? ${Buffer.isBuffer(finalEncIv)}, Key é Buffer? ${Buffer.isBuffer(finalPollKey)}`);
              // -------------------------------------------------------
              
              // Normalizar JIDs para garantir match na descriptografia
              const creatorJidRaw = pollCtx.pollCreatorJid || sock.user?.id || pollJid;
              const creatorJid = jidNormalizedUser ? jidNormalizedUser(creatorJidRaw) : normalizeJidHelper(creatorJidRaw);
              const voterJidNormalized = jidNormalizedUser ? jidNormalizedUser(voterJid) : normalizeJidHelper(voterJid);
              
              console.log(`[POLL] DEBUG JIDS: Creator=${creatorJid}, Voter=${voterJidNormalized}`);
              
              // Descriptografar o voto usando decryptPollVote
              const decryptedVote = decryptPollVote(
                {
                  encPayload: finalEncPayload,
                  encIv: finalEncIv
                },
                {
                  pollCreatorJid: creatorJid,
                  pollMsgId: messageId,
                  pollEncKey: finalPollKey,
                  voterJid: voterJidNormalized
                }
              );
              
              log.info(`[POLL] ✅ Voto descriptografado! Dados: ${JSON.stringify(decryptedVote).substring(0, 200)}`);
              
              // Extrair o índice selecionado
              // O voto descriptografado contém selectedOptions que são hashes SHA256 das opções
              // Precisamos comparar com os hashes das opções originais para encontrar o índice
              let selectedOptionIndex = -1;
              
              if (decryptedVote.selectedOptions && decryptedVote.selectedOptions.length > 0) {
                const selectedHash = Buffer.from(decryptedVote.selectedOptions[0]).toString('hex');
                log.info(`[POLL] Hash selecionado: ${selectedHash}`);
                
                // Calcular hash de cada opção e comparar
                for (let i = 0; i < pollCtx.options.length; i++) {
                  const optionHash = crypto.createHash('sha256').update(pollCtx.options[i]).digest('hex');
                  if (optionHash === selectedHash) {
                    selectedOptionIndex = i;
                    log.info(`[POLL] ✅ Opção ${i} corresponde ao hash (${pollCtx.options[i]})`);
                    break;
                  }
                }
              }
              
              if (selectedOptionIndex === -1) {
                // Tentar alternativa: usar selectedOptionIndex diretamente se disponível
                if (typeof decryptedVote.selectedOptionIndex === 'number') {
                  selectedOptionIndex = decryptedVote.selectedOptionIndex;
                  log.info(`[POLL] Usando selectedOptionIndex direto: ${selectedOptionIndex}`);
                } else {
                  log.warn(`[POLL] Não foi possível determinar o índice selecionado`);
                  continue;
                }
              }
              
              // Processar o voto
              log.info(`[POLL] Processando voto: índice ${selectedOptionIndex}`);
              await processPollVote(messageId, voterJid, selectedOptionIndex, pollCtx);
              
            } catch (decryptError) {
              log.error(`[POLL] ❌ Erro ao descriptografar voto: ${decryptError.message}`);
              if (decryptError.stack) {
                log.error(`[POLL] Stack: ${decryptError.stack}`);
              }
              // Fallback: informar usuário
              try {
                await sock.sendMessage(voterJid, { 
                  text: `❌ Erro ao processar seu voto. Por favor, digite o comando manualmente (ex: !saldo, !receita, etc.)` 
                });
              } catch (sendError) {
                log.error(`[POLL] Erro ao enviar mensagem de fallback: ${sendError.message}`);
              }
            }
          }
        }
      } catch (error) {
        log.error(`[POLL] Erro em messages.upsert: ${error.message}`);
      }
    });

    // Tratamento de atualizações de polls (quando usuário vota)
    sock.ev.on('messages.update', async (updates) => {
      if (!isReady || !sock) return;
      
      if (!Array.isArray(updates)) return;
      
      for (const update of updates) {
        try {
          // DEBUG: Log completo quando há atualizações para identificar padrões
          if (update && update.update) {
            const updateKeys = Object.keys(update.update);
            // Log apenas se não for apenas status (para evitar spam)
            if (updateKeys.length > 1 || !updateKeys.includes('status')) {
              log.info(`[POLL] Update recebido - keys: ${updateKeys.join(', ')}`);
            }
          }
          
          // Verificar se é uma atualização de poll - múltiplas formas
          if (!update || !update.update) {
            continue;
          }
          
          // Tentar diferentes formatos de pollUpdate
          let pollUpdate = null;
          if (update.update.pollUpdate) {
            pollUpdate = update.update.pollUpdate;
          } else if (update.update.pollUpdateMessage) {
            pollUpdate = update.update.pollUpdateMessage;
          } else if (update.update.message?.pollUpdateMessage) {
            pollUpdate = update.update.message.pollUpdateMessage;
          }
          
          if (!pollUpdate) {
            continue;
          }
          
          log.info(`[POLL] ✅ PollUpdate detectado!`);
          log.info(`[POLL] pollUpdate keys: ${Object.keys(pollUpdate).join(', ')}`);
          log.info(`[POLL] pollUpdate completo: ${JSON.stringify(pollUpdate).substring(0, 500)}`);
          
          // Tentar diferentes formas de obter a chave da mensagem
          const pollMessage = pollUpdate.pollCreationMessageKey || pollUpdate.pollCreationMessage || pollUpdate.messageKey;
          
          // Validações para evitar crashes
          if (!pollMessage || !pollMessage.id) {
            log.warn(`[POLL] pollCreationMessageKey ou ID não encontrado`);
            continue;
          }
          
          const messageId = pollMessage.id;
          const jid = pollMessage.remoteJid || update.key?.remoteJid;
          
          log.info(`[POLL] messageId: ${messageId}, jid: ${jid}`);
          
          if (!jid || typeof jid !== 'string' || jid.includes('@g.us')) {
            log.warn(`[POLL] JID inválido ou grupo: ${jid}`);
            continue; // Ignorar grupos e JIDs inválidos
          }
          
          const phoneNumber = jid.split('@')[0];
          if (!phoneNumber || phoneNumber.length < 10) {
            log.warn(`[POLL] Número de telefone inválido: ${phoneNumber}`);
            continue; // Ignorar números inválidos
          }
          
          // Obter informações do voto
          const pollVote = pollUpdate.vote;
          log.info(`[POLL] pollVote: ${pollVote ? JSON.stringify(pollVote).substring(0, 200) : 'null'}`);
          
          if (!pollVote) {
            log.warn(`[POLL] pollVote não encontrado no pollUpdate`);
            continue;
          }
          
          // Tentar diferentes formatos de selectedOptions
          let selectedOptionIndex = null;
          
          if (pollVote.selectedOptions && Array.isArray(pollVote.selectedOptions) && pollVote.selectedOptions.length > 0) {
            selectedOptionIndex = pollVote.selectedOptions[0];
          } else if (pollVote.selectedOption !== undefined) {
            selectedOptionIndex = pollVote.selectedOption;
          } else if (typeof pollVote === 'number') {
            selectedOptionIndex = pollVote;
          } else {
            log.warn(`[POLL] Formato de voto não reconhecido: ${JSON.stringify(pollVote)}`);
            continue;
          }
          
          // Validar índice selecionado
          if (typeof selectedOptionIndex !== 'number' || selectedOptionIndex < 0 || selectedOptionIndex > 11) {
            log.warn(`[POLL] Índice de voto inválido: ${selectedOptionIndex}`);
            continue;
          }
          
          // ANTI-LOOP: Verificar se já processamos este voto
          const voteKey = `${messageId}-${selectedOptionIndex}-${jid}`;
          if (processedVotes.has(voteKey)) {
            log.info(`[POLL] Voto já processado, ignorando duplicado: ${voteKey}`);
            continue;
          }
          
          // ANTI-LOOP: Marcar voto como processado
          processedVotes.set(voteKey, Date.now());
          
          // STATE MANAGEMENT: Buscar contexto da poll (tentar do contexto ou do voto pendente)
          let pollCtx = pollContext.get(messageId);
          if (!pollCtx) {
            // Tentar obter do voto pendente
            const pending = pendingPollVotes.get(messageId);
            if (pending && pending.pollCtx) {
              pollCtx = pending.pollCtx;
              log.info(`[POLL] Contexto obtido do voto pendente`);
            } else {
              log.warn(`[POLL] Contexto não encontrado para messageId: ${messageId}`);
              log.info(`[POLL] Contextos disponíveis: ${Array.from(pollContext.keys()).join(', ')}`);
              // Fallback para mapeamento padrão do menu principal
              pollCtx = {
                type: 'menu_principal',
                jid: jid,
                commandMap: {
                  0: '!saldo',
                  1: '!receita',
                  2: '!despesa',
                  3: '!tarefas',
                  4: '!menu'
                }
              };
            }
          }
          
          // Remover voto pendente se encontramos o contexto
          if (pendingPollVotes.has(messageId)) {
            pendingPollVotes.delete(messageId);
          }
          
          log.info(`[POLL] ✅ Usuário ${phoneNumber} votou na opção ${selectedOptionIndex} (poll: ${pollCtx.type})`);
          
          // Mapear opção para comando usando o contexto
          const command = pollCtx.commandMap && pollCtx.commandMap[selectedOptionIndex];
          if (!command) {
            log.warn(`[POLL] Comando não encontrado para índice ${selectedOptionIndex} no contexto ${pollCtx.type}`);
            log.warn(`[POLL] commandMap disponível: ${JSON.stringify(pollCtx.commandMap)}`);
            continue;
          }
          
          log.info(`[POLL] Executando comando: ${command} (contexto: ${pollCtx.type})`);
          
          // Processar comando automaticamente
          try {
            const apiUrl = `${FINANCEIRO_API_URL}/admin_bot_api.php`;
            log.info(`[POLL] Enviando requisição para: ${apiUrl}`);
            const apiResponse = await axios.post(apiUrl, {
              phone: phoneNumber,
              command: command,
              args: [],
              message: command,
              source: 'poll',
              pollContext: pollCtx.type
            }, {
              headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${FINANCEIRO_TOKEN}`
              },
              timeout: 30000
            });
            
            log.info(`[POLL] Resposta da API recebida: ${JSON.stringify(apiResponse.data).substring(0, 200)}`);
            
            if (apiResponse && apiResponse.data && apiResponse.data.message) {
              await sock.sendMessage(jid, { text: apiResponse.data.message });
              log.success(`[POLL] ✅ Comando ${command} executado via poll (${pollCtx.type})`);
            } else {
              log.warn(`[POLL] API não retornou mensagem na resposta`);
            }
          } catch (apiError) {
            log.error(`[POLL] Erro ao processar comando da poll: ${apiError.message}`);
            if (apiError.response) {
              log.error(`[POLL] Resposta de erro: ${JSON.stringify(apiError.response.data)}`);
            }
            try {
              await sock.sendMessage(jid, { 
                text: `❌ Erro ao processar sua escolha. Digite ${command} manualmente.` 
              });
            } catch (sendError) {
              log.error(`[POLL] Erro ao enviar mensagem de erro: ${sendError.message}`);
            }
          }
        } catch (error) {
          log.error(`[POLL] Erro ao processar atualização de poll: ${error.message}`);
          if (error.stack) {
            log.error(`[POLL] Stack: ${error.stack}`);
          }
          // Não propagar erro para não quebrar o handler
        }
      }
    });

    sock.ev.on('connection.update', async (update) => {
      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        lastQR = qr;
        qrcode.generate(qr, { small: true });
        log.info(`QR Code gerado - Acesse http://localhost:${PORT}/qr`);
      }
      
      // Log de estados intermediários para debug
      if (connection === 'connecting') {
        log.info('🔄 Reconectando...');
      }

      if (connection === 'open') {
        isReady = true;
        reconnectAttempts = 0;
        disconnectTimestamps = [];  // Limpar histórico de desconexões
        isInLoopState = false;      // Sair do estado de loop
        isReconnecting = false;     // Resetar flag de reconexão
        connectionStartTime = Date.now();
        lastHeartbeat = Date.now();
        lastQR = null;              // Limpar QR antigo
        
        log.success('✅ Conectado ao WhatsApp com sucesso!');
        log.info(`Sistema de heartbeat: ${HEARTBEAT_INTERVAL/1000}s | Ping: ${PING_INTERVAL/1000}s`);
        
        startHeartbeat();
        startPing();
        
        // Carregar automações e configurações
        log.info('[AUTOMATIONS] Carregando automações e configurações...');
        loadBotSettings().then(() => {
          log.success('[AUTOMATIONS] Configurações carregadas!');
        }).catch(err => {
          log.warn(`[AUTOMATIONS] Erro ao carregar configurações: ${err.message}`);
        });
        
        // Carregar configurações de grupos (antilink, automações por grupo)
        loadGroupSettings().then(() => {
          log.success('[GROUP SETTINGS] Configurações de grupos carregadas!');
        }).catch(err => {
          log.warn(`[GROUP SETTINGS] Erro ao carregar: ${err.message}`);
        });
        
        loadAutomations().then(autos => {
          log.success(`[AUTOMATIONS] ${autos.length} automações prontas!`);
        }).catch(err => {
          log.warn(`[AUTOMATIONS] Erro ao carregar automações: ${err.message}`);
        });
      }

      if (connection === 'close') {
        isReady = false;
        stopHeartbeat();
        
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const errorMessage = lastDisconnect?.error?.message || '';
        const shouldReconnect = statusCode !== DisconnectReason.loggedOut && 
                                statusCode !== 401 && 
                                statusCode !== 405;
        
        let reason = '';
        switch (statusCode) {
          case DisconnectReason.loggedOut:
          case 401: 
            reason = 'Sessão deslogada. Apague ./auth e escaneie QR novamente.'; 
            break;
          case 405: 
            reason = 'Sessão inválida. Apague ./auth e refaça login.'; 
            break;
          case DisconnectReason.connectionReplaced:
          case 409: 
            reason = 'Outra sessão aberta substituiu esta conexão.'; 
            break;
          case DisconnectReason.connectionClosed:
          case 428: 
            reason = 'Conexão fechada pelo servidor.'; 
            break;
          case DisconnectReason.connectionLost:
          case 408: 
            reason = 'Conexão perdida (timeout ou rede).'; 
            break;
          case DisconnectReason.timedOut:
          case 440: 
            reason = 'Timeout de conexão.'; 
            break;
          case DisconnectReason.restartRequired:
          case 410: 
            reason = 'Reinício necessário pelo WhatsApp.'; 
            break;
          case DisconnectReason.multideviceMismatch:
          case 411: 
            reason = 'Conflito de multi-dispositivo.'; 
            break;
          default: 
            reason = `Código: ${statusCode || 'desconhecido'}`;
        }

        // Log detalhado da desconexão
        log.warn(`🔌 DESCONEXÃO DETECTADA:`);
        log.warn(`   Status: ${statusCode || 'N/A'}`);
        log.warn(`   Motivo: ${reason}`);
        if (errorMessage) {
          log.warn(`   Erro: ${errorMessage}`);
        }
        const uptime = connectionStartTime ? Math.round((Date.now() - connectionStartTime) / 1000) : 0;
        log.warn(`   Uptime antes da desconexão: ${Math.floor(uptime/60)}m ${uptime%60}s`);

        if (shouldReconnect) {
          log.warn(`🔄 Tentando reconectar automaticamente...`);
          await reconnect(reason);
        } else {
          log.error(`🔒 Desconectado permanentemente: ${reason}`);
          log.error('Ação necessária: Apague a pasta ./auth e reinicie o bot.');
        }
      }
    });

    sock.ev.on('messages.upsert', async (m) => {
      try {
        const msg = m.messages?.[0];
        if (!msg?.message) return;
        
        // Verificar se é mensagem antiga (mais de 2 minutos) - ignorar para evitar processar mensagens antigas
        const messageTimestamp = msg.messageTimestamp ? msg.messageTimestamp * 1000 : Date.now();
        const now = Date.now();
        const messageAge = now - messageTimestamp;
        const MAX_MESSAGE_AGE = 120000; // 2 minutos em milissegundos
        
        if (messageAge > MAX_MESSAGE_AGE && !msg.key.fromMe) {
          log.info(`[MESSAGE] Ignorando mensagem antiga (${Math.round(messageAge / 1000)}s atrás) de ${msg.key.remoteJid?.split('@')[0]}`);
          return; // Ignorar mensagens antigas
        }
        
        const remoteJid = msg.key.remoteJid;
        // Extrair texto de várias formas (mensagem normal, respondida, etc)
        let text = msg.message.conversation || 
                   msg.message.extendedTextMessage?.text || 
                   msg.message.imageMessage?.caption ||
                   msg.message.videoMessage?.caption ||
                   '';
        
        // Se for mensagem respondida, pegar o texto da mensagem original também
        // MAS: se o texto atual for um comando ($, /, !), manter o comando e não sobrescrever
        const isCommand = text.trim().startsWith('$') || text.trim().startsWith('/') || text.trim().startsWith('!');
        let quotedText = '';
        
        if (msg.message.extendedTextMessage?.contextInfo?.quotedMessage) {
          const quoted = msg.message.extendedTextMessage.contextInfo.quotedMessage;
          quotedText = quoted.conversation || 
                       quoted.extendedTextMessage?.text ||
                       quoted.imageMessage?.caption ||
                       quoted.videoMessage?.caption ||
                       '';
        }
        
        // Se não for comando, usar o texto da mensagem original quando respondida (para anti-link)
        if (quotedText && !isCommand) {
          text = quotedText; // Usar texto da mensagem original quando respondida
          log.info(`[ANTILINK] Mensagem respondida detectada, texto original: "${quotedText.substring(0, 50)}"`);
        } else if (isCommand) {
          // Se for comando, manter o texto do comando atual
          log.info(`[COMMAND] Comando detectado em resposta, mantendo comando: "${text.substring(0, 50)}"`);
        }
        
        const isFromMe = msg.key.fromMe;
        
        // DEBUG: Log de todas as mensagens recebidas
        log.info(`📩 Mensagem recebida de ${remoteJid.split('@')[0]}: "${text.substring(0, 50)}" | fromMe=${isFromMe}`);
        
        // Atualizar heartbeat em qualquer mensagem recebida
        lastHeartbeat = Date.now();
        
        // Aceitar comandos com / (rastreamento), ! (financeiro) ou $ (comandos de grupo)
        // Para comandos, aceitar também mensagens próprias (para testes)
        if (text.startsWith('/') || text.startsWith('!') || text.startsWith('$')) {
          log.info(`🎯 Comando detectado: "${text}" de ${remoteJid.split('@')[0]}`);
          const result = await processAdminCommand(remoteJid, text, msg);
          // Se poll foi enviada, não enviar mensagem de texto adicional
          if (result && !result.pollSent && result.message) {
            // Verificar se precisa enviar com mentions
            if (result.mentions && result.mentions.length > 0) {
              await sock.sendMessage(remoteJid, { 
                text: result.message, 
                mentions: result.mentions 
              });
            } else {
              await sock.sendMessage(remoteJid, { text: result.message });
            }
          }
          return;
        }
        
        // Para outras mensagens, ignorar se forem mensagens próprias
        if (isFromMe) {
          return;
        }
        
        // ===== VERIFICAR ANTI-LINK =====
        const isGroup = remoteJid.includes('@g.us');
        if (isGroup && text) {
          const antilinkConfig = antilinkGroups.get(remoteJid);
          
          // Debug: mostrar se antilink está ativo
          log.info(`[ANTILINK] Grupo: ${remoteJid.split('@')[0]}, Config: ${JSON.stringify(antilinkConfig || 'não configurado')}`);
          
          if (antilinkConfig?.enabled) {
            // Regex melhorado para detectar links (mais abrangente)
            const linkRegex = /(https?:\/\/[^\s]+)|(www\.[^\s]+)|([a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]*\.(com|net|org|br|io|me|tv|info|co|app|dev|xyz|site|online|store|shop|link|click|ly|bit\.ly|wa\.me|chat\.whatsapp\.com|gg|gg\.gg|tinyurl|t\.co|goo\.gl|youtu\.be|youtube\.com|instagram\.com|facebook\.com|twitter\.com|tiktok\.com)[^\s]*)/gi;
            
            // Testar o regex
            const matches = text.match(linkRegex);
            const hasLink = matches && matches.length > 0;
            
            log.info(`[ANTILINK] Texto: "${text.substring(0, 100)}"`);
            log.info(`[ANTILINK] Contém link: ${hasLink}, Matches: ${matches ? JSON.stringify(matches) : 'nenhum'}`);
            
            if (hasLink) {
              // Pegar JID do sender - pode vir de várias formas
              let senderJid = msg.key.participant || msg.key.remoteJid;
              
              // Se for mensagem respondida, pegar o participant da mensagem original
              if (msg.message.extendedTextMessage?.contextInfo?.participant) {
                senderJid = msg.message.extendedTextMessage.contextInfo.participant;
                log.info(`[ANTILINK] Sender da mensagem original (respondida): ${senderJid}`);
              }
              
              log.info(`[ANTILINK] Sender JID: ${senderJid}, RemoteJid: ${remoteJid}`);
              
              // Verificar se o sender é admin (admins podem enviar links)
              try {
                const groupMetadata = await sock.groupMetadata(remoteJid);
                
                // Verificar se o sender é admin
                const senderIsAdmin = groupMetadata.participants.some(p => {
                  const senderNumber = senderJid.split('@')[0].split(':')[0];
                  const participantNumber = p.id.split('@')[0].split(':')[0];
                  const match = p.id === senderJid || 
                                participantNumber === senderNumber ||
                                p.id.includes(senderNumber);
                  const isAdmin = match && (p.admin === 'admin' || p.admin === 'superadmin');
                  if (isAdmin) {
                    log.info(`[ANTILINK] Sender é admin, permitindo link`);
                  }
                  return isAdmin;
                });
                
                log.info(`[ANTILINK] Sender é admin? ${senderIsAdmin}`);
                
                if (!senderIsAdmin) {
                  // Remover o membro que enviou link
                  log.warn(`[ANTILINK] Link detectado de ${senderJid.split('@')[0]} no grupo ${groupMetadata.subject}`);
                  
                  try {
                    // Tentar remover usando o JID completo
                    await sock.groupParticipantsUpdate(remoteJid, [senderJid], 'remove');
                    const senderNumber = senderJid.split('@')[0];
                    await sock.sendMessage(remoteJid, { 
                      text: `🚫 *Anti-Link*\n\n@${senderNumber} foi removido por enviar link.\n\n_Links não são permitidos neste grupo._`,
                      mentions: [senderJid]
                    });
                    log.success(`[ANTILINK] ✅ Usuário ${senderNumber} removido por enviar link`);
                  } catch (removeError) {
                    log.error(`[ANTILINK] ❌ Erro ao remover usuário: ${removeError.message}`);
                    log.error(`[ANTILINK] Stack: ${removeError.stack}`);
                    // Se não conseguir remover, apenas avisar
                    await sock.sendMessage(remoteJid, { 
                      text: `⚠️ Link detectado! Não foi possível remover o usuário.\n\n_Erro: ${removeError.message}_`
                    });
                  }
                  return;
                } else {
                  log.info(`[ANTILINK] Sender é admin, não removendo`);
                }
              } catch (e) {
                log.error(`[ANTILINK] Erro ao verificar: ${e.message}`);
                log.error(`[ANTILINK] Stack: ${e.stack}`);
              }
            } else {
              log.info(`[ANTILINK] Nenhum link detectado no texto`);
            }
          }
        }
        
        // Verificar se está aguardando foto (rastreamento ou financeiro)
        if ((msg.message.imageMessage || msg.message.documentMessage) && waitingPhoto.has(remoteJid)) {
          await processPhotoUpload(remoteJid, msg);
          return;
        }
        
        // ===== PROCESSAR AUTOMAÇÕES =====
        // Verificar se a mensagem corresponde a alguma automação configurada
        if (text && text.trim()) {
          const automationProcessed = await processAutomations(remoteJid, text, msg);
          if (automationProcessed) {
            return; // Automação respondeu, não continuar
          }
          
          // ===== PROCESSAR IA (Chat Inteligente) =====
          // Se nenhuma automação respondeu, tentar IA (principalmente para chats privados)
          const isPrivateChat = !remoteJid.includes('@g.us');
          if (IA_ENABLED && (isPrivateChat || !IA_ONLY_PRIVATE)) {
            // Não processar comandos especiais ou mensagens muito curtas
            const lowerText = text.toLowerCase().trim();
            if (!lowerText.startsWith('/') && !lowerText.startsWith('$') && !lowerText.startsWith('!') && text.length >= 2) {
              const senderNumber = msg.key.participant ? msg.key.participant.split('@')[0] : remoteJid.split('@')[0];
              const iaResult = await processIAChat(remoteJid, text, senderNumber);
              
              if (iaResult && iaResult.success && iaResult.response) {
                await sock.sendMessage(remoteJid, { text: iaResult.response });
                log.success(`[IA] Respondeu para ${senderNumber}`);
                return; // IA respondeu
              }
            }
          }
        }

        if (AUTO_REPLY) {
          const now = Date.now();
          const last = lastReplyAt.get(remoteJid) || 0;
          if (now - last > AUTO_REPLY_WINDOW_MS) {
            const lower = (text || '').toLowerCase();
            if (lower.includes('oi') || lower.includes('olá') || lower.includes('ola')) {
              await sock.sendMessage(remoteJid, { 
                text: 'Olá! Como posso ajudar?\n\nDigite */menu* para ver os comandos disponíveis.' 
              });
              lastReplyAt.set(remoteJid, now);
            }
          }
        }
      } catch (e) { 
        log.error(`Erro ao processar mensagem: ${e.message}`);
      }
    });
    
    // Evento de erro geral
    sock.ev.on('error', (error) => {
      log.error(`Erro do socket: ${error.message}`);
      if (error.stack) {
        log.error(`Stack: ${error.stack}`);
      }
      // Não reconectar automaticamente em erros, deixar o connection.update tratar
    });

  } catch (error) {
    log.error(`Erro fatal ao iniciar: ${error.message}`);
    await reconnect('Erro fatal na inicialização');
  }
}

// ===== MIDDLEWARE DE AUTENTICAÇÃO =====
function auth(req, res, next) {
  // Tentar ler o token de várias formas (case-insensitive)
  const tokenRaw = req.headers['x-api-token'] || 
                   req.headers['X-Api-Token'] || 
                   req.headers['X-API-Token'] ||
                   req.headers['X-API-TOKEN'];
  
  // Limpar token recebido (remover espaços e caracteres invisíveis)
  const token = tokenRaw ? String(tokenRaw).trim() : null;
  const expectedToken = API_TOKEN ? String(API_TOKEN).trim() : null;
  
  // Debug log detalhado
  if (!token || token !== expectedToken) {
    const receivedToken = token ? `${token.substring(0, 4)}***${token.length > 8 ? token.substring(token.length - 4) : ''}` : 'null';
    const expectedTokenDisplay = expectedToken ? `${expectedToken.substring(0, 4)}***${expectedToken.length > 8 ? expectedToken.substring(expectedToken.length - 4) : ''}` : 'null';
    log.warn(`❌ Auth failed: received="${receivedToken}" (${token ? token.length : 0} chars), expected="${expectedTokenDisplay}" (${expectedToken ? expectedToken.length : 0} chars), url=${req.url}`);
    log.warn(`   Token recebido completo: "${token}"`);
    log.warn(`   Token esperado completo: "${expectedToken}"`);
    log.warn(`   Token recebido (raw): "${tokenRaw}"`);
    log.warn(`   Token esperado (raw): "${API_TOKEN}"`);
  }
  
  if (!expectedToken || !token || token !== expectedToken) {
    return res.status(401).json({ 
      ok: false, 
      error: 'unauthorized',
      debug: {
        received_token: token ? `${token.substring(0, 4)}***${token.length > 8 ? token.substring(token.length - 4) : ''}` : 'null',
        received_length: token ? token.length : 0,
        received_raw: tokenRaw || 'null',
        expected_token: `${expectedToken ? expectedToken.substring(0, 4) : ''}***${expectedToken && expectedToken.length > 8 ? expectedToken.substring(expectedToken.length - 4) : ''}`,
        expected_length: expectedToken ? expectedToken.length : 0,
        token_length_match: token ? token.length === expectedToken.length : false,
        token_exact_match: token === expectedToken,
        api_token_defined: !!API_TOKEN
      }
    });
  }
  next();
}

// ===== ENDPOINTS =====

// Status (sem autenticação - apenas verificação)
app.get('/status', (req, res) => {
  const uptime = connectionStartTime ? Math.round((Date.now() - connectionStartTime) / 1000) : 0;
  const memUsed = Math.round(process.memoryUsage().heapUsed / 1024 / 1024);
  
  res.json({ 
    ok: !isInLoopState, 
    ready: isReady,
    loopState: isInLoopState,
    uptime: uptime,
    uptimeFormatted: `${Math.floor(uptime/3600)}h ${Math.floor((uptime%3600)/60)}m ${uptime%60}s`,
    reconnectAttempts: reconnectAttempts,
    recentDisconnects: disconnectTimestamps.length,
    memoryMB: memUsed,
    lastHeartbeat: new Date(lastHeartbeat).toISOString(),
    message: isInLoopState ? 'LOOP DETECTADO - Delete ./auth e reinicie' : 'OK'
  });
});

// QR Code
app.get('/qr', async (req, res) => {
  if (!lastQR) {
    return res.status(404).send(`
      <html><body style="background:#111;color:#eee;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh">
        <div style="text-align:center">
          <h3>Nenhum QR disponível</h3>
          <p>O bot já está conectado ou aguardando inicialização.</p>
          <a href="/status" style="color:#4fc3f7">Ver status</a>
        </div>
      </body></html>
    `);
  }
  try {
    const dataUrl = await QRCodeImg.toDataURL(lastQR, { scale: 8, margin: 1 });
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    res.end(`
      <html><body style="background:#0f0f10;color:#eee;font-family:system-ui;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh">
        <div style="text-align:center">
          <h3>Escaneie o QR Code</h3>
          <img src="${dataUrl}" style="image-rendering: pixelated; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.5)" />
          <p style="margin-top:20px;color:#888">Após escanear, esta página mostrará "Nenhum QR disponível"</p>
        </div>
      </body></html>
    `);
  } catch (e) {
    res.status(500).send('Falha ao gerar QR');
  }
});

// Health check
app.get('/health', (req, res) => {
  res.json({
    status: isReady ? 'healthy' : 'unhealthy',
    timestamp: new Date().toISOString()
  });
});

// Resolve JID
async function resolveJidFromPhone(digits) {
  const pnJid = `${digits}@s.whatsapp.net`;
  try {
    const res = await sock.onWhatsApp(pnJid);
    if (Array.isArray(res) && res.length > 0) {
      const item = res[0];
      const mapped = item?.jid || pnJid;
      const exists = !!item?.exists || !!item?.isBusiness || !!item?.isEnterprise;
      return { exists, pnJid, mappedJid: mapped };
    }
    const exists = !!res?.exists;
    const mapped = res?.jid || pnJid;
    return { exists, pnJid, mappedJid: mapped };
  } catch (e) {
    return { exists: false, pnJid, mappedJid: pnJid, error: e?.message || String(e) };
  }
}

// Enviar mensagem
app.post('/send', auth, async (req, res) => {
  try {
    if (!isReady) return res.status(503).json({ ok: false, error: 'not_ready' });

    let { to, text } = req.body || {};
    if (!to || !text) return res.status(400).json({ ok: false, error: 'missing_params' });

    const digits = formatBrazilNumber(to);
    const { exists, pnJid, mappedJid, error } = await resolveJidFromPhone(digits);
    
    if (!exists) {
      return res.status(400).json({ ok: false, error: 'number_not_registered', to: digits, detail: error });
    }

    await sock.sendMessage(mappedJid, { text });
    lastHeartbeat = Date.now();
    
    log.info(`Mensagem enviada para ${digits}`);
    return res.json({ ok: true, to: digits, jid: mappedJid });
    
  } catch (err) {
    log.error(`Erro ao enviar: ${err.message}`);
    
    if (err?.output?.statusCode === 400 || err?.message?.includes('not a WhatsApp user')) {
      return res.status(400).json({ ok: false, error: 'number_not_registered' });
    }
    
    return res.status(500).json({ ok: false, error: err.message || 'unknown_error' });
  }
});

// Verificar número
app.post('/check', auth, async (req, res) => {
  try {
    if (!isReady) return res.status(503).json({ ok: false, error: 'not_ready' });

    const { to } = req.body || {};
    if (!to) return res.status(400).json({ ok: false, error: 'missing_params' });

    const digits = formatBrazilNumber(to);
    const { exists, pnJid, mappedJid, error } = await resolveJidFromPhone(digits);
    
    if (!exists) {
      return res.status(400).json({ ok: false, error: 'number_not_registered', to: digits, detail: error });
    }
    
    return res.json({ ok: true, to: digits, jid: mappedJid });
  } catch (e) {
    log.error(`Erro ao verificar: ${e.message}`);
    res.status(500).json({ ok: false, error: e.message });
  }
});

// Forçar reconexão (admin)
app.post('/reconnect', auth, async (req, res) => {
  if (isInLoopState) {
    return res.json({ 
      ok: false, 
      message: 'Bot está em estado de loop. Delete a pasta ./auth e reinicie.',
      loopState: true
    });
  }
  log.warn('Reconexão forçada via API');
  await reconnect('Solicitação via API');
  res.json({ ok: true, message: 'Reconexão iniciada' });
});

// Resetar estado de loop (admin)
app.post('/reset-loop', auth, async (req, res) => {
  log.warn('Reset de estado de loop via API');
  isInLoopState = false;
  disconnectTimestamps = [];
  reconnectAttempts = 0;
  res.json({ ok: true, message: 'Estado de loop resetado. Use /reconnect para reconectar.' });
});

// Recarregar automações
app.post('/reload-automations', auth, async (req, res) => {
  log.info('Recarregando automações via API...');
  try {
    // Forçar reload limpando cache
    lastAutomationsLoad = 0;
    
    const settings = await loadBotSettings();
    const automations = await loadAutomations();
    
    res.json({ 
      ok: true, 
      message: 'Automações recarregadas!',
      count: automations.length,
      settings_loaded: Object.keys(settings).length > 0
    });
  } catch (error) {
    res.json({ ok: false, error: error.message });
  }
});

// Listar automações carregadas
app.get('/automations', auth, (req, res) => {
  res.json({
    ok: true,
    count: automationsCache.length,
    automations: automationsCache.map(a => ({
      id: a.id,
      nome: a.nome,
      tipo: a.tipo,
      gatilho: a.gatilho,
      ativo: true
    })),
    settings: automationsSettings,
    cache_age_seconds: Math.round((Date.now() - lastAutomationsLoad) / 1000)
  });
});

// Enviar poll (enquete)
app.post('/send-poll', auth, async (req, res) => {
  try {
    if (!isReady) return res.status(503).json({ ok: false, error: 'not_ready' });
    
    const { to, question, options } = req.body || {};
    
    if (!to || !question || !options || !Array.isArray(options)) {
      return res.status(400).json({ 
        ok: false, 
        error: 'to, question e options (array) são obrigatórios. Options deve ter entre 2 e 12 itens.' 
      });
    }
    
    if (options.length < 2 || options.length > 12) {
      return res.status(400).json({ 
        ok: false, 
        error: 'Poll deve ter entre 2 e 12 opções' 
      });
    }

    const digits = formatBrazilNumber(to);
    const { exists, pnJid, mappedJid, error } = await resolveJidFromPhone(digits);
    
    if (!exists) {
      return res.status(400).json({ ok: false, error: 'number_not_registered', to: digits, detail: error });
    }

    const result = await sendPoll(sock, mappedJid, question, options);
    lastHeartbeat = Date.now();
    
    log.info(`Poll enviada para ${digits}`);
    return res.json({ ok: true, ...result, to: digits, jid: mappedJid });
  } catch (e) {
    log.error(`Erro ao enviar poll: ${e.message}`);
    return res.status(500).json({ ok: false, error: e.message });
  }
});

// ===== INICIALIZAÇÃO =====
app.listen(PORT, () => {
  log.success(`API WhatsApp rodando em http://localhost:${PORT}`);
  log.info('Endpoints: /status, /qr, /health, /send, /check, /send-poll, /reconnect');
});

// Iniciar conexão
start().catch((err) => {
  log.error(`Erro ao iniciar: ${err.message}`);
});

// Monitoramento de memória
setInterval(checkMemory, MEMORY_CHECK_INTERVAL);

// Tratamento de erros não capturados
process.on('uncaughtException', (err) => {
  log.error(`Exceção não capturada: ${err.message}`);
  log.error(err.stack);
});

process.on('unhandledRejection', (reason, promise) => {
  log.error(`Promise rejeitada: ${reason}`);
});

// Tratamento de sinais de término
process.on('SIGINT', async () => {
  log.warn('Recebido SIGINT, encerrando...');
  stopHeartbeat();
  if (sock) {
    try { sock.end(); } catch (e) {}
  }
  process.exit(0);
});

process.on('SIGTERM', async () => {
  log.warn('Recebido SIGTERM, encerrando...');
  stopHeartbeat();
  if (sock) {
    try { sock.end(); } catch (e) {}
  }
  process.exit(0);
});

log.info('Bot WhatsApp iniciado com sistema de estabilidade ativo');
log.info(`Heartbeat: ${HEARTBEAT_INTERVAL/1000}s | Ping: ${PING_INTERVAL/1000}s | Timeout: ${CONNECTION_TIMEOUT/1000}s | Max reconexões: ${MAX_RECONNECT_ATTEMPTS}`);
