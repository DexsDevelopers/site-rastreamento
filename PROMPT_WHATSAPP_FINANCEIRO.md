# 🤖 PROMPT: Sistema de Comandos WhatsApp para Painel Financeiro

---

## 📋 PROMPT COMPLETO PARA A IA:

```
Preciso implementar um sistema completo de comandos administrativos via WhatsApp Bot para gerenciar meu painel financeiro PHP.

CONTEXTO DO PROJETO:
- Sistema: Painel Financeiro (gestão de receitas, despesas, clientes e pagamentos)
- Backend: PHP com MySQL/PDO
- Frontend: HTML, CSS, JavaScript
- Bot WhatsApp: Node.js com Baileys (WhatsApp Web API)
- Servidor: Hostinger (produção)

OBJETIVO:
Permitir que administradores gerenciem completamente o painel financeiro através de comandos via WhatsApp, incluindo:
- Adicionar receitas e despesas
- Consultar saldos e extratos
- Gerenciar clientes e cobranças
- Gerar relatórios financeiros
- Anexar comprovantes (fotos)
- Notificar clientes sobre pagamentos

---

FUNCIONALIDADES NECESSÁRIAS:

📊 COMANDOS FINANCEIROS:

1. GESTÃO DE TRANSAÇÕES:
   - /receita VALOR DESCRICAO CLIENTE - Registrar receita
   - /despesa VALOR DESCRICAO CATEGORIA - Registrar despesa
   - /saldo [MES] - Ver saldo atual ou de um mês específico
   - /extrato [INICIO] [FIM] - Ver extrato de período
   - /deletar TRANSACAO_ID - Remover transação

2. GESTÃO DE CLIENTES:
   - /cliente NOME TELEFONE EMAIL - Cadastrar cliente
   - /clientes - Listar todos os clientes
   - /clienteinfo ID - Ver detalhes do cliente
   - /pendencias [CLIENTE_ID] - Ver pagamentos pendentes

3. COMPROVANTES:
   - /comprovante TRANSACAO_ID - Anexar comprovante (aguarda foto)
   - /vercomprovante TRANSACAO_ID - Link para visualizar

4. RELATÓRIOS E CONSULTAS:
   - /relatorio [MES] - Relatório completo do mês
   - /dashboard - Resumo geral (receitas, despesas, saldo)
   - /topo [LIMITE] - Top clientes/categorias
   - /previsao - Previsão de receitas e despesas futuras

5. COBRANÇAS E NOTIFICAÇÕES:
   - /cobrar CLIENTE_ID VALOR VENCIMENTO DESCRICAO - Criar cobrança
   - /lembrar COBRANCA_ID - Enviar lembrete ao cliente
   - /notificar CLIENTE_ID MENSAGEM - Mensagem personalizada
   - /pagar COBRANCA_ID - Marcar como pago

6. COMANDOS PÚBLICOS (clientes):
   - /minhasdividas - Cliente consulta suas pendências
   - /meusaldo - Cliente consulta seu saldo/histórico
   - /pagarvia PIX|BOLETO - Cliente solicita dados para pagamento

7. SISTEMA:
   - /menu - Listar todos os comandos
   - /ajuda COMANDO - Detalhes de um comando específico
   - /backup - Gerar backup do banco de dados

---

ARQUITETURA TÉCNICA:

1. API PHP (admin_bot_api.php):
   - Receber comandos do bot Node.js via POST
   - Validar token Bearer de segurança
   - Verificar permissões por número de WhatsApp
   - Executar operações no banco de dados (transações, clientes, cobranças)
   - Retornar respostas formatadas em JSON
   - Gerar logs de todas as ações

2. Endpoint de Fotos (admin_bot_photo.php):
   - Receber uploads de comprovantes via FormData
   - Validar MIME types (JPEG, PNG, PDF)
   - Salvar em uploads/comprovantes/
   - Vincular ao ID da transação no banco
   - Proteger diretório com .htaccess

3. Bot Node.js (whatsapp-bot/index.js):
   - Detectar mensagens que começam com /
   - Extrair número do remetente do JID do WhatsApp
   - Processar comandos localmente (validação básica)
   - Enviar para API PHP via axios
   - Suporte a upload de fotos após comando /comprovante
   - Sistema de "aguardar foto" com timeout de 5 minutos
   - Logs detalhados coloridos no console

4. Funções Helper PHP (includes/finance_helper.php):
   - Criar: registerTransaction($pdo, $type, $value, $description, $category, $clientId)
   - Consultar: getBalance($pdo, $month, $year)
   - Extrato: getExtract($pdo, $startDate, $endDate)
   - Clientes: getClientPendencies($pdo, $clientId)
   - Relatório: generateMonthReport($pdo, $month, $year)

---

ESTRUTURA DO BANCO DE DADOS:

Tabelas necessárias:

```sql
-- Transações financeiras
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('receita', 'despesa') NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    client_id INT,
    receipt_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(20),
    INDEX idx_date (created_at),
    INDEX idx_client (client_id)
);

-- Clientes
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    whatsapp_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cobranças
CREATE TABLE charges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    description VARCHAR(255),
    status ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pendente',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);
```

---

CONFIGURAÇÃO (config.json):

```json
{
  "titulo_sistema": "Painel Financeiro Pro",
  "moeda": "BRL",
  "formato_data": "d/m/Y",
  "WHATSAPP_API_URL": "http://localhost:3000",
  "WHATSAPP_API_TOKEN": "seu-token-seguro-aqui",
  "WHATSAPP_API_ENABLED": true,
  "ADMIN_WHATSAPP_NUMBERS": [
    "5551996148568",
    "551996148568",
    "5537991101425",
    "553791101425"
  ],
  "LIMITE_UPLOAD_MB": 10,
  "COMPROVANTES_DIR": "uploads/comprovantes/"
}
```

---

CONFIGURAÇÃO BOT (.env):

```env
API_PORT=3000
API_TOKEN=seu-token-seguro-aqui
ADMIN_API_URL=https://seu-dominio.com
ADMIN_NUMBERS=5551996148568,551996148568,5537991101425,553791101425
AUTO_REPLY=true
AUTO_REPLY_MESSAGE=Olá! Sou o assistente financeiro. Digite /menu para ver os comandos.
```

---

ESTRUTURA DE RESPOSTA DOS COMANDOS:

```json
{
  "success": true,
  "message": "💰 *Receita Registrada*\n\nValor: R$ 1.500,00\nCliente: João Silva\nData: 27/11/2025\nID: #1234\n\n✅ Saldo atualizado!",
  "data": {
    "transaction_id": 1234,
    "new_balance": 15000.00
  }
}
```

---

EXEMPLOS DE MENSAGENS FORMATADAS:

1. Saldo:
```
💰 *SALDO - NOVEMBRO/2025*

📈 Receitas: R$ 25.000,00
📉 Despesas: R$ 12.500,00
━━━━━━━━━━━━━━━━━━━━━
💵 Saldo: R$ 12.500,00

📊 _Use /relatorio para detalhes_
```

2. Relatório:
```
📊 *RELATÓRIO - NOVEMBRO/2025*

*RECEITAS*
💰 Total: R$ 25.000,00
📦 Transações: 45
👥 Clientes: 12

*DESPESAS*
💸 Total: R$ 12.500,00
📦 Transações: 23
🏷️ Categorias: 8

*TOP 5 CLIENTES*
1. João Silva - R$ 5.200,00
2. Maria Santos - R$ 3.800,00
3. Pedro Costa - R$ 2.900,00
4. Ana Lima - R$ 2.100,00
5. Carlos Dias - R$ 1.800,00

━━━━━━━━━━━━━━━━━━━━━
💵 *SALDO FINAL: R$ 12.500,00*

📈 Comparado ao mês anterior: +15%
```

3. Pendências:
```
⚠️ *PENDÊNCIAS - João Silva*

ID: #567
💰 Valor: R$ 1.200,00
📅 Vencimento: 30/11/2025
📝 Serviço: Consultoria

ID: #589
💰 Valor: R$ 850,00
📅 Vencimento: 05/12/2025
📝 Serviço: Suporte técnico

━━━━━━━━━━━━━━━━━━━━━
💵 Total pendente: R$ 2.050,00

💡 _Use /lembrar 567 para notificar_
```

---

FLUXO DE TRABALHO TÍPICO:

1. **Registrar Receita:**
   ```
   Admin: /receita 1500 Consultoria João Silva
   Bot: ✅ Receita registrada! ID #1234
   ```

2. **Anexar Comprovante:**
   ```
   Admin: /comprovante 1234
   Bot: 📸 Envie o comprovante agora
   [Admin envia foto]
   Bot: ✅ Comprovante anexado ao ID #1234
   ```

3. **Criar Cobrança:**
   ```
   Admin: /cobrar 5 2500 30/12/2025 Desenvolvimento de site
   Bot: ✅ Cobrança criada! ID #890
   ```

4. **Lembrar Cliente:**
   ```
   Admin: /lembrar 890
   Bot: 📨 Lembrete enviado para João Silva
   ```

5. **Cliente Consulta:**
   ```
   Cliente: /minhasdividas
   Bot: Você tem 1 pendência: R$ 2.500,00 vence em 30/12
   ```

---

SEGURANÇA:

1. Validação de Token:
   - Todas as requisições verificam Bearer token
   - Token configurado em config.json e .env

2. Permissões por Número:
   - Array ADMIN_WHATSAPP_NUMBERS em config.json
   - Suporte a múltiplos formatos (55119XXX e 5511XXX)
   - Comandos públicos vs comandos admin

3. Upload de Arquivos:
   - Whitelist de MIME types
   - Limite de tamanho (10MB padrão)
   - .htaccess bloqueando execução de PHP em uploads/

4. Logs Completos:
   - Todas as ações em logs/finance_whatsapp.log
   - Format: [DATA] [NIVEL] [NUMERO] Ação realizada

---

ARQUIVOS A CRIAR:

1. **admin_bot_api.php** - API principal de comandos
2. **admin_bot_photo.php** - Endpoint de upload de comprovantes
3. **includes/finance_helper.php** - Funções financeiras
4. **debug_finance_whatsapp.php** - Página de diagnóstico
5. **whatsapp-bot/index.js** - Bot WhatsApp atualizado
6. **whatsapp-bot/package.json** - Dependências Node.js
7. **config.json** - Configurações do sistema
8. **whatsapp-bot/.env** - Variáveis de ambiente do bot
9. **setup_finance_tables.php** - Script de criação de tabelas
10. **COMANDOS_FINANCEIRO.md** - Documentação completa

---

REQUISITOS TÉCNICOS:

- PHP 7.4+ com PDO MySQL
- MySQL 5.7+ ou MariaDB 10.3+
- Node.js 18+
- npm ou yarn
- Extensões PHP: json, pdo_mysql, fileinfo, mbstring
- Pacotes npm: @whiskeysockets/baileys, axios, form-data, express, dotenv

---

IMPLEMENTAÇÃO:

Por favor, crie:

1. ✅ Toda a estrutura de arquivos listada
2. ✅ Sistema completo de permissões e segurança
3. ✅ Funções financeiras (transações, saldos, relatórios)
4. ✅ Todos os comandos listados funcionais
5. ✅ Sistema de upload de comprovantes
6. ✅ Notificações para clientes via WhatsApp
7. ✅ Página de debug e diagnóstico
8. ✅ Logs detalhados de todas as operações
9. ✅ Documentação completa de uso
10. ✅ Script de instalação e setup de banco

Mantenha o código:
- Limpo e bem comentado
- Modular e reutilizável
- Seguro (validações, sanitização)
- Com tratamento de erros completo
- Pronto para produção

Use logs extensivos com writeLog() e console.log() para facilitar debug.
Formate todas as respostas com emojis e markdown do WhatsApp (*negrito*, _itálico_).
```

---

## 🎯 COMO USAR ESTE PROMPT:

1. **Copie todo o conteúdo acima**
2. **Cole no chat da IA**
3. **Aguarde a implementação completa**
4. **Peça refinamentos específicos se necessário**

---

## ✅ CHECKLIST PÓS-IMPLEMENTAÇÃO:

- [ ] Todas as tabelas criadas no banco
- [ ] API PHP funcionando
- [ ] Bot Node.js conectado ao WhatsApp
- [ ] Números admin configurados
- [ ] Comando /menu funcionando
- [ ] Teste de registro de receita
- [ ] Teste de registro de despesa
- [ ] Teste de consulta de saldo
- [ ] Teste de upload de comprovante
- [ ] Teste de criação de cobrança
- [ ] Teste de notificação a cliente
- [ ] Comandos públicos funcionando
- [ ] Logs sendo gerados corretamente
- [ ] Deploy em produção realizado
- [ ] Documentação revisada

---

## 💡 COMANDOS PARA PEDIR À IA DEPOIS:

Após a implementação inicial, peça:

1. "Crie exemplos de uso para cada comando"
2. "Adicione validações de segurança extras"
3. "Implemente sistema de backup automático"
4. "Crie relatórios gráficos em PDF"
5. "Adicione suporte a múltiplas moedas"
6. "Implemente sistema de metas financeiras"
7. "Crie dashboard web complementar"

---

**📌 ESTE PROMPT ESTÁ 100% PRONTO PARA USO!**

Basta copiar e colar no chat com a IA. Ela terá todas as informações necessárias para implementar o sistema completo de comandos WhatsApp para seu painel financeiro.



