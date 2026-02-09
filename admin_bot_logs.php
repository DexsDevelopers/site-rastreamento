<?php
/**
 * Logs do Bot WhatsApp
 * Rastreamento de mensagens e erros
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';

requireLogin();

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filtros
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$where = "WHERE 1=1";
if ($filter) {
    $where .= " AND (tipo LIKE '%$filter%' OR mensagem LIKE '%$filter%')";
}

// Buscar logs
try {
    $totalLogs = fetchOne($pdo, "SELECT COUNT(*) as total FROM bot_automation_logs $where")['total'];
    $totalPages = ceil($totalLogs / $limit);

    $logs = fetchData($pdo, "SELECT l.*, a.nome as automation_nome 
                             FROM bot_automation_logs l 
                             LEFT JOIN bot_automations a ON l.automation_id = a.id 
                             $where 
                             ORDER BY l.criado_em DESC 
                             LIMIT $limit OFFSET $offset");
}
catch (Exception $e) {
    $logs = [];
    $totalLogs = 0;
    $totalPages = 1;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs do Bot | Rastreamento</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .logs-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            overflow: hidden;
        }

        .log-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .log-info {
            flex: 1;
        }

        .log-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
        }

        .log-title {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .log-time {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.8rem;
        }

        .log-msg {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .log-details {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.4);
            font-family: monospace;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .badge-error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }

        .badge-info {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .page-link {
            padding: 0.5rem 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            color: white;
            text-decoration: none;
            transition: all 0.2s;
        }

        .page-link.active {
            background: #0055FF;
        }

        .page-link:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <i class="fas fa-robot"></i> Bot WhatsApp
                </div>
                <button class="mobile-close-btn" onclick="toggleSidebar()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <nav class="sidebar-menu">
                <div class="menu-label">Bot Rastreamento</div>
                <a href="admin_bot.php" class="nav-item"><i class="fas fa-gauge-high"></i> Dashboard</a>
                <a href="admin_bot_logs.php" class="nav-item active"><i class="fas fa-scroll"></i> Logs</a>
                <a href="admin_mensagens.php" class="nav-item"><i class="fas fa-comment-dots"></i> Mensagens</a>
                <div class="menu-label">Sistema</div>
                <a href="admin.php" class="nav-item"><i class="fas fa-arrow-left"></i> Voltar ao Painel</a>
            </nav>
        </aside>

        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <main class="main-content">
            <header class="top-header">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <button class="mobile-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title">
                        <h2>Logs do Bot</h2>
                    </div>
                </div>
                <div style="display:flex; gap:1rem;">
                    <form action="" method="GET" style="display:flex; gap:0.5rem;">
                        <input type="text" name="filter" value="<?= htmlspecialchars($filter)?>"
                            placeholder="Filtrar logs..." class="form-control" style="padding:0.4rem 0.8rem;">
                        <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
                    </form>
                </div>
            </header>

            <div class="content-body">
                <div class="logs-container">
                    <?php if (empty($logs)): ?>
                    <div style="padding: 2rem; text-align: center; color: rgba(255,255,255,0.5);">
                        Nenhum log encontrado.
                    </div>
                    <?php
else: ?>
                    <?php foreach ($logs as $log):
        $icon = 'info-circle';
        $colorClass = 'badge-info';

        if (strpos($log['tipo'], 'erro') !== false || strpos($log['tipo'], 'falha') !== false) {
            $icon = 'exclamation-triangle';
            $colorClass = 'badge-error';
        }
        elseif (strpos($log['tipo'], 'mensagem') !== false) {
            $icon = 'comment';
            $colorClass = 'badge-success';
        }
?>
                    <div class="log-item">
                        <div class="log-icon <?= $colorClass?>">
                            <i class="fas fa-<?= $icon?>"></i>
                        </div>
                        <div class="log-info">
                            <div class="log-header">
                                <span class="log-title">
                                    <?= htmlspecialchars($log['tipo'])?>
                                </span>
                                <span class="log-time">
                                    <?= date('d/m/Y H:i:s', strtotime($log['criado_em']))?>
                                </span>
                            </div>
                            <div class="log-msg">
                                <?= htmlspecialchars($log['mensagem'])?>
                            </div>
                            <?php if ($log['automation_nome']): ?>
                            <div class="log-details">
                                Automação:
                                <?= htmlspecialchars($log['automation_nome'])?>
                            </div>
                            <?php
        endif; ?>
                        </div>
                    </div>
                    <?php
    endforeach; ?>
                    <?php
endif; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i?>&filter=<?= htmlspecialchars($filter)?>"
                        class="page-link <?= $i == $page ? 'active' : ''?>">
                        <?= $i?>
                    </a>
                    <?php
    endfor; ?>
                </div>
                <?php
endif; ?>
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
            if (overlay) overlay.addEventListener('click', toggleSidebar);
        });
    </script>
</body>

</html>