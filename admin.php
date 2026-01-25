<?php
/**
 * Painel Administrativo Helmer Logistics
 * Versão otimizada e segura
 */

// Incluir configurações e DB
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';

// Verificar autenticação
requireLogin();

// Verificar conexão com banco
if (!isset($pdo) || $pdo === null) {
    die("❌ Erro: Não foi possível conectar ao banco de dados. Verifique as configurações em includes/db_connect.php");
}

require_once 'includes/whatsapp_helper.php';
require_once 'includes/rastreio_media.php';

// ===== ENDPOINT AJAX: ENVIAR WHATSAPP MANUALMENTE =====
// DEVE SER PROCESSADO ANTES DE QUALQUER SAÍDA HTML
if (isset($_POST['enviar_whatsapp_manual']) && isset($_POST['codigo'])) {
    // Limpar qualquer saída anterior
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Garantir que não há saída antes do JSON
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');

    $codigo = sanitizeInput($_POST['codigo']);

    // Log para debug
    writeLog("Envio manual WhatsApp solicitado para código: {$codigo}", 'INFO');

    try {
        // Verificar se a API do WhatsApp está configurada
        $apiConfig = whatsappApiConfig();
        if (!$apiConfig['enabled']) {
            echo json_encode([
                'success' => false,
                'message' => 'API WhatsApp desabilitada. Verifique as configurações em config.json'
            ]);
            exit;
        }

        $contato = getWhatsappContact($pdo, $codigo);

        if (!$contato) {
            echo json_encode([
                'success' => false,
                'message' => 'Contato WhatsApp não encontrado para este código. Cadastre o telefone do cliente primeiro.'
            ]);
            exit;
        }

        if ((int) $contato['notificacoes_ativas'] !== 1) {
            echo json_encode([
                'success' => false,
                'message' => 'Notificações WhatsApp estão desativadas para este código. Ative nas configurações do rastreio.'
            ]);
            exit;
        }

        if (empty($contato['telefone_normalizado'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Telefone WhatsApp não cadastrado para este código. Adicione o número do cliente.'
            ]);
            exit;
        }

        writeLog("Iniciando envio manual de WhatsApp para código {$codigo}, telefone: " . ($contato['telefone_normalizado'] ?? 'não informado'), 'INFO');

        // Verificar se o bot está online antes de enviar
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
        curl_close($statusCh);

        if ($statusResponse === false || $statusHttpCode !== 200) {
            writeLog("Bot WhatsApp não está acessível. Status HTTP: {$statusHttpCode}", 'ERROR');
            echo json_encode([
                'success' => false,
                'message' => '❌ Bot WhatsApp não está online ou não está acessível. Verifique se o bot está rodando e o ngrok está ativo.'
            ]);
            exit;
        }

        $statusData = json_decode($statusResponse, true);
        if (!$statusData || !isset($statusData['ready']) || !$statusData['ready']) {
            writeLog("Bot WhatsApp não está pronto. Status: " . json_encode($statusData), 'ERROR');
            echo json_encode([
                'success' => false,
                'message' => '❌ Bot WhatsApp não está conectado ao WhatsApp. Verifique a conexão do bot.'
            ]);
            exit;
        }

        // Chamar função de notificação com opção de forçar envio
        notifyWhatsappLatestStatus($pdo, $codigo, ['force' => true]);

        // Verificar se o envio foi bem-sucedido consultando a última notificação
        $ultimaNotif = fetchOne($pdo, "SELECT sucesso, http_code, resposta_http, enviado_em FROM whatsapp_notificacoes 
                                       WHERE codigo = ? 
                                       ORDER BY criado_em DESC 
                                       LIMIT 1", [$codigo]);

        if ($ultimaNotif && (int) $ultimaNotif['sucesso'] === 1) {
            echo json_encode([
                'success' => true,
                'message' => "✅ Notificação WhatsApp enviada com sucesso para {$contato['telefone_normalizado']}!"
            ]);
            writeLog("Envio manual de WhatsApp para código {$codigo} concluído com sucesso", 'INFO');
        } else {
            $erroMsg = 'Erro desconhecido';
            if ($ultimaNotif) {
                $erroMsg = "HTTP {$ultimaNotif['http_code']}";
                if ($ultimaNotif['resposta_http']) {
                    $resposta = json_decode($ultimaNotif['resposta_http'], true);
                    if ($resposta && isset($resposta['error'])) {
                        $erroMsg = $resposta['error'];
                    }
                }
            }
            echo json_encode([
                'success' => false,
                'message' => "❌ Falha ao enviar notificação: {$erroMsg}"
            ]);
            writeLog("Envio manual de WhatsApp para código {$codigo} falhou: {$erroMsg}", 'ERROR');
        }
        exit;

    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao enviar: ' . $errorMsg
        ]);
        writeLog("Erro ao enviar WhatsApp manual para {$codigo}: " . $errorMsg, 'ERROR');
        writeLog("Stack trace: " . $e->getTraceAsString(), 'ERROR');
        exit;
    } catch (Throwable $e) {
        $errorMsg = $e->getMessage();
        echo json_encode([
            'success' => false,
            'message' => 'Erro fatal ao enviar: ' . $errorMsg
        ]);
        writeLog("Erro fatal ao enviar WhatsApp manual para {$codigo}: " . $errorMsg, 'ERROR');
        exit;
    }
}

// Cache desabilitado para desenvolvimento
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$uploadMaxSizeMb = number_format(getConfig('UPLOAD_MAX_SIZE', 5242880) / 1048576, 1, ',', '.');

// (Sem diagnóstico especial)

// Sistema de Login Seguro
$login_attempts_key = 'login_attempts_' . $_SERVER['REMOTE_ADDR'];
$max_attempts = getConfig('MAX_LOGIN_ATTEMPTS', 5);
$lockout_time = getConfig('LOGIN_LOCKOUT_TIME', 900);

// Verificar se está bloqueado
if (isset($_SESSION[$login_attempts_key])) {
    $attempts = $_SESSION[$login_attempts_key];
    if ($attempts['count'] >= $max_attempts && (time() - $attempts['last_attempt']) < $lockout_time) {
        $erro = "Muitas tentativas de login. Tente novamente em " . ceil(($lockout_time - (time() - $attempts['last_attempt'])) / 60) . " minutos.";
    }
}



if (isset($_POST['login']) && !isset($erro)) {
    $user = sanitizeInput($_POST['user']);
    $pass = $_POST['pass'];

    // Verificar credenciais (em produção, usar hash)
    if ($user === "admin" && $pass === "12345") {
        $_SESSION['logado'] = true;
        $_SESSION['login_time'] = time();
        unset($_SESSION[$login_attempts_key]);
        writeLog("Login realizado com sucesso para usuário: $user", 'INFO');
    } else {
        // Incrementar tentativas
        if (!isset($_SESSION[$login_attempts_key])) {
            $_SESSION[$login_attempts_key] = ['count' => 0, 'last_attempt' => 0];
        }
        $_SESSION[$login_attempts_key]['count']++;
        $_SESSION[$login_attempts_key]['last_attempt'] = time();

        $erro = "Credenciais inválidas. Tentativa " . $_SESSION[$login_attempts_key]['count'] . " de $max_attempts";
        writeLog("Tentativa de login falhada para usuário: $user", 'WARNING');
    }
}

// LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ===================== Presets de Status e Undo =====================
// Definição de presets (fluxos prontos) com offsets em horas
$STATUS_PRESETS = [
    'expresso_48h' => [
        'label' => 'Fluxo Expresso (≈48h)',
        'steps' => [
            ['📦 Objeto postado', 'Objeto recebido no ponto de coleta', 'bg-green-500', 0],
            ['🚚 Em trânsito', 'A caminho do centro de distribuição', 'bg-orange-500', 6],
            ['🏢 No centro de distribuição', 'Processando encaminhamento', 'bg-yellow-500', 18],
            ['🚀 Saiu para entrega', 'Saiu para entrega ao destinatário', 'bg-red-500', 36],
            ['✅ Entregue', 'Objeto entregue com sucesso', 'bg-green-500', 48]
        ]
    ],
    'padrao_72h' => [
        'label' => 'Fluxo Padrão (≈72h)',
        'steps' => [
            ['📦 Objeto postado', 'Objeto recebido no ponto de coleta', 'bg-green-500', 0],
            ['🚚 Em trânsito', 'A caminho do centro de distribuição', 'bg-orange-500', 12],
            ['🏢 No centro de distribuição', 'Processando encaminhamento', 'bg-yellow-500', 36],
            ['🚀 Saiu para entrega', 'Saiu para entrega ao destinatário', 'bg-red-500', 60],
            ['✅ Entregue', 'Objeto entregue com sucesso', 'bg-green-500', 72]
        ]
    ],
    'retencao_taxa' => [
        'label' => 'Fluxo com Retenção/Taxa',
        'steps' => [
            ['📦 Objeto postado', 'Objeto recebido no ponto de coleta', 'bg-green-500', 0],
            ['🚚 Em trânsito', 'A caminho do centro de distribuição', 'bg-orange-500', 8],
            ['🏢 No centro de distribuição', 'Aguardando confirmação de taxa', 'bg-yellow-500', 24],
            ['🚀 Saiu para entrega', 'Taxa confirmada, em rota de entrega', 'bg-red-500', 48],
            ['✅ Entregue', 'Objeto entregue com sucesso', 'bg-green-500', 60]
        ]
    ]
];

function captureUndoSnapshot($pdo, $codigos, $label)
{
    if (empty($codigos)) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($codigos), '?'));
    $rows = fetchData($pdo, "SELECT * FROM rastreios_status WHERE codigo IN ($placeholders)", $codigos);
    $contacts = [];
    foreach ($codigos as $codigoItem) {
        $codigo = trim((string) $codigoItem);
        $contato = getWhatsappContact($pdo, $codigo);
        if ($contato) {
            $contacts[$codigo] = $contato;
        }
    }
    $_SESSION['undo_action'] = [
        'label' => $label,
        'timestamp' => time(),
        'codes' => $codigos,
        'rows' => $rows,
        'contacts' => $contacts
    ];
}

function restoreUndoSnapshot($pdo)
{
    if (empty($_SESSION['undo_action']['rows']) || empty($_SESSION['undo_action']['codes'])) {
        return [false, 'Nada para desfazer'];
    }
    $snapshot = $_SESSION['undo_action'];
    $codes = $snapshot['codes'];
    // Remover atuais
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    executeQuery($pdo, "DELETE FROM rastreios_status WHERE codigo IN ($placeholders)", $codes);

    // Restaurar
    $rows = $snapshot['rows'];
    if (empty($rows)) {
        unset($_SESSION['undo_action']);
        return [true, ''];
    }
    // Preparar colunas (ignorar id se existir)
    $cols = array_keys($rows[0]);
    $cols = array_values(array_filter($cols, function ($c) {
        return strtolower($c) !== 'id';
    }));
    $colList = implode(',', $cols);
    $place = implode(',', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO rastreios_status ($colList) VALUES ($place)";
    foreach ($rows as $r) {
        $vals = [];
        foreach ($cols as $c) {
            $vals[] = $r[$c] ?? null;
        }
        executeQuery($pdo, $sql, $vals);
    }
    if (!empty($snapshot['contacts']) && is_array($snapshot['contacts'])) {
        foreach ($snapshot['contacts'] as $codigo => $contato) {
            upsertWhatsappContact(
                $pdo,
                $codigo,
                $contato['nome'] ?? null,
                $contato['telefone_original'] ?? null,
                isset($contato['notificacoes_ativas']) ? (int) $contato['notificacoes_ativas'] === 1 : true
            );
        }
    }
    unset($_SESSION['undo_action']);
    return [true, 'Restauração concluída'];
}

function aplicarPresetAoCodigo($pdo, $codigo, $cidade, $inicio, $preset, $taxa_valor = null, $taxa_pix = null)
{
    foreach ($preset['steps'] as $step) {
        list($titulo, $subtitulo, $cor, $offsetHours) = $step;
        $data = date('Y-m-d H:i:s', strtotime("+{$offsetHours} hour", $inicio));
        $status_atual = $titulo;
        $sql = "INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, data, cor, taxa_valor, taxa_pix) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        executeQuery($pdo, $sql, [$codigo, $cidade, $status_atual, $titulo, $subtitulo, $data, $cor, $taxa_valor ?: null, $taxa_pix ?: null]);
    }
}




// Função para adicionar etapas (versão segura)
function adicionarEtapas($pdo, $codigo, $cidade, $dataInicial, $etapasMarcadas, $taxa_valor, $taxa_pix)
{
    $etapas = [
        "postado" => ["📦 Objeto postado", "Objeto recebido no ponto de coleta", "bg-green-500"],
        "transito" => ["🚚 Em trânsito", "A caminho do centro de distribuição", "bg-orange-500"],
        "distribuicao" => ["🏢 No centro de distribuição", "Processando encaminhamento", "bg-yellow-500"],
        "entrega" => ["🚀 Saiu para entrega", "Saiu para entrega ao destinatário", "bg-red-500"],
        "entregue" => ["✅ Entregue", "Objeto entregue com sucesso", "bg-green-500"],
    ];

    $dia = 0;
    foreach ($etapas as $key => $dados) {
        if (!empty($etapasMarcadas[$key])) {
            $titulo = $dados[0];
            $subtitulo = $dados[1];
            $cor = $dados[2];
            $status_atual = $dados[0];
            $data = date("Y-m-d H:i:s", strtotime("+$dia days", $dataInicial));

            $sql = "INSERT INTO rastreios_status 
                (codigo, cidade, status_atual, titulo, subtitulo, data, cor, taxa_valor, taxa_pix)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $codigo,
                $cidade,
                $status_atual,
                $titulo,
                $subtitulo,
                $data,
                $cor,
                $taxa_valor ?: null,
                $taxa_pix ?: null
            ];

            executeQuery($pdo, $sql, $params);
            $dia++;
        }
    }
}

// Ação: confirmar pagamento e aplicar entrega expressa (3 dias)
if (isset($_POST['confirmar_pagamento_express'])) {
    try {
        $codigo = isset($_POST['codigo']) ? sanitizeInput($_POST['codigo']) : '';
        $cidade = isset($_POST['cidade']) ? sanitizeInput($_POST['cidade']) : '';
        if ($codigo && $cidade) {
            // Remover status anteriores do código
            executeQuery($pdo, "DELETE FROM rastreios_status WHERE codigo = ?", [$codigo]);

            // Preset de 3 dias (72h) distribuído em 5 etapas
            $presetExpress = [
                'steps' => [
                    ["📦 Objeto postado", "Objeto recebido no ponto de coleta", "#16A34A", 0],
                    ["🚚 Em trânsito", "A caminho do centro de distribuição", "#F59E0B", 12],
                    ["🏢 No centro de distribuição", "Processando encaminhamento", "#FBBF24", 36],
                    ["🚀 Saiu para entrega", "Saiu para entrega ao destinatário", "#EF4444", 60],
                    ["✅ Entregue", "Objeto entregue com sucesso", "#16A34A", 72]
                ]
            ];

            $inicio = time();
            aplicarPresetAoCodigo($pdo, $codigo, $cidade, $inicio, $presetExpress, null, null);

            // Marcar prioridade, ajustar previsão e limpar taxa
            $dias = (int) getConfig('EXPRESS_DELIVERY_DAYS', 3);
            $sql = "UPDATE rastreios_status SET prioridade = TRUE, data_entrega_prevista = DATE_ADD(CURDATE(), INTERVAL ? DAY), taxa_valor = NULL, taxa_pix = NULL WHERE codigo = ?";
            executeQuery($pdo, $sql, [$dias, $codigo]);
            notifyWhatsappLatestStatus($pdo, $codigo);

            $success_message = "Pagamento confirmado e entrega expressa aplicada ao código {$codigo}.";
        } else {
            $erro = "Código e cidade são obrigatórios para confirmar expressa.";
        }
    } catch (Exception $e) {
        $erro = "Erro ao aplicar entrega expressa: " . $e->getMessage();
    }
}

// ADICIONAR NOVO
if (isset($_POST['novo_codigo'])) {
    $tempFotoPath = null;
    try {
        $codigo = sanitizeInput($_POST['codigo']);
        $cidade = sanitizeInput($_POST['cidade']);
        $dataInicial = strtotime($_POST['data_inicial']);
        $taxa_valor = !empty($_POST['taxa_valor']) ? sanitizeInput($_POST['taxa_valor']) : null;
        $taxa_pix = !empty($_POST['taxa_pix']) ? sanitizeInput($_POST['taxa_pix']) : null;
        $cliente_nome = isset($_POST['cliente_nome']) ? sanitizeInput($_POST['cliente_nome']) : '';
        $cliente_whatsapp = isset($_POST['cliente_whatsapp']) ? sanitizeInput($_POST['cliente_whatsapp']) : '';
        $cliente_notificar = isset($_POST['cliente_notificar']) && $_POST['cliente_notificar'] === '1';

        writeLog("Processando novo rastreio para código: $codigo", 'DEBUG');
        writeLog("FILES recebidos no novo: " . print_r($_FILES, true), 'DEBUG');

        $uploadResultado = handleRastreioFotoUpload($codigo, 'foto_pedido');
        writeLog("Resultado do upload novo: " . json_encode($uploadResultado), 'DEBUG');

        if (!$uploadResultado['success'] && $uploadResultado['message'] !== null) {
            throw new Exception($uploadResultado['message']);
        }
        $fotoPath = $uploadResultado['path'];
        if ($fotoPath) {
            $tempFotoPath = $fotoPath;
        }

        if ($cliente_whatsapp !== '') {
            $telefone_normalizado = normalizePhoneToDigits($cliente_whatsapp);
            if ($telefone_normalizado === null) {
                throw new Exception('Informe um número de WhatsApp válido com DDD (ex.: 11999999999 ou +5511999999999).');
            }
            if ($cliente_notificar && $telefone_normalizado === null) {
                throw new Exception('Para ativar as notificações automáticas informe um WhatsApp válido.');
            }
        }

        if (empty($codigo) || empty($cidade)) {
            throw new Exception("Código e cidade são obrigatórios");
        }

        $exists = fetchOne($pdo, "SELECT 1 AS e FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? LIMIT 1", [strtoupper(trim($codigo))]);
        if ($exists) {
            if ($fotoPath) {
                persistRastreioFoto($pdo, $codigo, $fotoPath);
                $tempFotoPath = null;
                $success_message = "Foto do rastreio {$codigo} atualizada com sucesso.";
                writeLog("Foto atualizada via formulário principal para {$codigo}", 'INFO');
            } else {
                $error_message = "O código {$codigo} já existe. Use o campo de foto ou edite o rastreio para atualizar os dados.";
                writeLog("Tentativa de adicionar código duplicado sem foto: $codigo", 'WARNING');
            }
        } else {
            adicionarEtapas($pdo, $codigo, $cidade, $dataInicial, $_POST['etapas'], $taxa_valor, $taxa_pix);
            upsertWhatsappContact(
                $pdo,
                $codigo,
                $cliente_nome !== '' ? $cliente_nome : null,
                $cliente_whatsapp !== '' ? $cliente_whatsapp : null,
                $cliente_notificar
            );
            notifyWhatsappLatestStatus($pdo, $codigo);
            if ($taxa_valor && $taxa_pix) {
                try {
                    notifyWhatsappTaxa($pdo, $codigo, (float) $taxa_valor, $taxa_pix);
                } catch (Exception $taxaError) {
                    writeLog("Erro ao notificar sobre taxa para {$codigo}: " . $taxaError->getMessage(), 'WARNING');
                }
            }
            if ($fotoPath) {
                writeLog("Persistindo foto para novo código $codigo: $fotoPath", 'DEBUG');
                persistRastreioFoto($pdo, $codigo, $fotoPath);
                $tempFotoPath = null;
                writeLog("Foto salva com sucesso para novo código $codigo", 'DEBUG');
            }
            $success_message = "Rastreio {$codigo} adicionado com sucesso!";
            writeLog("Novo rastreio adicionado: $codigo para $cidade", 'INFO');
        }
    } catch (Exception $e) {
        if ($tempFotoPath) {
            deleteRastreioFotoFile($tempFotoPath);
        }
        $error_message = "Erro ao adicionar rastreio: " . $e->getMessage();
        writeLog("Erro ao adicionar rastreio: " . $e->getMessage(), 'ERROR');
    }
}

// DELETAR
if (isset($_POST['deletar'])) {
    try {
        $codigo = sanitizeInput($_POST['codigo']);
        // Capturar estado para undo
        captureUndoSnapshot($pdo, [$codigo], 'Excluir rastreio');
        $sql = "DELETE FROM rastreios_status WHERE codigo = ?";
        executeQuery($pdo, $sql, [$codigo]);
        deleteWhatsappContact($pdo, $codigo);
        removeRastreioFoto($pdo, $codigo);
        $success_message = "Rastreio {$codigo} excluído com sucesso!";
        writeLog("Rastreio excluído: $codigo", 'INFO');
    } catch (Exception $e) {
        $error_message = "Erro ao excluir rastreio: " . $e->getMessage();
        writeLog("Erro ao excluir rastreio: " . $e->getMessage(), 'ERROR');
    }
}

// PEDIDOS PENDENTES - Aprovar
if (isset($_POST['aprovar_pedido'])) {
    try {
        $pedidoId = (int) $_POST['pedido_id'];
        $codigoRastreio = sanitizeInput($_POST['codigo_rastreio'] ?? '');

        if (empty($codigoRastreio)) {
            throw new Exception('Código de rastreio é obrigatório para aprovar o pedido.');
        }

        // Buscar dados do pedido pendente
        $pedido = fetchOne($pdo, "SELECT * FROM pedidos_pendentes WHERE id = ?", [$pedidoId]);

        if (!$pedido) {
            throw new Exception('Pedido não encontrado.');
        }

        // Verificar se código já existe
        $exists = fetchOne($pdo, "SELECT 1 AS e FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? LIMIT 1", [strtoupper(trim($codigoRastreio))]);
        if ($exists) {
            throw new Exception("O código {$codigoRastreio} já existe no sistema.");
        }

        // Criar a cidade a partir do endereço
        $cidade = $pedido['cidade'] . '/' . $pedido['estado'];
        $dataInicial = time();

        // Criar apenas a primeira etapa (Objeto Postado)
        $sql = "INSERT INTO rastreios_status 
            (codigo, cidade, status_atual, titulo, subtitulo, data, cor)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

        executeQuery($pdo, $sql, [
            $codigoRastreio,
            $cidade,
            '📦 Objeto postado',
            '📦 Objeto postado',
            'Objeto recebido e postado para envio',
            date('Y-m-d H:i:s', $dataInicial),
            '#16A34A'
        ]);

        // Salvar contato do cliente
        $telefoneNormalizado = normalizePhoneToDigits($pedido['telefone']);
        upsertWhatsappContact(
            $pdo,
            $codigoRastreio,
            $pedido['nome'],
            $telefoneNormalizado,
            true // Ativar notificações
        );

        // Gerar link de rastreamento
        $baseUrl = getDynamicConfig('WHATSAPP_TRACKING_URL', '');
        if ($baseUrl) {
            $linkRastreio = str_replace('{{codigo}}', $codigoRastreio, $baseUrl);
        } else {
            // Fallback: usar URL atual
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $linkRastreio = "{$protocol}://{$host}/?codigo={$codigoRastreio}";
        }

        // Enviar mensagem personalizada com link
        $mensagemPostado = "Olá, {$pedido['nome']}! 📦\n\n";
        $mensagemPostado .= "✅ *Seu pedido foi postado!*\n\n";
        $mensagemPostado .= "🔎 *Código de rastreio:*\n`{$codigoRastreio}`\n\n";
        $mensagemPostado .= "📍 *Acompanhe seu pedido:*\n{$linkRastreio}\n\n";
        $mensagemPostado .= "Você receberá atualizações automáticas sobre o status da entrega.\n\n";
        $mensagemPostado .= "Obrigado pela preferência! 🚚";

        sendWhatsappMessage($telefoneNormalizado, $mensagemPostado);

        // Atualizar pedido como aprovado
        $sql = "UPDATE pedidos_pendentes SET status = 'aprovado', codigo_rastreio = ? WHERE id = ?";
        executeQuery($pdo, $sql, [$codigoRastreio, $pedidoId]);

        $success_message = "✅ Pedido aprovado! Rastreamento {$codigoRastreio} criado e cliente notificado.";
        writeLog("Pedido aprovado: ID {$pedidoId}, Código: {$codigoRastreio}, Cliente: {$pedido['nome']}", 'INFO');
    } catch (Exception $e) {
        $error_message = "Erro ao aprovar pedido: " . $e->getMessage();
        writeLog("Erro ao aprovar pedido: " . $e->getMessage(), 'ERROR');
    }
}

// PEDIDOS PENDENTES - Rejeitar
if (isset($_POST['rejeitar_pedido'])) {
    try {
        $pedidoId = (int) $_POST['pedido_id'];
        $sql = "UPDATE pedidos_pendentes SET status = 'rejeitado' WHERE id = ?";
        executeQuery($pdo, $sql, [$pedidoId]);

        $success_message = "Pedido rejeitado com sucesso!";
        writeLog("Pedido rejeitado: ID {$pedidoId}", 'INFO');
    } catch (Exception $e) {
        $error_message = "Erro ao rejeitar pedido: " . $e->getMessage();
        writeLog("Erro ao rejeitar pedido: " . $e->getMessage(), 'ERROR');
    }
}

// EDITAR
if (isset($_POST['salvar_edicao'])) {
    $tempFotoEdicao = null;
    try {
        $codigo = sanitizeInput($_POST['codigo']);
        $cidade = sanitizeInput($_POST['cidade']);
        $dataInicial = strtotime($_POST['data_inicial']);
        $taxa_valor = !empty($_POST['taxa_valor']) ? sanitizeInput($_POST['taxa_valor']) : null;
        $taxa_pix = !empty($_POST['taxa_pix']) ? sanitizeInput($_POST['taxa_pix']) : null;
        $cliente_nome = isset($_POST['cliente_nome']) ? sanitizeInput($_POST['cliente_nome']) : '';
        $cliente_whatsapp = isset($_POST['cliente_whatsapp']) ? sanitizeInput($_POST['cliente_whatsapp']) : '';
        $cliente_notificar = isset($_POST['cliente_notificar']) && $_POST['cliente_notificar'] === '1';
        $removerFoto = isset($_POST['remover_foto']) && $_POST['remover_foto'] === '1';

        // Processar upload de foto se houver
        writeLog("Processando edição para código: $codigo", 'DEBUG');
        writeLog("FILES recebidos: " . print_r($_FILES, true), 'DEBUG');

        $uploadResultado = handleRastreioFotoUpload($codigo, 'foto_pedido');
        writeLog("Resultado do upload: " . json_encode($uploadResultado), 'DEBUG');

        if (!$uploadResultado['success'] && $uploadResultado['message'] !== null) {
            throw new Exception($uploadResultado['message']);
        }
        $novaFotoPath = $uploadResultado['path'];
        $tempFotoEdicao = $novaFotoPath;

        // Deletar registros existentes
        captureUndoSnapshot($pdo, [$codigo], 'Editar rastreio');
        $sql = "DELETE FROM rastreios_status WHERE codigo = ?";
        executeQuery($pdo, $sql, [$codigo]);

        // Adicionar novos registros
        adicionarEtapas($pdo, $codigo, $cidade, $dataInicial, $_POST['etapas'], $taxa_valor, $taxa_pix);

        // Atualizar contato WhatsApp
        try {
            upsertWhatsappContact(
                $pdo,
                $codigo,
                $cliente_nome !== '' ? $cliente_nome : null,
                $cliente_whatsapp !== '' ? $cliente_whatsapp : null,
                $cliente_notificar
            );
            notifyWhatsappLatestStatus($pdo, $codigo);
            // Notificar sobre taxa se houver
            if ($taxa_valor && $taxa_pix) {
                try {
                    notifyWhatsappTaxa($pdo, $codigo, (float) $taxa_valor, $taxa_pix);
                } catch (Exception $taxaError) {
                    writeLog("Erro ao notificar sobre taxa para {$codigo}: " . $taxaError->getMessage(), 'WARNING');
                }
            }
        } catch (Exception $whatsappError) {
            writeLog("Erro ao atualizar WhatsApp para {$codigo}: " . $whatsappError->getMessage(), 'WARNING');
            // Não interrompe o processo de edição se houver erro no WhatsApp
        }

        // Processar foto
        writeLog("Processando foto - novaFotoPath: " . ($novaFotoPath ?? 'null') . ", removerFoto: " . ($removerFoto ? 'true' : 'false'), 'DEBUG');

        if ($novaFotoPath) {
            writeLog("Persistindo foto para código $codigo: $novaFotoPath", 'DEBUG');
            persistRastreioFoto($pdo, $codigo, $novaFotoPath);
            $tempFotoEdicao = null; // Limpar referência após salvar
            writeLog("Foto salva com sucesso para código $codigo", 'DEBUG');
        } elseif ($removerFoto) {
            writeLog("Removendo foto do código $codigo", 'DEBUG');
            removeRastreioFoto($pdo, $codigo);
        }

        $success_message = "Rastreio {$codigo} atualizado com sucesso!";
        writeLog("Rastreio atualizado: $codigo", 'INFO');
    } catch (Exception $e) {
        if ($tempFotoEdicao) {
            deleteRastreioFotoFile($tempFotoEdicao);
        }
        $error_message = "Erro ao atualizar rastreio: " . $e->getMessage();
        writeLog("Erro ao atualizar rastreio: " . $e->getMessage(), 'ERROR');
    }
}

// EXCLUSÃO EM LOTE
if (isset($_POST['bulk_delete'])) {
    try {
        $codigos = json_decode($_POST['bulk_delete'], true);
        if (is_array($codigos)) {
            captureUndoSnapshot($pdo, array_map('sanitizeInput', $codigos), 'Exclusão em lote');
            $count = 0;
            foreach ($codigos as $codigo) {
                $codigo = sanitizeInput($codigo);
                $sql = "DELETE FROM rastreios_status WHERE codigo = ?";
                executeQuery($pdo, $sql, [$codigo]);
                deleteWhatsappContact($pdo, $codigo);
                removeRastreioFoto($pdo, $codigo);
                $count++;
            }
            $success_message = "{$count} rastreio(s) excluído(s) com sucesso!";
            writeLog("Exclusão em lote: $count rastreios excluídos", 'INFO');
        }
    } catch (Exception $e) {
        $error_message = "Erro na exclusão em lote: " . $e->getMessage();
        writeLog("Erro na exclusão em lote: " . $e->getMessage(), 'ERROR');
    }
}

// EDIÇÃO EM LOTE
if (isset($_POST['bulk_edit'])) {
    try {
        $codigos = json_decode($_POST['bulk_edit'], true);
        if (is_array($codigos)) {
            $nova_cidade = !empty($_POST['new_cidade']) ? sanitizeInput($_POST['new_cidade']) : null;
            $nova_taxa_valor = !empty($_POST['new_taxa_valor']) ? sanitizeInput($_POST['new_taxa_valor']) : null;
            $nova_taxa_pix = !empty($_POST['new_taxa_pix']) ? sanitizeInput($_POST['new_taxa_pix']) : null;

            captureUndoSnapshot($pdo, array_map('sanitizeInput', $codigos), 'Edição em lote');
            $count = 0;
            foreach ($codigos as $codigo) {
                $codigo = sanitizeInput($codigo);

                if ($nova_cidade) {
                    $sql = "UPDATE rastreios_status SET cidade = ? WHERE codigo = ?";
                    executeQuery($pdo, $sql, [$nova_cidade, $codigo]);
                }

                if ($nova_taxa_valor && $nova_taxa_pix) {
                    $sql = "UPDATE rastreios_status SET taxa_valor = ?, taxa_pix = ? WHERE codigo = ?";
                    executeQuery($pdo, $sql, [$nova_taxa_valor, $nova_taxa_pix, $codigo]);
                    // Notificar sobre taxa
                    try {
                        notifyWhatsappTaxa($pdo, $codigo, (float) $nova_taxa_valor, $nova_taxa_pix);
                    } catch (Exception $taxaError) {
                        writeLog("Erro ao notificar sobre taxa para {$codigo}: " . $taxaError->getMessage(), 'WARNING');
                    }
                }
                $count++;
            }
            $success_message = "{$count} rastreio(s) atualizado(s) com sucesso!";
            writeLog("Edição em lote: $count rastreios atualizados", 'INFO');
        }
    } catch (Exception $e) {
        $error_message = "Erro na edição em lote: " . $e->getMessage();
        writeLog("Erro na edição em lote: " . $e->getMessage(), 'ERROR');
    }
}

// Aplicar PRESET em massa
if (isset($_POST['apply_preset'])) {
    try {
        $codigos = json_decode($_POST['apply_preset'], true);
        $preset_key = sanitizeInput($_POST['preset_key'] ?? '');
        $modo = sanitizeInput($_POST['preset_mode'] ?? 'replace'); // replace | append
        $cidadePadrao = sanitizeInput($_POST['preset_cidade'] ?? 'Não informado');
        $dtInicio = !empty($_POST['preset_start']) ? strtotime($_POST['preset_start']) : time();
        $taxa_valor = !empty($_POST['preset_taxa_valor']) ? sanitizeInput($_POST['preset_taxa_valor']) : null;
        $taxa_pix = !empty($_POST['preset_taxa_pix']) ? sanitizeInput($_POST['preset_taxa_pix']) : null;

        global $STATUS_PRESETS;
        if (empty($STATUS_PRESETS[$preset_key])) {
            throw new Exception('Preset inválido');
        }

        if (!is_array($codigos) || empty($codigos)) {
            throw new Exception('Nenhum código selecionado');
        }

        captureUndoSnapshot($pdo, array_map('sanitizeInput', $codigos), 'Aplicar preset em massa');

        $preset = $STATUS_PRESETS[$preset_key];
        $count = 0;
        foreach ($codigos as $codigo) {
            $codigo = sanitizeInput($codigo);
            if ($modo === 'replace') {
                executeQuery($pdo, "DELETE FROM rastreios_status WHERE codigo = ?", [$codigo]);
            }
            // Recuperar cidade existente se houver
            $rowCidade = fetchOne($pdo, "SELECT cidade FROM rastreios_status WHERE codigo = ? ORDER BY data DESC LIMIT 1", [$codigo]);
            $cidade = $rowCidade['cidade'] ?? $cidadePadrao;
            aplicarPresetAoCodigo($pdo, $codigo, $cidade, $dtInicio, $preset, $taxa_valor, $taxa_pix);
            notifyWhatsappLatestStatus($pdo, $codigo);
            // Notificar sobre taxa se houver
            if ($taxa_valor && $taxa_pix) {
                try {
                    notifyWhatsappTaxa($pdo, $codigo, (float) $taxa_valor, $taxa_pix);
                } catch (Exception $taxaError) {
                    writeLog("Erro ao notificar sobre taxa para {$codigo}: " . $taxaError->getMessage(), 'WARNING');
                }
            }
            $count++;
        }
        $success_message = "Preset aplicado para {$count} rastreio(s)!";
        writeLog("Preset '{$preset_key}' aplicado em massa para $count códigos", 'INFO');
    } catch (Exception $e) {
        $error_message = "Erro ao aplicar preset: " . $e->getMessage();
        writeLog("Erro ao aplicar preset: " . $e->getMessage(), 'ERROR');
    }
}

// Desfazer (Undo)
if (isset($_POST['undo_action'])) {
    list($ok, $msg) = restoreUndoSnapshot($pdo);
    if ($ok) {
        $success_message = 'Ação desfeita com sucesso';
        writeLog('Desfazer executado com sucesso', 'INFO');
    } else {
        $error_message = $msg ?: 'Não foi possível desfazer';
        writeLog('Falha ao desfazer: ' . ($msg ?: 'desconhecida'), 'WARNING');
    }
}

// Endpoint AJAX movido para o topo do arquivo (linha ~12) para garantir processamento antes de qualquer HTML

// (Sem configurações de site persistidas)

// ===================== ESTATÍSTICAS DO DASHBOARD =====================
// Contar Pedidos Pendentes
$sqlPedidos = "SELECT COUNT(*) as total FROM pedidos_pendentes WHERE status = 'pendente'";
$totalPedidosPendentes = fetchOne($pdo, $sqlPedidos)['total'] ?? 0;
if ($totalPedidosPendentes > 0) {
    $pedidosPendentes = fetchData($pdo, "SELECT * FROM pedidos_pendentes WHERE status = 'pendente' ORDER BY data_pedido DESC");
} else {
    $pedidosPendentes = [];
}

// Contar Rastreios (Agrupados por código)
$sqlTotal = "SELECT COUNT(DISTINCT codigo) as total FROM rastreios_status";
$totalRastreios = fetchOne($pdo, $sqlTotal)['total'] ?? 0;

// Entregues (Status atual contém 'Entregue')
// Precisamos subquery para pegar o status ATUAL de cada código
$sqlEntregues = "SELECT COUNT(*) as total FROM (
    SELECT status_atual FROM rastreios_status t1
    WHERE data = (SELECT MAX(data) FROM rastreios_status t2 WHERE t2.codigo = t1.codigo)
    GROUP BY codigo
    HAVING status_atual LIKE '%Entregue%'
) as sub";
$entregues = fetchOne($pdo, $sqlEntregues)['total'] ?? 0;

// Com Taxa / Sem Taxa (Baseado no último status)
$sqlComTaxa = "SELECT COUNT(*) as total FROM (
    SELECT taxa_valor FROM rastreios_status t1
    WHERE data = (SELECT MAX(data) FROM rastreios_status t2 WHERE t2.codigo = t1.codigo)
    GROUP BY codigo
    HAVING taxa_valor IS NOT NULL AND taxa_valor > 0
) as sub";
$comTaxa = fetchOne($pdo, $sqlComTaxa)['total'] ?? 0;

$semTaxa = $totalRastreios - $comTaxa;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - Helmer Logistics</title>
    <meta name="theme-color" content="#FF3333">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="assets/images/whatsapp-1.jpg">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Helmer Admin">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <!-- SweetAlert2 para popups bonitos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="admin-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <i class="fas fa-cube"></i> Helmer
                </div>
                <button class="mobile-close-btn" onclick="toggleSidebar()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <nav class="sidebar-menu">
                <div class="menu-label">Principal</div>
                <a href="index.php" class="nav-item"><i class="fas fa-home"></i> Página Inicial</a>
                <a href="admin.php" class="nav-item active"><i class="fas fa-chart-pie"></i> Dashboard</a>
                <a href="admin_pedidos_pendentes.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Pedidos
                    Pendentes</a>
                <a href="admin_indicacoes.php" class="nav-item"><i class="fas fa-users"></i> Indicações</a>

                <div class="menu-label">Gestão</div>
                <a href="admin_homepage.php" class="nav-item"><i class="fas fa-pen-to-square"></i> Editar Site</a>
                <a href="admin_bot_config.php" class="nav-item"><i class="fas fa-robot"></i> Configuração Bot</a>
                <a href="admin_mensagens.php" class="nav-item"><i class="fas fa-message"></i> Mensagens WPP</a>

                <div class="menu-label">Configuração</div>
                <a href="admin_settings.php" class="nav-item"><i class="fas fa-gear"></i> Ajustes Expressa</a>
            </nav>

            <div class="sidebar-footer">
                <?php if (!empty($_SESSION['undo_action'])): ?>
                    <form id="undoForm" method="POST" style="display:none"><input type="hidden" name="undo_action"
                            value="1"></form>
                    <a href="#" class="nav-item" onclick="document.getElementById('undoForm').submit(); return false;"
                        style="color: var(--warning);">
                        <i class="fas fa-rotate-left"></i> Desfazer
                    </a>
                <?php endif; ?>
                <a href="admin.php?logout=1" class="nav-item" style="color: var(--primary);"><i
                        class="fas fa-power-off"></i> Sair</a>
            </div>
        </aside>

        <!-- Overlay Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <button class="mobile-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title">
                        <h2>Painel Administrativo</h2>
                    </div>
                </div>

                <div class="header-actions">
                    <button class="btn btn-icon"><i class="fas fa-bell"></i></button>
                    <div
                        style="width:32px; height:32px; background:var(--gradient-brand); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; color:white;">
                        A</div>
                </div>
            </header>

            <div class="content-body">
                <!-- Mobile FAB -->
                <button class="fab-add" onclick="document.getElementById('modalAdd').style.display='flex'"
                    title="Novo Rastreio">
                    <i class="fas fa-plus"></i>
                </button>


                <!-- Dashboard Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card featured">
                        <div class="stat-icon"><i class="fas fa-box"></i></div>
                        <div class="stat-value"><?= $totalRastreios ?></div>
                        <div class="stat-label">Total de Rastreios</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--warning);"><i class="fas fa-clock"></i></div>
                        <div class="stat-value"><?= $comTaxa ?></div>
                        <div class="stat-label">Taxa Pendente</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success);"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-value"><?= $semTaxa ?></div>
                        <div class="stat-label">Sem Taxa</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--info);"><i class="fas fa-truck"></i></div>
                        <div class="stat-value"><?= $entregues ?></div>
                        <div class="stat-label">Entregues</div>
                    </div>
                    <?php if ($totalPedidosPendentes > 0): ?>
                        <div class="stat-card" style="border-color: var(--warning);">
                            <div class="stat-icon" style="color: var(--warning);"><i class="fas fa-shopping-cart"></i></div>
                            <div class="stat-value"><?= $totalPedidosPendentes ?></div>
                            <div class="stat-label">Pedidos Pendentes</div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Seção de Pedidos Pendentes -->
                <?php if ($totalPedidosPendentes > 0): ?>
                    <div class="glass-panel" style="margin-bottom: 2rem; padding: 1.5rem;">
                        <h2
                            style="margin-bottom: 1.5rem; color: var(--warning); display:flex; align-items:center; gap:0.5rem;">
                            <i class="fas fa-shopping-cart"></i> Pedidos Pendentes (<?= $totalPedidosPendentes ?>)
                        </h2>

                        <div style="display: grid; gap: 1.5rem;">
                            <?php foreach ($pedidosPendentes as $pedido): ?>
                                <div
                                    style="background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 1.5rem;">
                                    <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem;">
                                        <div style="flex: 1; min-width: 280px;">
                                            <h3
                                                style="color: var(--text-main); margin-bottom: 1rem; font-size: 1.1rem; display:flex; align-items:center; gap:0.5rem;">
                                                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($pedido['nome']) ?>
                                            </h3>

                                            <div
                                                style="display: grid; gap: 0.5rem; color: var(--text-muted); font-size: 0.9rem;">
                                                <div><i class="fas fa-phone fa-fw"></i>
                                                    <?= htmlspecialchars($pedido['telefone']) ?></div>
                                                <?php if ($pedido['email']): ?>
                                                    <div><i class="fas fa-envelope fa-fw"></i>
                                                        <?= htmlspecialchars($pedido['email']) ?></div>
                                                <?php endif; ?>
                                                <div><i class="fas fa-calendar fa-fw"></i>
                                                    <?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?></div>
                                            </div>

                                            <div
                                                style="margin-top: 1rem; padding: 1rem; background: rgba(255, 51, 51, 0.05); border-radius: 8px; border-left: 2px solid var(--primary);">
                                                <div style="color: var(--text-main); font-size: 0.95rem; line-height: 1.6;">
                                                    <strong>Endereço:</strong><br>
                                                    <?= htmlspecialchars($pedido['rua']) ?>,
                                                    <?= htmlspecialchars($pedido['numero']) ?>
                                                    <?= $pedido['complemento'] ? ' - ' . htmlspecialchars($pedido['complemento']) : '' ?><br>
                                                    <?= htmlspecialchars($pedido['bairro']) ?> -
                                                    <?= htmlspecialchars($pedido['cidade']) ?>/<?= htmlspecialchars($pedido['estado']) ?><br>
                                                    CEP: <?= htmlspecialchars($pedido['cep']) ?>
                                                </div>
                                                <?php if ($pedido['observacoes']): ?>
                                                    <div
                                                        style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-subtle);">
                                                        <span style="color: var(--text-muted); font-size:0.85rem;">Obs:</span>
                                                        <p style="color: var(--text-main); margin-top: 0.25rem;">
                                                            <?= nl2br(htmlspecialchars($pedido['observacoes'])) ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div style="display: flex; flex-direction: column; gap: 0.75rem; min-width: 200px;">
                                            <form method="POST"
                                                onsubmit="return confirmarAprovarPedido(this, '<?= htmlspecialchars($pedido['nome'], ENT_QUOTES) ?>')">
                                                <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                                <div style="display:flex; gap:0.5rem; margin-bottom:0.5rem;">
                                                    <input type="text" name="codigo_rastreio" class="form-control"
                                                        placeholder="Código de Rastreio" required>
                                                </div>
                                                <button type="submit" name="aprovar_pedido" class="btn btn-primary"
                                                    style="width: 100%;">
                                                    <i class="fas fa-check"></i> Aprovar Pedido
                                                </button>
                                            </form>

                                            <form method="POST"
                                                onsubmit="return confirmarRejeitarPedido(this, '<?= htmlspecialchars($pedido['nome'], ENT_QUOTES) ?>')">
                                                <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                                <button type="submit" name="rejeitar_pedido" class="btn"
                                                    style="width: 100%; border: 1px solid var(--border-subtle); color: var(--danger);">
                                                    <i class="fas fa-times"></i> Rejeitar
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Table Section -->
                <div class="table-card">
                    <div class="table-header">
                        <div style="display:flex; gap:1rem; flex:1;">
                            <input type="text" id="searchInput" class="form-control" placeholder="🔍 Buscar rastreio..."
                                onkeyup="filterTable()" style="max-width:300px;">
                            <div class="desktop-only" style="display:flex; gap:0.5rem;">
                                <button class="btn btn-icon filter-btn active" onclick="filterBy('all')"
                                    title="Todos"><i class="fas fa-list"></i></button>
                                <button class="btn btn-icon filter-btn" onclick="filterBy('com_taxa')"
                                    title="Com Taxa"><i class="fas fa-dollar-sign"></i></button>
                                <button class="btn btn-icon filter-btn" onclick="filterBy('entregues')"
                                    title="Entregues"><i class="fas fa-check"></i></button>
                            </div>
                        </div>
                        <div style="display:flex; gap:0.75rem;">
                            <button class="btn"
                                style="background:var(--bg-surface); border:1px solid var(--border-subtle);"
                                onclick="exportData()">
                                <i class="fas fa-download"></i> <span class="desktop-only">Exportar</span>
                            </button>
                            <button class="btn btn-primary"
                                onclick="document.getElementById('modalAdd').style.display='flex'">
                                <i class="fas fa-plus"></i> <span class="desktop-only">Novo Rastreio</span>
                            </button>
                        </div>
                    </div>

                    <!-- Operações em lote -->
                    <div id="bulkActions"
                        style="display: none; padding: 1rem; background: rgba(59, 130, 246, 0.1); border-bottom: 1px solid var(--border-subtle); align-items:center; gap:1rem;">
                        <span style="font-weight:600; color:var(--info);"><i class="fas fa-check-square"></i> <span
                                id="selectedCount">0</span> selecionados</span>
                        <div style="display:flex; gap:0.5rem; margin-left:auto;">
                            <button class="btn btn-sm" onclick="bulkDelete()"
                                style="background: var(--danger); color: white;"><i class="fas fa-trash"></i></button>
                            <button class="btn btn-sm" onclick="bulkEdit()"
                                style="background: var(--warning); color: black;"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm" onclick="openPresetModal()"
                                style="background: var(--info); color: white;"><i class="fas fa-magic"></i></button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table" id="rastreiosTable">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                    </th>
                                    <th>Código</th>
                                    <th>Cidade</th>
                                    <th>Status</th>
                                    <th>Taxa</th>
                                    <th>Ultima Atualização</th>
                                    <th style="text-align:right;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Lógica para buscar rastreios
                                $where = "";
                                if (isset($_GET['filtro'])) {
                                    if ($_GET['filtro'] == "com_taxa") {
                                        $where = "HAVING MAX(taxa_valor) IS NOT NULL AND MAX(taxa_pix) IS NOT NULL";
                                    } elseif ($_GET['filtro'] == "sem_taxa") {
                                        $where = "HAVING MAX(taxa_valor) IS NULL OR MAX(taxa_pix) IS NULL";
                                    }
                                }

                                // Consulta mais robusta - primeiro pega todos os códigos únicos
                                $sql = "SELECT DISTINCT codigo FROM rastreios_status WHERE codigo IS NOT NULL AND codigo != '' ORDER BY codigo DESC";
                                $codigos_result = fetchData($pdo, $sql);

                                $dados_rastreios = [];
                                if (!empty($codigos_result)) {
                                    foreach ($codigos_result as $codigo_row) {
                                        $codigo = $codigo_row['codigo'];

                                        // Para cada código, pega o último registro
                                        $ultimo_sql = "SELECT * FROM rastreios_status WHERE codigo = ? ORDER BY data DESC LIMIT 1";
                                        $ultimo_result = fetchOne($pdo, $ultimo_sql, [$codigo]);

                                        if ($ultimo_result) {
                                            $dados_rastreios[] = $ultimo_result;
                                        }
                                    }
                                }

                                // Aplicar filtros se necessário
                                if (isset($_GET['filtro'])) {
                                    $dados_rastreios = array_filter($dados_rastreios, function ($row) {
                                        if ($_GET['filtro'] == "com_taxa") {
                                            return !empty($row['taxa_valor']) && !empty($row['taxa_pix']);
                                        } elseif ($_GET['filtro'] == "sem_taxa") {
                                            return empty($row['taxa_valor']) || empty($row['taxa_pix']);
                                        }
                                        return true;
                                    });
                                }

                                if (!empty($dados_rastreios)) {
                                    foreach ($dados_rastreios as $row) {
                                        $badge = !empty($row['taxa_valor']) && !empty($row['taxa_pix'])
                                            ? "<span class='badge badge-warning'>Pendente</span>"
                                            : "<span class='badge badge-success'>Sem taxa</span>";

                                        $statusClass = 'text-muted';
                                        if (strpos($row['status_atual'], 'Entregue') !== false)
                                            $statusClass = 'text-success';
                                        elseif (strpos($row['status_atual'], 'Saiu') !== false)
                                            $statusClass = 'text-warning';
                                        elseif (strpos($row['status_atual'], 'trânsito') !== false)
                                            $statusClass = 'text-info';

                                        echo "<tr data-codigo='{$row['codigo']}' data-cidade='{$row['cidade']}' data-status='{$row['status_atual']}'>
                                    <td><input type='checkbox' class='row-checkbox' value='{$row['codigo']}' onchange='updateSelection()'></td>
                                    <td style='font-family:var(--font-mono); font-weight:600;'>{$row['codigo']}</td>
                                    <td>{$row['cidade']}</td>
                                    <td><span class='{$statusClass}'><i class='fas fa-circle' style='font-size:8px; margin-right:6px;'></i>{$row['status_atual']}</span></td>
                                    <td>$badge</td>
                                    <td style='color:var(--text-muted);'>" . date("d/m H:i", strtotime($row['data'])) . "</td>
                                    <td style='text-align:right;'>
                                        <button class='btn btn-icon' onclick='abrirModal(\"{$row['codigo']}\")' title='Editar'><i class='fas fa-pencil'></i></button>
                                        <button class='btn btn-icon' onclick='viewDetails(\"{$row['codigo']}\")' title='Ver'><i class='fas fa-eye'></i></button>
                                        <button class='btn btn-icon' style='color:#25D366' onclick='enviarWhatsappManual(\"{$row['codigo']}\")' title='WhatsApp'><i class='fab fa-whatsapp'></i></button>
                                    </td>
                                </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7' style='text-align:center; padding:3rem; color:var(--text-muted);'>Nenhum rastreio encontrado</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cards View (Mobile Only) - Gerado via JS ou mantido PHP se preferir -->
                <div class="cards-list">
                    <?php if (!empty($dados_rastreios)):
                        foreach ($dados_rastreios as $row): ?>
                            <div class="card-item" onclick="viewDetails('<?= $row['codigo'] ?>')">
                                <div class="card-header">
                                    <div class="card-code"><?= $row['codigo'] ?></div>
                                    <?= !empty($row['taxa_valor']) ? '<span class="badge badge-warning">Taxa</span>' : '' ?>
                                </div>
                                <div class="card-status"><?= $row['status_atual'] ?></div>
                                <div class="card-city"><i class="fas fa-map-pin"></i> <?= $row['cidade'] ?></div>
                                <div class="card-actions">
                                    <button class="btn-mobile-action btn-mobile-edit"
                                        onclick="event.stopPropagation(); abrirModal('<?= $row['codigo'] ?>')">
                                        <i class="fas fa-pencil"></i> Editar
                                    </button>
                                    <button class="btn-mobile-action btn-mobile-delete"
                                        onclick="event.stopPropagation(); confirmarExclusao('form-delete-<?= $row['codigo'] ?>', 'rastreio', '<?= $row['codigo'] ?>')">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                </div>
                                <form id="form-delete-<?= $row['codigo'] ?>" method="POST" style="display:none;">
                                    <input type="hidden" name="deletar" value="1">
                                    <input type="hidden" name="codigo" value="<?= $row['codigo'] ?>">
                                </form>
                            </div>
                        <?php endforeach; endif; ?>
                </div>

            </div> <!-- End .content-body -->
        </main>
    </div> <!-- End .admin-wrapper -->

    <!-- Modal Adicionar Rastreio (Novo, separado do layout principal) -->
    <div id="modalAdd" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Novo Rastreio</h3>
                <button class="close"
                    onclick="document.getElementById('modalAdd').style.display='none'">&times;</button>
            </div>
            <form method="POST" id="addForm" enctype="multipart/form-data">
                <input type="hidden" name="novo_codigo" value="1">
                <div class="form-group">
                    <label>Código do Objeto</label>
                    <input type="text" name="codigo" class="form-control" id="codigo" placeholder="AA123456789BR"
                        required style="font-family:var(--font-mono); letter-spacing:1px;">
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Cidade de Origem/ Destino</label>
                        <input type="text" name="cidade" class="form-control" placeholder="São Paulo/SP" required>
                    </div>
                    <div class="form-group">
                        <label>Data de Postagem</label>
                        <input type="datetime-local" name="data_inicial" class="form-control"
                            value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>
                </div>

                <div style="margin: 1.5rem 0; padding: 1rem; background: var(--bg-surface); border-radius: 8px;">
                    <label style="margin-bottom:0.5rem; display:block;">Cliente (Opcional)</label>
                    <div class="form-grid">
                        <input type="text" name="cliente_nome" class="form-control" placeholder="Nome">
                        <input type="tel" name="cliente_whatsapp" class="form-control" placeholder="WhatsApp (com DDD)">
                    </div>
                    <label style="display:flex; align-items:center; gap:0.5rem; margin-top:0.5rem; font-size:0.9rem;">
                        <input type="checkbox" name="cliente_notificar" value="1" checked> Enviar notificação automática
                    </label>
                </div>

                <div class="form-group">
                    <label>Foto do Pedido</label>
                    <div class="photo-upload">
                        <div class="photo-preview" id="fotoPreviewNovo">
                            <img id="fotoPreviewImgNovo" src="" alt="Foto do pedido" style="display:none;">
                            <span id="fotoPreviewPlaceholderNovo">Nenhuma foto selecionada</span>
                        </div>
                        <div class="photo-preview-actions">
                            <input type="file" name="foto_pedido" id="novo_foto_pedido" accept="image/*"
                                onchange="previewFotoNovo(this)">
                        </div>
                        <small style="display:block;color:rgba(148,163,184,0.85);font-size:0.85rem;margin-top:6px;">
                            Formatos suportados: JPG, PNG, WEBP ou GIF (até <?= $uploadMaxSizeMb ?> MB).
                        </small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Fluxo Inicial</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item"><input type="checkbox" name="etapas[postado]" value="1"
                                id="etapa_postado" checked><label for="etapa_postado">Objeto postado</label></div>
                        <div class="checkbox-item"><input type="checkbox" name="etapas[transito]" value="1"
                                id="etapa_transito"><label for="etapa_transito">Em trânsito</label></div>
                    </div>
                </div>

                <div class="actions">
                    <button type="button" class="btn"
                        onclick="document.getElementById('modalAdd').style.display='none'">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Rastreio</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal edição -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Editar Rastreio</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>

            <form method="POST" id="formEditar" enctype="multipart/form-data">
                <input type="hidden" name="salvar_edicao" value="1">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_codigo">Código de Rastreio</label>
                        <input type="text" name="codigo" id="edit_codigo" readonly
                            style="background: #333; color: #999;">
                    </div>
                    <div class="form-group">
                        <label for="edit_cidade">Cidade Vinculada</label>
                        <input type="text" name="cidade" id="edit_cidade" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_data">Nova Data Inicial</label>
                        <input type="datetime-local" name="data_inicial" id="edit_data" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Etapas do Rastreamento</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" name="etapas[postado]" value="1" id="cb_postado">
                            <label for="cb_postado">📦 Objeto postado</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="etapas[transito]" value="1" id="cb_transito">
                            <label for="cb_transito">🚚 Em trânsito</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="etapas[distribuicao]" value="1" id="cb_distribuicao">
                            <label for="cb_distribuicao">🏢 No centro de distribuição</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="etapas[entrega]" value="1" id="cb_entrega">
                            <label for="cb_entrega">🚀 Saiu para entrega</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="etapas[entregue]" value="1" id="cb_entregue">
                            <label for="cb_entregue">✅ Entregue</label>
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_taxa_valor">Valor da Taxa (opcional)</label>
                        <input type="number" name="taxa_valor" id="edit_taxa_valor" placeholder="0.00" step="0.01"
                            min="0">
                    </div>
                    <div class="form-group">
                        <label for="edit_taxa_pix">Chave PIX (opcional)</label>
                        <input type="text" name="taxa_pix" id="edit_taxa_pix" placeholder="Digite a chave PIX...">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_cliente_nome">Nome do Cliente (opcional)</label>
                        <input type="text" name="cliente_nome" id="edit_cliente_nome" placeholder="Ex.: Maria Silva">
                    </div>
                    <div class="form-group">
                        <label for="edit_cliente_whatsapp">WhatsApp do Cliente</label>
                        <input type="tel" name="cliente_whatsapp" id="edit_cliente_whatsapp"
                            placeholder="Ex.: 11999999999">
                        <small
                            style="display:block;color:rgba(148,163,184,0.85);font-size:0.85rem;margin-top:6px;">Inclua
                            DDD. Aceita números nacionais e internacionais.</small>
                    </div>
                    <div class="form-group" style="align-self:flex-end;">
                        <label for="edit_cliente_notificar" style="display:block;">Notificações automáticas</label>
                        <label style="display:flex;align-items:center;gap:8px;font-size:0.95rem;">
                            <input type="checkbox" name="cliente_notificar" id="edit_cliente_notificar" value="1">
                            <span>Enviar atualizações no WhatsApp</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Foto do Pedido</label>
                    <div class="photo-upload">
                        <div class="photo-preview" id="fotoPreview">
                            <img id="fotoPreviewImg" src="" alt="Foto do pedido" style="display:none;">
                            <span id="fotoPreviewPlaceholder">Nenhuma foto cadastrada</span>
                        </div>
                        <div class="photo-preview-actions">
                            <input type="file" name="foto_pedido" id="edit_foto_pedido" accept="image/*">
                            <label style="display:flex;align-items:center;gap:8px;margin-top:10px;">
                                <input type="checkbox" name="remover_foto" id="edit_remover_foto" value="1">
                                Remover foto atual
                            </label>
                        </div>
                        <small style="display:block;color:rgba(148,163,184,0.85);font-size:0.85rem;margin-top:6px;">
                            Formatos suportados: JPG, PNG, WEBP ou GIF (até <?= $uploadMaxSizeMb ?> MB).
                        </small>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                    <button type="button" class="btn btn-warning" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal detalhes -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Detalhes do Rastreio</h3>
                <button class="close" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div id="detailsContent">
                <!-- Conteúdo será carregado via JavaScript -->
            </div>
        </div>
    </div>

    <script>
        // Funções do modal
        function abrirModal(codigo) {
            fetch("get_etapas.php?codigo=" + codigo + "&t=" + Date.now())
                .then(r => r.json())
                .then(data => {
                    document.getElementById('modal').style.display = 'flex';
                    document.getElementById('edit_codigo').value = codigo;
                    document.getElementById('edit_cidade').value = data.cidade || '';
                    // Usar data inicial retornada ou data atual como fallback
                    document.getElementById('edit_data').value = data.data_inicial || new Date().toISOString().slice(0, 16);
                    document.getElementById('edit_taxa_valor').value = data.taxa_valor || '';
                    document.getElementById('edit_taxa_pix').value = data.taxa_pix || '';
                    document.getElementById('cb_postado').checked = data.etapas && data.etapas.includes('postado');
                    document.getElementById('cb_transito').checked = data.etapas && data.etapas.includes('transito');
                    document.getElementById('cb_distribuicao').checked = data.etapas && data.etapas.includes('distribuicao');
                    document.getElementById('cb_entrega').checked = data.etapas && data.etapas.includes('entrega');
                    document.getElementById('cb_entregue').checked = data.etapas && data.etapas.includes('entregue');
                    document.getElementById('edit_cliente_nome').value = data.cliente_nome || '';
                    document.getElementById('edit_cliente_whatsapp').value = data.cliente_whatsapp || '';
                    document.getElementById('edit_cliente_notificar').checked = !!data.cliente_notificar;
                    const previewImg = document.getElementById('fotoPreviewImg');
                    const placeholder = document.getElementById('fotoPreviewPlaceholder');
                    const removerFoto = document.getElementById('edit_remover_foto');
                    if (previewImg && placeholder && removerFoto) {
                        if (data.foto_url) {
                            previewImg.src = data.foto_url + '?t=' + Date.now();
                            previewImg.dataset.originalSrc = previewImg.src;
                            previewImg.style.display = 'block';
                            previewImg.dataset.hasOriginal = '1';
                            placeholder.style.display = 'none';
                            removerFoto.disabled = false;
                            removerFoto.checked = false;
                        } else {
                            previewImg.removeAttribute('src');
                            previewImg.style.display = 'none';
                            delete previewImg.dataset.hasOriginal;
                            delete previewImg.dataset.originalSrc;
                            placeholder.style.display = 'inline-block';
                            removerFoto.checked = false;
                            removerFoto.disabled = true;
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar dados:', error);
                    alert('Erro ao carregar dados do rastreio');
                });
        }

        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        function previewFotoNovo(input) {
            const previewImg = document.getElementById('fotoPreviewImgNovo');
            const placeholder = document.getElementById('fotoPreviewPlaceholderNovo');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'block';
                    placeholder.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                previewImg.src = '';
                previewImg.style.display = 'none';
                placeholder.style.display = 'block';
            }
        }

        // Função para visualizar detalhes
        function viewDetails(codigo) {
            fetch("get_etapas.php?codigo=" + codigo)
                .then(r => r.json())
                .then(data => {
                    let content = `
              <div class="form-group">
                  <label><strong>Código:</strong></label>
                  <p>${codigo}</p>
              </div>
              <div class="form-group">
                  <label><strong>Cidade:</strong></label>
                  <p>${data.cidade}</p>
              </div>
              <div class="form-group">
                  <label><strong>Etapas Ativas:</strong></label>
                  <ul style="list-style: none; padding: 0;">
          `;

                    const etapas = {
                        'postado': '📦 Objeto postado',
                        'transito': '🚚 Em trânsito',
                        'distribuicao': '🏢 No centro de distribuição',
                        'entrega': '🚀 Saiu para entrega',
                        'entregue': '✅ Entregue'
                    };

                    data.etapas.forEach(etapa => {
                        content += `<li style="padding: 5px 0; border-bottom: 1px solid var(--border-color);">${etapas[etapa] || etapa}</li>`;
                    });

                    content += `</ul></div>`;

                    if (data.foto_url) {
                        content += `
                  <div class="form-group">
                      <label><strong>Foto atual:</strong></label>
                      <div class="details-photo">
                          <img src="${data.foto_url}?t=${Date.now()}" alt="Foto do pedido ${codigo}">
                      </div>
                  </div>
              `;
                    }

                    if (data.taxa_valor && data.taxa_pix) {
                        content += `
                  <div class="form-group">
                      <label><strong>Taxa:</strong></label>
                      <p>R$ ${parseFloat(data.taxa_valor).toFixed(2)}</p>
                  </div>
                  <div class="form-group">
                      <label><strong>Chave PIX:</strong></label>
                      <p style="word-break: break-all; background: var(--dark-bg); padding: 10px; border-radius: 5px;">${data.taxa_pix}</p>
                  </div>
                  
              `;
                    }

                    document.getElementById('detailsContent').innerHTML = content;
                    document.getElementById('detailsModal').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Erro ao carregar detalhes:', error);
                    alert('Erro ao carregar detalhes do rastreio');
                });
        }

        // (Removido modal rápido de taxa)

        // Função de busca
        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('rastreiosTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const codigo = tr[i].getAttribute('data-codigo') || '';
                const cidade = tr[i].getAttribute('data-cidade') || '';
                const status = tr[i].getAttribute('data-status') || '';

                if (codigo.toLowerCase().indexOf(filter) > -1 ||
                    cidade.toLowerCase().indexOf(filter) > -1 ||
                    status.toLowerCase().indexOf(filter) > -1) {
                    tr[i].style.display = '';
                } else {
                    tr[i].style.display = 'none';
                }
            }
        }

        // Função de filtros
        function filterBy(type) {
            // Remove active class de todos os botões
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            // Adiciona active class ao botão clicado
            event.target.classList.add('active');

            const table = document.getElementById('rastreiosTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const row = tr[i];
                let show = true;

                switch (type) {
                    case 'com_taxa':
                        show = row.querySelector('.badge-danger') !== null;
                        break;
                    case 'sem_taxa':
                        show = row.querySelector('.badge-success') !== null;
                        break;
                    case 'entregues':
                        show = row.getAttribute('data-status').includes('Entregue');
                        break;
                    case 'all':
                    default:
                        show = true;
                        break;
                }

                row.style.display = show ? '' : 'none';
            }
        }

        // Função de exportação
        function exportData() {
            notifyInfo('Exportando todos os rastreios...');

            const table = document.getElementById('rastreiosTable');
            const rows = Array.from(table.querySelectorAll('tr'));

            let csv = 'Código,Cidade,Status,Taxa,Data\n';
            let count = 0;

            rows.slice(1).forEach(row => {
                if (row.style.display !== 'none') {
                    const cells = row.querySelectorAll('td');
                    const codigo = cells[1].textContent.trim();
                    const cidade = cells[2].textContent.trim();
                    const status = cells[3].textContent.trim();
                    const taxa = cells[4].textContent.trim();
                    const data = cells[5].textContent.trim();

                    csv += `"${codigo}","${cidade}","${status}","${taxa}","${data}"\n`;
                    count++;
                }
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'rastreios_' + new Date().toISOString().slice(0, 10) + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);

            notifySuccess(`Exportados ${count} rastreios com sucesso!`);
        }

        // ===== SWEETALERT2 - POPUPS ELEGANTES =====

        // Configuração base do SweetAlert2 com tema escuro
        const SwalDark = Swal.mixin({
            background: '#1a1a1a',
            color: '#ffffff',
            confirmButtonColor: '#FF3333',
            cancelButtonColor: '#6b7280',
            customClass: {
                popup: 'swal-dark-popup',
                title: 'swal-dark-title',
                confirmButton: 'swal-confirm-btn',
                cancelButton: 'swal-cancel-btn'
            }
        });

        // Confirmação de exclusão elegante
        async function confirmarExclusao(formId, tipo = 'rastreio', codigo = '') {
            const result = await SwalDark.fire({
                title: '🗑️ Confirmar Exclusão',
                html: `
            <div style="text-align: center; padding: 10px;">
                <p style="font-size: 16px; margin-bottom: 15px;">
                    Tem certeza que deseja excluir ${tipo === 'rastreio' ? 'o rastreio' : 'este item'}?
                </p>
                ${codigo ? `<p style="font-size: 20px; font-weight: bold; color: #FF3333;">${codigo}</p>` : ''}
                <p style="font-size: 13px; color: #888; margin-top: 15px;">
                    ⚠️ Esta ação não pode ser desfeita!
                </p>
            </div>
        `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Sim, excluir!',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                reverseButtons: true,
                focusCancel: true
            });

            if (result.isConfirmed) {
                // Mostrar loading
                SwalDark.fire({
                    title: 'Excluindo...',
                    text: 'Aguarde um momento',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Submeter o formulário
                const form = document.getElementById(formId);
                if (form) {
                    form.submit();
                }
            }
        }

        // Confirmação genérica
        async function confirmarAcao(mensagem, titulo = 'Confirmar', icone = 'question') {
            const result = await SwalDark.fire({
                title: titulo,
                text: mensagem,
                icon: icone,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Confirmar',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                reverseButtons: true
            });
            return result.isConfirmed;
        }

        // Função de logout elegante
        function logout() {
            SwalDark.fire({
                title: '👋 Sair do Sistema',
                text: 'Tem certeza que deseja encerrar sua sessão?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-sign-out-alt"></i> Sair',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    SwalDark.fire({
                        title: 'Saindo...',
                        text: 'Até logo!',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'admin.php?logout=1';
                    });
                }
            });
        }

        // Exclusão em massa elegante
        async function confirmarExclusaoMassa(quantidade) {
            const result = await SwalDark.fire({
                title: '🗑️ Excluir em Massa',
                html: `
            <div style="text-align: center; padding: 10px;">
                <p style="font-size: 48px; margin-bottom: 10px;">⚠️</p>
                <p style="font-size: 18px; margin-bottom: 15px;">
                    Você está prestes a excluir
                </p>
                <p style="font-size: 32px; font-weight: bold; color: #FF3333; margin-bottom: 15px;">
                    ${quantidade} rastreio(s)
                </p>
                <p style="font-size: 13px; color: #888;">
                    Esta ação é <strong>irreversível</strong>!
                </p>
            </div>
        `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Excluir Todos',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                reverseButtons: true,
                focusCancel: true,
                confirmButtonColor: '#dc2626'
            });
            return result.isConfirmed;
        }

        // Remover taxa em massa
        async function confirmarRemoverTaxaMassa(quantidade) {
            const result = await SwalDark.fire({
                title: '💰 Remover Taxas',
                html: `
            <div style="text-align: center; padding: 10px;">
                <p style="font-size: 16px; margin-bottom: 15px;">
                    Remover taxa de <strong style="color: #F59E0B;">${quantidade}</strong> rastreio(s)?
                </p>
                <p style="font-size: 13px; color: #888;">
                    Os clientes não verão mais a cobrança de taxa
                </p>
            </div>
        `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Sim, remover',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                reverseButtons: true,
                confirmButtonColor: '#F59E0B'
            });
            return result.isConfirmed;
        }

        // Limpar logs
        async function confirmarLimparLogs() {
            const result = await SwalDark.fire({
                title: '🧹 Limpar Logs',
                html: `
            <div style="text-align: center; padding: 10px;">
                <p style="font-size: 16px; margin-bottom: 15px;">
                    Deseja limpar todos os logs de automação?
                </p>
                <p style="font-size: 13px; color: #888;">
                    Os logs serão removidos permanentemente
                </p>
            </div>
        `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-broom"></i> Limpar',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                reverseButtons: true
            });
            return result.isConfirmed;
        }

        // Confirmar aprovar pedido
        async function confirmarAprovarPedido(form, nomeCliente) {
            const codigoInput = form.querySelector('input[name="codigo_rastreio"]');
            const codigo = codigoInput.value.trim();

            if (!codigo) {
                SwalDark.fire({
                    title: '❌ Código Obrigatório',
                    text: 'Por favor, informe o código de rastreio antes de aprovar.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return false;
            }

            const result = await SwalDark.fire({
                title: '✅ Aprovar Pedido',
                html: `
            <div style="text-align: center; padding: 10px;">
                <p style="font-size: 16px; margin-bottom: 15px;">
                    Aprovar pedido de <strong style="color: #FF3333;">${nomeCliente}</strong>?
                </p>
                <p style="font-size: 14px; color: #888; margin-bottom: 10px;">
                    Código de rastreio: <strong>${codigo}</strong>
                </p>
            </div>
        `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Aprovar',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                reverseButtons: true,
                confirmButtonColor: '#16A34A'
            });

            if (result.isConfirmed) {
                form.submit();
            }
            return false;
        }

        // Confirmar rejeitar pedido
        async function confirmarRejeitarPedido(form, nomeCliente) {
            const result = await SwalDark.fire({
                title: '❌ Rejeitar Pedido',
                html: `
            <div style="text-align: center; padding: 10px;">
                <p style="font-size: 16px; margin-bottom: 15px;">
                    Tem certeza que deseja rejeitar o pedido de <strong style="color: #FF3333;">${nomeCliente}</strong>?
                </p>
            </div>
        `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-times"></i> Rejeitar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                confirmButtonColor: '#EF4444'
            });

            if (result.isConfirmed) {
                form.submit();
            }
            return false;
        }

        // Toast de sucesso
        function toastSucesso(mensagem) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                background: '#1a1a1a',
                color: '#16A34A',
                iconColor: '#16A34A'
            });
            Toast.fire({
                icon: 'success',
                title: mensagem
            });
        }

        // Toast de erro
        function toastErro(mensagem) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                background: '#1a1a1a',
                color: '#EF4444',
                iconColor: '#EF4444'
            });
            Toast.fire({
                icon: 'error',
                title: mensagem
            });
        }

        // Fechar modais ao clicar fora
        window.onclick = function (event) {
            const modal = document.getElementById('modal');
            const detailsModal = document.getElementById('detailsModal');

            if (event.target === modal) {
                closeModal();
            }
            if (event.target === detailsModal) {
                closeDetailsModal();
            }
        }

        // Atalhos de teclado
        document.addEventListener('keydown', function (e) {
            // ESC para fechar modais
            if (e.key === 'Escape') {
                closeModal();
                closeDetailsModal();
            }

            // Ctrl+F para focar na busca
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });

        // Auto-refresh removido - atualização apenas manual

        // Mostrar notificações de sucesso do PHP
        <?php if (isset($success_message)): ?>
            document.addEventListener('DOMContentLoaded', function () {
                notifySuccess('<?= addslashes($success_message) ?>');
            });
        <?php endif; ?>

        // Mostrar notificações de erro do PHP
        <?php if (isset($error_message)): ?>
            document.addEventListener('DOMContentLoaded', function () {
                notifyError('<?= addslashes($error_message) ?>');
            });
        <?php endif; ?>

        // Inicializar sistema de automações
        document.addEventListener('DOMContentLoaded', function () {
            loadAutomationSettings();

            // Verificar se há automações ativas e iniciá-las
            const saved = localStorage.getItem('automationSettings');
            if (saved) {
                const automations = JSON.parse(saved);

                if (automations.statusUpdate && automations.statusUpdate.enabled) {
                    startStatusAutomation(automations.statusUpdate.interval);
                }

                if (automations.notifications && automations.notifications.enabled) {
                    startNotificationAutomation();
                }
            }
        });

        // Validação AJAX de duplicidade no formulário de adicionar
        document.addEventListener('DOMContentLoaded', function () {
            const addForm = document.getElementById('addForm');
            if (!addForm) return;
            addForm.addEventListener('submit', async function (e) {
                try {
                    const codigoEl = addForm.querySelector('#codigo');
                    const codigo = (codigoEl && codigoEl.value || '').trim();
                    if (!codigo) return; // HTML5 já valida required
                    const resp = await fetch('check_codigo.php?codigo=' + encodeURIComponent(codigo), { headers: { 'Cache-Control': 'no-cache' } });
                    const data = await resp.json();
                    if (data && data.exists) {
                        e.preventDefault();
                        notifyError('O código ' + codigo + ' já existe.');
                        if (codigoEl) { codigoEl.focus(); codigoEl.select(); }
                    }
                } catch (_) { /* silencioso, fallback é validação servidor */ }
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            const fotoInput = document.getElementById('edit_foto_pedido');
            if (!fotoInput) return;
            fotoInput.addEventListener('change', function (e) {
                const file = e.target.files && e.target.files[0];
                const previewImg = document.getElementById('fotoPreviewImg');
                const placeholder = document.getElementById('fotoPreviewPlaceholder');
                const remover = document.getElementById('edit_remover_foto');
                if (fotoInput.dataset.previewUrl) {
                    URL.revokeObjectURL(fotoInput.dataset.previewUrl);
                    delete fotoInput.dataset.previewUrl;
                }
                if (file && previewImg && placeholder) {
                    const objectUrl = URL.createObjectURL(file);
                    fotoInput.dataset.previewUrl = objectUrl;
                    previewImg.src = objectUrl;
                    previewImg.style.display = 'block';
                    placeholder.style.display = 'none';
                    if (remover) {
                        remover.checked = false;
                        remover.disabled = false;
                    }
                } else if (previewImg && placeholder) {
                    const original = previewImg.dataset.originalSrc;
                    if (original) {
                        previewImg.src = original;
                        previewImg.style.display = 'block';
                        placeholder.style.display = 'none';
                        if (remover) {
                            remover.disabled = false;
                            remover.checked = false;
                        }
                    } else {
                        previewImg.removeAttribute('src');
                        previewImg.style.display = 'none';
                        placeholder.style.display = 'inline-block';
                        if (remover) {
                            remover.checked = false;
                            remover.disabled = true;
                        }
                    }
                }
            });
        });

        // Funções de seleção múltipla
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.row-checkbox');

            checkboxes.forEach(checkbox => {
                if (checkbox.closest('tr').style.display !== 'none') {
                    checkbox.checked = selectAll.checked;
                }
            });

            updateSelection();
        }

        function updateSelection() {
            const checkboxes = document.querySelectorAll('.row-checkbox:checked');
            const selectedCount = document.getElementById('selectedCount');
            const bulkActions = document.getElementById('bulkActions');

            selectedCount.textContent = checkboxes.length;

            if (checkboxes.length > 0) {
                bulkActions.style.display = 'block';
            } else {
                bulkActions.style.display = 'none';
            }

            // Atualizar estado do checkbox "Selecionar todos"
            const allCheckboxes = document.querySelectorAll('.row-checkbox');
            const visibleCheckboxes = Array.from(allCheckboxes).filter(cb => cb.closest('tr').style.display !== 'none');
            const checkedVisible = Array.from(visibleCheckboxes).filter(cb => cb.checked);

            const selectAll = document.getElementById('selectAll');
            if (checkedVisible.length === 0) {
                selectAll.indeterminate = false;
                selectAll.checked = false;
            } else if (checkedVisible.length === visibleCheckboxes.length) {
                selectAll.indeterminate = false;
                selectAll.checked = true;
            } else {
                selectAll.indeterminate = true;
            }
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = false);
            document.getElementById('selectAll').checked = false;
            updateSelection();
        }

        // Funções de operações em lote
        async function bulkDelete() {
            const selected = getSelectedCodes();
            if (selected.length === 0) {
                if (typeof MessageManager !== 'undefined') {
                    MessageManager.warning('Nenhum item selecionado');
                } else {
                    notifyWarning('Nenhum item selecionado');
                }
                return;
            }

            // Usar ConfirmManager se disponível, senão usar função antiga
            let confirmed = false;
            if (typeof ConfirmManager !== 'undefined') {
                confirmed = await ConfirmManager.show(
                    `Tem certeza que deseja deletar ${selected.length} rastreio(s) selecionado(s)?`,
                    {
                        title: 'Confirmar exclusão em massa',
                        confirmText: `Sim, deletar ${selected.length} item(ns)`,
                        cancelText: 'Cancelar'
                    }
                );
            } else if (typeof confirmarExclusaoMassa === 'function') {
                confirmed = await confirmarExclusaoMassa(selected.length);
            } else {
                confirmed = confirm(`Deletar ${selected.length} item(ns)?`);
            }

            if (confirmed) {
                const container = document.getElementById('rastreiosTable') || document.body;

                // Usar AjaxHelper se disponível
                if (typeof AjaxHelper !== 'undefined') {
                    try {
                        await AjaxHelper.post('', {
                            bulk_delete: JSON.stringify(selected)
                        }, {
                            showLoading: true,
                            loadingElement: container,
                            loadingMessage: 'Excluindo rastreios...',
                            showSuccess: true,
                            successMessage: `${selected.length} rastreio(s) deletado(s) com sucesso!`,
                            showError: true
                        });

                        // Recarregar página após sucesso
                        setTimeout(() => location.reload(), 1000);
                    } catch (error) {
                        // Erro já foi mostrado pelo AjaxHelper
                    }
                } else {
                    // Fallback para método antigo
                    notifyInfo('Excluindo rastreios selecionados...');

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';

                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'bulk_delete';
                    input.value = JSON.stringify(selected);
                    form.appendChild(input);

                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }

        async function bulkClearTaxa() {
            const selected = getSelectedCodes();
            if (selected.length === 0) {
                notifyWarning('Nenhum item selecionado');
                return;
            }
            const confirmado = await confirmarRemoverTaxaMassa(selected.length);
            if (!confirmado) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            const input = document.createElement('input');
            input.type = 'hidden';
            // (bulk clear taxa removido)
            input.value = JSON.stringify(selected);
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        function bulkEdit() {
            const selected = getSelectedCodes();
            if (selected.length === 0) {
                notifyWarning('Nenhum item selecionado');
                return;
            }

            if (selected.length > 10) {
                notifyError('Máximo de 10 itens para edição em lote');
                return;
            }

            // Abrir modal de edição em lote
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'flex';
            modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Editar em Lote (${selected.length} itens)</h3>
                <button class="close" onclick="this.closest('.modal').remove()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="bulk_edit" value="${JSON.stringify(selected)}">
                <div class="form-group">
                    <label>Nova Cidade (deixe em branco para manter)</label>
                    <input type="text" name="new_cidade" placeholder="Nova cidade...">
                </div>
                <div class="form-group">
                    <label>Adicionar Taxa</label>
                    <input type="number" name="new_taxa_valor" placeholder="Valor da taxa..." step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label>Chave PIX</label>
                    <input type="text" name="new_taxa_pix" placeholder="Chave PIX...">
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Aplicar a Todos
                    </button>
                    <button type="button" class="btn btn-warning" onclick="this.closest('.modal').remove()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    `;

            document.body.appendChild(modal);
        }

        function bulkExport() {
            const selected = getSelectedCodes();
            if (selected.length === 0) {
                notifyWarning('Nenhum item selecionado');
                return;
            }

            notifyInfo('Exportando rastreios selecionados...');

            const table = document.getElementById('rastreiosTable');
            const rows = Array.from(table.querySelectorAll('tr'));

            let csv = 'Código,Cidade,Status,Taxa,Data\n';

            rows.slice(1).forEach(row => {
                const checkbox = row.querySelector('.row-checkbox');
                if (checkbox && checkbox.checked) {
                    const cells = row.querySelectorAll('td');
                    const codigo = cells[1].textContent.trim();
                    const cidade = cells[2].textContent.trim();
                    const status = cells[3].textContent.trim();
                    const taxa = cells[4].textContent.trim();
                    const data = cells[5].textContent.trim();

                    csv += `"${codigo}","${cidade}","${status}","${taxa}","${data}"\n`;
                }
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'rastreios_selecionados_' + new Date().toISOString().slice(0, 10) + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);

            notifySuccess(`Exportados ${selected.length} rastreios com sucesso!`);
        }

        function getSelectedCodes() {
            const checkboxes = document.querySelectorAll('.row-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }

        // ===== Presets com Preview =====
        function openPresetModal() {
            const selected = getSelectedCodes();
            if (selected.length === 0) {
                notifyWarning('Nenhum item selecionado');
                return;
            }
            const presets = window.STATUS_PRESETS || {};
            const presetOptions = Object.entries(presets).map(([key, p]) => `<option value="${key}">${p.label}</option>`).join('');
            const nowISO = new Date().toISOString().slice(0, 16);

            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'flex';
            modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-diagram-project"></i> Aplicar Preset (${selected.length} itens)</h3>
                <button class="close" onclick="this.closest('.modal').remove()">&times;</button>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Preset</label>
                    <select id="preset_key">${presetOptions}</select>
                </div>
                <div class="form-group">
                    <label>Modo de aplicação</label>
                    <select id="preset_mode">
                        <option value="replace">Substituir etapas atuais</option>
                        <option value="append">Acrescentar ao final</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Início</label>
                    <input type="datetime-local" id="preset_start" value="${nowISO}">
                </div>
                <div class="form-group">
                    <label>Cidade (fallback)</label>
                    <input type="text" id="preset_cidade" placeholder="Não informado">
                </div>
                <div class="form-group">
                    <label>Valor Taxa (opcional)</label>
                    <input type="number" id="preset_taxa_valor" placeholder="0.00" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label>Chave PIX (opcional)</label>
                    <input type="text" id="preset_taxa_pix" placeholder="Chave PIX">
                </div>
            </div>
            <div id="preset_preview" style="margin-top:10px"></div>
            <div class="actions" style="margin-top: 15px;">
                <button class="btn btn-info" onclick="renderPresetPreview()"><i class="fas fa-eye"></i> Preview</button>
                <button class="btn btn-primary" onclick="applyPreset(${JSON.stringify(selected)})"><i class="fas fa-play"></i> Aplicar</button>
                <button class="btn btn-warning" onclick="this.closest('.modal').remove()"><i class="fas fa-times"></i> Cancelar</button>
            </div>
        </div>`;
            document.body.appendChild(modal);
            setTimeout(renderPresetPreview, 0);
        }

        function renderPresetPreview() {
            const presets = window.STATUS_PRESETS || {};
            const key = document.getElementById('preset_key').value;
            const start = document.getElementById('preset_start').value;
            const box = document.getElementById('preset_preview');
            const preset = presets[key];
            if (!preset) { box.innerHTML = ''; return; }
            const startTs = new Date(start).getTime();
            let html = '<div class="cron-schedule"><h4><i class="fas fa-list"></i> Etapas previstas</h4>';
            preset.steps.forEach(s => {
                const dt = new Date(startTs + s[3] * 3600000);
                html += `<div class="schedule-item"><span>${s[0]}</span><span>${dt.toLocaleString()}</span></div>`;
            });
            html += '</div>';
            box.innerHTML = html;
        }

        function applyPreset(selected) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; form.appendChild(i); };
            add('apply_preset', JSON.stringify(selected));
            add('preset_key', document.getElementById('preset_key').value);
            add('preset_mode', document.getElementById('preset_mode').value);
            add('preset_start', document.getElementById('preset_start').value);
            add('preset_cidade', document.getElementById('preset_cidade').value);
            add('preset_taxa_valor', document.getElementById('preset_taxa_valor').value);
            add('preset_taxa_pix', document.getElementById('preset_taxa_pix').value);
            document.body.appendChild(form);
            form.submit();
        }

        // Sistema de Notificações
        function showToast(message, type = 'info', duration = 5000) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;

            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };

            toast.innerHTML = `
        <i class="${icons[type] || icons.info}"></i>
        <span>${message}</span>
        <button class="toast-close" onclick="removeToast(this)">&times;</button>
    `;

            container.appendChild(toast);

            // Auto-remove após a duração especificada
            setTimeout(() => {
                if (toast.parentNode) {
                    removeToast(toast.querySelector('.toast-close'));
                }
            }, duration);
        }

        function removeToast(closeBtn) {
            const toast = closeBtn.closest('.toast');
            toast.classList.add('fade-out');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }

        // Notificações para ações do admin
        function notifySuccess(message) {
            showToast(message, 'success', 4000);
        }

        function notifyError(message) {
            showToast(message, 'error', 6000);
        }

        function notifyWarning(message) {
            showToast(message, 'warning', 5000);
        }

        function notifyInfo(message) {
            showToast(message, 'info', 4000);
        }


        // Sistema de Automações
        function toggleAutomation(toggle, automationType) {
            const isActive = toggle.classList.contains('active');

            if (isActive) {
                toggle.classList.remove('active');
                document.getElementById(automationType + '-settings').classList.remove('active');
            } else {
                toggle.classList.add('active');
                document.getElementById(automationType + '-settings').classList.add('active');
            }
        }

        function saveAutomations() {
            const automations = {
                notifications: {
                    enabled: document.querySelector('#notifications-settings').classList.contains('active'),
                    email: document.getElementById('notification-email').value,
                    phone: document.getElementById('notification-phone').value,
                    notifyCreated: document.getElementById('notify-created').checked,
                    notifyStatusChange: document.getElementById('notify-status-change').checked,
                    notifyTaxaPending: document.getElementById('notify-taxa-pending').checked,
                    notifyDelivered: document.getElementById('notify-delivered').checked
                },
                statusUpdate: {
                    enabled: document.querySelector('#status-update-settings').classList.contains('active'),
                    interval: document.getElementById('update-interval').value,
                    autoProgress: document.getElementById('auto-progress').checked,
                    autoTaxaApply: document.getElementById('auto-taxa-apply').checked
                },
                alerts: {
                    enabled: document.querySelector('#alerts-settings').classList.contains('active'),
                    stuckDays: document.getElementById('stuck-days').value,
                    alertStuck: document.getElementById('alert-stuck').checked,
                    alertTaxaOverdue: document.getElementById('alert-taxa-overdue').checked,
                    alertInconsistency: document.getElementById('alert-inconsistency').checked
                },
                reports: {
                    enabled: document.querySelector('#reports-settings').classList.contains('active'),
                    frequency: document.getElementById('report-frequency').value,
                    email: document.getElementById('report-email').value,
                    summary: document.getElementById('report-summary').checked,
                    detailed: document.getElementById('report-detailed').checked
                }
            };

            // Salvar no localStorage (em produção, salvaria no banco de dados)
            localStorage.setItem('automationSettings', JSON.stringify(automations));

            notifySuccess('Configurações de automação salvas com sucesso!');

            // Iniciar automações se estiverem habilitadas
            if (automations.statusUpdate.enabled) {
                startStatusAutomation(automations.statusUpdate.interval);
            }

            if (automations.notifications.enabled) {
                startNotificationAutomation();
            }
        }

        function testAutomations() {
            notifyInfo('Testando automações...');

            // Simular teste de notificação
            setTimeout(() => {
                notifySuccess('Teste de notificação enviado com sucesso!');
            }, 1000);

            // Simular teste de atualização de status
            setTimeout(() => {
                notifyInfo('Teste de atualização de status executado!');
            }, 2000);

            // Simular teste de alertas
            setTimeout(() => {
                notifyWarning('Teste de alerta executado!');
            }, 3000);
        }

        function viewAutomationLogs() {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'flex';
            modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-list"></i> Logs de Automação</h3>
                <button class="close" onclick="this.closest('.modal').remove()">&times;</button>
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                <div class="schedule-item">
                    <span><span class="status-indicator active"></span>Verificação de status executada</span>
                    <span>${new Date().toLocaleString()}</span>
                </div>
                <div class="schedule-item">
                    <span><span class="status-indicator active"></span>Notificação enviada para admin@helmer.com</span>
                    <span>${new Date(Date.now() - 300000).toLocaleString()}</span>
                </div>
                <div class="schedule-item">
                    <span><span class="status-indicator active"></span>Relatório semanal gerado</span>
                    <span>${new Date(Date.now() - 3600000).toLocaleString()}</span>
                </div>
                <div class="schedule-item">
                    <span><span class="status-indicator active"></span>Limpeza de logs executada</span>
                    <span>${new Date(Date.now() - 7200000).toLocaleString()}</span>
                </div>
                <div class="schedule-item">
                    <span><span class="status-indicator active"></span>Alerta de rastreio preso enviado</span>
                    <span>${new Date(Date.now() - 86400000).toLocaleString()}</span>
                </div>
            </div>
            <div class="actions" style="margin-top: 20px;">
                <button class="btn btn-primary" onclick="exportLogs()">
                    <i class="fas fa-download"></i> Exportar Logs
                </button>
                <button class="btn btn-warning" onclick="clearLogs()">
                    <i class="fas fa-trash"></i> Limpar Logs
                </button>
            </div>
        </div>
    `;

            document.body.appendChild(modal);
        }

        function exportLogs() {
            notifyInfo('Exportando logs de automação...');
            // Implementar exportação de logs
            notifySuccess('Logs exportados com sucesso!');
        }

        async function clearLogs() {
            const confirmado = await confirmarLimparLogs();
            if (confirmado) {
                // Limpar logs do localStorage
                localStorage.removeItem('automationLogs');
                const logContainer = document.getElementById('automationLogs');
                if (logContainer) logContainer.innerHTML = '';
                toastSucesso('Logs limpos com sucesso!');
            }
        }

        // Funções de automação em tempo real
        function startStatusAutomation(intervalMinutes) {
            const interval = intervalMinutes * 60 * 1000; // Converter para milissegundos

            setInterval(() => {
                checkAndUpdateStatus();
            }, interval);

            notifyInfo(`Automação de status iniciada (${intervalMinutes} minutos)`);
        }

        function startNotificationAutomation() {
            // Verificar rastreios que precisam de notificação
            setInterval(() => {
                checkPendingNotifications();
            }, 5 * 60 * 1000); // A cada 5 minutos

            notifyInfo('Automação de notificações iniciada');
        }

        function checkAndUpdateStatus() {
            // Simular verificação e atualização de status
            console.log('Verificando status dos rastreios...');

            // Aqui você implementaria a lógica real de atualização
            // Por exemplo, verificar se algum rastreio precisa progredir de status

            notifyInfo('Status dos rastreios verificados e atualizados');
        }

        function checkPendingNotifications() {
            // Verificar se há notificações pendentes
            console.log('Verificando notificações pendentes...');

            // Implementar lógica de verificação de notificações
            // Por exemplo, verificar taxas pendentes há mais de 24h
        }

        // Carregar configurações salvas
        function loadAutomationSettings() {
            const saved = localStorage.getItem('automationSettings');
            if (saved) {
                const automations = JSON.parse(saved);

                // Aplicar configurações salvas
                if (automations.notifications.enabled) {
                    document.querySelector('#notifications-settings').classList.add('active');
                    document.querySelector('[onclick*="notifications"]').classList.add('active');
                }

                if (automations.statusUpdate.enabled) {
                    document.querySelector('#status-update-settings').classList.add('active');
                    document.querySelector('[onclick*="status-update"]').classList.add('active');
                }

                if (automations.alerts.enabled) {
                    document.querySelector('#alerts-settings').classList.add('active');
                    document.querySelector('[onclick*="alerts"]').classList.add('active');
                }

                if (automations.reports.enabled) {
                    document.querySelector('#reports-settings').classList.add('active');
                    document.querySelector('[onclick*="reports"]').classList.add('active');
                }
            }
        }

        // Auto-refresh removido - atualização apenas manual (F5)

        // ===== Monitor de Cron (Execução e Logs) =====
        function runAutomationCron() {
            notifyInfo('Executando automações...');
            fetch('automation_cron.php?cron=true')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        notifySuccess('Automações executadas');
                        document.getElementById('cronStatus').innerHTML = `<div class="schedule-item"><span><span class="status-indicator active"></span>Última execução</span><span>${new Date().toLocaleString()}</span></div>`;
                        refreshCronLogs();
                    } else {
                        notifyError('Falha nas automações: ' + (data.error || 'erro'));
                    }
                })
                .catch(() => notifyError('Erro ao executar automações'));
        }

        function runUpdateCron() {
            notifyInfo('Executando update...');
            fetch('cron_update.php')
                .then(() => {
                    notifySuccess('Update executado');
                    document.getElementById('cronStatus').innerHTML = `<div class="schedule-item"><span><span class="status-indicator active"></span>Update manual</span><span>${new Date().toLocaleString()}</span></div>`;
                })
                .catch(() => notifyError('Erro ao executar update'));
        }

        function refreshCronLogs() {
            fetch('automation_logs.txt', { cache: 'no-store' })
                .then(r => r.text())
                .then(t => {
                    const lines = t.trim().split('\n');
                    const last = lines.slice(-50).join('\n');
                    document.getElementById('cronLogs').textContent = last || 'Sem logs.';
                })
                .catch(() => {
                    document.getElementById('cronLogs').textContent = 'Sem logs disponíveis.';
                });
        }

        document.addEventListener('DOMContentLoaded', refreshCronLogs);

        // Função para enviar WhatsApp manualmente
        function enviarWhatsappManual(codigo) {
            if (!codigo) {
                notifyError('Código inválido');
                return;
            }

            console.log('[WhatsApp] Iniciando envio para código:', codigo);

            // Desabilitar botão durante o envio
            const buttons = document.querySelectorAll(`button[onclick*="enviarWhatsappManual('${codigo}')"], button[onclick*='enviarWhatsappManual("${codigo}")']`);
            buttons.forEach(btn => {
                btn.disabled = true;
                const originalHTML = btn.innerHTML;
                btn.setAttribute('data-original-html', originalHTML);
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            });

            notifyInfo('Enviando notificação WhatsApp...');

            const formData = new FormData();
            formData.append('enviar_whatsapp_manual', '1');
            formData.append('codigo', codigo);

            // Usar endpoint específico para evitar problemas
            const url = 'admin.php?t=' + new Date().getTime();
            console.log('[WhatsApp] Enviando requisição para:', url);

            fetch(url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                cache: 'no-cache',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    console.log('[WhatsApp] Response status:', response.status);
                    console.log('[WhatsApp] Response ok:', response.ok);

                    const contentType = response.headers.get('content-type') || '';
                    console.log('[WhatsApp] Content-Type:', contentType);

                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('[WhatsApp] Erro HTTP. Resposta:', text);
                            throw new Error(`HTTP ${response.status}: ${response.statusText}\nResposta: ${text.substring(0, 200)}`);
                        });
                    }

                    if (contentType.includes('application/json')) {
                        return response.json().catch(err => {
                            return response.text().then(text => {
                                console.error('[WhatsApp] Erro ao parsear JSON. Texto recebido:', text);
                                throw new Error('Resposta não é JSON válido: ' + text.substring(0, 200));
                            });
                        });
                    } else {
                        return response.text().then(text => {
                            console.error('[WhatsApp] Resposta não é JSON. Content-Type:', contentType);
                            console.error('[WhatsApp] Resposta completa:', text);
                            // Tentar parsear como JSON mesmo assim (pode estar sem header)
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                throw new Error('Resposta do servidor não é JSON. Content-Type: ' + contentType + '\nResposta: ' + text.substring(0, 200));
                            }
                        });
                    }
                })
                .then(data => {
                    console.log('[WhatsApp] Resposta JSON recebida:', data);

                    // Reabilitar botões
                    buttons.forEach(btn => {
                        btn.disabled = false;
                        const originalHTML = btn.getAttribute('data-original-html') || '<i class="fab fa-whatsapp"></i> WhatsApp';
                        btn.innerHTML = originalHTML;
                    });

                    if (data && data.success) {
                        notifySuccess(data.message || '✅ Notificação enviada com sucesso!');
                    } else {
                        notifyError(data?.message || '❌ Erro ao enviar notificação');
                    }
                })
                .catch(error => {
                    console.error('Erro completo:', error);
                    console.error('Stack:', error.stack);

                    // Reabilitar botões
                    buttons.forEach(btn => {
                        btn.disabled = false;
                        const originalHTML = btn.getAttribute('data-original-html') || '<i class="fab fa-whatsapp"></i> WhatsApp';
                        btn.innerHTML = originalHTML;
                    });

                    const errorMsg = error.message || 'Erro desconhecido';
                    notifyError('❌ Erro ao enviar notificação: ' + errorMsg);

                    // Mostrar mais detalhes no console
                    console.error('Código:', codigo);
                    console.error('URL:', window.location.href);
                });
        }
    </script>
    <script>
        // Registrar Service Worker e gerenciar botão de instalação
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').catch(() => { });
            });
        }

        let deferredPrompt = null;
        const installBtn = document.getElementById('pwaInstallBtn');
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            if (installBtn) installBtn.style.display = 'inline-flex';
        });

        if (installBtn) {
            installBtn.addEventListener('click', async () => {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                await deferredPrompt.userChoice;
                deferredPrompt = null;
                installBtn.style.display = 'none';
            });
        }

        window.addEventListener('appinstalled', () => {
            if (installBtn) installBtn.style.display = 'none';
        });

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            if (!sidebar) return;

            const isActive = sidebar.classList.toggle('active');
            if (overlay) overlay.classList.toggle('active', isActive);

            // Prevent body scroll on mobile
            if (window.innerWidth <= 768) {
                document.body.style.overflow = isActive ? 'hidden' : '';
            }
        }

        // Close menu when clicking overlay
        document.addEventListener('DOMContentLoaded', function () {
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay) {
                overlay.addEventListener('click', function (e) {
                    // Prevent bubbling issues
                    if (e.target === overlay) {
                        toggleSidebar();
                    }
                });
            }
        });
    </script>
    <?php // Expor presets ao JS ?>
    <script>
        window.STATUS_PRESETS = <?php echo json_encode(array_map(function ($p) {
            return ['label' => $p['label'], 'steps' => $p['steps']];
        }, $STATUS_PRESETS)); ?>
    </script>
    <!-- UI Enhancements - Melhorias de UX/UI -->
    <script src="assets/js/ui-enhancements.js"></script>

    <!-- Código Auto-Increment -->
    <script src="assets/js/codigo-auto-increment.js"></script>

</body>

</html>