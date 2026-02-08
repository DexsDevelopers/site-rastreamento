<?php
/**
 * Sistema de Login Unificado - Helmer Logistics
 * Acesso aos painéis de Rastreamento e Bot WhatsApp
 */

session_start();

// Se já estiver logado, redirecionar para o dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Credenciais (em produção, usar banco de dados com senha hash)
    $valid_username = 'admin';
    $valid_password = '12345'; // Trocar por senha segura e usar hash

    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();

        header('Location: dashboard.php');
        exit;
    }
    else {
        $error = 'Usuário ou senha incorretos';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo - Loggi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>
</head>

<body>
    <div class="search-card" style="max-width: 450px; width: 100%; padding: 3rem;">
        <div class="logo-container" style="text-align: center; margin-bottom: 2rem;">
            <div class="logo" style="justify-content: center; font-size: 2rem;">
                <i class="fas fa-shipping-fast"></i> Loggi
            </div>
            <p class="subtitle" style="color: var(--text-muted);">Acesso Restrito</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error)?>
        </div>
        <?php
endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success)?>
        </div>
        <?php
endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> Usuário
                </label>
                <input type="text" id="username" name="username" placeholder="Digite seu usuário" required
                    autocomplete="username" class="form-input">
            </div>

            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Senha
                </label>
                <input type="password" id="password" name="password" placeholder="Digite sua senha" required
                    autocomplete="current-password" class="form-input">
            </div>

            <button type="submit" name="login" class="btn-primary" style="width: 100%;">
                <i class="fas fa-sign-in-alt"></i> Entrar no Painel
            </button>
        </form>

        <div class="features">
            <div class="feature-item">
                <i class="fas fa-truck"></i>
                <span>Painel de Rastreamento</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-robot"></i>
                <span>Painel do Bot WhatsApp</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-chart-line"></i>
                <span>Estatísticas e Relatórios</span>
            </div>
        </div>

        <div class="back-home" style="text-align: center; margin-top: 2rem;">
            <a href="index.php" style="color: var(--primary); font-weight: 600;">
                <i class="fas fa-arrow-left"></i> Voltar para o site oficial
            </a>
        </div>
    </div>
</body>

</html>