<?php
/**
 * Arquivo de Teste - Verificação do Git e Informações do Sistema
 * 
 * Este arquivo serve para testar o funcionamento do Git e exibir
 * informações úteis sobre o servidor e ambiente.
 */

// Informações do PHP
$php_version = phpversion();
$php_sapi = php_sapi_name();
$php_os = PHP_OS;
$php_loaded_extensions = get_loaded_extensions();

// Informações do servidor
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
$server_name = $_SERVER['SERVER_NAME'] ?? 'N/A';
$server_port = $_SERVER['SERVER_PORT'] ?? 'N/A';
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? 'N/A';
$script_filename = $_SERVER['SCRIPT_FILENAME'] ?? 'N/A';

// Informações de data/hora
$current_date = date('d/m/Y H:i:s');
$timezone = date_default_timezone_get();

// Informações de memória
$memory_limit = ini_get('memory_limit');
$memory_usage = memory_get_usage(true);
$memory_peak = memory_get_peak_usage(true);

// Informações do Git (se disponível)
$git_info = [];
if (function_exists('shell_exec')) {
    $git_branch = @shell_exec('git rev-parse --abbrev-ref HEAD 2>&1');
    $git_commit = @shell_exec('git rev-parse --short HEAD 2>&1');
    $git_date = @shell_exec('git log -1 --format=%ci 2>&1');
    
    $git_info = [
        'branch' => trim($git_branch) ?: 'N/A',
        'commit' => trim($git_commit) ?: 'N/A',
        'last_commit_date' => trim($git_date) ?: 'N/A'
    ];
} else {
    $git_info = [
        'branch' => 'shell_exec não disponível',
        'commit' => 'N/A',
        'last_commit_date' => 'N/A'
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Git - Informações do Sistema</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0A0A0A 0%, #1A0000 100%);
            color: #FFFFFF;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(20, 20, 20, 0.98) 100%);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 51, 51, 0.2);
            text-align: center;
        }
        
        .header h1 {
            color: #FF3333;
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 0 0 30px rgba(255, 51, 51, 0.6);
        }
        
        .header p {
            color: #cbd5e1;
            font-size: 1.1rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(20, 20, 20, 0.98) 100%);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 51, 51, 0.15);
        }
        
        .info-card h2 {
            color: #FF3333;
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255, 51, 51, 0.3);
        }
        
        .info-item {
            margin-bottom: 15px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            border-left: 3px solid #FF3333;
        }
        
        .info-item strong {
            color: #FF6666;
            display: block;
            margin-bottom: 5px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item span {
            color: #cbd5e1;
            font-size: 1rem;
            word-break: break-all;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .status-success {
            background: #16A34A;
            color: white;
        }
        
        .status-warning {
            background: #F59E0B;
            color: white;
        }
        
        .status-info {
            background: #06b6d4;
            color: white;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #cbd5e1;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.75rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Teste Git - Informações do Sistema</h1>
            <p>Verificação do funcionamento do Git e informações do ambiente</p>
        </div>
        
        <div class="info-grid">
            <!-- Informações do Git -->
            <div class="info-card">
                <h2>📦 Informações do Git</h2>
                <div class="info-item">
                    <strong>Branch Atual:</strong>
                    <span><?= htmlspecialchars($git_info['branch']) ?></span>
                    <?php if ($git_info['branch'] !== 'N/A' && $git_info['branch'] !== 'shell_exec não disponível'): ?>
                        <span class="status-badge status-success">✓ Ativo</span>
                    <?php endif; ?>
                </div>
                <div class="info-item">
                    <strong>Último Commit:</strong>
                    <span><?= htmlspecialchars($git_info['commit']) ?></span>
                </div>
                <div class="info-item">
                    <strong>Data do Commit:</strong>
                    <span><?= htmlspecialchars($git_info['last_commit_date']) ?></span>
                </div>
            </div>
            
            <!-- Informações do PHP -->
            <div class="info-card">
                <h2>🐘 Informações do PHP</h2>
                <div class="info-item">
                    <strong>Versão:</strong>
                    <span><?= htmlspecialchars($php_version) ?></span>
                    <span class="status-badge status-info">PHP <?= $php_version ?></span>
                </div>
                <div class="info-item">
                    <strong>SAPI:</strong>
                    <span><?= htmlspecialchars($php_sapi) ?></span>
                </div>
                <div class="info-item">
                    <strong>Sistema Operacional:</strong>
                    <span><?= htmlspecialchars($php_os) ?></span>
                </div>
                <div class="info-item">
                    <strong>Extensões Carregadas:</strong>
                    <span><?= count($php_loaded_extensions) ?> extensões</span>
                </div>
            </div>
            
            <!-- Informações do Servidor -->
            <div class="info-card">
                <h2>🖥️ Informações do Servidor</h2>
                <div class="info-item">
                    <strong>Software:</strong>
                    <span><?= htmlspecialchars($server_software) ?></span>
                </div>
                <div class="info-item">
                    <strong>Nome do Servidor:</strong>
                    <span><?= htmlspecialchars($server_name) ?></span>
                </div>
                <div class="info-item">
                    <strong>Porta:</strong>
                    <span><?= htmlspecialchars($server_port) ?></span>
                </div>
                <div class="info-item">
                    <strong>Document Root:</strong>
                    <span><?= htmlspecialchars($document_root) ?></span>
                </div>
            </div>
            
            <!-- Informações de Memória -->
            <div class="info-card">
                <h2>💾 Informações de Memória</h2>
                <div class="info-item">
                    <strong>Limite de Memória:</strong>
                    <span><?= htmlspecialchars($memory_limit) ?></span>
                </div>
                <div class="info-item">
                    <strong>Uso Atual:</strong>
                    <span><?= number_format($memory_usage / 1024 / 1024, 2) ?> MB</span>
                </div>
                <div class="info-item">
                    <strong>Pico de Uso:</strong>
                    <span><?= number_format($memory_peak / 1024 / 1024, 2) ?> MB</span>
                </div>
            </div>
            
            <!-- Informações de Data/Hora -->
            <div class="info-card">
                <h2>🕐 Data e Hora</h2>
                <div class="info-item">
                    <strong>Data/Hora Atual:</strong>
                    <span><?= htmlspecialchars($current_date) ?></span>
                </div>
                <div class="info-item">
                    <strong>Timezone:</strong>
                    <span><?= htmlspecialchars($timezone) ?></span>
                </div>
            </div>
            
            <!-- Status do Git -->
            <div class="info-card">
                <h2>✅ Status do Git</h2>
                <div class="info-item">
                    <strong>Status:</strong>
                    <?php if ($git_info['branch'] !== 'N/A' && $git_info['branch'] !== 'shell_exec não disponível'): ?>
                        <span class="status-badge status-success">✓ Git Funcionando</span>
                    <?php else: ?>
                        <span class="status-badge status-warning">⚠ Verificação Limitada</span>
                    <?php endif; ?>
                </div>
                <div class="info-item">
                    <strong>Observação:</strong>
                    <span>Este arquivo foi criado para testar o funcionamento do Git. Pode ser deletado após o teste.</span>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>Arquivo de teste criado em <?= $current_date ?></p>
            <p style="margin-top: 10px; color: #FF6666;">⚠ Este arquivo pode ser removido após a verificação</p>
        </div>
    </div>
</body>
</html>

