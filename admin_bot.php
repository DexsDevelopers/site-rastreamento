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

// Processar ações
$message = '';
$msgType = '';

if (isset($_GET['action'])) {
    $apiConfig = whatsappApiConfig();
    $action = $_GET['action'];

    if ($apiConfig['enabled']) {
        $endpoint = '';
        if ($action === 'reconnect')
            $endpoint = '/reconnect';
        if ($action === 'reset_loop')
            $endpoint = '/reset-loop';
        if ($action === 'logout')
            $endpoint = '/logout';

        if ($endpoint) {
            $ch = curl_init($apiConfig['base_url'] . $endpoint);
            // Tentar com token configurado
            $headers = [
                'Content-Type: application/json',
                'x-api-token: ' . $apiConfig['token'],
                'ngrok-skip-browser-warning: true'
            ];

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $message = "Ação realizada com sucesso! Aguarde alguns segundos.";
                $msgType = "success";
            }
            else {
                // Tentar fallback com lucastav8012 se falhou auth
                if ($httpCode === 401 && $apiConfig['token'] !== 'lucastav8012') {
                    $ch2 = curl_init($apiConfig['base_url'] . $endpoint);
                    $headers['x-api-token'] = 'lucastav8012';
                    curl_setopt_array($ch2, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => $headers,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_SSL_VERIFYPEER => false
                    ]);
                    $response2 = curl_exec($ch2);
                    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    curl_close($ch2);

                    if ($httpCode2 >= 200 && $httpCode2 < 300) {
                        $message = "Ação realizada com sucesso (usando token padrão)!";
                        $msgType = "success";
                    }
                    else {
                        $message = "Erro ao executar ação: HTTP $httpCode";
                        $msgType = "error";
                    }
                }
                else {
                    $message = "Erro ao executar ação: HTTP $httpCode";
                    $msgType = "error";
                }
            }
        }
    }
}

// Buscar status do bot
$botStatus = [
    'online' => false,
    'ready' => false,
    'uptime' => 0,
    'uptimeFormatted' => '0h 0m 0s',
    'memoryMB' => 0,
    'reconnectAttempts' => 0
];

$qrCodeUrl = '';
$apiConfig = whatsappApiConfig();
$debugInfo = [];

if ($apiConfig['enabled']) {
    try {
        // Buscar status
        $statusUrl = $apiConfig['base_url'] . '/status';
        $debugInfo['status_url'] = $statusUrl;

        $ch = curl_init($statusUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'ngrok-skip-browser-warning: true',
                'x-api-token: ' . $apiConfig['token']
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $debugInfo['status_http'] = $httpCode;
        $debugInfo['status_error'] = $curlError;
        $debugInfo['status_response'] = substr($response, 0, 500);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if ($data) {
                $botStatus = array_merge($botStatus, [
                    'online' => true,
                    'ready' => $data['ready'] ?? false,
                    'uptime' => $data['uptime'] ?? 0,
                    'uptimeFormatted' => $data['uptimeFormatted'] ?? '0h 0m 0s',
                    'memoryMB' => round($data['memoryMB'] ?? 0, 1),
                    'reconnectAttempts' => $data['reconnectAttempts'] ?? 0
                ]);
            }
        }
    }
    catch (Exception $e) {
        $debugInfo['error'] = $e->getMessage();
    }
}

// URL do QR Code (sempre externo)
$qrCodeUrl = $apiConfig['base_url'] . '/qr';

// Estatísticas de RASTREAMENTO (usar bot_automation_logs para estatísticas reais)
$stats = [
    'total_automations' => 0,
    'active_automations' => 0,
    'total_usos' => 0,
    'logs_hoje' => 0
];

try {
    $result = fetchOne($pdo, "SELECT COUNT(*) as total FROM bot_automations");
    $stats['total_automations'] = $result['total'] ?? 0;

    $result = fetchOne($pdo, "SELECT COUNT(*) as total FROM bot_automations WHERE ativo = 1");
    $stats['active_automations'] = $result['total'] ?? 0;

    $result = fetchOne($pdo, "SELECT COALESCE(SUM(contador_uso), 0) as total FROM bot_automations");
    $stats['total_usos'] = $result['total'] ?? 0;

    $result = fetchOne($pdo, "SELECT COUNT(*) as total FROM bot_automation_logs WHERE DATE(criado_em) = CURDATE()");
    $stats['logs_hoje'] = $result['total'] ?? 0;
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

        .qr-frame {
            background: white;
            padding: 10px;
            border-radius: 12px;
        :root {
            --primary: #3b82f6;
            --primary-glow: rgba(59, 130, 246, 0.5);
            --bg-deep: #0f172a;
            --bg-card: rgba(30, 41, 59, 0.7);
            --bg-surface: #1e293b;
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border: rgba(255, 255, 255, 0.1);
            --glass: rgba(15, 23, 42, 0.8);
        }

        body {
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow-x: hidden;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Glassmorphism */
        .sidebar {
            width: 280px;
            background: var(--glass);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--border);
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 1000;
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 3rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: linear-gradient(to right, #60a5fa, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.85rem 1rem;
            color: var(--text-dim);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            margin-bottom: 0.5rem;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(59, 130, 246, 0.1);
            color: var(--text-main);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.1);
        }

        .nav-item.active i {
            color: var(--primary);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            max-width: 1400px;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
        }

        .header-title h2 {
            font-size: 1.875rem;
            font-weight: 700;
            margin: 0;
        }

        /* Dashboard Elite Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }

        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
        }

        .stat-label {
            color: var(--text-dim);
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Bot Status Hero */
        .bot-hero {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
            margin-bottom: 2.5rem;
        }

        .hero-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .qr-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: #ffffff;
            border-radius: 24px;
            padding: 2rem;
            color: #0f172a;
        }

        .qr-placeholder {
            width: 250px;
            height: 250px;
            background: #f1f5f9;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            border: 2px dashed #cbd5e1;
        }

        /* Buttons Elite */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 15px var(--primary-glow);
        }

        .btn-primary:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 20px var(--primary-glow);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-danger:hover {
            background: var(--danger);
            color: white;
        }

        /* Logs Section */
        .logs-window {
            background: #000000;
            border-radius: 16px;
            padding: 1.5rem;
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 0.85rem;
            color: #10b981;
            height: 400px;
            overflow-y: auto;
            border: 1px solid var(--border);
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
        }

        .log-entry { margin-bottom: 0.25rem; }
        .log-time { color: #64748b; margin-right: 0.5rem; }
        .log-info { color: #3b82f6; }
        .log-warn { color: #f59e0b; }
        .log-error { color: #ef4444; font-weight: bold; }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); transition: 0.3s; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .bot-hero { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <i class="fas fa-robot"></i>
                <span>BOT PREMIUM</span>
            </div>

            <nav>
                <a href="admin_bot.php" class="nav-item active"><i class="fas fa-gauge-high"></i> Dashboard</a>
                <a href="admin.php" class="nav-item"><i class="fas fa-box"></i> Rastreios</a>
                <a href="admin_settings.php" class="nav-item"><i class="fas fa-gears"></i> Configurações</a>
                <a href="admin_bot_logs.php" class="nav-item"><i class="fas fa-terminal"></i> Histórico Logs</a>
            </nav>

            <div style="margin-top: auto; padding-top: 2rem;">
                <a href="admin.php?logout=1" class="nav-item btn-danger">
                    <i class="fas fa-power-off"></i> Sair do Painel
                </a>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <div class="header-title">
                    <h2>Centro de Comando</h2>
                    <p style="color: var(--text-dim);">Gestão elite de inteligência e logística</p>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <a href="?action=reconnect" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Sincronizar Agora
                    </a>
                </div>
            </header>

                                </div>
                                <div class="stat-label">Automações</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                                <div class="stat-value">
                                    <?= number_format($stats['active_automations'])?>
                                </div>
                                <div class="stat-label">Ativas</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon purple"><i class="fas fa-play"></i></div>
                                <div class="stat-value">
                                    <?= number_format($stats['total_usos'])?>
                                </div>
                                <div class="stat-label">Usos Totais</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon orange"><i class="fas fa-calendar-day"></i></div>
                                <div class="stat-value">
                                    <?= number_format($stats['logs_hoje'])?>
                                </div>
                                <div class="stat-label">Logs Hoje</div>
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
                                    <p>Histórico de mensagens</p>
                                </div>
                            </a>
                            <a href="admin_mensagens.php" class="action-card">
                                <div class="action-icon"><i class="fas fa-comment-dots"></i></div>
                                <div class="action-info">
                                    <h4>Templates de Mensagens</h4>
                                    <p>Personalizar textos enviados</p>
                                </div>
                            </a>
                            <a href="<?= htmlspecialchars($qrCodeUrl)?>" target="_blank" class="action-card">
                                <div class="action-icon"><i class="fas fa-qrcode"></i></div>
                                <div class="action-info">
                                    <h4>QR Code</h4>
                                    <p>Reconectar WhatsApp</p>
                                </div>
                            </a>
                            <a href="admin.php" class="action-card">
                                <div class="action-icon"><i class="fas fa-truck"></i></div>
                                <div class="action-info">
                                    <h4>Rastreamentos</h4>
                                    <p>Gerenciar códigos e pedidos</p>
                                </div>
                            </a>
                        </div>
                    </div>

                </div>

                <!-- Debug info (hidden by default) -->
                <div class="debug-info" id="debugInfo">
                    <strong>Debug Info:</strong><br>
                    <pre><?= htmlspecialchars(json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))?></pre>
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

            // Show debug with ?debug=1
            if (window.location.search.includes('debug=1')) {
                document.getElementById('debugInfo').classList.add('show');
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