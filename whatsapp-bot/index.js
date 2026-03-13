/* WhatsApp Bot Centralizado - Baileys + Express
 * - Bot para projeto: Rastreamento (/)
 * - Exibe QR no console para logar
 * - Sistema de reconexão automática
 * - Heartbeat para manter conexão ativa
 * - Sistema de polls (enquetes)
 * - Endpoints:
 *   GET  /status
 *   GET  /qr
 *   POST /send  { to: "55DDDNUMERO", text: "mensagem" }  Header: x-api-token
 *   POST /check { to: "55DDDNUMERO" } Header: x-api-token
 *   POST /send-poll { to: "55DDDNUMERO", question: "...", options: [...] }  Header: x-api-token
 */
import { default as makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers, downloadMediaMessage, proto } from '@whiskeysockets/baileys';
// Exportar variáveis de estado para integração direta
export let isReady = false;
export let lastQR = null;
export let sock = null;
import { decryptPollVote } from '@whiskeysockets/baileys/lib/Utils/process-message.js';
import { jidNormalizedUser } from '@whiskeysockets/baileys/lib/WABinary/jid-utils.js';
import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import os from 'os';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
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

  // Se for muito longo (provável LID ou JID), não adicionar 55
  if (digits.length > 13) return digits;

  if (!digits.startsWith('55')) digits = '55' + digits;
  return digits;
}

// Verifica se o JID é um grupo (incluindo comunidades/newsletters)
function isGroupJid(jid) {
  if (!jid || typeof jid !== 'string') return false;
  return jid.includes('@g.us') || jid.includes('@newsletter');
}

const app = express();
app.use(cors());
app.use(express.json());

// Criar roteador modular para o bot
export const botRouter = express.Router();
botRouter.use(cors());
botRouter.use(express.json());
const PORT = Number(process.env.API_PORT || process.env.PORT || 3001); // Fix porta para 3001

// Servir o Frontend Web App (React) que está na pasta webapp/dist
app.use(express.static(path.join(__dirname, '../webapp/dist')));

app.get('/health', (req, res) => res.status(200).send('OK'));

// Iniciar servidor IMEDIATAMENTE (apenas se executado diretamente)
let server;
// Detecção mais robusta de execução direta vs importação
const isMainModule = (import.meta.url === `file:///${path.resolve(process.argv[1]).replace(/\\/g, '/')}`) ||
  (process.argv[1] && process.argv[1].includes('whatsapp-bot/index.js'));

if (isMainModule) {
  console.log('🚀 [BOT] Standalone');
  server = app.listen(PORT, '0.0.0.0', () => {
    console.log(`✅ [BOT] Porta: ${PORT}`);
    if (process.send) process.send('ready');
  });
} else {
  console.log('📦 [BOT] Integrado');
}

// Exportar função de inicialização para integração
export async function initWhatsAppBot(mainApp = null) {
  if (mainApp) {
    log.info('Integrando rotas do bot no app principal...');
    // Copiar rotas para o app principal
    // (A implementação real precisará que as rotas sejam definidas de forma modular)
  }
  start().catch((err) => {
    log.error(`Erro crítico ao iniciar bot integrado: ${err.message}`);
    log.error(err.stack);
  });
}

// DEBUG: Ver porta configurada
console.log('🔌 DEBUG - API_PORT do .env:', process.env.API_PORT || 'não definido');
console.log('🔌 DEBUG - Porta final:', PORT);
console.log('🔌 DEBUG - Escutando em 0.0.0.0');

// DEBUG: Ver exatamente o que está no .env
const rawEnvToken = process.env.API_TOKEN;
console.log('🔍 DEBUG - Token do .env (raw):', rawEnvToken ? `"${rawEnvToken}"` : 'undefined');
console.log('🔍 DEBUG - Comprimento raw:', rawEnvToken ? rawEnvToken.length : 0);
if (rawEnvToken) {
  const bytes = Buffer.from(rawEnvToken, 'utf8');
  console.log('🔍 DEBUG - Bytes hex:', bytes.toString('hex'));
}

// Limpar token completamente - remover espaços e caracteres invisíveis
let rawToken = process.env.API_TOKEN || 'lucastav8012';
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

// URLs das APIs
const RASTREAMENTO_API_URL = process.env.RASTREAMENTO_API_URL || 'https://transloggi.site';

// Tokens por projeto
const RASTREAMENTO_TOKEN = process.env.RASTREAMENTO_TOKEN || process.env.API_TOKEN || 'lucastav8012';

const ADMIN_API_URL = RASTREAMENTO_API_URL; // Compatibilidade
const ADMIN_NUMBERS = (process.env.ADMIN_NUMBERS || '').split(',').map(n => formatBrazilNumber(n)).filter(Boolean);

// ===== SISTEMA DE LICENÇAS DE GRUPOS =====
const LICENSE_CHECK_ENABLED = String(process.env.LICENSE_CHECK_ENABLED || 'true').toLowerCase() === 'true';
const LICENSE_CACHE_TTL = 300000; // 5 minutos de cache
const groupLicenseCache = new Map(); // key: groupJid, value: { valid: boolean, expires: timestamp, data: object }

// ===== SISTEMA DE IA PARA CHAT PRIVADO =====
const IA_ENABLED = String(process.env.IA_ENABLED || 'false').toLowerCase() === 'true';
const IA_ONLY_PRIVATE = String(process.env.IA_ONLY_PRIVATE || 'true').toLowerCase() === 'true'; // Só responde no privado

// ===== SISTEMA DE SEGURANÇA ANTI-BAN =====
const SAFETY_ENABLED = String(process.env.SAFETY_ENABLED || 'false').toLowerCase() === 'true'; // DESABILITADO por padrão
const MAX_MESSAGES_PER_MINUTE = Number(process.env.MAX_MESSAGES_PER_MINUTE || 20); // Máximo 20 mensagens/minuto
const MAX_MESSAGES_PER_HOUR = Number(process.env.MAX_MESSAGES_PER_HOUR || 200); // Máximo 200 mensagens/hora
const MIN_DELAY_BETWEEN_MESSAGES = Number(process.env.MIN_DELAY_BETWEEN_MESSAGES || 1000); // 1 segundo mínimo entre mensagens
const MAX_MESSAGES_PER_CHAT_PER_MINUTE = Number(process.env.MAX_MESSAGES_PER_CHAT_PER_MINUTE || 5); // 5 mensagens por chat/minuto
const CHECK_CONTACT_BEFORE_SEND = String(process.env.CHECK_CONTACT_BEFORE_SEND || 'true').toLowerCase() === 'true';
const ENABLE_DELAYS = String(process.env.ENABLE_DELAYS || 'true').toLowerCase() === 'true';
const SIMULATE_TYPING = String(process.env.SIMULATE_TYPING || 'true').toLowerCase() === 'true';
const SIMULATE_TYPING_GROUPS = String(process.env.SIMULATE_TYPING_GROUPS || 'false').toLowerCase() === 'true'; // Falso por padrão para evitar lag em grupos grandes
const RANDOM_SUFFIX_ENABLED = String(process.env.RANDOM_SUFFIX_ENABLED || 'true').toLowerCase() === 'true';

// Contadores de segurança
const messageCounts = new Map(); // key: jid, value: { count: number, resetAt: timestamp }
const lastMessageTime = new Map(); // key: jid, value: timestamp
const hourlyCounts = new Map(); // key: 'global', value: { count: number, resetAt: timestamp }
const chatMessageCounts = new Map(); // key: jid, value: { count: number, resetAt: timestamp }
const blacklist = new Set(); // Números bloqueados temporariamente
const commandCooldowns = new Map(); // key: `${jid}-${command}`, value: timestamp
const COMMAND_COOLDOWN_MS = 2000; // 2 segundos entre comandos do mesmo tipo

// Verificar cooldown de comando
function checkCommandCooldown(jid, command) {
  if (!SAFETY_ENABLED) return { allowed: true };

  const key = `${jid}-${command}`;
  const lastUse = commandCooldowns.get(key);

  if (!lastUse) {
    return { allowed: true };
  }

  const timeSinceLastUse = Date.now() - lastUse;
  if (timeSinceLastUse < COMMAND_COOLDOWN_MS) {
    const waitTime = COMMAND_COOLDOWN_MS - timeSinceLastUse;
    return { allowed: false, waitTime };
  }

  return { allowed: true };
}

// Registrar uso de comando
function registerCommandUse(jid, command) {
  if (!SAFETY_ENABLED) return;

  const key = `${jid}-${command}`;
  commandCooldowns.set(key, Date.now());

  // Limpar cooldowns antigos (mais de 1 hora)
  const oneHourAgo = Date.now() - 3600000;
  for (const [k, v] of commandCooldowns.entries()) {
    if (v < oneHourAgo) commandCooldowns.delete(k);
  }
}

// ===== MODO AQUECIMENTO (WARMING) =====
const WARMING_PHRASES = [
  "Olá! Tudo bem?",
  "Oi! Já te respondo em um instante.",
  "Bom dia! Como posso ajudar?",
  "Tudo certo aqui, e com você?",
  "Aguarde um momento, por favor.",
  "Estou verificando aqui...",
  "👍",
  "Oi oi",
  "Pode falar",
  "Só um minuto",
  "Estou meio ocupado agora, mas já vejo",
  "Recebi sua mensagem!",
  "😄",
  "Qualquer dúvida é só chamar",
  "Opa, tudo bem?"
];

// Status aleatórios para parecer ativo
const WARMING_STATUSES = [
  "Trabalhando muito! 🚀",
  "Disponível para atendimento ✅",
  "Foco total hoje 💪",
  "Atendendo clientes 📞",
  "Ótimo dia a todos! ☀️",
  "Sempre evoluindo 📈",
  "Pausa para o café ☕",
  "Online e operante 🤖",
  "Resolvendo pendências 📝",
  "Tudo flui 🌊"
];

let warmingStatusTimer = null;

// Loop para postar status automaticamente
function startWarmingStatusLoop() {
  if (warmingStatusTimer) clearInterval(warmingStatusTimer);

  // Intervalo aleatório entre 2 e 5 horas
  const interval = (Math.floor(Math.random() * (5 - 2 + 1)) + 2) * 60 * 60 * 1000;

  warmingStatusTimer = setInterval(async () => {
    if (!automationsSettings.warming_mode || !sock || !isReady) return;

    try {
      const status = WARMING_STATUSES[Math.floor(Math.random() * WARMING_STATUSES.length)];
      log.info(`[WARMING-STATUS] Postando status: "${status}"`);

      await sock.sendMessage('status@broadcast', { text: status, backgroundColor: '#3b82f6', font: 2 });
    } catch (e) {
      log.error(`[WARMING-STATUS] Erro ao postar: ${e.message}`);
    }
  }, interval);

  log.info(`[WARMING] Loop de status iniciado (Intervalo: ~${Math.round(interval / 1000 / 60)}min)`);
}

// Processar mensagem de aquecimento (resposta natural)
async function processWarming(remoteJid, text) {
  // 30% de chance de responder para não floodar
  if (Math.random() > 0.3) {
    log.info(`[WARMING] Ignorando mensagem para parecer natural (Chance 30%)`);
    return;
  }

  const phrase = WARMING_PHRASES[Math.floor(Math.random() * WARMING_PHRASES.length)];

  // Delay aleatório entre 5 e 20 segundos
  const delay = Math.floor(Math.random() * (20000 - 5000 + 1)) + 5000;

  log.info(`[WARMING] Agendando resposta para ${remoteJid.split('@')[0]} em ${delay / 1000}s: "${phrase}"`);

  setTimeout(async () => {
    if (!isReady || !sock) return;

    try {
      // Simular "Digitando..."
      await sock.sendPresenceUpdate('composing', remoteJid);

      // Esperar mais 3-6 segundos digitando
      setTimeout(async () => {
        await sock.sendPresenceUpdate('paused', remoteJid);
        await safeSendMessage(sock, remoteJid, { text: phrase });
        log.success(`[WARMING] Resposta enviada para ${remoteJid.split('@')[0]}`);
      }, Math.floor(Math.random() * 3000) + 3000);

    } catch (e) {
      log.error(`[WARMING] Erro ao responder: ${e.message}`);
    }
  }, delay);
}

// Função para verificar e atualizar rate limits
function checkRateLimit(jid) {
  if (!SAFETY_ENABLED) return { allowed: true };

  const now = Date.now();

  // Verificar blacklist
  if (blacklist.has(jid)) {
    return { allowed: false, reason: 'blacklisted', retryAfter: 3600000 }; // 1 hora
  }

  // Limite por chat (mensagens por minuto)
  const chatKey = jid;
  let chatCount = chatMessageCounts.get(chatKey) || { count: 0, resetAt: now + 60000 };
  if (now > chatCount.resetAt) {
    chatCount = { count: 0, resetAt: now + 60000 };
  }
  if (chatCount.count >= MAX_MESSAGES_PER_CHAT_PER_MINUTE) {
    const waitTime = chatCount.resetAt - now;
    return { allowed: false, reason: 'chat_rate_limit', retryAfter: waitTime };
  }

  // Limite global por minuto
  let globalCount = messageCounts.get('global') || { count: 0, resetAt: now + 60000 };
  if (now > globalCount.resetAt) {
    globalCount = { count: 0, resetAt: now + 60000 };
  }
  if (globalCount.count >= MAX_MESSAGES_PER_MINUTE) {
    const waitTime = globalCount.resetAt - now;
    return { allowed: false, reason: 'global_rate_limit', retryAfter: waitTime };
  }

  // Limite global por hora
  let hourlyCount = hourlyCounts.get('global') || { count: 0, resetAt: now + 3600000 };
  if (now > hourlyCount.resetAt) {
    hourlyCount = { count: 0, resetAt: now + 3600000 };
  }
  if (hourlyCount.count >= MAX_MESSAGES_PER_HOUR) {
    const waitTime = hourlyCount.resetAt - now;
    return { allowed: false, reason: 'hourly_rate_limit', retryAfter: waitTime };
  }

  // Verificar delay mínimo entre mensagens
  const lastTime = lastMessageTime.get(jid);
  if (lastTime && ENABLE_DELAYS) {
    const timeSinceLastMessage = now - lastTime;
    if (timeSinceLastMessage < MIN_DELAY_BETWEEN_MESSAGES) {
      const waitTime = MIN_DELAY_BETWEEN_MESSAGES - timeSinceLastMessage;
      return { allowed: false, reason: 'min_delay', retryAfter: waitTime };
    }
  }

  return { allowed: true };
}

// Função para registrar envio de mensagem
function registerMessageSent(jid) {
  if (!SAFETY_ENABLED) return;

  const now = Date.now();

  // Atualizar contador por chat
  let chatCount = chatMessageCounts.get(jid) || { count: 0, resetAt: now + 60000 };
  if (now > chatCount.resetAt) {
    chatCount = { count: 0, resetAt: now + 60000 };
  }
  chatCount.count++;
  chatMessageCounts.set(jid, chatCount);

  // Atualizar contador global por minuto
  let globalCount = messageCounts.get('global') || { count: 0, resetAt: now + 60000 };
  if (now > globalCount.resetAt) {
    globalCount = { count: 0, resetAt: now + 60000 };
  }
  globalCount.count++;
  messageCounts.set('global', globalCount);

  // Atualizar contador global por hora
  let hourlyCount = hourlyCounts.get('global') || { count: 0, resetAt: now + 3600000 };
  if (now > hourlyCount.resetAt) {
    hourlyCount = { count: 0, resetAt: now + 3600000 };
  }
  hourlyCount.count++;
  hourlyCounts.set('global', hourlyCount);

  // Registrar último envio
  lastMessageTime.set(jid, now);
}

// Função para verificar se contato existe antes de enviar
async function checkContactExists(sock, jid) {
  // Ignorar verificação se desabilitada
  if (!CHECK_CONTACT_BEFORE_SEND || !SAFETY_ENABLED) return true;

  try {
    // 1. Grupos e Comunidades sempre existem (se estamos neles)
    if (isGroupJid(jid)) {
      return true;
    }

    // 2. LIDs (Identificadores de Dispositivo)
    // Eles não respondem ao 'onWhatsApp' tradicional da mesma forma.
    // Se o JID termina em @lid, assumimos que é válido se o formato estiver correto (apenas números).
    if (jid.includes('@lid')) {
      const userPart = jid.split('@')[0];
      // Verificação simples: se tem só números, é um LID "potencialmente" válido.
      // Infelizmente a Baileys não tem um check confiável para LIDs sem tentar enviar.
      // Vamos assumir TRUE para LIDs para não bloquear envios legítimos.
      if (/^\d+$/.test(userPart)) {
        return true;
      }
      return false;
    }

    // 3. Normalização para @s.whatsapp.net
    let normalizedJid = jid;
    if (jid.includes(':')) {
      normalizedJid = jid.split(':')[0] + '@' + jid.split('@')[1];
    }

    // Garantir sufixo correto
    if (!normalizedJid.includes('@s.whatsapp.net')) {
      // Se não tem sufixo nenhum, adiciona o padrão
      normalizedJid = normalizedJid.replace(/@.*$/, '') + '@s.whatsapp.net';
    }

    // 4. Verificação Real na API do WhatsApp
    const onWhatsApp = await sock.onWhatsApp(normalizedJid);

    if (!onWhatsApp || onWhatsApp.length === 0) {
      log.warn(`[SAFETY] Número ${normalizedJid} não está no WhatsApp`);
      return false;
    }

    return onWhatsApp[0].exists || onWhatsApp[0].isBusiness || onWhatsApp[0].isEnterprise;
  } catch (error) {
    log.error(`[SAFETY] Erro ao verificar contato: ${error.message}`);
    // Fail-open: na dúvida (erro de rede, etc), permite o envio para não travar filas
    return true;
  }
}

// Função para gerar sufixo aleatório para quebrar padrões
function generateRandomSuffix() {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let result = '';
  // Gera entre 2 e 4 caracteres aleatórios
  const length = Math.floor(Math.random() * 3) + 2;
  for (let i = 0; i < length; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return `\n\n[ID: ${result}]`;
}

// Função wrapper segura para sendMessage com proteções anti-ban
async function safeSendMessage(sock, jid, message, options = {}) {
  if (!sock) {
    log.error('[SAFETY] Socket não disponível em safeSendMessage');
    return null;
  }

  if (!SAFETY_ENABLED) {
    return await sock.sendMessage(jid, message, options);
  }

  try {
    // 1. Verificar Rate Limit
    let rateLimit = checkRateLimit(jid);
    if (!rateLimit.allowed) {
      log.warn(`[SAFETY] Rate limit atingido para ${jid}: ${rateLimit.reason}. Aguardando ${rateLimit.retryAfter}ms`);

      // Se for apenas delay mínimo, aguardar e continuar
      if (rateLimit.reason === 'min_delay' || rateLimit.reason === 'chat_rate_limit') {
        const waitTime = Math.min(rateLimit.retryAfter, 10000); // No máximo 10s de espera síncrona
        await new Promise(resolve => setTimeout(resolve, waitTime));
        // Re-verificar após o delay
        rateLimit = checkRateLimit(jid);
        if (!rateLimit.allowed && rateLimit.reason !== 'min_delay') {
          log.error(`[SAFETY] Rate limit ainda ativo após espera: ${rateLimit.reason}`);
          return null;
        }
      } else {
        // Para limites globais ou blacklist, abortar
        return null;
      }
    }

    // 2. Verificar se o contato existe (apenas para privados)
    const exists = await checkContactExists(sock, jid);
    if (!exists) {
      log.error(`[SAFETY] Cancelando envio para ${jid} - contato não existe ou é inválido`);
      return null;
    }

    // 3. Simular Digitação/Presença (Humanização)
    if (SIMULATE_TYPING) {
      const isGroup = isGroupJid(jid);
      // Só simula em grupo se explicitamente habilitado, para evitar overhead
      if (!isGroup || (isGroup && SIMULATE_TYPING_GROUPS)) {
        try {
          await sock.sendPresenceUpdate('composing', jid);

          // Calcular tempo de "digitação" baseado no tamanho do texto
          let textContent = '';
          if (typeof message === 'string') textContent = message;
          else if (message.text) textContent = message.text;
          else if (message.caption) textContent = message.caption;

          const typingTime = Math.min(Math.max(textContent.length * 50, 1500), 4000);
          await new Promise(resolve => setTimeout(resolve, typingTime));

          await sock.sendPresenceUpdate('paused', jid);
        } catch (e) {
          // Ignorar erros de presença para não travar o envio
        }
      }
    }

    // 4. Delay aleatório adicional (Humanização extra)
    if (ENABLE_DELAYS) {
      const extraDelay = Math.floor(Math.random() * 1500) + 500; // 0.5s a 2s
      await new Promise(resolve => setTimeout(resolve, extraDelay));
    }

    // 5. Adicionar Sufixo Aleatório (Quebra de Padrão)
    if (RANDOM_SUFFIX_ENABLED) {
      const suffix = generateRandomSuffix();
      if (typeof message === 'string') {
        message = message + suffix;
      } else if (message.text) {
        message.text = message.text + suffix;
      } else if (message.caption) {
        message.caption = message.caption + suffix;
      }
    }

    // 6. Envio Real
    const result = await sock.sendMessage(jid, message, options);

    // 7. Registrar sucesso no sistema de cotas
    registerMessageSent(jid);

    return result;
  } catch (error) {
    log.error(`[SAFETY] Falha fatal no safeSendMessage: ${error.message}`);
    // Tentar envio direto como último recurso se não for erro de conexão
    if (!error.message.includes('close') && !error.message.includes('reconnect')) {
      try {
        return await sock.sendMessage(jid, message, options);
      } catch (e2) {
        return null;
      }
    }
    return null;
  }
}

// Limites máximos para evitar crescimento indefinido (reduzidos para evitar OOM)
const MAX_CACHE_SIZE = 200; // Máximo de 200 entradas por cache (reduzido de 1000)
const MAX_STORE_MESSAGES = 20; // Máximo de 20 mensagens por chat no store (reduzido de 50)
const MAX_STORE_CHATS = 50; // Máximo de 50 chats no store (reduzido de 100)

// Função para limpar cache quando exceder limite
function enforceCacheLimit(map, maxSize = MAX_CACHE_SIZE) {
  if (map.size > maxSize) {
    const entries = Array.from(map.entries());
    // Manter apenas as mais recentes
    entries.sort((a, b) => {
      const aTime = typeof a[1] === 'object' && a[1].timestamp ? a[1].timestamp : (typeof a[1] === 'number' ? a[1] : 0);
      const bTime = typeof b[1] === 'object' && b[1].timestamp ? b[1].timestamp : (typeof b[1] === 'number' ? b[1] : 0);
      return bTime - aTime; // Mais recentes primeiro
    });

    // Remover as mais antigas
    const toKeep = entries.slice(0, maxSize);
    map.clear();
    for (const [key, value] of toKeep) {
      map.set(key, value);
    }
  }
}

// Limpar contadores antigos periodicamente (mais frequente)
setInterval(() => {
  const now = Date.now();
  const oneHourAgo = now - 3600000;
  const fiveMinutesAgo = now - 300000;

  // Limpar contadores expirados
  for (const [key, value] of messageCounts.entries()) {
    if (now > value.resetAt || (now - value.resetAt) > 60000) {
      messageCounts.delete(key);
    }
  }

  for (const [key, value] of hourlyCounts.entries()) {
    if (now > value.resetAt) {
      hourlyCounts.delete(key);
    }
  }

  for (const [key, value] of chatMessageCounts.entries()) {
    if (now > value.resetAt) {
      chatMessageCounts.delete(key);
    }
  }

  // Limpar lastMessageTime antigo (mais de 5 minutos)
  for (const [key, value] of lastMessageTime.entries()) {
    if (now - value > 300000) {
      lastMessageTime.delete(key);
    }
  }

  // Limpar commandCooldowns antigos
  for (const [key, value] of commandCooldowns.entries()) {
    if (now - value > 3600000) {
      commandCooldowns.delete(key);
    }
  }

  // Limpar blacklist antiga
  // (blacklist já tem timeout automático, mas garantir limpeza)

  // Enforçar limites de tamanho (mais agressivos)
  enforceCacheLimit(messageCounts, 100);
  enforceCacheLimit(lastMessageTime, 100);
  enforceCacheLimit(chatMessageCounts, 100);
  enforceCacheLimit(commandCooldowns, 100);

  // Limpar cache de licenças expiradas
  for (const [key, value] of groupLicenseCache.entries()) {
    if (now > value.expires) {
      groupLicenseCache.delete(key);
    }
  }
  enforceCacheLimit(groupLicenseCache, 50);

  // Limpar store de mensagens apenas se habilitado
  if (ENABLE_STORE) {
    const allJids = Object.keys(store.messages);
    if (allJids.length > MAX_STORE_CHATS_MEMORY) {
      const jidsWithTime = allJids.map(jid => {
        const msgs = store.messages[jid];
        const lastMsg = Object.values(msgs).reduce((latest, msg) => {
          const msgTime = msg?.messageTimestamp || msg?.key?.timestamp || 0;
          return msgTime > latest ? msgTime : latest;
        }, 0);
        return { jid, lastMsg };
      });
      jidsWithTime.sort((a, b) => b.lastMsg - a.lastMsg);

      // Manter apenas os mais recentes
      for (let i = MAX_STORE_CHATS_MEMORY; i < jidsWithTime.length; i++) {
        delete store.messages[jidsWithTime[i].jid];
      }
    }

    // Limpar mensagens antigas dentro de cada chat
    for (const jid of Object.keys(store.messages)) {
      const keys = Object.keys(store.messages[jid]);
      if (keys.length > MAX_STORE_MESSAGES_MEMORY) {
        const sortedKeys = keys.sort((a, b) => {
          const aMsg = store.messages[jid][a];
          const bMsg = store.messages[jid][b];
          const aTime = aMsg?.messageTimestamp || aMsg?.key?.timestamp || 0;
          const bTime = bMsg?.messageTimestamp || bMsg?.key?.timestamp || 0;
          return aTime - bTime;
        });
        for (let i = 0; i < sortedKeys.length - MAX_STORE_MESSAGES_MEMORY; i++) {
          delete store.messages[jid][sortedKeys[i]];
        }
      }
    }
  }

}, 15000); // Limpar a cada 15 segundos (muito mais frequente)

console.log('📡 APIs configuradas:');
console.log('   Rastreamento:', RASTREAMENTO_API_URL, '(token:', RASTREAMENTO_TOKEN.substring(0, 4) + '***)');
console.log('   Verificação de licença:', LICENSE_CHECK_ENABLED ? 'ATIVADA' : 'DESATIVADA');
console.log('   IA Chat:', IA_ENABLED ? 'ATIVADA' : 'DESATIVADA', IA_ONLY_PRIVATE ? '(só privado)' : '(todos)');
console.log(`🛡️  Sistema de Segurança: ${SAFETY_ENABLED ? 'ATIVADO' : 'DESATIVADO'}`);

// ===== CONFIGURAÇÕES DE ESTABILIDADE =====
const RECONNECT_DELAY_MIN = 5000;       // 5 segundos mínimo
const RECONNECT_DELAY_MAX = 120000;     // 2 minutos máximo
const HEARTBEAT_INTERVAL = 20000;       // 20 segundos (mais frequente)
const CONNECTION_TIMEOUT = 180000;      // 3 minutos timeout (mais tolerante)
const MAX_RECONNECT_ATTEMPTS = 10;      // Máximo antes de parar e pedir QR
const MEMORY_CHECK_INTERVAL = 30000;   // 30 segundos (muito frequente para evitar vazamentos)
const LOOP_DETECTION_WINDOW = 60000;    // 1 minuto para detectar loop
const MAX_DISCONNECTS_IN_WINDOW = 5;    // 5 desconexões em 1 min = loop
const PING_INTERVAL = 60000;            // 1 minuto - ping para manter conexão

// (Variáveis isReady, lastQR, sock são exportadas no topo do arquivo)

// Getters para integração
export const getBotState = () => ({
  ready: isReady,
  connected: isReady, // Compatibilidade
  qr: lastQR,
  uptime: connectionStartTime ? Math.round((Date.now() - connectionStartTime) / 1000) : 0,
  reconnectAttempts,
  isInLoopState
});
let reconnectAttempts = 0;
let reconnectTimer = null;
let heartbeatTimer = null;
let pingTimer = null;
let lastHeartbeat = Date.now();
let connectionStartTime = null;
let disconnectTimestamps = [];  // Para detectar loop de desconexão
let isInLoopState = false;      // Flag de loop detectado
let isReconnecting = false;     // Flag para evitar reconexões simultâneas

// ===== CUSTOM SIMPLE STORE =====
// Store de mensagens para o Baileys
const ENABLE_STORE = String(process.env.ENABLE_STORE || 'true').toLowerCase() === 'true'; // Habilitado por padrão
const MAX_STORE_MESSAGES_MEMORY = 50; // Máximo 50 mensagens por chat
const MAX_STORE_CHATS_MEMORY = 100; // Máximo 100 chats

const simpleStore = {
  messages: {},

  bind(ev) {
    if (!ENABLE_STORE) {
      // Store desabilitado - não armazenar nada
      return;
    }

    ev.on('messages.upsert', ({ messages: newMessages }) => {
      for (const msg of newMessages) {
        if (!msg.message) continue;
        const jid = msg.key.remoteJid;
        const id = msg.key.id;

        if (!this.messages[jid]) this.messages[jid] = {};
        this.messages[jid][id] = msg;

        // Limitar memória drasticamente: manter apenas últimas 5 mensagens por chat
        const keys = Object.keys(this.messages[jid]);
        if (keys.length > MAX_STORE_MESSAGES_MEMORY) {
          // Remover as mais antigas
          const sortedKeys = keys.sort((a, b) => {
            const aMsg = this.messages[jid][a];
            const bMsg = this.messages[jid][b];
            const aTime = aMsg?.messageTimestamp || aMsg?.key?.timestamp || 0;
            const bTime = bMsg?.messageTimestamp || bMsg?.key?.timestamp || 0;
            return aTime - bTime;
          });
          // Remover as mais antigas até ficar com o limite
          for (let i = 0; i < sortedKeys.length - MAX_STORE_MESSAGES_MEMORY; i++) {
            delete this.messages[jid][sortedKeys[i]];
          }
        }

        // Limitar número total de chats (manter apenas os 10 mais recentes)
        const allJids = Object.keys(this.messages);
        if (allJids.length > MAX_STORE_CHATS_MEMORY) {
          // Ordenar por última mensagem
          const jidsWithTime = allJids.map(jid => {
            const msgs = this.messages[jid];
            const lastMsg = Object.values(msgs).reduce((latest, msg) => {
              const msgTime = msg?.messageTimestamp || msg?.key?.timestamp || 0;
              return msgTime > latest ? msgTime : latest;
            }, 0);
            return { jid, lastMsg };
          });
          jidsWithTime.sort((a, b) => b.lastMsg - a.lastMsg);

          // Remover os chats mais antigos
          for (let i = MAX_STORE_CHATS_MEMORY; i < jidsWithTime.length; i++) {
            delete this.messages[jidsWithTime[i].jid];
          }
        }
      }
    });
  },

  async loadMessage(jid, id) {
    if (!ENABLE_STORE) return undefined;
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

// ===== CONFIGURAÇÃO DE DIRETÓRIOS =====
const isProduction = process.env.NODE_ENV === 'production';
let authPath;

if (isProduction) {
  authPath = path.resolve(__dirname, 'auth');
  console.log(`[INIT] Produção: ${authPath}`);
} else {
  authPath = path.resolve(__dirname, 'auth');
  console.log(`[INIT] Local: ${authPath}`);
}

// Garantir que a pasta existe com fallback para /tmp
try {
  if (!fs.existsSync(authPath)) {
    console.log(`[INIT] Criando pasta de autenticação: ${authPath}`);
    fs.mkdirSync(authPath, { recursive: true });
  }
  // Testar permissão de escrita
  const testFile = path.join(authPath, '.test');
  fs.writeFileSync(testFile, 'test');
  fs.unlinkSync(testFile);
} catch (err) {
  console.error(`[INIT] ⚠️ Erro ao usar pasta persistente: ${err.message}`);
  // Fallback para diretório temporário
  authPath = path.join(os.tmpdir(), 'auth_info_baileys_rastreamento');
  console.log(`[INIT] Usando FALLBACK temporário: ${authPath}`);
  if (!fs.existsSync(authPath)) {
    fs.mkdirSync(authPath, { recursive: true });
  }
}

const storePath = path.join(authPath, 'baileys_store.json');

// Inicializar Store
const store = simpleStore;
store.readFromFile(storePath);

if (ENABLE_STORE) {
  console.log(`📦 Store habilitado (${MAX_STORE_MESSAGES_MEMORY} msgs/chat, ${MAX_STORE_CHATS_MEMORY} chats)`);
} else {
  console.log('📦 Store desabilitado');
}

// Salvar periodicamente
setInterval(() => {
  if (ENABLE_STORE) {
    store.writeToFile(storePath);
  }
}, 10_000);

// Controle simples para evitar auto-resposta repetida
const lastReplyAt = new Map(); // key: jid, value: timestamp
// Controle de comandos aguardando foto
const waitingPhoto = new Map(); // key: jid, value: { codigo: string, timestamp: number }
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
const automationLocks = new Map(); // key: `${automationId}-${jid}`, value: timestamp da trava (previne execução simultânea)
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

  const isGroup = isGroupJid(remoteJid);

  // FORÇAR IA APENAS NO PRIVADO (Solicitado pelo usuário)
  // Ignorar qualquer interação de IA em grupos
  if (isGroup) {
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

    // Tratamento para respostas filtradas (Silêncio intencional)
    if (response.data && response.data.success && (response.data.source === 'gemini_filtered' || response.data.source === 'local_filter_silence')) {
      log.info(`[IA] Mensagem ignorada pelo filtro inteligente (${response.data.source})`);
      return null;
    }

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
        log.warn(`Heartbeat: Conexão parece travada (${Math.round(timeSinceLastBeat / 1000)}s sem atividade)`);
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
    log.error(`${disconnectTimestamps.length} desconexões em ${LOOP_DETECTION_WINDOW / 1000} segundos`);
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
  log.warn(`Reconexão #${reconnectAttempts} em ${Math.round(delay / 1000)}s. Motivo: ${reason}`);

  reconnectTimer = setTimeout(async () => {
    isReconnecting = true;
    try {
      stopHeartbeat();
      if (sock) {
        try { sock.end(); } catch (e) { }
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

    // Processar comando automaticamente para Rastreamento
    if (command && (command.startsWith('/') || command.startsWith('!'))) {
      log.info(`[POLL] Encaminhando comando para processAdminCommand: ${command}`);
      const result = await processAdminCommand(jid, command, null);
      if (result && result.message) {
        await safeSendMessage(sock, jid, { text: result.message });
      }
      return;
    }

    log.info(`[POLL] Voto recebido para comando: ${command}. Nenhuma automação específica além de forwarding.`);

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
    const sent = await safeSendMessage(sock, jid, pollMessage);

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
  const fiveMinutesAgo = now - 300000;

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
    if (!value.timestamp || value.timestamp < oneHourAgo) {
      pollContext.delete(key);
    }
  }
  // Limpar votos processados antigos
  for (const [key, timestamp] of processedVotes.entries()) {
    if (!timestamp || timestamp < oneHourAgo) {
      processedVotes.delete(key);
    }
  }
  // Limpar votos pendentes antigos
  for (const [key, value] of pendingPollVotes.entries()) {
    if (!value.timestamp || value.timestamp < fiveMinutesAgo) {
      pendingPollVotes.delete(key);
    }
  }

  // Limpar automações cooldowns antigos
  for (const [key, timestamp] of automationCooldowns.entries()) {
    if (!timestamp || timestamp < oneHourAgo) {
      automationCooldowns.delete(key);
    }
  }

  // Enforçar limites
  enforceCacheLimit(lastReplyAt, 200);
  enforceCacheLimit(waitingPhoto, 100);
  enforceCacheLimit(pollContext, 100);
  enforceCacheLimit(processedVotes, 500);
  enforceCacheLimit(pendingPollVotes, 100);
  enforceCacheLimit(automationCooldowns, 500);
  enforceCacheLimit(antilinkGroups, 200);

  // Limpeza preventiva quando memória > 300MB
  if (heapUsedMB > 300) {
    log.warn(`⚠️ Memória moderada: ${heapUsedMB}MB / ${heapTotalMB}MB - Limpeza preventiva...`);
  }

  // Limpar store de mensagens se memória alta (apenas se habilitado)
  if (heapUsedMB > 400 && ENABLE_STORE) {
    log.warn(`⚠️ Memória alta: ${heapUsedMB}MB / ${heapTotalMB}MB - Limpando store...`);

    // Limpar store completamente se memória alta
    store.messages = {};
  }

  if (heapUsedMB > 500) {
    log.error(`🚨 MEMÓRIA CRÍTICA: ${heapUsedMB}MB / ${heapTotalMB}MB - Limpeza de emergência!`);

    // Limpeza de emergência - limpar quase tudo
    const emergencyLimit = 50;

    // Limpar todos os caches drasticamente
    messageCounts.clear();
    lastMessageTime.clear();
    chatMessageCounts.clear();
    commandCooldowns.clear();
    groupLicenseCache.clear();

    enforceCacheLimit(lastReplyAt, emergencyLimit);
    enforceCacheLimit(waitingPhoto, emergencyLimit);
    enforceCacheLimit(pollContext, emergencyLimit);
    enforceCacheLimit(processedVotes, emergencyLimit);
    enforceCacheLimit(pendingPollVotes, emergencyLimit);
    enforceCacheLimit(automationCooldowns, emergencyLimit);
    enforceCacheLimit(antilinkGroups, emergencyLimit);

    // Limpar store completamente se habilitado
    if (ENABLE_STORE) {
      store.messages = {};
    }

    // Forçar garbage collection se disponível
    if (global.gc) {
      log.info('🗑️ Forçando garbage collection de emergência...');
      global.gc();
    } else {
      log.warn('⚠️ GC não disponível. Execute com --expose-gc para habilitar.');
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
  if (!text || !automation.gatilho) {
    log.warn(`[AUTOMATIONS-MATCH] Texto ou gatilho vazio`);
    return false;
  }

  const lowerText = text.toLowerCase().trim();
  const gatilho = automation.gatilho.toLowerCase();

  log.info(`[AUTOMATIONS-MATCH] Testando automação "${automation.nome}" (ID: ${automation.id})`);
  log.info(`[AUTOMATIONS-MATCH] Tipo: ${automation.tipo}`);
  log.info(`[AUTOMATIONS-MATCH] Texto recebido: "${lowerText}"`);
  log.info(`[AUTOMATIONS-MATCH] Gatilho: "${gatilho.substring(0, 100)}..."`);

  let matched = false;

  switch (automation.tipo) {
    case 'mensagem_especifica':
      // Match exato
      matched = lowerText === gatilho;
      log.info(`[AUTOMATIONS-MATCH] Mensagem específica: ${matched ? 'MATCH ✅' : 'NO MATCH ❌'}`);
      return matched;

    case 'palavra_chave':
      // Match com palavras-chave separadas por |
      const keywords = gatilho.split('|').map(k => k.trim()).filter(k => k.length > 0); // Remover vazias
      log.info(`[AUTOMATIONS-MATCH] Palavras-chave: ${keywords.length} palavras`);

      if (keywords.length === 0) {
        log.warn(`[AUTOMATIONS-MATCH] Nenhuma palavra-chave válida encontrada!`);
        return false;
      }

      matched = keywords.some(keyword => {
        const hasMatch = lowerText.includes(keyword) || lowerText === keyword;
        if (hasMatch) {
          log.info(`[AUTOMATIONS-MATCH] ✅ MATCH com palavra: "${keyword}"`);
        }
        return hasMatch;
      });

      log.info(`[AUTOMATIONS-MATCH] Resultado: ${matched ? 'MATCH ✅' : 'NO MATCH ❌'}`);
      return matched;

    case 'regex':
      // Match com expressão regular
      try {
        const regex = new RegExp(automation.gatilho, 'i');
        matched = regex.test(text);
        log.info(`[AUTOMATIONS-MATCH] Regex: ${matched ? 'MATCH ✅' : 'NO MATCH ❌'}`);
        return matched;
      } catch (e) {
        log.warn(`[AUTOMATIONS] Regex inválido: ${automation.gatilho}`);
        return false;
      }

    default:
      log.warn(`[AUTOMATIONS-MATCH] Tipo desconhecido: ${automation.tipo}`);
      return false;
  }
}

// Verificar cooldown (usando banco de dados para persistência)
async function checkCooldown(automationId, jid, cooldownSeconds) {
  log.info(`[AUTOMATIONS-COOLDOWN] 🔍 Verificando cooldown para automação ${automationId}, grupo ${jid.split('@')[0]}, cooldown: ${cooldownSeconds}s`);

  if (!cooldownSeconds || cooldownSeconds <= 0) {
    log.info(`[AUTOMATIONS-COOLDOWN] Sem cooldown configurado (automationId: ${automationId})`);
    return false; // Sem cooldown configurado
  }

  try {
    log.info(`[AUTOMATIONS-COOLDOWN] 📡 Consultando API para verificar cooldown...`);
    // Verificar no banco de dados (persistente, sobrevive a reinicializações)
    const response = await axios.post(
      `${RASTREAMENTO_API_URL}/api_bot_automations.php?action=check_cooldown`,
      {
        automation_id: automationId,
        jid_origem: jid,
        cooldown_segundos: cooldownSeconds
      },
      {
        headers: {
          'x-api-token': RASTREAMENTO_TOKEN,
          'Content-Type': 'application/json'
        },
        timeout: 5000
      }
    );

    if (response.data && response.data.success) {
      const data = response.data;
      const isInCooldown = data.in_cooldown;

      log.info(`[AUTOMATIONS-COOLDOWN] Automação ${automationId} para ${jid.split('@')[0]}:`);
      log.info(`  - Cooldown configurado: ${cooldownSeconds}s`);
      log.info(`  - Tempo decorrido: ${data.elapsed_seconds}s`);
      log.info(`  - Em cooldown: ${isInCooldown ? 'SIM' : 'NÃO'}`);

      if (isInCooldown) {
        const remaining = data.remaining_seconds;
        log.warn(`[AUTOMATIONS-COOLDOWN] ⏳ Cooldown ativo: ${remaining}s restantes (total: ${cooldownSeconds}s)`);
      } else {
        log.success(`[AUTOMATIONS-COOLDOWN] ✅ Cooldown OK, pode executar`);
      }

      return isInCooldown;
    }

    // Fallback para memória local se API falhar
    log.warn(`[AUTOMATIONS-COOLDOWN] API falhou, usando fallback em memória`);
    const key = `${automationId}-${jid}`;
    const lastUse = automationCooldowns.get(key);

    if (!lastUse) {
      return false;
    }

    const elapsed = (Date.now() - lastUse) / 1000;
    return elapsed < cooldownSeconds;

  } catch (error) {
    log.error(`[AUTOMATIONS-COOLDOWN] ❌ Erro ao verificar cooldown: ${error.message}`);
    log.error(`[AUTOMATIONS-COOLDOWN] Stack: ${error.stack}`);
    // Em caso de erro, permitir execução (fail-open)
    log.warn(`[AUTOMATIONS-COOLDOWN] ⚠️  Fallback: permitindo execução devido a erro na API`);
    return false;
  }
}

// Registrar uso de automação
function registerAutomationUse(automationId, jid) {
  const key = `${automationId}-${jid}`;
  const now = Date.now();
  automationCooldowns.set(key, now);

  log.info(`[AUTOMATIONS-COOLDOWN] 📝 Registrado uso da automação ${automationId} em ${new Date(now).toLocaleString('pt-BR')}`);

  // Limpar cooldowns antigos (mais de 7 dias para suportar cooldowns longos)
  const sevenDaysAgo = Date.now() - (7 * 24 * 3600 * 1000);
  for (const [k, v] of automationCooldowns.entries()) {
    if (v < sevenDaysAgo) automationCooldowns.delete(k);
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

    const isGroup = isGroupJid(remoteJid);
    const grupoId = isGroup ? remoteJid : null;

    // Automações agora funcionam em todos os grupos, mesmo sem licença
    // (A verificação de licença foi removida conforme solicitado)

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
      log.info(`[AUTOMATIONS] ━━━ Verificando automação: "${automation.nome}" (ID: ${automation.id}) ━━━`);
      log.info(`[AUTOMATIONS] Configuração: apenas_privado=${automation.apenas_privado}, apenas_grupo=${automation.apenas_grupo}, grupo_id=${automation.grupo_id || 'TODOS'}`);
      log.info(`[AUTOMATIONS] Contexto: isGroup=${isGroup}, remoteJid=${remoteJid}`);

      // Verificar se é para grupo/privado
      if (automation.apenas_privado == 1 && isGroup) {
        log.warn(`[AUTOMATIONS] ❌ Pulando: automação é apenas para PRIVADO e mensagem veio de GRUPO`);
        continue;
      }
      if (automation.apenas_grupo == 1 && !isGroup) {
        log.warn(`[AUTOMATIONS] ❌ Pulando: automação é apenas para GRUPO e mensagem veio de PRIVADO`);
        continue;
      }

      log.info(`[AUTOMATIONS] ✅ Passou verificação de grupo/privado`);

      // Verificar se é para grupo específico
      if (automation.grupo_id) {
        // Suporte para múltiplos grupos (CSV)
        const allowedGroups = automation.grupo_id.split(',').map(g => g.trim());
        if (!allowedGroups.includes(remoteJid)) {
          log.warn(`[AUTOMATIONS] ❌ Pulando: automação é para grupo(s) específico(s) diferente(s)`);
          continue;
        }
      }

      log.info(`[AUTOMATIONS] ✅ Passou verificação de grupo específico`);

      // Verificar match
      if (!matchAutomation(text, automation)) {
        log.warn(`[AUTOMATIONS] ❌ Pulando: texto não deu match com gatilho`);
        continue;
      }

      log.success(`[AUTOMATIONS] ✅✅✅ Match encontrado: "${automation.nome}" (ID: ${automation.id}, Cooldown: ${automation.cooldown_segundos}s)`);

      // TRAVA DE SEGURANÇA: Verificar se já está executando
      const lockKey = `${automation.id}-${remoteJid}`;
      const now = Date.now();
      const existingLock = automationLocks.get(lockKey);

      if (existingLock && (now - existingLock) < 30000) { // 30 segundos de trava
        log.warn(`[AUTOMATIONS] 🔒 TRAVA ATIVA: Automação ${automation.id} já está executando para este grupo (trava há ${Math.floor((now - existingLock) / 1000)}s)`);
        continue;
      }

      // Criar trava
      automationLocks.set(lockKey, now);
      log.info(`[AUTOMATIONS] 🔐 Trava criada para prevenir execução simultânea`);

      // Verificar cooldown
      if (await checkCooldown(automation.id, remoteJid, automation.cooldown_segundos)) {
        log.info(`[AUTOMATIONS] Cooldown ativo para automação ${automation.id} e JID ${remoteJid}`);
        automationLocks.delete(lockKey); // Remover trava antes de continuar
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
          await safeSendMessage(sock, remoteJid, {
            image: { url: automation.imagem_url },
            caption: automation.resposta
          });
        } else {
          // Enviar apenas texto
          await safeSendMessage(sock, remoteJid, { text: automation.resposta });
        }

        log.success(`[AUTOMATIONS] 📤 Mensagem enviada com sucesso!`);

        // Registrar uso
        registerAutomationUse(automation.id, remoteJid);
        log.success(`[AUTOMATIONS] 🔒 Cooldown registrado para ${automation.cooldown_segundos}s (${Math.floor(automation.cooldown_segundos / 3600)}h ${Math.floor((automation.cooldown_segundos % 3600) / 60)}min)`);

        // Log na API
        logAutomationExecution(automation, remoteJid, text, automation.resposta, grupoId, grupoNome);

        // Remover trava após sucesso
        automationLocks.delete(lockKey);
        log.info(`[AUTOMATIONS] 🔓 Trava removida após envio bem-sucedido`);

        return true; // Automação executada
      } catch (sendError) {
        log.error(`[AUTOMATIONS] Erro ao enviar resposta: ${sendError.message}`);

        // Remover trava em caso de erro
        automationLocks.delete(lockKey);
        log.warn(`[AUTOMATIONS] 🔓 Trava removida após erro`);

        // Se falhar com imagem, tentar só texto
        if (automation.imagem_url) {
          try {
            log.warn(`[AUTOMATIONS] Tentando enviar apenas texto após falha de imagem`);
            await safeSendMessage(sock, remoteJid, { text: automation.resposta });
            registerAutomationUse(automation.id, remoteJid);
            automationLocks.delete(lockKey); // Garantir remoção
            return true;
          } catch (textError) {
            log.error(`[AUTOMATIONS] Também falhou só texto: ${textError.message}`);
            automationLocks.delete(lockKey); // Garantir remoção
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

// Sincronizar todos os grupos (Lista Completa)
async function syncGroups(sock) {
  try {
    log.info('[GROUPS] Buscando grupos do WhatsApp...');
    // groupFetchAllParticipating retorna um objeto onde chaves são JIDs
    const groups = await sock.groupFetchAllParticipating();

    const groupList = [];
    for (const jid in groups) {
      const g = groups[jid];
      groupList.push({
        jid: g.id,
        nome: g.subject,
        descricao: g.desc || '',
        participantes: g.participants?.length || 0
      });
    }

    log.info(`[GROUPS] Encontrados ${groupList.length} grupos. Sincronizando com o servidor...`);

    await axios.post(
      `${RASTREAMENTO_API_URL}/api_bot_automations.php?action=sync_all_groups`,
      { groups: groupList },
      {
        headers: {
          'x-api-token': RASTREAMENTO_TOKEN,
          'Content-Type': 'application/json'
        },
        timeout: 20000 // Aumentado timeout para lista grande
      }
    );
    log.success(`[GROUPS] Sincronização de grupos concluída!`);
  } catch (error) {
    log.error(`[GROUPS] Erro ao sincronizar grupos: ${error.message}`);
  }
}

// ===== COMANDOS DE ADMIN DO GRUPO =====
// Comandos como /ban, /kick, /promote, /demote
async function processGroupAdminCommand(remoteJid, text, msg) {
  try {
    const command = text.split(' ')[0].toLowerCase();
    const senderJid = msg.key.participant || msg.key.remoteJid;

    // Sistema de segurança desabilitado - sem cooldown

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

    const isGroup = isGroupJid(remoteJid);
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

              await safeSendMessage(sock, remoteJid, {
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
          } catch (e) { }

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

    // Apenas Rastreamento (/)
    const prefix = text.charAt(0);
    // Ignorar comandos que não começam com / (rastreamento) ou comandos de admin de grupo ($)
    if (prefix !== '/') return null;

    const apiUrl = RASTREAMENTO_API_URL;
    const apiToken = RASTREAMENTO_TOKEN;
    const projectName = 'Rastreamento';

    log.info(`[${projectName}] Comando de ${fromNumber}: ${text}`);

    const parts = text.trim().split(/\s+/);
    // Rastreamento espera o comando sem a "/"
    const commandToSend = parts[0].substring(1).toLowerCase();
    const params = parts.slice(1);

    // Preparar payload da requisição
    const requestPayload = {
      command: commandToSend,
      params,
      from: fromNumber
    };

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

    // Verificar se a API pede uma foto
    if (result.waiting_photo && result.photo_codigo) {
      waitingPhoto.set(from, {
        codigo: result.photo_codigo,
        timestamp: Date.now()
      });

      // Remover automaticamente se não enviar em 5 minutos
      setTimeout(() => {
        if (waitingPhoto.get(from)?.codigo === result.photo_codigo) {
          waitingPhoto.delete(from);
        }
      }, 5 * 60 * 1000);
    }

    // Atualizar heartbeat
    lastHeartbeat = Date.now();

    return result;
  } catch (error) {
    log.error(`[RASTREAMENTO] Erro no comando: ${error.message}`);
    if (error.response) {
      log.error(`[RASTREAMENTO] Resposta da API: ${JSON.stringify(error.response.data)}`);
    }
    return {
      success: false,
      message: '❌ Erro ao processar comando rastreamento.\n' + (error.response?.data?.message || error.response?.data?.error || error.message)
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

    // Processar upload de foto para Rastreamento
    if (waiting.codigo) {
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

      await safeSendMessage(sock, from, {
        text: response.data.message || '✅ Foto recebida e anexada ao pedido!'
      });
    } else {
      waitingPhoto.delete(from);
      await safeSendMessage(sock, from, {
        text: '❌ Erro: formato de upload não reconhecido'
      });
      return true;
    }

    lastHeartbeat = Date.now();
    return true;
  } catch (error) {
    log.error(`Erro foto: ${error.message}`);
    waitingPhoto.delete(from);

    await safeSendMessage(sock, from, {
      text: '❌ Erro ao processar a foto. Tente novamente.'
    });

    return true;
  }
}

// ===== FUNÇÃO PRINCIPAL DE CONEXÃO =====
async function start() {
  try {
    log.info('Iniciando conexão com WhatsApp...');

    // Usar caminho de autenticação global já configurado
    log.info(`[AUTH] Usando caminho de autenticação: ${authPath}`);

    const { version, isLatest } = await fetchLatestBaileysVersion();
    log.info(`WhatsApp Web version: ${version?.join('.')} (latest=${isLatest})`);

    const { state, saveCreds } = await useMultiFileAuthState(authPath);

    // Logger personalizado que silencia TUDO do Baileys
    const silentLogger = pino({
      level: 'silent',
      enabled: false
    });
    silentLogger.child = () => silentLogger;
    silentLogger.trace = () => { };
    silentLogger.debug = () => { };
    silentLogger.info = () => { };
    silentLogger.warn = () => { };
    silentLogger.error = () => { };
    silentLogger.fatal = () => { };

    // Cache para retry de mensagens (Ajuda no erro "Aguardando mensagem")
    const msgRetryCounterCache = new Map();

    sock = makeWASocket({
      auth: state,
      logger: silentLogger,
      version,
      browser: ['Windows', 'Chrome', '10.0'],
      connectTimeoutMs: 60000,
      keepAliveIntervalMs: 20000,  // Keep-alive mais frequente (20s)
      retryRequestDelayMs: 500,
      defaultQueryTimeoutMs: 60000,
      emitOwnEvents: false,
      markOnlineOnConnect: true,
      syncFullHistory: false,
      printQRInTerminal: false, // Desativa QR duplicado
      msgRetryCounterCache, // Adicionado para lidar com retries
      getMessage: async (key) => {
        if (store && ENABLE_STORE) {
          const msg = await store.loadMessage(key.remoteJid, key.id);
          return msg?.message || undefined;
        }
        return undefined;
      },
      shouldReconnectMessage: () => true,  // Sempre tentar reconectar
      shouldIgnoreJid: () => false
    });

    // Bind do store aos eventos do socket para manter sincronização
    store.bind(sock.ev);

    sock.ev.on('creds.update', saveCreds);

    // Listener para atualizações de grupos (Adição/Modificação)
    sock.ev.on('groups.upsert', async (groups) => {
      log.info(`[GROUPS] Evento groups.upsert recebido: ${groups.length} grupos`);
      for (const g of groups) {
        if (g.id) {
          // Em upsert, as vezes vem incompleto, mas tentamos salvar o que temos
          saveGroupInfo(g.id, g.subject || '', g.desc || '', g.participants?.length || 0);
        }
      }
    });

    // Listener para atualizações de metadados de grupos
    sock.ev.on('groups.update', async (groups) => {
      try {
        for (const g of groups) {
          if (!g.id) continue;
          // Fetch info completa para garantir
          const meta = await sock.groupMetadata(g.id);
          saveGroupInfo(meta.id, meta.subject, meta.desc, meta.participants.length);
        }
      } catch (e) {
        // Ignorar erro se não conseguir buscar metadata (pode não estar mais no grupo)
      }
    });

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

            if (!pollJid || typeof pollJid !== 'string' || isGroupJid(pollJid)) {
              continue; // Ignorar grupos e comunidades
            }

            if (!voterJid || typeof voterJid !== 'string' || isGroupJid(voterJid)) {
              continue; // Ignorar grupos e comunidades
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
                  0: '/ajuda',
                  1: '/pedido',
                  2: '/status',
                  3: '/menu'
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
                await safeSendMessage(sock, voterJid, {
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

          if (!jid || typeof jid !== 'string' || isGroupJid(jid)) {
            log.warn(`[POLL] JID inválido, grupo ou comunidade: ${jid}`);
            continue; // Ignorar grupos, comunidades e JIDs inválidos
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
          // OBS: O sistema de automação de polls para o projeto Financeiro foi removido.
          log.info(`[POLL] Voto recebido para comando: ${command}. Nenhuma automação configurada.`);
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
        log.info(`Sistema de heartbeat: ${HEARTBEAT_INTERVAL / 1000}s | Ping: ${PING_INTERVAL / 1000}s`);

        startHeartbeat();
        startPing();

        // Sincronizar grupos
        syncGroups(sock);

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

        // Iniciar loop de status (aquecimento)
        startWarmingStatusLoop();

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
        log.warn(`   Uptime antes da desconexão: ${Math.floor(uptime / 60)}m ${uptime % 60}s`);

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
        const textTrimmed = text.trim();
        if (textTrimmed.startsWith('/') || textTrimmed.startsWith('!') || textTrimmed.startsWith('$')) {
          log.info(`🎯 Comando detectado: "${textTrimmed}" de ${remoteJid.split('@')[0]}`);
          const result = await processAdminCommand(remoteJid, textTrimmed, msg);
          // Se poll foi enviada, não enviar mensagem de texto adicional
          if (result && !result.pollSent && result.message) {
            // Verificar se precisa enviar com mentions
            if (result.mentions && result.mentions.length > 0) {
              await safeSendMessage(sock, remoteJid, {
                text: result.message,
                mentions: result.mentions
              });
            } else {
              await safeSendMessage(sock, remoteJid, { text: result.message });
            }
          }
          return;
        }

        // Para outras mensagens, ignorar se forem mensagens próprias
        if (isFromMe) {
          return;
        }

        // NOVO: Menu Interativo Inteligente (Poll)
        const lowerText = textTrimmed.toLowerCase();
        const menuWords = ['bot', 'menu', 'ajuda', 'oi', 'ola', 'hello', 'hi', 'start', 'inicio'];
        if (menuWords.includes(lowerText)) {
          log.info(`[MENU] Enviando menu interativo para ${remoteJid}`);
          try {
            const pollOptions = [
              '📦 Rastrear Pedido',
              '💰 Ver Minhas Taxas',
              '📸 Foto do Pedido',
              '❓ Dúvidas Frequentes',
              '📞 Falar com Atendente'
            ];
            // Mapear opções para comandos internos
            const commandMap = {
              0: '/rastrear',
              1: '/taxa',
              2: '/foto',
              3: '/ajuda',
              4: '/suporte'
            };
            await sendPoll(sock, remoteJid, "Olá! Sou seu assistente de logística. Como posso te ajudar hoje? 🚚💨", pollOptions, { type: 'main_menu', commandMap });
            return;
          } catch (e) {
            log.error(`[MENU] Erro ao enviar poll: ${e.message}`);
            // Fallback para texto se poll falhar
            await safeSendMessage(sock, remoteJid, { text: "🤖 *MENU DE ATENDIMENTO*\n\nDigite o código de rastreio diretamente ou use:\n\n*/rastrear* CODIGO\n*/taxa* CODIGO\n*/foto* CODIGO\n*/ajuda*" });
            return;
          }
        }

        // NOVO: Detecção Inteligente de Código (Smart-Tracking)
        // Padrão: 2 letras + 9 números + 2 letras (Internacional)
        const trackRegex = /\b[A-Za-z]{2}\d{9}[A-Za-z]{2}\b/;
        const match = textTrimmed.match(trackRegex);
        if (match && !isCommand) {
          const codigo = match[0].toUpperCase();
          log.info(`[SMART-TRACK] Código detectado automaticamente: ${codigo}`);

          // Simular digitação para parecer humano
          try { await sock.sendPresenceUpdate('composing', remoteJid); } catch (e) { }
          await new Promise(r => setTimeout(r, 1500));

          const result = await processAdminCommand(remoteJid, `/rastrear ${codigo}`, msg);
          if (result && result.message) {
            await safeSendMessage(sock, remoteJid, { text: result.message });
          }
          return;
        }


        // ===== AUTO ENTRAR EM GRUPOS (PRIVATE CHAT ONLY) =====
        const isGroup = isGroupJid(remoteJid);

        if (!isGroup && automationsSettings.auto_join_groups && text) {
          const inviteMatch = text.match(/chat\.whatsapp\.com\/([a-zA-Z0-9]{20,})/);
          if (inviteMatch) {
            const inviteCode = inviteMatch[1];
            log.info(`[AUTO-JOIN] Link de grupo detectado: ${inviteCode}`);

            try {
              // Validar convite
              const info = await sock.groupGetInviteInfo(inviteCode);
              log.info(`[AUTO-JOIN] Convite válido para grupo: ${info.subject}`);

              // Verificar se já está no grupo (participants)
              const alreadyIn = info.participants?.some(p => p.id?.includes(sock.user.id.split(':')[0]));

              if (alreadyIn) {
                await safeSendMessage(sock, remoteJid, { text: `✅ Já estou no grupo *${info.subject}*!` });
              } else {
                // Entrar no grupo
                await sock.groupAcceptInvite(inviteCode);
                log.success(`[AUTO-JOIN] Entrou no grupo: ${info.subject}`);
                await safeSendMessage(sock, remoteJid, { text: `✅ Entrei no grupo *${info.subject}* com sucesso!` });

                // Sincronizar grupos após entrar
                setTimeout(() => syncGroups(sock), 5000);
              }
            } catch (joinErr) {
              log.error(`[AUTO-JOIN] Erro ao entrar: ${joinErr.message}`);
              let errorMsg = '❌ Erro ao entrar no grupo.';
              if (joinErr.message.includes('401')) errorMsg = '❌ O link foi redefinido ou expirou.';
              if (joinErr.message.includes('409')) errorMsg = '✅ Eu já estou neste grupo.';

              await safeSendMessage(sock, remoteJid, { text: errorMsg });
            }
          }
        }

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
                    await safeSendMessage(sock, remoteJid, {
                      text: `🚫 *Anti-Link*\n\n@${senderNumber} foi removido por enviar link.\n\n_Links não são permitidos neste grupo._`,
                      mentions: [senderJid]
                    });
                    log.success(`[ANTILINK] ✅ Usuário ${senderNumber} removido por enviar link`);
                  } catch (removeError) {
                    log.error(`[ANTILINK] ❌ Erro ao remover usuário: ${removeError.message}`);
                    log.error(`[ANTILINK] Stack: ${removeError.stack}`);
                    // Se não conseguir remover, apenas avisar
                    await safeSendMessage(sock, remoteJid, {
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
          const isPrivateChat = !isGroupJid(remoteJid);

          // ===== MODO AQUECIMENTO (RESPOSTA PASSIVA) =====
          if (false && !automationProcessed && automationsSettings.warming_mode && isPrivateChat) {
            const lowerText = text.toLowerCase().trim();
            // Não responder comandos
            if (!lowerText.startsWith('/') && !lowerText.startsWith('$') && !lowerText.startsWith('!') && text.length >= 2) {
              processWarming(remoteJid, text);
              // Não damos return aqui para permitir que IA ou AutoReply também funcionem se necessário, 
              // mas idealmente o warming substitui o behavior padrão. 
              // Vamos dar return para que o warming seja o comportamento exclusivo quando ativado.
              return;
            }
          }

          // GARANTIDO: IA apenas no privado
          if (IA_ENABLED && isPrivateChat) {
            // Não processar comandos especiais ou mensagens muito curtas
            const lowerText = text.toLowerCase().trim();
            if (!lowerText.startsWith('/') && !lowerText.startsWith('$') && !lowerText.startsWith('!') && text.length >= 2) {
              const senderNumber = msg.key.participant ? msg.key.participant.split('@')[0] : remoteJid.split('@')[0];
              const iaResult = await processIAChat(remoteJid, text, senderNumber);

              if (iaResult && iaResult.success && iaResult.response) {
                await safeSendMessage(sock, remoteJid, { text: iaResult.response });
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
              await safeSendMessage(sock, remoteJid, {
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
botRouter.get('/status', (req, res) => {
  const uptime = connectionStartTime ? Math.round((Date.now() - connectionStartTime) / 1000) : 0;
  const memUsed = Math.round(process.memoryUsage().heapUsed / 1024 / 1024);

  res.json({
    ok: !isInLoopState,
    ready: isReady,
    loopState: isInLoopState,
    uptime: uptime,
    uptimeFormatted: `${Math.floor(uptime / 3600)}h ${Math.floor((uptime % 3600) / 60)}m ${uptime % 60}s`,
    reconnectAttempts: reconnectAttempts,
    recentDisconnects: disconnectTimestamps.length,
    memoryMB: memUsed,
    lastHeartbeat: new Date(lastHeartbeat).toISOString(),
    message: isInLoopState ? 'LOOP DETECTADO - Delete ./auth e reinicie' : 'OK'
  });
});

// QR Code (HTML para acesso direto)
botRouter.get('/qr', async (req, res) => {
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

// QR Code (JSON para API)
botRouter.get('/api/qr', auth, async (req, res) => {
  if (!lastQR) return res.json({ success: false, message: 'Nenhum QR disponível' });
  try {
    const dataUrl = await QRCodeImg.toDataURL(lastQR, { scale: 8, margin: 1 });
    res.json({ success: true, qr: dataUrl });
  } catch (e) {
    res.status(500).json({ success: false, error: e.message });
  }
});

// Health check
botRouter.get('/health', (req, res) => {
  res.json({
    status: isReady ? 'healthy' : 'unhealthy',
    timestamp: new Date().toISOString()
  });
});

// Resolve JID
async function resolveJidFromPhone(digits) {
  const pnJid = `${digits}@s.whatsapp.net`;

  const checkOne = async (jid) => {
    try {
      const res = await sock.onWhatsApp(jid);
      if (Array.isArray(res) && res.length > 0) {
        const item = res[0];
        const mapped = item?.jid || jid;
        const exists = !!item?.exists || !!item?.isBusiness || !!item?.isEnterprise;
        return { exists, mappedJid: mapped, error: null };
      }
      return { exists: false, mappedJid: jid, error: null };
    } catch (e) {
      return { exists: false, mappedJid: jid, error: e?.message || String(e) };
    }
  };

  // 1. Tentar original
  let result = await checkOne(pnJid);
  if (result.exists) return { exists: true, pnJid, mappedJid: result.mappedJid };

  // 2. Se falhar e for BR, tentar variação do 9º dígito
  if (digits.startsWith('55')) {
    let altDigits = null;
    if (digits.length === 13 && digits[4] === '9') {
      // Remover 9 (55 11 9 8888 8888 -> 55 11 8888 8888)
      altDigits = digits.substring(0, 4) + digits.substring(5);
    } else if (digits.length === 12) {
      // Adicionar 9 (55 11 8888 8888 -> 55 11 9 8888 8888)
      altDigits = digits.substring(0, 4) + '9' + digits.substring(4);
    }

    if (altDigits) {
      console.log(`[RESOLVE] Tentando variação BR: ${altDigits} (original: ${digits})`);
      const altJid = `${altDigits}@s.whatsapp.net`;
      const resultAlt = await checkOne(altJid);
      if (resultAlt.exists) {
        console.log(`[RESOLVE] Variação encontrada: ${altDigits}`);
        return { exists: true, pnJid: altJid, mappedJid: resultAlt.mappedJid };
      }
    }
  }

  return { exists: false, pnJid, mappedJid: pnJid, error: result.error || 'not_found' };
}

// Enviar mensagem
botRouter.post('/send', auth, async (req, res) => {
  try {
    if (!isReady) return res.status(503).json({ ok: false, error: 'not_ready' });

    let { to, text } = req.body || {};
    if (!to || !text) return res.status(400).json({ ok: false, error: 'missing_params' });

    log.info(`[SEND] Recebido destino: "${to}"`);

    let digits = to;
    let mappedJid = to;

    // Se NÃO for um JID completo (não contém @), resolver
    if (typeof to === 'string' && !to.includes('@')) {
      digits = formatBrazilNumber(to);
      log.info(`[SEND] Resolvendo número: ${digits}`);
      const resolution = await resolveJidFromPhone(digits);
      mappedJid = resolution.mappedJid;

      if (!resolution.exists) {
        log.warn(`[SEND] Número não verificado via onWhatsApp, tentando envio forçado: ${digits} -> ${mappedJid}`);
      } else {
        log.info(`[SEND] Número resolvido com sucesso: ${digits} -> ${mappedJid}`);
      }
    } else {
      // É um JID (ou LID), usar direto
      log.info(`[SEND] Destino é JID/LID direto. Ignorando resolução: ${to}`);
      digits = String(to).split('@')[0];
    }

    await safeSendMessage(sock, mappedJid, { text });
    lastHeartbeat = Date.now();

    log.success(`[SEND] Mensagem enviada para ${digits} (JID: ${mappedJid})`);
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
botRouter.post('/check', auth, async (req, res) => {
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
botRouter.post('/reconnect', auth, async (req, res) => {
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
botRouter.post('/reset-loop', auth, async (req, res) => {
  log.warn('Reset de estado de loop via API');
  isInLoopState = false;
  disconnectTimestamps = [];
  reconnectAttempts = 0;
  res.json({ ok: true, message: 'Estado de loop resetado. Use /reconnect para reconectar.' });
});

// Logout (Limpar sessão e gerar novo QR) - Útil para sessões travadas
export const logoutBot = async () => {
  log.warn('🚨 EXECUÇÃO DE LOGOUT COMPLETO');
  try {
    isReady = false;
    stopHeartbeat();

    if (sock) {
      log.info('Solicitando logout do socket...');
      try { await sock.logout(); } catch (e) { }
      try { sock.end(); } catch (e) { }
    }

    // Aguardar um pouco para garantir que o socket fechou
    await new Promise(r => setTimeout(r, 1000));

    log.warn(`Limpando pasta de autenticação: ${authPath}`);
    if (fs.existsSync(authPath)) {
      fs.rmSync(authPath, { recursive: true, force: true });
      log.success('Pasta de autenticação removida com sucesso');
    }

    // Encerrar processo - o PM2 irá reiniciar automaticamente
    setTimeout(() => {
      log.info('Reiniciando processo via PM2...');
      process.exit(0);
    }, 2000);

    return { success: true };
  } catch (error) {
    log.error(`Erro ao realizar logout: ${error.message}`);
    return { success: false, error: error.message };
  }
};

botRouter.post('/logout', auth, async (req, res) => {
  const result = await logoutBot();
  if (result.success) {
    res.json({
      ok: true,
      message: 'Sessão encerrada com sucesso! O bot será reiniciado agora para gerar um novo QR Code.'
    });
  } else {
    res.status(500).json({ ok: false, error: result.error });
  }
});

// Recarregar automações
botRouter.post('/reload-automations', auth, async (req, res) => {
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
botRouter.get('/automations', auth, (req, res) => {
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
botRouter.post('/send-poll', auth, async (req, res) => {
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

// ===== MARKETING ENDPOINTS =====

// Sincronizar membros de grupos (background)
botRouter.post('/sync-members', auth, async (req, res) => {
  res.json({ ok: true, message: 'Sincronização iniciada em background' });

  // Executar em background (sem await)
  (async () => {
    try {
      if (!isReady || !sock) return;

      log.info('[MARKETING] Iniciando sincronização de membros de grupos...');

      // Obter todos os grupos
      const groups = await sock.groupFetchAllParticipating();
      const groupJids = Object.keys(groups);

      log.info(`[MARKETING] Encontrados ${groupJids.length} grupos.`);

      for (const jid of groupJids) {
        try {
          const metadata = await sock.groupMetadata(jid);
          const participants = metadata.participants.map(p => p.id); // Guardar o JID completo para evitar problemas de 9º dígito

          // Enviar para API PHP salvar
          await axios.post(`${RASTREAMENTO_API_URL}/api_marketing.php?action=save_members`, {
            group_jid: jid,
            members: participants
          }, {
            headers: { 'x-api-token': RASTREAMENTO_TOKEN }
          });

          log.info(`[MARKETING] Salvos ${participants.length} membros do grupo ${metadata.subject}`);

          // Delay para não sobrecarregar
          await new Promise(r => setTimeout(r, 2000));
        } catch (err) {
          log.error(`[MARKETING] Erro ao processar grupo ${jid}: ${err.message}`);
        }
      }

      log.success('[MARKETING] Sincronização concluída!');
    } catch (e) {
      log.error(`[MARKETING] Falha geral na sync: ${e.message}`);
    }
  })();
});

// ===== MARKETING LOOP SYSTEM =====
// Flag para evitar sobreposição de execuções
let marketingTimer = null;
let isProcessingMarketing = false;

function startMarketingLoop() {
  if (marketingTimer) clearInterval(marketingTimer);

  // Rodar a cada 60 segundos
  marketingTimer = setInterval(async () => {
    if (isProcessingMarketing) {
      log.info('[MARKETING] Loop anterior ainda em execução, pulando este ciclo.');
      return;
    }

    isProcessingMarketing = true;

    try {
      // 1. Chamar API para processar a lógica diária e pegar tarefas pendentes
      const response = await axios.get(`${RASTREAMENTO_API_URL}/api_marketing.php?action=cron_process&token=${RASTREAMENTO_TOKEN}`);

      if (response.data && response.data.success && response.data.tasks) {
        const tasks = response.data.tasks;

        if (tasks.length > 0) {
          log.info(`[MARKETING] Processando ${tasks.length} tarefas de envio...`);

          for (const task of tasks) {
            // Executar envio
            const result = await sendMarketingMessage(task);

            // Reportar resultado
            await axios.post(`${RASTREAMENTO_API_URL}/api_marketing.php?action=update_task`, {
              member_id: task.member_id,
              step_order: task.step_order,
              success: result.success,
              reason: result.reason
            }, {
              headers: { 'x-api-token': RASTREAMENTO_TOKEN }
            });

            // Delay aleatório entre envios (safety - aumentado para evitar ban)
            await new Promise(r => setTimeout(r, rand(30000, 120000))); // 30s a 2min
          }
        }
      }
    } catch (e) {
      // Silencioso se der erro de conexão
      // log.error(`[MARKETING-LOOP] Erro: ${e.message}`);
    } finally {
      isProcessingMarketing = false;
    }
  }, 60000);

  log.info('[MARKETING] Loop iniciado (60s)');
}

// Cache para evitar envios duplicados em curto período (Dedup)
const marketingCache = new Map();
const MARKETING_CACHE_TTL = 10 * 60 * 1000; // 10 minutos

async function sendMarketingMessage(task) {
  try {
    if (!isReady || !sock) return { success: false, reason: 'not_ready' };

    const phone = task.phone;

    // VERIFICAÇÃO DE DEDUPLICAÇÃO (Cache Local)
    // Se enviamos para esse número nos últimos 10 min, ignorar e fingir sucesso
    // Isso protege contra falha de update na API que causa loop
    const lastSend = marketingCache.get(phone);
    if (lastSend && (Date.now() - lastSend < MARKETING_CACHE_TTL)) {
      log.warn(`[MARKETING] Bloqueando envio duplicado para ${phone} (Protect Loop)`);
      // Retorna sucesso para a API parar de tentar mandar
      return { success: true, reason: 'deduplicated' };
    }

    // Tratamento especial para LIDs (Device IDs) e JIDs longos
    const cleanPhone = formatBrazilNumber(phone);
    let jid = cleanPhone + '@s.whatsapp.net';
    let isLid = false;

    // Se for muito longo (>= 15), provavelmente é um LID
    if (cleanPhone.length >= 15) {
      log.info(`[MARKETING] Número longo detectado (${cleanPhone.length} dígitos). Tentando como LID...`);
      jid = cleanPhone + '@lid';
      isLid = true;
    }

    // Verificar se existe (Safety)
    // Se for LID, checkContactExists pode falhar ou retornar true direto, vamos testar
    let exists = await checkContactExists(sock, jid);

    // Se falhou como LID, tenta como user normal (vai que é um número gringo longo)
    if (!exists && isLid) {
      log.warn(`[MARKETING] Falha ao verificar LID ${jid}. Tentando fallback para @s.whatsapp.net`);
      jid = cleanPhone + '@s.whatsapp.net';
      exists = await checkContactExists(sock, jid);
    }

    if (!exists) return { success: false, reason: 'invalid_number' };

    // Setup da mensagem
    let msgContent = {};
    if (task.message_type === 'texto') {
      msgContent = { text: task.message };
    } else {
      // Suporte futuro a imagem/audio
      msgContent = { text: task.message };
    }

    // Enviar mensagem segura
    await safeSendMessage(sock, jid, msgContent);

    // MARCAR NO CACHE (Sucesso)
    marketingCache.set(phone, Date.now());

    // Limpar cache antigo de vez em quando
    if (marketingCache.size > 5000) {
      const now = Date.now();
      for (const [k, v] of marketingCache) {
        if (now - v > MARKETING_CACHE_TTL) marketingCache.delete(k);
      }
    }

    log.success(`[MARKETING] Mensagem enviada para ${task.phone}`);

    return { success: true };
  } catch (e) {
    log.error(`[MARKETING] Erro ao enviar para ${task.phone}: ${e.message}`);
    return { success: false, reason: 'error: ' + e.message };
  }
}

// Helper random
function rand(min, max) {
  return Math.floor(Math.random() * (max - min + 1) + min);
}

// ===== INICIALIZAÇÃO =====
/*
app.listen(PORT, () => {
  log.success(`API WhatsApp rodando em http://localhost:${PORT}`);
  log.info('Endpoints: /status, /qr, /health, /send, /check, /send-poll, /reconnect');
});
*/

// Iniciar conexão (Movido para o final do arquivo para garantir que tudo foi carregado)

// Monitoramento de memória
// Monitoramento de memória mais frequente
setInterval(checkMemory, MEMORY_CHECK_INTERVAL);

// Limpeza adicional a cada 30 segundos para caches críticos
setInterval(() => {
  const now = Date.now();
  const fiveMinutesAgo = now - 300000;

  // Limpar caches que crescem rapidamente
  for (const [key, value] of lastReplyAt.entries()) {
    if (now - value > AUTO_REPLY_WINDOW_MS) {
      lastReplyAt.delete(key);
    }
  }

  for (const [key, value] of waitingPhoto.entries()) {
    if (now - value.timestamp > 5 * 60 * 1000) {
      waitingPhoto.delete(key);
    }
  }

  // Limpar votos pendentes antigos
  for (const [key, value] of pendingPollVotes.entries()) {
    if (!value.timestamp || value.timestamp < fiveMinutesAgo) {
      pendingPollVotes.delete(key);
    }
  }

  // Enforçar limites (mais agressivos)
  enforceCacheLimit(lastReplyAt, 50);
  enforceCacheLimit(waitingPhoto, 30);
  enforceCacheLimit(pendingPollVotes, 30);

  // Limpar store preventivamente (apenas se habilitado)
  if (ENABLE_STORE) {
    const allJids = Object.keys(store.messages);
    if (allJids.length > MAX_STORE_CHATS_MEMORY) {
      const jidsWithTime = allJids.map(jid => {
        const msgs = store.messages[jid];
        const lastMsg = Object.values(msgs).reduce((latest, msg) => {
          const msgTime = msg?.messageTimestamp || msg?.key?.timestamp || 0;
          return msgTime > latest ? msgTime : latest;
        }, 0);
        return { jid, lastMsg };
      });
      jidsWithTime.sort((a, b) => b.lastMsg - a.lastMsg);

      for (let i = MAX_STORE_CHATS_MEMORY; i < jidsWithTime.length; i++) {
        delete store.messages[jidsWithTime[i].jid];
      }
    }
  }
}, 15000); // A cada 15 segundos

// Tratamento de erros não capturados para estabilidade em produção
process.on('uncaughtException', (err) => {
  log.error('❌ EXCEÇÃO NÃO CAPTURADA - RESTART DO PROCESSO NECESSÁRIO');
  log.error(`Erro: ${err.message}`);
  log.error(err.stack);

  // Dar tempo para o log ser gravado antes de sair
  setTimeout(() => {
    process.exit(1);
  }, 1000);
});

process.on('unhandledRejection', (reason, promise) => {
  log.error('⚠️ PROMISE REJEITADA E NÃO TRATADA');
  log.error(`Motivo: ${reason}`);
  if (reason instanceof Error) {
    log.error(reason.stack);
  }
});

// Tratamento de sinais de término
process.on('SIGINT', async () => {
  log.warn('Recebido SIGINT, encerrando...');
  stopHeartbeat();
  if (sock) {
    try { sock.end(); } catch (e) { }
  }
  process.exit(0);
});

process.on('SIGTERM', async () => {
  log.warn('Recebido SIGTERM, encerrando...');
  stopHeartbeat();
  if (sock) {
    try { sock.end(); } catch (e) { }
  }
  process.exit(0);
});

log.info('Bot WhatsApp iniciado com sistema de estabilidade ativo');
log.info(`Heartbeat: ${HEARTBEAT_INTERVAL / 1000}s | Ping: ${PING_INTERVAL / 1000}s | Timeout: ${CONNECTION_TIMEOUT / 1000}s | Max reconexões: ${MAX_RECONNECT_ATTEMPTS}`);

// Iniciar Loops Extras
startMarketingLoop();

// INICIAR O BOT
log.info('Chamando função start()...');

// Adicionar a rota coringa para o React Router por último (para não interceptar APIs)
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, '../webapp/dist/index.html'));
});

if (isMainModule) {
  start().catch((err) => {
    log.error(`Erro crítico ao iniciar bot: ${err.message}`);
    log.error(err.stack);
  });
}
