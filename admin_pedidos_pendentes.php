<?php
/**
 * Painel Admin - Pedidos Pendentes
 * Helmer Logistics
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';

// Verificar autenticação
requireLogin();

// Processar aprovação/rejeição
$success_message = '';
$error_message = '';

if (isset($_POST['aprovar_pedido'])) {
    try {
        $pedidoId = (int) $_POST['pedido_id'];
        $codigoRastreio = sanitizeInput($_POST['codigo_rastreio'] ?? '');

        if (empty($codigoRastreio)) {
            throw new Exception('Código de rastreio é obrigatório.');
        }

        // Buscar dados do pedido
        $pedido = fetchOne($pdo, "SELECT * FROM pedidos_pendentes WHERE id = ?", [$pedidoId]);
        if (!$pedido) throw new Exception('Pedido não encontrado.');

        // Verificar duplicidade
        $exists = fetchOne($pdo, "SELECT 1 FROM rastreios_status WHERE codigo = ?", [$codigoRastreio]);
        if ($exists) throw new Exception("Código {$codigoRastreio} já existe.");

        // Criar rastreio
        $cidade = $pedido['cidade'] . '/' . $pedido['estado'];
        $sql = "INSERT INTO rastreios_status 
            (codigo, cidade, status_atual, titulo, subtitulo, data, cor)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        executeQuery($pdo, $sql, [
            $codigoRastreio,
            $cidade,
            '📦 Objeto postado',
            '📦 Objeto postado',
            'Objeto recebido e postado para envio',
            date('Y-m-d H:i:s'),
            '#16A34A'
        ]);

        // Atualizar pedido
        executeQuery($pdo, "UPDATE pedidos_pendentes SET status = 'aprovado', codigo_rastreio = ? WHERE id = ?", [$codigoRastreio, $pedidoId]);

        $success_message = "Pedido aprovado com sucesso! Rastreio criado: {$codigoRastreio}";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

if (isset($_POST['rejeitar_pedido'])) {
    try {
        $pedidoId = (int) $_POST['pedido_id'];
        executeQuery($pdo, "UPDATE pedidos_pendentes SET status = 'rejeitado' WHERE id = ?", [$pedidoId]);
        $success_message = "Pedido rejeitado com sucesso.";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Buscar pedidos pendentes
$pedidos = fetchData($pdo, "SELECT * FROM pedidos_pendentes WHERE status = 'pendente' ORDER BY data_pedido DESC");

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos Pendentes | Helmer Admin</title>
    <meta name="theme-color" content="#FF3333">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <i class="fas fa-cube"></i> Helmer
                </div>
                <button class="mobile-close-btn" onclick="toggleSidebar()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <nav class="sidebar-menu">
                <div class="menu-label">Principal</div>
                <a href="index.php" class="nav-item"><i class="fas fa-home"></i> Página Inicial</a>
                <a href="admin.php" class="nav-item"><i class="fas fa-chart-pie"></i> Dashboard</a>
                <a href="admin_pedidos_pendentes.php" class="nav-item active"><i class="fas fa-shopping-cart"></i> Pedidos Pendentes</a>
                <a href="admin_indicacoes.php" class="nav-item"><i class="fas fa-users"></i> Indicações</a>
                
                <div class="menu-label">Gestão</div>
                <a href="admin_homepage.php" class="nav-item"><i class="fas fa-pen-to-square"></i> Editar Site</a>
                <a href="admin_bot_config.php" class="nav-item"><i class="fas fa-robot"></i> Configuração Bot</a>
                <a href="admin_mensagens.php" class="nav-item"><i class="fas fa-message"></i> Mensagens WPP</a>
                
                <div class="menu-label">Configuração</div>
                <a href="admin_settings.php" class="nav-item"><i class="fas fa-gear"></i> Ajustes Expressa</a>
            </nav>
            
            <div class="sidebar-footer">
                <a href="admin.php?logout=1" class="nav-item" style="color: var(--primary);"><i class="fas fa-power-off"></i> Sair</a>
            </div>
        </aside>

        <!-- Overlay Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <button class="mobile-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title">
                        <h2>Pedidos Pendentes</h2>
                    </div>
                </div>
            </header>

            <div class="content-body">
                <?php if (empty($pedidos)): ?>
                    <div style="text-align:center; padding: 4rem 2rem; color: var(--text-muted);">
                        <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 1rem; color: var(--success);"></i>
                        <h3>Tudo em dia!</h3>
                        <p>Não há novos pedidos pendentes no momento.</p>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 1.5rem;">
                        <?php foreach ($pedidos as $pedido): ?>
                            <div class="card-item" style="padding: 1.5rem; cursor: default;">
                                <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
                                    <div>
                                        <h3 style="color:var(--text-main); font-size:1.2rem; display:flex; align-items:center; gap:0.5rem;">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($pedido['nome']) ?>
                                        </h3>
                                        <div style="color:var(--text-muted); font-size:0.9rem; margin-top:0.25rem;">
                                            <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <span class="badge badge-warning">Pendente</span>
                                    </div>
                                </div>

                                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:1.5rem; margin-bottom:1.5rem;">
                                    <div style="background:rgba(255,255,255,0.03); padding:1rem; border-radius:8px;">
                                        <h4 style="color:var(--text-muted); font-size:0.85rem; margin-bottom:0.5rem; text-transform:uppercase;">Contato</h4>
                                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                            <div><i class="fas fa-phone fa-fw"></i> <?= htmlspecialchars($pedido['telefone']) ?></div>
                                            <?php if($pedido['email']): ?>
                                                <div><i class="fas fa-envelope fa-fw"></i> <?= htmlspecialchars($pedido['email']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div style="background:rgba(255,255,255,0.03); padding:1rem; border-radius:8px;">
                                        <h4 style="color:var(--text-muted); font-size:0.85rem; margin-bottom:0.5rem; text-transform:uppercase;">Endereço</h4>
                                        <div style="line-height:1.5;">
                                            <?= htmlspecialchars($pedido['rua']) ?>, <?= htmlspecialchars($pedido['numero']) ?><br>
                                            <?= htmlspecialchars($pedido['bairro']) ?> - <?= htmlspecialchars($pedido['cidade']) ?>/<?= htmlspecialchars($pedido['estado']) ?><br>
                                            CEP: <?= htmlspecialchars($pedido['cep']) ?>
                                        </div>
                                    </div>
                                </div>

                                <form method="POST" style="background:#111; padding:1rem; border-radius:8px; border:1px solid var(--border-subtle);">
                                    <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                    <div style="display:grid; grid-template-columns: 1fr auto; gap:1rem; align-items:end;">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label style="font-size:0.9rem;">Gerar Código de Rastreio</label>
                                            <div class="input-with-button" style="display:flex; gap:0.5rem;">
                                                <input type="text" name="codigo_rastreio" class="form-control" placeholder="AA123456789BR" required style="font-family:var(--font-mono);">
                                                <!-- O JS de auto-increment irá adicionar o botão aqui -->
                                            </div>
                                        </div>
                                        <div style="display:flex; gap:0.5rem;">
                                            <button type="submit" name="rejeitar_pedido" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja rejeitar?')">
                                                <i class="fas fa-times"></i> Rejeitar
                                            </button>
                                            <button type="submit" name="aprovar_pedido" class="btn btn-success">
                                                <i class="fas fa-check"></i> Aprovar
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if(sidebar) {
                const isActive = sidebar.classList.toggle('active');
                if(overlay) overlay.classList.toggle('active', isActive);
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('sidebarOverlay');
            if(overlay) {
                overlay.addEventListener('click', toggleSidebar);
            }
            
            <?php if ($success_message): ?>
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: '<?= addslashes($success_message) ?>',
                background: '#1a1a1a',
                color: '#fff',
                confirmButtonColor: '#16A34A'
            });
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: '<?= addslashes($error_message) ?>',
                background: '#1a1a1a',
                color: '#fff',
                confirmButtonColor: '#EF4444'
            });
            <?php endif; ?>
        });
    </script>
    <script src="assets/js/codigo-auto-increment.js"></script>
</body>
</html>
