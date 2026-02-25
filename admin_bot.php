<?php
/**
 * Painel do Bot WhatsApp - Rastreamento PREMIUM ELITE
 * Interface de Comando Centralizada
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';
require_once 'includes/whatsapp_helper.php';

requireLogin();

$message = '';
$msgType = '';

// Ações via GET
if (isset($_GET['action'])) {
    $apiConfig = whatsappApiConfig();
    $action = $_GET['action'];

    if ($apiConfig['enabled']) {
        $endpoint = '';
        if ($action === 'reconnect') $endpoint = '/reconnect';
        if ($action === 'reset_loop') $endpoint = '/reset-loop';
        if ($action === 'logout') $endpoint = '/logout';

        if ($endpoint) {
            $ch = curl_init($apiConfig['base_url'] . $endpoint);
            $headers = [
                'Content-Type: application/json',
                'x-api-token: ' . $apiConfig['token'],
                'ngrok-skip-browser-warning: true'
            ];

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $message = "Comando '{$action}' enviado com sucesso!";
                $msgType = "success";
            } else {
                $message = "Erro ao enviar comando: HTTP $httpCode";
                $msgType = "danger";
            }
        }
    }
}

// Buscar Status Real do Bot
$apiConfig = whatsappApiConfig();
$botStatus = [
    'online' => false,
    'ready' => false,
    'uptimeFormatted' => '0h 0m 0s',
    'memoryMB' => 0,
    'reconnectAttempts' => 0,
    'jid' => ''
];

if ($apiConfig['enabled']) {
    $statusUrl = $apiConfig['base_url'] . '/status';
    $ch = curl_init($statusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'x-api-token: ' . $apiConfig['token'],
            'ngrok-skip-browser-warning: true'
        ],
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
                'uptimeFormatted' => $data['uptimeFormatted'] ?? '0h 0m 0s',
                'memoryMB' => round($data['memoryMB'] ?? 0, 1),
                'reconnectAttempts' => $data['reconnectAttempts'] ?? 0,
                'jid' => $data['jid'] ?? ''
            ]);
        }
    }
}

$qrCodeUrl = $apiConfig['base_url'] . '/qr';

// Contatos Ativos
$contatosAtivos = 0;
try {
    $res = $pdo->query("SELECT COUNT(*) FROM whatsapp_contatos WHERE notificacoes_ativas = 1");
    $contatosAtivos = $res->fetchColumn() ?: 0;
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comando Central | Bot WhatsApp</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.4);
            --bg-dark: #0f172a;
            --bg-card: rgba(30, 41, 59, 0.7);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border: rgba(255, 255, 255, 0.08);
            --glass: rgba(15, 23, 42, 0.85);
        }

        * { box-sizing: border-box; }
        body {
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            min-height: 100vh;
        }

        .layout { display: flex; min-height: 100vh; }

        /* Sidebar Elite */
        .sidebar {
            width: 280px;
            background: var(--glass);
            backdrop-filter: blur(25px);
            border-right: 1px solid var(--border);
            padding: 2.5rem 1.5rem;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
        }

        .brand {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 3.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: linear-gradient(135deg, #818cf8, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.875rem 1.25rem;
            color: var(--text-dim);
            text-decoration: none;
            border-radius: 14px;
            transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(99, 102, 241, 0.1);
            color: var(--text-main);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.1);
        }

        .nav-link.active i { color: var(--primary); }

        /* Main Content */
        .main {
            flex: 1;
            margin-left: 280px;
            padding: 3rem;
            max-width: 1400px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 3rem;
        }

        .header h1 { font-size: 2.25rem; font-weight: 800; margin: 0; letter-spacing: -0.04em; }
        .header p { color: var(--text-dim); margin: 0.5rem 0 0; font-size: 1.125rem; }

        /* Stats Grid */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .card-stat {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 1.75rem;
            backdrop-filter: blur(12px);
            transition: 0.3s;
        }

        .card-stat:hover { border-color: var(--primary); transform: translateY(-4px); }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .val { font-size: 1.75rem; font-weight: 800; display: block; margin-bottom: 0.25rem; }
        .lab { color: var(--text-dim); font-size: 0.875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }

        /* Hero Layout */
        .hero-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .glass-panel {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 32px;
            padding: 2.5rem;
            backdrop-filter: blur(15px);
        }

        /* Console */
        .console {
            background: #020617;
            border-radius: 20px;
            padding: 1.5rem;
            font-family: 'JetBrains Mono', monospace;
            height: 350px;
            overflow-y: auto;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: inset 0 4px 20px rgba(0,0,0,0.4);
        }

        .line { margin-bottom: 0.5rem; font-size: 0.9rem; border-left: 2px solid transparent; padding-left: 0.75rem; }
        .line.info { border-color: var(--primary); color: #94a3b8; }
        .line.sys { border-color: var(--success); color: var(--success); }
        .line.warn { border-color: var(--warning); color: var(--warning); }
        .line.err { border-color: var(--danger); color: var(--danger); }

        /* QR Frame */
        .qr-wrapper {
            background: white;
            border-radius: 28px;
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            color: #1e293b;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }

        .qr-placeholder {
            width: 250px;
            height: 250px;
            background: #f8fafc;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            border: 2px dashed #e2e8f0;
        }

        /* Buttons */
        .btn {
            padding: 0.875rem 1.5rem;
            border-radius: 16px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            transition: 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn-p { background: var(--primary); color: white; box-shadow: 0 8px 25px var(--primary-glow); }
        .btn-p:hover { transform: translateY(-2px); box-shadow: 0 12px 30px var(--primary-glow); }
        
        .btn-outline { background: rgba(255,255,255,0.03); color: white; border: 1px solid var(--border); }
        .btn-outline:hover { background: rgba(255,255,255,0.08); }

        .btn-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }
        .btn-danger:hover { background: var(--danger); color: white; }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .badge-on { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .badge-off { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

        @media (max-width: 1100px) {
            .sidebar { transform: translateX(-100%); transition: 0.3s; }
            .main { margin-left: 0; padding: 1.5rem; }
            .hero-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="brand">
                <i class="fas fa-robot"></i>
                <span>BOT PREMIUM</span>
            </div>
            <nav>
                <a href="admin_bot.php" class="nav-link active"><i class="fas fa-bolt"></i> Dashboard</a>
                <a href="admin.php" class="nav-link"><i class="fas fa-cube"></i> Rastreios</a>
                <a href="admin_pedidos_pendentes.php" class="nav-link"><i class="fas fa-shopping-cart"></i> Pedidos</a>
                <a href="admin_settings.php" class="nav-link"><i class="fas fa-sliders"></i> Ajustes</a>
            </nav>
            <div style="margin-top: auto;">
                <a href="admin.php?logout=1" class="nav-link" style="color: var(--danger);">
                    <i class="fas fa-sign-out-alt"></i> Sair do Painel
                </a>
            </div>
        </aside>

        <main class="main">
            <header class="header">
                <div>
                    <h1>Centro de Comando</h1>
                    <p>Sistemas ativos e monitoramento de rede</p>
                </div>
                <div style="display:flex; gap: 1rem; align-items:center;">
                    <div class="status-badge <?= $botStatus['ready'] ? 'badge-on' : 'badge-off' ?>">
                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                        <?= $botStatus['ready'] ? 'Sistema Online' : 'Aguardando Link' ?>
                    </div>
                    <button onclick="location.reload()" class="btn btn-outline" style="padding: 0.5rem 1rem;">
                        <i class="fas fa-sync"></i>
                    </button>
                </div>
            </header>

            <?php if ($message): ?>
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); padding: 1.25rem; border-radius: 18px; margin-bottom: 2.5rem; display: flex; align-items: center; gap: 1rem;">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="stats">
                <div class="card-stat">
                    <div class="stat-icon" style="background: rgba(99, 102, 241, 0.15); color: #818cf8;"><i class="fas fa-clock"></i></div>
                    <span class="val"><?= $botStatus['uptimeFormatted'] ?></span>
                    <span class="lab">Tempo Sincronizado</span>
                </div>
                <div class="card-stat">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;"><i class="fas fa-memory"></i></div>
                    <span class="val"><?= $botStatus['memoryMB'] ?> MB</span>
                    <span class="lab">Uso de Heap</span>
                </div>
                <div class="card-stat">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;"><i class="fas fa-users-viewfinder"></i></div>
                    <span class="val"><?= $contatosAtivos ?></span>
                    <span class="lab">Clientes Protegidos</span>
                </div>
                <div class="card-stat">
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #f87171;"><i class="fas fa-bug"></i></div>
                    <span class="val"><?= $botStatus['reconnectAttempts'] ?></span>
                    <span class="lab">Loops de Recup.</span>
                </div>
            </div>

            <div class="hero-grid">
                <div class="glass-panel">
                    <h3 style="margin: 0 0 2rem; display:flex; align-items:center; gap:0.75rem;">
                        <i class="fas fa-terminal" style="color: var(--primary);"></i> Monitor de Fluxo
                    </h3>
                    <div class="console" id="botConsole">
                        <div class="line info">> Estabelecendo conexão com núcleo do bot...</div>
                        <div class="line sys">> API: <?= $apiConfig['base_url'] ?> vinculada.</div>
                        <div class="line info">> Status atual: <?= $botStatus['ready'] ? 'OPERACIONAL' : 'OFFLINE' ?></div>
                    </div>
                    <div style="margin-top: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                        <button onclick="sendAction('reconnect')" class="btn btn-outline">
                            <i class="fas fa-plug-circle-bolt"></i> Reconectar Núcleo
                        </button>
                        <button onclick="sendAction('reset_loop')" class="btn btn-outline">
                            <i class="fas fa-rotate"></i> Resetar Memória
                        </button>
                        <button onclick="if(confirm('Hard Reset?')) sendAction('logout')" class="btn btn-danger" style="grid-column: span 2;">
                            <i class="fas fa-trash-can"></i> Limpeza Total de Sessão (Logout)
                        </button>
                    </div>
                </div>

                <div class="qr-col">
                    <?php if ($botStatus['ready']): ?>
                        <div class="glass-panel" style="text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%;">
                            <div style="width: 140px; height: 140px; background: rgba(16,185,129,0.1); border: 4px solid var(--success); border-radius: 50%; display:flex; align-items:center; justify-content:center; margin-bottom: 2rem; box-shadow: 0 0 30px rgba(16,185,129,0.2);">
                                <i class="fas fa-check" style="font-size: 4.5rem; color: var(--success);"></i>
                            </div>
                            <h2 style="margin: 0;">SISTEMA ATIVO</h2>
                            <p style="color: var(--text-dim); margin: 1rem 0 2rem;">Vinculação estável efetuada.</p>
                            <?php if ($botStatus['jid']): ?>
                                <a href="https://wa.me/<?= preg_replace('/\D/', '', $botStatus['jid']) ?>" target="_blank" class="btn btn-p">
                                    <i class="fab fa-whatsapp"></i> Testar Canal
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="qr-wrapper">
                            <h3 style="margin: 0 0 0.5rem;">Sincronizar Celular</h3>
                            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 2rem;">Aponte a câmera do WhatsApp</p>
                            
                            <div style="position: relative;">
                                <iframe id="qrFrame" src="<?= $qrCodeUrl ?>" style="width: 250px; height: 250px; border:none; border-radius: 20px;"></iframe>
                                <div id="qrLoader" class="qr-placeholder" style="display:none; position: absolute; inset: 0; background: white; z-index: 5;">
                                    <div style="width: 40px; height: 40px; border: 4px solid #f1f5f9; border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite;"></div>
                                    <span style="font-weight: 700; color: #94a3b8;">Gerando Link...</span>
                                </div>
                            </div>

                            <a href="<?= $qrCodeUrl ?>" target="_blank" class="btn btn-outline" style="margin-top: 2rem; width: 100%; color: #1e293b; border-color: #e2e8f0;">
                                <i class="fas fa-expand"></i> Tela Cheia
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function sendAction(act) {
            location.href = '?action=' + act;
        }

        function addLine(msg, type = 'info') {
            const console = document.getElementById('botConsole');
            const line = document.createElement('div');
            line.className = 'line ' + type;
            line.innerText = '> [' + new Date().toLocaleTimeString() + '] ' + msg;
            console.appendChild(line);
            console.scrollTop = console.scrollHeight;
        }

        // Simulação de atividade se o bot estiver pronto
        <?php if ($botStatus['ready']): ?>
            setInterval(() => {
                addLine('Pulso de vida: Ping OK', 'info');
            }, 10000);
        <?php endif; ?>

        // Refresh automático se não estiver logado para atualizar QR
        <?php if (!$botStatus['ready']): ?>
            setTimeout(() => location.reload(), 45000);
        <?php endif; ?>

        @keyframes spin { to { transform: rotate(360deg); } }
    </script>
</body>
</html>