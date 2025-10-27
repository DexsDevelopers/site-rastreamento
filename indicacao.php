<?php
/**
 * Sistema de Indicação - Helmer Logistics
 * Interface para clientes indicarem outros clientes
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/referral_system.php';

$referralSystem = new ReferralSystem($pdo);
$message = '';
$messageType = '';

// Processar indicação
if (isset($_POST['indicar'])) {
    $codigoIndicador = sanitizeInput($_POST['codigo_indicador']);
    $codigoIndicado = sanitizeInput($_POST['codigo_indicado']);
    $dadosIndicado = [
        'nome' => sanitizeInput($_POST['nome_indicado']),
        'telefone' => sanitizeInput($_POST['telefone_indicado']),
        'cidade' => sanitizeInput($_POST['cidade_indicado'])
    ];
    
    $result = $referralSystem->registrarIndicacao($codigoIndicador, $codigoIndicado, $dadosIndicado);
    
    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = $result['message'];
        $messageType = 'error';
    }
}

// Processar compra com indicação
if (isset($_POST['comprar_com_indicacao'])) {
    $codigoCliente = sanitizeInput($_POST['codigo_cliente']);
    $codigoIndicador = sanitizeInput($_POST['codigo_indicador']);
    $valor = floatval($_POST['valor']);
    $dadosRastreio = [
        'cidade' => sanitizeInput($_POST['cidade'])
    ];
    
    $result = $referralSystem->processarCompraComIndicacao($codigoCliente, $codigoIndicador, $valor, $dadosRastreio);
    
    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = $result['message'];
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Indicação - Helmer Logistics</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0a0a0a, #1a0000 80%);
            color: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            color: #ff3333;
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 0 0 20px #ff0000;
        }
        
        .header p {
            color: #bbb;
            font-size: 1.2rem;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .card {
            background: rgba(20, 0, 0, 0.9);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 0 20px #ff0000aa;
            border: 1px solid #ff3333;
        }
        
        .card h2 {
            color: #ff3333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #fff;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid transparent;
            border-radius: 10px;
            background: #111;
            color: #fff;
            font-size: 15px;
            transition: 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: #ff3333;
            box-shadow: 0 0 8px #ff3333;
            outline: none;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            transition: 0.3s;
            margin-top: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(90deg, #ff0000, #ff6600);
            color: #fff;
            box-shadow: 0 0 12px #ff3300aa;
        }
        
        .btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px #ff6600;
        }
        
        .btn-success {
            background: linear-gradient(90deg, #16a34a, #059669);
            color: #fff;
            box-shadow: 0 0 12px #16a34aaa;
        }
        
        .btn-success:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px #059669;
        }
        
        .benefits {
            background: rgba(20, 0, 0, 0.9);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 0 20px #ff0000aa;
            margin-bottom: 30px;
        }
        
        .benefits h2 {
            color: #ff3333;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .benefits-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .benefit-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(255, 51, 51, 0.1);
            border-radius: 10px;
            border-left: 4px solid #ff3333;
        }
        
        .benefit-item i {
            color: #ff3333;
            font-size: 24px;
        }
        
        .benefit-item div h3 {
            color: #fff;
            margin-bottom: 5px;
        }
        
        .benefit-item div p {
            color: #bbb;
            font-size: 14px;
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        
        .message.success {
            background: rgba(22, 163, 74, 0.2);
            border: 1px solid #16a34a;
            color: #16a34a;
        }
        
        .message.error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .stat-card {
            background: rgba(20, 0, 0, 0.9);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid #ff3333;
        }
        
        .stat-card h3 {
            color: #ff3333;
            font-size: 2rem;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #bbb;
            font-size: 14px;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .benefits-list {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-users"></i> Sistema de Indicação</h1>
            <p>Indique amigos e ganhe prioridade na entrega!</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <div class="benefits">
            <h2><i class="fas fa-gift"></i> Vantagens do Sistema de Indicação</h2>
            <div class="benefits-list">
                <div class="benefit-item">
                    <i class="fas fa-rocket"></i>
                    <div>
                        <h3>Entrega Prioritária</h3>
                        <p>Seu pedido será entregue em apenas 2 dias</p>
                    </div>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <h3>Processamento Rápido</h3>
                        <p>Seu pedido tem prioridade em todo o processo</p>
                    </div>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-star"></i>
                    <div>
                        <h3>Status VIP</h3>
                        <p>Seu rastreamento terá status especial</p>
                    </div>
                </div>
                <div class="benefit-item">
                    <i class="fas fa-heart"></i>
                    <div>
                        <h3>Indique e Ganhe</h3>
                        <p>Ajude seus amigos e ganhe benefícios</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="cards-grid">
            <!-- Formulário de Indicação -->
            <div class="card">
                <h2><i class="fas fa-user-plus"></i> Indicar Amigo</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="codigo_indicador">Seu Código de Cliente</label>
                        <input type="text" name="codigo_indicador" id="codigo_indicador" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="codigo_indicado">Código do Amigo</label>
                        <input type="text" name="codigo_indicado" id="codigo_indicado" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="nome_indicado">Nome do Amigo</label>
                        <input type="text" name="nome_indicado" id="nome_indicado" required>
                    </div>
                    
                    
                    <div class="form-group">
                        <label for="telefone_indicado">Telefone do Amigo</label>
                        <input type="tel" name="telefone_indicado" id="telefone_indicado">
                    </div>
                    
                    <div class="form-group">
                        <label for="cidade_indicado">Cidade do Amigo</label>
                        <input type="text" name="cidade_indicado" id="cidade_indicado" required>
                    </div>
                    
                    <button type="submit" name="indicar" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Indicar Amigo
                    </button>
                </form>
            </div>
            
            <!-- Formulário de Compra com Indicação -->
            <div class="card">
                <h2><i class="fas fa-shopping-cart"></i> Comprar com Indicação</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="codigo_cliente">Seu Código de Cliente</label>
                        <input type="text" name="codigo_cliente" id="codigo_cliente" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="codigo_indicador">Código de Quem Te Indicou</label>
                        <input type="text" name="codigo_indicador" id="codigo_indicador" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="valor">Valor da Compra</label>
                        <input type="number" name="valor" id="valor" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cidade">Sua Cidade</label>
                        <input type="text" name="cidade" id="cidade" required>
                    </div>
                    
                    <button type="submit" name="comprar_com_indicacao" class="btn btn-success">
                        <i class="fas fa-bolt"></i> Comprar com Prioridade
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats">
            <?php
            try {
                $stats = $referralSystem->getEstatisticasIndicacoes();
            ?>
            <div class="stat-card">
                <h3><?= $stats['total_indicacoes'] ?></h3>
                <p>Total de Indicações</p>
            </div>
            <div class="stat-card">
                <h3><?= $stats['confirmadas'] ?></h3>
                <p>Indicações Confirmadas</p>
            </div>
            <div class="stat-card">
                <h3><?= $stats['entregues'] ?></h3>
                <p>Entregues com Prioridade</p>
            </div>
            <div class="stat-card">
                <h3><?= round($stats['tempo_medio_entrega'], 1) ?> dias</h3>
                <p>Tempo Médio de Entrega</p>
            </div>
            <?php } catch (Exception $e) { ?>
            <div class="stat-card">
                <h3>0</h3>
                <p>Carregando estatísticas...</p>
            </div>
            <?php } ?>
        </div>
    </div>
    
    <script>
        // Validação de formulários
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const inputs = form.querySelectorAll('input[required]');
                    let valid = true;
                    
                    inputs.forEach(input => {
                        if (!input.value.trim()) {
                            valid = false;
                            input.style.borderColor = '#ef4444';
                        } else {
                            input.style.borderColor = 'transparent';
                        }
                    });
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('Por favor, preencha todos os campos obrigatórios.');
                    }
                });
            });
        });
    </script>
</body>
</html>
