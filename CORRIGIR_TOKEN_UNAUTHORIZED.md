# 🚨 CORREÇÃO URGENTE - Erro "unauthorized"

## ⚠️ Problema

O erro `unauthorized` continua ocorrendo mesmo após sincronizar o token.

## 🔍 Causas Possíveis

### 1. Bot Node.js não foi reiniciado
**O MAIS PROVÁVEL!** O Node.js lê o arquivo `.env` apenas **na inicialização**. 

Se você atualizou o `.env` mas não reiniciou o bot, ele ainda está usando o token antigo na memória.

### 2. Token com espaços ou caracteres invisíveis
Às vezes o token pode ter espaços extras ou BOM (Byte Order Mark) que não são visíveis.

### 3. Header case-sensitive
Alguns proxies/servidores podem alterar o case do header.

## ✅ SOLUÇÃO DEFINITIVA - Passo a Passo

### Passo 1: Sincronizar Token
```powershell
.\sync_whatsapp_token.ps1
```

### Passo 2: VERIFICAR se o .env foi atualizado
```powershell
Get-Content whatsapp-bot\.env
```

Você deve ver:
```
API_PORT=3000
API_TOKEN=lucastav8012
```

### Passo 3: **PARAR o bot Node.js**
- Pressione `Ctrl+C` no terminal onde o bot está rodando
- **AGUARDE** até o processo terminar completamente

### Passo 4: **REINICIAR o bot Node.js**
```bash
cd whatsapp-bot
npm run dev
```

### Passo 5: VERIFICAR se o bot carregou o token correto
- Observe a primeira linha de log quando o bot inicia
- Se aparecer algum aviso sobre token, significa que não leu corretamente

### Passo 6: TESTAR novamente
- Acesse: `http://seu-dominio/test_whatsapp_endpoint.php?codigo=GH56YJ1474BR`
- Ou: `http://seu-dominio/verificar_token_bot.php`

## 🧪 Ferramentas de Diagnóstico

### 1. Verificador Web (Recomendado)
Acesse: `verificar_token_bot.php`

Mostra:
- Token no config.json
- Token no .env
- Se correspondem
- Teste de conexão em tempo real

### 2. Teste JSON
Acesse: `test_token_sync.php`

Retorna JSON com diagnóstico completo.

### 3. Script PowerShell
Execute: `.\sync_whatsapp_token.ps1`

Sincroniza automaticamente.

## 🔧 Se Ainda Não Funcionar

### Verificação Manual do .env

1. Abra `whatsapp-bot/.env` em um editor de texto
2. Verifique se está exatamente assim (sem aspas, sem espaços extras):
   ```
   API_TOKEN=lucastav8012
   ```
3. **NÃO deve ter:**
   - Aspas: `API_TOKEN="lucastav8012"` ❌
   - Espaços: `API_TOKEN = lucastav8012` ❌
   - BOM ou caracteres invisíveis

### Verificar se o Bot Está Lendo o Token

No arquivo `whatsapp-bot/index.js`, adicione temporariamente na linha 35:

```javascript
const API_TOKEN = process.env.API_TOKEN || 'troque-este-token';
console.log('🔑 API_TOKEN carregado:', API_TOKEN ? `${API_TOKEN.substring(0, 4)}***` : 'NÃO DEFINIDO');
```

Ao reiniciar o bot, você verá nos logs qual token foi carregado.

### Verificar Processo do Node

Certifique-se de que não há múltiplas instâncias do bot rodando:

```powershell
Get-Process node | Where-Object {$_.Path -like "*whatsapp-bot*"}
```

Se houver múltiplas, termine todas e inicie apenas uma.

## 📝 Checklist Final

- [ ] Executei `.\sync_whatsapp_token.ps1`
- [ ] Verifiquei que o `.env` tem `API_TOKEN=lucastav8012`
- [ ] **PAREI completamente o bot Node.js (Ctrl+C)**
- [ ] **REINICIEI o bot Node.js (`npm run dev`)**
- [ ] Testei novamente e ainda dá erro?

Se completou tudo e ainda não funciona, verifique:
1. Se o ngrok está apontando para a porta correta (3000)
2. Se há firewall bloqueando
3. Se há proxy intermediário alterando headers
