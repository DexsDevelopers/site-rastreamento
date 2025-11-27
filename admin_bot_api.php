<?php
/**
 * API para comandos administrativos via WhatsApp Bot
 * Recebe comandos do bot Node.js e executa ações no painel
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/rastreio_media.php';
require_once 'includes/whatsapp_helper.php';

// Validação de token para segurança
$expectedToken = getDynamicConfig('WHATSAPP_API_TOKEN', 'lucastav8012');
$receivedToken = $_SERVER['HTTP_AUTHORIZATION'] ?? $_POST['token'] ?? '';

if ($receivedToken !== "Bearer $expectedToken" && $receivedToken !== $expectedToken) {
    http_response_code(401);
    die(json_encode(['error' => 'Token inválido']));
}

// Números autorizados para comandos admin
$adminNumbers = getDynamicConfig('ADMIN_WHATSAPP_NUMBERS', []);
if (!is_array($adminNumbers)) {
    // Se for string separada por vírgula, converter para array
    $adminNumbers = array_map('trim', explode(',', $adminNumbers));
}
// Limpar e formatar números
$adminNumbers = array_filter(array_map(function($num) {
    return preg_replace('/\D/', '', $num);
}, $adminNumbers));

header('Content-Type: application/json; charset=UTF-8');

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$command = $input['command'] ?? '';
$params = $input['params'] ?? [];
$fromNumber = $input['from'] ?? '';

// Log de debug
writeLog("DEBUG - Comando recebido: $command de número: $fromNumber", 'DEBUG');
writeLog("DEBUG - Números admin cadastrados: " . json_encode($adminNumbers), 'DEBUG');
writeLog("DEBUG - Comparação: número=$fromNumber, está na lista? " . (in_array($fromNumber, $adminNumbers) ? 'SIM' : 'NÃO'), 'DEBUG');

// Comandos públicos que não precisam de permissão admin
$comandosPublicos = ['menu', 'rastrear', 'codigo', 'track', 'ajuda', 'help'];
$isComandoPublico = in_array(strtolower($command), $comandosPublicos);

// Verificar se é admin (apenas para comandos não-públicos)
if (!$isComandoPublico && !in_array($fromNumber, $adminNumbers)) {
    writeLog("Tentativa de comando admin sem permissão de $fromNumber", 'WARNING');
    echo json_encode([
        'success' => false, 
        'message' => "❌ Você não tem permissão para usar comandos administrativos.\n\n" .
                    "📱 Seu número: $fromNumber\n" .
                    "🔐 Permissões: Cliente\n\n" .
                    "💡 Você pode usar:\n" .
                    "• /rastrear CODIGO - Consultar seu pedido\n" .
                    "• /menu - Ver comandos disponíveis"
    ]);
    exit;
}

// Log do comando
writeLog("Comando WhatsApp recebido de $fromNumber: $command " . json_encode($params), 'INFO');

try {
    switch (strtolower($command)) {
        case 'menu':
            $response = getMenu();
            break;
            
        case 'rastrear':
        case 'codigo':
        case 'track':
            $response = rastrearPedido($params, $pdo, false); // Comando público
            break;
            
        case 'adicionar':
        case 'add':
            $response = adicionarRastreio($params, $pdo);
            break;
            
        case 'status':
            $response = verStatus($params, $pdo);
            break;
            
        case 'listar':
        case 'list':
            $response = listarRastreios($params, $pdo);
            break;
            
        case 'deletar':
        case 'del':
            $response = deletarRastreio($params, $pdo);
            break;
            
        case 'taxa':
            $response = definirTaxa($params, $pdo);
            break;
            
        case 'limpartaxa':
            $response = limparTaxa($params, $pdo);
            break;
            
        case 'foto':
            $response = processarFoto($params, $pdo);
            break;
            
        case 'relatorio':
        case 'stats':
            $response = getRelatorio($pdo);
            break;
            
        case 'pendentes':
            $response = getPendentes($pdo);
            break;
            
        case 'notificar':
            $response = notificarCliente($params, $pdo);
            break;
            
        case 'express':
            $response = aplicarExpress($params, $pdo);
            break;
            
        case 'ajuda':
        case 'help':
            $response = getAjuda($params);
            break;
            
        default:
            $response = [
                'success' => false,
                'message' => "❓ Comando não reconhecido: *$command*\n\nDigite */menu* para ver os comandos disponíveis."
            ];
    }
} catch (Exception $e) {
    writeLog("Erro no comando $command: " . $e->getMessage(), 'ERROR');
    $response = [
        'success' => false,
        'message' => "❌ Erro ao executar comando: " . $e->getMessage()
    ];
}

echo json_encode($response);

// ===== FUNÇÕES DOS COMANDOS =====

function rastrearPedido($params, $pdo, $isAdmin = false) {
    if (count($params) < 1) {
        return [
            'success' => false,
            'message' => "❌ Uso correto: */rastrear* SEU_CODIGO\n\n" .
                        "Exemplo: /rastrear ABC123BR\n\n" .
                        "💡 Digite o código exatamente como recebeu"
        ];
    }
    
    $codigo = strtoupper($params[0]);
    
    // Buscar status
    $sql = "SELECT * FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data DESC";
    $results = fetchData($pdo, $sql, [$codigo]);
    
    if (empty($results)) {
        return [
            'success' => false,
            'message' => "❌ Código *$codigo* não encontrado.\n\n" .
                        "Verifique se digitou corretamente.\n" .
                        "_Exemplo: /rastrear ABC123BR_"
        ];
    }
    
    $ultimoStatus = $results[0];
    $etapaAtual = null;
    $proximaEtapa = null;
    $totalEtapas = count($results);
    $etapaConcluida = 0;
    
    // Encontrar etapa atual
    $agora = time();
    foreach (array_reverse($results) as $idx => $etapa) {
        $dataEtapa = strtotime($etapa['data']);
        if ($dataEtapa <= $agora) {
            $etapaAtual = $etapa;
            $etapaConcluida = $idx + 1;
            if (isset($results[count($results) - $idx - 2])) {
                $proximaEtapa = $results[count($results) - $idx - 2];
            }
            break;
        }
    }
    
    if (!$etapaAtual) {
        $etapaAtual = end($results);
        $proximaEtapa = prev($results);
    }
    
    $message = "📦 *RASTREAMENTO: $codigo*\n\n";
    $message .= "📍 Destino: {$ultimoStatus['cidade']}\n";
    $message .= "📊 Etapa atual: *{$etapaAtual['titulo']}*\n";
    $message .= "📝 {$etapaAtual['subtitulo']}\n";
    $message .= "🕐 " . date('d/m/Y às H:i', strtotime($etapaAtual['data'])) . "\n\n";
    
    // Barra de progresso
    $progresso = round(($etapaConcluida / $totalEtapas) * 100);
    $barraCheia = str_repeat('▰', floor($progresso / 10));
    $barraVazia = str_repeat('▱', 10 - floor($progresso / 10));
    $message .= "Progresso: {$barraCheia}{$barraVazia} {$progresso}%\n";
    $message .= "Etapa {$etapaConcluida} de {$totalEtapas}\n\n";
    
    // Taxa pendente
    if (!empty($ultimoStatus['taxa_valor']) && !empty($ultimoStatus['taxa_pix'])) {
        $message .= "⚠️ *TAXA PENDENTE*\n";
        $message .= "💰 Valor: R$ " . number_format($ultimoStatus['taxa_valor'], 2, ',', '.') . "\n";
        $message .= "🔑 PIX: `{$ultimoStatus['taxa_pix']}`\n";
        $message .= "_Copie a chave PIX acima e pague para liberar_\n\n";
    }
    
    // Próxima etapa
    if ($proximaEtapa && $etapaConcluida < $totalEtapas) {
        $horasRestantes = round((strtotime($proximaEtapa['data']) - $agora) / 3600);
        if ($horasRestantes > 0) {
            $message .= "⏭️ *Próxima atualização*\n";
            $message .= "{$proximaEtapa['titulo']}\n";
            if ($horasRestantes < 24) {
                $message .= "Em aproximadamente {$horasRestantes} horas\n\n";
            } else {
                $dias = round($horasRestantes / 24);
                $message .= "Em aproximadamente {$dias} dia" . ($dias > 1 ? 's' : '') . "\n\n";
            }
        }
    } elseif ($etapaConcluida >= $totalEtapas) {
        $message .= "✅ *PEDIDO ENTREGUE!*\n";
        $message .= "Obrigado por confiar em nossos serviços.\n\n";
    }
    
    // Link para acompanhar
    $trackingUrl = getDynamicConfig('WHATSAPP_TRACKING_URL', '');
    if ($trackingUrl) {
        $link = str_replace('{{codigo}}', $codigo, $trackingUrl);
        $message .= "🔗 Acompanhe online:\n{$link}\n\n";
    }
    
    $message .= "_Digite /rastrear CODIGO para nova consulta_";
    
    return ['success' => true, 'message' => $message];
}

function getMenu() {
    return [
        'success' => true,
        'message' => "📋 *MENU DE COMANDOS ADMIN*\n\n" .
                    "*📦 GESTÃO DE RASTREIOS*\n" .
                    "*/adicionar* CODIGO CIDADE - Criar novo rastreio\n" .
                    "*/status* CODIGO - Ver etapas atuais\n" .
                    "*/listar* [quantidade] - Ver últimos códigos\n" .
                    "*/deletar* CODIGO - Remover rastreio\n\n" .
                    
                    "*💰 GESTÃO DE TAXAS*\n" .
                    "*/taxa* CODIGO VALOR PIX - Adicionar taxa\n" .
                    "*/limpartaxa* CODIGO - Remover taxa\n" .
                    "*/express* CODIGO - Aplicar entrega expressa\n\n" .
                    
                    "*📸 GESTÃO DE FOTOS*\n" .
                    "*/foto* CODIGO - Prepara para receber foto\n" .
                    "_(Envie a foto logo após o comando)_\n\n" .
                    
                    "*📊 CONSULTAS*\n" .
                    "*/relatorio* - Estatísticas do sistema\n" .
                    "*/pendentes* - Códigos sem foto\n\n" .
                    
                    "*💬 COMUNICAÇÃO*\n" .
                    "*/notificar* CODIGO MENSAGEM - Enviar msg ao cliente\n\n" .
                    
                    "*❓ AJUDA*\n" .
                    "*/ajuda* COMANDO - Detalhes de um comando\n" .
                    "*/menu* - Exibir este menu\n\n" .
                    
                    "💡 _Digite o comando seguido dos parâmetros_\n" .
                    "_Ex: /adicionar ABC123 São Paulo_"
    ];
}

function adicionarRastreio($params, $pdo) {
    if (count($params) < 2) {
        return [
            'success' => false,
            'message' => "❌ Uso correto: */adicionar* CODIGO CIDADE\n\nExemplo: /adicionar ABC123BR São Paulo"
        ];
    }
    
    $codigo = strtoupper($params[0]);
    $cidade = implode(' ', array_slice($params, 1));
    
    // Verificar se já existe
    $exists = fetchOne($pdo, "SELECT 1 FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ?", [$codigo]);
    if ($exists) {
        return [
            'success' => false,
            'message' => "⚠️ O código *$codigo* já existe no sistema."
        ];
    }
    
    // Adicionar etapas padrão
    $etapas = [
        ["📦 Objeto postado", "Objeto recebido no ponto de coleta", "#16A34A", 0],
        ["🚚 Em trânsito", "A caminho do centro de distribuição", "#F59E0B", 24],
        ["🏢 No centro de distribuição", "Processando encaminhamento", "#FBBF24", 48],
        ["🚀 Saiu para entrega", "Saiu para entrega ao destinatário", "#EF4444", 72],
        ["✅ Entregue", "Objeto entregue com sucesso", "#16A34A", 96]
    ];
    
    $dataInicial = time();
    foreach ($etapas as $etapa) {
        list($titulo, $subtitulo, $cor, $offsetHours) = $etapa;
        $data = date('Y-m-d H:i:s', strtotime("+{$offsetHours} hour", $dataInicial));
        
        $sql = "INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, data, cor) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        executeQuery($pdo, $sql, [$codigo, $cidade, $titulo, $titulo, $subtitulo, $data, $cor]);
    }
    
    return [
        'success' => true,
        'message' => "✅ Rastreio *$codigo* adicionado com sucesso!\n\n" .
                    "📍 Cidade: $cidade\n" .
                    "📅 Etapas criadas: 5\n\n" .
                    "_Use /status $codigo para ver detalhes_"
    ];
}

function verStatus($params, $pdo) {
    if (count($params) < 1) {
        return [
            'success' => false,
            'message' => "❌ Uso correto: */status* CODIGO\n\nExemplo: /status ABC123BR"
        ];
    }
    
    $codigo = strtoupper($params[0]);
    
    $sql = "SELECT * FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data DESC";
    $results = fetchData($pdo, $sql, [$codigo]);
    
    if (empty($results)) {
        return [
            'success' => false,
            'message' => "❌ Código *$codigo* não encontrado."
        ];
    }
    
    $ultimoStatus = $results[0];
    $totalEtapas = count($results);
    $temTaxa = !empty($ultimoStatus['taxa_valor']) && !empty($ultimoStatus['taxa_pix']);
    
    // Verificar se tem foto
    $temFoto = false;
    try {
        $foto = fetchRastreioFotoPath($pdo, $codigo);
        $temFoto = !empty($foto);
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    $message = "📦 *STATUS: $codigo*\n\n";
    $message .= "📍 Cidade: {$ultimoStatus['cidade']}\n";
    $message .= "📊 Status atual: {$ultimoStatus['status_atual']}\n";
    $message .= "📅 Última atualização: " . date('d/m/Y H:i', strtotime($ultimoStatus['data'])) . "\n";
    $message .= "🔢 Total de etapas: $totalEtapas\n";
    
    if ($temTaxa) {
        $message .= "\n💰 *TAXA PENDENTE*\n";
        $message .= "Valor: R$ " . number_format($ultimoStatus['taxa_valor'], 2, ',', '.') . "\n";
        $message .= "PIX: {$ultimoStatus['taxa_pix']}\n";
    }
    
    if ($temFoto) {
        $message .= "📸 Foto: ✅ Anexada\n";
    } else {
        $message .= "📸 Foto: ❌ Não anexada\n";
    }
    
    $message .= "\n_Use /listar para ver outros códigos_";
    
    return ['success' => true, 'message' => $message];
}

function listarRastreios($params, $pdo) {
    $limite = isset($params[0]) ? (int)$params[0] : 10;
    $limite = min($limite, 50); // Máximo 50
    
    $sql = "SELECT DISTINCT codigo, MAX(cidade) as cidade, MAX(status_atual) as status_atual, 
            MAX(data) as ultima_atualizacao
            FROM rastreios_status 
            GROUP BY codigo 
            ORDER BY MAX(data) DESC 
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limite]);
    $results = $stmt->fetchAll();
    
    if (empty($results)) {
        return ['success' => true, 'message' => "📭 Nenhum rastreio cadastrado."];
    }
    
    $message = "📋 *ÚLTIMOS $limite RASTREIOS*\n\n";
    
    foreach ($results as $idx => $row) {
        $num = $idx + 1;
        $emoji = strpos($row['status_atual'], 'Entregue') !== false ? '✅' : '📦';
        $message .= "{$num}. {$emoji} *{$row['codigo']}*\n";
        $message .= "   📍 {$row['cidade']}\n";
        $message .= "   📅 " . date('d/m H:i', strtotime($row['ultima_atualizacao'])) . "\n\n";
    }
    
    $message .= "_Use /status CODIGO para detalhes_";
    
    return ['success' => true, 'message' => $message];
}

function deletarRastreio($params, $pdo) {
    if (count($params) < 1) {
        return [
            'success' => false,
            'message' => "❌ Uso correto: */deletar* CODIGO\n\nExemplo: /deletar ABC123BR"
        ];
    }
    
    $codigo = strtoupper($params[0]);
    
    // Verificar se existe
    $exists = fetchOne($pdo, "SELECT 1 FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ?", [$codigo]);
    if (!$exists) {
        return [
            'success' => false,
            'message' => "❌ Código *$codigo* não encontrado."
        ];
    }
    
    // Deletar
    executeQuery($pdo, "DELETE FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ?", [$codigo]);
    
    // Limpar foto se houver
    try {
        removeRastreioFoto($pdo, $codigo);
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    return [
        'success' => true,
        'message' => "🗑️ Rastreio *$codigo* excluído com sucesso!"
    ];
}

function definirTaxa($params, $pdo) {
    if (count($params) < 3) {
        return [
            'success' => false,
            'message' => "❌ Uso correto: */taxa* CODIGO VALOR CHAVE_PIX\n\n" .
                        "Exemplo: /taxa ABC123BR 29.90 email@exemplo.com"
        ];
    }
    
    $codigo = strtoupper($params[0]);
    $valor = (float) str_replace(',', '.', $params[1]);
    $pix = implode(' ', array_slice($params, 2));
    
    // Verificar se existe
    $exists = fetchOne($pdo, "SELECT 1 FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ?", [$codigo]);
    if (!$exists) {
        return [
            'success' => false,
            'message' => "❌ Código *$codigo* não encontrado."
        ];
    }
    
    // Atualizar taxa
    $sql = "UPDATE rastreios_status SET taxa_valor = ?, taxa_pix = ? WHERE UPPER(TRIM(codigo)) = ?";
    executeQuery($pdo, $sql, [$valor, $pix, $codigo]);
    
    // Notificar cliente se tiver WhatsApp cadastrado
    try {
        notifyWhatsappTaxa($pdo, $codigo, $valor, $pix);
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    return [
        'success' => true,
        'message' => "💰 Taxa definida para *$codigo*:\n\n" .
                    "💵 Valor: R$ " . number_format($valor, 2, ',', '.') . "\n" .
                    "🔑 PIX: $pix\n\n" .
                    "_Cliente será notificado se tiver WhatsApp cadastrado_"
    ];
}

function limparTaxa($params, $pdo) {
    if (count($params) < 1) {
        return [
            'success' => false,
            'message' => "❌ Uso correto: */limpartaxa* CODIGO\n\nExemplo: /limpartaxa ABC123BR"
        ];
    }
    
    $codigo = strtoupper($params[0]);
    
    $sql = "UPDATE rastreios_status SET taxa_valor = NULL, taxa_pix = NULL WHERE UPPER(TRIM(codigo)) = ?";
    executeQuery($pdo, $sql, [$codigo]);
    
    return [
        'success' => true,
        'message' => "✅ Taxa removida do código *$codigo*"
    ];
}

function processarFoto($params, $pdo) {
    if (count($params) < 1) {
        return [
            'success' => false,
            'message' => "❌ Uso correto: */foto* CODIGO\n\n" .
                        "Após enviar este comando, envie a foto do pedido.\n\n" .
                        "Exemplo: /foto ABC123BR"
        ];
    }
    
    $codigo = strtoupper($params[0]);
    
    // Verificar se existe
    $exists = fetchOne($pdo, "SELECT 1 FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ?", [$codigo]);
    if (!$exists) {
        return [
            'success' => false,
            'message' => "❌ Código *$codigo* não encontrado."
        ];
    }
    
    // Salvar código na sessão para quando a foto chegar
    return [
        'success' => true,
        'message' => "📸 Modo foto ativado para *$codigo*\n\n" .
                    "Agora envie a foto do pedido.\n" .
                    "_A próxima imagem será anexada a este código_",
        'waiting_photo' => true,
        'photo_codigo' => $codigo
    ];
}

function getRelatorio($pdo) {
    // Estatísticas gerais
    $totalCodigos = (int) $pdo->query("SELECT COUNT(DISTINCT codigo) FROM rastreios_status")->fetchColumn();
    $comTaxa = (int) $pdo->query("SELECT COUNT(DISTINCT codigo) FROM rastreios_status WHERE taxa_valor IS NOT NULL")->fetchColumn();
    $entregues = (int) $pdo->query("SELECT COUNT(DISTINCT codigo) FROM rastreios_status WHERE status_atual LIKE '%Entregue%'")->fetchColumn();
    
    // Fotos
    $comFoto = 0;
    try {
        $comFoto = (int) $pdo->query("SELECT COUNT(*) FROM rastreios_midias")->fetchColumn();
    } catch (Exception $e) {
        // Tabela pode não existir
    }
    
    // Últimas 24h
    $ultimas24h = (int) $pdo->query("SELECT COUNT(DISTINCT codigo) FROM rastreios_status WHERE data >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    
    $message = "📊 *RELATÓRIO DO SISTEMA*\n\n";
    $message .= "📦 Total de códigos: *$totalCodigos*\n";
    $message .= "✅ Entregues: *$entregues*\n";
    $message .= "💰 Com taxa: *$comTaxa*\n";
    $message .= "📸 Com foto: *$comFoto*\n";
    $message .= "🕐 Últimas 24h: *$ultimas24h* atualizações\n\n";
    
    $percentualFotos = $totalCodigos > 0 ? round(($comFoto / $totalCodigos) * 100, 1) : 0;
    $percentualEntregues = $totalCodigos > 0 ? round(($entregues / $totalCodigos) * 100, 1) : 0;
    
    $message .= "📈 *MÉTRICAS*\n";
    $message .= "• Taxa de fotos: $percentualFotos%\n";
    $message .= "• Taxa de entrega: $percentualEntregues%\n\n";
    
    $message .= "_Use /pendentes para ver códigos sem foto_";
    
    return ['success' => true, 'message' => $message];
}

function getPendentes($pdo) {
    // Códigos sem foto
    $sql = "SELECT DISTINCT rs.codigo, rs.cidade, rs.status_atual 
            FROM rastreios_status rs
            LEFT JOIN rastreios_midias rm ON rs.codigo = rm.codigo
            WHERE rm.id IS NULL
            GROUP BY rs.codigo
            ORDER BY rs.data DESC
            LIMIT 20";
    
    try {
        $results = fetchData($pdo, $sql);
    } catch (Exception $e) {
        // Se falhar, tentar sem JOIN (tabela pode não existir)
        $sql = "SELECT DISTINCT codigo, MAX(cidade) as cidade, MAX(status_atual) as status_atual 
                FROM rastreios_status 
                GROUP BY codigo 
                ORDER BY MAX(data) DESC 
                LIMIT 20";
        $results = fetchData($pdo, $sql);
    }
    
    if (empty($results)) {
        return ['success' => true, 'message' => "✅ Todos os códigos têm foto anexada!"];
    }
    
    $message = "📸 *CÓDIGOS SEM FOTO*\n\n";
    
    foreach ($results as $idx => $row) {
        $num = $idx + 1;
        $message .= "{$num}. *{$row['codigo']}*\n";
        $message .= "   📍 {$row['cidade']}\n\n";
    }
    
    $message .= "_Use /foto CODIGO para anexar uma foto_";
    
    return ['success' => true, 'message' => $message];
}

function notificarCliente($params, $pdo) {
    if (count($params) < 2) {
        return [
            'success' => false,
            'message' => "❌ Uso correto: */notificar* CODIGO MENSAGEM\n\n" .
                        "Exemplo: /notificar ABC123BR Seu pedido está chegando hoje!"
        ];
    }
    
    $codigo = strtoupper($params[0]);
    $mensagem = implode(' ', array_slice($params, 1));
    
    // Buscar contato do cliente
    $contato = getWhatsappContact($pdo, $codigo);
    if (!$contato || empty($contato['telefone_normalizado'])) {
        return [
            'success' => false,
            'message' => "❌ Cliente do código *$codigo* não tem WhatsApp cadastrado."
        ];
    }
    
    // Enviar notificação personalizada
    $customMessage = "🔔 *Atualização do seu pedido $codigo*\n\n$mensagem";
    
    try {
        $result = sendWhatsappMessage($contato['telefone_normalizado'], $customMessage);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => "✅ Mensagem enviada para *{$contato['nome']}*\n" .
                            "📱 {$contato['telefone_original']}\n\n" .
                            "_Mensagem: $mensagem_"
            ];
        } else {
            return [
                'success' => false,
                'message' => "❌ Falha ao enviar mensagem: " . ($result['error'] ?? 'Erro desconhecido')
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "❌ Erro ao enviar: " . $e->getMessage()
        ];
    }
}

function aplicarExpress($params, $pdo) {
    if (count($params) < 1) {
        return [
            'success' => false,
            'message' => "❌ Uso correto: */express* CODIGO\n\nExemplo: /express ABC123BR"
        ];
    }
    
    $codigo = strtoupper($params[0]);
    
    // Verificar se existe
    $cidade = fetchOne($pdo, "SELECT cidade FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? LIMIT 1", [$codigo]);
    if (!$cidade) {
        return [
            'success' => false,
            'message' => "❌ Código *$codigo* não encontrado."
        ];
    }
    
    // Remover status anteriores
    executeQuery($pdo, "DELETE FROM rastreios_status WHERE codigo = ?", [$codigo]);
    
    // Aplicar preset express (3 dias)
    $presetExpress = [
        ["📦 Objeto postado", "Objeto recebido no ponto de coleta", "#16A34A", 0],
        ["🚚 Em trânsito", "A caminho do centro de distribuição", "#F59E0B", 12],
        ["🏢 No centro de distribuição", "Processando encaminhamento", "#FBBF24", 36],
        ["🚀 Saiu para entrega", "Saiu para entrega ao destinatário", "#EF4444", 60],
        ["✅ Entregue", "Objeto entregue com sucesso", "#16A34A", 72]
    ];
    
    $inicio = time();
    foreach ($presetExpress as $etapa) {
        list($titulo, $subtitulo, $cor, $offsetHours) = $etapa;
        $data = date('Y-m-d H:i:s', strtotime("+{$offsetHours} hour", $inicio));
        
        $sql = "INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, data, cor, prioridade) 
                VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)";
        executeQuery($pdo, $sql, [$codigo, $cidade['cidade'], $titulo, $titulo, $subtitulo, $data, $cor]);
    }
    
    // Notificar cliente
    try {
        notifyWhatsappLatestStatus($pdo, $codigo);
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    return [
        'success' => true,
        'message' => "⚡ *Entrega Express aplicada!*\n\n" .
                    "📦 Código: *$codigo*\n" .
                    "🚀 Entrega em: 3 dias\n" .
                    "✅ Prioridade: Ativada\n\n" .
                    "_Cliente foi notificado da atualização_"
    ];
}

function getAjuda($params) {
    if (count($params) < 1) {
        return getMenu();
    }
    
    $comando = strtolower($params[0]);
    
    $ajudas = [
        'adicionar' => "📦 *COMANDO: /adicionar*\n\n" .
                      "Cria um novo rastreio com etapas padrão.\n\n" .
                      "*Uso:* /adicionar CODIGO CIDADE\n" .
                      "*Exemplo:* /adicionar ABC123BR São Paulo\n\n" .
                      "• O código será convertido para maiúsculas\n" .
                      "• 5 etapas serão criadas automaticamente\n" .
                      "• Distribuídas ao longo de 4 dias",
                      
        'foto' => "📸 *COMANDO: /foto*\n\n" .
                 "Anexa uma foto a um pedido.\n\n" .
                 "*Uso:* /foto CODIGO\n" .
                 "*Exemplo:* /foto ABC123BR\n\n" .
                 "• Após enviar o comando, envie a foto\n" .
                 "• A foto será vinculada ao código\n" .
                 "• Aparecerá para o cliente na consulta",
                 
        'taxa' => "💰 *COMANDO: /taxa*\n\n" .
                 "Define uma taxa para o pedido.\n\n" .
                 "*Uso:* /taxa CODIGO VALOR CHAVE_PIX\n" .
                 "*Exemplo:* /taxa ABC123BR 29.90 email@pix.com\n\n" .
                 "• O cliente será notificado\n" .
                 "• Aparecerá campo PIX na consulta\n" .
                 "• Use /limpartaxa para remover",
                 
        'notificar' => "💬 *COMANDO: /notificar*\n\n" .
                      "Envia mensagem personalizada ao cliente.\n\n" .
                      "*Uso:* /notificar CODIGO MENSAGEM\n" .
                      "*Exemplo:* /notificar ABC123BR Seu pedido chega hoje!\n\n" .
                      "• Cliente precisa ter WhatsApp cadastrado\n" .
                      "• Mensagem é enviada imediatamente\n" .
                      "• Útil para avisos especiais"
    ];
    
    $ajuda = $ajudas[$comando] ?? null;
    
    if (!$ajuda) {
        return [
            'success' => false,
            'message' => "❓ Comando *$comando* não encontrado.\n\nDigite */menu* para ver todos os comandos."
        ];
    }
    
    return ['success' => true, 'message' => $ajuda];
}
