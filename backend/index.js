const express = require('express');
const cors = require('cors');
const dotenv = require('dotenv');
const path = require('path');

// Garante que fetch está disponível (Node <18 não tem nativo)
if (!globalThis.fetch) {
    globalThis.fetch = require('node-fetch');
}

// Carrega .env da raiz ou da pasta backend
dotenv.config();
dotenv.config({ path: path.join(__dirname, '.env') });

const app = express();
const PORT = process.env.PORT || 3000;

// Configuração do Banco de Dados
const mysql = require('mysql2/promise');

let db;
async function runMigrations() {
    if (!db) return;
    try {
        // Verificar se a tabela clientes existe antes de migrar
        const [tables] = await db.query("SHOW TABLES LIKE 'clientes'");
        if (tables.length === 0) return;

        const [columns] = await db.query('SHOW COLUMNS FROM clientes');
        const columnNames = columns.map(c => c.Field);

        if (!columnNames.includes('senha')) {
            await db.query('ALTER TABLE clientes ADD COLUMN senha varchar(255) DEFAULT NULL');
            console.log('✅ Migration: Coluna "senha" adicionada à tabela clientes');
        }
        if (!columnNames.includes('whatsapp')) {
            await db.query('ALTER TABLE clientes ADD COLUMN whatsapp varchar(50) DEFAULT NULL');
            console.log('✅ Migration: Coluna "whatsapp" adicionada à tabela clientes');
        }

        // UNIQUE Index
        try {
            const [indexes] = await db.query("SHOW INDEX FROM clientes WHERE Key_name = 'idx_email'");
            if (indexes.length === 0) {
                await db.query('ALTER TABLE clientes ADD UNIQUE INDEX idx_email (email)');
                console.log('✅ Migration: Index UNIQUE adicionado ao email');
            }
        } catch (e) { }

        // Tabela de configurações
        await db.query(`
            CREATE TABLE IF NOT EXISTS \`configuracoes\` (
              \`chave\` varchar(100) NOT NULL,
              \`valor\` text DEFAULT NULL,
              PRIMARY KEY (\`chave\`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        `);

        // Valor padrão para centavos_aleatorios se não existir
        const [configRows] = await db.query("SELECT valor FROM configuracoes WHERE chave = 'centavos_aleatorios'");
        if (configRows.length === 0) {
            await db.query("INSERT INTO configuracoes (chave, valor) VALUES ('centavos_aleatorios', 'true')");
        }

        // CORREÇÃO: Limpar espaços extras nos códigos de rastreio (Bug reportado pelo usuário)
        console.log('🧹 Limpando espaços extras nos códigos de rastreio (Agressivo)...');
        // Usa REPLACE para tirar espaços internos, quebras de linha e tabs
        const clearSql = (col) => `REPLACE(REPLACE(REPLACE(REPLACE(${col}, '\n', ''), '\r', ''), '\t', ''), ' ', '')`;

        await db.query(`UPDATE IGNORE rastreios_status SET codigo = ${clearSql('codigo')}`);
        await db.query(`UPDATE IGNORE whatsapp_contatos SET codigo = ${clearSql('codigo')}`);
        await db.query(`UPDATE IGNORE pedidos_pendentes SET codigo_rastreio = ${clearSql('codigo_rastreio')} WHERE codigo_rastreio IS NOT NULL`);
        console.log('✅ Limpeza agressiva concluída!');

        // NOVO: Tabela de Fila de Mensagens WhatsApp (Solução Hostinger Multi-process)
        await db.query(`
            CREATE TABLE IF NOT EXISTS \`whatsapp_fila_mensagens\` (
              \`id\` int(11) NOT NULL AUTO_INCREMENT,
              \`telefone\` varchar(50) NOT NULL,
              \`mensagem\` text NOT NULL,
              \`status\` enum('pendente','enviado','erro') DEFAULT 'pendente',
              \`erro\` text DEFAULT NULL,
              \`data_criacao\` datetime DEFAULT CURRENT_TIMESTAMP,
              \`data_envio\` datetime DEFAULT NULL,
              PRIMARY KEY (\`id\`),
              INDEX (\`status\`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        `);
        console.log('✅ Migration: Fila de mensagens garantida!');

    } catch (err) {
        console.error('⚠️ Erro nas migrações:', err.message);
    }
}

async function connectDB() {
    try {
        const host = (process.env.DB_HOST === 'localhost' || !process.env.DB_HOST) ? '127.0.0.1' : process.env.DB_HOST;
        console.log(`📡 Tentando conectar ao banco em: ${host}`);

        db = await mysql.createPool({
            host: host,
            user: process.env.DB_USER,
            password: process.env.DB_PASSWORD,
            database: process.env.DB_NAME,
            waitForConnections: true,
            connectionLimit: 10,
            queueLimit: 0,
            enableKeepAlive: true,
            keepAliveInitialDelay: 0
        });
        console.log('✅ Banco de dados conectado com sucesso!');
        await runMigrations(); // Garante colunas novas
    } catch (err) {
        console.error('❌ Erro ao conectar no banco de dados:', err.message);
    }
}

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ===== AUTH API =====

app.post('/api/auth/register', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { nome, email, senha, whatsapp } = req.body;

        if (!nome || !email || !senha) {
            return res.status(400).json({ success: false, message: 'Campos obrigatórios faltando' });
        }

        // Verificar se já existe
        const [rows] = await db.query('SELECT id FROM clientes WHERE email = ?', [email]);
        if (rows.length > 0) {
            return res.status(400).json({ success: false, message: 'Este e-mail já está cadastrado' });
        }

        await db.query(
            'INSERT INTO clientes (nome, email, senha, whatsapp) VALUES (?, ?, ?, ?)',
            [nome, email, senha, whatsapp || null]
        );

        res.json({ success: true, message: 'Cadastro realizado com sucesso!' });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message });
    }
});

app.post('/api/auth/login', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { email, senha } = req.body;

        const [rows] = await db.query('SELECT * FROM clientes WHERE email = ? AND senha = ?', [email, senha]);

        if (rows.length === 0) {
            return res.status(401).json({ success: false, message: 'E-mail ou senha incorretos' });
        }

        const user = rows[0];
        delete user.senha; // Não enviar a senha de volta

        res.json({
            success: true,
            user: {
                id: user.id,
                nome: user.nome,
                email: user.email,
                telefone: user.whatsapp
            }
        });
    } catch (error) {
        res.status(500).json({ success: false, message: error.message });
    }
});

// ===== API ENDPOINTS =====

// 1. Pedidos (Rastreios)
app.get('/api/orders', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [rows] = await db.query('SELECT * FROM rastreios_status ORDER BY data DESC LIMIT 100');
        res.json(rows);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Detalhes de um pedido específico
app.get('/api/orders/:codigo', async (req, res) => {
    const codigo = req.params.codigo?.toUpperCase().trim();
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [rows] = await db.query('SELECT * FROM rastreios_status WHERE codigo = ?', [codigo]);
        if (rows.length === 0) return res.status(404).json({ error: 'Pedido não encontrado' });
        res.json(rows[0]);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 2. Clientes
app.get('/api/clients', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [rows] = await db.query('SELECT * FROM clientes ORDER BY data_cadastro DESC');
        res.json(rows);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 3. Entregadores
app.get('/api/drivers', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [rows] = await db.query('SELECT * FROM entregadores ORDER BY nome ASC');
        res.json(rows);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/drivers', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { nome, telefone, veiculo, status } = req.body;
        await db.query(
            'INSERT INTO entregadores (nome, telefone, veiculo, status) VALUES (?, ?, ?, ?)',
            [nome, telefone, veiculo, status || 'disponivel']
        );
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.put('/api/drivers/:id', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { id } = req.params;
        const { nome, telefone, veiculo, status } = req.body;
        await db.query(
            'UPDATE entregadores SET nome = ?, telefone = ?, veiculo = ?, status = ? WHERE id = ?',
            [nome, telefone, veiculo, status, id]
        );
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.delete('/api/drivers/:id', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { id } = req.params;
        await db.query('DELETE FROM entregadores WHERE id = ?', [id]);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Rota de Teste de Banco
app.get('/api/db-check', async (req, res) => {
    if (!db) return res.status(500).json({ status: 'error', message: 'Módulo DB não carregado' });
    try {
        const [rows] = await db.query('SELECT 1 + 1 AS solution');
        res.json({ status: 'success', database: 'conectado', result: rows[0].solution });
    } catch (error) {
        res.status(500).json({ status: 'error', message: error.message });
    }
});

// Integração PixGo API
const PIXGO_API_KEY = 'pk_f07a39496a7a22c7d7e1694cdf28baee4a7aeae7a4133cc0fec23a1b627d8d56';
const PIXGO_API_URL = 'https://pixgo.org/api/v1';

app.post('/api/pix/create', async (req, res) => {
    try {
        const { amount, description } = req.body;
        const response = await fetch(`${PIXGO_API_URL}/payment/create`, {
            method: 'POST',
            headers: {
                'x-api-key': PIXGO_API_KEY,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ amount: parseFloat(amount) || 29.90, description: description || 'Acelerar Entrega' })
        });
        const data = await response.json();

        if (data.success) {
            res.json(data);
        } else {
            console.error('[PIXGO ERROR]', data);
            res.status(400).json({ success: false, error: data.message || 'Erro na API PixGo' });
        }
    } catch (error) {
        console.error('[PIXGO CREATE ERROR]', error.message);
        res.status(500).json({ success: false, error: 'Erro no servidor' });
    }
});

app.get('/api/pix/status/:id', async (req, res) => {
    try {
        const { id } = req.params;
        const codigo = req.query.codigo?.toUpperCase().trim(); // Código de rastreio opcional e limpo!

        const response = await fetch(`${PIXGO_API_URL}/payment/${id}/status`, {
            method: 'GET',
            headers: { 'x-api-key': PIXGO_API_KEY }
        });
        const data = await response.json();

        // Se o pagamento foi confirmado e temos o código de rastreio
        if (data.success && (data.status === 'PAID' || data.status === 'CONFIRMED' || data.data?.status === 'PAID') && codigo && db) {
            const cleanCodigo = codigo.toUpperCase().trim();

            // 1. Verificar se já existe a etapa de confirmação para não duplicar
            const [existing] = await db.query(
                "SELECT id FROM rastreios_status WHERE codigo = ? AND titulo LIKE '%Pagamento Confirmado%'",
                [cleanCodigo]
            );

            if (existing.length === 0) {
                // 2. Buscar cidade do rastreio atual
                const [rows] = await db.query("SELECT cidade FROM rastreios_status WHERE codigo = ? LIMIT 1", [cleanCodigo]);
                const cidade = rows.length > 0 ? rows[0].cidade : 'Centro de Distribuição';

                // 3. Atualizar todas as etapas removendo o valor da taxa (botão some)
                await db.query(
                    "UPDATE rastreios_status SET taxa_valor = NULL, taxa_pix = NULL WHERE codigo = ?",
                    [cleanCodigo]
                );

                // 4. Inserir nova etapa de confirmação
                await db.query(
                    "INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, data, cor) VALUES (?, ?, ?, ?, ?, NOW(), ?)",
                    [
                        cleanCodigo,
                        cidade,
                        '✅ Pagamento Confirmado',
                        '✅ Pagamento Confirmado',
                        'A taxa foi processada com sucesso. Seu pacote seguirá para a próxima etapa de entrega.',
                        '#16A34A'
                    ]
                );

                console.log(`[AUTO-UPDATE] Pagamento confirmado para o código: ${cleanCodigo}`);
            }
        }

        res.json(data);
    } catch (error) {
        console.error('[PIXGO STATUS ERROR]', error.message);
        res.status(500).json({ success: false, error: 'Erro no servidor' });
    }
});

// Busca Pública de Rastreio (Usado na Home e Tracking)
app.post(['/api/rastreio', '/api/rastreio-publico'], async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        let { codigo, cidade } = req.body;

        if (codigo) codigo = codigo.toUpperCase().trim();

        if (!codigo) return res.status(400).json({ success: false, message: 'Código é obrigatório' });

        // Buscar todos os status do código
        const [rows] = await db.query(
            'SELECT * FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data ASC',
            [codigo.toUpperCase().trim()]
        );

        if (rows.length === 0) {
            return res.status(404).json({ success: false, message: 'Código de rastreio não encontrado.' });
        }

        // Se a cidade foi enviada (pela Home), podemos validar, mas opcional
        // No momento vamos apenas retornar os dados encontrados
        const lastStatus = rows[rows.length - 1];

        // CORREÇÃO: Pegar taxa de qualquer linha que tenha, pois novas linhas podem ser inseridas sem o valor
        const taxaRow = rows.find(r => r.taxa_valor && r.taxa_valor !== '0' && r.taxa_valor !== '0.00') || lastStatus;

        const etapas = rows.map(r => ({
            titulo: r.titulo || r.status_atual,
            subtitulo: r.subtitulo || '',
            data: r.data,
            status_atual: r.status_atual,
            status_slug: r.status_atual ? r.status_atual.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9]/g, "") : 'transito'
        }));

        res.json({
            success: true,
            codigo: lastStatus.codigo,
            cidade: lastStatus.cidade,
            statusAtual: lastStatus.status_atual,
            taxa_valor: taxaRow.taxa_valor,
            taxa_pix: taxaRow.taxa_pix,
            etapas: etapas
        });

    } catch (error) {
        console.error('Erro na busca pública:', error);
        res.status(500).json({ success: false, message: 'Erro interno ao buscar rastreio.' });
    }
});

// ===== ADMIN API =====

// Estatísticas do painel admin
app.get('/api/admin/stats', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');

        const [[{ total }]] = await db.query(
            'SELECT COUNT(DISTINCT codigo) as total FROM rastreios_status'
        );

        // Entregues: último status de cada código contém 'Entregue'
        const [[{ entregues }]] = await db.query(`
            SELECT COUNT(*) as entregues FROM (
                SELECT status_atual FROM rastreios_status t1
                WHERE data = (SELECT MAX(data) FROM rastreios_status t2 WHERE t2.codigo = t1.codigo)
                GROUP BY codigo
                HAVING status_atual LIKE '%Entregue%'
            ) sub
        `);

        // Com taxa pendente
        const [[{ com_taxa }]] = await db.query(`
            SELECT COUNT(*) as com_taxa FROM (
                SELECT taxa_valor FROM rastreios_status t1
                WHERE data = (SELECT MAX(data) FROM rastreios_status t2 WHERE t2.codigo = t1.codigo)
                GROUP BY codigo
                HAVING taxa_valor IS NOT NULL AND taxa_valor > 0
            ) sub
        `);

        res.json({ total, entregues, com_taxa, sem_taxa: total - com_taxa });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Listar todos os rastreios (último status por código)
app.get('/api/admin/rastreios', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');

        const [codigos] = await db.query(
            "SELECT DISTINCT codigo FROM rastreios_status WHERE codigo IS NOT NULL AND codigo != '' ORDER BY codigo DESC"
        );

        const rastreios = [];
        for (const { codigo } of codigos) {
            const [[row]] = await db.query(
                'SELECT * FROM rastreios_status WHERE codigo = ? ORDER BY data DESC LIMIT 1',
                [codigo]
            );
            if (row) rastreios.push(row);
        }

        res.json(rastreios);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Detalhes de um rastreio (todas etapas + contato)
app.get('/api/admin/rastreios/:codigo/detalhes', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const codigo = req.params.codigo?.toUpperCase().trim();

        const [rows] = await db.query(
            'SELECT * FROM rastreios_status WHERE codigo = ? ORDER BY data ASC',
            [codigo]
        );

        if (!rows.length) return res.status(404).json({ error: 'Rastreio não encontrado' });

        const etapaMap = {
            '📦 Objeto postado': 'postado',
            '🚚 Em trânsito': 'transito',
            '🏢 No centro de distribuição': 'distribuicao',
            '🚀 Saiu para entrega': 'entrega',
            '✅ Entregue': 'entregue',
        };

        const etapas = rows.map(r => {
            const key = Object.keys(etapaMap).find(k => r.titulo && r.titulo.includes(k.replace(/📦|🚚|🏢|🚀|✅/g, '').trim()));
            for (const [titulo, etKey] of Object.entries(etapaMap)) {
                if (r.titulo === titulo || (r.status_atual && r.status_atual === titulo)) return etKey;
            }
            return r.titulo;
        });

        // Tentar buscar contato do whatsapp
        let contato = null;
        try {
            const [[c]] = await db.query(
                'SELECT * FROM whatsapp_contatos WHERE codigo = ? LIMIT 1',
                [codigo]
            );
            contato = c || null;
        } catch (e) { /* tabela pode não existir */ }

        const ultimo = rows[rows.length - 1];

        // CORREÇÃO: Pegar taxa de qualquer linha que tenha
        const taxaRow = rows.find(r => r.taxa_valor && r.taxa_valor !== '0' && r.taxa_valor !== '0.00') || ultimo;
        const foto_url = ultimo.foto_url || null;

        res.json({
            codigo: codigo,
            cidade: rows[0].cidade,
            data_inicial: rows[0].data ? rows[0].data.toISOString().slice(0, 16) : new Date().toISOString().slice(0, 16),
            taxa_valor: taxaRow.taxa_valor || null,
            taxa_pix: taxaRow.taxa_pix || null,
            etapas,
            cliente_nome: contato?.nome || null,
            cliente_whatsapp: contato?.telefone_original || null,
            cliente_notificar: contato ? (contato.notificacoes_ativas === 1) : false,
            foto_url,
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Criar novo rastreio
app.post('/api/admin/rastreios', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        let { codigo, cidade, data_inicial, taxa_valor, taxa_pix, cliente_nome, cliente_whatsapp, cliente_notificar, etapas } = req.body;

        if (codigo) codigo = codigo.toUpperCase().trim();

        if (!codigo || !cidade) return res.status(400).json({ error: 'Código e cidade são obrigatórios' });

        // Verificar se já existe
        const [[existe]] = await db.query(
            'SELECT 1 as e FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? LIMIT 1',
            [codigo.toUpperCase().trim()]
        );
        if (existe) return res.status(409).json({ error: `O código ${codigo} já existe no sistema` });

        const etapasMap = {
            postado: ['📦 Objeto postado', 'Objeto recebido no ponto de coleta', '#16A34A'],
            transito: ['🚚 Em trânsito', 'A caminho do centro de distribuição', '#F59E0B'],
            distribuicao: ['🏢 No centro de distribuição', 'Processando encaminhamento', '#FBBF24'],
            entrega: ['🚀 Saiu para entrega', 'Saiu para entrega ao destinatário', '#0055FF'],
            entregue: ['✅ Entregue', 'Objeto entregue com sucesso', '#16A34A'],
        };

        let dia = 0;
        const inicio = data_inicial ? new Date(data_inicial) : new Date();

        for (const [key, dados] of Object.entries(etapasMap)) {
            if (etapas && etapas[key]) {
                const dataEtapa = new Date(inicio);
                dataEtapa.setDate(dataEtapa.getDate() + dia);
                await db.query(
                    'INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, data, cor, taxa_valor, taxa_pix) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [codigo, cidade, dados[0], dados[0], dados[1], dataEtapa, dados[2], taxa_valor || null, taxa_pix || null]
                );
                dia++;
            }
        }

        // Salvar contato
        if (cliente_whatsapp) {
            try {
                await db.query(
                    `INSERT INTO whatsapp_contatos (codigo, nome, telefone_original, notificacoes_ativas)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE nome = VALUES(nome), telefone_original = VALUES(telefone_original), notificacoes_ativas = VALUES(notificacoes_ativas)`,
                    [codigo, cliente_nome || null, cliente_whatsapp, cliente_notificar ? 1 : 0]
                );
            } catch (e) { /* ignorar se tabela não existe */ }
        }

        res.json({ success: true, message: `Rastreio ${codigo} criado com sucesso!` });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Atualizar rastreio
app.put('/api/admin/rastreios/:codigo', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const codigo = req.params.codigo?.toUpperCase().trim();
        const { cidade, data_inicial, taxa_valor, taxa_pix, etapas, cliente_nome, cliente_whatsapp, cliente_notificar } = req.body;

        // Deletar registros existentes
        await db.query('DELETE FROM rastreios_status WHERE codigo = ?', [codigo]);

        const etapasMap = {
            postado: ['📦 Objeto postado', 'Objeto recebido no ponto de coleta', '#16A34A'],
            transito: ['🚚 Em trânsito', 'A caminho do centro de distribuição', '#F59E0B'],
            distribuicao: ['🏢 No centro de distribuição', 'Processando encaminhamento', '#FBBF24'],
            entrega: ['🚀 Saiu para entrega', 'Saiu para entrega ao destinatário', '#0055FF'],
            entregue: ['✅ Entregue', 'Objeto entregue com sucesso', '#16A34A'],
        };

        let dia = 0;
        const inicio = data_inicial ? new Date(data_inicial) : new Date();

        for (const [key, dados] of Object.entries(etapasMap)) {
            if (etapas && etapas.includes(key)) {
                const dataEtapa = new Date(inicio);
                dataEtapa.setDate(dataEtapa.getDate() + dia);
                await db.query(
                    'INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, data, cor, taxa_valor, taxa_pix) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [codigo, cidade, dados[0], dados[0], dados[1], dataEtapa, dados[2], taxa_valor || null, taxa_pix || null]
                );
                dia++;
            }
        }

        // Atualizar contato
        if (cliente_whatsapp !== undefined) {
            try {
                await db.query(
                    `INSERT INTO whatsapp_contatos (codigo, nome, telefone_original, notificacoes_ativas)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE nome = VALUES(nome), telefone_original = VALUES(telefone_original), notificacoes_ativas = VALUES(notificacoes_ativas)`,
                    [codigo, cliente_nome || null, cliente_whatsapp || null, cliente_notificar ? 1 : 0]
                );
            } catch (e) { /* ignorar */ }
        }

        res.json({ success: true, message: `Rastreio ${codigo} atualizado!` });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Deletar rastreio
app.delete('/api/admin/rastreios/:codigo', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { codigo } = req.params;
        await db.query('DELETE FROM rastreios_status WHERE codigo = ?', [codigo]);
        try { await db.query('DELETE FROM whatsapp_contatos WHERE codigo = ?', [codigo]); } catch (e) { }
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Exclusão em lote
app.post('/api/admin/rastreios/bulk-delete', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { codigos } = req.body;
        if (!Array.isArray(codigos) || !codigos.length) return res.status(400).json({ error: 'Nenhum código informado' });
        const placeholders = codigos.map(() => '?').join(',');
        await db.query(`DELETE FROM rastreios_status WHERE codigo IN (${placeholders})`, codigos);
        try { await db.query(`DELETE FROM whatsapp_contatos WHERE codigo IN (${placeholders})`, codigos); } catch (e) { }
        res.json({ success: true, count: codigos.length });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Edição em lote
app.post('/api/admin/rastreios/bulk-edit', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { codigos, cidade, taxa_valor, taxa_pix } = req.body;
        if (!Array.isArray(codigos) || !codigos.length) return res.status(400).json({ error: 'Nenhum código informado' });

        const placeholders = codigos.map(() => '?').join(',');
        if (cidade) {
            await db.query(`UPDATE rastreios_status SET cidade = ? WHERE codigo IN (${placeholders})`, [cidade, ...codigos]);
        }
        if (taxa_valor && taxa_pix) {
            await db.query(`UPDATE rastreios_status SET taxa_valor = ?, taxa_pix = ? WHERE codigo IN (${placeholders})`, [taxa_valor, taxa_pix, ...codigos]);
        }

        res.json({ success: true, count: codigos.length });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Enviar WhatsApp manual
app.post('/api/admin/rastreios/:codigo/whatsapp', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const codigo = req.params.codigo?.toUpperCase().trim();

        // Buscar configuração da API do WhatsApp
        let apiToken = process.env.WHATSAPP_API_TOKEN || 'lucastav8012';
        let apiUrl = 'http://127.0.0.1:3001';

        if (!apiUrl) {
            return res.json({ success: false, message: '❌ URL da API WhatsApp não configurada no .env' });
        }

        // Buscar contato
        let contato = null;
        try {
            const [[c]] = await db.query('SELECT * FROM whatsapp_contatos WHERE codigo = ? LIMIT 1', [codigo]);
            contato = c;
        } catch (e) {
            return res.json({ success: false, message: '❌ Tabela de contatos não encontrada' });
        }

        if (!contato) return res.json({ success: false, message: '❌ Contato WhatsApp não encontrado para este código' });
        if (!contato.notificacoes_ativas) return res.json({ success: false, message: '❌ Notificações desativadas para este código' });
        if (!contato.telefone_normalizado && !contato.telefone_original) return res.json({ success: false, message: '❌ Telefone não cadastrado' });

        const [[ultimoStatus]] = await db.query(
            'SELECT * FROM rastreios_status WHERE codigo = ? ORDER BY data DESC LIMIT 1',
            [codigo]
        );

        if (!ultimoStatus) return res.json({ success: false, message: '❌ Nenhum status encontrado para este código' });

        const telefone = contato.telefone_normalizado || contato.telefone_original;

        // Buscar template
        const [[template]] = await db.query("SELECT mensagem FROM whatsapp_templates WHERE slug = 'rastreio_update' LIMIT 1");
        let mensagem = template?.mensagem || `📦 *Atualização de Rastreio*\n\nCódigo: {codigo}\nStatus: {status}\n{subtitulo}\n\nAcompanhe seu pedido em nosso site!`;

        mensagem = mensagem
            .replace('{codigo}', codigo)
            .replace('{status}', ultimoStatus.status_atual)
            .replace('{subtitulo}', ultimoStatus.subtitulo || '');

        // Enfileirar via Banco de Dados (Solução Hostinger Multi-process)
        try {
            await queueWhatsAppMessage(telefone, mensagem);
            res.json({ success: true, message: `✅ Mensagem enviada para fila (${telefone})!` });
        } catch (err) {
            console.error('Erro ao enfileirar mensagem:', err.message);
            res.json({ success: false, message: `❌ Erro ao processar envio: ${err.message}` });
        }
    } catch (error) {
        res.status(500).json({ success: false, message: '❌ Erro interno: ' + error.message });
    }
});

// 7.5. Pedidos Pendentes
app.get('/api/admin/pedidos-pendentes', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [rows] = await db.query("SELECT * FROM pedidos_pendentes WHERE status = 'pendente' ORDER BY data_pedido DESC");
        res.json(rows);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/admin/pedidos-pendentes/:id/aprovar', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { id } = req.params;
        const { codigo_rastreio } = req.body;

        if (!codigo_rastreio) return res.status(400).json({ error: 'Código de rastreio é obrigatório' });

        // Buscar dados do pedido
        const [[pedido]] = await db.query("SELECT * FROM pedidos_pendentes WHERE id = ?", [id]);
        if (!pedido) return res.status(404).json({ error: 'Pedido não encontrado' });

        // Criar rastreio inicial
        const cidade = `${pedido.cidade}/${pedido.estado}`;
        await db.query(`
            INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, data, cor)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        `, [codigo_rastreio, cidade, '📦 Objeto postado', '📦 Objeto postado', 'Objeto recebido e postado para envio', '#16A34A']);

        // Salvar contato
        try {
            await db.query(`
                INSERT INTO whatsapp_contatos (codigo, nome, telefone_original, telefone_normalizado, notificacoes_ativas)
                VALUES (?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE nome = ?, telefone_original = ?, telefone_normalizado = ?
            `, [
                codigo_rastreio, pedido.nome, pedido.telefone,
                pedido.telefone.replace(/\D/g, ''),
                pedido.nome, pedido.telefone, pedido.telefone.replace(/\D/g, '')
            ]);
        } catch (e) { console.error('Erro ao salvar contato:', e.message); }

        // Atualizar status do pedido
        await db.query("UPDATE pedidos_pendentes SET status = 'aprovado', codigo_rastreio = ? WHERE id = ?", [codigo_rastreio, id]);

        res.json({ success: true, message: 'Pedido aprovado com sucesso!' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/admin/pedidos-pendentes/:id/rejeitar', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { id } = req.params;
        await db.query("UPDATE pedidos_pendentes SET status = 'rejeitado' WHERE id = ?", [id]);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/admin/pedidos-pendentes/:id/cobrar', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { id } = req.params;

        // Buscar configuração da API do WhatsApp
        let apiToken = process.env.WHATSAPP_API_TOKEN || 'lucastav8012';
        let apiUrl = 'http://127.0.0.1:3001';

        if (!apiUrl) return res.json({ success: false, message: '❌ API WhatsApp não configurada.' });

        const [[pedido]] = await db.query("SELECT * FROM pedidos_pendentes WHERE id = ?", [id]);
        if (!pedido) return res.status(404).json({ error: 'Pedido não encontrado' });

        const telefone = pedido.telefone.replace(/\D/g, '');

        // Buscar template
        const [[template]] = await db.query("SELECT mensagem FROM whatsapp_templates WHERE slug = 'cobranca_pendente' LIMIT 1");
        let mensagem = template?.mensagem || `Olá {nome}, identificamos que seu pedido está pendente. Para que possamos fazer o envio, é necessário finalizar o pagamento. Precisa de alguma ajuda?`;

        mensagem = mensagem.replace('{nome}', pedido.nome);

        // Enfileirar via Banco de Dados (Solução Hostinger Multi-process)
        try {
            await queueWhatsAppMessage(telefone, mensagem);
            res.json({ success: true, message: `✅ Cobrança enfileirada para ${telefone}!` });
        } catch (err) {
            console.error('Erro ao enfileirar cobrança:', err.message);
            res.json({ success: false, message: `❌ Erro ao processar cobrança: ${err.message}` });
        }
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 7.6. Templates de WhatsApp
app.get('/api/admin/whatsapp-templates', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');

        let rows = [];
        try {
            const [data] = await db.query('SELECT * FROM whatsapp_templates');
            rows = data;
        } catch (err) {
            // Tabela possivelmente não existe, usar fallback
        }

        // Se não houver templates, retornar os padrões
        if (!rows || rows.length === 0) {
            return res.json([
                { slug: 'rastreio_update', titulo: 'Atualização de Rastreio', mensagem: '📦 *Atualização de Rastreio*\n\nCódigo: {codigo}\nStatus: {status}\n{subtitulo}\n\nAcompanhe seu pedido em nosso site!' },
                { slug: 'cobranca_pendente', titulo: 'Cobrança de Pedido Pendente', mensagem: 'Olá {nome}, identificamos que seu pedido está pendente. Para que possamos fazer o envio, é necessário finalizar o pagamento. Precisa de alguma ajuda?' }
            ]);
        }
        res.json(rows);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/admin/whatsapp-templates', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const { templates } = req.body; // Array de { slug, titulo, mensagem }

        for (const t of templates) {
            await db.query(
                'INSERT INTO whatsapp_templates (slug, titulo, mensagem) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), mensagem = VALUES(mensagem)',
                [t.slug, t.titulo, t.mensagem]
            );
        }
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 8. Saúde do Banco de Dados e Tabelas
app.get('/api/admin/db-health', async (req, res) => {
    try {
        if (!db) throw new Error('Conexão com banco de dados não estabelecida.');

        const tablesToCheck = ['rastreios_status', 'clientes', 'whatsapp_contatos', 'pedidos_pendentes', 'entregadores', 'whatsapp_templates'];
        const status = {
            connected: true,
            database: process.env.DB_NAME,
            tables: []
        };

        for (const table of tablesToCheck) {
            try {
                const [info] = await db.query(`SHOW TABLES LIKE ?`, [table]);
                const exists = info.length > 0;
                let count = 0;

                if (exists) {
                    const [[{ total }]] = await db.query(`SELECT COUNT(*) as total FROM ??`, [table]);
                    count = total;
                } else {
                    // Fallback automático criador de tabelas se estiverem faltando
                    if (table === 'entregadores') {
                        await db.query(`
                            CREATE TABLE IF NOT EXISTS \`entregadores\` (
                              \`id\` int(11) NOT NULL AUTO_INCREMENT,
                              \`nome\` varchar(255) DEFAULT NULL,
                              \`telefone\` varchar(50) DEFAULT NULL,
                              \`veiculo\` varchar(100) DEFAULT NULL,
                              \`status\` varchar(50) DEFAULT 'disponivel',
                              \`data_cadastro\` datetime DEFAULT CURRENT_TIMESTAMP,
                              PRIMARY KEY (\`id\`)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                        `);
                    } else if (table === 'whatsapp_templates') {
                        await db.query(`
                            CREATE TABLE IF NOT EXISTS \`whatsapp_templates\` (
                              \`slug\` varchar(100) NOT NULL,
                              \`titulo\` varchar(255) DEFAULT NULL,
                              \`mensagem\` text DEFAULT NULL,
                              PRIMARY KEY (\`slug\`)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                        `);
                    }
                }
                status.tables.push({ name: table, exists: exists || (table === 'entregadores' || table === 'whatsapp_templates'), count });
            } catch (err) {
                status.tables.push({ name: table, exists: false, error: err.message });
            }
        }

        res.json(status);
    } catch (error) {
        res.status(500).json({ connected: false, error: error.message });
    }
});

// 9. Configuração inicial do Banco (Criar Tabelas)
app.post('/api/admin/db-setup', async (req, res) => {
    try {
        if (!db) throw new Error('DB não disponível');

        // Criar tabela de rastreios
        await db.query(`
            CREATE TABLE IF NOT EXISTS \`rastreios_status\` (
              \`id\` int(11) NOT NULL AUTO_INCREMENT,
              \`codigo\` varchar(255) DEFAULT NULL,
              \`cidade\` varchar(255) DEFAULT NULL,
              \`status_atual\` varchar(255) DEFAULT NULL,
              \`titulo\` varchar(255) DEFAULT NULL,
              \`subtitulo\` text DEFAULT NULL,
              \`data\` datetime DEFAULT NULL,
              \`cor\` varchar(50) DEFAULT NULL,
              \`taxa_valor\` decimal(10,2) DEFAULT NULL,
              \`taxa_pix\` text DEFAULT NULL,
              PRIMARY KEY (\`id\`),
              KEY \`idx_codigo\` (\`codigo\`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        `);

        // Criar tabela de clientes
        await db.query(`
            CREATE TABLE IF NOT EXISTS \`clientes\` (
              \`id\` int(11) NOT NULL AUTO_INCREMENT,
              \`nome\` varchar(255) DEFAULT NULL,
              \`email\` varchar(255) DEFAULT NULL,
              \`senha\` varchar(255) DEFAULT NULL,
              \`whatsapp\` varchar(50) DEFAULT NULL,
              \`data_cadastro\` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (\`id\`),
              UNIQUE KEY \`idx_email\` (\`email\`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        `);

        // Criar tabela de contatos whatsapp
        await db.query(`
            CREATE TABLE IF NOT EXISTS \`whatsapp_contatos\` (
              \`codigo\` varchar(255) NOT NULL,
              \`nome\` varchar(255) DEFAULT NULL,
              \`telefone_original\` varchar(50) DEFAULT NULL,
              \`telefone_normalizado\` varchar(50) DEFAULT NULL,
              \`notificacoes_ativas\` tinyint(1) DEFAULT 1,
              PRIMARY KEY (\`codigo\`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        `);

        // Criar tabela de pedidos pendentes
        await db.query(`
            CREATE TABLE IF NOT EXISTS \`pedidos_pendentes\` (
              \`id\` int(11) NOT NULL AUTO_INCREMENT,
              \`nome\` varchar(255) DEFAULT NULL,
              \`email\` varchar(255) DEFAULT NULL,
              \`telefone\` varchar(50) DEFAULT NULL,
              \`cpf\` varchar(20) DEFAULT NULL,
              \`cep\` varchar(10) DEFAULT NULL,
              \`rua\` varchar(255) DEFAULT NULL,
              \`numero\` varchar(50) DEFAULT NULL,
              \`bairro\` varchar(255) DEFAULT NULL,
              \`cidade\` varchar(255) DEFAULT NULL,
              \`estado\` varchar(50) DEFAULT NULL,
              \`data_pedido\` datetime DEFAULT CURRENT_TIMESTAMP,
              \`status\` varchar(50) DEFAULT 'pendente',
              \`codigo_rastreio\` varchar(255) DEFAULT NULL,
              PRIMARY KEY (\`id\`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        `);

        // Criar tabela de entregadores
        await db.query(`
            CREATE TABLE IF NOT EXISTS \`entregadores\` (
              \`id\` int(11) NOT NULL AUTO_INCREMENT,
              \`nome\` varchar(255) DEFAULT NULL,
              \`telefone\` varchar(50) DEFAULT NULL,
              \`veiculo\` varchar(100) DEFAULT NULL,
              \`status\` varchar(50) DEFAULT 'disponivel',
              \`data_cadastro\` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (\`id\`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        `);

        // Criar tabela de templates whatsapp
        await db.query(`
            CREATE TABLE IF NOT EXISTS \`whatsapp_templates\` (
              \`slug\` varchar(100) NOT NULL,
              \`titulo\` varchar(255) DEFAULT NULL,
              \`mensagem\` text DEFAULT NULL,
              PRIMARY KEY (\`slug\`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        `);

        // NOVO: Tabela de Fila de Mensagens WhatsApp (Solução Hostinger Multi-process)
        await db.query(`
            CREATE TABLE IF NOT EXISTS \`whatsapp_fila_mensagens\` (
              \`id\` int(11) NOT NULL AUTO_INCREMENT,
              \`telefone\` varchar(50) NOT NULL,
              \`mensagem\` text NOT NULL,
              \`status\` enum('pendente','enviado','erro') DEFAULT 'pendente',
              \`erro\` text DEFAULT NULL,
              \`data_criacao\` datetime DEFAULT CURRENT_TIMESTAMP,
              \`data_envio\` datetime DEFAULT NULL,
              PRIMARY KEY (\`id\`),
              INDEX (\`status\`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        `);

        res.json({ success: true, message: 'Tabelas criadas ou já existentes com sucesso!' });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// 10. WhatsApp Bot Integrated Management
let botInitialized = false;
// Função auxiliar para enfileirar mensagens (Solução multi-processo Hostinger)
async function queueWhatsAppMessage(telefone, mensagem) {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [result] = await db.query(
            "INSERT INTO whatsapp_fila_mensagens (telefone, mensagem, status) VALUES (?, ?, 'pendente')",
            [telefone, mensagem]
        );
        console.log(`[QUEUE] Mensagem enfileirada para ${telefone} (ID: ${result.insertId})`);
        return result.insertId;
    } catch (err) {
        console.error('[QUEUE] Erro ao enfileirar mensagem:', err.message);
        throw err;
    }
}

async function initBotModule() {
    try {
        console.log('🤖 Iniciando módulo WhatsApp Bot integrado...');
        botModule = await import('../whatsapp-bot/index.js');

        // Montar rotas do bot no app principal
        if (botModule.botRouter) {
            app.use('/api/whatsapp-internal', botModule.botRouter);
            console.log('✅ Rotas do Bot integradas em /api/whatsapp-internal');
        }

        // Iniciar o bot Baileys - AGORA PASSANDO DB PARA POLLING
        await botModule.initWhatsAppBot(app, db);
        botInitialized = true;
        console.log('✅ Módulo WhatsApp Bot integrado com sucesso (Queue/Polling habilitado)');
    } catch (err) {
        console.error('❌ Erro ao carregar módulo WhatsApp Bot:', err.message);
        console.error(err.stack);
    }
}

app.get('/api/admin/bot/status', async (req, res) => {
    try {
        if (!botInitialized || !botModule) {
            return res.json({
                success: true,
                status: { connected: false, message: 'Bot não inicializado (Módulo off)' }
            });
        }

        const state = botModule.getBotState();
        res.json({
            success: true,
            status: {
                ...state,
                connected: !!state.connected
            }
        });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.get('/api/admin/bot/qr', async (req, res) => {
    try {
        if (!botInitialized || !botModule) return res.json({ success: false, message: 'Bot off' });

        const state = botModule.getBotState();
        if (state.qr) {
            // Gerar base64 do QR localmente (mais confiável)
            const QRCodeImg = await import('qrcode');
            const dataUrl = await QRCodeImg.toDataURL(state.qr, { scale: 8, margin: 1 });
            res.json({ success: true, qr: dataUrl });
        } else {
            res.json({ success: false, message: state.connected ? 'Bot já conectado' : 'Gerando QR Code...' });
        }
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/api/admin/bot/restart', async (req, res) => {
    try {
        if (!botInitialized || !botModule) return res.json({ success: false, message: 'Bot off' });

        const result = await botModule.logoutBot();

        if (result.success) {
            res.json({ success: true, message: 'Comando de reinício enviado! O bot será reiniciado em instantes.' });
        } else {
            res.json({ success: false, message: 'Erro ao processar reinício: ' + result.error });
        }
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// 11. Configurações do Sistema
app.get('/api/admin/config', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const [rows] = await db.query('SELECT * FROM configuracoes');
        const config = {};
        rows.forEach(r => config[r.chave] = r.valor);
        res.json({ success: true, config });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/api/admin/config', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const updates = req.body; // { chave: valor, ... }
        for (const [chave, valor] of Object.entries(updates)) {
            await db.query(
                'INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
                [chave, String(valor)]
            );
        }
        res.json({ success: true, message: 'Configurações atualizadas!' });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// Endpoint público para pegar se centavos estão ativos
app.get('/api/config/centavos', async (req, res) => {
    try {
        if (!db) return res.json({ active: true });
        const [rows] = await db.query("SELECT valor FROM configuracoes WHERE chave = 'centavos_aleatorios'");
        res.json({ active: rows.length > 0 ? rows[0].valor === 'true' : true });
    } catch (e) {
        res.json({ active: true });
    }
});

// 12. Receber Novo Pedido (Frontend React)
app.post('/api/pedidos', async (req, res) => {
    try {
        if (!db) throw new Error('Banco de dados não disponível');
        const {
            nome, cpf, telefone, email,
            cep, estado, cidade, bairro,
            rua, numero, complemento, observacoes
        } = req.body;

        if (!nome || !cpf || !telefone || !cep || !estado || !cidade || !bairro || !rua || !numero) {
            return res.status(400).json({ success: false, message: 'Campos obrigatórios faltando' });
        }

        const cpfLimpo = cpf.replace(/\D/g, '');
        const telefoneLimpo = telefone.replace(/\D/g, '');

        const [result] = await db.query(
            `INSERT INTO pedidos_pendentes 
            (nome, cpf, telefone, email, cep, estado, cidade, bairro, rua, numero, complemento, observacoes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')`,
            [nome, cpfLimpo, telefoneLimpo, email || null, cep.replace(/\D/g, ''), estado, cidade, bairro, rua, numero, complemento || null, observacoes || null]
        );

        // Notificação WhatsApp (opcional, se configurado)
        let whatsappEnviado = false;
        // Enfileirar notificação WhatsApp (Solução Hostinger Multi-process)
        try {
            await queueWhatsAppMessage(telefoneLimpo, mensagem);
            whatsappEnviado = true;
        } catch (wppErr) {
            console.error('Erro ao enfileirar notificação WhatsApp:', wppErr.message);
        }

        res.json({
            success: true,
            message: 'Pedido recebido com sucesso!',
            pedidoId: result.insertId,
            whatsappEnviado
        });

    } catch (error) {
        console.error('Erro ao salvar pedido:', error);
        res.status(500).json({ success: false, message: 'Erro interno ao processar pedido.' });
    }
});


// Servir o Frontend (da raiz do repositório)
const distPath = path.join(__dirname, '..');
app.use(express.static(distPath));

app.get(/.*/, (req, res) => {
    const indexPath = path.join(distPath, 'index.html');
    res.sendFile(indexPath, (err) => {
        if (err) {
            res.status(500).send(`
                <h1>Erro de Inicialização v2</h1>
                <p>O servidor está ONLINE, mas não achou o arquivo index.html no novo local.</p>
                <p>Caminho tentado: ${indexPath}</p>
                <hr>
                <p>Por favor, verifique se a pasta 'backend/dist' existe no seu Gerenciador de Arquivos.</p>
            `);
        }
    });
});
// Se carregado via bridge (index.js da raiz), exporta o app
if (global._expressApp !== undefined || require.main !== module) {
    global._expressApp = app;
    console.log('✅ Express registrado na bridge HTTP');
    connectDB();
    initBotModule(); // Iniciar bot junto com o DB
} else {
    // Se executado diretamente
    app.listen(PORT, () => {
        console.log(`🚀 Servidor rodando na porta ${PORT}`);
        connectDB();
        initBotModule(); // Iniciar bot junto com o DB
    });
}
