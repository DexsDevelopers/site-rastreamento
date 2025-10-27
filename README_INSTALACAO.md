# 🚀 Sistema de Indicação Helmer Logistics

## 📋 Instruções de Instalação

### 1. **Configuração do Banco de Dados**

1. Acesse o **cPanel** da Hostinger
2. Vá em **Bancos de Dados MySQL**
3. Crie um novo banco de dados (se ainda não existir)
4. Anote as credenciais do banco

### 2. **Configuração dos Arquivos**

1. **Edite o arquivo `includes/config.php`** com suas credenciais:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'seu_banco_aqui');
define('DB_USER', 'seu_usuario_aqui');
define('DB_PASS', 'sua_senha_aqui');
```

### 3. **Executar Setup do Banco**

1. Acesse: `https://seudominio.com/setup_database.php`
2. Aguarde a criação das tabelas
3. **IMPORTANTE**: Delete o arquivo `setup_database.php` após a execução

### 4. **Estrutura de Arquivos**

```
public_html/
├── index.php (página principal)
├── admin.php (painel administrativo)
├── indicacao.php (sistema de indicação)
├── admin_indicacoes.php (gerenciar indicações)
├── includes/
│   ├── config.php (configurações)
│   ├── db_connect.php (conexão segura)
│   └── referral_system.php (sistema de indicação)
├── .htaccess (segurança)
└── README_INSTALACAO.md
```

## 🎯 Funcionalidades Implementadas

### ✅ **Sistema de Indicação**
- Clientes podem indicar amigos
- Entrega prioritária em 2 dias
- Sistema de aprovação no admin

### ✅ **Segurança Aprimorada**
- Prepared statements (anti SQL injection)
- Headers de segurança
- Rate limiting
- Proteção de arquivos sensíveis

### ✅ **Responsividade**
- Layout adaptável para mobile
- Interface moderna e intuitiva
- Animações suaves

### ✅ **Automações**
- Atualização automática de status
- Relatórios semanais
- Alertas de taxas pendentes

## 🔧 Como Usar

### **Para Clientes:**
1. Acesse `indicacao.php`
2. Preencha o formulário de indicação
3. Aguarde aprovação
4. Compre com prioridade

### **Para Administradores:**
1. Acesse `admin.php` (login: admin / senha: 12345)
2. Gerencie indicações em `admin_indicacoes.php`
3. Monitore rastreios prioritários
4. Aprove/rejeite indicações

## 📊 Recursos do Sistema

### **Dashboard Admin:**
- Estatísticas em tempo real
- Gerenciamento de indicações
- Rastreios prioritários
- Relatórios automáticos

### **Sistema de Prioridade:**
- Entrega em 2 dias para indicações
- Status especial no rastreamento
- Processamento acelerado
- Notificações automáticas

## 🛡️ Segurança

### **Implementado:**
- ✅ Prepared statements
- ✅ Sanitização de entrada
- ✅ Headers de segurança
- ✅ Proteção de arquivos
- ✅ Rate limiting
- ✅ Logs de auditoria

### **Recomendações:**
- Altere as credenciais padrão
- Configure SSL/HTTPS
- Faça backups regulares
- Monitore os logs

## 📱 Responsividade

### **Testado em:**
- ✅ Desktop (1920x1080)
- ✅ Tablet (768px)
- ✅ Mobile (375px)
- ✅ Orientação landscape/portrait

## 🚀 Performance

### **Otimizações:**
- ✅ Cache de 10 minutos
- ✅ Compressão GZIP
- ✅ Minificação de CSS/JS
- ✅ Lazy loading de imagens
- ✅ Consultas otimizadas

## 📈 Monitoramento

### **Logs Disponíveis:**
- Sistema de indicações
- Erros de aplicação
- Tentativas de login
- Automações executadas

### **Relatórios:**
- Estatísticas de indicações
- Tempo médio de entrega
- Taxa de conversão
- Performance do sistema

## 🔄 Automações

### **Cron Jobs Configurados:**
```bash
# Atualização de status (a cada 30 minutos)
*/30 * * * * curl "https://seudominio.com/automation_cron.php?cron=true"

# Atualização de rastreios (a cada hora)
0 * * * * curl "https://seudominio.com/cron_update.php"
```

## 📞 Suporte

### **Em caso de problemas:**
1. Verifique os logs em `logs/`
2. Confirme as credenciais do banco
3. Teste a conectividade
4. Verifique permissões de arquivo

### **Arquivos de Log:**
- `logs/system.log` - Logs gerais
- `logs/automation.log` - Logs de automação
- `automation_logs.txt` - Logs de cron

## 🎉 Próximos Passos

1. **Execute o setup**: Acesse `setup_database.php`
2. **Teste o sistema**: Use `indicacao.php`
3. **Configure automações**: Configure os cron jobs
4. **Personalize**: Ajuste cores e textos conforme necessário
5. **Monitore**: Acompanhe os logs e estatísticas

---

**Desenvolvido para Helmer Logistics**  
*Sistema de Indicação com Prioridade de Entrega*  
*Versão 2.0 - Otimizada e Segura*

