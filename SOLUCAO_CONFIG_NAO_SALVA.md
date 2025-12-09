# 🔧 Solução: Configurações não salvam (Express e Mensagens WhatsApp)

## ❌ Problema Relatado

Quando você altera as configurações de:
- **Entrega Expressa** (valor da taxa e chave PIX)
- **Mensagens WhatsApp** (templates personalizados)

E volta nessas páginas, elas aparecem com os valores padrão ao invés das suas alterações.

---

## ✅ Solução Implementada

Criei **3 melhorias** para resolver o problema:

### 1️⃣ **Página de Diagnóstico** (`debug_config.php`)

Acesse: `https://seu-dominio.com/debug_config.php`

Esta página mostra:
- ✅ Se o arquivo `config.json` existe e tem permissões corretas
- ✅ Conteúdo atual das configurações
- ✅ Teste de escrita em tempo real
- ✅ Erros do PHP (se houver)
- ✅ Permissões do diretório

**Use para identificar o problema!**

---

### 2️⃣ **Limpeza de Cache Automática**

Agora, ao salvar configurações:
- Limpa cache do OpCode do PHP
- Força releitura do arquivo
- Verifica se realmente salvou

**Elimina problemas de cache!**

---

### 3️⃣ **Feedback Melhorado**

As páginas agora mostram:
- ✅ Confirmação com os valores salvos
- ⚠️ Avisos se algo deu errado
- 🔍 Botão de diagnóstico em cada página

---

## 🎯 Como Testar Agora

### **Passo 1: Upload dos Arquivos**
Envie para sua Hostinger:
- `debug_config.php` (novo)
- `admin_settings.php` (atualizado)
- `admin_mensagens.php` (atualizado)

### **Passo 2: Verificar Permissões**
Acesse: `https://seu-dominio.com/debug_config.php`

Procure por:
```
1. ARQUIVO CONFIG.JSON:
   Existe: SIM
   Legível: SIM
   Gravável: SIM  <-- IMPORTANTE!
   Permissões: 0644 ou 0664
```

Se aparecer **"Gravável: NÃO"**, faça:

**Via FileManager da Hostinger:**
1. Clique com botão direito em `config.json`
2. Permissões → 644 ou 664
3. Salve

**Via FTP:**
```
chmod 664 config.json
```

---

### **Passo 3: Testar Salvamento**

1. **Configuração Express:**
   - Vá em: `admin.php` → **Configurações Expressa**
   - Altere o valor da taxa: `35.50`
   - Altere a chave PIX: `sua-chave@pix.com`
   - Clique em **Salvar**
   
   **Deve aparecer:**
   ```
   ✅ Configurações salvas e verificadas com sucesso!
   
   Valor: R$ 35,50
   PIX: sua-chave@pix.com
   ```

2. **Mensagens WhatsApp:**
   - Vá em: `admin.php` → **Mensagens WhatsApp**
   - Edite qualquer mensagem
   - Clique em **💾 Salvar Todas as Mensagens**
   
   **Deve aparecer:**
   ```
   ✅ 6 mensagem(ns) salva(s) e verificada(s) com sucesso!
   ```

3. **Verificar se Persistiu:**
   - **RECARREGUE a página** (F5 ou Ctrl+R)
   - Suas alterações devem aparecer!

---

## 🚨 Se Ainda Não Funcionar

### **Problema: Permissões**

Se o diagnóstico mostrar `Gravável: NÃO`:

**Solução:**
```bash
# Via SSH na Hostinger
cd /home/seu-usuario/public_html
chmod 664 config.json
chown seu-usuario:seu-usuario config.json
```

---

### **Problema: Cache do Navegador**

Se você vê os valores novos depois de salvar, mas ao recarregar voltam aos antigos:

**Solução:**
1. Limpe o cache do navegador:
   - Chrome: `Ctrl+Shift+Delete`
   - Firefox: `Ctrl+Shift+Delete`
   - Edge: `Ctrl+Shift+Delete`

2. Ou use modo anônimo/privado para testar

3. Ou force reload sem cache: `Ctrl+F5`

---

### **Problema: OpCache do PHP**

Se usa servidor compartilhado (Hostinger):

**Solução:**
Adicione no `.htaccess` da raiz:
```apache
<IfModule mod_php7.c>
    php_flag opcache.enable Off
</IfModule>

<IfModule mod_php8.c>
    php_flag opcache.enable Off
</IfModule>
```

---

### **Problema: Arquivo config.json Corrompido**

Se o diagnóstico mostrar `JSON inválido`:

**Solução:**
1. Faça backup do `config.json` atual
2. Crie um novo com estrutura básica:

```json
{
  "titulo_pagina": "Helmer Logistics S/A — Acompanhamento de Recebimentos",
  "mensagem_inicial": "Acompanhe seus recebimentos em tempo real — sistema premium",
  "erro_consulta": "❌ Código inválido ou recebimento não encontrado.",
  "mensagem_aguarde": "⏳ Consultando status do recebimento...",
  "footer_fake": "© 2025 Helmer Logistics | Todos os direitos reservados.",
  "WHATSAPP_API_URL": "https://lazaro-enforceable-finley.ngrok-free.dev",
  "WHATSAPP_API_TOKEN": "lucastav8012",
  "WHATSAPP_API_ENABLED": true,
  "WHATSAPP_TEMPLATE": "Olá {nome}! Seu pedido {codigo} foi atualizado:\n{status}\n{descricao}\n{link}",
  "WHATSAPP_TRACKING_URL": "https://cornflowerblue-fly-883408.hostingersite.com?codigo={{codigo}}",
  "ADMIN_WHATSAPP_NUMBERS": ["5551996148568", "551996148568", "5537991101425", "553791101425"],
  "EXPRESS_FEE_VALUE": 29.90,
  "EXPRESS_PIX_KEY": "pix@exemplo.com"
}
```

3. Faça upload
4. Configure permissões: 664
5. Teste novamente

---

## 📊 Checklist de Verificação

- [ ] Arquivo `config.json` existe
- [ ] Permissões do arquivo: 644 ou 664
- [ ] Diretório tem permissão de escrita
- [ ] Diagnóstico mostra "Gravável: SIM"
- [ ] Teste de escrita no diagnóstico funciona
- [ ] Cache do navegador limpo
- [ ] Ao salvar, mensagem de sucesso aparece
- [ ] Ao recarregar (F5), valores permanecem
- [ ] Valores aparecem no site público

---

## 🎯 Resultado Esperado

Depois das correções:

1. **Você altera as configurações**
2. **Clica em Salvar**
3. **Ve mensagem de sucesso com os valores**
4. **Recarrega a página**
5. **Suas alterações permanecem!** ✅

---

## 💡 Dica Extra: Botão de Diagnóstico

Em ambas as páginas de configuração agora há um botão **🔍 Diagnóstico**.

Use-o sempre que algo não salvar para ver exatamente qual é o problema!

---

## 📞 Suporte

Se após seguir todos os passos ainda não funcionar, envie o resultado de:
```
https://seu-dominio.com/debug_config.php
```

Isso mostrará exatamente onde está o problema!



