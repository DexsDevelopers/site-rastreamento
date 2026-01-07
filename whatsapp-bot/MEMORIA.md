# 🧠 Gerenciamento de Memória

Este documento explica como o bot gerencia memória e como resolver problemas de "out of memory".

## ⚠️ Problema: JavaScript heap out of memory

Se você receber o erro `FATAL ERROR: Reached heap limit Allocation failed - JavaScript heap out of memory`, siga estas soluções:

## 🔧 Soluções

### 1. **Aumentar Limite de Memória do Node.js**

#### Opção A: Usar scripts do package.json (Recomendado)
```bash
npm run start        # 4GB de memória
npm run start:watch  # 4GB com watch mode
npm run start:gc     # 4GB com garbage collection exposto
```

#### Opção B: Executar manualmente
```bash
node --max-old-space-size=4096 index.js
```

#### Opção C: Windows (PowerShell)
```powershell
$env:NODE_OPTIONS="--max-old-space-size=4096"
node index.js
```

#### Opção D: Linux/Mac
```bash
NODE_OPTIONS="--max-old-space-size=4096" node index.js
```

### 2. **Habilitar Garbage Collection Manual**

Para habilitar GC manual (recomendado para produção):

```bash
node --max-old-space-size=4096 --expose-gc index.js
```

O bot automaticamente forçará GC quando a memória estiver alta.

### 3. **Verificar Configurações de Limpeza**

O bot agora inclui limpeza automática agressiva:

- **Limpeza a cada 30 segundos**: Contadores e caches expirados
- **Limpeza a cada 1 minuto**: Monitoramento completo de memória
- **Limpeza agressiva**: Quando memória > 400MB
- **Limpeza crítica**: Quando memória > 600MB

## 📊 Limites de Cache Implementados

Todos os caches agora têm limites máximos:

| Cache | Limite Normal | Limite Agressivo |
|-------|--------------|------------------|
| Mensagens por chat | 50 | 30 |
| Total de chats | 100 | 20 |
| Contadores de segurança | 500 | 100 |
| Cooldowns | 500 | 100 |
| Polls context | 100 | 50 |
| Automações | 500 | 100 |

## 🔍 Monitoramento

O bot monitora memória automaticamente e registra:

- **⚠️ Memória moderada** (>400MB): Limpeza automática de caches
- **🚨 Memória crítica** (>600MB): Limpeza agressiva + GC forçado

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

✅ Limites máximos em todos os caches  
✅ Limpeza automática a cada 30 segundos  
✅ Limpeza agressiva quando memória alta  
✅ Limite de mensagens no store (50 por chat)  
✅ Limite de chats no store (100 total)  
✅ Limpeza de caches expirados  
✅ Monitoramento contínuo de memória  
✅ Garbage collection forçado quando necessário  

## ⚡ Performance

Com as melhorias implementadas:
- **Uso normal de memória**: 100-300MB
- **Uso moderado**: 300-500MB (limpeza automática)
- **Uso alto**: 500-700MB (limpeza agressiva)
- **Uso crítico**: >700MB (GC forçado + limpeza total)

## 📞 Suporte

Se o problema persistir mesmo após seguir estas instruções:

1. Verifique logs do bot para ver padrões de uso
2. Monitore memória com `process.memoryUsage()`
3. Considere aumentar recursos do servidor
4. Verifique se há memory leaks em dependências

