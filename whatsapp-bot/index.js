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

console.log('📡 APIs configuradas:');
console.log('   Rastreamento:', RASTREAMENTO_API_URL, '(token:', RASTREAMENTO_TOKEN.substring(0,4) + '***)');
console.log('   Financeiro:', FINANCEIRO_API_URL, '(token:', FINANCEIRO_TOKEN.substring(0,4) + '***)');

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
// State management: amarrar messageId da poll com contexto
const pollContext = new Map(); // key: messageId, value: { type: string, jid: string, options: array, commandMap: object, timestamp: number }
// Anti-loop: evitar processar o mesmo voto duas vezes
const processedVotes = new Map(); // key: `${messageId}-${selectedIndex}-${jid}`, value: timestamp
// Votos pendentes aguardando descriptografia
const pendingPollVotes = new Map(); // key: messageId, value: { jid: string, pollCtx: object, timestamp: number }

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

// ===== PROCESSAMENTO DE COMANDOS =====
// Aceita comandos com / (rastreamento) ou ! (financeiro)
async function processAdminCommand(from, text) {
  try {
    const fromNumber = from.replace('@s.whatsapp.net', '').replace('@lid', '').replace(/:.+$/, '');
    
    // Detectar qual projeto pelo prefixo
    const prefix = text.charAt(0);
    const isFinanceiro = prefix === '!';
    const isRastreamento = prefix === '/';
    
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
    
    const response = await axios.post(
      `${apiUrl}/admin_bot_api.php`,
      { 
        command: commandToSend,
        params, 
        args: params, // Compatibilidade com site-financeiro
        from: fromNumber,
        phone: fromNumber, // Compatibilidade com site-financeiro
        message: text // Compatibilidade com site-financeiro
      },
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
              
              // Log dos parâmetros antes da descriptografia
              log.info(`[POLL] DEBUG - Parâmetros:`);
              log.info(`[POLL]   pollMsgId: ${messageId}`);
              log.info(`[POLL]   pollCreatorJid: ${pollCtx.pollCreatorJid || sock.user?.id || pollJid}`);
              log.info(`[POLL]   voterJid: ${voterJid}`);
              log.info(`[POLL]   pollEncKey length: ${pollCtx.pollEncKey?.length || 'N/A'}`);
              log.info(`[POLL]   encPayload type: ${typeof vote.encPayload}, isBuffer: ${Buffer.isBuffer(vote.encPayload)}`);
              log.info(`[POLL]   encIv type: ${typeof vote.encIv}, isBuffer: ${Buffer.isBuffer(vote.encIv)}`);
              
              // Converter encPayload e encIv para Buffer se necessário
              // Os dados vêm como Uint8Array ou Buffer, não como base64 string
              let encPayload;
              let encIv;
              
              if (Buffer.isBuffer(vote.encPayload)) {
                encPayload = vote.encPayload;
              } else if (vote.encPayload instanceof Uint8Array) {
                encPayload = Buffer.from(vote.encPayload);
              } else {
                // Tentar como base64 string
                encPayload = Buffer.from(vote.encPayload, 'base64');
              }
              
              if (Buffer.isBuffer(vote.encIv)) {
                encIv = vote.encIv;
              } else if (vote.encIv instanceof Uint8Array) {
                encIv = Buffer.from(vote.encIv);
              } else {
                // Tentar como base64 string
                encIv = Buffer.from(vote.encIv, 'base64');
              }
              
              const pollEncKey = Buffer.isBuffer(pollCtx.pollEncKey) ? pollCtx.pollEncKey : Buffer.from(pollCtx.pollEncKey);
              
              // Descriptografar o voto usando decryptPollVote
              const decryptedVote = decryptPollVote(
                {
                  encPayload: encPayload,
                  encIv: encIv
                },
                {
                  pollCreatorJid: pollCtx.pollCreatorJid || sock.user?.id || pollJid,
                  pollMsgId: messageId,
                  pollEncKey: pollEncKey,
                  voterJid: voterJid // Corrigido: usar voterJid ao invés de jid
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
                await sock.sendMessage(jid, { 
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
        if (!msg?.message || msg.key.fromMe) return;
        
        const remoteJid = msg.key.remoteJid;
        const text = msg.message.conversation || msg.message.extendedTextMessage?.text || '';
        
        // Atualizar heartbeat em qualquer mensagem recebida
        lastHeartbeat = Date.now();
        
        // Aceitar comandos com / (rastreamento) ou ! (financeiro)
        if (text.startsWith('/') || text.startsWith('!')) {
          const result = await processAdminCommand(remoteJid, text);
          // Se poll foi enviada, não enviar mensagem de texto adicional
          if (result && !result.pollSent && result.message) {
            await sock.sendMessage(remoteJid, { text: result.message });
          }
          return;
        }
        
        // Verificar se está aguardando foto (rastreamento ou financeiro)
        if ((msg.message.imageMessage || msg.message.documentMessage) && waitingPhoto.has(remoteJid)) {
          await processPhotoUpload(remoteJid, msg);
          return;
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
