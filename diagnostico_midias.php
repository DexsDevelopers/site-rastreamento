<?php
/**
 * Página de diagnóstico para mídias (fotos de pedidos)
 * Permite identificar códigos sem foto, arquivos ausentes e arquivos órfãos no disco.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/rastreio_media.php';

if (!isset($_SESSION['logado'])) {
    header('Location: admin.php');
    exit;
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$diagnostics = [];
$referencedFiles = [];
$totalCodes = 0;
$comFoto = 0;
$semFoto = 0;
$arquivoFaltando = 0;
$caminhoInvalido = 0;

$codes = fetchData($pdo, "SELECT DISTINCT codigo FROM rastreios_status WHERE codigo IS NOT NULL AND codigo != '' ORDER BY codigo ASC");
$totalCodes = count($codes);

$stmtMedia = $pdo->prepare("SELECT arquivo, atualizado_em FROM rastreios_midias WHERE codigo = ? LIMIT 1");

foreach ($codes as $entry) {
    $codigo = $entry['codigo'];
    $registro = null;
    $stmtMedia->execute([$codigo]);
    $row = $stmtMedia->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $registro = $row;
        $referencedFiles[] = $row['arquivo'];
    }

    $status = 'Sem foto cadastrada';
    $severity = 'warning';
    $arquivo = $registro['arquivo'] ?? null;
    $absolutePath = null;
    $fileExists = false;
    $url = null;
    $issues = [];

    if ($arquivo) {
        $url = '/' . ltrim($arquivo, '/');
        try {
            $absolutePath = resolveRastreioAbsolutePath($arquivo);
            $fileExists = is_file($absolutePath);
            if ($fileExists) {
                $status = 'OK';
                $severity = 'success';
                $comFoto++;
            } else {
                $status = 'Arquivo ausente';
                $severity = 'danger';
                $arquivoFaltando++;
            }
        } catch (InvalidArgumentException $e) {
            $issues[] = 'Caminho inválido';
            $status = 'Caminho inválido';
            $severity = 'danger';
            $caminhoInvalido++;
        }
    } else {
        $semFoto++;
    }

    $diagnostics[] = [
        'codigo' => $codigo,
        'arquivo' => $arquivo,
        'url' => $url ? $baseUrl . $url : null,
        'status' => $status,
        'severity' => $severity,
        'file_exists' => $fileExists,
        'absolute' => $absolutePath,
        'updated_at' => $registro['atualizado_em'] ?? null,
        'issues' => $issues
    ];
}

$dir = ensureRastreioUploadsDir();
$filesOnDisk = [];
if (is_dir($dir)) {
    $scan = scandir($dir);
    foreach ($scan as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $full = $dir . $item;
        if (is_file($full)) {
            $filesOnDisk[] = $item;
        }
    }
}

$referencedSet = array_unique(array_filter($referencedFiles));
$orphans = [];
foreach ($filesOnDisk as $fileName) {
    $relativePath = rtrim(RASTREIO_UPLOAD_RELATIVE_DIR, '/') . '/' . $fileName;
    if (!in_array($relativePath, $referencedSet, true)) {
        $orphans[] = $fileName;
    }
}

$totalOrphans = count($orphans);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Fotos - Helmer Logistics</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #0b0b0f;
            --card: #14141c;
            --card-light: #1d1d27;
            --text: #f5f5ff;
            --muted: #a7a7c4;
            --primary: #ff3b5c;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border: rgba(255,255,255,0.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: radial-gradient(circle at top, #151520, #07070b);
            color: var(--text);
            min-height: 100vh;
            padding: 30px;
        }
        .wrap {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .muted { color: var(--muted); }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
            margin: 25px 0;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        }
        .card h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 500;
            color: var(--muted);
        }
        .card strong {
            display: block;
            font-size: 1.9rem;
            margin-top: 0.3rem;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-success { background: rgba(22,163,74,0.2); color: var(--success); }
        .status-warning { background: rgba(245,158,11,0.2); color: var(--warning); }
        .status-danger { background: rgba(239,68,68,0.2); color: var(--danger); }
        .table-wrap {
            border-radius: 18px;
            background: var(--card-light);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
        }
        thead {
            background: rgba(255,255,255,0.05);
        }
        th, td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            text-align: left;
        }
        tbody tr:hover {
            background: rgba(255,255,255,0.03);
        }
        .actions a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .issues {
            color: var(--danger);
            font-size: 0.82rem;
            margin-top: 4px;
        }
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .filters input {
            flex: 1;
            min-width: 220px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.04);
            color: var(--text);
        }
        .legend {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 0.85rem;
            margin-top: 10px;
        }
        .legend span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .legend i { font-size: 0.7rem; }
        .orphans {
            margin-top: 30px;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,59,92,0.08);
        }
        .orphans ul {
            margin: 10px 0 0;
            padding-left: 18px;
            color: var(--muted);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #ff3b5c, #ff6833);
            text-decoration: none;
        }
        .top-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        @media (max-width: 768px) {
            body { padding: 16px; }
            th, td { font-size: 0.82rem; padding: 10px; }
            .cards { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top-actions">
        <div>
            <h1>Diagnóstico de Fotos</h1>
            <div class="muted">Cheque inconsistências entre banco e arquivos físicos.</div>
        </div>
        <a class="btn" href="admin.php"><i class="fas fa-arrow-left"></i> Voltar para o Painel</a>
    </div>

    <div class="cards">
        <div class="card">
            <h3>Total de códigos rastreados</h3>
            <strong><?= number_format($totalCodes) ?></strong>
        </div>
        <div class="card">
            <h3>Fotos OK</h3>
            <strong><?= number_format($comFoto) ?></strong>
            <span class="status-badge status-success"><i class="fas fa-check-circle"></i> pronto</span>
        </div>
        <div class="card">
            <h3>Sem foto cadastrada</h3>
            <strong><?= number_format($semFoto) ?></strong>
            <span class="status-badge status-warning"><i class="fas fa-exclamation-triangle"></i> pendente</span>
        </div>
        <div class="card">
            <h3>Problemas detectados</h3>
            <strong><?= number_format($arquivoFaltando + $caminhoInvalido) ?></strong>
            <span class="status-badge status-danger"><i class="fas fa-bug"></i> atenção</span>
        </div>
    </div>

    <div class="filters">
        <input type="text" id="searchInput" placeholder="Buscar por código ou status..." oninput="filterTable()">
    </div>
    <div class="legend">
        <span><i class="fas fa-circle" style="color: var(--success);"></i> OK</span>
        <span><i class="fas fa-circle" style="color: var(--warning);"></i> Sem foto</span>
        <span><i class="fas fa-circle" style="color: var(--danger);"></i> Arquivo ausente / caminho inválido</span>
    </div>

    <div class="table-wrap">
        <table id="diagTable">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Status</th>
                    <th>Arquivo</th>
                    <th>Última atualização</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($diagnostics as $item): ?>
                <tr data-severity="<?= htmlspecialchars($item['severity']) ?>">
                    <td><strong><?= htmlspecialchars($item['codigo']) ?></strong></td>
                    <td>
                        <span class="status-badge status-<?= htmlspecialchars($item['severity']) ?>">
                            <?= htmlspecialchars($item['status']) ?>
                        </span>
                        <?php if (!empty($item['issues'])): ?>
                            <div class="issues"><?= htmlspecialchars(implode(', ', $item['issues'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($item['arquivo']): ?>
                            <div><?= htmlspecialchars($item['arquivo']) ?></div>
                            <?php if ($item['absolute']): ?>
                                <small class="muted"><?= htmlspecialchars($item['absolute']) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $item['updated_at'] ? date('d/m/Y H:i', strtotime($item['updated_at'])) : '—' ?></td>
                    <td class="actions">
                        <?php if ($item['url']): ?>
                            <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank"><i class="fas fa-external-link-alt"></i> Abrir</a>
                        <?php else: ?>
                            <span class="muted">Sem link</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="orphans">
        <strong>Arquivos órfãos no diretório (<?= $totalOrphans ?>)</strong>
        <p>Estes arquivos existem em <code><?= htmlspecialchars(rtrim(RASTREIO_UPLOAD_RELATIVE_DIR, '/')) ?>/</code>, mas não possuem registro no banco.</p>
        <?php if ($totalOrphans > 0): ?>
            <ul>
                <?php foreach ($orphans as $filename): ?>
                    <li><?= htmlspecialchars($filename) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">Nenhum órfão encontrado.</p>
        <?php endif; ?>
    </div>
</div>

<script>
function filterTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll('#diagTable tbody tr');
    rows.forEach(row => {
        const codigo = (row.cells[0].innerText || '').toLowerCase();
        const status = (row.cells[1].innerText || '').toLowerCase();
        if (codigo.includes(filter) || status.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
</body>
</html>

