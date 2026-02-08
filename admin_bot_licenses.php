<?php
/**
 * Gerenciamento de Licenças de Grupos
 * Sistema de aluguel do bot para grupos WhatsApp
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';

// Verificar autenticação
requireLogin();

// Verificar se tabela existe
try {
    $pdo->query("SELECT 1 FROM bot_group_licenses LIMIT 1");
} catch (PDOException $e) {
    header('Location: setup_group_licenses.php');
    exit;
}

// Função para gerar key única
function generateLicenseKey() {
    $prefix = 'HLB'; // Helmer Logistics Bot
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $key = $prefix . '-';
    for ($i = 0; $i < 4; $i++) {
        if ($i > 0) $key .= '-';
        for ($j = 0; $j < 4; $j++) {
            $key .= $chars[random_int(0, strlen($chars) - 1)];
        }
    }
    return $key;
}

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'message' => 'Ação não reconhecida'];
    
    try {
        switch ($_POST['action']) {
            case 'create_license':
                $days = (int)($_POST['days'] ?? 30);
                $notes = sanitizeInput($_POST['notes'] ?? '');
                $quantity = (int)($_POST['quantity'] ?? 1);
                $quantity = min(max($quantity, 1), 50); // Entre 1 e 50
                
                $created = [];
                for ($i = 0; $i < $quantity; $i++) {
                    $key = generateLicenseKey();
                    // Garantir que key é única
                    while (fetchOne($pdo, "SELECT 1 FROM bot_group_licenses WHERE license_key = ?", [$key])) {
                        $key = generateLicenseKey();
                    }
                    
                    executeQuery($pdo, 
                        "INSERT INTO bot_group_licenses (license_key, days_purchased, notes, status) VALUES (?, ?, ?, 'pending')",
                        [$key, $days, $notes]
                    );
                    
                    $licenseId = $pdo->lastInsertId();
                    executeQuery($pdo,
                        "INSERT INTO bot_license_history (license_id, action, details) VALUES (?, 'created', ?)",
                        [$licenseId, "Licença criada com {$days} dias"]
                    );
                    
                    $created[] = $key;
                }
                
                $response = [
                    'success' => true, 
                    'message' => count($created) . ' licença(s) criada(s) com sucesso!',
                    'keys' => $created
                ];
                break;
                
            case 'activate_license':
                $key = strtoupper(trim($_POST['license_key'] ?? ''));
                $groupJid = trim($_POST['group_jid'] ?? '');
                $groupName = sanitizeInput($_POST['group_name'] ?? '');
                
                if (empty($key)) {
                    $response = ['success' => false, 'message' => 'Informe a chave da licença'];
                    break;
                }
                
                $license = fetchOne($pdo, "SELECT * FROM bot_group_licenses WHERE license_key = ?", [$key]);
                
                if (!$license) {
                    $response = ['success' => false, 'message' => 'Licença não encontrada'];
                    break;
                }
                
                if ($license['status'] === 'active' && $license['group_jid']) {
                    $response = ['success' => false, 'message' => 'Esta licença já está ativa em outro grupo'];
                    break;
                }
                
                if ($license['status'] === 'revoked') {
                    $response = ['success' => false, 'message' => 'Esta licença foi revogada'];
                    break;
                }
                
                $activatedAt = date('Y-m-d H:i:s');
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$license['days_purchased']} days"));
                
                executeQuery($pdo, 
                    "UPDATE bot_group_licenses SET group_jid = ?, group_name = ?, activated_at = ?, expires_at = ?, status = 'active' WHERE id = ?",
                    [$groupJid, $groupName, $activatedAt, $expiresAt, $license['id']]
                );
                
                executeQuery($pdo,
                    "INSERT INTO bot_license_history (license_id, action, group_jid, group_name, details) VALUES (?, 'activated', ?, ?, ?)",
                    [$license['id'], $groupJid, $groupName, "Ativado por {$license['days_purchased']} dias até {$expiresAt}"]
                );
                
                $response = [
                    'success' => true, 
                    'message' => "Licença ativada! Válida até " . date('d/m/Y H:i', strtotime($expiresAt)),
                    'expires_at' => $expiresAt
                ];
                break;
                
            case 'renew_license':
                $licenseId = (int)($_POST['license_id'] ?? 0);
                $extraDays = (int)($_POST['extra_days'] ?? 30);
                
                $license = fetchOne($pdo, "SELECT * FROM bot_group_licenses WHERE id = ?", [$licenseId]);
                
                if (!$license) {
                    $response = ['success' => false, 'message' => 'Licença não encontrada'];
                    break;
                }
                
                // Se expirada, renovar a partir de agora
                $baseDate = ($license['status'] === 'expired' || strtotime($license['expires_at']) < time()) 
                    ? time() 
                    : strtotime($license['expires_at']);
                
                $newExpires = date('Y-m-d H:i:s', strtotime("+{$extraDays} days", $baseDate));
                
                executeQuery($pdo, 
                    "UPDATE bot_group_licenses SET expires_at = ?, status = 'active', days_purchased = days_purchased + ? WHERE id = ?",
                    [$newExpires, $extraDays, $licenseId]
                );
                
                executeQuery($pdo,
                    "INSERT INTO bot_license_history (license_id, action, group_jid, group_name, details) VALUES (?, 'renewed', ?, ?, ?)",
                    [$licenseId, $license['group_jid'], $license['group_name'], "Renovado por mais {$extraDays} dias até {$newExpires}"]
                );
                
                $response = [
                    'success' => true, 
                    'message' => "Licença renovada! Nova validade: " . date('d/m/Y H:i', strtotime($newExpires))
                ];
                break;
                
            case 'revoke_license':
                $licenseId = (int)($_POST['license_id'] ?? 0);
                $reason = sanitizeInput($_POST['reason'] ?? 'Sem motivo especificado');
                
                $license = fetchOne($pdo, "SELECT * FROM bot_group_licenses WHERE id = ?", [$licenseId]);
                
                if (!$license) {
                    $response = ['success' => false, 'message' => 'Licença não encontrada'];
                    break;
                }
                
                executeQuery($pdo, 
                    "UPDATE bot_group_licenses SET status = 'revoked' WHERE id = ?",
                    [$licenseId]
                );
                
                executeQuery($pdo,
                    "INSERT INTO bot_license_history (license_id, action, group_jid, group_name, details) VALUES (?, 'revoked', ?, ?, ?)",
                    [$licenseId, $license['group_jid'], $license['group_name'], "Revogada: {$reason}"]
                );
                
                $response = ['success' => true, 'message' => 'Licença revogada com sucesso'];
                break;
                
            case 'delete_license':
                $licenseId = (int)($_POST['license_id'] ?? 0);
                
                executeQuery($pdo, "DELETE FROM bot_group_licenses WHERE id = ?", [$licenseId]);
                
                $response = ['success' => true, 'message' => 'Licença excluída'];
                break;
                
            case 'get_licenses':
                $status = $_POST['status'] ?? 'all';
                $search = sanitizeInput($_POST['search'] ?? '');
                
                $sql = "SELECT * FROM bot_group_licenses WHERE 1=1";
                $params = [];
                
                if ($status !== 'all') {
                    $sql .= " AND status = ?";
                    $params[] = $status;
                }
                
                if ($search) {
                    $sql .= " AND (license_key LIKE ? OR group_name LIKE ? OR group_jid LIKE ?)";
                    $params[] = "%{$search}%";
                    $params[] = "%{$search}%";
                    $params[] = "%{$search}%";
                }
                
                $sql .= " ORDER BY created_at DESC";
                
                $licenses = fetchData($pdo, $sql, $params);
                
                // Verificar e atualizar licenças expiradas
                foreach ($licenses as &$lic) {
                    if ($lic['status'] === 'active' && $lic['expires_at'] && strtotime($lic['expires_at']) < time()) {
                        executeQuery($pdo, "UPDATE bot_group_licenses SET status = 'expired' WHERE id = ?", [$lic['id']]);
                        $lic['status'] = 'expired';
                    }
                }
                
                $response = ['success' => true, 'data' => $licenses];
                break;
                
            case 'get_history':
                $licenseId = (int)($_POST['license_id'] ?? 0);
                
                $history = fetchData($pdo, 
                    "SELECT * FROM bot_license_history WHERE license_id = ? ORDER BY created_at DESC",
                    [$licenseId]
                );
                
                $response = ['success' => true, 'data' => $history];
                break;
                
            case 'get_plans':
                $plans = fetchData($pdo, "SELECT * FROM bot_license_plans WHERE is_active = 1 ORDER BY days ASC");
                $response = ['success' => true, 'data' => $plans];
                break;
                
            case 'check_license':
                $groupJid = trim($_POST['group_jid'] ?? '');
                
                $license = fetchOne($pdo, 
                    "SELECT * FROM bot_group_licenses WHERE group_jid = ? AND status = 'active' AND expires_at > NOW()",
                    [$groupJid]
                );
                
                if ($license) {
                    $daysLeft = ceil((strtotime($license['expires_at']) - time()) / 86400);
                    $response = [
                        'success' => true,
                        'valid' => true,
                        'license_key' => $license['license_key'],
                        'expires_at' => $license['expires_at'],
                        'days_left' => $daysLeft
                    ];
                } else {
                    $response = [
                        'success' => true,
                        'valid' => false,
                        'message' => 'Grupo sem licença válida'
                    ];
                }
                break;
                
            case 'get_stats':
                $stats = [
                    'total' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_group_licenses")['c'],
                    'active' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_group_licenses WHERE status = 'active' AND expires_at > NOW()")['c'],
                    'pending' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_group_licenses WHERE status = 'pending'")['c'],
                    'expired' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_group_licenses WHERE status = 'expired' OR (status = 'active' AND expires_at <= NOW())")['c'],
                    'expiring_soon' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_group_licenses WHERE status = 'active' AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)")['c']
                ];
                $response = ['success' => true, 'data' => $stats];
                break;
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

// Buscar estatísticas
$stats = [
    'total' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_group_licenses")['c'] ?? 0,
    'active' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_group_licenses WHERE status = 'active' AND expires_at > NOW()")['c'] ?? 0,
    'pending' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_group_licenses WHERE status = 'pending'")['c'] ?? 0,
    'expired' => fetchOne($pdo, "SELECT COUNT(*) as c FROM bot_group_licenses WHERE status = 'expired' OR (status = 'active' AND expires_at <= NOW())")['c'] ?? 0
];

$plans = fetchData($pdo, "SELECT * FROM bot_license_plans WHERE is_active = 1 ORDER BY days ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licenças de Grupos - Helmer Bot</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary: #0055FF;
            --primary-dark: #CC0000;
            --secondary: #FF6600;
            --dark: #0A0A0A;
            --dark-light: #1A1A1A;
            --light: #FFF;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gradient: linear-gradient(135deg, #180F33 0%, #FF6600 100%);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0A0A0A 0%, #1A0000 100%);
            color: var(--light);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header h1 {
            font-size: 1.8rem;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
        }
        
        .nav-link {
            padding: 0.6rem 1.2rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 85, 255, 0.2);
            border-radius: 8px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            background: rgba(0, 85, 255, 0.15);
            border-color: var(--primary);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 85, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 85, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--light);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(0, 85, 255, 0.2);
            border-radius: 8px;
            color: var(--light);
            font-size: 0.95rem;
            font-family: inherit;
        }
        
        .form-group select {
            background: #1a1a1a;
            cursor: pointer;
        }
        
        .form-group select option {
            background: #1a1a1a;
            color: #ffffff;
            padding: 10px;
            font-size: 0.95rem;
        }
        
        .form-group select option:hover,
        .form-group select option:checked {
            background: linear-gradient(135deg, #0055FF, #FF6600);
            color: #ffffff;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--gradient);
            color: var(--light);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 85, 255, 0.4);
        }
        
        .btn-success { background: var(--success); color: var(--light); }
        .btn-warning { background: var(--warning); color: var(--dark); }
        .btn-danger { background: var(--danger); color: var(--light); }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.85rem; }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        th {
            background: rgba(0, 85, 255, 0.1);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }
        
        .badge {
            display: inline-block;
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-active { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .badge-pending { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .badge-expired { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .badge-revoked { background: rgba(100, 100, 100, 0.2); color: #888; }
        
        .key-display {
            font-family: 'Courier New', monospace;
            background: rgba(0, 85, 255, 0.1);
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }
        
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-bar input, .filter-bar select {
            padding: 0.6rem 1rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(0, 85, 255, 0.2);
            border-radius: 8px;
            color: var(--light);
            font-size: 0.9rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal.active { display: flex; }
        
        .modal-content {
            background: linear-gradient(135deg, #1a1a1a, #0a0a0a);
            border: 1px solid rgba(0, 85, 255, 0.3);
            border-radius: 16px;
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }
        
        .keys-list {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .keys-list .key-item {
            font-family: 'Courier New', monospace;
            padding: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .keys-list .key-item:last-child { border-bottom: none; }
        
        .copy-btn {
            background: transparent;
            border: none;
            color: var(--primary);
            cursor: pointer;
            padding: 0.3rem;
        }
        
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            z-index: 2000;
            animation: slideIn 0.3s ease;
        }
        
        .toast-success { background: var(--success); }
        .toast-error { background: var(--danger); }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: flex-start; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            th, td { padding: 0.75rem 0.5rem; font-size: 0.85rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-key"></i> Licenças de Grupos</h1>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
                <a href="admin_bot_config.php" class="nav-link"><i class="fas fa-robot"></i> Config Bot</a>
            </div>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="stat-total"><?= $stats['total'] ?></div>
                <div class="stat-label">Total de Licenças</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-active"><?= $stats['active'] ?></div>
                <div class="stat-label">Ativas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-pending"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pendentes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-expired"><?= $stats['expired'] ?></div>
                <div class="stat-label">Expiradas</div>
            </div>
        </div>
        
        <!-- Criar Licença -->
        <div class="card">
            <h2 class="card-title"><i class="fas fa-plus-circle"></i> Criar Nova Licença</h2>
            <form id="createLicenseForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Plano / Dias</label>
                        <select name="days" id="licenseDays">
                            <?php foreach ($plans as $plan): ?>
                            <option value="<?= $plan['days'] ?>"><?= htmlspecialchars($plan['name']) ?> - <?= $plan['days'] ?> dias (R$ <?= number_format($plan['price'], 2, ',', '.') ?>)</option>
                            <?php endforeach; ?>
                            <option value="custom">Personalizado</option>
                        </select>
                    </div>
                    <div class="form-group" id="customDaysGroup" style="display:none;">
                        <label>Dias Personalizados</label>
                        <input type="number" name="custom_days" id="customDays" min="1" max="365" value="30">
                    </div>
                    <div class="form-group">
                        <label>Quantidade</label>
                        <input type="number" name="quantity" min="1" max="50" value="1">
                    </div>
                </div>
                <div class="form-group">
                    <label>Observações (opcional)</label>
                    <input type="text" name="notes" placeholder="Ex: Cliente João, Grupo XYZ">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-key"></i> Gerar Licença(s)
                </button>
            </form>
        </div>
        
        <!-- Lista de Licenças -->
        <div class="card">
            <h2 class="card-title"><i class="fas fa-list"></i> Licenças</h2>
            
            <div class="filter-bar">
                <input type="text" id="searchInput" placeholder="Buscar por key, grupo...">
                <select id="statusFilter">
                    <option value="all">Todos os Status</option>
                    <option value="active">Ativas</option>
                    <option value="pending">Pendentes</option>
                    <option value="expired">Expiradas</option>
                    <option value="revoked">Revogadas</option>
                </select>
                <button class="btn btn-primary btn-sm" onclick="loadLicenses()">
                    <i class="fas fa-search"></i> Filtrar
                </button>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Chave</th>
                            <th>Grupo</th>
                            <th>Dias</th>
                            <th>Status</th>
                            <th>Expira em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="licensesTable">
                        <tr><td colspan="6" style="text-align:center;">Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Ativar Licença -->
    <div class="modal" id="activateModal">
        <div class="modal-content">
            <h3 class="modal-title"><i class="fas fa-check-circle"></i> Ativar Licença</h3>
            <form id="activateForm">
                <input type="hidden" name="license_key" id="activateLicenseKey">
                <div class="form-group">
                    <label>Chave da Licença</label>
                    <input type="text" id="activateKeyDisplay" readonly class="key-display">
                </div>
                <div class="form-group">
                    <label>JID do Grupo (WhatsApp)</label>
                    <input type="text" name="group_jid" placeholder="Ex: 120363123456789012@g.us" required>
                </div>
                <div class="form-group">
                    <label>Nome do Grupo</label>
                    <input type="text" name="group_name" placeholder="Ex: Grupo de Vendas">
                </div>
                <div style="display:flex; gap:1rem; margin-top:1.5rem;">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Ativar</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('activateModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Renovar -->
    <div class="modal" id="renewModal">
        <div class="modal-content">
            <h3 class="modal-title"><i class="fas fa-sync"></i> Renovar Licença</h3>
            <form id="renewForm">
                <input type="hidden" name="license_id" id="renewLicenseId">
                <div class="form-group">
                    <label>Adicionar Dias</label>
                    <select name="extra_days">
                        <?php foreach ($plans as $plan): ?>
                        <option value="<?= $plan['days'] ?>"><?= $plan['days'] ?> dias (R$ <?= number_format($plan['price'], 2, ',', '.') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex; gap:1rem; margin-top:1.5rem;">
                    <button type="submit" class="btn btn-success"><i class="fas fa-sync"></i> Renovar</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('renewModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Keys Geradas -->
    <div class="modal" id="keysModal">
        <div class="modal-content">
            <h3 class="modal-title"><i class="fas fa-key"></i> Licenças Geradas</h3>
            <p style="color:rgba(255,255,255,0.7); margin-bottom:1rem;">Copie e guarde essas chaves com segurança:</p>
            <div class="keys-list" id="generatedKeysList"></div>
            <button type="button" class="btn btn-primary" onclick="copyAllKeys()" style="margin-top:1rem;">
                <i class="fas fa-copy"></i> Copiar Todas
            </button>
            <button type="button" class="btn btn-danger" onclick="closeModal('keysModal')" style="margin-top:1rem;">
                Fechar
            </button>
        </div>
    </div>

    <script>
        let generatedKeys = [];
        
        // Carregar licenças ao iniciar
        document.addEventListener('DOMContentLoaded', loadLicenses);
        
        // Toggle dias personalizados
        document.getElementById('licenseDays').addEventListener('change', function() {
            document.getElementById('customDaysGroup').style.display = this.value === 'custom' ? 'block' : 'none';
        });
        
        // Criar licença
        document.getElementById('createLicenseForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'create_license');
            
            let days = formData.get('days');
            if (days === 'custom') {
                days = formData.get('custom_days');
            }
            formData.set('days', days);
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    generatedKeys = data.keys;
                    showGeneratedKeys(data.keys);
                    this.reset();
                    loadLicenses();
                    loadStats();
                }
                showToast(data.message, data.success ? 'success' : 'error');
            } catch (e) {
                showToast('Erro ao criar licença', 'error');
            }
        });
        
        // Ativar licença
        document.getElementById('activateForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'activate_license');
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    closeModal('activateModal');
                    loadLicenses();
                    loadStats();
                }
                showToast(data.message, data.success ? 'success' : 'error');
            } catch (e) {
                showToast('Erro ao ativar licença', 'error');
            }
        });
        
        // Renovar licença
        document.getElementById('renewForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'renew_license');
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    closeModal('renewModal');
                    loadLicenses();
                    loadStats();
                }
                showToast(data.message, data.success ? 'success' : 'error');
            } catch (e) {
                showToast('Erro ao renovar licença', 'error');
            }
        });
        
        async function loadLicenses() {
            const formData = new FormData();
            formData.append('action', 'get_licenses');
            formData.append('status', document.getElementById('statusFilter').value);
            formData.append('search', document.getElementById('searchInput').value);
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    renderLicenses(data.data);
                }
            } catch (e) {
                console.error(e);
            }
        }
        
        function renderLicenses(licenses) {
            const tbody = document.getElementById('licensesTable');
            
            if (licenses.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:rgba(255,255,255,0.5);">Nenhuma licença encontrada</td></tr>';
                return;
            }
            
            tbody.innerHTML = licenses.map(lic => {
                const statusBadge = {
                    'active': '<span class="badge badge-active">Ativa</span>',
                    'pending': '<span class="badge badge-pending">Pendente</span>',
                    'expired': '<span class="badge badge-expired">Expirada</span>',
                    'revoked': '<span class="badge badge-revoked">Revogada</span>'
                }[lic.status] || lic.status;
                
                const expiresAt = lic.expires_at ? new Date(lic.expires_at).toLocaleDateString('pt-BR') : '-';
                const daysLeft = lic.expires_at ? Math.ceil((new Date(lic.expires_at) - new Date()) / 86400000) : '-';
                const daysDisplay = typeof daysLeft === 'number' ? (daysLeft > 0 ? `${daysLeft} dias` : 'Expirado') : '-';
                
                let actions = '';
                if (lic.status === 'pending') {
                    actions += `<button class="btn btn-success btn-sm" onclick="openActivateModal('${lic.license_key}')"><i class="fas fa-check"></i></button> `;
                }
                if (lic.status === 'active' || lic.status === 'expired') {
                    actions += `<button class="btn btn-warning btn-sm" onclick="openRenewModal(${lic.id})"><i class="fas fa-sync"></i></button> `;
                }
                if (lic.status !== 'revoked') {
                    actions += `<button class="btn btn-danger btn-sm" onclick="revokeLicense(${lic.id})"><i class="fas fa-ban"></i></button> `;
                }
                actions += `<button class="btn btn-sm" style="background:rgba(255,255,255,0.1)" onclick="copyKey('${lic.license_key}')"><i class="fas fa-copy"></i></button>`;
                
                return `<tr>
                    <td><span class="key-display">${lic.license_key}</span></td>
                    <td>${lic.group_name || '<span style="color:rgba(255,255,255,0.4)">Não ativado</span>'}</td>
                    <td>${lic.days_purchased}</td>
                    <td>${statusBadge}</td>
                    <td>${expiresAt}<br><small style="color:rgba(255,255,255,0.5)">${daysDisplay}</small></td>
                    <td>${actions}</td>
                </tr>`;
            }).join('');
        }
        
        async function loadStats() {
            const formData = new FormData();
            formData.append('action', 'get_stats');
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('stat-total').textContent = data.data.total;
                    document.getElementById('stat-active').textContent = data.data.active;
                    document.getElementById('stat-pending').textContent = data.data.pending;
                    document.getElementById('stat-expired').textContent = data.data.expired;
                }
            } catch (e) {}
        }
        
        function openActivateModal(key) {
            document.getElementById('activateLicenseKey').value = key;
            document.getElementById('activateKeyDisplay').value = key;
            document.getElementById('activateModal').classList.add('active');
        }
        
        function openRenewModal(id) {
            document.getElementById('renewLicenseId').value = id;
            document.getElementById('renewModal').classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        async function revokeLicense(id) {
            if (!confirm('Tem certeza que deseja revogar esta licença?')) return;
            
            const formData = new FormData();
            formData.append('action', 'revoke_license');
            formData.append('license_id', id);
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    loadLicenses();
                    loadStats();
                }
                showToast(data.message, data.success ? 'success' : 'error');
            } catch (e) {
                showToast('Erro ao revogar licença', 'error');
            }
        }
        
        function showGeneratedKeys(keys) {
            const list = document.getElementById('generatedKeysList');
            list.innerHTML = keys.map(key => `
                <div class="key-item">
                    <span>${key}</span>
                    <button class="copy-btn" onclick="copyKey('${key}')"><i class="fas fa-copy"></i></button>
                </div>
            `).join('');
            document.getElementById('keysModal').classList.add('active');
        }
        
        function copyKey(key) {
            navigator.clipboard.writeText(key);
            showToast('Chave copiada!', 'success');
        }
        
        function copyAllKeys() {
            navigator.clipboard.writeText(generatedKeys.join('\n'));
            showToast('Todas as chaves copiadas!', 'success');
        }
        
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        
        // Fechar modais ao clicar fora
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) closeModal(this.id);
            });
        });
    </script>
</body>
</html>

