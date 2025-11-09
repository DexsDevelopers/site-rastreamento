<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Painel de Testes - WhatsApp Bot</title>
    <style>
      :root {
        color-scheme: light dark;
        --bg: #0f172a;
        --bg-card: rgba(15, 23, 42, 0.85);
        --bg-input: rgba(255, 255, 255, 0.05);
        --border: rgba(148, 163, 184, 0.2);
        --accent: #22d3ee;
        --text: #e2e8f0;
        --text-muted: #94a3b8;
        --shadow: 0 25px 50px -12px rgba(15, 118, 110, 0.35);
      }

      [data-theme='light'] {
        --bg: #f8fafc;
        --bg-card: rgba(255, 255, 255, 0.9);
        --bg-input: rgba(15, 23, 42, 0.05);
        --border: rgba(15, 23, 42, 0.12);
        --accent: #0ea5e9;
        --text: #0f172a;
        --text-muted: #475569;
        --shadow: 0 20px 45px -18px rgba(14, 165, 233, 0.35);
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        min-height: 100vh;
        font-family: 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        background: linear-gradient(135deg, var(--bg), rgba(14, 116, 144, 0.2));
        color: var(--text);
        display: flex;
        justify-content: center;
        padding: 32px 16px;
      }

      .container {
        width: 100%;
        max-width: 1100px;
        display: grid;
        gap: 24px;
      }

      .card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 24px;
        padding: 28px;
        backdrop-filter: blur(18px);
        box-shadow: var(--shadow);
      }

      .header {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        align-items: center;
        justify-content: space-between;
      }

      .title {
        font-size: clamp(24px, 3vw, 32px);
        display: flex;
        flex-direction: column;
        gap: 6px;
      }

      .title span {
        font-size: clamp(14px, 2vw, 16px);
        color: var(--text-muted);
      }

      .button {
        background: linear-gradient(135deg, var(--accent), rgba(34, 211, 238, 0.6));
        border: none;
        color: #0f172a;
        font-weight: 600;
        border-radius: 999px;
        padding: 12px 22px;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }

      .button.secondary {
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text);
      }

      .button:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 25px -15px rgba(56, 189, 248, 0.8);
      }

      .form-grid {
        display: grid;
        gap: 20px;
      }

      .group {
        display: grid;
        gap: 10px;
      }

      label {
        font-weight: 600;
        color: var(--text);
      }

      input,
      textarea {
        border-radius: 16px;
        border: 1px solid var(--border);
        background: var(--bg-input);
        color: var(--text);
        padding: 14px 16px;
        font-size: 15px;
        transition: border 0.2s ease;
        width: 100%;
      }

      input:focus,
      textarea:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.2);
      }

      textarea {
        min-height: 120px;
        resize: vertical;
      }

      .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
      }

      .grid-responsive {
        display: grid;
        gap: 24px;
      }

      @media (min-width: 900px) {
        .grid-responsive {
          grid-template-columns: 1fr 1fr;
        }
      }

      pre {
        margin: 0;
        padding: 24px;
        background: rgba(15, 23, 42, 0.6);
        border-radius: 16px;
        font-size: 14px;
        white-space: pre-wrap;
        word-break: break-word;
      }

      .status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 999px;
        border: 1px solid var(--border);
        font-size: 14px;
        color: var(--text-muted);
        background: rgba(148, 163, 184, 0.08);
      }

      .status-indicator span {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        background: #fbbf24;
      }

      .status-indicator[data-ready='true'] span {
        background: #22c55e;
      }

      .badge {
        font-size: 13px;
        border-radius: 999px;
        padding: 4px 12px;
        background: rgba(34, 211, 238, 0.15);
        color: var(--accent);
        border: 1px solid rgba(34, 211, 238, 0.35);
      }

      .toggle {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 8px;
        border: 1px solid var(--border);
        background: rgba(148, 163, 184, 0.08);
        cursor: pointer;
        gap: 10px;
        font-size: 14px;
      }

      .toggle input {
        display: none;
      }
    </style>
  </head>
  <body data-theme="dark">
    <div class="container">
      <header class="card header">
        <div class="title">
          <div>Console de Testes - WhatsApp Bot</div>
          <span>Valide rapidamente o status da sessão e o envio de mensagens via API HTTP.</span>
        </div>
        <div class="actions">
          <label class="toggle" id="themeToggle">
            <input type="checkbox" />
            <span>Alternar tema</span>
          </label>
          <button class="button secondary" type="button" id="openQrButton">Abrir QR</button>
        </div>
      </header>

      <section class="card">
        <div class="group" style="margin-bottom: 24px;">
          <span class="badge">Configuração da API</span>
          <p style="margin: 0; color: var(--text-muted); font-size: 14px;">
            Informe abaixo a URL exposta (ngrok/cloudflared) e o token definido no arquivo <code>.env</code> do serviço Node.
          </p>
        </div>
        <div class="form-grid">
          <div class="group">
            <label for="apiUrl">API Base URL</label>
            <input id="apiUrl" type="url" name="apiUrl" placeholder="https://seu-subdominio.ngrok-free.dev" required />
          </div>
          <div class="group">
            <label for="apiToken">API Token</label>
            <input id="apiToken" type="password" name="apiToken" placeholder="Token secreto definido no .env" required />
          </div>
        </div>
      </section>

      <section class="grid-responsive">
        <article class="card">
          <div class="group">
            <span class="badge">Monitoramento</span>
            <h2 style="margin: 4px 0 0 0;">Status da Sessão</h2>
            <p style="margin: 0; color: var(--text-muted); font-size: 14px;">
              Consulte o endpoint <code>/status</code> para confirmar se o bot está pareado e pronto para envio.
            </p>
          </div>
          <div class="actions" style="margin: 20px 0;">
            <button class="button" type="button" id="statusButton">Verificar Status</button>
            <span class="status-indicator" id="statusIndicator" data-ready="false">
              <span></span>
              Aguardando...
            </span>
          </div>
          <pre id="statusOutput">Clique em "Verificar Status" para consultar a API.</pre>
        </article>

        <article class="card">
          <div class="group">
            <span class="badge">Validação de Número</span>
            <h2 style="margin: 4px 0 0 0;">Endpoint /check</h2>
            <p style="margin: 0; color: var(--text-muted); font-size: 14px;">
              Normaliza o telefone informado e confirma se ele está ativo no WhatsApp usando <code>onWhatsApp</code>.
            </p>
          </div>
          <form id="checkForm" class="form-grid" autocomplete="off">
            <div class="group">
              <label for="checkNumber">Telefone</label>
              <input id="checkNumber" type="tel" name="checkNumber" placeholder="Ex.: 11999999999" required />
            </div>
            <div class="actions">
              <button class="button" type="submit">Validar Número</button>
            </div>
          </form>
          <pre id="checkOutput">Preencha o número e clique em "Validar Número".</pre>
        </article>
      </section>

      <section class="card">
        <div class="group">
          <span class="badge">Envio de Mensagem</span>
          <h2 style="margin: 4px 0 0 0;">Endpoint /send</h2>
          <p style="margin: 0; color: var(--text-muted); font-size: 14px;">
            Envia mensagens de texto para contatos individuais. O número é normalizado automaticamente.
          </p>
        </div>
        <form id="sendForm" class="form-grid" autocomplete="off">
          <div class="group">
            <label for="sendNumber">Telefone</label>
            <input id="sendNumber" type="tel" name="sendNumber" placeholder="Ex.: 5511999999999" required />
          </div>
          <div class="group">
            <label for="sendMessage">Mensagem</label>
            <textarea id="sendMessage" name="sendMessage" placeholder="Digite a mensagem que deseja enviar" required></textarea>
          </div>
          <div class="actions">
            <button class="button" type="submit">Enviar Mensagem</button>
            <button class="button secondary" type="button" id="clearLogsButton">Limpar Logs</button>
          </div>
        </form>
        <pre id="sendOutput">Os resultados do envio aparecerão aqui.</pre>
      </section>
    </div>

    <script>
      const apiUrlInput = document.getElementById('apiUrl');
      const apiTokenInput = document.getElementById('apiToken');
      const statusButton = document.getElementById('statusButton');
      const statusOutput = document.getElementById('statusOutput');
      const statusIndicator = document.getElementById('statusIndicator');
      const checkForm = document.getElementById('checkForm');
      const checkOutput = document.getElementById('checkOutput');
      const sendForm = document.getElementById('sendForm');
      const sendOutput = document.getElementById('sendOutput');
      const clearLogsButton = document.getElementById('clearLogsButton');
      const openQrButton = document.getElementById('openQrButton');
      const themeToggle = document.getElementById('themeToggle');
      const body = document.body;

      const formatJson = (payload) => {
        try {
          return JSON.stringify(payload, null, 2);
        } catch (err) {
          return String(payload);
        }
      };

      const getConfig = () => {
        const baseURL = apiUrlInput.value.trim().replace(/\/+$/, '');
        const token = apiTokenInput.value.trim();

        if (!baseURL) {
          throw new Error('Informe a API Base URL.');
        }

        if (!token) {
          throw new Error('Informe o API Token.');
        }

        return { baseURL, token };
      };

      const handleRequest = async (method, endpoint, payload) => {
        const { baseURL, token } = getConfig();
        const url = `${baseURL}${endpoint}`;
        const options = {
          method,
          headers: {
            'Content-Type': 'application/json',
            'x-api-token': token
          }
        };

        if (payload) {
          options.body = JSON.stringify(payload);
        }

        const response = await fetch(url, options);
        const data = await response.json().catch(() => ({
          ok: false,
          error: 'invalid_json_response'
        }));

        return {
          status: response.status,
          ok: response.ok,
          data
        };
      };

      statusButton.addEventListener('click', async () => {
        statusOutput.textContent = 'Consultando /status...';
        statusIndicator.dataset.ready = 'false';

        try {
          const result = await handleRequest('GET', '/status');
          statusOutput.textContent = formatJson(result);
          statusIndicator.dataset.ready = String(Boolean(result.data?.ready));
          statusIndicator.innerHTML = `<span></span>${result.data?.ready ? 'Conectado' : 'Pendente'} · HTTP ${result.status}`;
        } catch (err) {
          statusOutput.textContent = err.message;
          statusIndicator.dataset.ready = 'false';
          statusIndicator.innerHTML = '<span></span>Erro';
        }
      });

      checkForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const to = document.getElementById('checkNumber').value.trim();

        checkOutput.textContent = 'Consultando /check...';

        try {
          const result = await handleRequest('POST', '/check', { to });
          checkOutput.textContent = formatJson(result);
        } catch (err) {
          checkOutput.textContent = err.message;
        }
      });

      sendForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const to = document.getElementById('sendNumber').value.trim();
        const text = document.getElementById('sendMessage').value.trim();

        sendOutput.textContent = 'Enviando mensagem...';

        try {
          const result = await handleRequest('POST', '/send', { to, text });
          sendOutput.textContent = formatJson(result);
        } catch (err) {
          sendOutput.textContent = err.message;
        }
      });

      clearLogsButton.addEventListener('click', () => {
        statusOutput.textContent = 'Clique em "Verificar Status" para consultar a API.';
        checkOutput.textContent = 'Preencha o número e clique em "Validar Número".';
        sendOutput.textContent = 'Os resultados do envio aparecerão aqui.';
        statusIndicator.dataset.ready = 'false';
        statusIndicator.innerHTML = '<span></span>Aguardando...';
      });

      openQrButton.addEventListener('click', () => {
        try {
          const { baseURL, token } = getConfig();
          const target = `${baseURL}/qr?token=${encodeURIComponent(token)}`;
          window.open(target, '_blank', 'noopener');
        } catch (err) {
          alert(err.message);
        }
      });

      themeToggle.addEventListener('click', () => {
        const isDark = body.getAttribute('data-theme') === 'dark';
        body.setAttribute('data-theme', isDark ? 'light' : 'dark');
      });
    </script>
  </body>
</html>

