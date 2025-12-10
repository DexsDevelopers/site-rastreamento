/* WhatsApp Bot local - Baileys + Express
 * - Exibe QR no console para logar
 * - Sistema de reconexão automática
 * - Heartbeat para manter conexão ativa
 * - Endpoints:
 *   GET  /status
 *   GET  /qr
 *   POST /send  { to: "55DDDNUMERO", text: "mensagem" }  Header: x-api-token
 *   POST /check { to: "55DDDNUMERO" } Header: x-api-token
 */
import { default as makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers } from '@whiskeysockets/baileys';
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
// Limpar token completamente - remover espaços e caracteres invisíveis
let rawToken = process.env.API_TOKEN || 'troque-este-token';
// Remover todos os espaços e caracteres não alfanuméricos
rawToken = String(rawToken).trim().replace(/\s+/g, '');
// Manter apenas letras e números
rawToken = rawToken.replace(/[^a-zA-Z0-9]/g, '');
const API_TOKEN = rawToken;

// Log do token carregado (mascarado por segurança)
console.log('🔑 API_TOKEN carregado:', API_TOKEN ? `${API_TOKEN.substring(0, 4)}***${API_TOKEN.length > 8 ? API_TOKEN.substring(API_TOKEN.length - 4) : ''} (${API_TOKEN.length} chars)` : 'NÃO DEFINIDO');
if (API_TOKEN === 'troque-este-token') {
  console.warn('⚠️  AVISO: API_TOKEN ainda está no valor padrão! Configure no arquivo .env');
}

const AUTO_REPLY = String(process.env.AUTO_REPLY || 'false').toLowerCase() === 'true';
const AUTO_REPLY_WINDOW_MS = Number(process.env.AUTO_REPLY_WINDOW_MS || 3600000); // 1h
const ADMIN_API_URL = process.env.ADMIN_API_URL || 'https://cornflowerblue-fly-883408.hostingersite.com';
const ADMIN_NUMBERS = (process.env.ADMIN_NUMBERS || '').split(',').map(n => formatBrazilNumber(n)).filter(Boolean);

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

// Controle simples para evitar auto-resposta repetida
const lastReplyAt = new Map(); // key: jid, value: timestamp
// Controle de comandos aguardando foto
const waitingPhoto = new Map(); // key: jid, value: { codigo: string, timestamp: number }

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

// ===== MONITORAMENTO DE MEMÓRIA =====
function checkMemory() {
  const used = process.memoryUsage();
  const heapUsedMB = Math.round(used.heapUsed / 1024 / 1024);
  const heapTotalMB = Math.round(used.heapTotal / 1024 / 1024);
  
  if (heapUsedMB > 500) {
    log.warn(`Memória alta: ${heapUsedMB}MB / ${heapTotalMB}MB`);
    
    // Forçar garbage collection se disponível
    if (global.gc) {
      log.info('Forçando garbage collection...');
      global.gc();
    }
    
    // Limpar caches antigos
    const now = Date.now();
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
  }
}

// ===== PROCESSAMENTO DE COMANDOS =====
async function processAdminCommand(from, text) {
  try {
    const fromNumber = from.replace('@s.whatsapp.net', '').replace('@lid', '').replace(/:.+$/, '');
    
    log.info(`Comando de ${fromNumber}: ${text}`);
    
    const parts = text.trim().split(/\s+/);
    const command = parts[0].substring(1).toLowerCase();
    const params = parts.slice(1);
    
    const response = await axios.post(
      `${ADMIN_API_URL}/admin_bot_api.php`,
      { command, params, from: fromNumber },
      {
        headers: {
          'Authorization': `Bearer ${API_TOKEN}`,
          'Content-Type': 'application/json'
        },
        timeout: 30000
      }
    );
    
    const result = response.data;
    
    if (result.waiting_photo && result.photo_codigo) {
      waitingPhoto.set(from, {
        codigo: result.photo_codigo,
        timestamp: Date.now()
      });
      
      setTimeout(() => {
        waitingPhoto.delete(from);
      }, 5 * 60 * 1000);
    }
    
    // Atualizar heartbeat
    lastHeartbeat = Date.now();
    
    return result;
  } catch (error) {
    log.error(`Erro comando: ${error.message}`);
    return {
      success: false,
      message: '❌ Erro ao processar comando.\n' + (error.response?.data?.message || error.message)
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
    
    const imageMessage = msg.message.imageMessage;
    if (!imageMessage) return false;
    
    const buffer = await sock.downloadMediaMessage(msg);
    
    const form = new FormData();
    form.append('foto_pedido', buffer, {
      filename: `${waiting.codigo}.jpg`,
      contentType: 'image/jpeg'
    });
    form.append('codigo', waiting.codigo);
    form.append('from', from.replace('@s.whatsapp.net', '').replace('@lid', ''));
    form.append('token', API_TOKEN);
    
    const response = await axios.post(
      `${ADMIN_API_URL}/admin_bot_photo.php`,
      form,
      {
        headers: {
          ...form.getHeaders(),
          'Authorization': `Bearer ${API_TOKEN}`
        },
        timeout: 30000
      }
    );
    
    waitingPhoto.delete(from);
    
    await sock.sendMessage(from, { 
      text: response.data.message || '✅ Foto recebida e anexada ao pedido!'
    });
    
    lastHeartbeat = Date.now();
    return true;
  } catch (error) {
    log.error(`Erro foto: ${error.message}`);
    waitingPhoto.delete(from);
    
    await sock.sendMessage(from, { 
      text: '❌ Erro ao processar a foto. Tente novamente com /foto CODIGO'
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
      getMessage: async () => undefined, // Evita logs de mensagens antigas
      shouldReconnectMessage: () => true,  // Sempre tentar reconectar
      shouldIgnoreJid: () => false
    });

    sock.ev.on('creds.update', saveCreds);

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
        
        if (text.startsWith('/')) {
          const result = await processAdminCommand(remoteJid, text);
          if (result.message) {
            await sock.sendMessage(remoteJid, { text: result.message });
          }
          return;
        }
        
        if (msg.message.imageMessage && waitingPhoto.has(remoteJid)) {
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

// Status (com autenticação para debug)
app.get('/status', auth, (req, res) => {
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

// ===== INICIALIZAÇÃO =====
app.listen(PORT, () => {
  log.success(`API WhatsApp rodando em http://localhost:${PORT}`);
  log.info('Endpoints: /status, /qr, /health, /send, /check, /reconnect');
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
