<?php
$file = 'write_test.txt';
$content = 'Testing write permissions at ' . date('Y-m-d H:i:s');
$res = @file_put_contents($file, $content);
if ($res !== false) {
    echo "SUCCESS: Wrote to $file (" . $res . " bytes)\n";
    @unlink($file);
}
else {
    $err = error_get_last();
    echo "FAILURE: Could not write to $file. Error: " . ($err['message'] ?? 'Unknown') . "\n";
}

$dir = 'config_test_dir';
if (@mkdir($dir)) {
    echo "SUCCESS: Created directory $dir\n";
    @rmdir($dir);
}
else {
    $err = error_get_last();
    echo "FAILURE: Could not create directory $dir. Error: " . ($err['message'] ?? 'Unknown') . "\n";
}
?>