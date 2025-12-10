<?php
/**
 * Verificar comprimento exato do token
 */

require_once 'includes/config.php';
require_once 'includes/whatsapp_helper.php';

header('Content-Type: application/json; charset=utf-8');

$apiConfig = whatsappApiConfig();
$token = $apiConfig['token'];

$resultado = [
    'token_config_json' => $token,
    'comprimento' => strlen($token),
    'bytes' => array_map('ord', str_split($token)),
    'hex' => bin2hex($token),
    'trimmed' => trim($token),
    'comprimento_trimmed' => strlen(trim($token)),
    'bytes_trimmed' => array_map('ord', str_split(trim($token))),
    'hex_trimmed' => bin2hex(trim($token)),
    'tem_espacos' => $token !== trim($token),
    'caracteres_especiais' => []
];

// Verificar cada caractere
for ($i = 0; $i < strlen($token); $i++) {
    $char = $token[$i];
    $code = ord($char);
    if ($code < 32 || $code > 126) {
        $resultado['caracteres_especiais'][] = [
            'posicao' => $i,
            'char' => $char,
            'code' => $code,
            'hex' => dechex($code),
            'tipo' => $code < 32 ? 'controle' : 'unicode'
        ];
    }
}

// Verificar se é exatamente "lucastav8012"
$tokenEsperado = 'lucastav8012';
$resultado['comparacao'] = [
    'esperado' => $tokenEsperado,
    'comprimento_esperado' => strlen($tokenEsperado),
    'igual' => trim($token) === $tokenEsperado,
    'igual_sem_trim' => $token === $tokenEsperado
];

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
