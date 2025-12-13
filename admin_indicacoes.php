<?php
/**
 * Painel Admin - Gerenciamento de Indicações
 * Helmer Logistics
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Verificar login
session_start();
if (!isset($_SESSION['logado'])) {
    header('Location: admin.php');
    exit;
}

$message = '';
$messageType = '';

// Processar ações
if (isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'aprovar_indicacao':
                $id = sanitizeInput($_POST['id']);
                $sql = "UPDATE indicacoes SET status = 'confirmada' WHERE id = ?";
                executeQuery($pdo, $sql, [$id]);
                $message = "Indicação aprovada com sucesso!";
                $messageType = 'success';
                break;
                
            case 'rejeitar_indicacao':
                $id = sanitizeInput($_POST['id']);
                $sql = "UPDATE indicacoes SET status = 'pendente' WHERE id = ?";
                executeQuery($pdo, $sql, [$id]);
                $message = "Indicação rejeitada!";
                $messageType = 'warning';
                break;
                
            case 'marcar_entregue':
                $id = sanitizeInput($_POST['id']);
                $sql = "UPDATE indicacoes SET status = 'entregue' WHERE id = ?";
                executeQuery($pdo, $sql, [$id]);
                $message = "Entrega marcada como concluída!";
                $messageType = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = "Erro: " . $e->getMessage();
        $messageType = 'error';
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
    
    $rastreiosPrioritarios = fetchData($pdo, "SELECT rs.*, c.nome as nome_cliente, ci.nome as nome_indicador
                                              FROM rastreios_status rs
                                              LEFT JOIN clientes c ON rs.codigo = c.codigo
                                              LEFT JOIN clientes ci ON rs.codigo_indicador = ci.codigo
                                              WHERE rs.prioridade = TRUE
                                              ORDER BY rs.data_entrega_prevista ASC, rs.data ASC");
} catch (Exception $e) {
    $indicacoes = [];
    $stats = ['total_indicacoes' => 0, 'confirmadas' => 0, 'entregues' => 0];
    $rastreiosPrioritarios = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Indicações | Helmer Logistics</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            color: #f8fafc;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: #1e293b;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #334155;
        }
        
        .header h1 {
            color: #3b82f6;
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #1e293b;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #334155;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 5px;
            color: #3b82f6;
        }
        
        .stat-card p {
            color: #cbd5e1;
            font-size: 0.9rem;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            background: #334155;
            color: #cbd5e1;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            background: #3b82f6;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .table-container {
            background: #1e293b;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #334155;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #0f172a;
            color: #f8fafc;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #334155;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #334155;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-success {
            background: #10b981;
            color: white;
        }
        
        .badge-warning {
            background: #f59e0b;
            color: black;
        }
        
        .badge-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: black;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        
        .message.success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid #10b981;
            color: #10b981;
        }
        
        .message.warning {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid #f59e0b;
            color: #f59e0b;
        }
        
        .message.error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        
        .priority-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #10b981;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: #ef4444;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            z-index: 100;
        }
        
        .back-btn:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body>
    <button class="back-btn" onclick="window.location.href='admin.php'">
        <i class="fas fa-arrow-left"></i> Voltar ao Admin
    </button>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-users"></i> Gerenciamento de Indicações</h1>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= $stats['total_indicacoes'] ?></h3>
                <p>Total de Indicações</p>
            </div>
            <div class="stat-card">
                <h3><?= $stats['confirmadas'] ?></h3>
                <p>Confirmadas</p>
            </div>
            <div class="stat-card">
                <h3><?= $stats['entregues'] ?></h3>
                <p>Entregues</p>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('indicacoes')">
                <i class="fas fa-list"></i> Indicações
            </button>
            <button class="tab" onclick="showTab('prioritarios')">
                <i class="fas fa-star"></i> Rastreios Prioritários
            </button>
        </div>
        
        <!-- Tab: Indicações -->
        <div id="indicacoes" class="tab-content active">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Indicador</th>
                            <th>Indicado</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($indicacoes)): ?>
                            <?php foreach ($indicacoes as $indicacao): ?>
                            <tr>
                                <td><?= $indicacao['id'] ?></td>
                                <td>
                                    <strong><?= $indicacao['codigo_indicador'] ?></strong><br>
                                    <small><?= $indicacao['nome_indicador'] ?></small>
                                </td>
                                <td>
                                    <strong><?= $indicacao['codigo_indicado'] ?></strong><br>
                                    <small><?= $indicacao['nome_indicado'] ?></small>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($indicacao['data_indicacao'])) ?></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusText = '';
                                    switch ($indicacao['status']) {
                                        case 'pendente':
                                            $statusClass = 'badge-warning';
                                            $statusText = 'Pendente';
                                            break;
                                        case 'confirmada':
                                            $statusClass = 'badge-success';
                                            $statusText = 'Confirmada';
                                            break;
                                        case 'entregue':
                                            $statusClass = 'badge-success';
                                            $statusText = 'Entregue';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <?php if ($indicacao['prioridade']): ?>
                                        <span class="priority-indicator"></span>Prioritário
                                    <?php else: ?>
                                        Normal
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <?php if ($indicacao['status'] == 'pendente'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="aprovar_indicacao">
                                                <input type="hidden" name="id" value="<?= $indicacao['id'] ?>">
                                                <button type="submit" class="btn btn-success" title="Aprovar">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($indicacao['status'] == 'confirmada'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="marcar_entregue">
                                                <input type="hidden" name="id" value="<?= $indicacao['id'] ?>">
                                                <button type="submit" class="btn btn-success" title="Marcar como Entregue">
                                                    <i class="fas fa-truck"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="rejeitar_indicacao">
                                            <input type="hidden" name="id" value="<?= $indicacao['id'] ?>">
                                            <button type="submit" class="btn btn-danger" title="Rejeitar">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 20px; color: #cbd5e1;">
                                    <i class="fas fa-inbox"></i> Nenhuma indicação encontrada
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Tab: Rastreios Prioritários -->
        <div id="prioritarios" class="tab-content">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Indicador</th>
                            <th>Status</th>
                            <th>Data Entrega</th>
                            <th>Prioridade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rastreiosPrioritarios)): ?>
                            <?php foreach ($rastreiosPrioritarios as $rastreio): ?>
                            <tr>
                                <td><strong><?= $rastreio['codigo'] ?></strong></td>
                                <td><?= $rastreio['nome_cliente'] ?></td>
                                <td><?= $rastreio['nome_indicador'] ?></td>
                                <td><?= $rastreio['status_atual'] ?></td>
                                <td><?= date('d/m/Y', strtotime($rastreio['data_entrega_prevista'])) ?></td>
                                <td>
                                    <span class="priority-indicator"></span>Prioritário
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px; color: #cbd5e1;">
                                    <i class="fas fa-inbox"></i> Nenhum rastreio prioritário encontrado
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Esconder todas as tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remover active de todos os botões
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Mostrar tab selecionada
            document.getElementById(tabName).classList.add('active');
            
            // Adicionar active ao botão clicado
            event.target.classList.add('active');
        }
        
        // Auto-refresh removido - atualização manual apenas
    </script>
</body>
</html>