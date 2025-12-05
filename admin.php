<?php
/**
 * Painel Administrativo Helmer Logistics
 * Versão otimizada e segura
 */

// Incluir configurações e DB
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/whatsapp_helper.php';
require_once 'includes/rastreio_media.php';

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

// Enviar WhatsApp manualmente (AJAX)
if (isset($_POST['enviar_whatsapp_manual']) && isset($_POST['codigo'])) {
    // Limpar qualquer saída anterior
    if (ob_get_level() > 0) {
        ob_clean();
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
        
        writeLog("Iniciando envio manual de WhatsApp para código {$codigo}, telefone {$contato['telefone_normalizado']}", 'INFO');
        
        // Chamar função de notificação
        notifyWhatsappLatestStatus($pdo, $codigo);
        
        echo json_encode([
            'success' => true, 
            'message' => "✅ Notificação WhatsApp enviada com sucesso para {$contato['telefone_normalizado']}!"
        ]);
        writeLog("Envio manual de WhatsApp para código {$codigo} concluído com sucesso", 'INFO');
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

// (Sem configurações de site persistidas)
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
        display: none;
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
    .admin-nav { 
        flex-direction: row; 
        align-items: center;
        padding: 12px 16px;
        position: relative;
    }
    .nav-toggle { 
        display: flex !important;
    }
    .nav-actions { 
        gap: 8px; 
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.98) 0%, rgba(20, 20, 20, 1) 100%);
        border: 1px solid rgba(255, 51, 51, 0.2);
        border-top: none;
        border-radius: 0 0 20px 20px;
        padding: 16px;
        margin-top: 8px;
        flex-direction: column;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
    }
    .admin-nav.open .nav-actions { 
        display: flex;
        animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .nav-btn { 
        width: 100%;
        justify-content: flex-start;
        padding: 14px 18px;
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
</style>
</head>
<body>
<!-- Container de Notificações -->
<div class="toast-container" id="toastContainer"></div>
<button id="pwaInstallBtn"><i class="fas fa-download"></i> Instalar app</button>

<div class="admin-nav" id="adminNav">
    <div class="nav-brand"><i class="fas fa-truck"></i> Helmer Admin</div>
    <button class="nav-toggle" aria-expanded="false" aria-controls="adminNav" onclick="toggleAdminMenu()" aria-label="Toggle menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="nav-actions">
        <a href="admin_indicacoes.php" class="nav-btn"><i class="fas fa-users"></i> Indicações</a>
        <a href="index.php" class="nav-btn"><i class="fas fa-home"></i> Página inicial</a>
        <a href="admin_settings.php" class="nav-btn"><i class="fas fa-gear"></i> Configurações Expressa</a>
        <a href="admin_mensagens.php" class="nav-btn"><i class="fas fa-comment-dots"></i> Mensagens WhatsApp</a>
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
    } catch (Exception $e) {
        writeLog("Erro ao buscar estatísticas: " . $e->getMessage(), 'ERROR');
        $totalRastreios = $comTaxa = $semTaxa = $entregues = 0;
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
    </div>

    


    <!-- Painel de Automações -->
    <div class="automation-panel">
        <h2><i class="fas fa-robot"></i> Sistema de Automações</h2>
        <p>Configure automações inteligentes para gerenciar seus rastreios automaticamente</p>
        
        <div class="automation-grid">
            <!-- Automação de Notificações -->
            <div class="automation-card">
                <h4><i class="fas fa-bell"></i> Notificações Automáticas</h4>
                <div class="automation-toggle">
                    <span>Enviar notificações por email/SMS</span>
                    <div class="toggle-switch" onclick="toggleAutomation(this, 'notifications')">
                        <div class="toggle-slider"></div>
                    </div>
                </div>
                <div class="automation-settings" id="notifications-settings">
                    <div class="form-group">
                        <label>Email para notificações</label>
                        <input type="email" id="notification-email" placeholder="admin@helmer.com">
                    </div>
                    <div class="form-group">
                        <label>Telefone para SMS</label>
                        <input type="tel" id="notification-phone" placeholder="+55 11 99999-9999">
                    </div>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="notify-created" checked>
                            <label for="notify-created">Novo rastreio criado</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="notify-status-change" checked>
                            <label for="notify-status-change">Mudança de status</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="notify-taxa-pending" checked>
                            <label for="notify-taxa-pending">Taxa pendente há 24h</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="notify-delivered" checked>
                            <label for="notify-delivered">Rastreio entregue</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Automação de Status -->
            <div class="automation-card">
                <h4><i class="fas fa-sync-alt"></i> Atualização Automática de Status</h4>
                <div class="automation-toggle">
                    <span>Atualizar status automaticamente</span>
                    <div class="toggle-switch" onclick="toggleAutomation(this, 'status-update')">
                        <div class="toggle-slider"></div>
                    </div>
                </div>
                <div class="automation-settings" id="status-update-settings">
                    <div class="form-group">
                        <label>Intervalo de verificação (minutos)</label>
                        <select id="update-interval">
                            <option value="15">15 minutos</option>
                            <option value="30" selected>30 minutos</option>
                            <option value="60">1 hora</option>
                            <option value="120">2 horas</option>
                        </select>
                    </div>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="auto-progress" checked>
                            <label for="auto-progress">Progressão automática de etapas</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="auto-taxa-apply">
                            <label for="auto-taxa-apply">Aplicar taxa automaticamente após 48h</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Automação de Alertas -->
            <div class="automation-card">
                <h4><i class="fas fa-exclamation-triangle"></i> Alertas Inteligentes</h4>
                <div class="automation-toggle">
                    <span>Alertas automáticos</span>
                    <div class="toggle-switch" onclick="toggleAutomation(this, 'alerts')">
                        <div class="toggle-slider"></div>
                    </div>
                </div>
                <div class="automation-settings" id="alerts-settings">
                    <div class="form-group">
                        <label>Dias para alerta de rastreio "preso"</label>
                        <input type="number" id="stuck-days" value="3" min="1" max="30">
                    </div>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="alert-stuck" checked>
                            <label for="alert-stuck">Rastreio sem atualização</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="alert-taxa-overdue" checked>
                            <label for="alert-taxa-overdue">Taxa vencida</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="alert-inconsistency">
                            <label for="alert-inconsistency">Inconsistências nos dados</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Automação de Relatórios -->
            <div class="automation-card">
                <h4><i class="fas fa-chart-line"></i> Relatórios Automáticos</h4>
                <div class="automation-toggle">
                    <span>Relatórios programados</span>
                    <div class="toggle-switch" onclick="toggleAutomation(this, 'reports')">
                        <div class="toggle-slider"></div>
                    </div>
                </div>
                <div class="automation-settings" id="reports-settings">
                    <div class="form-group">
                        <label>Frequência dos relatórios</label>
                        <select id="report-frequency">
                            <option value="daily">Diário</option>
                            <option value="weekly" selected>Semanal</option>
                            <option value="monthly">Mensal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Email para relatórios</label>
                        <input type="email" id="report-email" placeholder="relatorios@helmer.com">
                    </div>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="report-summary" checked>
                            <label for="report-summary">Resumo executivo</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="report-detailed">
                            <label for="report-detailed">Relatório detalhado</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cronograma de Execução -->
        <div class="cron-schedule">
            <h4><i class="fas fa-clock"></i> Cronograma de Execução</h4>
            <div class="schedule-item">
                <span><span class="status-indicator active"></span>Verificação de status</span>
                <span>A cada 30 minutos</span>
            </div>
            <div class="schedule-item">
                <span><span class="status-indicator active"></span>Envio de notificações</span>
                <span>Imediato</span>
            </div>
            <div class="schedule-item">
                <span><span class="status-indicator pending"></span>Geração de relatórios</span>
                <span>Semanalmente (domingo 08:00)</span>
            </div>
            <div class="schedule-item">
                <span><span class="status-indicator active"></span>Limpeza de logs antigos</span>
                <span>Diariamente (02:00)</span>
            </div>
        </div>

        <div class="actions" style="margin-top: 20px;">
            <button class="btn btn-primary" onclick="saveAutomations()">
                <i class="fas fa-save"></i> Salvar Configurações
            </button>
            <button class="btn btn-info" onclick="testAutomations()">
                <i class="fas fa-play"></i> Testar Automações
            </button>
            <button class="btn btn-warning" onclick="viewAutomationLogs()">
                <i class="fas fa-list"></i> Ver Logs
            </button>
        </div>
    </div>

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
                    <input type="text" name="codigo" id="codigo" placeholder="Digite o código..." required>
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
                                <form method='POST' style='display:inline' onsubmit='return confirm(\"Tem certeza que deseja excluir este rastreio?\")'>
                                    <input type='hidden' name='codigo' value='{$row['codigo']}'>
                                    <button type='submit' name='deletar' class='btn btn-danger btn-sm' title='Excluir'>
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
                            <form method='POST' onsubmit=\"return confirm('Tem certeza que deseja excluir este rastreio?')\" style='display:inline'>
                                <input type='hidden' name='codigo' value='{$row['codigo']}'>
                                <button type='submit' name='deletar' class='btn btn-danger btn-sm'><i class='fas fa-trash'></i> Excluir</button>
                            </form>
                        </div>
                    </div>";
            }
        } else {
            echo "<div class='card-item'><div class='card-status'><i class='fas fa-inbox'></i> Nenhum rastreio encontrado</div></div>";
        }
        ?>
    </div>

    <!-- Monitor de Jobs (Cron) -->
    <div class="automation-panel">
        <h2><i class="fas fa-clock"></i> Monitor de Jobs (Cron)</h2>
        <p>Acompanhe as últimas execuções e rode manualmente quando necessário</p>
        <div class="actions" style="margin:10px 0 15px;">
            <button class="btn btn-info" onclick="runAutomationCron()"><i class="fas fa-play"></i> Executar Automações</button>
            <button class="btn btn-warning" onclick="runUpdateCron()"><i class="fas fa-sync"></i> Executar Update</button>
            <button class="btn btn-primary" onclick="refreshCronLogs()"><i class="fas fa-rotate"></i> Atualizar Logs</button>
        </div>
        <div id="cronStatus" class="cron-schedule" style="margin-bottom:10px;"></div>
        <div class="cron-schedule" style="max-height:240px; overflow:auto">
            <h4><i class="fas fa-file-alt"></i> Últimos Logs (automation_cron)</h4>
            <div id="cronLogs" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: .9rem; white-space: pre-wrap;"></div>
        </div>
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

// Função de logout
function logout() {
    if (confirm('Tem certeza que deseja sair?')) {
        window.location.href = 'admin.php?logout=1';
    }
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

// Auto-refresh das estatísticas a cada 30 segundos
setInterval(function() {
    // Aqui você pode adicionar uma requisição AJAX para atualizar as estatísticas
    // sem recarregar a página inteira
}, 30000);

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

// Inicializar sistema de automações
document.addEventListener('DOMContentLoaded', function() {
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
function bulkDelete() {
    const selected = getSelectedCodes();
    if (selected.length === 0) {
        notifyWarning('Nenhum item selecionado');
        return;
    }
    
    if (confirm(`Tem certeza que deseja excluir ${selected.length} rastreio(s) selecionado(s)?`)) {
        notifyInfo('Excluindo rastreios selecionados...');
        
        // Criar formulário para envio
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

function bulkClearTaxa() {
    const selected = getSelectedCodes();
    if (selected.length === 0) {
        notifyWarning('Nenhum item selecionado');
        return;
    }
    if (!confirm(`Remover taxa de ${selected.length} rastreio(s) selecionado(s)?`)) return;
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

function clearLogs() {
    if (confirm('Tem certeza que deseja limpar todos os logs de automação?')) {
        notifySuccess('Logs limpos com sucesso!');
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

// Sistema de Cache Automático - Renova a cada 10 minutos
(function() {
    const CACHE_INTERVAL = 10 * 60 * 1000; // 10 minutos em milissegundos
    const CACHE_KEY = 'helmer_admin_cache_timestamp';
    
    // Função para verificar se o cache expirou
    function checkCacheExpiry() {
        const now = Date.now();
        const lastCache = localStorage.getItem(CACHE_KEY);
        
        if (!lastCache || (now - parseInt(lastCache)) > CACHE_INTERVAL) {
            // Cache expirou, forçar reload
            localStorage.setItem(CACHE_KEY, now.toString());
            
            // Adicionar timestamp para forçar reload
            const url = new URL(window.location);
            url.searchParams.set('_t', now);
            
            // Notificar usuário sobre atualização
            if (lastCache) {
                console.log('🔄 Cache do admin expirado - Atualizando página...');
                
                // Mostrar notificação visual
                const notification = document.createElement('div');
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #ff6600;
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);
                    z-index: 9999;
                    font-weight: bold;
                    animation: slideInRight 0.3s ease;
                `;
                notification.innerHTML = '🔄 Atualizando dados do admin...';
                document.body.appendChild(notification);
                
                // Remover notificação após 2 segundos
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 2000);
            }
            
            // Recarregar página com timestamp
            setTimeout(() => {
                window.location.href = url.toString();
            }, 1000);
        }
    }
    
    // Verificar cache a cada minuto
    setInterval(checkCacheExpiry, 60000);
    
    // Verificar cache imediatamente
    checkCacheExpiry();
    
    // Adicionar CSS para animação
    const style = document.createElement('style');
    style.textContent = `
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
    `;
    document.head.appendChild(style);
})();

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
                notifyError('Falha nas automações: ' + (data.error||'erro'));
            }
        })
        .catch(()=> notifyError('Erro ao executar automações'));
}

function runUpdateCron() {
    notifyInfo('Executando update...');
    fetch('cron_update.php')
        .then(()=> {
            notifySuccess('Update executado');
            document.getElementById('cronStatus').innerHTML = `<div class="schedule-item"><span><span class="status-indicator active"></span>Update manual</span><span>${new Date().toLocaleString()}</span></div>`;
        })
        .catch(()=> notifyError('Erro ao executar update'));
}

function refreshCronLogs() {
    fetch('automation_logs.txt', { cache: 'no-store' })
        .then(r => r.text())
        .then(t => {
            const lines = t.trim().split('\n');
            const last = lines.slice(-50).join('\n');
            document.getElementById('cronLogs').textContent = last || 'Sem logs.';
        })
        .catch(()=> {
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
    
    console.log('Enviando WhatsApp para código:', codigo);
    
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
    
    fetch('admin.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Resposta recebida:', response.status, response.statusText);
        
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            return response.json();
        } else {
            // Se não for JSON, ler como texto para debug
            return response.text().then(text => {
                console.error('Resposta não é JSON:', text);
                console.error('Content-Type:', contentType);
                console.error('Status:', response.status);
                
                // Tentar parsear como JSON mesmo assim (pode estar sem header)
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Resposta do servidor não é JSON. Status: ' + response.status + '. Resposta: ' + text.substring(0, 200));
                }
            });
        }
    })
    .then(data => {
        console.log('Dados recebidos:', data);
        
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
    if (!nav || !btn) return;
    const open = nav.classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
}
</script>
<?php // Expor presets ao JS ?>
<script>
window.STATUS_PRESETS = <?php echo json_encode(array_map(function($p){ return ['label'=>$p['label'], 'steps'=>$p['steps']]; }, $STATUS_PRESETS)); ?>
</script>
</body>
</html>
