# 🛡️ Sistema de Segurança Anti-Ban

Este bot inclui um sistema completo de proteção para evitar banimento do WhatsApp.

## ⚙️ Configurações Disponíveis

Adicione estas variáveis no arquivo `.env`:

```env
# Ativar/desativar sistema de segurança
SAFETY_ENABLED=true

# Limites de mensagens
MAX_MESSAGES_PER_MINUTE=20          # Máximo 20 mensagens por minuto (global)
MAX_MESSAGES_PER_HOUR=200           # Máximo 200 mensagens por hora (global)
MAX_MESSAGES_PER_CHAT_PER_MINUTE=5  # Máximo 5 mensagens por chat/minuto

# Delays entre mensagens
MIN_DELAY_BETWEEN_MESSAGES=1000     # 1 segundo mínimo entre mensagens
ENABLE_DELAYS=true                  # Ativar delays automáticos

# Verificações
CHECK_CONTACT_BEFORE_SEND=true      # Verificar se contato existe antes de enviar
```

## 🔒 Proteções Implementadas

### 1. **Rate Limiting Global**
- Limite de mensagens por minuto (padrão: 20)
- Limite de mensagens por hora (padrão: 200)
- Contadores são resetados automaticamente

### 2. **Rate Limiting por Chat**
- Limite de mensagens por chat/minuto (padrão: 5)
- Evita spam em grupos ou chats individuais

### 3. **Cooldown entre Mensagens**
- Delay mínimo entre mensagens (padrão: 1 segundo)
- Aplica delay automático quando necessário

### 4. **Cooldown de Comandos**
- 2 segundos entre comandos do mesmo tipo
- Protege contra abuso de comandos ($ban, $kick, etc)

### 5. **Verificação de Contato**
- Verifica se o número existe no WhatsApp antes de enviar
- Evita enviar para números inválidos

### 6. **Sistema de Blacklist**
- Números problemáticos são bloqueados temporariamente
- Remoção automática após 1 hora

### 7. **Limpeza Automática**
- Contadores antigos são limpos automaticamente
- Otimiza uso de memória

## 📊 Logs de Segurança

O bot registra automaticamente:
- Quando rate limits são atingidos
- Quando comandos estão em cooldown
- Quando contatos não existem
- Quando números são adicionados à blacklist

## ⚠️ Recomendações

1. **Não desative o sistema de segurança** a menos que seja absolutamente necessário
2. **Ajuste os limites** conforme o uso do bot
3. **Monitore os logs** para identificar padrões de uso
4. **Use delays maiores** se o bot enviar muitas mensagens

## 🚨 O que fazer se receber rate limit?

Se o bot atingir um rate limit:
- Aguarde o tempo indicado nos logs
- Reduza a frequência de mensagens
- Aumente os delays entre mensagens
- Verifique se há spam ou uso excessivo

## 📝 Exemplo de Configuração Conservadora

Para bots com muito tráfego, use configurações mais conservadoras:

```env
SAFETY_ENABLED=true
MAX_MESSAGES_PER_MINUTE=10
MAX_MESSAGES_PER_HOUR=100
MAX_MESSAGES_PER_CHAT_PER_MINUTE=3
MIN_DELAY_BETWEEN_MESSAGES=2000
ENABLE_DELAYS=true
CHECK_CONTACT_BEFORE_SEND=true
```

## 📝 Exemplo de Configuração Agressiva

Para bots com pouco tráfego, pode usar limites maiores:

```env
SAFETY_ENABLED=true
MAX_MESSAGES_PER_MINUTE=50
MAX_MESSAGES_PER_HOUR=500
MAX_MESSAGES_PER_CHAT_PER_MINUTE=10
MIN_DELAY_BETWEEN_MESSAGES=500
ENABLE_DELAYS=true
CHECK_CONTACT_BEFORE_SEND=true
```

