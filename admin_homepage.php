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
        if ($_POST['action'] === 'save_config_multiple') {
            // Salvar múltiplas configurações de uma vez
            $fieldsToSave = [
                'how_it_works_title', 'feature1_title', 'feature1_description',
                'feature2_title', 'feature2_description', 'feature3_title', 'feature3_description',
                'social_proof1_title', 'social_proof1_link_text',
                'social_proof2_title', 'social_proof2_link_text',
                'social_proof3_title', 'social_proof3_link_text'
            ];

            foreach ($fieldsToSave as $chave) {
                if (isset($_POST[$chave])) {
                    $valor = $_POST[$chave];
                    $exists = fetchOne($pdo, "SELECT id FROM homepage_config WHERE chave = ?", [$chave]);

                    if ($exists) {
                        executeQuery($pdo, "UPDATE homepage_config SET valor = ? WHERE chave = ?", [$valor, $chave]);
                    }
                    else {
                        executeQuery($pdo, "INSERT INTO homepage_config (chave, valor, tipo) VALUES (?, ?, 'text')", [$chave, $valor]);
                    }
                }
            }

            $message = 'Configurações salvas com sucesso!';
            $messageType = 'success';

            // Redirecionar após salvar
            if ($messageType === 'success') {
                header("Location: admin_homepage.php?success=" . urlencode($message));
                exit;
            }
        }
        elseif ($_POST['action'] === 'save_config') {
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
                    }
                    else {
                        executeQuery($pdo, "INSERT INTO homepage_config (chave, valor, tipo) VALUES (?, ?, 'text')", [$chave, $valor]);
                    }
                }

                $message = 'Badges salvos com sucesso!';
                $messageType = 'success';
            }
            else {
                // Salvar configuração individual
                $chave = sanitizeInput($_POST['chave'] ?? '');
                $valor = $_POST['valor'] ?? '';
                $tipo = sanitizeInput($_POST['tipo'] ?? 'text');

                if ($chave) {
                    // Verificar se já existe
                    $exists = fetchOne($pdo, "SELECT id FROM homepage_config WHERE chave = ?", [$chave]);

                    if ($exists) {
                        executeQuery($pdo, "UPDATE homepage_config SET valor = ?, tipo = ? WHERE chave = ?", [$valor, $tipo, $chave]);
                    }
                    else {
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
        }

        // Redirecionar após qualquer ação bem-sucedida para evitar reenvio do formulário
        if (isset($messageType) && $messageType === 'success') {
            header("Location: admin_homepage.php?success=" . urlencode($message ?? 'Ação realizada com sucesso!'));
            exit;
        }
    }
    catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Verificar se há mensagem de sucesso na URL (após redirecionamento)
if (!isset($message) && isset($_GET['success'])) {
    $message = urldecode($_GET['success']);
    $messageType = 'success';
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
$nomeEmpresa = $configArray['nome_empresa'] ?? 'Loggi';
$tituloHero = $configArray['titulo_hero'] ?? 'Acompanhe seus Recebimentos em Tempo Real';
$descricaoHero = $configArray['descricao_hero'] ?? 'Acompanhe seu pedido em tempo real com a Loggi. Frete grátis para todo o Brasil.';
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
            --primary: #0055FF;
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
            box-shadow: 0 0 0 3px rgba(0, 85, 255, 0.1);
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
            background: linear-gradient(135deg, #0055FF 0%, #180F33 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 85, 255, 0.4);
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

        .image-preview,
        .video-preview {
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
                <p class="text-gray-400">Edite o nome, badges, fotos de referência e outros elementos da página inicial
                </p>
            </div>
            <a href="admin.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType?>">
            <?= htmlspecialchars($message)?>
        </div>
        <?php
endif; ?>

        <!-- Informações Básicas -->
        <div class="card">
            <h2 class="text-xl font-semibold mb-6">
                <i class="fas fa-info-circle"></i> Informações Básicas
            </h2>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save_config">

                <div>
                    <label class="block text-sm text-gray-400 mb-2">Nome da Empresa</label>
                    <input type="text" name="valor" value="<?= htmlspecialchars($nomeEmpresa)?>" class="input-field"
                        required>
                    <input type="hidden" name="chave" value="nome_empresa">
                    <input type="hidden" name="tipo" value="text">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-2">Título Principal (Hero)</label>
                    <input type="text" name="valor" value="<?= htmlspecialchars($tituloHero)?>" class="input-field"
                        required>
                    <input type="hidden" name="chave" value="titulo_hero">
                    <input type="hidden" name="tipo" value="text">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-2">Descrição (Hero)</label>
                    <textarea name="valor" class="input-field" rows="3"
                        required><?= htmlspecialchars($descricaoHero)?></textarea>
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
                        <input type="text" name="badge_satisfacao" value="<?= htmlspecialchars($badgeSatisfacao)?>"
                            class="input-field">
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Badge Entregas</label>
                        <input type="text" name="badge_entregas" value="<?= htmlspecialchars($badgeEntregas)?>"
                            class="input-field">
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Badge Cidades</label>
                        <input type="text" name="badge_cidades" value="<?= htmlspecialchars($badgeCidades)?>"
                            class="input-field">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Badges
                </button>
            </form>
        </div>

        <!-- Seção "Como funciona" -->
        <div class="card">
            <h2 class="text-xl font-semibold mb-6">
                <i class="fas fa-cogs"></i> Seção "Como funciona"
            </h2>

            <form method="POST" class="space-y-4" id="formComoFunciona">
                <input type="hidden" name="action" value="save_config_multiple">

                <div>
                    <label class="block text-sm text-gray-400 mb-2">Título da Seção</label>
                    <input type="text" name="how_it_works_title"
                        value="<?= htmlspecialchars($configArray['how_it_works_title'] ?? 'Como funciona')?>"
                        class="input-field">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Feature 1 - Título</label>
                        <input type="text" name="feature1_title"
                            value="<?= htmlspecialchars($configArray['feature1_title'] ?? '1) Rastreie')?>"
                            class="input-field mb-2">
                        <textarea name="feature1_description" class="input-field" rows="2"
                            placeholder="Descrição"><?= htmlspecialchars($configArray['feature1_description'] ?? 'Digite o código e a cidade para validar e ver o status do envio.')?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Feature 2 - Título</label>
                        <input type="text" name="feature2_title"
                            value="<?= htmlspecialchars($configArray['feature2_title'] ?? '2) Acompanhe')?>"
                            class="input-field mb-2">
                        <textarea name="feature2_description" class="input-field" rows="2"
                            placeholder="Descrição"><?= htmlspecialchars($configArray['feature2_description'] ?? 'Veja a linha do tempo com todas as etapas do seu recebimento.')?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Feature 3 - Título</label>
                        <input type="text" name="feature3_title"
                            value="<?= htmlspecialchars($configArray['feature3_title'] ?? '3) Entrega Expressa')?>"
                            class="input-field mb-2">
                        <textarea name="feature3_description" class="input-field" rows="2"
                            placeholder="Descrição"><?= htmlspecialchars($configArray['feature3_description'] ?? 'Antecipe para 3 dias com pagamento rápido por PIX, caso precise de urgência.')?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar "Como funciona"
                </button>
            </form>
        </div>

        <!-- Seção "Prova Social" -->
        <div class="card">
            <h2 class="text-xl font-semibold mb-6">
                <i class="fas fa-star"></i> Seção "Prova Social"
            </h2>

            <form method="POST" class="space-y-4" id="formProvaSocial">
                <input type="hidden" name="action" value="save_config_multiple">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Prova Social 1 - Título</label>
                        <input type="text" name="social_proof1_title"
                            value="<?= htmlspecialchars($configArray['social_proof1_title'] ?? 'Satisfação 98,7%')?>"
                            class="input-field mb-2">
                        <input type="text" name="social_proof1_link_text"
                            value="<?= htmlspecialchars($configArray['social_proof1_link_text'] ?? 'Ver metodologia')?>"
                            class="input-field" placeholder="Texto do link">
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Prova Social 2 - Título</label>
                        <input type="text" name="social_proof2_title"
                            value="<?= htmlspecialchars($configArray['social_proof2_title'] ?? '+5.247 Entregas')?>"
                            class="input-field mb-2">
                        <input type="text" name="social_proof2_link_text"
                            value="<?= htmlspecialchars($configArray['social_proof2_link_text'] ?? 'Ver histórico')?>"
                            class="input-field" placeholder="Texto do link">
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Prova Social 3 - Título</label>
                        <input type="text" name="social_proof3_title"
                            value="<?= htmlspecialchars($configArray['social_proof3_title'] ?? 'Confiabilidade')?>"
                            class="input-field mb-2">
                        <input type="text" name="social_proof3_link_text"
                            value="<?= htmlspecialchars($configArray['social_proof3_link_text'] ?? 'Política e garantias')?>"
                            class="input-field" placeholder="Texto do link">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar "Prova Social"
                </button>
            </form>
        </div>


        <div class="text-center text-gray-400 mt-8">
            <p>Alterações são salvas automaticamente. Atualize a página inicial para ver as mudanças.</p>
        </div>
    </div>
</body>

</html>