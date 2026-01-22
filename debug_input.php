<?php
header('Content-Type: application/json');
$raw = file_get_contents('php://input');
echo json_encode([
    'raw_input' => $raw,
    'post' => $_POST,
    'json_error' => json_last_error_msg(),
    'headers' => getallheaders()
]);
