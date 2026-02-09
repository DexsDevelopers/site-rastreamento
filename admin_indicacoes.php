<?php
/**
 * Painel Admin - Gerenciamento de Indicações (Moderno)
 * Loggi
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';

// Verificar login
requireLogin();

// Processar ações
if (isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'aprovar_indicacao':
                $id = sanitizeInput($_POST['id']);
                executeQuery($pdo, "UPDATE indicacoes SET status = 'confirmada' WHERE id = ?", [$id]);
                $success_message = "Indicação aprovada!";
                break;
            case 'rejeitar_indicacao':
                $id = sanitizeInput($_POST['id']);
                executeQuery($pdo, "UPDATE indicacoes SET status = 'pendente' WHERE id = ?", [$id]);
                $success_message = "Indicação rejeitada!";
                break;
            case 'marcar_entregue':
                $id = sanitizeInput($_POST['id']);
                executeQuery($pdo, "UPDATE indicacoes SET status = 'entregue' WHERE id = ?", [$id]);
                $success_message = "Entrega marcada como concluída!";
                break;
        }
    }
    catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Buscar dados
try {
    $indicacoes = fetchData($pdo, "SELECT i.*, c1.nome as nome_indicador, c2.nome as nome_indicado 
                                   FROM indicacoes i 
                                   LEFT JOIN clientes c1 ON i.codigo_indicador = c1.codigo 
                                   LEFT JOIN clientes c2 ON i.codigo_indicado = c2.codigo 
                                   ORDER BY i.data_indicacao DESC");

    $stats = fetchOne($pdo, "SELECT 
                                COUNT(*) as total_indicacoes,
                                COUNT(CASE WHEN status = 'confirmada' THEN 1 END) as confirmadas,
                                COUNT(CASE WHEN status = 'entregue' THEN 1 END) as entregues
                              FROM indicacoes");
}
catch (Exception $e) {
    $indicacoes = [];
    $stats = ['total_indicacoes' => 0, 'confirmadas' => 0, 'entregues' => 0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indicações | Loggi Admin</title>
    <meta name="theme-color" content="#0055FF">
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
                    <i class="fas fa-cube"></i> Loggi
                </div>
                <button class="mobile-close-btn" onclick="toggleSidebar()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <nav class="sidebar-menu">
                <div class="menu-label">Principal</div>
                <a href="index.php" class="nav-item"><i class="fas fa-home"></i> Página Inicial</a>
                <a href="admin.php" class="nav-item"><i class="fas fa-chart-pie"></i> Dashboard</a>
                <a href="admin_pedidos_pendentes.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Pedidos
                    Pendentes</a>
                <a href="admin_indicacoes.php" class="nav-item active"><i class="fas fa-users"></i> Indicações</a>

                <div class="menu-label">Gestão</div>
                <a href="admin_homepage.php" class="nav-item"><i class="fas fa-pen-to-square"></i> Editar Site</a>
                <a href="admin_bot.php" class="nav-item"><i class="fas fa-robot"></i> Painel Bot</a>
                <a href="admin_mensagens.php" class="nav-item"><i class="fas fa-message"></i> Mensagens WPP</a>

                <div class="menu-label">Configuração</div>
                <a href="admin_settings.php" class="nav-item"><i class="fas fa-gear"></i> Ajustes Expressa</a>
            </nav>

            <div class="sidebar-footer">
                <a href="admin.php?logout=1" class="nav-item" style="color: var(--primary);"><i
                        class="fas fa-power-off"></i> Sair</a>
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
                        <h2>Indicações</h2>
                    </div>
                </div>
            </header>

            <div class="content-body">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card featured">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-value">
                            <?= $stats['total_indicacoes']?>
                        </div>
                        <div class="stat-label">Total Indicações</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-value">
                            <?= $stats['confirmadas']?>
                        </div>
                        <div class="stat-label">Confirmadas</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--info);"><i class="fas fa-truck"></i></div>
                        <div class="stat-value">
                            <?= $stats['entregues']?>
                        </div>
                        <div class="stat-label">Entregues</div>
                    </div>
                </div>

                <!-- Table -->
                <div class="glass-panel" style="margin-top: 2rem;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Indicador</th>
                                    <th>Indicado</th>
                                    <th>Data</th>
                                    <th>Status</th>
                                    <th style="text-align:right;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($indicacoes)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 2rem; color: var(--text-muted);">
                                        Nenhuma indicação encontrada.
                                    </td>
                                </tr>
                                <?php
else: ?>
                                <?php foreach ($indicacoes as $row): ?>
                                <tr>
                                    <td style="color: var(--text-muted);">#
                                        <?= $row['id']?>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;">
                                            <?= htmlspecialchars($row['nome_indicador'] ?? 'N/A')?>
                                        </div>
                                        <div style="font-size:0.8rem; color:var(--text-muted);">
                                            <?= htmlspecialchars($row['codigo_indicador'])?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;">
                                            <?= htmlspecialchars($row['nome_indicado'] ?? 'N/A')?>
                                        </div>
                                        <div style="font-size:0.8rem; color:var(--text-muted);">
                                            <?= htmlspecialchars($row['codigo_indicado'])?>
                                        </div>
                                    </td>
                                    <td style="color:var(--text-muted);">
                                        <?= date('d/m/Y H:i', strtotime($row['data_indicacao']))?>
                                    </td>
                                    <td>
                                        <?php
        $badgeClass = 'badge-warning';
        switch ($row['status']) {
            case 'confirmada':
                $badgeClass = 'badge-success';
                break;
            case 'entregue':
                $badgeClass = 'badge-info';
                break;
        }
?>
                                        <span class="badge <?= $badgeClass?>">
                                            <?= ucfirst($row['status'])?>
                                        </span>
                                    </td>
                                    <td style="text-align:right;">
                                        <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                                            <?php if ($row['status'] == 'pendente'): ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="aprovar_indicacao">
                                                <input type="hidden" name="id" value="<?= $row['id']?>">
                                                <button type="submit" class="btn btn-icon"
                                                    style="color: var(--success);" title="Aprovar">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <?php
        endif; ?>

                                            <?php if ($row['status'] == 'confirmada'): ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="marcar_entregue">
                                                <input type="hidden" name="id" value="<?= $row['id']?>">
                                                <button type="submit" class="btn btn-icon" style="color: var(--info);"
                                                    title="Marcar Entregue">
                                                    <i class="fas fa-truck"></i>
                                                </button>
                                            </form>
                                            <?php
        endif; ?>

                                            <form method="POST" onsubmit="return confirm('Rejeitar indicação?');">
                                                <input type="hidden" name="action" value="rejeitar_indicacao">
                                                <input type="hidden" name="id" value="<?= $row['id']?>">
                                                <button type="submit" class="btn btn-icon" style="color: var(--danger);"
                                                    title="Rejeitar">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php
    endforeach; ?>
                                <?php
endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) {
                const isActive = sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active', isActive);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay) {
                overlay.addEventListener('click', toggleSidebar);
            }

            <? php if (isset($success_message)): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso',
                    text: '<?= addslashes($success_message)?>',
                    background: '#1a1a1a',
                    color: '#fff',
                    confirmButtonColor: '#16A34A',
                    timer: 2000
                });
            <? php
endif; ?>
        });
    </script>
</body>

</html>