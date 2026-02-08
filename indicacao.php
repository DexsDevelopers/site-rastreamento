<?php
/**
 * Sistema de Indicação - Loggi
 * Interface para clientes indicarem outros clientes
 */

// Headers para evitar cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/referral_system.php';

$referralSystem = new ReferralSystem($pdo);
$message = '';
$messageType = '';

// Processar indicação
if (isset($_POST['indicar'])) {
    $codigoIndicador = sanitizeInput($_POST['codigo_indicador']);
    $codigoIndicado = sanitizeInput($_POST['codigo_indicado']);
    $dadosIndicado = [
        'nome' => sanitizeInput($_POST['nome_indicado']),
        'telefone' => sanitizeInput($_POST['telefone_indicado']),
        'cidade' => sanitizeInput($_POST['cidade_indicado'])
    ];

    $result = $referralSystem->registrarIndicacao($codigoIndicador, $codigoIndicado, $dadosIndicado);

    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    }
    else {
        $message = $result['message'];
        $messageType = 'error';
    }
}

// Processar compra com indicação
if (isset($_POST['comprar_com_indicacao'])) {
    $codigoCliente = sanitizeInput($_POST['codigo_cliente']);
    $codigoIndicador = sanitizeInput($_POST['codigo_indicador']);
    $valor = floatval($_POST['valor']);
    $dadosRastreio = [
        'cidade' => sanitizeInput($_POST['cidade'])
    ];

    $result = $referralSystem->processarCompraComIndicacao($codigoCliente, $codigoIndicador, $valor, $dadosRastreio);

    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    }
    else {
        $message = $result['message'];
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indique a Loggi - Ganhe Benefícios</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>
</head>

<body>
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <i class="fas fa-shipping-fast"></i> Loggi
            </a>
            <nav class="nav-links">
                <a href="index.php">Início</a>
                <a href="sobre.php">Sobre</a>
            </nav>
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
            <nav class="mobile-menu" id="mobileMenu">
                <a href="index.php">Início</a>
                <a href="sobre.php">Sobre</a>
            </nav>
        </div>
    </header>

    <div class="container" style="padding-top: 8rem; padding-bottom: 4rem;">
        <div class="hero-content" style="text-align: center; margin-bottom: 3rem;">
            <h1>Indique a Loggi</h1>
            <p style="color: var(--text-muted); font-size: 1.2rem;">Ajude amigos a enviarem com a melhor logística do
                Brasil.</p>
        </div>

        <?php if ($message): ?>
        <div class="message <?= $messageType?>">
            <?= $message?>
        </div>
        <?php
endif; ?>

        <div class="features" style="margin-bottom: 2rem;">
            <h2 class="section-title">Vantagens de Indicar</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <span class="feature-icon"><i class="fas fa-rocket"></i></span>
                    <h3>Entrega Prioritária</h3>
                    <p>Seu pedido será entregue em apenas 2 dias</p>
                </div>
                <div class="feature-card">
                    <span class="feature-icon"><i class="fas fa-clock"></i></span>
                    <h3>Processamento Rápido</h3>
                    <p>Seu pedido tem prioridade em todo o processo</p>
                </div>
                <div class="feature-card">
                    <span class="feature-icon"><i class="fas fa-star"></i></span>
                    <h3>Status VIP</h3>
                    <p>Seu rastreamento terá status especial</p>
                </div>
                <div class="feature-card">
                    <span class="feature-icon"><i class="fas fa-heart"></i></span>
                    <h3>Indique e Ganhe</h3>
                    <p>Ajude seus amigos e ganhe benefícios</p>
                </div>
            </div>
        </div>

        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
            <!-- Formulário de Indicação -->
            <div class="search-card">
                <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-user-plus"></i> Indicar Amigo</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="codigo_indicador">Seu Código de Cliente</label>
                        <input type="text" name="codigo_indicador" id="codigo_indicador" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="codigo_indicado">Código do Amigo</label>
                        <input type="text" name="codigo_indicado" id="codigo_indicado" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="nome_indicado">Nome do Amigo</label>
                        <input type="text" name="nome_indicado" id="nome_indicado" class="form-input" required>
                    </div>


                    <div class="form-group">
                        <label for="telefone_indicado">Telefone do Amigo</label>
                        <input type="tel" name="telefone_indicado" id="telefone_indicado" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="cidade_indicado">Cidade do Amigo</label>
                        <input type="text" name="cidade_indicado" id="cidade_indicado" class="form-input" required>
                    </div>

                    <button type="submit" name="indicar" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Indicar Amigo
                    </button>
                </form>
            </div>

            <!-- Formulário de Compra com Indicação -->
            <div class="search-card">
                <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-shopping-cart"></i> Comprar com Indicação</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="codigo_cliente">Seu Código de Cliente</label>
                        <input type="text" name="codigo_cliente" id="codigo_cliente" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="codigo_indicador">Código de Quem Te Indicou</label>
                        <input type="text" name="codigo_indicador" id="codigo_indicador" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="valor">Valor da Compra</label>
                        <input type="number" name="valor" id="valor" step="0.01" min="0" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="cidade">Sua Cidade</label>
                        <input type="text" name="cidade" id="cidade" class="form-input" required>
                    </div>

                    <button type="submit" name="comprar_com_indicacao" class="btn-primary"
                        style="background: linear-gradient(135deg, #16a34a, #059669);">
                        <i class="fas fa-bolt"></i> Comprar com Prioridade
                    </button>
                </form>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="features-grid">
            <?php
try {
    $stats = $referralSystem->getEstatisticasIndicacoes();
?>
            <div class="feature-card" style="text-align: center;">
                <div class="feature-icon" style="margin: 0 auto 1rem;"><i class="fas fa-users"></i></div>
                <h3 style="font-size: 2rem;">
                    <?= $stats['total_indicacoes']?>
                </h3>
                <p>Total de Indicações</p>
            </div>
            <div class="feature-card" style="text-align: center;">
                <div class="feature-icon" style="margin: 0 auto 1rem;"><i class="fas fa-check"></i></div>
                <h3 style="font-size: 2rem;">
                    <?= $stats['confirmadas']?>
                </h3>
                <p>Indicações Confirmadas</p>
            </div>
            <div class="feature-card" style="text-align: center;">
                <div class="feature-icon" style="margin: 0 auto 1rem;"><i class="fas fa-truck"></i></div>
                <h3 style="font-size: 2rem;">
                    <?= $stats['entregues']?>
                </h3>
                <p>Entregues com Prioridade</p>
            </div>
            <div class="feature-card" style="text-align: center;">
                <div class="feature-icon" style="margin: 0 auto 1rem;"><i class="fas fa-stopwatch"></i></div>
                <h3 style="font-size: 2rem;">
                    <?= round($stats['tempo_medio_entrega'], 1)?> dias
                </h3>
                <p>Tempo Médio de Entrega</p>
            </div>
            <?php
}
catch (Exception $e) { ?>
            <div class="feature-card" style="text-align: center;">
                <h3>0</h3>
                <p>Carregando estatísticas...</p>
            </div>
            <?php
}?>
        </div>
    </div>

    <footer class="footer"
        style="text-align: center; padding: 4rem 0; background: #F8F9FA; border-top: 1px solid #E2E8F0;">
        <p>&copy; 2026 Loggi Technology. Todos os direitos reservados.</p>
    </footer>

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('active');
        }

        // Validação de formulários
        document.addEventListener('DOMContentLoaded', function () {
            const forms = document.querySelectorAll('form');

            forms.forEach(form => {
                form.addEventListener('submit', function (e) {
                    const inputs = form.querySelectorAll('input[required]');
                    let valid = true;

                    inputs.forEach(input => {
                        if (!input.value.trim()) {
                            valid = false;
                            input.style.borderColor = '#ef4444';
                        } else {
                            input.style.borderColor = 'transparent';
                        }
                    });

                    if (!valid) {
                        e.preventDefault();
                        alert('Por favor, preencha todos os campos obrigatórios.');
                    }
                });
            });
        });
    </script>
</body>

</html>