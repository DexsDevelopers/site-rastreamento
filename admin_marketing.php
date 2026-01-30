<?php
/**
 * Gerenciador de Marketing WhatsApp
 * Configuração de campanhas, mensagens e membros
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Verificar login
if (!isset($_SESSION['logado'])) {
    header('Location: admin.php');
    exit;
}

$message = '';
$type = '';

// Criar tabelas se não existirem (Safety check)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketing_campanhas (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nome VARCHAR(100) NOT NULL,
            ativo BOOLEAN DEFAULT 0,
            membros_por_dia_grupo INT DEFAULT 5,
            intervalo_min_minutos INT DEFAULT 30,
            intervalo_max_minutos INT DEFAULT 120,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS marketing_mensagens (
            id INT PRIMARY KEY AUTO_INCREMENT,
            campanha_id INT,
            ordem INT NOT NULL,
            tipo ENUM('texto', 'imagem', 'audio') DEFAULT 'texto',
            conteudo TEXT,
            delay_apos_anterior_minutos INT DEFAULT 0,
            FOREIGN KEY (campanha_id) REFERENCES marketing_campanhas(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS marketing_membros (
            id INT PRIMARY KEY AUTO_INCREMENT,
            telefone VARCHAR(20) NOT NULL,
            grupo_origem_jid VARCHAR(100),
            nome VARCHAR(100),
            status ENUM('novo', 'em_progresso', 'concluido', 'bloqueado') DEFAULT 'novo',
            ultimo_passo_id INT DEFAULT 0,
            data_proximo_envio DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (telefone, grupo_origem_jid)
        );

        INSERT IGNORE INTO marketing_campanhas (id, nome, ativo, membros_por_dia_grupo) VALUES (1, 'Campanha Grupos', 0, 5);
    ");
} catch (PDOException $e) {
    // Ignorar erro se tabelas já existirem ou erro de permissão
}

// Processar formulário de Campanha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_campaign'])) {
    try {
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $membros_dia = (int)$_POST['membros_dia'];
        $intervalo_min = (int)$_POST['intervalo_min'];
        $intervalo_max = (int)$_POST['intervalo_max'];
        
        $sql = "UPDATE marketing_campanhas SET ativo = ?, membros_por_dia_grupo = ?, intervalo_min_minutos = ?, intervalo_max_minutos = ? WHERE id = 1";
        executeQuery($pdo, $sql, [$ativo, $membros_dia, $intervalo_min, $intervalo_max]);
        
        $message = "✅ Configurações da campanha atualizadas!";
        $type = 'success';
    } catch (Exception $e) {
        $message = "❌ Erro ao salvar: " . $e->getMessage();
        $type = 'error';
    }
}

// Processar adição de mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_message'])) {
    try {
        $conteudo = trim($_POST['conteudo']);
        $delay = (int)$_POST['delay'];
        
        // Pegar a próxima ordem
        $lastOrder = fetchOne($pdo, "SELECT MAX(ordem) as max_ordem FROM marketing_mensagens WHERE campanha_id = 1");
        $ordem = ($lastOrder['max_ordem'] ?? 0) + 1;
        
        if (!empty($conteudo)) {
            $sql = "INSERT INTO marketing_mensagens (campanha_id, ordem, conteudo, delay_apos_anterior_minutos) VALUES (1, ?, ?, ?)";
            executeQuery($pdo, $sql, [$ordem, $conteudo, $delay]);
            $message = "✅ Mensagem adicionada!";
            $type = 'success';
        }
    } catch (Exception $e) {
        $message = "❌ Erro ao adicionar mensagem: " . $e->getMessage();
        $type = 'error';
    }
}

// Processar exclusão de mensagem
if (isset($_GET['delete_msg'])) {
    try {
        $id = (int)$_GET['delete_msg'];
        executeQuery($pdo, "DELETE FROM marketing_mensagens WHERE id = ?", [$id]);
        $message = "🗑️ Mensagem removida!";
        $type = 'success';
    } catch (Exception $e) {
        $message = "❌ Erro ao remover: " . $e->getMessage();
        $type = 'error';
    }
}

// Processar Sync (chamada AJAX simulada)
if (isset($_POST['sync_members'])) {
    // Inserir lógica de chamar o bot para sincronizar membros
    // Isso será feito via JS chamando o endpoint do bot
}

// Carregar dados
$campanha = fetchOne($pdo, "SELECT * FROM marketing_campanhas WHERE id = 1");
$mensagens = fetchData($pdo, "SELECT * FROM marketing_mensagens WHERE campanha_id = 1 ORDER BY ordem ASC");
$stats = fetchOne($pdo, "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'novo' THEN 1 ELSE 0 END) as novos,
    SUM(CASE WHEN status = 'em_progresso' THEN 1 ELSE 0 END) as progresso,
    SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidos
    FROM marketing_membros");

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Marketing Config - WhatsApp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/admin-mobile.css">
    <style>
        /* Estilos baseados no admin_mensagens.php */
        body { background: #0b0b0b; color: #fff; font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #161616; padding: 24px; border-radius: 12px; border: 1px solid #2a2a2a; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .topbar h1 { color: #ff3333; margin: 0; font-size: 1.5rem; }
        .topbar a { text-decoration: none; color: #aaa; background: #252525; padding: 8px 12px; border-radius: 6px; }
        
        .card { background: #1a1a1a; padding: 20px; border-radius: 8px; border: 1px solid #333; margin-bottom: 20px; }
        .card h2 { margin-top: 0; color: #fff; border-bottom: 1px solid #333; padding-bottom: 10px; font-size: 1.2rem; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #ccc; }
        .form-group input[type="number"], .form-group input[type="text"], .form-group textarea {
            width: 100%; padding: 10px; background: #0f0f0f; border: 1px solid #333; color: #fff; border-radius: 4px;
        }
        .form-group input[type="checkbox"] { transform: scale(1.5); margin-right: 10px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; color: #fff; font-weight: bold; }
        .btn-primary { background: linear-gradient(90deg, #ff3333, #ff6600); }
        .btn-secondary { background: #333; }
        .btn-danger { background: #991b1b; padding: 5px 10px; font-size: 0.8rem; }
        
        .msg-list { list-style: none; padding: 0; }
        .msg-item { background: #222; padding: 15px; margin-bottom: 10px; border-radius: 6px; border-left: 3px solid #ff3333; display: flex; justify-content: space-between; align-items: center; }
        .msg-content { flex: 1; }
        .msg-meta { font-size: 0.8rem; color: #888; margin-top: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; text-align: center; }
        .stat-box { background: #222; padding: 10px; border-radius: 6px; }
        .stat-val { font-size: 1.5rem; font-weight: bold; color: #ff3333; }
        .stat-label { font-size: 0.8rem; color: #aaa; }
        
        .msg { padding: 10px; border-radius: 6px; margin-bottom: 20px; }
        .success { background: #0a2915; color: #4ade80; border: 1px solid #1a6b2d; }
        .error { background: #2a1111; color: #f87171; border: 1px solid #6b1a1a; }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <h1>📣 Marketing Grupos</h1>
            <a href="admin.php">⟵ Voltar</a>
        </div>

        <?php if ($message): ?>
            <div class="msg <?= $type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-val"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-label">Total Membros</div>
            </div>
            <div class="stat-box">
                <div class="stat-val"><?= $stats['novos'] ?? 0 ?></div>
                <div class="stat-label">Na Fila (Novos)</div>
            </div>
            <div class="stat-box">
                <div class="stat-val"><?= $stats['progresso'] ?? 0 ?></div>
                <div class="stat-label">Em Andamento</div>
            </div>
            <div class="stat-box">
                <div class="stat-val"><?= $stats['concluidos'] ?? 0 ?></div>
                <div class="stat-label">Finalizados</div>
            </div>
        </div>

        <!-- Configuração da Campanha -->
        <div class="card">
            <h2>⚙️ Configuração da Campanha</h2>
            <form method="POST">
                <div class="form-group" style="display: flex; align-items: center;">
                    <input type="checkbox" name="ativo" id="ativo" <?= ($campanha['ativo'] ?? 0) ? 'checked' : '' ?>>
                    <label for="ativo" style="margin: 0; cursor: pointer;">Campanha Ativa</label>
                </div>
                
                <div class="form-group">
                    <label>Membros por Dia (por Grupo):</label>
                    <input type="number" name="membros_dia" value="<?= $campanha['membros_por_dia_grupo'] ?? 5 ?>" min="1" max="50">
                    <small style="color: #666;">Segurança: Recomendado máximo 5-10 para evitar banimento.</small>
                </div>
                
                <div class="form-group">
                    <label>Intervalo Aleatório entre Envios (Minutos):</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="number" name="intervalo_min" value="<?= $campanha['intervalo_min_minutos'] ?? 30 ?>" placeholder="Mínimo">
                        <input type="number" name="intervalo_max" value="<?= $campanha['intervalo_max_minutos'] ?? 120 ?>" placeholder="Máximo">
                    </div>
                </div>
                
                <button type="submit" name="save_campaign" class="btn btn-primary">Salvar Configurações</button>
                <button type="button" id="btnSync" class="btn btn-secondary" style="margin-left: 10px;">🔄 Sincronizar Membros dos Grupos</button>
            </form>
            <p id="syncStatus" style="margin-top: 10px; color: #aaa; font-size: 0.9rem;"></p>
        </div>

        <!-- Mensagens -->
        <div class="card">
            <h2>💬 Sequência de Mensagens</h2>
            <p style="color: #aaa; font-size: 0.9rem;">Configure as mensagens que serão enviadas para cada contato extraído.</p>
            
            <?php if (empty($mensagens)): ?>
                <div style="padding: 20px; text-align: center; color: #666; dashed: 1px border #333;">Nenhuma mensagem configurada. Adicione a primeira abaixo.</div>
            <?php else: ?>
                <ul class="msg-list">
                    <?php foreach ($mensagens as $msg): ?>
                        <li class="msg-item">
                            <div class="msg-content">
                                <strong>#<?= $msg['ordem'] ?></strong>: <?= nl2br(htmlspecialchars(substr($msg['conteudo'], 0, 100))) ?><?= strlen($msg['conteudo']) > 100 ? '...' : '' ?>
                                <div class="msg-meta">
                                    Delay: <?= $msg['delay_apos_anterior_minutos'] == 0 ? 'Imediato (1º msg)' : 'Aguarda ' . $msg['delay_apos_anterior_minutos'] . ' min após anterior' ?>
                                </div>
                            </div>
                            <a href="?delete_msg=<?= $msg['id'] ?>" class="btn btn-danger" onclick="return confirm('Tem certeza?')">X</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <hr style="border: 0; border-top: 1px solid #333; margin: 20px 0;">

            <h3>Adicionar Nova Mensagem</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Conteúdo da Mensagem:</label>
                    <textarea name="conteudo" rows="4" required placeholder="Olá, vi que você está no grupo..."></textarea>
                    <small style="color: #666;">Você pode usar {nome} para o nome do contato (se disponível).</small>
                </div>
                
                <div class="form-group">
                    <label>Delay (Minutos após a mensagem ANTERIOR):</label>
                    <input type="number" name="delay" value="<?= empty($mensagens) ? 0 : 60 ?>" required>
                    <small style="color: #666;">Se for a primeira mensagem, o delay será contado a partir do momento que o membro for selecionado no dia.</small>
                </div>
                
                <button type="submit" name="add_message" class="btn btn-primary">+ Adicionar Mensagem</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('btnSync').addEventListener('click', async () => {
            const btn = document.getElementById('btnSync');
            const status = document.getElementById('syncStatus');
            
            if (!confirm('Isso irá pedir ao Bot para varrer todos os grupos e salvar os membros no banco de dados. Pode levar alguns instantes. Continuar?')) return;
            
            btn.disabled = true;
            status.textContent = '⏳ Solicitando sincronização ao bot...';
            
            try {
                // Tenta chamar o bot diretamente (se estiver na mesma rede/acessível publicamente)
                // Ou chama um script PHP que faz a ponte curl
                // Como o bot roda na porta configurada, vamos tentar chamar um script PHP local que faz a ponte
                
                const response = await fetch('api_marketing_trigger.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'sync_groups' })
                });

                const data = await response.json();
                
                if (data.success) {
                    status.textContent = '✅ Comando enviado! O bot está processando os grupos em plano de fundo. Atualize a página em alguns instantes.';
                    status.style.color = '#4ade80';
                } else {
                    status.textContent = '❌ Erro: ' + (data.message || 'Falha na comunicação');
                    status.style.color = '#f87171';
                }
            } catch (e) {
                status.textContent = '❌ Erro de conexão: ' + e.message;
                status.style.color = '#f87171';
            } finally {
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
