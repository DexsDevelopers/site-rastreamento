# 📱 Comandos WhatsApp Admin

Sistema completo de gerenciamento do painel via WhatsApp Bot.

## 🚀 Configuração Inicial

### 1. Configurar Números Admin
Edite o arquivo `config.json` e adicione os números dos administradores:
```json
"ADMIN_WHATSAPP_NUMBERS": ["5511999999999", "5511888888888"]
```

### 2. Configurar o Bot
No diretório `whatsapp-bot/`, crie um arquivo `.env`:
```env
API_PORT=3000
API_TOKEN=lucastav8012
ADMIN_API_URL=https://cornflowerblue-fly-883408.hostingersite.com
ADMIN_NUMBERS=5511999999999,5511888888888
AUTO_REPLY=false
```

### 3. Instalar Dependências
```bash
cd whatsapp-bot
npm install
```

### 4. Iniciar o Bot
```bash
npm run dev
```

## 📋 Menu de Comandos

Digite `/menu` no WhatsApp para ver todos os comandos disponíveis.

### 📦 Gestão de Rastreios
- `/adicionar CODIGO CIDADE` - Criar novo rastreio
- `/status CODIGO` - Ver etapas atuais
- `/listar [quantidade]` - Ver últimos códigos (máx 50)
- `/deletar CODIGO` - Remover rastreio

### 💰 Gestão de Taxas
- `/taxa CODIGO VALOR PIX` - Adicionar taxa
- `/limpartaxa CODIGO` - Remover taxa
- `/express CODIGO` - Aplicar entrega expressa (3 dias)

### 📸 Gestão de Fotos
1. Digite `/foto CODIGO`
2. Envie a foto logo em seguida
3. A foto será anexada ao pedido

### 📊 Consultas
- `/relatorio` - Estatísticas completas do sistema
- `/pendentes` - Listar códigos sem foto

### 💬 Comunicação
- `/notificar CODIGO MENSAGEM` - Enviar mensagem personalizada ao cliente

### ❓ Ajuda
- `/ajuda COMANDO` - Ver detalhes de um comando específico
- `/menu` - Exibir menu completo

## 🌍 Comando Público

### Para Clientes
- `/rastrear CODIGO` - Qualquer pessoa pode rastrear seu pedido

## 💡 Exemplos de Uso

### Adicionar novo rastreio
```
/adicionar GH56YJ1469BR São Paulo
```

### Definir taxa
```
/taxa GH56YJ1469BR 29.90 email@exemplo.com
```

### Anexar foto
```
/foto GH56YJ1469BR
[Enviar a imagem]
```

### Notificar cliente
```
/notificar GH56YJ1469BR Seu pedido está a caminho! Chegará hoje entre 14h e 18h.
```

### Aplicar entrega express
```
/express GH56YJ1469BR
```

## 🔧 Troubleshooting

### Bot não responde aos comandos
1. Verifique se o número está na lista de admins em `config.json`
2. Confirme que o bot está rodando (`npm run dev`)
3. Verifique se o token está correto em ambos os lados

### Erro ao enviar foto
1. A foto deve ser enviada logo após o comando `/foto`
2. O código precisa existir no sistema
3. Tamanho máximo: 10MB

### Cliente não recebe notificação
1. Verifique se o WhatsApp do cliente está cadastrado
2. Confirme que o bot está conectado
3. Teste com `/notificar CODIGO teste`

## 📝 Logs

Os logs são salvos em `logs/system.log` com informações de todos os comandos executados.

## 🔐 Segurança

- Apenas números cadastrados em `ADMIN_WHATSAPP_NUMBERS` podem usar comandos administrativos
- O comando `/rastrear` é público e pode ser usado por qualquer pessoa
- Todas as ações são registradas com timestamp e número do executor

## 🎯 Fluxo Recomendado

1. **Novo pedido chega:**
   - `/adicionar CODIGO CIDADE`
   - `/foto CODIGO` + enviar imagem
   
2. **Cliente pergunta sobre pedido:**
   - Orientar a usar `/rastrear CODIGO`
   
3. **Problema com alfândega:**
   - `/taxa CODIGO VALOR PIX`
   - Cliente será notificado automaticamente
   
4. **Entrega urgente:**
   - `/express CODIGO`
   - Reduz tempo para 3 dias

5. **Verificação diária:**
   - `/relatorio` - ver estatísticas
   - `/pendentes` - códigos sem foto

## 🆘 Suporte

Em caso de problemas, verifique:
1. Logs em `logs/system.log`
2. Status do bot com `http://localhost:3000/status`
3. QR Code em `http://localhost:3000/qr`
