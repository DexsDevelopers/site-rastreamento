<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

$codigo = 'GH56YJ14108BR';
echo "Checking code: $codigo\n";

try {
    // 1. Check DISTINCT city
    $stmt = $pdo->prepare("SELECT DISTINCT cidade FROM rastreios_status WHERE codigo = ?");
    $stmt->execute([$codigo]);
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "DISTINCT Cities found: " . implode(', ', $cities) . "\n";
    foreach ($cities as $c) {
        echo " - Raw: '$c' -> Norm: '" . normalizeString($c) . "'\n";
    }

    // 2. Check All Rows ordered by Data
    $stmt = $pdo->prepare("SELECT id, cidade, data, titulo FROM rastreios_status WHERE codigo = ? ORDER BY data ASC");
    $stmt->execute([$codigo]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nAll Rows (Ordered by Date ASC):\n";
    foreach ($rows as $i => $row) {
        $norm = normalizeString($row['cidade']);
        echo "Row #$i: Date={$row['data']} | City='{$row['cidade']}' (Norm: '$norm') | Title='{$row['titulo']}'\n";
    }

    // 3. Simulate Logic
    $validRows = [];
    foreach ($rows as $row) {
        if (strtotime($row['data']) <= time()) {
            $validRows[] = $row;
        }
    }

    if (empty($validRows)) {
        echo "\nNo valid rows (future dates?)\n";
    } else {
        $firstRow = $validRows[0];
        echo "\nFirst Valid Row: City='{$firstRow['cidade']}'\n";

        // Simulation
        $targetCity = $cities[0] ?? ''; // Assuming first distinct found
        $match = normalizeString($firstRow['cidade']) === normalizeString($targetCity);
        echo "Match Test with '{$targetCity}': " . ($match ? "PASS" : "FAIL") . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
