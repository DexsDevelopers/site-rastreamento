<?php
/**
 * Helper de integração WhatsApp
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connect.php';

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

function normalizePhoneToDigits(?string $input): ?string {
    if ($input === null) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $input);

    if ($digits === '') {
        return null;
    }

    if (substr($digits, 0, 2) === '55' && strlen($digits) >= 12) {
        return $digits;
    }

    if (strlen($digits) === 11) {
        return '55' . $digits;
    }

    if (strlen($digits) >= 12) {
        return $digits;
    }

    return null;
}

function whatsappApiConfig(): array {
    // URL do Bot via Ngrok
    $baseUrl = rtrim((string) getDynamicConfig('WHATSAPP_API_URL', 'https://leola-formulable-iridescently.ngrok-free.dev'), '/');
    
    // Remover hacks de localhost/IP, usar URL direta
    // $baseUrl = str_replace(['localhost', '127.0.0.1'], '192.168.2.111', $baseUrl);
    
    $token = trim((string) getDynamicConfig('WHATSAPP_API_TOKEN', 'lucastav8012')); // Limpar espaços do token
    $enabled = filter_var(getDynamicConfig('WHATSAPP_API_ENABLED', true), FILTER_VALIDATE_BOOLEAN);

    return [
        'enabled' => $enabled && $baseUrl !== '' && $token !== '',
        'base_url' => $baseUrl,
        'token' => $token
    ];
}

function upsertWhatsappContact(PDO $pdo, string $codigo, ?string $nome, ?string $telefoneBruto, bool $notificar): void {
    $codigoKey = strtoupper(trim($codigo));
    $nomeFinal = $nome !== null && $nome !== '' ? trim($nome) : null;
    $telefoneOriginal = $telefoneBruto !== null && $telefoneBruto !== '' ? trim($telefoneBruto) : null;
    $telefoneNormalizado = normalizePhoneToDigits($telefoneOriginal);
    $notificacoesAtivas = $notificar && $telefoneNormalizado !== null ? 1 : 0;

    $sql = "INSERT INTO whatsapp_contatos (codigo, nome, telefone_original, telefone_normalizado, notificacoes_ativas)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nome = VALUES(nome),
                telefone_original = VALUES(telefone_original),
                telefone_normalizado = VALUES(telefone_normalizado),
                notificacoes_ativas = VALUES(notificacoes_ativas),
                atualizado_em = CURRENT_TIMESTAMP";

    executeQuery($pdo, $sql, [
        $codigoKey,
        $nomeFinal,
        $telefoneOriginal,
        $telefoneNormalizado,
        $notificacoesAtivas
    ]);
}

function getWhatsappContact(PDO $pdo, string $codigo): ?array {
    $sql = "SELECT id, codigo, nome, telefone_original, telefone_normalizado, notificacoes_ativas
            FROM whatsapp_contatos
            WHERE codigo = ?
            LIMIT 1";

    $row = fetchOne($pdo, $sql, [strtoupper(trim($codigo))]);

    return $row ?: null;
}

function deleteWhatsappContact(PDO $pdo, string $codigo): void {
    executeQuery($pdo, "DELETE FROM whatsapp_contatos WHERE codigo = ?", [strtoupper(trim($codigo))]);
}

function buildWhatsappTrackingLink(string $codigo): ?string {
    $tracking = getDynamicConfig('WHATSAPP_TRACKING_URL', '');

    if ($tracking === '') {
        return null;
    }

    if (str_contains($tracking, '{{codigo}}')) {
        return str_replace('{{codigo}}', urlencode($codigo), $tracking);
    }

    return rtrim($tracking, '/') . (str_contains($tracking, '?') ? '&' : '?') . 'codigo=' . urlencode($codigo);
}

function buildWhatsappMessage(array $statusData, array $contato): string {
    $nome = $contato['nome'] ?? '';
    $nome = $nome !== '' ? $nome : 'cliente';

    $dataHora = new DateTime($statusData['data'] ?? 'now');
    $dataFormatada = $dataHora->format('d/m/Y');
    $horaFormatada = $dataHora->format('H:i');

    $link = buildWhatsappTrackingLink($statusData['codigo']);
    if ($link) {
        $linkTexto = 'Acompanhe: ' . $link;
    } else {
        $linkTexto = '';
    }

    // Identificar a etapa baseado no título/status
    $titulo = $statusData['titulo'] ?? $statusData['status_atual'] ?? '';
    $etapaKey = null;
    $templateKey = null;
    
    if (strpos($titulo, 'Objeto postado') !== false || strpos($titulo, 'postado') !== false) {
        $etapaKey = 'postado';
        $templateKey = 'WHATSAPP_MSG_POSTADO';
    } elseif (strpos($titulo, 'Em trânsito') !== false || strpos($titulo, 'trânsito') !== false) {
        $etapaKey = 'transito';
        $templateKey = 'WHATSAPP_MSG_TRANSITO';
    } elseif (strpos($titulo, 'centro de distribuição') !== false || strpos($titulo, 'distribuição') !== false) {
        $etapaKey = 'distribuicao';
        $templateKey = 'WHATSAPP_MSG_DISTRIBUICAO';
    } elseif (strpos($titulo, 'Saiu para entrega') !== false || strpos($titulo, 'entrega') !== false) {
        $etapaKey = 'entrega';
        $templateKey = 'WHATSAPP_MSG_ENTREGA';
    } elseif (strpos($titulo, 'Entregue') !== false) {
        $etapaKey = 'entregue';
        $templateKey = 'WHATSAPP_MSG_ENTREGUE';
    }
    
    // Buscar template personalizado ou usar padrão
    $defaultTemplate = "Olá {nome}! Seu pedido {codigo} foi atualizado:\n{status}\n{descricao}\nAtualizado em {data} às {hora}.\n{link}";
    
    if ($templateKey) {
        $template = (string) getDynamicConfig($templateKey, $defaultTemplate);
    } else {
        $template = (string) getDynamicConfig('WHATSAPP_TEMPLATE', $defaultTemplate);
    }

    $replacements = [
        '{nome}' => $nome,
        '{codigo}' => $statusData['codigo'],
        '{status}' => $statusData['status_atual'],
        '{titulo}' => $statusData['titulo'],
        '{descricao}' => $statusData['subtitulo'] ?? '',
        '{cidade}' => $statusData['cidade'] ?? '',
        '{data}' => $dataFormatada,
        '{hora}' => $horaFormatada,
        '{link}' => $linkTexto
    ];

    $mensagem = strtr($template, $replacements);

    return trim(preg_replace("/\n{3,}/", "\n\n", $mensagem));
}

function sendWhatsappMessage(string $telefone, string $mensagem): array {
    if (!function_exists('curl_init')) {
        writeLog('Extensão cURL não disponível. Notificação WhatsApp não enviada.', 'ERROR');
        return [
            'success' => false,
            'error' => 'curl_extension_missing',
            'http_code' => null,
            'response' => null
        ];
    }

    $config = whatsappApiConfig();

    if (!$config['enabled']) {
        writeLog("WhatsApp API desabilitada. URL: {$config['base_url']}, Token: " . (empty($config['token']) ? 'VAZIO' : 'DEFINIDO'), 'WARNING');
        return [
            'success' => false,
            'error' => 'WhatsApp API desabilitada ou configuração ausente',
            'http_code' => null,
            'response' => null
        ];
    }

    // Validar token antes de enviar
    if (empty($config['token']) || $config['token'] === 'troque-este-token') {
        writeLog('Token do WhatsApp não configurado ou ainda está no valor padrão. Configure o token em config.json.', 'ERROR');
        return [
            'success' => false,
            'error' => 'token_not_configured',
            'error_message' => 'Token não configurado. Configure WHATSAPP_API_TOKEN em config.json',
            'http_code' => null,
            'response' => null
        ];
    }

    $endpoint = $config['base_url'] . '/send';
    $payload = json_encode([
        'to' => $telefone,
        'text' => $mensagem
    ], JSON_UNESCAPED_UNICODE);
    
    writeLog("Enviando WhatsApp para {$telefone} via {$endpoint}", 'INFO');

    $ch = curl_init($endpoint);
    if ($ch === false) {
        writeLog('Falha ao inicializar cURL para envio WhatsApp.', 'ERROR');
        return [
            'success' => false,
            'error' => 'curl_init_failed',
            'http_code' => null,
            'response' => null
        ];
    }

    // Garantir token limpo (sem espaços, sem caracteres invisíveis)
    $tokenClean = trim($config['token']);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-token: ' . $tokenClean,
            'ngrok-skip-browser-warning: true'  // Pular página de warning do ngrok
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false  // Para ngrok
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        
        writeLog("Erro cURL ao enviar WhatsApp para {$telefone}: {$error}", 'ERROR');

        return [
            'success' => false,
            'error' => $error,
            'http_code' => $httpCode ?: null,
            'response' => null
        ];
    }

    curl_close($ch);

    // Verificar se é erro de autenticação
    $responseData = json_decode($response, true);
    $isAuthError = $httpCode === 401 || ($responseData && isset($responseData['error']) && $responseData['error'] === 'unauthorized');
    
    if ($isAuthError) {
        writeLog("ERRO DE AUTENTICAÇÃO ao enviar WhatsApp para {$telefone}: Token inválido. Verifique se o token no config.json corresponde ao .env do bot.", 'ERROR');
        return [
            'success' => false,
            'error' => 'unauthorized',
            'error_message' => 'Token de autenticação inválido. Execute scripts/sync_whatsapp_token.ps1 para sincronizar.',
            'http_code' => $httpCode,
            'response' => $response
        ];
    }

    $success = $httpCode >= 200 && $httpCode < 300;
    
    if (!$success) {
        $errorMsg = 'HTTP ' . $httpCode;
        if ($responseData && isset($responseData['error'])) {
            $errorMsg .= ' - ' . $responseData['error'];
        }
        writeLog("Falha ao enviar WhatsApp para {$telefone}: {$errorMsg} - {$response}", 'ERROR');
    } else {
        writeLog("WhatsApp enviado com sucesso para {$telefone}: HTTP {$httpCode}", 'INFO');
    }

    return [
        'success' => $success,
        'error' => $success ? null : ($responseData['error'] ?? 'HTTP ' . $httpCode),
        'http_code' => $httpCode,
        'response' => $response
    ];
}

function logWhatsappNotification(PDO $pdo, array $statusData, array $contato, array $resultado, string $mensagem): void {
    $sql = "INSERT INTO whatsapp_notificacoes (codigo, status_titulo, status_subtitulo, status_data, telefone, mensagem, resposta_http, sucesso, http_code, enviado_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                telefone = VALUES(telefone),
                mensagem = VALUES(mensagem),
                resposta_http = VALUES(resposta_http),
                sucesso = VALUES(sucesso),
                http_code = VALUES(http_code),
                enviado_em = VALUES(enviado_em),
                atualizado_em = CURRENT_TIMESTAMP";

    $enviadoEm = $resultado['success'] ? date('Y-m-d H:i:s') : null;

    executeQuery($pdo, $sql, [
        $statusData['codigo'],
        $statusData['status_atual'],
        $statusData['subtitulo'] ?? null,
        $statusData['data'],
        $contato['telefone_normalizado'],
        $mensagem,
        $resultado['response'],
        $resultado['success'] ? 1 : 0,
        $resultado['http_code'],
        $enviadoEm
    ]);
}

function notifyWhatsappLatestStatus(PDO $pdo, string $codigo, array $options = []): void {
    $apiConfig = whatsappApiConfig();

    if (!$apiConfig['enabled']) {
        writeLog("notifyWhatsappLatestStatus: API desabilitada para código {$codigo}", 'WARNING');
        return;
    }

    $contato = getWhatsappContact($pdo, $codigo);

    if (!$contato || (int) $contato['notificacoes_ativas'] !== 1 || empty($contato['telefone_normalizado'])) {
        writeLog("notifyWhatsappLatestStatus: Contato não encontrado ou notificações desativadas para código {$codigo}", 'INFO');
        return;
    }
    
    writeLog("notifyWhatsappLatestStatus: Processando notificação para código {$codigo}, telefone {$contato['telefone_normalizado']}", 'INFO');

    $status = fetchOne($pdo, "SELECT codigo, cidade, status_atual, titulo, subtitulo, data
                              FROM rastreios_status
                              WHERE codigo = ?
                              ORDER BY data DESC
                              LIMIT 1", [$codigo]);

    if (!$status) {
        return;
    }

    // Se não for forçado, verificar se já foi enviado com sucesso
    $force = isset($options['force']) && $options['force'] === true;
    
    if (!$force) {
        $statusKey = fetchOne($pdo, "SELECT id, sucesso FROM whatsapp_notificacoes WHERE codigo = ? AND status_titulo = ? AND status_data = ? LIMIT 1", [
            $status['codigo'],
            $status['status_atual'],
            $status['data']
        ]);

        if ($statusKey && (int) $statusKey['sucesso'] === 1) {
            writeLog("notifyWhatsappLatestStatus: Notificação já enviada com sucesso para código {$codigo}, status {$status['status_atual']}. Pulando envio.", 'INFO');
            return;
        }
    } else {
        writeLog("notifyWhatsappLatestStatus: Envio forçado para código {$codigo}, ignorando verificação de duplicatas.", 'INFO');
    }

    $statusData = [
        'codigo' => $status['codigo'],
        'cidade' => $status['cidade'],
        'status_atual' => $status['status_atual'],
        'titulo' => $status['titulo'],
        'subtitulo' => $status['subtitulo'],
        'data' => $status['data']
    ];

    $mensagem = buildWhatsappMessage($statusData, $contato);

    try {
        $resultado = sendWhatsappMessage($contato['telefone_normalizado'], $mensagem);
    } catch (Throwable $th) {
        writeLog("Exceção ao enviar WhatsApp para {$codigo}: " . $th->getMessage(), 'ERROR');
        $resultado = [
            'success' => false,
            'error' => 'exception',
            'http_code' => null,
            'response' => null
        ];
    }

    logWhatsappNotification($pdo, $statusData, $contato, $resultado, $mensagem);

    if (!$resultado['success']) {
        writeLog("Falha ao notificar WhatsApp para {$codigo}: " . ($resultado['error'] ?? 'Erro desconhecido'), 'ERROR');
    } else {
        writeLog("Notificação WhatsApp enviada para {$codigo}", 'INFO');
    }
}

function notifyWhatsappTaxa(PDO $pdo, string $codigo, float $taxaValor, string $taxaPix): void {
    $apiConfig = whatsappApiConfig();

    if (!$apiConfig['enabled']) {
        return;
    }

    $contato = getWhatsappContact($pdo, $codigo);

    if (!$contato || (int) $contato['notificacoes_ativas'] !== 1 || empty($contato['telefone_normalizado'])) {
        return;
    }

    // Verificar se já notificou sobre esta taxa
    $taxaKey = fetchOne($pdo, "SELECT id, sucesso FROM whatsapp_notificacoes WHERE codigo = ? AND status_titulo = ? LIMIT 1", [
        $codigo,
        "TAXA_PENDENTE_" . number_format($taxaValor, 2, '.', '')
    ]);

    if ($taxaKey && (int) $taxaKey['sucesso'] === 1) {
        return;
    }

    $status = fetchOne($pdo, "SELECT codigo, cidade FROM rastreios_status WHERE codigo = ? ORDER BY data DESC LIMIT 1", [$codigo]);
    
    if (!$status) {
        return;
    }

    $nome = $contato['nome'] ?? 'cliente';
    $link = buildWhatsappTrackingLink($codigo);
    $linkTexto = $link ? "Acompanhe: {$link}" : '';
    
    // Buscar mensagem personalizada ou usar padrão
    $defaultTaxaMsg = "Olá {nome}!\n\n💰 *Taxa de distribuição nacional*\n\nSeu pedido *{codigo}* precisa de uma taxa de R$ {taxa_valor} para seguir para entrega.\n\nFaça o pagamento via PIX:\n`{taxa_pix}`\n\nApós o pagamento, a liberação acontece rapidamente e seu produto segue normalmente para o endereço informado.\n\n{link}";
    $template = (string) getDynamicConfig('WHATSAPP_MSG_TAXA', $defaultTaxaMsg);
    
    $replacements = [
        '{nome}' => $nome,
        '{codigo}' => $codigo,
        '{taxa_valor}' => number_format($taxaValor, 2, ',', '.'),
        '{taxa_pix}' => $taxaPix,
        '{cidade}' => $status['cidade'] ?? '',
        '{link}' => $linkTexto
    ];
    
    $mensagem = strtr($template, $replacements);

    try {
        $resultado = sendWhatsappMessage($contato['telefone_normalizado'], $mensagem);
    } catch (Throwable $th) {
        writeLog("Exceção ao enviar notificação de taxa para {$codigo}: " . $th->getMessage(), 'ERROR');
        $resultado = [
            'success' => false,
            'error' => 'exception',
            'http_code' => null,
            'response' => null
        ];
    }

    // Registrar notificação com tipo especial para taxa
    $statusData = [
        'codigo' => $status['codigo'],
        'cidade' => $status['cidade'],
        'status_atual' => "TAXA_PENDENTE_" . number_format($taxaValor, 2, '.', ''),
        'titulo' => "Taxa pendente",
        'subtitulo' => "Taxa de R$ " . number_format($taxaValor, 2, ',', '.'),
        'data' => date('Y-m-d H:i:s')
    ];

    logWhatsappNotification($pdo, $statusData, $contato, $resultado, $mensagem);

    if (!$resultado['success']) {
        writeLog("Falha ao notificar sobre taxa para {$codigo}: " . ($resultado['error'] ?? 'Erro desconhecido'), 'ERROR');
    } else {
        writeLog("Notificação de taxa enviada para {$codigo}", 'INFO');
    }
}

