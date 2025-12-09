# 🤖 PROMPT: Sistema de Comandos Admin via WhatsApp Bot

Use este prompt para replicar o sistema completo de gerenciamento administrativo via WhatsApp em outros projetos.

---

## 📋 PROMPT PARA A IA:

```
Preciso implementar um sistema completo de comandos administrativos via WhatsApp Bot para gerenciar meu painel PHP.

ESTRUTURA DO PROJETO ATUAL:
- Backend: PHP com MySQL
- Bot: Node.js com Baileys (WhatsApp Web API)
- Arquivos principais: [descrever sua estrutura]

FUNCIONALIDADES NECESSÁRIAS:

1. API PHP (admin_bot_api.php):
   - Receber comandos do bot Node.js
   - Validar token de segurança
   - Verificar permissões por número de WhatsApp
   - Processar comandos administrativos
   - Retornar respostas formatadas

2. Comandos que preciso:
   - /menu - Listar todos os comandos disponíveis
   - /adicionar PARAMETROS - Criar novo registro
   - /listar [limite] - Listar últimos registros
   - /status ID - Ver detalhes de um registro
   - /deletar ID - Remover registro
   - /foto ID - Anexar foto a um registro (receber imagem depois)
   - /relatorio - Estatísticas gerais do sistema
   - /notificar ID MENSAGEM - Enviar mensagem personalizada
   - Comando público /rastrear ID - Qualquer pessoa pode consultar

3. Bot Node.js (whatsapp-bot/index.js):
   - Detectar mensagens que começam com /
   - Extrair número do remetente do JID
   - Enviar comando para API PHP via axios
   - Suportar upload de fotos via FormData
   - Sistema de "aguardar foto" após comando /foto
   - Logs detalhados de debug

4. Configuração:
   - Arquivo config.json com array ADMIN_WHATSAPP_NUMBERS
   - Suportar múltiplos formatos de número (com/sem 9 adicional)
   - Token de API compartilhado
   - URL da API configurável

5. Segurança:
   - Validação de token em todas as requisições
   - Verificação de permissões por número
   - Comandos públicos vs comandos admin
   - Logs de todas as ações

6. Extras:
   - Página de debug (debug_admin_whatsapp.php) para diagnóstico
   - Suporte a envio de fotos pelo WhatsApp
   - Mensagens formatadas com emojis
   - Sistema de help por comando (/ajuda COMANDO)

ESTRUTURA DE RESPOSTA DOS COMANDOS:
{
  "success": true/false,
  "message": "Mensagem formatada com *negrito* e emojis"
}

REQUISITOS TÉCNICOS:
- PHP 7.4+
- Node.js 18+
- Baileys (WhatsApp Web API)
- axios e form-data no Node.js
- MySQL/PDO no PHP

OBSERVAÇÕES IMPORTANTES:
- Números brasileiros podem vir com ou sem o 9 adicional (55119XXXX vs 5511XXXX)
- JID do WhatsApp vem como numero@s.whatsapp.net ou numero@lid
- Preciso limpar o JID removendo @s.whatsapp.net, @lid e qualquer :texto
- Sistema de "aguardar foto" expira em 5 minutos
- Todas as ações devem gerar logs

ARQUIVOS A CRIAR:
1. admin_bot_api.php - API principal
2. admin_bot_photo.php - Receber fotos do bot
3. debug_admin_whatsapp.php - Página de diagnóstico
4. Atualizar whatsapp-bot/index.js - Lógica do bot
5. Atualizar config.json - Adicionar ADMIN_WHATSAPP_NUMBERS
6. whatsapp-bot/.env - Configurações do bot
7. COMANDOS_WHATSAPP.md - Documentação

EXEMPLO DE FLUXO:
1. Admin envia "/adicionar ABC123 São Paulo" no WhatsApp
2. Bot recebe, extrai número do admin
3. Bot envia POST para API PHP com comando, params e from
4. API valida token, verifica se número é admin
5. API processa comando e retorna resposta
6. Bot envia resposta formatada de volta no WhatsApp

Por favor, implemente:
- Toda a estrutura de arquivos
- Sistema de permissões completo
- Logs de debug detalhados
- Página de diagnóstico
- Documentação de uso
- Configure tudo para funcionar em produção

Mantenha o código limpo, comentado e pronto para produção.
```

---

## 📝 INFORMAÇÕES ADICIONAIS PARA PASSAR À IA:

### Seus números admin:
```
5551996148568  (número do bot)
5537991101425  (admin adicional)
```

### URL da sua API:
```
https://seu-dominio.com
```

### Token de segurança:
```
seu-token-aqui
```

---

## 🎯 COMANDOS PARA A IA SEGUIR:

Depois que a IA gerar os arquivos, peça:

1. "Configure os números admin no config.json"
2. "Crie o arquivo .env para o bot"
3. "Adicione logs detalhados de debug"
4. "Faça commit e push de tudo"
5. "Crie instruções de instalação"
6. "Teste o sistema com comandos de exemplo"

---

## 📦 DEPENDÊNCIAS NECESSÁRIAS:

### PHP (via Composer ou manual):
- PDO MySQL
- JSON

### Node.js (package.json):
```json
{
  "dependencies": {
    "@whiskeysockets/baileys": "^6.7.21",
    "axios": "^1.6.2",
    "cors": "^2.8.5",
    "dotenv": "^16.4.5",
    "express": "^4.19.2",
    "form-data": "^4.0.0",
    "pino": "^9.3.2",
    "qrcode": "^1.5.4",
    "qrcode-terminal": "^0.12.0"
  }
}
```

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO:

- [ ] API PHP criada e funcional
- [ ] Bot Node.js detectando comandos
- [ ] Sistema de permissões funcionando
- [ ] Upload de fotos operacional
- [ ] Comandos públicos vs admin separados
- [ ] Logs de debug implementados
- [ ] Página de diagnóstico criada
- [ ] Documentação completa
- [ ] Testes realizados
- [ ] Deploy em produção
- [ ] QR Code escaneado
- [ ] Primeiro comando testado com sucesso

---

## 🚀 RESULTADO ESPERADO:

Ao enviar `/menu` no WhatsApp, receber:

```
📋 *MENU DE COMANDOS ADMIN*

*📦 GESTÃO*
/adicionar PARAMS - Criar novo
/status ID - Ver detalhes
/listar - Ver últimos
/deletar ID - Remover

*📸 FOTOS*
/foto ID - Anexar foto

*📊 RELATÓRIOS*
/relatorio - Estatísticas

*💬 COMUNICAÇÃO*
/notificar ID MSG - Avisar

*❓ AJUDA*
/menu - Este menu
/ajuda COMANDO - Detalhes
```

---

## 💡 DICAS PARA A IA:

- Sempre use logs detalhados (writeLog)
- Valide todas as entradas
- Formate mensagens com emojis
- Use try-catch em todas as funções
- Documente cada função
- Crie mensagens de erro descritivas
- Teste ambos formatos de número brasileiro
- Adicione página de debug desde o início
- Mantenha código modular e reutilizável
- Commit após cada etapa importante

---

**📌 COPIE TODO O CONTEÚDO ACIMA E COLE NO CHAT DA IA!**



