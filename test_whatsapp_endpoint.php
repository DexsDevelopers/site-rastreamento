<?php
/**
 * Endpoint de teste para envio manual de WhatsApp
 * Use este arquivo para testar diretamente o envio
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/whatsapp_helper.php';

header('Content-Type: application/json; charset=utf-8');

$codigo = isset($_GET['codigo']) ? $_GET['codigo'] : (isset($_POST['codigo']) ? $_POST['codigo'] : 'GH56YJ1474BR');

$resultado = [
    'codigo' => $codigo,
    'timestamp' => date('Y-m-d H:i:s'),
    'steps' => []
];

try {
    // Step 1: Verificar configuração
    $resultado['steps'][] = ['step' => 1, 'action' => 'Verificar configuração API'];
    $apiConfig = whatsappApiConfig();
    $resultado['api_config'] = [
        'enabled' => $apiConfig['enabled'],
        'base_url' => $apiConfig['base_url'],
        'token_defined' => !empty($apiConfig['token'])
    ];
    
    if (!$apiConfig['enabled']) {
        $resultado['error'] = 'API WhatsApp desabilitada';
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Step 2: Verificar contato
    $resultado['steps'][] = ['step' => 2, 'action' => 'Buscar contato'];
    $contato = getWhatsappContact($pdo, $codigo);
    
    if (!$contato) {
        $resultado['error'] = 'Contato não encontrado';
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $resultado['contato'] = [
        'nome' => $contato['nome'],
        'telefone' => $contato['telefone_normalizado'],
        'notificacoes_ativas' => (int) $contato['notificacoes_ativas']
    ];
    
    // Step 3: Verificar bot online
    $resultado['steps'][] = ['step' => 3, 'action' => 'Verificar bot online'];
    $statusUrl = $apiConfig['base_url'] . '/status';
    $statusCh = curl_init($statusUrl);
    curl_setopt_array($statusCh, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'x-api-token: ' . $apiConfig['token'],
            'ngrok-skip-browser-warning: true'
        ],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $statusResponse = curl_exec($statusCh);
    $statusHttpCode = curl_getinfo($statusCh, CURLINFO_HTTP_CODE);
    $statusError = curl_error($statusCh);
    curl_close($statusCh);
    
    $statusResponseData = null;
    if ($statusResponse) {
        $statusResponseData = json_decode($statusResponse, true);
    }
    
    $resultado['bot_status'] = [
        'http_code' => $statusHttpCode,
        'curl_error' => $statusError ?: null,
        'response' => $statusResponse,
        'response_parsed' => $statusResponseData,
        'token_used' => substr($apiConfig['token'], 0, 4) . '***' . substr($apiConfig['token'], -4) // Primeiros 4 e últimos 4 caracteres
    ];
    
    if ($statusHttpCode === 401) {
        $resultado['error'] = '❌ Erro de autenticação (401 Unauthorized)';
        $resultado['error_details'] = [
            'message' => 'O token enviado não corresponde ao token configurado no bot Node.js',
            'token_config_json' => substr($apiConfig['token'], 0, 4) . '***' . substr($apiConfig['token'], -4),
            'action_required' => 'Execute o script scripts/sync_whatsapp_token.ps1 para sincronizar o token do config.json para o .env do bot',
            'bot_response' => $statusResponseData
        ];
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($statusResponse === false || $statusHttpCode !== 200) {
        $resultado['error'] = "Bot não está acessível. HTTP: {$statusHttpCode}, Erro: {$statusError}";
        if ($statusResponseData && isset($statusResponseData['error'])) {
            $resultado['error'] .= " - " . $statusResponseData['error'];
        }
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $statusData = json_decode($statusResponse, true);
    $resultado['bot_data'] = $statusData;
    
    if (!$statusData || !isset($statusData['ready']) || !$statusData['ready']) {
        $resultado['error'] = 'Bot não está conectado ao WhatsApp';
        echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Step 4: Enviar mensagem
    $resultado['steps'][] = ['step' => 4, 'action' => 'Enviar notificação'];
    notifyWhatsappLatestStatus($pdo, $codigo);
    
    // Step 5: Verificar resultado
    $resultado['steps'][] = ['step' => 5, 'action' => 'Verificar resultado'];
    $ultimaNotif = fetchOne($pdo, "SELECT sucesso, http_code, resposta_http, enviado_em 
                                   FROM whatsapp_notificacoes 
                                   WHERE codigo = ? 
                                   ORDER BY criado_em DESC 
                                   LIMIT 1", [$codigo]);
    
    $resultado['ultima_notificacao'] = $ultimaNotif;
    $resultado['success'] = $ultimaNotif && (int) $ultimaNotif['sucesso'] === 1;
    
    if ($resultado['success']) {
        $resultado['message'] = '✅ Notificação enviada com sucesso!';
    } else {
        $resultado['message'] = '❌ Falha ao enviar notificação';
        if ($ultimaNotif) {
            $respostaHttp = $ultimaNotif['resposta_http'];
            $respostaParsed = null;
            if ($respostaHttp) {
                $respostaParsed = json_decode($respostaHttp, true);
            }
            
            $resultado['error_details'] = [
                'http_code' => $ultimaNotif['http_code'],
                'resposta_raw' => $respostaHttp,
                'resposta_parsed' => $respostaParsed
            ];
            
            // Detectar erro específico de autenticação
            if ((int)$ultimaNotif['http_code'] === 401) {
                $resultado['error_details']['auth_error'] = true;
                $resultado['error_details']['message'] = 'Token de autenticação inválido. Verifique se o token no config.json corresponde ao token no .env do bot.';
                $resultado['error_details']['solution'] = 'Execute: .\scripts\sync_whatsapp_token.ps1';
            } elseif ($respostaParsed && isset($respostaParsed['error']) && $respostaParsed['error'] === 'unauthorized') {
                $resultado['error_details']['auth_error'] = true;
                $resultado['error_details']['message'] = 'Erro de autenticação detectado na resposta da API.';
                $resultado['error_details']['solution'] = 'Execute: .\sync_whatsapp_token.ps1 para sincronizar o token';
            }
        }
    }
    
} catch (Exception $e) {
    $resultado['error'] = $e->getMessage();
    $resultado['trace'] = $e->getTraceAsString();
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>


