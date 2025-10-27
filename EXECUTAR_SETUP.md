# 🚀 Instruções para Executar o Setup

## ⚠️ **IMPORTANTE: Execute estes passos na ordem correta!**

### **Passo 1: Executar Setup do Banco**
1. Acesse: `https://seudominio.com/setup_database.php`
2. Aguarde a criação das tabelas
3. **NÃO feche a página** até ver a mensagem de sucesso

### **Passo 2: Verificar se Funcionou**
1. Acesse: `https://seudominio.com/test_system.php`
2. Deve mostrar "Sistema Funcionando Perfeitamente!"
3. Se mostrar erros, execute o setup novamente

### **Passo 3: Testar o Sistema**
1. **Página Principal**: `https://seudominio.com/index.php`
   - Deve mostrar o botão "Indicar Amigo"
   - Clique em "Como Funciona" para ver o modal

2. **Sistema de Indicação**: `https://seudominio.com/indicacao.php`
   - Teste indicar um amigo
   - Preencha o formulário

3. **Painel Admin**: `https://seudominio.com/admin.php`
   - Login: `admin` / Senha: `12345`
   - Clique no botão azul "Indicações"

### **Passo 4: Segurança (APÓS o setup)**
1. **DELETE** o arquivo `setup_database.php` após a execução
2. **DELETE** o arquivo `test_system.php` após os testes
3. **DELETE** este arquivo `EXECUTAR_SETUP.md`

## 🔧 **Se Ainda Der Erro 403:**

### **Opção 1: Renomear o arquivo**
```bash
# Renomeie setup_database.php para setup.php
# Acesse: https://seudominio.com/setup.php
```

### **Opção 2: Executar via cPanel**
1. Acesse o cPanel da Hostinger
2. Vá em "File Manager"
3. Navegue até o arquivo `setup_database.php`
4. Clique com botão direito → "Edit"
5. Copie todo o conteúdo
6. Vá em "SQL" no cPanel
7. Cole e execute o SQL manualmente

### **Opção 3: Executar SQL Manualmente**
Execute este SQL no phpMyAdmin:

```sql
-- Criar tabela de clientes
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    telefone VARCHAR(20),
    cidade VARCHAR(100),
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_indicacoes INT DEFAULT 0,
    total_compras INT DEFAULT 0,
    INDEX idx_codigo (codigo),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar tabela de indicações
CREATE TABLE indicacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_indicador VARCHAR(50) NOT NULL,
    codigo_indicado VARCHAR(50) NOT NULL,
    data_indicacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pendente', 'confirmada', 'entregue') DEFAULT 'pendente',
    prioridade BOOLEAN DEFAULT TRUE,
    data_entrega_prevista DATE,
    INDEX idx_codigo_indicador (codigo_indicador),
    INDEX idx_codigo_indicado (codigo_indicado),
    INDEX idx_data_indicacao (data_indicacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar tabela de compras
CREATE TABLE compras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_cliente VARCHAR(50) NOT NULL,
    codigo_indicador VARCHAR(50),
    valor DECIMAL(10,2) NOT NULL,
    data_compra TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pendente', 'confirmada', 'entregue') DEFAULT 'pendente',
    prioridade BOOLEAN DEFAULT FALSE,
    data_entrega_prevista DATE,
    INDEX idx_codigo_cliente (codigo_cliente),
    INDEX idx_data_compra (data_compra)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar colunas à tabela rastreios_status (se existir)
ALTER TABLE rastreios_status 
ADD COLUMN IF NOT EXISTS prioridade BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS codigo_indicador VARCHAR(50),
ADD COLUMN IF NOT EXISTS data_entrega_prevista DATE;

-- Adicionar índices
ALTER TABLE rastreios_status 
ADD INDEX IF NOT EXISTS idx_prioridade (prioridade),
ADD INDEX IF NOT EXISTS idx_codigo_indicador (codigo_indicador);
```

## ✅ **Após Executar o Setup:**

1. **Teste a página inicial** - deve mostrar o botão de indicação
2. **Teste o sistema de indicação** - deve funcionar normalmente
3. **Teste o admin** - deve mostrar as indicações
4. **Delete os arquivos de setup** por segurança

## 🆘 **Se Ainda Não Funcionar:**

1. Verifique se as credenciais do banco estão corretas em `includes/config.php`
2. Confirme se o usuário do banco tem permissões para criar tabelas
3. Teste a conexão com o banco primeiro
4. Verifique se não há erros de PHP nos logs

---

**Desenvolvido para Helmer Logistics**  
*Sistema de Indicação com Prioridade de Entrega*
