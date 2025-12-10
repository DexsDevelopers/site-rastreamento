# 🚨 INSTRUÇÕES URGENTES - Parar e Reiniciar o Bot

## ⚠️ O PROBLEMA

O erro "unauthorized" persiste porque:

1. ✅ O token foi sincronizado no arquivo `.env`
2. ❌ **MAS o bot Node.js não foi reiniciado**
3. ❌ O Node.js carrega o `.env` apenas quando **INICIA**
4. ❌ Mudanças no `.env` **NÃO têm efeito** enquanto o bot está rodando

## ✅ SOLUÇÃO DEFINITIVA

### Passo 1: PARAR o bot Node.js

**Opção A - Via Terminal:**
1. Vá até o terminal onde o bot está rodando
2. Pressione `Ctrl+C`
3. Aguarde até ver a mensagem que o processo foi encerrado

**Opção B - Via PowerShell (forçar parada):**
```powershell
# Ver processos Node.js
Get-Process node

# Parar TODOS os processos Node.js (CUIDADO!)
Stop-Process -Name node -Force

# Verificar se parou
Get-Process node  # Não deve retornar nada
```

### Passo 2: VERIFICAR se parou completamente

```powershell
Get-Process node -ErrorAction SilentlyContinue
```

Se não retornar nada, está OK. Se retornar processos, execute:
```powershell
Stop-Process -Name node -Force
```

### Passo 3: SINCRONIZAR o token (novamente, para garantir)

```powershell
cd "c:\Users\Johan 7K\Documents\GitHub\site-rastreamento"
.\scripts\corrigir_token_agora.ps1
```

Ou:
```powershell
.\scripts\sync_whatsapp_token.ps1
```

### Passo 4: REINICIAR o bot Node.js

```powershell
cd whatsapp-bot
npm run dev
```

**OBSERVE a primeira linha de log!** Você deve ver algo como:
```
🔑 API_TOKEN carregado: luca***8012 (11 chars)
```

Se aparecer `troque-este-token`, significa que o `.env` não foi lido corretamente.

### Passo 5: TESTAR

1. Aguarde 5-10 segundos após iniciar o bot
2. Acesse: `http://seu-dominio/verificar_token_bot.php`
3. Ou: `http://seu-dominio/test_token_direto.php`

## 🔍 VERIFICAÇÃO ADICIONAL

### Ver qual token o bot está usando:

Quando o bot iniciar, procure no console por:
```
🔑 API_TOKEN carregado: ...
```

Se você ver:
- ✅ `luca***8012 (11 chars)` → Token correto!
- ❌ `troque-***token` → Token não foi carregado do .env
- ❌ `NÃO DEFINIDO` → Problema no .env

### Ver qual token está sendo recebido:

Quando testar e receber erro 401, o bot agora mostra nos logs:
```
❌ Auth failed: received="luca***" (X chars), expected="outr***" (Y chars)
```

Compare os valores!

## 📝 CHECKLIST FINAL

- [ ] Parei o bot Node.js (Ctrl+C ou Stop-Process)
- [ ] Verifiquei que não há processos Node.js rodando
- [ ] Executei o script de sincronização
- [ ] Reiniciei o bot (`npm run dev`)
- [ ] Verifiquei que o token foi carregado corretamente nos logs
- [ ] Testei novamente

## 🆘 SE AINDA NÃO FUNCIONAR

1. Verifique o conteúdo exato do `.env`:
   ```powershell
   Get-Content whatsapp-bot\.env
   ```
   
2. Deve estar exatamente assim:
   ```
   API_TOKEN=lucastav8012
   ```
   Sem aspas, sem espaços antes ou depois do `=`

3. Verifique se o bot está lendo o `.env`:
   - O bot deve estar na pasta `whatsapp-bot`
   - O arquivo `.env` deve estar na mesma pasta que `index.js`

4. Execute o teste direto:
   ```
   http://seu-dominio/test_token_direto.php
   ```
   
   Isso mostra a comparação byte a byte dos tokens.
