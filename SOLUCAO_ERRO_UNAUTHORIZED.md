# Solução para Erro "unauthorized" no WhatsApp Bot

## 🔍 Problema Identificado

O erro `unauthorized` ocorre quando o token configurado no `config.json` não corresponde ao token configurado no arquivo `.env` do bot Node.js.

### Como funciona a autenticação:

1. **PHP (Sistema)** → Lê o token de `config.json` (`WHATSAPP_API_TOKEN`)
2. **Envia** o token no header `x-api-token` para o bot Node.js
3. **Bot Node.js** → Lê o token do arquivo `.env` (`API_TOKEN`)
4. **Valida** se os tokens correspondem
5. **Se não corresponder** → Retorna `401 Unauthorized` com erro `"unauthorized"`

## ✅ Solução

### Opção 1: Script Automático (Recomendado)

Execute o script PowerShell que sincroniza automaticamente o token:

```powershell
.\sync_whatsapp_token.ps1
```

Este script:
- ✅ Lê o token do `config.json`
- ✅ Atualiza o arquivo `.env` do bot com o token correto
- ✅ Mantém outras configurações do `.env` intactas

### Opção 2: Sincronização Manual

1. **Verifique o token no `config.json`:**
   ```json
   {
     "WHATSAPP_API_TOKEN": "lucastav8012"
   }
   ```

2. **Edite ou crie o arquivo `whatsapp-bot/.env`:**
   ```
   API_PORT=3000
   API_TOKEN=lucastav8012
   ```

3. **Reinicie o bot Node.js:**
   ```bash
   cd whatsapp-bot
   npm run dev
   ```

## 🔄 Após Sincronizar

**IMPORTANTE:** Após atualizar o `.env`, você **DEVE reiniciar o bot Node.js** para que as mudanças tenham efeito.

```bash
# Pare o bot atual (Ctrl+C)
# Depois inicie novamente:
cd whatsapp-bot
npm run dev
```

## 🧪 Como Testar

Execute o arquivo de teste:

```
http://seu-dominio/test_whatsapp_endpoint.php?codigo=GH56YJ1474BR
```

Agora o teste mostrará:
- ✅ Detalhes completos sobre erros de autenticação
- ✅ Token usado (mascarado por segurança)
- ✅ Instruções específicas para resolver o problema

## 📝 Verificação Rápida

**Token no config.json:**
```bash
# PowerShell
(Get-Content config.json | ConvertFrom-Json).WHATSAPP_API_TOKEN
```

**Token no .env (se existir):**
```bash
# PowerShell
Select-String -Path "whatsapp-bot\.env" -Pattern "^API_TOKEN="
```

**Ambos devem ter o mesmo valor!**

## 🛠️ Melhorias Implementadas

1. ✅ Script `sync_whatsapp_token.ps1` para sincronização automática
2. ✅ Melhorias no `test_whatsapp_endpoint.php` para detectar erros de autenticação
3. ✅ Melhorias no `whatsapp_helper.php` para identificar erros `unauthorized`
4. ✅ Mensagens de erro mais claras e acionáveis

## ⚠️ Importante

- O arquivo `.env` está no `.gitignore` por segurança
- Não commite o `.env` no Git
- Mantenha os tokens sincronizados entre `config.json` e `.env`
- Reinicie o bot sempre que alterar o `.env`
