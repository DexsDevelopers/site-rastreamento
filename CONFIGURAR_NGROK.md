# 🌐 Como Expor o Bot WhatsApp para a Hostinger

## 🔴 Problema Atual

A Hostinger está tentando acessar `http://localhost:3000`, mas isso não funciona porque:
- `localhost` na Hostinger = servidor da Hostinger (não seu PC)
- Seu bot está rodando no seu PC local
- Precisa expor o bot para a internet

## ✅ Solução: Usar ngrok ou Cloudflared

### **Opção 1: ngrok (RECOMENDADO)**

#### **1. Instalar ngrok:**
- Baixe em: https://ngrok.com/download
- Extraia o arquivo `ngrok.exe`
- Coloque em uma pasta (ex: `C:\ngrok\`)

#### **2. Criar conta (grátis):**
- Acesse: https://dashboard.ngrok.com/signup
- Faça login e copie seu **authtoken**

#### **3. Configurar ngrok:**
```bash
# Abra o PowerShell na pasta do ngrok
cd C:\ngrok
.\ngrok.exe authtoken SEU_TOKEN_AQUI
```

#### **4. Iniciar túnel:**
```bash
# Com o bot rodando em localhost:3000
.\ngrok.exe http 3000
```

#### **5. Copiar a URL:**
Você verá algo como:
```
Forwarding  https://abc123.ngrok-free.app -> http://localhost:3000
```

#### **6. Atualizar config.json na Hostinger:**
```json
{
  "WHATSAPP_API_URL": "https://abc123.ngrok-free.app",
  "WHATSAPP_API_TOKEN": "lucastav8012",
  "WHATSAPP_API_ENABLED": true
}
```

⚠️ **IMPORTANTE:** A URL do ngrok muda a cada vez que você reinicia (na versão grátis). Use ngrok com domínio fixo ou cloudflared.

---

### **Opção 2: Cloudflared (URL Fixa)**

#### **1. Instalar Cloudflared:**
- Baixe em: https://github.com/cloudflare/cloudflared/releases
- Extraia `cloudflared.exe`

#### **2. Iniciar túnel:**
```bash
cloudflared tunnel --url http://localhost:3000
```

#### **3. Copiar a URL:**
Você verá algo como:
```
https://random-subdomain.trycloudflare.com
```

#### **4. Atualizar config.json:**
```json
{
  "WHATSAPP_API_URL": "https://random-subdomain.trycloudflare.com",
  "WHATSAPP_API_TOKEN": "lucastav8012"
}
```

---

### **Opção 3: ngrok com Domínio Fixo (PAGO)**

Se você tem plano pago do ngrok, pode usar domínio fixo:

```bash
ngrok http 3000 --domain=seu-dominio.ngrok.app
```

Isso mantém a URL sempre a mesma.

---

## 🚀 Script Automático para Iniciar

Crie um arquivo `iniciar_bot_com_tunel.bat`:

```batch
@echo off
echo Iniciando Bot WhatsApp com Túnel...

REM Iniciar ngrok em background
start "ngrok" cmd /k "cd C:\ngrok && ngrok.exe http 3000"

REM Aguardar ngrok iniciar
timeout /t 3

REM Iniciar bot
cd whatsapp-bot
start "Bot WhatsApp" cmd /k "npm run dev"

echo.
echo Bot e túnel iniciados!
echo Acesse http://localhost:4040 para ver a URL do ngrok
pause
```

---

## 📝 Checklist de Configuração

- [ ] Bot WhatsApp rodando em `localhost:3000`
- [ ] ngrok ou cloudflared instalado
- [ ] Túnel ativo e funcionando
- [ ] URL do túnel copiada
- [ ] `config.json` na Hostinger atualizado com URL do túnel
- [ ] Testado com `test_whatsapp_manual.php`

---

## 🔍 Verificar se Está Funcionando

### **1. Testar túnel localmente:**
```bash
curl https://sua-url-ngrok.ngrok-free.app/status
```

Deve retornar JSON com `"ready": true`

### **2. Testar da Hostinger:**
Acesse: `https://seu-dominio.com/test_whatsapp_manual.php?codigo=GH56YJ1474BR`

Deve mostrar sucesso no envio.

---

## ⚠️ Problemas Comuns

### **Erro: "Connection refused"**
- Verifique se o bot está rodando
- Verifique se o túnel está ativo
- Verifique se a porta 3000 está correta

### **Erro: "Tunnel not found"**
- ngrok expirou (versão grátis)
- Reinicie o ngrok e atualize a URL

### **Erro: "Timeout"**
- Verifique sua internet
- Verifique firewall do Windows
- Tente cloudflared como alternativa

---

## 💡 Dica: Manter Túnel Sempre Ativo

Use **NSSM** (Non-Sucking Service Manager) para rodar ngrok como serviço do Windows:

1. Baixe NSSM: https://nssm.cc/download
2. Instale ngrok como serviço:
```bash
nssm install ngrok "C:\ngrok\ngrok.exe" http 3000
nssm start ngrok
```

Isso mantém o túnel rodando mesmo após reiniciar o PC.


