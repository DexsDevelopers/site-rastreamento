<?php
/**
 * Painel Administrativo Helmer Logistics
 * Versão otimizada e segura
 */

// Incluir configurações e DB
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

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

function captureUndoSnapshot($pdo, $codigos, $label) {
    if (empty($codigos)) { return; }
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

function restoreUndoSnapshot($pdo) {
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
    if (empty($rows)) { unset($_SESSION['undo_action']); return [true, '']; }
    // Preparar colunas (ignorar id se existir)
    $cols = array_keys($rows[0]);
    $cols = array_values(array_filter($cols, function($c){ return strtolower($c) !== 'id'; }));
    $colList = implode(',', $cols);
    $place = implode(',', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO rastreios_status ($colList) VALUES ($place)";
    foreach ($rows as $r) {
        $vals = [];
        foreach ($cols as $c) { $vals[] = $r[$c] ?? null; }
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

function aplicarPresetAoCodigo($pdo, $codigo, $cidade, $inicio, $preset, $taxa_valor = null, $taxa_pix = null) {
    foreach ($preset['steps'] as $step) {
        list($titulo, $subtitulo, $cor, $offsetHours) = $step;
        $data = date('Y-m-d H:i:s', strtotime("+{$offsetHours} hour", $inicio));
        $status_atual = $titulo;
        $sql = "INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, data, cor, taxa_valor, taxa_pix) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        executeQuery($pdo, $sql, [$codigo, $cidade, $status_atual, $titulo, $subtitulo, $data, $cor, $taxa_valor ?: null, $taxa_pix ?: null]);
    }
}

if (!isset($_SESSION['logado'])) {
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Helmer Admin - Login</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<style>
    :root { --primary:#FF3333; --accent:#FF6600; --bg:#0A0A0A; --card:#1A1A1A; --text:#FFF; --muted:#A3A3A3; }
    * { box-sizing: border-box; }
    body { margin:0; background: linear-gradient(135deg, #0A0A0A 0%, #1A0000 100%); color: var(--text); font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Arial; min-height: 100vh; display: grid; place-items: center; }
    .login-wrap { width: 100%; max-width: 420px; padding: 20px; }
    .card { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.18); border-radius: 18px; padding: 28px; box-shadow: 0 12px 32px rgba(0,0,0,0.35); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
    .brand { display:flex; align-items:center; gap:12px; margin-bottom: 10px; }
    .brand .logo { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--accent)); display:flex; align-items:center; justify-content:center; box-shadow: 0 6px 18px rgba(255,51,51,0.35); }
    .brand h1 { margin:0; font-size: 1.4rem; letter-spacing: .2px; }
    .subtitle { color: var(--muted); font-size: .95rem; margin-bottom: 18px; }
    .alert { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.35); color: #fecaca; padding: 10px 12px; border-radius: 10px; margin-bottom: 14px; font-size: .95rem; }
    .input-group { position: relative; margin-bottom: 14px; }
    .input-group .icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#ffb3b3; }
    .input { width: 100%; padding: 14px 14px 14px 40px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.18); background: rgba(0,0,0,0.35); color: var(--text); outline: none; transition: border .2s ease, box-shadow .2s ease; }
    .input:focus { border-color: rgba(255,102,0,.6); box-shadow: 0 0 0 4px rgba(255,102,0,.15); }
    .toggle-pass { position:absolute; right:10px; top:50%; transform: translateY(-50%); background: none; border: none; color: #ffb3b3; cursor: pointer; font-weight: 700; }
    .actions { margin-top: 6px; display:flex; gap:10px; align-items:center; }
    .btn-primary { flex:1; padding: 12px 16px; border: none; border-radius: 12px; cursor: pointer; color: #fff; background: linear-gradient(135deg, var(--primary), var(--accent)); font-weight: 800; letter-spacing:.2px; box-shadow: 0 10px 24px rgba(255,51,51,.35); transition: transform .15s ease; }
    .btn-primary:hover { transform: translateY(-1px); }
    .links { margin-top: 10px; display:flex; justify-content: space-between; font-size: .92rem; }
    .links a { color: #ffb3b3; text-decoration: none; }
    .footer { margin-top: 16px; text-align:center; color: var(--muted); font-size:.85rem; }
    @media (max-width:480px){ .card { padding: 22px; } }
</style>
</head>
<body>
<div class="login-wrap">
    <div class="card">
        <div class="brand">
            <div class="logo">⚡</div>
            <h1>Helmer Admin</h1>
        </div>
        <div class="subtitle">Acesse o painel administrativo</div>
        <?php if (!empty($erro)) echo '<div class="alert">' . htmlspecialchars($erro) . '</div>'; ?>
        <form method="POST">
            <div class="input-group">
                <span class="icon">👤</span>
                <input class="input" type="text" name="user" placeholder="Usuário" required>
            </div>
            <div class="input-group">
                <span class="icon">🔒</span>
                <input class="input" type="password" name="pass" id="passField" placeholder="Senha" required>
                <button class="toggle-pass" type="button" onclick="togglePass()">ver</button>
            </div>
            <div class="actions">
                <button type="submit" name="login" class="btn-primary">Entrar</button>
            </div>
        </form>
        <div class="links">
            <a href="index.php">Página inicial</a>
            <span></span>
        </div>
        <div class="footer">© <?= date('Y') ?> Helmer Logistics</div>
    </div>
    <script>
    function togglePass(){
        var f = document.getElementById('passField');
        if(!f) return; f.type = f.type === 'password' ? 'text' : 'password';
    }
    </script>
</div>
    <!-- UI Enhancements - Melhorias de UX/UI -->
    <script src="assets/js/ui-enhancements.js"></script>
    
    <!-- Código Auto-Increment -->
    <script src="assets/js/codigo-auto-increment.js"></script>
    
</body>
</html>
<?php exit; } ?>

<?php
// Função para adicionar etapas (versão segura)
function adicionarEtapas($pdo, $codigo, $cidade, $dataInicial, $etapasMarcadas, $taxa_valor, $taxa_pix) {
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Painel Admin - Helmer Logistics</title>
<meta name="theme-color" content="#FF3333">
<meta name="description" content="Painel administrativo Helmer Logistics - Gerencie rastreamentos, mensagens e configurações">
<meta name="keywords" content="helmer, logistics, admin, rastreamento">
<meta name="author" content="Helmer Logistics">

<!-- PWA Meta Tags -->
<link rel="manifest" href="manifest.webmanifest">
<link rel="apple-touch-icon" href="assets/images/whatsapp-1.jpg">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Helmer Admin">
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="Helmer Admin">

<!-- Preconnect para melhor performance -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://cdnjs.cloudflare.com">
<link rel="preconnect" href="https://cdn.jsdelivr.net">

<!-- CSS Mobile Responsivo -->
<link rel="stylesheet" href="assets/css/admin-mobile.css">
<style>
/* FORÇAR MENU HAMBÚRGUER VISÍVEL NO MOBILE - CSS INLINE */
@media screen and (max-width: 768px) {
    #navToggleBtn,
    .nav-toggle {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        position: fixed !important;
        top: 16px !important;
        left: 16px !important;
        z-index: 10001 !important;
        width: 44px !important;
        height: 44px !important;
        background: rgba(26, 26, 26, 0.95) !important;
        border: 2px solid rgba(255, 51, 51, 0.3) !important;
        border-radius: 8px !important;
        cursor: pointer !important;
        flex-direction: column !important;
        justify-content: center !important;
        align-items: center !important;
        gap: 4px !important;
    }
    
    #navToggleBtn span,
    .nav-toggle span {
        display: block !important;
        width: 24px !important;
        height: 2px !important;
        background: #FF3333 !important;
        border-radius: 2px !important;
    }
}
</style>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- SweetAlert2 para popups bonitos -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Script imediato para mostrar menu hambúrguer no mobile
(function() {
    function forceMenuVisible() {
        const btn = document.getElementById('navToggleBtn') || document.querySelector('.nav-toggle');
        if (btn && (window.innerWidth <= 768 || window.matchMedia('(max-width: 768px)').matches)) {
            btn.style.cssText = 'display: flex !important; visibility: visible !important; opacity: 1 !important; position: fixed !important; top: 16px !important; left: 16px !important; z-index: 10001 !important; width: 44px !important; height: 44px !important; background: rgba(26, 26, 26, 0.95) !important; border: 2px solid rgba(255, 51, 51, 0.3) !important; border-radius: 8px !important; cursor: pointer !important; flex-direction: column !important; justify-content: center !important; align-items: center !important; gap: 4px !important;';
            const spans = btn.querySelectorAll('span');
            spans.forEach(s => s.style.cssText = 'display: block !important; width: 24px !important; height: 2px !important; background: #FF3333 !important; border-radius: 2px !important;');
        }
    }
    
    // Executar quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', forceMenuVisible);
    } else {
        forceMenuVisible();
    }
    
    // Executar múltiplas vezes
    setTimeout(forceMenuVisible, 10);
    setTimeout(forceMenuVisible, 50);
    setTimeout(forceMenuVisible, 100);
    setTimeout(forceMenuVisible, 300);
    window.addEventListener('resize', forceMenuVisible);
})();
</script>
<style>
:root {
    --primary-color: #FF3333;
    --secondary-color: #FF6600;
    --success-color: #16A34A;
    --warning-color: #F59E0B;
    --danger-color: #EF4444;
    --info-color: #FF6666;
    --dark-bg: #0A0A0A;
    --card-bg: #1A1A1A;
    --border-color: #2A2A2A;
    --text-primary: #FFFFFF;
    --text-secondary: #cbd5e1;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
    --gradient-primary: linear-gradient(135deg, #FF0000 0%, #FF6600 100%);
    --gradient-success: linear-gradient(135deg, #16A34A, #059669);
    --gradient-warning: linear-gradient(135deg, #F59E0B, #D97706);
    --gradient-danger: linear-gradient(135deg, #EF4444, #DC2626);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, #0A0A0A 0%, #1A0000 100%);
    color: var(--text-primary);
    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    min-height: 100vh;
    line-height: 1.6;
    font-weight: 400;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.header {
    background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(20, 20, 20, 0.98) 100%);
    padding: 32px 24px;
    border-radius: 24px;
    margin-bottom: 30px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4), 
                0 0 0 1px rgba(255, 51, 51, 0.15) inset,
                0 4px 16px rgba(255, 51, 51, 0.1);
    border: 1px solid rgba(255, 51, 51, 0.2);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    position: relative;
    overflow: hidden;
}
.header::before {
    content: '';
    position: absolute;
    top: 0; 
    left: 0; 
    right: 0; 
    height: 4px;
    background: var(--gradient-primary);
    opacity: 0.9;
    box-shadow: 0 0 20px rgba(255, 51, 51, 0.6);
}
.header::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255, 51, 51, 0.1) 0%, transparent 70%);
    border-radius: 50%;
}

.header h1 {
    color: var(--primary-color);
    text-align: center;
    font-size: 2.75rem;
    margin-bottom: 12px;
    text-shadow: 0 0 30px rgba(255, 51, 51, 0.6),
                 0 2px 8px rgba(0, 0, 0, 0.5);
    font-weight: 700;
    letter-spacing: -0.5px;
    position: relative;
    z-index: 1;
}

.header p {
    text-align: center;
    color: var(--text-secondary);
    font-size: 1.15rem;
    font-weight: 400;
    position: relative;
    z-index: 1;
    opacity: 0.9;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(20, 20, 20, 0.98) 100%);
    padding: 28px 24px;
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), 
                0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                0 2px 8px rgba(255, 51, 51, 0.1);
    border: 1px solid rgba(255, 51, 51, 0.15);
    text-align: center;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4), 
                0 0 0 1px rgba(255, 51, 51, 0.3) inset,
                0 4px 16px rgba(255, 51, 51, 0.2);
    border-color: rgba(255, 51, 51, 0.4);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gradient-primary);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.stat-card:hover::before {
    transform: scaleX(1);
}

.stat-card i {
    font-size: 2.5rem;
    margin-bottom: 15px;
    display: block;
}

.stat-card h3 {
    font-size: 2rem;
    margin-bottom: 5px;
    color: var(--primary-color);
}

.stat-card p {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.controls {
    background: rgba(255,255,255,0.06);
    padding: 25px;
    border-radius: 18px;
    margin-bottom: 30px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    border: 1px solid rgba(255,255,255,0.18);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.controls h2 {
    color: var(--primary-color);
    margin-bottom: 20px;
    font-size: 1.5rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 8px;
    color: var(--text-secondary);
    font-weight: 500;
}

.form-group input,
.form-group select {
    padding: 12px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--dark-bg);
    color: var(--text-primary);
    font-size: 1rem;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(255, 51, 51, 0.1);
}

.photo-upload {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.photo-preview {
    border: 2px dashed rgba(255,255,255,0.15);
    border-radius: 12px;
    padding: 18px;
    text-align: center;
    background: rgba(255,255,255,0.03);
}

.photo-preview img {
    max-width: 100%;
    border-radius: 10px;
    display: none;
    border: 1px solid rgba(255,255,255,0.08);
}

.photo-preview span {
    color: var(--text-secondary);
    font-size: 0.9rem;
    display: inline-block;
}

.photo-preview-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.photo-preview-actions input[type="file"] {
    padding: 10px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.2);
    background: rgba(0,0,0,0.25);
    color: var(--text-secondary);
}

.details-photo img {
    width: 100%;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.18);
    margin-top: 8px;
}

.checkbox-group {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin: 15px 0;
}

.checkbox-item {
    display: flex;
    align-items: center;
    padding: 10px;
    background: var(--dark-bg);
    border-radius: 8px;
    border: 1px solid var(--border-color);
    transition: background-color 0.3s ease;
}

.checkbox-item:hover {
    background: #333;
}

.checkbox-item input[type="checkbox"] {
    margin-right: 10px;
    transform: scale(1.2);
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-primary {
    background: var(--gradient-primary);
    color: white;
    border: none;
    box-shadow: 0 4px 14px 0 rgba(37, 99, 235, 0.3);
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(37, 99, 235, 0.4);
}

.btn-success {
    background: var(--gradient-success);
    color: white;
    border: none;
    box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.3);
    transition: all 0.3s ease;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(16, 185, 129, 0.4);
}

.btn-warning {
    background: var(--gradient-warning);
    color: white;
    border: none;
    box-shadow: 0 4px 14px 0 rgba(245, 158, 11, 0.3);
    transition: all 0.3s ease;
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(245, 158, 11, 0.4);
}

.btn-danger {
    background: var(--gradient-danger);
    color: white;
    border: none;
    box-shadow: 0 4px 14px 0 rgba(239, 68, 68, 0.3);
    transition: all 0.3s ease;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(239, 68, 68, 0.4);
}

.btn-info {
    background: linear-gradient(135deg, var(--info-color), #0891b2);
    color: white;
    border: none;
    box-shadow: 0 4px 14px 0 rgba(6, 182, 212, 0.3);
    transition: all 0.3s ease;
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(6, 182, 212, 0.4);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 0.875rem;
}

.search-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.search-bar input {
    flex: 1;
    min-width: 200px;
}

.filters {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 8px 16px;
    background: var(--border-color);
    color: var(--text-primary);
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-btn.active {
    background: var(--primary-color);
}

.table-container {
    background: rgba(255,255,255,0.06);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    border: 1px solid rgba(255,255,255,0.18);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.table {
    width: 100%;
    border-collapse: collapse;
}

    .table th {
        background: rgba(0,0,0,0.5);
    color: var(--text-primary);
    padding: 15px;
    text-align: left;
    font-weight: 600;
        border-bottom: 2px solid rgba(255,255,255,0.18);
        backdrop-filter: blur(6px);
}

    .table td {
    padding: 15px;
        border-bottom: 1px solid rgba(255,255,255,0.12);
    vertical-align: middle;
}

.table tbody tr:hover {
    background: rgba(255, 51, 51, 0.05);
}

.badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}

.badge-success {
    background: var(--success-color);
    color: white;
}

.badge-danger {
    background: var(--danger-color);
    color: white;
}

.badge-warning {
    background: var(--warning-color);
    color: black;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.modal-content {
    background: rgba(255,255,255,0.06);
    padding: 30px;
    border-radius: 18px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 12px 32px rgba(0,0,0,0.25);
    border: 1px solid rgba(255,255,255,0.18);
    position: relative;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    color: var(--primary-color);
    font-size: 1.5rem;
}

.close {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 5px;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.close:hover {
    background: var(--border-color);
    color: var(--text-primary);
}

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.logout-btn {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--danger-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 25px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    z-index: 100;
}

    .logout-btn:hover {
        background: #b91c1c;
        transform: translateY(-2px);
    }

    /* Nav superior com efeito de vidro - Design Profissional */
    .admin-nav {
        position: sticky;
        top: 12px;
        margin: 12px auto 20px;
        max-width: 1400px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 16px 24px;
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(20, 20, 20, 0.98) 100%);
        border: 1px solid rgba(255, 51, 51, 0.2);
        border-radius: 20px;
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 
                    0 0 0 1px rgba(255, 51, 51, 0.1) inset,
                    0 2px 8px rgba(255, 51, 51, 0.1);
        z-index: 1000;
    }
    
    .nav-brand { 
        font-weight: 700; 
        letter-spacing: 0.5px; 
        display: flex; 
        align-items: center; 
        gap: 12px;
        font-size: 1.2rem;
        color: var(--text-primary);
        text-shadow: 0 0 10px rgba(255, 51, 51, 0.3);
    }
    
    .nav-brand i {
        color: var(--primary-color);
        font-size: 1.4rem;
    }
    
    /* Menu Hambúrguer Profissional */
    .nav-toggle { 
        display: none; /* Escondido por padrão no desktop */
        flex-direction: column;
        justify-content: center;
        align-items: center;
        width: 44px;
        height: 44px;
        background: rgba(255, 51, 51, 0.1);
        border: 2px solid rgba(255, 51, 51, 0.3);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        z-index: 1001;
    }
    
    /* NO MOBILE: Mostrar o botão */
    @media (max-width: 768px) {
        .nav-toggle,
        #navToggleBtn,
        .nav-toggle.mobile-visible,
        #navToggleBtn.mobile-visible {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: fixed !important;
            top: 16px !important;
            left: 16px !important;
            z-index: 10001 !important;
            width: 44px !important;
            height: 44px !important;
            background: rgba(26, 26, 26, 0.95) !important;
            border: 2px solid rgba(255, 51, 51, 0.3) !important;
            border-radius: 8px !important;
            cursor: pointer !important;
        }
    }
    
    .nav-toggle:hover {
        background: rgba(255, 51, 51, 0.2);
        border-color: rgba(255, 51, 51, 0.5);
        transform: scale(1.05);
    }
    
    .nav-toggle:active {
        transform: scale(0.95);
    }
    
    .nav-toggle span {
        display: block;
        width: 24px;
        height: 3px;
        background: var(--primary-color);
        border-radius: 3px;
        transition: all 0.3s ease;
        box-shadow: 0 0 8px rgba(255, 51, 51, 0.5);
    }
    
    .nav-toggle span:nth-child(1) {
        margin-bottom: 6px;
    }
    
    .nav-toggle span:nth-child(2) {
        margin-bottom: 6px;
    }
    
    .nav-toggle span:nth-child(3) {
        margin-bottom: 0;
    }
    
    /* Animação do hambúrguer quando aberto */
    .admin-nav.open .nav-toggle span:nth-child(1) {
        transform: rotate(45deg) translate(8px, 8px);
    }
    
    .admin-nav.open .nav-toggle span:nth-child(2) {
        opacity: 0;
        transform: translateX(-10px);
    }
    
    .admin-nav.open .nav-toggle span:nth-child(3) {
        transform: rotate(-45deg) translate(7px, -7px);
    }
    
    /* Menu mobile - garantir que aparece */
    @media (max-width: 768px) {
        .admin-nav {
            display: block !important;
        }
        
        .admin-nav:not(.active) {
            left: -100% !important;
        }
        
        .admin-nav.active {
            left: 0 !important;
        }
        
        /* Forçar botão hambúrguer visível no mobile */
        .nav-toggle,
        #navToggleBtn {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: fixed !important;
            top: 16px !important;
            left: 16px !important;
            z-index: 10001 !important;
            width: 44px !important;
            height: 44px !important;
            background: rgba(26, 26, 26, 0.95) !important;
            border: 2px solid rgba(255, 51, 51, 0.3) !important;
            border-radius: 8px !important;
            cursor: pointer !important;
        }
    }
    
    .nav-actions { 
        display: flex; 
        gap: 10px; 
        flex-wrap: wrap;
        align-items: center;
    }
    
    .nav-btn {
        padding: 12px 20px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: var(--text-primary);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        font-size: 0.95rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    
    .nav-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 51, 51, 0.2), transparent);
        transition: left 0.5s ease;
    }
    
    .nav-btn:hover::before {
        left: 100%;
    }
    
    .nav-btn:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 20px rgba(255, 51, 51, 0.3), 
                    0 0 0 1px rgba(255, 51, 51, 0.2) inset;
        background: rgba(255, 51, 51, 0.1);
        border-color: rgba(255, 51, 51, 0.4);
    }
    
    .nav-btn:active {
        transform: translateY(0);
    }
    
    .nav-btn i {
        font-size: 1rem;
    }
    
    .nav-btn.danger {
        background: rgba(239, 68, 68, 0.1);
        border-color: rgba(239, 68, 68, 0.3);
    }
    
    .nav-btn.danger:hover {
        background: rgba(239, 68, 68, 0.2);
        border-color: rgba(239, 68, 68, 0.5);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
    }

    /* Botão flutuante PWA */
    #pwaInstallBtn { position: fixed; right: 16px; bottom: 16px; z-index: 9999; display: none; padding: 12px 16px; border-radius: 999px; border: none; color: #fff; background: var(--gradient-primary); font-weight: 700; box-shadow: var(--shadow-lg); }

    /* Cartões responsivos para lista de códigos (mobile) */
    .cards-list { display: none; }
    .card-item {
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.18);
        border-radius: 16px;
        padding: 14px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        display: grid; grid-template-columns: 1fr; gap: 8px;
    }
    .card-header { display:flex; justify-content: space-between; align-items:center; gap: 8px; }
    .card-code { font-weight: 800; letter-spacing: .3px; }
    .card-city { color: var(--text-secondary); font-size: .95rem; }
    .card-status { display:flex; align-items:center; gap:6px; color: var(--text-primary); font-size: .95rem; }
    .card-meta { color: var(--text-secondary); font-size: .9rem; }
    .card-actions { display:flex; gap:8px; flex-wrap: wrap; }
    .card-actions .btn { padding: 8px 12px; border-radius: 10px; }
    .nav-btn.danger { background: linear-gradient(135deg, #ef4444, #dc2626); border: none; }

    /* Sistema de Notificações */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .toast {
        background: rgba(255,255,255,0.08);
        color: var(--text-primary);
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 10px 24px rgba(0,0,0,0.25);
        border-left: 4px solid var(--primary-color);
        min-width: 300px;
        max-width: 400px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideInRight 0.3s ease;
        position: relative;
    }

    .toast.success {
        border-left-color: var(--success-color);
    }

    .toast.warning {
        border-left-color: var(--warning-color);
    }

    .toast.error {
        border-left-color: var(--danger-color);
    }

    .toast.info {
        border-left-color: var(--info-color);
    }

    .toast-close {
        position: absolute;
        top: 5px;
        right: 10px;
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        font-size: 18px;
        line-height: 1;
    }

    .toast-close:hover {
        color: var(--text-primary);
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 20px #ff6600;
            transform: scale(1);
        }
        50% {
            box-shadow: 0 0 30px #ff3300;
            transform: scale(1.05);
        }
        100% {
            box-shadow: 0 0 20px #ff6600;
            transform: scale(1);
        }
    }

    .toast.fade-out {
        animation: slideOutRight 0.3s ease forwards;
    }

    /* Sistema de Automações */
    .automation-panel {
        background: rgba(255,255,255,0.06);
        padding: 25px;
        border-radius: 18px;
        margin-bottom: 30px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        border: 1px solid rgba(255,255,255,0.18);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .automation-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .automation-card {
        background: var(--dark-bg);
        padding: 20px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .automation-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .automation-card h4 {
        color: var(--primary-color);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .automation-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 15px;
    }

    .toggle-switch {
        position: relative;
        width: 60px;
        height: 30px;
        background: var(--border-color);
        border-radius: 15px;
        cursor: pointer;
        transition: background 0.3s ease;
    }

    .toggle-switch.active {
        background: var(--success-color);
    }

    .toggle-slider {
        position: absolute;
        top: 3px;
        left: 3px;
        width: 24px;
        height: 24px;
        background: white;
        border-radius: 50%;
        transition: transform 0.3s ease;
    }

    .toggle-switch.active .toggle-slider {
        transform: translateX(30px);
    }

    .automation-settings {
        display: none;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid var(--border-color);
    }

    .automation-settings.active {
        display: block;
    }

    .cron-schedule {
        background: rgba(255,255,255,0.04);
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
        border: 1px solid rgba(255,255,255,0.12);
    }

    .schedule-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid var(--border-color);
    }

    .schedule-item:last-child {
        border-bottom: none;
    }

    .status-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 8px;
    }

    .status-indicator.active {
        background: var(--success-color);
        box-shadow: 0 0 10px var(--success-color);
    }

    .status-indicator.inactive {
        background: var(--danger-color);
    }

    .status-indicator.pending {
        background: var(--warning-color);
    }

/* Responsividade Mobile - Melhorada */
@media (max-width: 1024px) {
    .container {
        padding: 15px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .automation-grid {
        grid-template-columns: 1fr;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 768px) {
    /* Menu mobile - drawer lateral */
    .admin-nav { 
        position: fixed !important;
        top: 0 !important;
        left: -100% !important;
        width: 85% !important;
        max-width: 320px !important;
        height: 100vh !important;
        height: 100dvh !important;
        flex-direction: column !important;
        align-items: stretch !important;
        padding: 0 !important;
        margin: 0 !important;
        z-index: 10000 !important;
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
    
    .admin-nav.active {
        left: 0 !important;
    }
    
    /* FORÇAR BOTÃO HAMBÚRGUER VISÍVEL NO MOBILE */
    .nav-toggle,
    #navToggleBtn { 
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        position: fixed !important;
        top: 16px !important;
        left: 16px !important;
        z-index: 10001 !important;
        width: 44px !important;
        height: 44px !important;
        background: rgba(26, 26, 26, 0.95) !important;
        border: 2px solid rgba(255, 51, 51, 0.3) !important;
        border-radius: 8px !important;
        cursor: pointer !important;
        flex-direction: column !important;
        justify-content: center !important;
        align-items: center !important;
        gap: 4px !important;
    }
    
    .nav-toggle span,
    #navToggleBtn span {
        display: block !important;
        width: 24px !important;
        height: 2px !important;
        background: #FF3333 !important;
        border-radius: 2px !important;
    }
    
    .nav-actions { 
        gap: 4px !important; 
        display: flex !important;
        flex-direction: column !important;
        position: static !important;
        background: transparent !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 12px !important;
        margin: 0 !important;
        box-shadow: none !important;
        backdrop-filter: none !important;
    }
    
    .admin-nav.open .nav-actions { 
        display: flex !important;
        animation: none !important;
    }
    
    .nav-btn { 
        width: 100% !important;
        justify-content: flex-start !important;
        padding: 16px 18px !important;
        min-height: 56px !important;
    }
    .stats-grid { grid-template-columns: 1fr !important; }
    .automation-grid { grid-template-columns: 1fr !important; }
    table { display: block; overflow-x: auto; white-space: nowrap; width: 100%; }
    .cards-list { display: grid; grid-template-columns: 1fr; gap: 12px; }
    .table-container { display: none; }
}

@media (max-width: 768px) {
    .container {
        padding: 10px;
    }
    
    .header {
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .header h1 {
        font-size: 1.8rem;
        margin-bottom: 8px;
    }
    
    .header p {
        font-size: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-card h3 {
        font-size: 1.8rem;
    }
    
    .controls {
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .controls h2 {
        font-size: 1.3rem;
        margin-bottom: 15px;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .search-bar {
        flex-direction: column;
        gap: 10px;
    }
    
    .search-bar input {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .search-bar button {
        width: 100%;
        margin: 5px 0;
    }
    
    .filters {
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .filter-btn {
        padding: 10px 15px;
        font-size: 0.9rem;
    }
    
    .bulk-actions {
        padding: 10px;
    }
    
    .bulk-actions > div {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .table-container {
        overflow-x: auto;
        border-radius: 10px;
    }
    
    .table {
        min-width: 700px;
        font-size: 0.9rem;
    }
    
    .table th,
    .table td {
        padding: 10px 8px;
    }
    
    .actions {
        justify-content: center;
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
    
    .logout-btn {
        position: static;
        margin: 10px auto;
        display: block;
        width: fit-content;
        padding: 12px 20px;
    }
    
    .automation-panel {
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .automation-panel h2 {
        font-size: 1.3rem;
        margin-bottom: 15px;
    }
    
    .automation-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .automation-card {
        padding: 15px;
    }
    
    .modal-content {
        padding: 20px;
        margin: 10px;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header h3 {
        font-size: 1.2rem;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 5px;
    }
    
    .header {
        padding: 10px;
        margin-bottom: 15px;
    }
    
    .header h1 {
        font-size: 1.4rem;
        margin-bottom: 5px;
    }
    
    .header p {
        font-size: 0.9rem;
    }
    
    .stat-card {
        padding: 15px;
        margin-bottom: 10px;
    }
    
    .stat-card h3 {
        font-size: 1.5rem;
    }
    
    .stat-card i {
        font-size: 2rem;
    }
    
    .controls {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .controls h2 {
        font-size: 1.2rem;
        margin-bottom: 10px;
    }
    
    .form-group label {
        font-size: 0.9rem;
    }
    
    .form-group input,
    .form-group select {
        padding: 10px;
        font-size: 0.9rem;
    }
    
    .checkbox-group {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .checkbox-item {
        padding: 8px;
    }
    
    .btn {
        padding: 10px 16px;
        font-size: 0.9rem;
        width: 100%;
        margin: 5px 0;
    }
    
    .search-bar input {
        font-size: 0.9rem;
        padding: 12px;
    }
    
    .filters {
        flex-direction: column;
        gap: 5px;
    }
    
    .filter-btn {
        width: 100%;
        padding: 12px;
        font-size: 0.9rem;
    }
    
    .table {
        min-width: 600px;
        font-size: 0.8rem;
    }
    
    .table th,
    .table td {
        padding: 8px 6px;
    }
    
    .actions {
        flex-direction: column;
        gap: 5px;
    }
    
    .btn-sm {
        padding: 8px 12px;
        font-size: 0.8rem;
        width: 100%;
    }
    
    .logout-btn {
        position: static;
        margin: 10px auto;
        display: block;
        width: 100%;
        padding: 12px;
    }
    
    .automation-panel {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .automation-panel h2 {
        font-size: 1.2rem;
        margin-bottom: 10px;
    }
    
    .automation-card {
        padding: 12px;
    }
    
    .automation-card h4 {
        font-size: 1rem;
        margin-bottom: 10px;
    }
    
    .modal-content {
        padding: 15px;
        margin: 5px;
        max-height: 95vh;
    }
    
    .modal-header h3 {
        font-size: 1.1rem;
    }
    
    .modal-header .close {
        font-size: 1.2rem;
    }
    
    .bulk-actions {
        padding: 8px;
    }
    
    .bulk-actions > div {
        flex-direction: column;
        gap: 8px;
    }
    
    .bulk-actions button {
        width: 100%;
        margin: 3px 0;
    }
}

/* ===== SWEETALERT2 CUSTOM STYLES ===== */
.swal2-popup.swal-dark-popup {
    background: linear-gradient(145deg, #1a1a1a 0%, #0f0f0f 100%) !important;
    border: 1px solid rgba(255, 51, 51, 0.3) !important;
    border-radius: 20px !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 
                0 0 30px rgba(255, 51, 51, 0.15) !important;
}

.swal2-title.swal-dark-title {
    color: #ffffff !important;
    font-family: 'Inter', sans-serif !important;
    font-weight: 600 !important;
}

.swal2-html-container {
    color: #e0e0e0 !important;
    font-family: 'Inter', sans-serif !important;
}

.swal-confirm-btn {
    background: linear-gradient(135deg, #FF3333 0%, #FF6600 100%) !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 12px 28px !important;
    font-weight: 600 !important;
    font-family: 'Inter', sans-serif !important;
    box-shadow: 0 4px 15px rgba(255, 51, 51, 0.3) !important;
    transition: all 0.3s ease !important;
}

.swal-confirm-btn:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(255, 51, 51, 0.4) !important;
}

.swal-cancel-btn {
    background: #2a2a2a !important;
    border: 1px solid #444 !important;
    border-radius: 12px !important;
    padding: 12px 28px !important;
    font-weight: 500 !important;
    font-family: 'Inter', sans-serif !important;
    color: #ccc !important;
    transition: all 0.3s ease !important;
}

.swal-cancel-btn:hover {
    background: #3a3a3a !important;
    border-color: #555 !important;
}

.swal2-icon {
    border-color: rgba(255, 51, 51, 0.3) !important;
}

.swal2-icon.swal2-warning {
    border-color: #F59E0B !important;
    color: #F59E0B !important;
}

.swal2-icon.swal2-question {
    border-color: #3B82F6 !important;
    color: #3B82F6 !important;
}

.swal2-icon.swal2-success {
    border-color: #16A34A !important;
    color: #16A34A !important;
}

.swal2-icon.swal2-success .swal2-success-ring {
    border-color: rgba(22, 163, 74, 0.3) !important;
}

.swal2-icon.swal2-success [class^=swal2-success-line] {
    background-color: #16A34A !important;
}

.swal2-timer-progress-bar {
    background: linear-gradient(90deg, #FF3333, #FF6600) !important;
}
</style>
</head>
<body>
<!-- Container de Notificações -->
<div class="toast-container" id="toastContainer"></div>
<button id="pwaInstallBtn"><i class="fas fa-download"></i> Instalar app</button>

<div class="nav-overlay" id="navOverlay" onclick="toggleAdminMenu()"></div>
<div class="admin-nav" id="adminNav">
    <div class="nav-brand"><i class="fas fa-truck"></i> Helmer Admin</div>
    <button class="nav-toggle mobile-visible" id="navToggleBtn" aria-expanded="false" aria-controls="adminNav" onclick="toggleAdminMenu()" aria-label="Toggle menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="nav-actions">
        <a href="admin_indicacoes.php" class="nav-btn"><i class="fas fa-users"></i> Indicações</a>
        <a href="index.php" class="nav-btn"><i class="fas fa-home"></i> Página inicial</a>
        <a href="admin_settings.php" class="nav-btn"><i class="fas fa-gear"></i> Configurações Expressa</a>
        <a href="admin_mensagens.php" class="nav-btn"><i class="fas fa-comment-dots"></i> Mensagens WhatsApp</a>
        <a href="admin_bot_config.php" class="nav-btn"><i class="fas fa-robot"></i> Config Bot</a>
        <?php if (!empty($_SESSION['undo_action'])): ?>
            <a href="#" class="nav-btn" onclick="document.getElementById('undoForm').submit(); return false;"><i class="fas fa-rotate-left"></i> Desfazer</a>
        <?php else: ?>
            <span class="nav-btn" style="opacity:.5; cursor:not-allowed"><i class="fas fa-rotate-left"></i> Desfazer</span>
        <?php endif; ?>
        <a href="admin.php?logout=1" class="nav-btn danger"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</div>
<form id="undoForm" method="POST" style="display:none"><input type="hidden" name="undo_action" value="1"></form>

<div class="container">
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-truck"></i> Painel Admin - Helmer Logistics</h1>
        <p>Sistema de gerenciamento de rastreamento</p>
        
    </div>

    <?php
    // Estatísticas (versão segura)
    try {
        $totalRastreios = fetchOne($pdo, "SELECT COUNT(DISTINCT codigo) as total FROM rastreios_status")['total'];
        $comTaxa = fetchOne($pdo, "SELECT COUNT(DISTINCT codigo) as total FROM rastreios_status WHERE taxa_valor IS NOT NULL AND taxa_pix IS NOT NULL")['total'];
        $semTaxa = $totalRastreios - $comTaxa;
        $entregues = fetchOne($pdo, "SELECT COUNT(DISTINCT codigo) as total FROM rastreios_status WHERE status_atual LIKE '%Entregue%'")['total'];
        
        // Pedidos pendentes
        $pedidosPendentes = [];
        $totalPedidosPendentes = 0;
        try {
            $pedidosPendentes = fetchData($pdo, "SELECT * FROM pedidos_pendentes WHERE status = 'pendente' ORDER BY data_pedido DESC LIMIT 20");
            $totalPedidosPendentes = count($pedidosPendentes);
        } catch (Exception $e) {
            // Tabela pode não existir ainda
        }
    } catch (Exception $e) {
        writeLog("Erro ao buscar estatísticas: " . $e->getMessage(), 'ERROR');
        $totalRastreios = $comTaxa = $semTaxa = $entregues = 0;
        $totalPedidosPendentes = 0;
        $pedidosPendentes = [];
    }
    ?>

    <!-- Dashboard Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-box" style="color: var(--info-color);"></i>
            <h3><?= $totalRastreios ?></h3>
            <p>Total de Rastreios</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-dollar-sign" style="color: var(--danger-color);"></i>
            <h3><?= $comTaxa ?></h3>
            <p>Com Taxa Pendente</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
            <h3><?= $semTaxa ?></h3>
            <p>Sem Taxa</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-truck-loading" style="color: var(--warning-color);"></i>
            <h3><?= $entregues ?></h3>
            <p>Entregues</p>
        </div>
        <?php if ($totalPedidosPendentes > 0): ?>
        <div class="stat-card" style="border: 2px solid var(--warning-color); background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.05) 100%);">
            <i class="fas fa-shopping-cart" style="color: var(--warning-color);"></i>
            <h3><?= $totalPedidosPendentes ?></h3>
            <p>Pedidos Pendentes</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Seção de Pedidos Pendentes -->
    <?php if ($totalPedidosPendentes > 0): ?>
    <div style="margin-bottom: 40px;">
        <div style="background: linear-gradient(145deg, #1a1a1a 0%, #0f0f0f 100%); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 24px; padding: 30px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);">
            <h2 style="margin-bottom: 20px; color: var(--warning-color);">
                <i class="fas fa-shopping-cart"></i> Pedidos Pendentes (<?= $totalPedidosPendentes ?>)
            </h2>
            <p style="color: var(--text-secondary); margin-bottom: 25px;">
                Clientes que preencheram o formulário aguardando aprovação e código de rastreio
            </p>
            
            <div style="display: grid; gap: 20px;">
                <?php foreach ($pedidosPendentes as $pedido): ?>
                <div style="background: #0f0f0f; border: 1px solid var(--border-color); border-radius: 16px; padding: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 20px;">
                        <div style="flex: 1; min-width: 250px;">
                            <h3 style="color: var(--text-primary); margin-bottom: 15px; font-size: 1.2rem;">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($pedido['nome']) ?>
                            </h3>
                            
                            <div style="display: grid; gap: 10px; color: var(--text-secondary); font-size: 0.95rem;">
                                <div><i class="fas fa-phone"></i> <?= htmlspecialchars($pedido['telefone']) ?></div>
                                <?php if ($pedido['email']): ?>
                                <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($pedido['email']) ?></div>
                                <?php endif; ?>
                                <div><i class="fas fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?></div>
                            </div>
                            
                            <div style="margin-top: 15px; padding: 15px; background: rgba(255, 51, 51, 0.05); border-radius: 12px; border-left: 3px solid var(--primary-color);">
                                <strong style="color: var(--primary-color); display: block; margin-bottom: 8px;">
                                    <i class="fas fa-map-marker-alt"></i> Endereço de Entrega:
                                </strong>
                                <div style="color: var(--text-secondary); line-height: 1.8;">
                                    <?= htmlspecialchars($pedido['rua']) ?>, <?= htmlspecialchars($pedido['numero']) ?>
                                    <?php if ($pedido['complemento']): ?><br><?= htmlspecialchars($pedido['complemento']) ?><?php endif; ?><br>
                                    <?= htmlspecialchars($pedido['bairro']) ?> - <?= htmlspecialchars($pedido['cidade']) ?>/<?= htmlspecialchars($pedido['estado']) ?><br>
                                    CEP: <?= htmlspecialchars($pedido['cep']) ?>
                                </div>
                                <?php if ($pedido['observacoes']): ?>
                                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border-color);">
                                    <strong style="color: var(--text-primary);">Observações:</strong>
                                    <p style="color: var(--text-secondary); margin-top: 5px;"><?= nl2br(htmlspecialchars($pedido['observacoes'])) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 10px; min-width: 200px;">
                            <form method="POST" onsubmit="return confirmarAprovarPedido(this, '<?= htmlspecialchars($pedido['nome'], ENT_QUOTES) ?>')" style="display: flex; flex-direction: column; gap: 8px;">
                                <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                <input type="text" name="codigo_rastreio" placeholder="Código de rastreio" required 
                                       style="width: 100%; padding: 10px; background: #1a1a1a; border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary);">
                                <button type="submit" name="aprovar_pedido" class="btn btn-success" style="width: 100%;">
                                    <i class="fas fa-check"></i> Aprovar
                                </button>
                            </form>
                            
                            <form method="POST" id="formRejeitar_<?= $pedido['id'] ?>" onsubmit="event.preventDefault(); confirmarRejeitarPedido(this, '<?= htmlspecialchars($pedido['nome'], ENT_QUOTES) ?>', <?= $pedido['id'] ?>); return false;">
                                <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                <button type="submit" name="rejeitar_pedido" class="btn btn-danger" style="width: 100%;">
                                    <i class="fas fa-times"></i> Rejeitar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Controles -->
    <div class="controls">
        <h2><i class="fas fa-plus-circle"></i> Adicionar Novo Rastreio</h2>
        
        <!-- Filtros e Busca -->
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="🔍 Buscar por código ou cidade..." onkeyup="filterTable()">
            <button type="button" class="btn btn-info" onclick="exportData()">
                <i class="fas fa-download"></i> Exportar
            </button>
        </div>

        <div class="filters">
            <button class="filter-btn active" onclick="filterBy('all')">Todos</button>
            <button class="filter-btn" onclick="filterBy('com_taxa')">Com Taxa</button>
            <button class="filter-btn" onclick="filterBy('sem_taxa')">Sem Taxa</button>
            <button class="filter-btn" onclick="filterBy('entregues')">Entregues</button>
        </div>

        <!-- Operações em lote -->
        <div class="bulk-actions" id="bulkActions" style="display: none; margin-bottom: 20px; padding: 15px; background: var(--dark-bg); border-radius: 8px; border: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <span><i class="fas fa-check-square"></i> <span id="selectedCount">0</span> itens selecionados</span>
                <button class="btn btn-danger btn-sm" onclick="bulkDelete()">
                    <i class="fas fa-trash"></i> Excluir Selecionados
                </button>
                <button class="btn btn-warning btn-sm" onclick="bulkEdit()">
                    <i class="fas fa-edit"></i> Editar em Lote
                </button>
                <button class="btn btn-primary btn-sm" onclick="openPresetModal()" type="button">
                    <i class="fas fa-diagram-project"></i> Aplicar Preset
                </button>
                <button class="btn btn-info btn-sm" onclick="bulkExport()">
                    <i class="fas fa-download"></i> Exportar Selecionados
                </button>
                <button class="btn btn-secondary btn-sm" onclick="clearSelection()">
                    <i class="fas fa-times"></i> Limpar Seleção
                </button>
            </div>
        </div>

    <!-- Formulário adicionar -->
        <form method="POST" id="addForm" enctype="multipart/form-data">
        <input type="hidden" name="novo_codigo" value="1">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="codigo">Código de Rastreio</label>
                    <input type="text" name="codigo" id="codigo" placeholder="Digite o código... (Ctrl + Plus para incrementar)" required>
                    <small style="display:block;color:rgba(148,163,184,0.85);font-size:0.85rem;margin-top:6px;">
                        <i class="fas fa-info-circle"></i> O sistema lembra o último código e sugere o próximo automaticamente. Use o botão +1 ou Ctrl + Plus para incrementar.
                    </small>
                </div>
                <div class="form-group">
                    <label for="cidade">Cidade Vinculada</label>
                    <input type="text" name="cidade" id="cidade" placeholder="Digite a cidade..." required>
                </div>
                <div class="form-group">
                    <label for="data_inicial">Data Inicial</label>
                    <input type="datetime-local" name="data_inicial" id="data_inicial" value="<?= date('Y-m-d\TH:i') ?>" required>
                </div>
            </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="cliente_nome">Nome do Cliente (opcional)</label>
                <input type="text" name="cliente_nome" id="cliente_nome" placeholder="Ex.: Maria Silva">
            </div>
            <div class="form-group">
                <label for="cliente_whatsapp">WhatsApp do Cliente</label>
                <input type="tel" name="cliente_whatsapp" id="cliente_whatsapp" placeholder="Ex.: 11999999999">
                <small style="display:block;color:rgba(148,163,184,0.85);font-size:0.85rem;margin-top:6px;">Inclua DDD. Aceita números nacionais e internacionais.</small>
            </div>
            <div class="form-group" style="align-self:flex-end;">
                <label for="cliente_notificar" style="display:block;">Notificações automáticas</label>
                <label style="display:flex;align-items:center;gap:8px;font-size:0.95rem;">
                    <input type="checkbox" name="cliente_notificar" id="cliente_notificar" value="1" checked>
                    <span>Enviar atualizações no WhatsApp</span>
                </label>
            </div>
        </div>

            <div class="form-group">
                <label for="foto_pedido">Foto do Pedido (opcional)</label>
                <input type="file" name="foto_pedido" id="foto_pedido" accept="image/*">
                <small style="display:block;color:rgba(148,163,184,0.85);font-size:0.85rem;margin-top:6px;">
                    Formatos suportados: JPG, PNG, WEBP ou GIF (até <?= $uploadMaxSizeMb ?> MB).
                </small>
            </div>

            <div class="form-group">
                <label>Etapas do Rastreamento</label>
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" name="etapas[postado]" value="1" id="etapa_postado">
                        <label for="etapa_postado">📦 Objeto postado</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" name="etapas[transito]" value="1" id="etapa_transito">
                        <label for="etapa_transito">🚚 Em trânsito</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" name="etapas[distribuicao]" value="1" id="etapa_distribuicao">
                        <label for="etapa_distribuicao">🏢 No centro de distribuição</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" name="etapas[entrega]" value="1" id="etapa_entrega">
                        <label for="etapa_entrega">🚀 Saiu para entrega</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" name="etapas[entregue]" value="1" id="etapa_entregue">
                        <label for="etapa_entregue">✅ Entregue</label>
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="taxa_valor">Valor da Taxa (opcional)</label>
                    <input type="number" name="taxa_valor" id="taxa_valor" placeholder="0.00" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label for="taxa_pix">Chave PIX (opcional)</label>
                    <input type="text" name="taxa_pix" id="taxa_pix" placeholder="Digite a chave PIX...">
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Adicionar Rastreio
                </button>
                <button type="reset" class="btn btn-warning">
                    <i class="fas fa-undo"></i> Limpar
                </button>
            </div>
    </form>
    </div>

    <!-- Lista de Rastreios -->
    <div class="table-container" style="overflow-x:auto; -webkit-overflow-scrolling: touch;">
        <table class="table" id="rastreiosTable" style="min-width: 760px;">
            <thead>
                <tr>
                    <th style="width: 50px;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" title="Selecionar todos">
                    </th>
                    <th><i class="fas fa-barcode"></i> Código</th>
                    <th><i class="fas fa-map-marker-alt"></i> Cidade</th>
                    <th><i class="fas fa-info-circle"></i> Status Atual</th>
                    <th><i class="fas fa-dollar-sign"></i> Taxa</th>
                    <th><i class="fas fa-calendar"></i> Data</th>
                    <th><i class="fas fa-cogs"></i> Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
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
                    $dados_rastreios = array_filter($dados_rastreios, function($row) {
                        if ($_GET['filtro'] == "com_taxa") {
                            return !empty($row['taxa_valor']) && !empty($row['taxa_pix']);
                        } elseif ($_GET['filtro'] == "sem_taxa") {
                            return empty($row['taxa_valor']) || empty($row['taxa_pix']);
                        }
                        return true;
                    });
                }
                
                // Verificar se há resultados
                if (!empty($dados_rastreios)) {
                    foreach ($dados_rastreios as $row) {
                    $badge = !empty($row['taxa_valor']) && !empty($row['taxa_pix'])
                        ? "<span class='badge badge-danger'><i class='fas fa-exclamation-triangle'></i> Taxa pendente</span>"
                        : "<span class='badge badge-success'><i class='fas fa-check'></i> Sem taxa</span>";

                    $statusIcon = "";
                    if (strpos($row['status_atual'], 'Entregue') !== false) {
                        $statusIcon = "<i class='fas fa-check-circle' style='color: var(--success-color);'></i> ";
                    } elseif (strpos($row['status_atual'], 'Saiu para entrega') !== false) {
                        $statusIcon = "<i class='fas fa-truck' style='color: var(--warning-color);'></i> ";
                    } elseif (strpos($row['status_atual'], 'Em trânsito') !== false) {
                        $statusIcon = "<i class='fas fa-shipping-fast' style='color: var(--info-color);'></i> ";
                    } else {
                        $statusIcon = "<i class='fas fa-box' style='color: var(--text-secondary);'></i> ";
                    }

                    echo "<tr data-codigo='{$row['codigo']}' data-cidade='{$row['cidade']}' data-status='{$row['status_atual']}'>
                        <td>
                            <input type='checkbox' class='row-checkbox' value='{$row['codigo']}' onchange='updateSelection()'>
                        </td>
                        <td><strong>{$row['codigo']}</strong></td>
                        <td>{$row['cidade']}</td>
                        <td>{$statusIcon}{$row['status_atual']}</td>
                        <td>$badge</td>
                        <td>" . date("d/m/Y H:i", strtotime($row['data'])) . "</td>
                        <td>
                            <div class='actions'>
                                <button class='btn btn-warning btn-sm' onclick='abrirModal(\"{$row['codigo']}\")' title='Editar'>
                                    <i class='fas fa-edit'></i>
                                </button>
                                <button class='btn btn-info btn-sm' onclick='viewDetails(\"{$row['codigo']}\")' title='Ver detalhes'>
                                    <i class='fas fa-eye'></i>
                                </button>
                                <button class='btn btn-success btn-sm' onclick='enviarWhatsappManual(\"{$row['codigo']}\")' title='Enviar atualização via WhatsApp' style='background: #25D366 !important; border-color: #25D366 !important; color: white !important; display: inline-flex !important;'>
                                    <i class='fab fa-whatsapp'></i> WhatsApp
                                </button>
                                <form method='POST' style='display:inline' id='formDeletar{$row['codigo']}'>
                                    <input type='hidden' name='codigo' value='{$row['codigo']}'>
                                    <input type='hidden' name='deletar' value='1'>
                                    <button type='button' onclick='confirmarExclusao(\"formDeletar{$row['codigo']}\", \"rastreio\", \"{$row['codigo']}\")' class='btn btn-danger btn-sm' title='Excluir'>
                                        <i class='fas fa-trash'></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>";
                    }
                } else {
                    echo "<tr><td colspan='7' style='text-align: center; padding: 20px; color: var(--text-secondary);'>
                            <i class='fas fa-inbox'></i> Nenhum rastreio encontrado
                          </td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Cards (mobile) -->
    <div class="cards-list" id="rastreiosCards">
        <?php
        if (!empty($dados_rastreios)) {
            foreach ($dados_rastreios as $row) {
                $badge = !empty($row['taxa_valor']) && !empty($row['taxa_pix'])
                    ? "<span class='badge badge-danger'><i class='fas fa-exclamation-triangle'></i> Taxa pendente</span>"
                    : "<span class='badge badge-success'><i class='fas fa-check'></i> Sem taxa</span>";
                $statusIcon = "";
                if (strpos($row['status_atual'], 'Entregue') !== false) {
                    $statusIcon = "<i class='fas fa-check-circle' style='color: var(--success-color);'></i> ";
                } elseif (strpos($row['status_atual'], 'Saiu para entrega') !== false) {
                    $statusIcon = "<i class='fas fa-truck' style='color: var(--warning-color);'></i> ";
                } elseif (strpos($row['status_atual'], 'Em trânsito') !== false) {
                    $statusIcon = "<i class='fas fa-shipping-fast' style='color: var(--info-color);'></i> ";
                } else {
                    $statusIcon = "<i class='fas fa-box' style='color: var(--text-secondary);'></i> ";
                }
                echo "<div class='card-item' data-codigo='{$row['codigo']}'>
                        <div class='card-header'>
                            <div>
                                <div class='card-code'>{$row['codigo']}</div>
                                <div class='card-city'><i class='fas fa-map-marker-alt'></i> {$row['cidade']}</div>
                            </div>
                            <div>{$badge}</div>
                        </div>
                        <div class='card-status'>{$statusIcon}{$row['status_atual']}</div>
                        <div class='card-meta'><i class='fas fa-calendar'></i> " . date("d/m/Y H:i", strtotime($row['data'])) . "</div>
                        <div class='card-actions'>
                            <button class='btn btn-warning btn-sm' onclick=\"abrirModal('{$row['codigo']}')\"><i class='fas fa-edit'></i> Editar</button>
                            <button class='btn btn-info btn-sm' onclick=\"viewDetails('{$row['codigo']}')\"><i class='fas fa-eye'></i> Detalhes</button>
                            <button class='btn btn-success btn-sm' onclick=\"enviarWhatsappManual('{$row['codigo']}')\" style='background: #25D366 !important; border-color: #25D366 !important; color: white !important; display: inline-flex !important;'><i class='fab fa-whatsapp'></i> WhatsApp</button>
                            <form method='POST' style='display:inline' id='formDeletarMobile{$row['codigo']}'>
                                <input type='hidden' name='codigo' value='{$row['codigo']}'>
                                <input type='hidden' name='deletar' value='1'>
                                <button type='button' onclick=\"confirmarExclusao('formDeletarMobile{$row['codigo']}', 'rastreio', '{$row['codigo']}')\" class='btn btn-danger btn-sm'><i class='fas fa-trash'></i> Excluir</button>
                            </form>
                        </div>
                    </div>";
            }
        } else {
            echo "<div class='card-item'><div class='card-status'><i class='fas fa-inbox'></i> Nenhum rastreio encontrado</div></div>";
        }
        ?>
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
                    <input type="text" name="codigo" id="edit_codigo" readonly style="background: #333; color: #999;">
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
                    <input type="number" name="taxa_valor" id="edit_taxa_valor" placeholder="0.00" step="0.01" min="0">
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
                    <input type="tel" name="cliente_whatsapp" id="edit_cliente_whatsapp" placeholder="Ex.: 11999999999">
                    <small style="display:block;color:rgba(148,163,184,0.85);font-size:0.85rem;margin-top:6px;">Inclua DDD. Aceita números nacionais e internacionais.</small>
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
          document.getElementById('modal').style.display='flex';
          document.getElementById('edit_codigo').value = codigo;
          document.getElementById('edit_cidade').value = data.cidade || '';
          // Usar data inicial retornada ou data atual como fallback
          document.getElementById('edit_data').value = data.data_inicial || new Date().toISOString().slice(0,16);
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
        
        switch(type) {
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
    a.download = 'rastreios_' + new Date().toISOString().slice(0,10) + '.csv';
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
async function confirmarRejeitarPedido(form, nomeCliente, pedidoId) {
    // Prevenir submit padrão (já feito no onsubmit, mas garantindo)
    event.preventDefault();
    
    const result = await SwalDark.fire({
        title: '❌ Rejeitar Pedido',
        html: `
            <div style="text-align: center; padding: 10px;">
                <p style="font-size: 16px; margin-bottom: 15px;">
                    Tem certeza que deseja rejeitar o pedido de <strong style="color: #FF3333;">${nomeCliente}</strong>?
                </p>
                <p style="font-size: 14px; color: #cbd5e1; margin-top: 10px;">
                    Esta ação não pode ser desfeita.
                </p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-times"></i> Sim, Rejeitar',
        cancelButtonText: '<i class="fas fa-arrow-left"></i> Cancelar',
        reverseButtons: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        focusCancel: true
    });
    
    // Só submeter se confirmado
    if (result.isConfirmed) {
        // Desabilitar botão para evitar duplo submit
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rejeitando...';
        }
        
        // Submeter formulário
        form.submit();
    }
    
    // Retornar false para garantir que não submete
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
window.onclick = function(event) {
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
document.addEventListener('keydown', function(e) {
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
    document.addEventListener('DOMContentLoaded', function() {
        notifySuccess('<?= addslashes($success_message) ?>');
    });
<?php endif; ?>

// Mostrar notificações de erro do PHP
<?php if (isset($error_message)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        notifyError('<?= addslashes($error_message) ?>');
    });
<?php endif; ?>

// Inicializar sistema
document.addEventListener('DOMContentLoaded', function() {
            startStatusAutomation(automations.statusUpdate.interval);
        }
        
        if (automations.notifications && automations.notifications.enabled) {
            startNotificationAutomation();
        }
    }
});

// Validação AJAX de duplicidade no formulário de adicionar
document.addEventListener('DOMContentLoaded', function() {
    const addForm = document.getElementById('addForm');
    if (!addForm) return;
    addForm.addEventListener('submit', async function(e) {
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

document.addEventListener('DOMContentLoaded', function() {
    const fotoInput = document.getElementById('edit_foto_pedido');
    if (!fotoInput) return;
    fotoInput.addEventListener('change', function(e) {
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
    a.download = 'rastreios_selecionados_' + new Date().toISOString().slice(0,10) + '.csv';
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
    const nowISO = new Date().toISOString().slice(0,16);

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
        const dt = new Date(startTs + s[3]*3600000);
        html += `<div class="schedule-item"><span>${s[0]}</span><span>${dt.toLocaleString()}</span></div>`;
    });
    html += '</div>';
    box.innerHTML = html;
}

function applyPreset(selected) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    const add = (n,v) => { const i=document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); };
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




// Auto-refresh removido - atualização apenas manual (F5)


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
        navigator.serviceWorker.register('sw.js').catch(()=>{});
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

function toggleAdminMenu() {
    const nav = document.getElementById('adminNav');
    const btn = document.querySelector('.nav-toggle');
    const overlay = document.getElementById('navOverlay');
    const body = document.body;
    
    if (!nav || !btn) {
        console.error('Menu elements not found');
        return;
    }
    
    const isOpen = nav.classList.contains('active');
    
    if (isOpen) {
        // Fechar menu
        nav.classList.remove('active');
        nav.classList.remove('open'); // Compatibilidade com CSS antigo
        if (overlay) overlay.classList.remove('active');
        body.classList.remove('menu-open');
        btn.setAttribute('aria-expanded', 'false');
        body.style.overflow = '';
    } else {
        // Abrir menu
        nav.classList.add('active');
        nav.classList.add('open'); // Compatibilidade com CSS antigo
        if (overlay) overlay.classList.add('active');
        body.classList.add('menu-open');
        btn.setAttribute('aria-expanded', 'true');
        body.style.overflow = 'hidden'; // Prevenir scroll do body
    }
}

// Fechar menu ao clicar no overlay
document.addEventListener('DOMContentLoaded', function() {
    // Garantir que o botão hambúrguer apareça no mobile - FORÇAR
    function showMenuButton() {
        const navToggle = document.getElementById('navToggleBtn') || document.querySelector('.nav-toggle');
        if (!navToggle) {
            console.error('Menu button not found!');
            return;
        }
        
        const isMobile = window.innerWidth <= 768 || window.matchMedia('(max-width: 768px)').matches;
        console.log('Mobile check:', isMobile, 'Width:', window.innerWidth);
        
        if (isMobile) {
            // Remover qualquer estilo que possa estar escondendo
            navToggle.removeAttribute('style');
            
            // Forçar todos os estilos inline com setProperty para garantir
            navToggle.style.setProperty('display', 'flex', 'important');
            navToggle.style.setProperty('visibility', 'visible', 'important');
            navToggle.style.setProperty('opacity', '1', 'important');
            navToggle.style.setProperty('position', 'fixed', 'important');
            navToggle.style.setProperty('top', '16px', 'important');
            navToggle.style.setProperty('left', '16px', 'important');
            navToggle.style.setProperty('z-index', '10001', 'important');
            navToggle.style.setProperty('width', '44px', 'important');
            navToggle.style.setProperty('height', '44px', 'important');
            navToggle.style.setProperty('background', 'rgba(26, 26, 26, 0.95)', 'important');
            navToggle.style.setProperty('border', '2px solid rgba(255, 51, 51, 0.3)', 'important');
            navToggle.style.setProperty('border-radius', '8px', 'important');
            navToggle.style.setProperty('cursor', 'pointer', 'important');
            navToggle.style.setProperty('flex-direction', 'column', 'important');
            navToggle.style.setProperty('justify-content', 'center', 'important');
            navToggle.style.setProperty('align-items', 'center', 'important');
            navToggle.style.setProperty('gap', '4px', 'important');
            
            // Garantir que os spans apareçam
            const spans = navToggle.querySelectorAll('span');
            spans.forEach((span, index) => {
                span.style.setProperty('display', 'block', 'important');
                span.style.setProperty('width', '24px', 'important');
                span.style.setProperty('height', '2px', 'important');
                span.style.setProperty('background', '#FF3333', 'important');
                span.style.setProperty('border-radius', '2px', 'important');
                if (index < spans.length - 1) {
                    span.style.setProperty('margin-bottom', '4px', 'important');
                }
            });
            
            // Forçar também via className para garantir
            navToggle.classList.add('mobile-visible');
            
            console.log('Menu button forced visible. Computed display:', window.getComputedStyle(navToggle).display);
        } else {
            navToggle.style.setProperty('display', 'none', 'important');
            navToggle.classList.remove('mobile-visible');
        }
    }
    
    // Mostrar imediatamente
    showMenuButton();
    
    // Mostrar ao redimensionar
    window.addEventListener('resize', showMenuButton);
    
    // Forçar múltiplas vezes para garantir
    setTimeout(showMenuButton, 10);
    setTimeout(showMenuButton, 50);
    setTimeout(showMenuButton, 100);
    setTimeout(showMenuButton, 300);
    setTimeout(showMenuButton, 500);
    setTimeout(showMenuButton, 1000);
    
    const overlay = document.getElementById('navOverlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            toggleAdminMenu();
        });
    }
    
    // Fechar menu ao clicar em link
    const navLinks = document.querySelectorAll('.nav-actions .nav-btn');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Pequeno delay para permitir navegação
            setTimeout(() => {
                toggleAdminMenu();
            }, 150);
        });
    });
    
    // Fechar menu ao clicar no X do header (se existir)
    const navBrand = document.querySelector('.nav-brand');
    if (navBrand) {
        // Adicionar evento de clique no brand para fechar
        navBrand.style.cursor = 'pointer';
        navBrand.addEventListener('click', function(e) {
            // Só fechar se clicar no próprio brand, não nos filhos
            if (e.target === navBrand || e.target.closest('.nav-brand') === navBrand) {
                const nav = document.getElementById('adminNav');
                if (nav && nav.classList.contains('active')) {
                    toggleAdminMenu();
                }
            }
        });
    }
    
    // Fechar menu ao pressionar ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const nav = document.getElementById('adminNav');
            if (nav && nav.classList.contains('active')) {
                toggleAdminMenu();
            }
        }
    });
    
    // Log para debug
    console.log('Menu mobile inicializado');
});
</script>
<?php // Expor presets ao JS ?>
<script>
window.STATUS_PRESETS = <?php echo json_encode(array_map(function($p){ return ['label'=>$p['label'], 'steps'=>$p['steps']]; }, $STATUS_PRESETS)); ?>
</script>
    <!-- UI Enhancements - Melhorias de UX/UI -->
    <script src="assets/js/ui-enhancements.js"></script>
    
    <!-- Código Auto-Increment -->
    <script src="assets/js/codigo-auto-increment.js"></script>
    
</body>
</html>
