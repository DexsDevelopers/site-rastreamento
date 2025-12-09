# 🚀 Guia de Deploy para Hostinger

## 📦 Arquivos que Precisam ser Enviados

### **Arquivos Principais Modificados:**
1. `admin.php` - Botão WhatsApp e melhorias
2. `includes/whatsapp_helper.php` - Melhorias no envio
3. `config.json` - URL atualizada para localhost:3000
4. `test_whatsapp_manual.php` - Script de teste (NOVO)

## 📤 Métodos de Upload

### **Método 1: File Manager (cPanel) - RECOMENDADO**

1. **Acesse o cPanel da Hostinger**
   - URL: `https://hpanel.hostinger.com`
   - Faça login com suas credenciais

2. **Abra o File Manager**
   - Procure por "File Manager" no cPanel
   - Navegue até `public_html/` (ou `domains/seu-dominio.com/public_html/`)

3. **Faça Upload dos Arquivos:**
   - Clique em "Upload" no topo
   - Selecione os arquivos:
     - `admin.php`
     - `includes/whatsapp_helper.php`
     - `config.json`
     - `test_whatsapp_manual.php` (opcional, para testes)
   - Aguarde o upload completar

4. **Verificar Permissões:**
   - Arquivos PHP: `644` (padrão)
   - Pastas: `755` (padrão)

### **Método 2: FTP (FileZilla ou similar)**

1. **Credenciais FTP:**
   - Host: `ftp.seu-dominio.com` ou IP do servidor
   - Usuário: Seu usuário FTP
   - Senha: Sua senha FTP
   - Porta: `21` (ou `22` para SFTP)

2. **Conectar:**
   - Use FileZilla, WinSCP ou outro cliente FTP
   - Conecte ao servidor

3. **Navegar até:**
   ```
   /public_html/
   ```

4. **Fazer Upload:**
   - Arraste os arquivos da pasta local para o servidor
   - Substitua os arquivos existentes quando solicitado

### **Método 3: Git (se configurado)**

Se você tem Git configurado na Hostinger:

```bash
# No servidor (via SSH ou terminal do cPanel)
cd public_html
git pull origin main
```

## ⚠️ IMPORTANTE: Configurações Após Upload

### **1. Atualizar config.json na Hostinger**

O `config.json` local está configurado para `localhost:3000`, mas na Hostinger você precisa:

**Opção A: Usar ngrok (se bot está rodando local)**
```json
{
  "WHATSAPP_API_URL": "https://seu-ngrok-url.ngrok-free.dev",
  "WHATSAPP_API_TOKEN": "lucastav8012",
  "WHATSAPP_API_ENABLED": true
}
```

**Opção B: Se o bot estiver em outro servidor**
```json
{
  "WHATSAPP_API_URL": "http://ip-do-servidor:3000",
  "WHATSAPP_API_TOKEN": "lucastav8012",
  "WHATSAPP_API_ENABLED": true
}
```

### **2. Verificar Permissões**

Após o upload, verifique se os arquivos têm as permissões corretas:
- Arquivos PHP: `644`
- Pasta `includes/`: `755`
- Pasta `logs/`: `755` (se existir)

### **3. Testar o Sistema**

1. **Acesse o script de teste:**
   ```
   https://seu-dominio.com/test_whatsapp_manual.php?codigo=GH56YJ1474BR
   ```

2. **Teste o botão no admin:**
   - Acesse: `https://seu-dominio.com/admin.php`
   - Clique no botão verde do WhatsApp em qualquer código
   - Verifique se aparece mensagem de sucesso/erro

## 🔍 Verificação Pós-Deploy

### **Checklist:**
- [ ] `admin.php` foi atualizado
- [ ] `includes/whatsapp_helper.php` foi atualizado
- [ ] `config.json` foi atualizado com URL correta
- [ ] Botão WhatsApp aparece na tabela
- [ ] Script de teste funciona
- [ ] Logs estão sendo gerados

### **Verificar Logs:**
- Acesse: `https://seu-dominio.com/logs/system.log`
- Ou via File Manager: `public_html/logs/system.log`

## 🐛 Troubleshooting

### **Problema: Botão não aparece**
- Limpe o cache do navegador (Ctrl+F5)
- Verifique se `admin.php` foi realmente atualizado
- Verifique o console do navegador (F12) para erros

### **Problema: Erro ao enviar**
- Verifique se o bot está online: `http://localhost:3000/status`
- Verifique a URL no `config.json`
- Use o script `test_whatsapp_manual.php` para diagnosticar

### **Problema: Arquivos não sobem**
- Verifique permissões da pasta `public_html`
- Verifique espaço em disco
- Tente fazer upload de um arquivo por vez

## 📝 Notas Importantes

1. **Backup antes de fazer upload:**
   - Faça backup dos arquivos antigos antes de substituir
   - Pode usar o File Manager para renomear: `admin.php.bak`

2. **Bot WhatsApp:**
   - O bot precisa estar rodando no seu PC local
   - Use ngrok ou cloudflared para expor para a internet
   - Atualize a URL no `config.json` da Hostinger

3. **Segurança:**
   - Após testar, considere remover `test_whatsapp_manual.php`
   - Não exponha credenciais no código

## 🎯 Próximos Passos

1. Fazer upload dos arquivos
2. Atualizar `config.json` com URL correta
3. Testar o botão no admin
4. Verificar logs se houver problemas


