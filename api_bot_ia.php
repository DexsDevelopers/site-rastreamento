<?php
/**
 * API de IA Própria (Local Brain)
 * Sistema de Inteligência Autônomo sem dependência de APIs externas.
 * Baseado em similaridade de texto, palavras-chave e aprendizado supervisionado.
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

// =================================================================================
// 🧠 CÉREBRO LOCAL: FUNÇÕES DE INTELIGÊNCIA
// =================================================================================

/**
 * Remove palavras irrelevantes (stopwords) para focar no significado
 */
function cleanText($text) {
    $text = mb_strtolower(trim($text));
    // Remover pontuação
    $text = preg_replace('/[?!.,;:]/', '', $text);
    // Remover acentos
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    
    // Lista de palavras irrelevantes em português
    $stopwords = [
        'o','a','os','as','um','uma','uns','umas','de','do','da','em','no','na',
        'que','e','é','eh','para','pra','com','por','se','eu','vc','voce','você',
        'me','mim','te','tua','seu','sua','nos','gostaria','queria','saber','pode',
        'por favor','favor','dizer','falar','perguntar'
    ];
    
    $words = explode(' ', $text);
    $cleanWords = array_diff($words, $stopwords);
    return implode(' ', $cleanWords);
}

/**
 * Calcula a similaridade entre duas frases (0 a 100)
 * Usa Levenshtein combinada com Jaccard (conjunto de palavras)
 */
function calculateSimilarity($input, $target) {
    $cleanInput = cleanText($input);
    $cleanTarget = cleanText($target);
    
    if (empty($cleanInput) || empty($cleanTarget)) return 0;
    
    // 1. Similaridade de caracteres (Levenshtein)
    $lev = levenshtein($cleanInput, $cleanTarget);
    $maxLength = max(strlen($cleanInput), strlen($cleanTarget));
    $charScore = (1 - ($lev / $maxLength)) * 100;
    
    // 2. Similaridade de palavras (Jaccard)
    $inputWords = explode(' ', $cleanInput);
    $targetWords = explode(' ', $cleanTarget);
    $intersection = array_intersect($inputWords, $targetWords);
    $union = array_unique(array_merge($inputWords, $targetWords));
    
    if (count($union) === 0) $wordScore = 0;
    else $wordScore = (count($intersection) / count($union)) * 100;
    
    // Peso maior para palavras iguais (60% palavras, 40% escrita)
    return ($wordScore * 0.6) + ($charScore * 0.4);
}

/**
 * Busca a melhor resposta na base de conhecimento
 */
function findBestMatch($pdo, $query) {
    $queryFormatted = cleanText($query);
    
    // 1. Busca textual no banco (primeiro filtro)
    // Busca por palavras-chave ou texto parcial
    $stmt = $pdo->prepare("
        SELECT id, pergunta, resposta, palavras_chave, categoria, prioridade 
        FROM bot_ia_knowledge 
        WHERE ativo = 1
    ");
    $stmt->execute();
    $allKnowledge = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $bestMatch = null;
    $highestScore = 0;
    
    foreach ($allKnowledge as $item) {
        // Calcular Score da Pergunta Principal
        $score = calculateSimilarity($query, $item['pergunta']);
        
        // Calcular Score das Palavras-Chave (Bônus)
        if (!empty($item['palavras_chave'])) {
            $keywords = explode(',', $item['palavras_chave']);
            foreach ($keywords as $kw) {
                $kw = trim($kw);
                if (empty($kw)) continue;
                
                // Se a palavra-chave estiver contida na query
                if (mb_stripos($query, $kw) !== false) {
                    $score += 30; // Bônus alto por palavra-chave exata
                }
                
                // Similaridade da palavra-chave
                $kwProp = calculateSimilarity($query, $kw);
                if ($kwProp > 80) {
                     $score += 20; // Bônus por palavra-chave parecida
                }
            }
        }
        
        // Ajustar score pela prioridade manual (0 a 100 viram 0 a 10 pontos extras)
        $score += ($item['prioridade'] / 10);
        
        if ($score > $highestScore) {
            $highestScore = $score;
            $bestMatch = $item;
        }
    }
    
    return ['match' => $bestMatch, 'score' => $highestScore];
}

/**
 * Salva pergunta não respondida para aprendizado futuro
 */
function logUnansweredQuestion($pdo, $query, $phone) {
    // Verificar se já existe log recente dessa pergunta
    $stmt = $pdo->prepare("SELECT id FROM bot_ia_feedback WHERE pergunta_original = ? AND processado = 0 LIMIT 1");
    $stmt->execute([$query]);
    if ($stmt->fetch()) return; // Já está na fila
    
    $stmt = $pdo->prepare("INSERT INTO bot_ia_feedback (phone_number, pergunta_original, resposta_ia, processado) VALUES (?, ?, 'SEM_RESPOSTA', 0)");
    $stmt->execute([$phone, $query]);
}

// =================================================================================
// 🧠 SISTEMA DE FATOS (MEMÓRIA DE LONGO PRAZO)
// =================================================================================

function saveUserFact($pdo, $phone, $key, $value) {
    $key = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', $key)));
    if (empty($key) || empty($value)) return;
    $stmt = $pdo->prepare("INSERT INTO bot_ia_user_facts (phone_number, fact_key, fact_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE fact_value = ?, updated_at = NOW()");
    $stmt->execute([$phone, $key, $value, $value]);
}

function getUserFact($pdo, $phone, $key) {
    $stmt = $pdo->prepare("SELECT fact_value FROM bot_ia_user_facts WHERE phone_number = ? AND fact_key = ?");
    $stmt->execute([$phone, $key]);
    return $stmt->fetchColumn();
}

/**
 * Extrai fatos simples baseados em padrões de regex (Regras Locais)
 * Substitui o Gemini na extração de fatos
 */
function extractFactsRuleBased($pdo, $phone, $message) {
    $msgLower = mb_strtolower($message);
    
    // Regra 1: Nome (Meu nome é X, Me chamo X, Sou o X)
    if (preg_match('/(meu nome (?:é|eh)|me chamo|sou (?:o|a)) ([a-zà-ú]+)/u', $msgLower, $matches)) {
        $nome = ucfirst($matches[2]);
        // Ignorar nomes comuns de erro
        if (strlen($nome) > 2 && !in_array(strtolower($nome), ['um', 'uma', 'seu', 'cliente'])) {
            saveUserFact($pdo, $phone, 'nome_usuario', $nome);
            return true;
        }
    }
    
    // Regra 2: Localização (Moro em X, Sou de X)
    if (preg_match('/(moro em|sou de|estou em) ([a-zà-ú\s]+)/u', $msgLower, $matches)) {
        $cidade = ucwords(trim($matches[2]));
        if (strlen($cidade) > 3) {
            saveUserFact($pdo, $phone, 'cidade', $cidade);
            return true;
        }
    }
    
    // Regra 3: Captura de Email
    if (preg_match('/[\w\.-]+@[\w\.-]+\.\w+/', $message, $matches)) {
        saveUserFact($pdo, $phone, 'email', $matches[0]);
        return true;
    }
    
    return false;
}

// =================================================================================
// 🔄 PROCESSAMENTO DA REQUISIÇÃO
// =================================================================================

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
        
        // 1. Verificar Contexto Especial (Data/Hora)
        if (preg_match('/\b(que dia|que horas|data de hoje|hora certa)\b/i', $message)) {
            date_default_timezone_set('America/Sao_Paulo');
            $response = "📅 Hoje é " . date('d/m/Y') . " e são " . date('H:i') . ".";
            echo json_encode(['success' => true, 'response' => $response, 'source' => 'system_time']);
            exit;
        }
        
        // 2. Extrair fatos da mensagem (Aprendizado Passivo)
        extractFactsRuleBased($pdo, $phone, $message);
        
        // 3. Buscar no Cérebro Local (RAG - Contexto)
        $result = findBestMatch($pdo, $message);
        $localContext = $result['match']; // Pode ser null
        $localScore = $result['score'];

        // 4. Carregar Histórico de Conversa (Contexto)
        $stmt = $pdo->prepare("SELECT role, message FROM bot_ia_conversations WHERE phone_number = ? ORDER BY id DESC LIMIT 6");
        $stmt->execute([$phone]);
        $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        // 5. Carregar Fatos do Usuário
        $stmt = $pdo->prepare("SELECT fact_key, fact_value FROM bot_ia_user_facts WHERE phone_number = ?");
        $stmt->execute([$phone]);
        $userFacts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $userName = $userFacts['nome_usuario'] ?? 'Cliente';
        $userCity = $userFacts['cidade'] ?? 'Não informada';

        // 6. ANÁLISE AVANÇADA LOCAL (Sem API Externa)
        // O "Cérebro Local" analisa histórico e contexto para decidir a melhor resposta

        // A. Verificar Filtro de Silêncio (Anti-Spam / Irrelevância)
        // Se a mensagem for muito curta ou sem sentido, ignorar
        $ignoredPatterns = ['/^(ok|tá|ta|blz|beleza|👍|👋|kkk|rsrs)$/i', '/^(\?|\.)$/'];
        foreach ($ignoredPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                // Silêncio estratégico: não responde a interações vazias
                echo json_encode(['success' => true, 'response' => null, 'source' => 'local_filter_silence']);
                exit;
            }
        }

        // B. Análise de Sentimento (Básica)
        $isAngry = preg_match('/(porra|caralho|merda|lixo|inútil|idiota|burro|atrasado)/i', $message);
        $isHappy = preg_match('/(obrigado|valeu|top|ótimo|perfeito|excelente|bom trabalho)/i', $message);

        // C. Tentar entender o contexto da conversa anterior
        $lastTopic = 'geral';
        if (!empty($history)) {
            $lastUserMsg = $history[0]['message'] ?? '';
            // Tentar inferir sobre o que estavam falando
            if (stripos($lastUserMsg, 'rastreio') !== false || stripos($lastUserMsg, 'pedido') !== false) {
                $lastTopic = 'rastreamento';
            } elseif (stripos($lastUserMsg, 'preço') !== false || stripos($lastUserMsg, 'valor') !== false) {
                $lastTopic = 'financeiro';
            }
        }

        // D. Melhorar o Score baseado no contexto
        // Se a pergunta atual for curta (ex: "e o preço?"), tentar combinar com o tópico anterior
        if (str_word_count($message) < 4 && $lastTopic !== 'geral') {
             $contextualQuery = $lastTopic . " " . $message;
             $contextResult = findBestMatch($pdo, $contextualQuery);
             
             // Se a busca contextual der um resultado melhor, usa ela
             if ($contextResult['score'] > $localScore + 10) {
                 $localContext = $contextResult['match'];
                 $localScore = $contextResult['score'];
                 error_log("[IA_LOCAL] Contexto aplicado: '{$lastTopic}' melhorou o entendimento.");
             }
        }

        // E. Resposta Específica para Xingamentos (Modo Profissional)
        if ($isAngry) {
            echo json_encode([
                'success' => true, 
                'response' => "Sinto muito que você esteja insatisfeito. 😔\nMeu objetivo é ajudar. Por favor, me diga o número do seu pedido para que eu possa verificar o que houve.", 
                'source' => 'local_sentiment_angry',
                'confidence' => 100
            ]);
            exit;
        }

        // === MODO LOCAL (FALLBACK) ===
        // Continua com a lógica antiga se o Gemini falhar ou não estiver configurado
        
        $match = $localContext;
        $score = $localScore;
        
        // SCORE MÍNIMO DE CONFIANÇA (Ajustável)
        $MIN_CONFIDENCE = 45; // Se for menor que isso, ele assume que não sabe
        
        if ($match && $score >= $MIN_CONFIDENCE) {
            $response = $match['resposta'];
            
            // Personalização com fatos (Injeção de Variáveis)
            // Se tiver {nome} na resposta e soubermos o nome, substitui
            if (strpos($response, '{nome}') !== false) {
                $nomeUser = getUserFact($pdo, $phone, 'nome_usuario') ?? 'amigo(a)';
                $response = str_replace('{nome}', $nomeUser, $response);
            }
            
            // Incrementar contador de uso
            $pdo->exec("UPDATE bot_ia_knowledge SET uso_count = uso_count + 1 WHERE id = {$match['id']}");
            
            // Salvar histórico
            if ($phone) {
                // Salvar conversa (simples)
                $stmt = $pdo->prepare("INSERT INTO bot_ia_conversations (phone_number, role, message) VALUES (?, 'user', ?), (?, 'assistant', ?)");
                $stmt->execute([$phone, $message, $phone, $response]);
            }

            error_log("[IA_LOCAL] Match encontrado: '{$match['pergunta']}' (Score: {$score})");
            
            echo json_encode([
                'success' => true, 
                'response' => $response, 
                'source' => 'local_brain',
                'confidence' => $score,
                'category' => $match['categoria']
            ]);
            
        } else {
            // NÃO SABE A RESPOSTA
            error_log("[IA_LOCAL] Sem resposta para: '{$message}' (Melhor score: {$score})");
            
            // Registrar para aprendizado
            logUnansweredQuestion($pdo, $message, $phone);
            
            // Respostas de fallback variadas
            $fallbacks = [
                "Desculpe, ainda estou aprendendo e não sei responder isso. 🧠\nVou anotar aqui para meu criador me ensinar!",
                "Essa eu não sei! 😅\nMas já registrei sua dúvida para aprender em breve.",
                "Humm... não encontrei essa informação na minha base. Pode tentar perguntar de outra forma?",
                "Minha inteligência ainda está em treinamento para esse assunto. Tente usar o menu principal!"
            ];
            $fallbackResponse = $fallbacks[array_rand($fallbacks)];
            
            echo json_encode([
                'success' => true,
                'response' => $fallbackResponse,
                'source' => 'fallback_unknown',
                'confidence' => $score
            ]);
        }
        break;

    // ... (Manter casos de teach, correct, etc. para permitir que o admin ensine)
    case 'teach':
        // Admin ensina algo novo diretamente
        $pergunta = trim($input['pergunta'] ?? '');
        $resposta = trim($input['resposta'] ?? '');
        $categoria = trim($input['categoria'] ?? 'geral');
        $palavrasChave = trim($input['palavras_chave'] ?? '');
        
        if (empty($pergunta) || empty($resposta)) {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos']); exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO bot_ia_knowledge (pergunta, resposta, categoria, palavras_chave, prioridade) VALUES (?, ?, ?, ?, 50)");
        $stmt->execute([$pergunta, $resposta, $categoria, $palavrasChave]);
        echo json_encode(['success' => true, 'message' => 'Cérebro atualizado!']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ação desconhecida']);
}
?>
