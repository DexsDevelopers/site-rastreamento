# 📁 Scripts PowerShell

Esta pasta contém todos os scripts PowerShell do projeto para facilitar a localização e organização.

## 📋 Scripts Disponíveis

### 🔐 `sync_whatsapp_token.ps1`
Sincroniza o token do WhatsApp do `config.json` para o arquivo `.env` do bot Node.js.

**Uso:**
```powershell
.\scripts\sync_whatsapp_token.ps1
```

**O que faz:**
- Lê o token do `config.json` (WHATSAPP_API_TOKEN)
- Atualiza o arquivo `whatsapp-bot/.env` com o token correto
- Mantém outras configurações do .env intactas

---

### 🚨 `corrigir_token_agora.ps1`
Script URGENTE para corrigir token com verificações detalhadas e diagnóstico completo.

**Uso:**
```powershell
.\scripts\corrigir_token_agora.ps1
```

**O que faz:**
- Força a sincronização do token
- Remove o .env antigo e cria um novo (garante limpeza)
- Verifica byte a byte se os tokens correspondem
- Mostra análise hexadecimal
- Verifica processos Node.js rodando
- Fornece instruções detalhadas

**Quando usar:**
- Quando o erro "unauthorized" persiste
- Após atualizar o token no config.json
- Para diagnóstico completo do problema

---

### 📦 `verificar_deploy.ps1`
Script para verificar o deploy e status do projeto.

**Uso:**
```powershell
.\scripts\verificar_deploy.ps1
```

---

## 🔄 Como Usar os Scripts

### Opção 1: Executar diretamente
```powershell
cd "c:\Users\Johan 7K\Documents\GitHub\site-rastreamento"
.\scripts\sync_whatsapp_token.ps1
```

### Opção 2: Com permissão explícita
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\sync_whatsapp_token.ps1
```

## ⚠️ Importante

Após executar qualquer script de sincronização de token:

1. ✅ **PARE o bot Node.js** (Ctrl+C no terminal)
2. ✅ **REINICIE o bot Node.js** (`cd whatsapp-bot && npm run dev`)
3. ✅ O Node.js carrega o `.env` apenas na inicialização!

## 📝 Notas

- Todos os scripts foram testados no Windows PowerShell
- Os scripts são seguros e não modificam arquivos além do necessário
- Sempre faça backup antes de executar scripts (especialmente em produção)
