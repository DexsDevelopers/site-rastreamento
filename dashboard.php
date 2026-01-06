<?php
/**
 * Dashboard Principal - Helmer Logistics
 * Escolha entre Painel de Rastreamento ou Painel do Bot
 */

session_start();

// Verificar se está logado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Helmer Logistics</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary: #FF3333;
            --primary-dark: #CC0000;
            --secondary: #FF6600;
            --dark: #0A0A0A;
            --dark-light: #1A1A1A;
            --light: #FFF;
            --gradient: linear-gradient(135deg, #FF0000 0%, #FF6600 100%);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0A0A0A 0%, #1A0000 100%);
            color: var(--light);
            min-height: 100vh;
            padding: 20px;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 51, 51, 0.2);
            border-radius: 20px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 900;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-name {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }
        
        .btn-logout {
            padding: 0.6rem 1.2rem;
            background: rgba(255, 51, 51, 0.15);
            border: 1px solid rgba(255, 51, 51, 0.3);
            border-radius: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-logout:hover {
            background: rgba(255, 51, 51, 0.25);
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .welcome-section {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .welcome-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .welcome-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
        }
        
        .panels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .panel-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 2px solid rgba(255, 51, 51, 0.2);
            border-radius: 24px;
            padding: 2.5rem;
            text-align: center;
            transition: all 0.4s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            overflow: hidden;
        }
        
        .panel-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .panel-card:hover {
            transform: translateY(-8px);
            border-color: var(--primary);
            box-shadow: 0 20px 60px rgba(255, 51, 51, 0.3);
        }
        
        .panel-card:hover::before {
            transform: scaleX(1);
        }
        
        .panel-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            box-shadow: 0 10px 30px rgba(255, 51, 51, 0.3);
        }
        
        .panel-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--light);
        }
        
        .panel-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .panel-features {
            text-align: left;
            margin-bottom: 1.5rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }
        
        .feature-item i {
            color: var(--primary);
            font-size: 0.8rem;
        }
        
        .btn-access {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--gradient);
            border: none;
            border-radius: 10px;
            color: var(--light);
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .btn-access:hover {
            transform: scale(1.05);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 51, 51, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .panels-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="logo">
                <i class="fas fa-shield-alt"></i> Helmer Logistics
            </div>
        </div>
        <div class="user-info">
            <span class="user-name">
                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($admin_username) ?>
            </span>
            <a href="?logout=1" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome-section">
            <h1 class="welcome-title">Bem-vindo ao Dashboard</h1>
            <p class="welcome-subtitle">Escolha qual painel você deseja acessar</p>
        </div>
        
        <div class="panels-grid">
            <!-- Painel de Rastreamento -->
            <a href="admin.php" class="panel-card">
                <div class="panel-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <h2 class="panel-title">Painel de Rastreamento</h2>
                <p class="panel-description">
                    Gerencie rastreamentos, pedidos, indicações e mídias dos clientes
                </p>
                <div class="panel-features">
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Criar e gerenciar rastreamentos</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Sistema de indicações</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Upload de fotos de pedidos</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Relatórios e estatísticas</span>
                    </div>
                </div>
                <button class="btn-access">
                    Acessar Painel <i class="fas fa-arrow-right"></i>
                </button>
            </a>
            
            <!-- Painel do Bot -->
            <a href="admin_bot_config.php" class="panel-card">
                <div class="panel-icon">
                    <i class="fas fa-robot"></i>
                </div>
                <h2 class="panel-title">Painel do Bot WhatsApp</h2>
                <p class="panel-description">
                    Configure bot WhatsApp, automações, contatos e envio de notificações
                </p>
                <div class="panel-features">
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Gerenciar contatos WhatsApp</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Automações e respostas</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Envio de notificações</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Templates e configurações</span>
                    </div>
                </div>
                <button class="btn-access">
                    Acessar Painel <i class="fas fa-arrow-right"></i>
                </button>
            </a>
        </div>
        
        <div class="stats-grid">
            <?php
            try {
                // Estatísticas rápidas
                $totalRastreios = fetchOne($pdo, "SELECT COUNT(*) as total FROM rastreios_status");
                $rastreiosHoje = fetchOne($pdo, "SELECT COUNT(DISTINCT codigo) as total FROM rastreios_status WHERE DATE(data) = CURDATE()");
                $totalWhatsapp = fetchOne($pdo, "SELECT COUNT(*) as total FROM whatsapp_contatos WHERE notificacoes_ativas = 1");
                $automacoes = fetchOne($pdo, "SELECT COUNT(*) as total FROM bot_automations WHERE ativo = 1");
            ?>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($totalRastreios['total'] ?? 0) ?></div>
                    <div class="stat-label">Total de Rastreios</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($rastreiosHoje['total'] ?? 0) ?></div>
                    <div class="stat-label">Rastreios Hoje</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($totalWhatsapp['total'] ?? 0) ?></div>
                    <div class="stat-label">Contatos Ativos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($automacoes['total'] ?? 0) ?></div>
                    <div class="stat-label">Automações Ativas</div>
                </div>
            <?php
            } catch (Exception $e) {
                // Silenciosamente falhar se não conseguir buscar stats
            }
            ?>
        </div>
    </div>
</body>
</html>

