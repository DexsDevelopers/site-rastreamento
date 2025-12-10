<?php
/**
 * Teste específico para identificar o erro atual
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/whatsapp_helper.php';

$resultado = [
    'timestamp' => date('Y-m-d H:i:s'),
    'erros' => [],
    'testes' => []
];

try {
    // Teste 1: Configuração
    $resultado['testes'][] = '1. Verificando configuração...';
    $apiConfig = whatsappApiConfig();
    if (!$apiConfig['enabled']) {
        $resultado['erros'][] = 'API WhatsApp desabilitada';
    }
    
    // Teste 2: Token
    $resultado['testes'][] = '2. Verificando token...';
    $token = $apiConfig['token'] ?? '';
    if (empty($token)) {
        $resultado['erros'][] = 'Token não definido';
    }
    
    // Teste 3: URL
    $resultado['testes'][] = '3. Verificando URL...';
    $baseUrl = $apiConfig['base_url'] ?? '';
    if (empty($baseUrl)) {
        $resultado['erros'][] = 'URL não definida';
    }
    
    // Teste 4: Conexão com bot
    if (!empty($baseUrl) && !empty($token)) {
        $resultado['testes'][] = '4. Testando conexão com bot...';
        $statusUrl = rtrim($baseUrl, '/') . '/status';
        
        $ch = curl_init($statusUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-api-token: ' . $token,
                'ngrok-skip-browser-warning: true'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $resultado['teste_status'] = [
            'url' => $statusUrl,
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'response' => $response
        ];
        
        if ($httpCode === 401) {
            $resultado['erros'][] = 'ERRO 401: Token de autenticação inválido';
            $resultado['token_enviado'] = substr($token, 0, 4) . '***' . substr($token, -4);
        } elseif ($httpCode !== 200) {
            $resultado['erros'][] = "Erro HTTP $httpCode: " . ($curlError ?: $response);
        }
        
        // Teste 5: Tentar enviar mensagem
        if ($httpCode === 200) {
            $resultado['testes'][] = '5. Testando envio de mensagem...';
            $codigo = 'GH56YJ1474BR';
            $contato = getWhatsappContact($pdo, $codigo);
            
            if ($contato && !empty($contato['telefone_normalizado'])) {
                $telefone = $contato['telefone_normalizado'];
                $mensagem = 'Teste de envio';
                
                $endpoint = rtrim($baseUrl, '/') . '/send';
                $payload = json_encode([
                    'to' => $telefone,
                    'text' => $mensagem
                ], JSON_UNESCAPED_UNICODE);
                
                $ch2 = curl_init($endpoint);
                
                // Preparar headers - garantir que não há espaços extras
                $headers = [
                    'Content-Type: application/json',
                    'x-api-token: ' . trim($token),
                    'ngrok-skip-browser-warning: true'
                ];
                
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_TIMEOUT => 20,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);
                
                // Log dos headers sendo enviados (para debug)
                $resultado['debug_headers'] = $headers;
                $resultado['debug_token_length'] = strlen(trim($token));
                $resultado['debug_token_bytes'] = bin2hex(trim($token));
                
                $response2 = curl_exec($ch2);
                $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                $curlError2 = curl_error($ch2);
                curl_close($ch2);
                
                $resultado['teste_envio'] = [
                    'endpoint' => $endpoint,
                    'telefone' => $telefone,
                    'http_code' => $httpCode2,
                    'curl_error' => $curlError2,
                    'response' => $response2,
                    'response_parsed' => $response2 ? json_decode($response2, true) : null
                ];
                
                if ($httpCode2 === 401) {
                    $resultado['erros'][] = 'ERRO 401 ao enviar: Token inválido';
                } elseif ($httpCode2 !== 200) {
                    $resultado['erros'][] = "Erro HTTP $httpCode2 ao enviar: " . ($curlError2 ?: $response2);
                }
            }
        }
    }
    
    // Teste 6: Verificar última notificação no banco
    $resultado['testes'][] = '6. Verificando última notificação no banco...';
    $ultimaNotif = fetchOne($pdo, "SELECT sucesso, http_code, resposta_http, criado_em 
                                   FROM whatsapp_notificacoes 
                                   ORDER BY criado_em DESC 
                                   LIMIT 1");
    
    if ($ultimaNotif) {
        $resultado['ultima_notificacao'] = $ultimaNotif;
        if ((int)$ultimaNotif['sucesso'] !== 1) {
            $resposta = $ultimaNotif['resposta_http'] ?? '';
            $respostaParsed = $resposta ? json_decode($resposta, true) : null;
            if ($respostaParsed && isset($respostaParsed['error']) && $respostaParsed['error'] === 'unauthorized') {
                $resultado['erros'][] = 'Última notificação falhou com erro "unauthorized"';
            }
        }
    }
    
} catch (Throwable $e) {
    $resultado['erros'][] = 'Exceção: ' . $e->getMessage();
    $resultado['trace'] = $e->getTraceAsString();
}

$resultado['total_erros'] = count($resultado['erros']);
$resultado['status'] = count($resultado['erros']) === 0 ? 'OK' : 'ERRO';

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
