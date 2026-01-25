<?php
// Turn on error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug Info</h1>";
echo "<p>Server Time: " . date('Y-m-d H:i:s') . "</p>";

// Try to include config
try {
    if (file_exists('includes/config.php')) {
        require_once 'includes/config.php';
        require_once 'includes/db_connect.php';
        echo "<p>✅ Config loaded.</p>";
    } else {
        echo "<p>❌ Config NOT found.</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p>❌ Error loading config: " . $e->getMessage() . "</p>";
    exit;
}

$codigo = 'GH56YJ14108BR';
echo "<h2>Checking Code: $codigo</h2>";

try {
    // Check normalizeString
    if (function_exists('normalizeString')) {
        echo "<p>✅ normalizeString function EXISTS.</p>";
        echo "<p>Test: 'São Paulo' -> '" . normalizeString('São Paulo') . "'</p>";
    } else {
        echo "<p>❌ normalizeString function MISSING. Update not deployed!</p>";
    }

    // DB Checks
    $stmt = $pdo->prepare("SELECT DISTINCT cidade FROM rastreios_status WHERE codigo = ?");
    $stmt->execute([$codigo]);
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>DISTINCT Cities in DB:</h3>";
    if (empty($cities))
        echo "<p>None found.</p>";
    foreach ($cities as $c) {
        $norm = function_exists('normalizeString') ? normalizeString($c) : 'N/A';
        echo "<p>Raw: '$c' | Norm: '$norm'</p>";
    }

    $stmt = $pdo->prepare("SELECT id, cidade, data, titulo FROM rastreios_status WHERE codigo = ? ORDER BY data ASC");
    $stmt->execute([$codigo]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>All Rows (Time <= Now):</h3>";
    $validRows = [];
    foreach ($rows as $row) {
        if (strtotime($row['data']) <= time()) {
            $validRows[] = $row;
            $c = $row['cidade'];
            $norm = function_exists('normalizeString') ? normalizeString($c) : 'N/A';
            echo "<p>Date: {$row['data']} | City: '$c' (Norm: $norm) | Title: {$row['titulo']}</p>";
        } else {
            echo "<p style='color:gray'>Future: {$row['data']} | City: {$row['cidade']}</p>";
        }
    }

    if (!empty($validRows) && !empty($cities)) {
        $first = $validRows[0]['cidade'];
        $distinct = $cities[0];
        if (function_exists('normalizeString')) {
            $match = normalizeString($first) === normalizeString($distinct);
            echo "<h3>Comparison Result:</h3>";
            echo "<p>First Row ($first) vs Distinct ($distinct): " . ($match ? "MATCH ✅" : "MISMATCH ❌") . "</p>";
        }
    }

    // Check Pedidos Pendentes
    echo "<h2>Pedidos Pendentes Check</h2>";

    // Check Columns
    $stmt = $pdo->query("SHOW COLUMNS FROM pedidos_pendentes");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Columns: " . implode(', ', $cols) . "</p>";

    // Check Data
    $stmt = $pdo->query("SELECT id, nome, telefone, cidade, estado, status, codigo_rastreio FROM pedidos_pendentes ORDER BY id DESC LIMIT 5");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Last 5 Pedidos:</h3>";
    foreach ($orders as $o) {
        require_once 'includes/whatsapp_helper.php';
        $norm = normalizePhoneToDigits($o['telefone']);
        echo "<p>ID: {$o['id']} | Nome: '{$o['nome']}' | Tel Raw: '{$o['telefone']}' -> Norm: '{$norm}' | Status: {$o['status']}</p>";
        if ($norm === null) {
            echo "<p style='color:red'>⚠️ Phone normalization failed for '{$o['telefone']}'!</p>";
        }
    }
} catch (Exception $e) {
    echo "<p>❌ DB Error: " . $e->getMessage() . "</p>";
}
$logFile = __DIR__ . '/logs/system.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $last = array_slice($lines, -20);
    echo "<pre>" . htmlspecialchars(implode("", $last)) . "</pre>";
} else {
    echo "<p>Log file not found at $logFile</p>";
}
?>