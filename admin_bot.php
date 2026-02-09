<?php
/**
 * Painel do Bot WhatsApp - Rastreamento
 * Dashboard com status, QR Code e informações essenciais
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';
require_once 'includes/whatsapp_helper.php';

requireLogin();

// Buscar status do bot
$botStatus = [
    'online' => false,
    'ready' => false,
    'uptime' => 0,
    'uptimeFormatted' => '0h 0m 0s',
    'memoryMB' => 0,
    'reconnectAttempts' => 0
];

$qrCode = null;
$apiConfig = whatsappApiConfig();

if ($apiConfig['enabled']) {
    try {
        // Buscar status
        $ch = curl_init($apiConfig['base_url'] . '/status');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['ngrok-skip-browser-warning: true'],
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if ($data) {
                $botStatus = array_merge($botStatus, [
                    'online' => true,
                    'ready' => $data['ready'] ?? false,
                    'uptime' => $data['uptime'] ?? 0,
                    'uptimeFormatted' => $data['uptimeFormatted'] ?? '0h 0m 0s',
                    'memoryMB' => $data['memoryMB'] ?? 0,
                    'reconnectAttempts' => $data['reconnectAttempts'] ?? 0
                ]);
            }
        }

        // Buscar QR Code se não estiver conectado
        if (!$botStatus['ready']) {
            $ch = curl_init($apiConfig['base_url'] . '/qr');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => ['ngrok-skip-browser-warning: true'],
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $qrResponse = curl_exec($ch);
            curl_close($ch);

            // Extrair imagem base64 do QR se existir
            if (preg_match('/src="(data:image\/png;base64,[^"]+)"/', $qrResponse, $matches)) {
                $qrCode = $matches[1];
            }
        }
    }
    catch (Exception $e) {
    // Bot offline
    }
}

// Estatísticas de notificações
$stats = [
    'total_enviadas' => 0,
    'hoje' => 0,
    'sucesso' => 0,
    'falhas' => 0
];

try {
    $result = fetchOne($pdo, "SELECT COUNT(*) as total FROM whatsapp_notificacoes");
    $stats['total_enviadas'] = $result['total'] ?? 0;

    $result = fetchOne($pdo, "SELECT COUNT(*) as total FROM whatsapp_notificacoes WHERE DATE(enviado_em) = CURDATE()");
    $stats['hoje'] = $result['total'] ?? 0;

    $result = fetchOne($pdo, "SELECT COUNT(*) as total FROM whatsapp_notificacoes WHERE sucesso = 1");
    $stats['sucesso'] = $result['total'] ?? 0;

    $stats['falhas'] = $stats['total_enviadas'] - $stats['sucesso'];
}
catch (Exception $e) {
// Tabela pode não existir
}

// Contatos ativos
$contatosAtivos = 0;
try {
    $result = fetchOne($pdo, "SELECT COUNT(*) as total FROM whatsapp_contatos WHERE notificacoes_ativas = 1");
    $contatosAtivos = $result['total'] ?? 0;
}
catch (Exception $e) {
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bot WhatsApp | Rastreamento</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .bot-dashboard {
            display: grid;
            gap: 2rem;
        }

        .status-hero {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-radius: 24px;
            padding: 2.5rem;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 2rem;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .status-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .status-dot.online {
            background: #10b981;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.5);
        }

        .status-dot.offline {
            background: #ef4444;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.5);
        }

        .status-dot.waiting {
            background: #f59e0b;
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.5);
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.7;
                transform: scale(1.1);
            }
        }

        .status-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }

        .status-details {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .detail-value {
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .qr-section {
            text-align: center;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            min-width: 280px;
        }

        .qr-section img {
            max-width: 200px;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .qr-section .waiting-msg {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin: 0 auto 1rem;
        }

        .stat-icon.green {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .stat-icon.blue {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
        }

        .stat-icon.purple {
            background: rgba(139, 92, 246, 0.15);
            color: #8b5cf6;
        }

        .stat-icon.orange {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .action-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: white;
            transition: all 0.3s;
        }

        .action-card:hover {
            background: rgba(0, 85, 255, 0.1);
            border-color: rgba(0, 85, 255, 0.3);
            transform: translateX(5px);
        }

        .action-icon {
            width: 50px;
            height: 50px;
            background: rgba(0, 85, 255, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #0055FF;
        }

        .action-info h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .action-info p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: #0055FF;
        }

        @media (max-width: 768px) {
            .status-hero {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .status-details {
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
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
                <a href="admin_bot.php" class="nav-item active"><i class="fas fa-gauge-high"></i> Dashboard</a>
                <a href="admin_bot_logs.php" class="nav-item"><i class="fas fa-scroll"></i> Logs</a>
                <a href="admin_bot_config.php" class="nav-item"><i class="fas fa-cog"></i> Configurações</a>

                <div class="menu-label">Sistema</div>
                <a href="admin.php" class="nav-item"><i class="fas fa-arrow-left"></i> Voltar ao Painel</a>
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
                        <h2>Dashboard do Bot</h2>
                    </div>
                </div>
                <div style="display:flex; gap:1rem;">
                    <button onclick="location.reload()" class="btn btn-secondary" style="padding: 0.5rem 1rem;">
                        <i class="fas fa-refresh"></i> Atualizar
                    </button>
                </div>
            </header>

            <div class="content-body">
                <div class="bot-dashboard">

                    <!-- Status Principal -->
                    <div class="status-hero">
                        <div>
                            <div class="status-indicator">
                                <?php if ($botStatus['ready']): ?>
                                <div class="status-dot online"></div>
                                <span class="status-text">✅ Bot Conectado</span>
                                <?php
elseif ($botStatus['online']): ?>
                                <div class="status-dot waiting"></div>
                                <span class="status-text">⏳ Aguardando QR Code</span>
                                <?php
else: ?>
                                <div class="status-dot offline"></div>
                                <span class="status-text">❌ Bot Offline</span>
                                <?php
endif; ?>
                            </div>

                            <div class="status-details">
                                <div class="detail-item">
                                    <span class="detail-label">Uptime</span>
                                    <span class="detail-value">
                                        <?= htmlspecialchars($botStatus['uptimeFormatted'])?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Memória</span>
                                    <span class="detail-value">
                                        <?= $botStatus['memoryMB']?> MB
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Reconexões</span>
                                    <span class="detail-value">
                                        <?= $botStatus['reconnectAttempts']?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Contatos Ativos</span>
                                    <span class="detail-value">
                                        <?= $contatosAtivos?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <?php if (!$botStatus['ready']): ?>
                        <div class="qr-section">
                            <?php if ($qrCode): ?>
                            <img src="<?= $qrCode?>" alt="QR Code">
                            <p class="waiting-msg">Escaneie o QR Code<br>com o WhatsApp</p>
                            <?php
    else: ?>
                            <div style="padding: 2rem;">
                                <i class="fas fa-qrcode"
                                    style="font-size: 3rem; color: rgba(255,255,255,0.3); margin-bottom: 1rem;"></i>
                                <p class="waiting-msg">QR Code não disponível<br>Aguardando geração...</p>
                            </div>
                            <?php
    endif; ?>
                            <a href="<?= htmlspecialchars($apiConfig['base_url'])?>/qr" target="_blank"
                                class="btn btn-primary"
                                style="margin-top: 1rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-external-link-alt"></i> Abrir QR em nova aba
                            </a>
                        </div>
                        <?php
else: ?>
                        <div class="qr-section" style="background: rgba(16, 185, 129, 0.1);">
                            <i class="fas fa-check-circle"
                                style="font-size: 4rem; color: #10b981; margin-bottom: 1rem;"></i>
                            <p style="color: #10b981; font-weight: 600;">WhatsApp Conectado!</p>
                        </div>
                        <?php
endif; ?>
                    </div>

                    <!-- Estatísticas -->
                    <div>
                        <h3 class="section-title"><i class="fas fa-chart-bar"></i> Estatísticas de Notificações</h3>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon blue"><i class="fas fa-paper-plane"></i></div>
                                <div class="stat-value">
                                    <?= number_format($stats['total_enviadas'])?>
                                </div>
                                <div class="stat-label">Total Enviadas</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon purple"><i class="fas fa-calendar-day"></i></div>
                                <div class="stat-value">
                                    <?= number_format($stats['hoje'])?>
                                </div>
                                <div class="stat-label">Enviadas Hoje</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                                <div class="stat-value">
                                    <?= number_format($stats['sucesso'])?>
                                </div>
                                <div class="stat-label">Sucesso</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
                                <div class="stat-value">
                                    <?= number_format($stats['falhas'])?>
                                </div>
                                <div class="stat-label">Falhas</div>
                            </div>
                        </div>
                    </div>

                    <!-- Ações Rápidas -->
                    <div>
                        <h3 class="section-title"><i class="fas fa-bolt"></i> Ações Rápidas</h3>
                        <div class="quick-actions">
                            <a href="admin_bot_logs.php" class="action-card">
                                <div class="action-icon"><i class="fas fa-scroll"></i></div>
                                <div class="action-info">
                                    <h4>Ver Logs</h4>
                                    <p>Histórico de mensagens e erros</p>
                                </div>
                            </a>
                            <a href="admin_bot_config.php" class="action-card">
                                <div class="action-icon"><i class="fas fa-cog"></i></div>
                                <div class="action-info">
                                    <h4>Configurações</h4>
                                    <p>Ajustes do bot e automações</p>
                                </div>
                            </a>
                            <a href="admin_mensagens.php" class="action-card">
                                <div class="action-icon"><i class="fas fa-comment-dots"></i></div>
                                <div class="action-info">
                                    <h4>Templates de Mensagens</h4>
                                    <p>Personalizar textos enviados</p>
                                </div>
                            </a>
                            <a href="<?= htmlspecialchars($apiConfig['base_url'])?>/qr" target="_blank"
                                class="action-card">
                                <div class="action-icon"><i class="fas fa-qrcode"></i></div>
                                <div class="action-info">
                                    <h4>QR Code</h4>
                                    <p>Reconectar WhatsApp</p>
                                </div>
                            </a>
                        </div>
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
        });

        // Auto-refresh a cada 30 segundos se não estiver conectado
        <?php if (!$botStatus['ready']): ?>
            setTimeout(() => location.reload(), 30000);
        <?php
endif; ?>
    </script>
</body>

</html>