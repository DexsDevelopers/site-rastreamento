<?php
/**
 * Verificador completo do pipeline de fotos.
 * Checa banco, tabela, permissões e configuracões PHP relacionadas ao upload.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/rastreio_media.php';

if (!isset($_SESSION['logado'])) {
    header('Location: admin.php');
    exit;
}

$checks = [];

function addCheck(array &$checks, string $titulo, string $status, string $detalhe, string $severity = 'success')
{
    $checks[] = [
        'titulo' => $titulo,
        'status' => $status,
        'detalhe' => $detalhe,
        'severity' => $severity
    ];
}

try {
    $pdo->query('SELECT 1');
    addCheck($checks, 'Conexão com banco', 'OK', 'Conectado e respondendo.', 'success');
} catch (Throwable $e) {
    addCheck($checks, 'Conexão com banco', 'Erro', 'Falha ao se conectar: ' . $e->getMessage(), 'danger');
}

$tableExists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'rastreios_midias'");
    $tableExists = $stmt->rowCount() > 0;
    if ($tableExists) {
        addCheck($checks, 'Tabela rastreios_midias', 'Encontrada', 'Estrutura detectada no banco.', 'success');
    } else {
        addCheck($checks, 'Tabela rastreios_midias', 'Ausente', 'Execute o CREATE TABLE para habilitar o recurso.', 'danger');
    }
} catch (Throwable $e) {
    addCheck($checks, 'Tabela rastreios_midias', 'Erro', 'Não foi possível verificar: ' . $e->getMessage(), 'danger');
}

if ($tableExists) {
    $expectedColumns = ['id','codigo','arquivo','tipo','legenda','criado_em','atualizado_em'];
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM rastreios_midias")->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_diff($expectedColumns, $cols);
        if (empty($missing)) {
            addCheck($checks, 'Colunas da tabela', 'OK', 'Todas as colunas necessárias estão presentes.', 'success');
        } else {
            addCheck($checks, 'Colunas da tabela', 'Incompleto', 'Colunas faltando: ' . implode(', ', $missing), 'danger');
        }
    } catch (Throwable $e) {
        addCheck($checks, 'Colunas da tabela', 'Erro', 'Falha ao listar colunas: ' . $e->getMessage(), 'danger');
    }
}

$uploadsDir = __DIR__ . '/uploads';
$rastreiosDir = $uploadsDir . '/rastreios';

if (is_dir($uploadsDir)) {
    $msg = 'Diretório existente';
    $severity = 'success';
} else {
    $msg = 'Criar diretório uploads (permissões 755).';
    $severity = 'danger';
}
addCheck($checks, 'Pasta uploads/', is_dir($uploadsDir) ? 'OK' : 'Ausente', $msg, $severity);

if (is_dir($rastreiosDir)) {
    $writable = is_writable($rastreiosDir);
    $msg = $writable ? 'Pronta para receber fotos.' : 'Sem permissão de escrita. Ajuste para 755.';
    addCheck($checks, 'Pasta uploads/rastreios/', $writable ? 'OK' : 'Sem permissão', $msg, $writable ? 'success' : 'danger');
} else {
    addCheck($checks, 'Pasta uploads/rastreios/', 'Ausente', 'Crie a subpasta para armazenar as fotos.', 'danger');
}

$testFile = $rastreiosDir . '/.diag_' . uniqid();
$writeTest = false;
if (is_dir($rastreiosDir) && is_writable($rastreiosDir)) {
    if (@file_put_contents($testFile, 'diagnostico') !== false) {
        $writeTest = true;
        @unlink($testFile);
    }
}
addCheck(
    $checks,
    'Teste de escrita no diretório',
    $writeTest ? 'OK' : 'Falhou',
    $writeTest ? 'PHP conseguiu criar e remover um arquivo de teste.' : 'Falha ao escrever na pasta. Verifique permissões.',
    $writeTest ? 'success' : 'danger'
);

$uploadsHtaccess = $uploadsDir . '/.htaccess';
if (file_exists($uploadsHtaccess)) {
    addCheck($checks, '.htaccess em uploads/', 'Encontrado', 'Proteção básica aplicada.', 'success');
} else {
    addCheck($checks, '.htaccess em uploads/', 'Faltando', 'Recomendo manter o arquivo para bloquear execução de scripts.', 'warning');
}

$phpUploadsEnabled = ini_get('file_uploads');
addCheck(
    $checks,
    'PHP file_uploads',
    $phpUploadsEnabled ? 'Habilitado' : 'Desabilitado',
    $phpUploadsEnabled ? 'Uploads permitidos no PHP.' : 'Ative file_uploads no php.ini.',
    $phpUploadsEnabled ? 'success' : 'danger'
);

$uploadMax = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');
addCheck($checks, 'upload_max_filesize', $uploadMax, 'Limite atual do PHP.', 'info');
addCheck($checks, 'post_max_size', $postMax, 'Tamanho máximo aceito no POST.', 'info');

$totalMidias = 0;
$midiasSemArquivo = 0;
if ($tableExists) {
    try {
        $totalMidias = (int) $pdo->query("SELECT COUNT(*) FROM rastreios_midias")->fetchColumn();
        addCheck($checks, 'Registros em rastreios_midias', (string) $totalMidias, 'Quantidade total de fotos cadastradas.', 'info');

        $query = "SELECT COUNT(*) FROM rastreios_midias WHERE arquivo IS NULL OR arquivo = ''";
        $midiasSemArquivo = (int) $pdo->query($query)->fetchColumn();
        if ($midiasSemArquivo > 0) {
            addCheck($checks, 'Registros sem caminho', (string) $midiasSemArquivo, 'Há mídias cadastradas sem caminho de arquivo.', 'warning');
        }
    } catch (Throwable $e) {
        addCheck($checks, 'Registros em rastreios_midias', 'Erro', 'Não foi possível contar: ' . $e->getMessage(), 'danger');
    }
}

$totalRastreios = (int) $pdo->query("SELECT COUNT(DISTINCT codigo) FROM rastreios_status")->fetchColumn();
$totalComFoto = 0;
if ($tableExists) {
    $totalComFoto = (int) $pdo->query("SELECT COUNT(*) FROM rastreios_midias")->fetchColumn();
}
$percentual = $totalRastreios > 0 ? round(($totalComFoto / $totalRastreios) * 100, 2) : 0;

$checksSummary = [
    'total_codigos' => $totalRastreios,
    'com_foto' => $totalComFoto,
    'percentual' => $percentual
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificador de Fotos - Helmer Logistics</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #050507;
            --card: #12121a;
            --border: rgba(255,255,255,0.08);
            --text: #f5f6ff;
            --muted: #9aa0c2;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #38bdf8;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, sans-serif;
            background: radial-gradient(circle at top, #141428, #050507);
            color: var(--text);
            min-height: 100vh;
            padding: 32px;
        }
        .wrap { max-width: 1100px; margin: 0 auto; }
        h1 { margin: 0 0 8px; font-size: 2rem; }
        .muted { color: var(--muted); }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 999px;
            text-decoration: none;
            color: #fff;
            background: linear-gradient(120deg, #ff3b5c, #ff7c3d);
            font-weight: 600;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin: 30px 0;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .card h3 { margin: 0; color: var(--muted); font-size: 0.95rem; }
        .card strong { display: block; margin-top: 6px; font-size: 1.8rem; }
        .checks {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .check-item {
            padding: 16px;
            background: rgba(255,255,255,0.03);
            border-radius: 14px;
            border: 1px solid var(--border);
        }
        .check-item h4 {
            margin: 0 0 4px;
            font-size: 1rem;
        }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }
        .status.success { color: var(--success); }
        .status.warning { color: var(--warning); }
        .status.danger { color: var(--danger); }
        .status.info { color: var(--info); }
        @media (max-width: 768px) {
            body { padding: 18px; }
            .top-bar { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top-bar">
        <div>
            <h1>Verificador de Fotos</h1>
            <div class="muted">Diagnóstico automático do pipeline de uploads.</div>
        </div>
        <a href="admin.php" class="btn"><i class="fas fa-arrow-left"></i> Voltar ao Painel</a>
    </div>

    <div class="cards">
        <div class="card">
            <h3>Códigos monitorados</h3>
            <strong><?= number_format($checksSummary['total_codigos']) ?></strong>
        </div>
        <div class="card">
            <h3>Códigos com foto</h3>
            <strong><?= number_format($checksSummary['com_foto']) ?></strong>
        </div>
        <div class="card">
            <h3>Conversão</h3>
            <strong><?= $checksSummary['percentual'] ?>%</strong>
        </div>
    </div>

    <div class="checks">
        <?php foreach ($checks as $check): ?>
            <div class="check-item">
                <h4><?= htmlspecialchars($check['titulo']) ?></h4>
                <div class="status <?= htmlspecialchars($check['severity']) ?>">
                    <i class="fas fa-circle"></i> <?= htmlspecialchars($check['status']) ?>
                </div>
                <p class="muted"><?= htmlspecialchars($check['detalhe']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>

