# 🧠 Gerenciamento de Memória

Este documento explica como o bot gerencia memória e como resolver problemas de "out of memory".

## ⚠️ Problema: JavaScript heap out of memory

Se você receber o erro `FATAL ERROR: Reached heap limit Allocation failed - JavaScript heap out of memory`, siga estas soluções:

## 🔧 Soluções

### 1. **Aumentar Limite de Memória do Node.js**

#### ⭐ Opção A: Usar scripts do package.json (RECOMENDADO)
```bash
npm run start        # 4GB de memória + GC exposto (PADRÃO)
npm run start:watch  # 4GB com watch mode + GC
npm run start:8gb    # 8GB de memória (para muito alto tráfego)
```

#### ⭐ Opção B: Usar scripts de inicialização (MAIS FÁCIL)
**Windows:**
```bash
start.bat
```

**Linux/Mac:**
```bash
chmod +x start.sh
./start.sh
```

#### Opção C: Executar manualmente
```bash
node --max-old-space-size=4096 --expose-gc index.js
```

### 2. **Store de Mensagens Desabilitado por Padrão**

O bot agora **desabilita o store de mensagens por padrão** para economizar memória. Isso significa:
- ✅ **Economia de memória**: Não armazena mensagens antigas
- ✅ **Menos vazamentos**: Menos dados em memória
- ✅ **Mais estável**: Menos chance de OOM

Para habilitar o store (não recomendado):
```env
ENABLE_STORE=true
```

Se habilitado, o store mantém apenas:
- 5 mensagens por chat
- 10 chats no total

### 3. **Verificar Configurações de Limpeza**

O bot agora inclui limpeza automática agressiva:

- **Limpeza a cada 30 segundos**: Contadores e caches expirados
- **Limpeza a cada 1 minuto**: Monitoramento completo de memória
- **Limpeza agressiva**: Quando memória > 400MB
- **Limpeza crítica**: Quando memória > 600MB

## 📊 Limites de Cache Implementados

Todos os caches agora têm limites máximos (reduzidos para evitar OOM):

| Cache | Limite Normal | Limite Preventivo | Limite Crítico |
|-------|--------------|-------------------|----------------|
| Mensagens por chat | 20 | 15 | 10 |
| Total de chats | 50 | 30 | 10 |
| Contadores de segurança | 100 | 50 | 0 (limpar tudo) |
| Cooldowns | 100 | 50 | 0 (limpar tudo) |
| Polls context | 50 | 30 | 50 |
| Automações | 100 | 50 | 50 |

## 🔍 Monitoramento

O bot monitora memória automaticamente e registra:

- **⚠️ Memória moderada** (>300MB): Limpeza preventiva de caches
- **⚠️ Memória alta** (>400MB): Limpeza agressiva de caches
- **🚨 Memória crítica** (>500MB): Limpeza de emergência + GC forçado (limpa quase tudo)

## 🛠️ Troubleshooting

### O bot ainda está consumindo muita memória

1. **Verifique se está usando o script correto:**
   ```bash
   npm run start
   ```

2. **Habilite GC manual:**
   ```bash
   npm run start:gc
   ```

3. **Reduza limites no código** (edite `index.js`):
   ```javascript
   const MAX_CACHE_SIZE = 500; // Reduzir de 1000 para 500
   const MAX_STORE_MESSAGES = 30; // Reduzir de 50 para 30
   ```

4. **Aumente frequência de limpeza:**
   ```javascript
   const MEMORY_CHECK_INTERVAL = 30000; // 30 segundos ao invés de 1 minuto
   ```

### O bot está lento após limpeza

Isso é normal. A limpeza agressiva pode causar pequenas pausas. Se persistir:

1. Aumente o limite de memória:
   ```bash
   node --max-old-space-size=6144 index.js  # 6GB
   ```

2. Reduza frequência de limpeza:
   ```javascript
   const MEMORY_CHECK_INTERVAL = 120000; // 2 minutos
   ```

## 📝 Configurações Recomendadas

### Desenvolvimento
```bash
npm run start:watch
```

### Produção (Baixo Tráfego)
```bash
node --max-old-space-size=2048 index.js  # 2GB
```

### Produção (Alto Tráfego)
```bash
node --max-old-space-size=4096 --expose-gc index.js  # 4GB + GC
```

### Produção (Muito Alto Tráfego)
```bash
node --max-old-space-size=6144 --expose-gc index.js  # 6GB + GC
```

## 🔄 Melhorias Implementadas

✅ Limites máximos reduzidos em todos os caches (200 entradas)  
✅ Limpeza automática a cada 15 segundos (muito frequente)  
✅ Limpeza preventiva quando memória > 300MB  
✅ Limpeza agressiva quando memória > 400MB  
✅ Limpeza de emergência quando memória > 500MB (limpa quase tudo)  
✅ Limite de mensagens no store (20 por chat, reduzido de 50)  
✅ Limite de chats no store (50 total, reduzido de 100)  
✅ Limpeza de caches expirados a cada 15 segundos  
✅ Monitoramento contínuo de memória a cada 30 segundos  
✅ Garbage collection forçado quando memória crítica  

## ⚡ Performance

Com as melhorias implementadas:
- **Uso normal de memória**: 100-250MB
- **Uso moderado**: 250-300MB (limpeza preventiva)
- **Uso alto**: 300-400MB (limpeza agressiva)
- **Uso crítico**: 400-500MB (limpeza muito agressiva)
- **Emergência**: >500MB (limpeza total + GC forçado)

## 📞 Suporte

Se o problema persistir mesmo após seguir estas instruções:

1. Verifique logs do bot para ver padrões de uso
2. Monitore memória com `process.memoryUsage()`
3. Considere aumentar recursos do servidor
4. Verifique se há memory leaks em dependências

