<?php
/**
 * Admin - Configurações da Página Inicial
 * Editar nome, badges, fotos de referência, etc.
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';

// Verificar autenticação
requireLogin();

// Processar salvamento
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'save_config') {
            // Suporte para salvar múltiplos badges de uma vez
            if (isset($_POST['badge_satisfacao']) || isset($_POST['badge_entregas']) || isset($_POST['badge_cidades'])) {
                // Salvar todos os badges
                $badges = [
                    'badge_satisfacao' => $_POST['badge_satisfacao'] ?? '',
                    'badge_entregas' => $_POST['badge_entregas'] ?? '',
                    'badge_cidades' => $_POST['badge_cidades'] ?? ''
                ];
                
                foreach ($badges as $chave => $valor) {
                    $exists = fetchOne($pdo, "SELECT id FROM homepage_config WHERE chave = ?", [$chave]);
                    
                    if ($exists) {
                        executeQuery($pdo, "UPDATE homepage_config SET valor = ? WHERE chave = ?", [$valor, $chave]);
                    } else {
                        executeQuery($pdo, "INSERT INTO homepage_config (chave, valor, tipo) VALUES (?, ?, 'text')", [$chave, $valor]);
                    }
                }
                
                $message = 'Badges salvos com sucesso!';
                $messageType = 'success';
            } else {
                // Salvar configuração individual
                $chave = sanitizeInput($_POST['chave'] ?? '');
                $valor = $_POST['valor'] ?? '';
                $tipo = sanitizeInput($_POST['tipo'] ?? 'text');
                
                if ($chave) {
                    // Verificar se já existe
                    $exists = fetchOne($pdo, "SELECT id FROM homepage_config WHERE chave = ?", [$chave]);
                    
                    if ($exists) {
                        executeQuery($pdo, "UPDATE homepage_config SET valor = ?, tipo = ? WHERE chave = ?", [$valor, $tipo, $chave]);
                    } else {
                        executeQuery($pdo, "INSERT INTO homepage_config (chave, valor, tipo) VALUES (?, ?, ?)", [$chave, $valor, $tipo]);
                    }
                    
                $message = 'Configuração salva com sucesso!';
                $messageType = 'success';
                }
            }
            
            // Redirecionar após salvar para evitar reenvio do formulário (POST-Redirect-GET)
            if ($messageType === 'success') {
                header("Location: admin_homepage.php?success=" . urlencode($message));
                exit;
            }
        } elseif ($_POST['action'] === 'upload_image' || $_POST['action'] === 'upload_video') {
            $referenciaNum = (int)($_POST['referencia_num'] ?? 0);
            $isVideo = $_POST['action'] === 'upload_video';
            
            if ($referenciaNum >= 1 && $referenciaNum <= 6) {
                $fileKey = $isVideo ? 'video' : 'imagem';
                
                if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$fileKey];
                    
                    if ($isVideo) {
                        $allowedTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
                        $maxSize = 50 * 1024 * 1024; // 50MB para vídeos
                        $uploadDir = 'assets/videos/';
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = 'whatsapp-' . $referenciaNum . '.' . $ext;
                        $tipoMedia = 'video';
                    } else {
                        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                        $maxSize = 5 * 1024 * 1024; // 5MB para imagens
                        $uploadDir = 'assets/images/';
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = 'whatsapp-' . $referenciaNum . '.' . $ext;
                        $tipoMedia = 'image';
                    }
                    
                    if (!in_array($file['type'], $allowedTypes)) {
                        throw new Exception($isVideo ? 'Tipo de arquivo não permitido. Use MP4, WebM ou OGG.' : 'Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP.');
                    }
                    
                    if ($file['size'] > $maxSize) {
                        throw new Exception($isVideo ? 'Arquivo muito grande (máx 50MB)' : 'Arquivo muito grande (máx 5MB)');
                    }
                    
                    // Criar diretório se não existir
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $filepath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Salvar no banco
                        $chave = 'referencia_imagem_' . $referenciaNum;
                        $chaveTipo = 'referencia_tipo_' . $referenciaNum;
                        
                        $exists = fetchOne($pdo, "SELECT id FROM homepage_config WHERE chave = ?", [$chave]);
                        
                        if ($exists) {
                            executeQuery($pdo, "UPDATE homepage_config SET valor = ? WHERE chave = ?", [$filepath, $chave]);
                        } else {
                            executeQuery($pdo, "INSERT INTO homepage_config (chave, valor, tipo) VALUES (?, ?, ?)", [$chave, $filepath, $tipoMedia]);
                        }
                        
                        // Salvar tipo (imagem ou vídeo)
                        $existsTipo = fetchOne($pdo, "SELECT id FROM homepage_config WHERE chave = ?", [$chaveTipo]);
                        if ($existsTipo) {
                            executeQuery($pdo, "UPDATE homepage_config SET valor = ? WHERE chave = ?", [$tipoMedia, $chaveTipo]);
                        } else {
                            executeQuery($pdo, "INSERT INTO homepage_config (chave, valor, tipo) VALUES (?, ?, 'text')", [$chaveTipo, $tipoMedia]);
                        }
                        
                        $message = ($isVideo ? 'Vídeo' : 'Imagem') . ' enviado com sucesso!';
                        $messageType = 'success';
                    } else {
                        throw new Exception('Erro ao salvar arquivo');
                    }
                } else {
                    throw new Exception('Erro no upload do arquivo');
                }
            }
        } elseif ($_POST['action'] === 'remove_referencia') {
            $referenciaNum = (int)($_POST['referencia_num'] ?? 0);
            if ($referenciaNum >= 1 && $referenciaNum <= 6) {
                // Buscar dados da referência
                $chaveImagem = 'referencia_imagem_' . $referenciaNum;
                $chaveTipo = 'referencia_tipo_' . $referenciaNum;
                $chaveNome = 'referencia_nome_' . $referenciaNum;
                $chaveDesc = 'referencia_desc_' . $referenciaNum;
                
                // Buscar caminho do arquivo
                $imagemConfig = fetchOne($pdo, "SELECT valor FROM homepage_config WHERE chave = ?", [$chaveImagem]);
                if ($imagemConfig && !empty($imagemConfig['valor'])) {
                    $filepath = $imagemConfig['valor'];
                    // Deletar arquivo se existir
                    if (file_exists($filepath)) {
                        @unlink($filepath);
                    }
                }
                
                // Deletar todas as configurações da referência
                $chavesParaRemover = [$chaveImagem, $chaveTipo, $chaveNome, $chaveDesc];
                foreach ($chavesParaRemover as $chave) {
                    executeQuery($pdo, "DELETE FROM homepage_config WHERE chave = ?", [$chave]);
                }
                
                $message = 'Referência removida com sucesso!';
                $messageType = 'success';
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Carregar todas as configurações
// Verificar se há mensagem de sucesso na URL (após redirecionamento)
if (isset($_GET['success'])) {
    $message = urldecode($_GET['success']);
    $messageType = 'success';
}

// Carregar todas as configurações
$configs = fetchData($pdo, "SELECT * FROM homepage_config ORDER BY chave");
$configArray = [];
foreach ($configs as $config) {
    $configArray[$config['chave']] = $config['valor'];
}

// Valores padrão se não existirem
$nomeEmpresa = $configArray['nome_empresa'] ?? 'Helmer Logistics';
$tituloHero = $configArray['titulo_hero'] ?? 'Acompanhe seus Recebimentos em Tempo Real';
$descricaoHero = $configArray['descricao_hero'] ?? 'Verifique o status dos seus recebimentos com tecnologia de ponta e acompanhamento em tempo real';
$badgeSatisfacao = $configArray['badge_satisfacao'] ?? '98.7% de Satisfação';
$badgeEntregas = $configArray['badge_entregas'] ?? '5.247 Entregas';
$badgeCidades = $configArray['badge_cidades'] ?? '247 Cidades';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações da Página Inicial | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #FF3333;
            --bg-dark: #0F0F0F;
            --bg-card: #1A1A1A;
            --border: rgba(255, 255, 255, 0.1);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-dark);
            color: #fff;
            min-height: 100vh;
        }
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .input-field {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
        }
        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 51, 51, 0.1);
        }
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #FF3333 0%, #FF6666 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 51, 51, 0.4);
        }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .alert-success {
            background: rgba(22, 163, 74, 0.2);
            border: 1px solid #16A34A;
            color: #4ade80;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #fca5a5;
        }
        .image-preview, .video-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            margin-top: 12px;
            border: 2px solid var(--border);
        }
        .video-preview {
            width: 100%;
            background: #000;
        }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold mb-2">
                    <i class="fas fa-home"></i> Configurações da Página Inicial
                </h1>
                <p class="text-gray-400">Edite o nome, badges, fotos de referência e outros elementos da página inicial</p>
            </div>
            <a href="admin.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <!-- Informações Básicas -->
        <div class="card">
            <h2 class="text-xl font-semibold mb-6">
                <i class="fas fa-info-circle"></i> Informações Básicas
            </h2>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save_config">
                
                <div>
                    <label class="block text-sm text-gray-400 mb-2">Nome da Empresa</label>
                    <input type="text" name="valor" value="<?= htmlspecialchars($nomeEmpresa) ?>" class="input-field" required>
                    <input type="hidden" name="chave" value="nome_empresa">
                    <input type="hidden" name="tipo" value="text">
                </div>
                
                <div>
                    <label class="block text-sm text-gray-400 mb-2">Título Principal (Hero)</label>
                    <input type="text" name="valor" value="<?= htmlspecialchars($tituloHero) ?>" class="input-field" required>
                    <input type="hidden" name="chave" value="titulo_hero">
                    <input type="hidden" name="tipo" value="text">
                </div>
                
                <div>
                    <label class="block text-sm text-gray-400 mb-2">Descrição (Hero)</label>
                    <textarea name="valor" class="input-field" rows="3" required><?= htmlspecialchars($descricaoHero) ?></textarea>
                    <input type="hidden" name="chave" value="descricao_hero">
                    <input type="hidden" name="tipo" value="text">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Informações Básicas
                </button>
            </form>
        </div>

        <!-- Badges -->
        <div class="card">
            <h2 class="text-xl font-semibold mb-6">
                <i class="fas fa-award"></i> Badges (Estatísticas)
            </h2>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save_config">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Badge Satisfação</label>
                        <input type="text" name="badge_satisfacao" value="<?= htmlspecialchars($badgeSatisfacao) ?>" class="input-field">
                    </div>
                    
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Badge Entregas</label>
                        <input type="text" name="badge_entregas" value="<?= htmlspecialchars($badgeEntregas) ?>" class="input-field">
                    </div>
                    
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Badge Cidades</label>
                        <input type="text" name="badge_cidades" value="<?= htmlspecialchars($badgeCidades) ?>" class="input-field">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Badges
                </button>
            </form>
        </div>

        <!-- Fotos e Vídeos de Referência -->
        <div class="card">
            <h2 class="text-xl font-semibold mb-6">
                <i class="fas fa-images"></i> Fotos e Vídeos de Referência (WhatsApp)
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php for ($i = 1; $i <= 6; $i++): 
                    $imgKey = 'referencia_imagem_' . $i;
                    $tipoKey = 'referencia_tipo_' . $i;
                    $nomeKey = 'referencia_nome_' . $i;
                    $descKey = 'referencia_desc_' . $i;
                    
                    $mediaPath = $configArray[$imgKey] ?? 'assets/images/whatsapp-' . $i . '.jpg';
                    $tipoMedia = $configArray[$tipoKey] ?? 'image';
                    $nome = $configArray[$nomeKey] ?? '';
                    $desc = $configArray[$descKey] ?? '';
                ?>
                <div class="border border-gray-700 rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-4">Referência <?= $i ?></h3>
                    
                    <!-- Upload de Imagem -->
                    <form method="POST" enctype="multipart/form-data" class="mb-3">
                        <input type="hidden" name="action" value="upload_image">
                        <input type="hidden" name="referencia_num" value="<?= $i ?>">
                        
                        <label class="block text-sm text-gray-400 mb-2">Imagem (JPG, PNG, GIF, WebP - máx 5MB)</label>
                        <input type="file" name="imagem" accept="image/*" class="input-field mb-2">
                        <button type="submit" class="btn btn-primary w-full">
                            <i class="fas fa-image"></i> Enviar Imagem
                        </button>
                    </form>
                    
                    <!-- Upload de Vídeo -->
                    <form method="POST" enctype="multipart/form-data" class="mb-4">
                        <input type="hidden" name="action" value="upload_video">
                        <input type="hidden" name="referencia_num" value="<?= $i ?>">
                        
                        <label class="block text-sm text-gray-400 mb-2">Vídeo (MP4, WebM, OGG - máx 50MB)</label>
                        <input type="file" name="video" accept="video/*" class="input-field mb-2">
                        <button type="submit" class="btn btn-primary w-full" style="background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);">
                            <i class="fas fa-video"></i> Enviar Vídeo
                        </button>
                    </form>
                    
                    <?php if (file_exists($mediaPath)): ?>
                        <?php if ($tipoMedia === 'video'): ?>
                        <video src="<?= htmlspecialchars($mediaPath) ?>?v=<?= time() ?>" controls class="image-preview" style="max-height: 300px;">
                            Seu navegador não suporta vídeo.
                        </video>
                        <?php else: ?>
                        <img src="<?= htmlspecialchars($mediaPath) ?>?v=<?= time() ?>" alt="Referência <?= $i ?>" class="image-preview">
                        <?php endif; ?>
                        <div class="mt-2 text-sm text-gray-400">
                            Tipo: <strong><?= $tipoMedia === 'video' ? 'Vídeo' : 'Imagem' ?></strong>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Nome e Descrição -->
                    <form method="POST" class="mt-4 space-y-3">
                        <input type="hidden" name="action" value="save_config">
                        
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Nome</label>
                            <input type="text" name="valor" value="<?= htmlspecialchars($nome) ?>" class="input-field">
                            <input type="hidden" name="chave" value="referencia_nome_<?= $i ?>">
                            <input type="hidden" name="tipo" value="text">
                        </div>
                        
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Descrição</label>
                            <textarea name="valor" class="input-field" rows="2"><?= htmlspecialchars($desc) ?></textarea>
                            <input type="hidden" name="chave" value="referencia_desc_<?= $i ?>">
                            <input type="hidden" name="tipo" value="text">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-full">
                            <i class="fas fa-save"></i> Salvar
                        </button>
                    </form>
                    
                    <!-- Remover Referência -->
                    <?php if (file_exists($mediaPath) || !empty($nome) || !empty($desc)): ?>
                    <form method="POST" class="mt-3" onsubmit="return confirm('Tem certeza que deseja remover esta referência? Esta ação não pode ser desfeita.');">
                        <input type="hidden" name="action" value="remove_referencia">
                        <input type="hidden" name="referencia_num" value="<?= $i ?>">
                        <button type="submit" class="btn w-full" style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5;">
                            <i class="fas fa-trash"></i> Remover Referência
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="text-center text-gray-400 mt-8">
            <p>Alterações são salvas automaticamente. Atualize a página inicial para ver as mudanças.</p>
        </div>
    </div>
</body>
</html>

