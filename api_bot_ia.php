<?php
/**
 * API de IA para o Bot WhatsApp
 * Processa mensagens usando Gemini + Base de Conhecimento + Aprendizado
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, x-api-token');

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Validar token
$expectedToken = getDynamicConfig('WHATSAPP_API_TOKEN', 'lucastav8012');
$receivedToken = $_SERVER['HTTP_X_API_TOKEN'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$receivedToken = str_replace('Bearer ', '', $receivedToken);

if ($receivedToken !== $expectedToken) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token inválido']);
    exit;
}

// Verificar tabelas
try {
    $pdo->query("SELECT 1 FROM bot_ia_settings LIMIT 1");
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Sistema de IA não configurado. Execute setup_bot_ia.php'
    ]);
    exit;
}

// Funções auxiliares
function getIASetting($pdo, $key, $default = null) {
    $stmt = $pdo->prepare("SELECT setting_value FROM bot_ia_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

function setIASetting($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO bot_ia_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$key, $value, $value]);
}

function searchKnowledge($pdo, $query) {
    $queryLower = mb_strtolower(trim($query));
    $queryWords = explode(' ', $queryLower);
    $queryWords = array_filter($queryWords, function($w) { return strlen($w) >= 2; });
    
    // 1. Busca exata na pergunta (mais precisa)
    $stmt = $pdo->prepare("
        SELECT pergunta, resposta, categoria, prioridade 
        FROM bot_ia_knowledge 
        WHERE ativo = 1 
        AND LOWER(pergunta) LIKE ?
        ORDER BY prioridade DESC, uso_count DESC
        LIMIT 1
    ");
    $stmt->execute(["%{$queryLower}%"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Verificar similaridade - se a pergunta do conhecimento contém palavras-chave da query
        $knowledgeWords = explode(' ', mb_strtolower($result['pergunta']));
        $matchCount = 0;
        foreach ($queryWords as $qw) {
            foreach ($knowledgeWords as $kw) {
                if (strpos($kw, $qw) !== false || strpos($qw, $kw) !== false) {
                    $matchCount++;
                    break;
                }
            }
        }
        $similarity = count($queryWords) > 0 ? ($matchCount / count($queryWords)) : 0;
        
        // Só retornar se similaridade for alta (>= 60%) ou prioridade muito alta (>= 90)
        if ($similarity >= 0.6 || $result['prioridade'] >= 90) {
            $pdo->exec("UPDATE bot_ia_knowledge SET uso_count = uso_count + 1 WHERE pergunta = " . $pdo->quote($result['pergunta']));
            return $result;
        }
    }
    
    // 2. Busca por palavras-chave (mais específica)
    foreach ($queryWords as $word) {
        if (strlen($word) < 3) continue; // Ignorar palavras muito curtas
        
        $stmt = $pdo->prepare("
            SELECT pergunta, resposta, categoria, prioridade 
            FROM bot_ia_knowledge 
            WHERE ativo = 1 
            AND LOWER(palavras_chave) LIKE ?
            AND prioridade >= 85
            ORDER BY prioridade DESC, uso_count DESC
            LIMIT 1
        ");
        $stmt->execute(["%{$word}%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $pdo->exec("UPDATE bot_ia_knowledge SET uso_count = uso_count + 1 WHERE pergunta = " . $pdo->quote($result['pergunta']));
            return $result;
        }
    }
    
    // 3. Busca FULLTEXT (apenas se score muito alto)
    try {
        $stmt = $pdo->prepare("
            SELECT pergunta, resposta, categoria, prioridade,
                   MATCH(pergunta) AGAINST(? IN NATURAL LANGUAGE MODE) as score
            FROM bot_ia_knowledge 
            WHERE ativo = 1 
            AND MATCH(pergunta) AGAINST(? IN NATURAL LANGUAGE MODE)
            ORDER BY score DESC, prioridade DESC
            LIMIT 1
        ");
        $stmt->execute([$queryLower, $queryLower]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Score mais alto necessário (0.7 ao invés de 0.5)
        if ($result && $result['score'] > 0.7 && $result['prioridade'] >= 80) {
            $pdo->exec("UPDATE bot_ia_knowledge SET uso_count = uso_count + 1 WHERE pergunta = " . $pdo->quote($result['pergunta']));
            return $result;
        }
    } catch (Exception $e) {
        // FULLTEXT pode falhar, ignorar
    }
    
    // Se não encontrou correspondência forte, retornar null para usar IA
    return null;
}

function getConversationContext($pdo, $phone, $limit = 10) {
    // Pegar apenas mensagens recentes (últimas 30 minutos) para evitar contexto antigo
    $stmt = $pdo->prepare("
        SELECT role, message, created_at
        FROM bot_ia_conversations 
        WHERE phone_number = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$phone, $limit]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_reverse($messages);
}

function saveConversation($pdo, $phone, $role, $message) {
    $stmt = $pdo->prepare("INSERT INTO bot_ia_conversations (phone_number, role, message) VALUES (?, ?, ?)");
    $stmt->execute([$phone, $role, $message]);
    
    // Limpar conversas antigas (manter últimas 100)
    $pdo->exec("DELETE FROM bot_ia_conversations WHERE phone_number = " . $pdo->quote($phone) . " AND id NOT IN (SELECT id FROM (SELECT id FROM bot_ia_conversations WHERE phone_number = " . $pdo->quote($phone) . " ORDER BY created_at DESC LIMIT 100) tmp)");
}

function saveFeedback($pdo, $phone, $pergunta, $respostaIA) {
    $stmt = $pdo->prepare("INSERT INTO bot_ia_feedback (phone_number, pergunta_original, resposta_ia) VALUES (?, ?, ?)");
    $stmt->execute([$phone, $pergunta, $respostaIA]);
    return $pdo->lastInsertId();
}

function callGeminiAPI($apiKey, $model, $messages, $systemPrompt, $temperature, $maxTokens) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    
    // Construir conteúdo
    $contents = [];
    
    // Adicionar mensagens de contexto
    foreach ($messages as $msg) {
        $contents[] = [
            'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $msg['message']]]
        ];
    }
    
    $data = [
        'contents' => $contents,
        'systemInstruction' => [
            'parts' => [['text' => $systemPrompt]]
        ],
        'generationConfig' => [
            'temperature' => (float)$temperature,
            'maxOutputTokens' => (int)$maxTokens,
            'topP' => 0.95,
            'topK' => 40
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => "Erro cURL: {$error}"];
    }
    
        if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? "HTTP {$httpCode}";
        $errorDetails = $errorData['error'] ?? [];
        
        // Detectar quota excedida especificamente
        $isQuotaExceeded = false;
        if ($httpCode === 429 || strpos(strtolower($errorMsg), 'quota') !== false || strpos(strtolower($errorMsg), 'exceeded') !== false) {
            $isQuotaExceeded = true;
        }
        
        return [
            'success' => false, 
            'error' => $errorMsg, 
            'httpCode' => $httpCode,
            'quotaExceeded' => $isQuotaExceeded,
            'errorDetails' => $errorDetails
        ];
    }
    
    $result = json_decode($response, true);
    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
    
    if (!$text) {
        return ['success' => false, 'error' => 'Resposta vazia da IA'];
    }
    
    return ['success' => true, 'response' => $text];
}

// ========== PROCESSAMENTO ==========
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? 'chat';
$phone = $input['phone'] ?? '';
$message = trim($input['message'] ?? $input['pergunta'] ?? '');

switch ($action) {
    case 'chat':
        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Mensagem vazia']);
            exit;
        }
        
        // Verificar se IA está habilitada
        if (getIASetting($pdo, 'ia_enabled', '1') !== '1') {
            echo json_encode(['success' => false, 'message' => 'IA desabilitada']);
            exit;
        }
        
        // Log da mensagem recebida
        error_log("[BOT_IA] Mensagem recebida: " . substr($message, 0, 100) . " | Phone: {$phone} | Timestamp: " . date('Y-m-d H:i:s'));
        
        // Salvar mensagem do usuário
        if ($phone) {
            saveConversation($pdo, $phone, 'user', $message);
        }
        
        // Base de conhecimento desabilitada - usar apenas IA
        // (Comentado para usar apenas IA do Gemini)
        /*
        // Verificar se é pergunta sobre data/hora (sempre usar IA para essas)
        $messageLower = mb_strtolower(trim($message));
        $isDateTimeQuestion = preg_match('/\b(hoje|agora|que\s+dia|que\s+hora|data|horário|dia\s+é|hora\s+é|quando)\b/i', $messageLower);
        
        // 1. Tentar base de conhecimento primeiro (exceto perguntas sobre data/hora)
        if (getIASetting($pdo, 'ia_use_knowledge', '1') === '1' && !$isDateTimeQuestion) {
            $knowledge = searchKnowledge($pdo, $message);
            if ($knowledge) {
                $response = $knowledge['resposta'];
                
                if ($phone) {
                    saveConversation($pdo, $phone, 'assistant', $response);
                }
                
                echo json_encode([
                    'success' => true,
                    'response' => $response,
                    'source' => 'knowledge',
                    'category' => $knowledge['categoria']
                ]);
                exit;
            }
        }
        */
        
        // 2. Usar Gemini
        $apiKey = getIASetting($pdo, 'gemini_api_key', '');
        if (empty($apiKey)) {
            // Mensagem mais útil quando API key não está configurada
            $fallback = getIASetting($pdo, 'ia_fallback_response', 'Desculpe, não consigo responder agora. A IA ainda não está configurada completamente.');
            
            // Log para debug
            error_log("[BOT_IA] API Key não configurada. Mensagem: " . substr($message, 0, 50));
            
            echo json_encode([
                'success' => true,
                'response' => $fallback,
                'source' => 'fallback',
                'error' => 'API Key não configurada',
                'needs_config' => true
            ]);
            exit;
        }
        
        // Verificar se quota está desabilitada (flag de quota excedida)
        $quotaDisabled = getIASetting($pdo, 'ia_quota_disabled', '0') === '1';
        if ($quotaDisabled) {
            // Se quota está desabilitada, usar apenas base de conhecimento
            $fallback = getIASetting($pdo, 'ia_fallback_response', 'Desculpe, a IA está temporariamente indisponível. Tente novamente mais tarde.');
            error_log("[BOT_IA] Quota desabilitada - usando apenas base de conhecimento");
            
            echo json_encode([
                'success' => true,
                'response' => $fallback,
                'source' => 'fallback',
                'error' => 'Quota desabilitada',
                'quota_exceeded' => true
            ]);
            exit;
        }
        
        $model = getIASetting($pdo, 'ia_model', 'gemini-2.5-flash');
        $systemPrompt = getIASetting($pdo, 'ia_system_prompt', 'Você é um assistente virtual amigável.');
        $temperature = getIASetting($pdo, 'ia_temperature', '0.7');
        $maxTokens = getIASetting($pdo, 'ia_max_tokens', '500');
        $contextLimit = (int)getIASetting($pdo, 'ia_context_messages', '10');
        
        // Adicionar data/hora atual ao prompt
        date_default_timezone_set('America/Sao_Paulo');
        $currentDate = date('d/m/Y');
        $currentTime = date('H:i');
        $currentDay = date('l'); // Nome do dia em inglês
        $dayNames = [
            'Monday' => 'Segunda-feira',
            'Tuesday' => 'Terça-feira',
            'Wednesday' => 'Quarta-feira',
            'Thursday' => 'Quinta-feira',
            'Friday' => 'Sexta-feira',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo'
        ];
        $currentDayPT = $dayNames[$currentDay] ?? $currentDay;
        
        $dateTimeInfo = "\n\nINFORMAÇÕES ATUAIS:\n";
        $dateTimeInfo .= "- Data atual: {$currentDayPT}, {$currentDate}\n";
        $dateTimeInfo .= "- Hora atual: {$currentTime} (horário de Brasília)\n";
        $dateTimeInfo .= "- Quando o usuário perguntar sobre data, hora, dia da semana, etc., use essas informações.\n";
        
        // Base de conhecimento desabilitada - usar apenas IA
        $fullSystemPrompt = $systemPrompt . $dateTimeInfo;
        
        // Obter contexto da conversa
        $context = [];
        if ($phone) {
            $context = getConversationContext($pdo, $phone, $contextLimit);
        }
        
        // Se não tem contexto, adiciona só a mensagem atual
        if (empty($context)) {
            $context = [['role' => 'user', 'message' => $message]];
        }
        
        // Tentar modelos em cascata (mesma ordem do financeiro que está funcionando)
        $models = [
            'gemini-2.5-flash',      // Primary - modelo mais recente
            'gemini-1.5-flash',      // Standard fallback
            'gemini-1.5-flash-001',  // Legacy stable
            'gemini-1.5-pro'         // High capacity fallback
        ];
        
        // Se o modelo configurado não estiver na lista, adicionar no início
        if (!in_array($model, $models)) {
            array_unshift($models, $model);
        }
        $result = null;
        
        foreach ($models as $tryModel) {
            $result = callGeminiAPI($apiKey, $tryModel, $context, $fullSystemPrompt, $temperature, $maxTokens);
            if ($result['success']) {
                break;
            }
            // Se for rate limit, para
            if (isset($result['httpCode']) && $result['httpCode'] === 429) {
                break;
            }
        }
        
        if (!$result['success']) {
            $fallback = getIASetting($pdo, 'ia_fallback_response', 'Desculpe, não consigo responder agora. Tente novamente em alguns instantes.');
            
            // Log detalhado do erro
            $errorMsg = $result['error'] ?? 'Erro desconhecido';
            $httpCode = $result['httpCode'] ?? null;
            $quotaExceeded = $result['quotaExceeded'] ?? false;
            
            error_log("[BOT_IA] Erro ao chamar Gemini: {$errorMsg}" . ($httpCode ? " (HTTP {$httpCode})" : ""));
            error_log("[BOT_IA] Mensagem original: " . substr($message, 0, 100));
            
            // Se quota foi excedida, avisar mas não desativar automaticamente (pode ser temporário)
            if ($quotaExceeded || ($httpCode === 429 && strpos(strtolower($errorMsg), 'quota') !== false)) {
                // Não desativar automaticamente - pode ser temporário ou modelo específico
                // Apenas logar o erro
                error_log("[BOT_IA] ⚠️ QUOTA EXCEDIDA ou RATE LIMIT - Tentando outros modelos ou aguardando.");
                
                // Tentar outros modelos antes de desistir
                $fallback = '⏳ Estou recebendo muitas requisições agora. Aguarde alguns instantes e tente novamente.';
            } elseif ($httpCode === 429) {
                // Rate limit temporário
                $fallback = '⏳ Estou recebendo muitas mensagens agora. Aguarde alguns minutos e tente novamente.';
            } elseif (strpos(strtolower($errorMsg), 'api key') !== false || strpos(strtolower($errorMsg), 'invalid') !== false) {
                $fallback = '⚠️ A configuração da IA precisa ser atualizada. Entre em contato com o administrador.';
            }
            
            // Salvar para feedback/aprendizado
            if ($phone) {
                saveFeedback($pdo, $phone, $message, null);
            }
            
            echo json_encode([
                'success' => true,
                'response' => $fallback,
                'source' => 'fallback',
                'error' => $errorMsg,
                'httpCode' => $httpCode,
                'quotaExceeded' => $quotaExceeded
            ]);
            exit;
        }
        
        $response = $result['response'];
        
        // Salvar resposta da IA
        if ($phone) {
            saveConversation($pdo, $phone, 'assistant', $response);
            saveFeedback($pdo, $phone, $message, $response);
        }
        
        echo json_encode([
            'success' => true,
            'response' => $response,
            'source' => 'gemini'
        ]);
        break;
        
    case 'teach':
        // Admin ensina algo novo para a IA
        $pergunta = trim($input['pergunta'] ?? '');
        $resposta = trim($input['resposta'] ?? '');
        $categoria = trim($input['categoria'] ?? 'geral');
        $palavrasChave = trim($input['palavras_chave'] ?? '');
        
        if (empty($pergunta) || empty($resposta)) {
            echo json_encode(['success' => false, 'message' => 'Pergunta e resposta são obrigatórias']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO bot_ia_knowledge (pergunta, resposta, categoria, palavras_chave, prioridade) VALUES (?, ?, ?, ?, 50)");
        $stmt->execute([$pergunta, $resposta, $categoria, $palavrasChave]);
        
        echo json_encode(['success' => true, 'message' => 'Conhecimento adicionado!', 'id' => $pdo->lastInsertId()]);
        break;
        
    case 'correct':
        // Admin corrige uma resposta da IA
        $feedbackId = (int)($input['feedback_id'] ?? 0);
        $correcao = trim($input['correcao'] ?? '');
        $salvarConhecimento = (bool)($input['salvar_conhecimento'] ?? true);
        
        if (!$feedbackId || empty($correcao)) {
            echo json_encode(['success' => false, 'message' => 'ID e correção são obrigatórios']);
            exit;
        }
        
        // Atualizar feedback
        $stmt = $pdo->prepare("UPDATE bot_ia_feedback SET correcao = ?, aprovado = 1 WHERE id = ?");
        $stmt->execute([$correcao, $feedbackId]);
        
        // Se deve salvar como conhecimento
        if ($salvarConhecimento && getIASetting($pdo, 'ia_learn_from_corrections', '1') === '1') {
            $feedback = fetchOne($pdo, "SELECT pergunta_original FROM bot_ia_feedback WHERE id = ?", [$feedbackId]);
            if ($feedback) {
                $stmt = $pdo->prepare("INSERT INTO bot_ia_knowledge (pergunta, resposta, categoria, prioridade, criado_por) VALUES (?, ?, 'correcao', 60, 'admin')");
                $stmt->execute([$feedback['pergunta_original'], $correcao]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Correção salva!']);
        break;
        
    case 'clear_context':
        // Limpar contexto de conversa de um número
        if (empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Número não informado']);
            exit;
        }
        
        $pdo->exec("DELETE FROM bot_ia_conversations WHERE phone_number = " . $pdo->quote($phone));
        echo json_encode(['success' => true, 'message' => 'Contexto limpo']);
        break;
        
    case 'get_settings':
        $settings = fetchData($pdo, "SELECT setting_key, setting_value, description FROM bot_ia_settings ORDER BY setting_key");
        echo json_encode(['success' => true, 'data' => $settings]);
        break;
        
    case 'save_settings':
        $settings = $input['settings'] ?? [];
        foreach ($settings as $key => $value) {
            setIASetting($pdo, $key, $value);
        }
        echo json_encode(['success' => true, 'message' => 'Configurações salvas!']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
}
?>

