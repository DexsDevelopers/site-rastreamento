# 🚀 Código Auto-Increment - Documentação

## 📋 Funcionalidade

Sistema automático que incrementa o último número/letra do código de rastreamento, facilitando a adição de códigos sequenciais.

## ✨ Recursos

1. **Memória do Último Código**
   - Salva automaticamente o último código usado no `localStorage`
   - Lembra mesmo após fechar o navegador

2. **Sugestão Automática**
   - Ao abrir o formulário, sugere o próximo código automaticamente
   - Baseado no último código salvo

3. **Incremento Inteligente**
   - Detecta se o código termina com número ou letra
   - Incrementa corretamente:
     - `ABC123` → `ABC124`
     - `ABC001` → `ABC002`
     - `ABC9` → `ABC10`
     - `XYZ` → `XZA` (letras)

4. **Botão de Incremento**
   - Botão "+1" ao lado do campo de código
   - Clique para incrementar rapidamente

5. **Atalho de Teclado**
   - `Ctrl + Plus` (ou `Ctrl + =`) para incrementar
   - Funciona quando o campo está focado

## 🎯 Como Usar

### Método 1: Botão +1
1. Digite o código base (ex.: `ABC123`)
2. Clique no botão **+1** ao lado do campo
3. O código será incrementado para `ABC124`

### Método 2: Atalho de Teclado
1. Digite o código base (ex.: `ABC123`)
2. Pressione `Ctrl + Plus` (ou `Ctrl + =`)
3. O código será incrementado

### Método 3: Automático
1. Após salvar um código (ex.: `ABC123`)
2. Ao abrir o formulário novamente
3. O campo já virá preenchido com `ABC124`

## 📝 Exemplos

### Códigos Numéricos
```
ABC001 → ABC002
ABC123 → ABC124
ABC999 → ABC1000
CODE9 → CODE10
```

### Códigos com Letras
```
XYZ → XZA
ABC → ABD
```

### Código Vazio
- Se você não digitar nada e clicar em +1
- O sistema pegará o último código salvo
- E incrementará ele

## 🔧 Funcionamento Técnico

### Detecção de Padrão
- Procura primeiro por dígitos numéricos no final
- Se não encontrar, procura por letras
- Mantém o formato original (maiúsculas/minúsculas, zeros à esquerda)

### Armazenamento
- Usa `localStorage` do navegador
- Chave: `helmer_last_codigo`
- Persiste entre sessões

### Integração
- Funciona com o formulário `addForm`
- Compatível com validação AJAX existente
- Não interfere com outras funcionalidades

## ⚙️ Personalização

### Alterar Comportamento
Edite `assets/js/codigo-auto-increment.js`:

```javascript
// Mudar chave de armazenamento
const STORAGE_KEY = 'helmer_last_codigo'; // Altere aqui

// Mudar ID do campo
const CODIGO_INPUT_ID = 'codigo'; // Altere aqui
```

### Estilizar Botão
O botão usa CSS inline, mas você pode estilizar em `admin.php`:

```css
.btn-increment-codigo {
    /* Suas customizações */
}
```

## 🐛 Solução de Problemas

### Botão não aparece
- Verifique se o campo tem `id="codigo"`
- Confira o console do navegador para erros
- Certifique-se que o script está carregado

### Não incrementa corretamente
- O código precisa terminar com número ou letra
- Exemplos válidos: `ABC123`, `XYZ`, `123ABC`
- Exemplos inválidos: `ABC-123` (caractere especial no final)

### Não lembra último código
- Verifique se o `localStorage` está habilitado
- Limpe o cache se necessário
- O código só é salvo após submit bem-sucedido

## 📱 Compatibilidade

- ✅ Chrome/Edge (recomendado)
- ✅ Firefox
- ✅ Safari
- ✅ Navegadores modernos com suporte a ES6+

## 🔒 Segurança

- Os dados ficam apenas no navegador do usuário
- Não são enviados para o servidor
- Podem ser limpos pelo usuário a qualquer momento

---

**Criado em:** 2025-01-15  
**Versão:** 1.0  
**Status:** ✅ Funcional

