<?php
require_once 'includes/config.php';
$res = setDynamicConfig('TEST_KEY', 'TEST_VALUE');
if ($res) {
    echo "SUCCESS: config_custom.json should be created/updated.\n";
    echo "Path: " . __DIR__ . "/config_custom.json\n";
}
else {
    echo "FAILURE: Could not save config.\n";
}
?>