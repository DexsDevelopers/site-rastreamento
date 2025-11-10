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
    $baseUrl = rtrim((string) getDynamicConfig('WHATSAPP_API_URL', ''), '/');
    $token = (string) getDynamicConfig('WHATSAPP_API_TOKEN', '');
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
    $template = (string) getDynamicConfig('WHATSAPP_TEMPLATE', "Olá {nome}! Seu pedido {codigo} foi atualizado:\n{status}\n{descricao}\nAtualizado em {data} às {hora}.\n{link}");
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
    $config = whatsappApiConfig();

    if (!$config['enabled']) {
        return [
            'success' => false,
            'error' => 'WhatsApp API desabilitada ou configuração ausente',
            'http_code' => null,
            'response' => null
        ];
    }

    $endpoint = $config['base_url'] . '/send';
    $payload = json_encode([
        'to' => $telefone,
        'text' => $mensagem
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-token: ' . $config['token']
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => false,
            'error' => $error,
            'http_code' => $httpCode ?: null,
            'response' => null
        ];
    }

    curl_close($ch);

    $success = $httpCode >= 200 && $httpCode < 300;

    return [
        'success' => $success,
        'error' => $success ? null : 'HTTP ' . $httpCode,
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
        return;
    }

    $contato = getWhatsappContact($pdo, $codigo);

    if (!$contato || (int) $contato['notificacoes_ativas'] !== 1 || empty($contato['telefone_normalizado'])) {
        return;
    }

    $status = fetchOne($pdo, "SELECT codigo, cidade, status_atual, titulo, subtitulo, data
                              FROM rastreios_status
                              WHERE codigo = ?
                              ORDER BY data DESC
                              LIMIT 1", [$codigo]);

    if (!$status) {
        return;
    }

    $statusKey = fetchOne($pdo, "SELECT id, sucesso FROM whatsapp_notificacoes WHERE codigo = ? AND status_titulo = ? AND status_data = ? LIMIT 1", [
        $status['codigo'],
        $status['status_atual'],
        $status['data']
    ]);

    if ($statusKey && (int) $statusKey['sucesso'] === 1) {
        return;
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

    $resultado = sendWhatsappMessage($contato['telefone_normalizado'], $mensagem);

    logWhatsappNotification($pdo, $statusData, $contato, $resultado, $mensagem);

    if (!$resultado['success']) {
        writeLog("Falha ao notificar WhatsApp para {$codigo}: " . ($resultado['error'] ?? 'Erro desconhecido'), 'ERROR');
    } else {
        writeLog("Notificação WhatsApp enviada para {$codigo}", 'INFO');
    }
}

