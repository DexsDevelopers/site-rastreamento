<?php
/**
 * Sistema de Login Unificado - Transloggi
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
    <title>Login Administrativo - Transloggi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            position: relative;
            overflow: hidden;
        }

        /* Animated background */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(circle at 20% 80%, rgba(0, 85, 255, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(0, 85, 255, 0.08) 0%, transparent 40%);
            animation: bgFloat 20s ease-in-out infinite;
        }

        @keyframes bgFloat {

            0%,
            100% {
                transform: translate(0, 0) rotate(0deg);
            }

            33% {
                transform: translate(30px, -30px) rotate(5deg);
            }

            66% {
                transform: translate(-20px, 20px) rotate(-5deg);
            }
        }

        /* Floating particles */
        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(0, 85, 255, 0.4);
            border-radius: 50%;
            animation: float 15s infinite;
        }

        .particle:nth-child(1) {
            left: 10%;
            animation-delay: 0s;
        }

        .particle:nth-child(2) {
            left: 20%;
            animation-delay: 2s;
        }

        .particle:nth-child(3) {
            left: 30%;
            animation-delay: 4s;
        }

        .particle:nth-child(4) {
            left: 40%;
            animation-delay: 1s;
        }

        .particle:nth-child(5) {
            left: 50%;
            animation-delay: 3s;
        }

        .particle:nth-child(6) {
            left: 60%;
            animation-delay: 5s;
        }

        .particle:nth-child(7) {
            left: 70%;
            animation-delay: 2.5s;
        }

        .particle:nth-child(8) {
            left: 80%;
            animation-delay: 1.5s;
        }

        .particle:nth-child(9) {
            left: 90%;
            animation-delay: 3.5s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(100vh) scale(0);
                opacity: 0;
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                transform: translateY(-100vh) scale(1);
                opacity: 0;
            }
        }

        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 480px;
            padding: 2rem;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 32px;
            padding: 3.5rem;
            box-shadow:
                0 25px 50px -12px rgba(0, 0, 0, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.05) inset;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0055FF, #3b82f6);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
            box-shadow: 0 20px 40px -10px rgba(0, 85, 255, 0.5);
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .logo-text {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            letter-spacing: -0.03em;
            margin-bottom: 0.5rem;
        }

        .logo-subtitle {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.95rem;
            font-weight: 500;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            20%,
            60% {
                transform: translateX(-5px);
            }

            40%,
            80% {
                transform: translateX(5px);
            }
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.4);
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1rem 1.25rem 1rem 3rem;
            font-size: 1rem;
            color: white;
            font-family: inherit;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        .form-input:focus {
            border-color: #0055FF;
            background: rgba(0, 85, 255, 0.05);
            box-shadow: 0 0 0 4px rgba(0, 85, 255, 0.15);
        }

        .input-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.4);
            font-size: 1rem;
            transition: color 0.3s;
        }

        .form-input:focus+.input-icon,
        .input-wrapper:focus-within .input-icon {
            color: #0055FF;
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #0055FF, #3b82f6);
            color: white;
            border: none;
            border-radius: 16px;
            padding: 1.1rem 2rem;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px -10px rgba(0, 85, 255, 0.5);
        }

        .btn-login:active {
            transform: translateY(-1px);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 2rem 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .divider span {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .feature-item {
            text-align: center;
            padding: 1.25rem 0.75rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            background: rgba(0, 85, 255, 0.1);
            border-color: rgba(0, 85, 255, 0.2);
            transform: translateY(-3px);
        }

        .feature-item i {
            font-size: 1.5rem;
            color: #0055FF;
            margin-bottom: 0.75rem;
            display: block;
        }

        .feature-item span {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 600;
            display: block;
            line-height: 1.3;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 2rem;
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: #0055FF;
        }

        .back-link i {
            margin-right: 0.5rem;
            transition: transform 0.3s;
        }

        .back-link:hover i {
            transform: translateX(-5px);
        }

        /* Responsive */
        @media (max-width: 520px) {
            .login-container {
                padding: 1rem;
            }

            .login-card {
                padding: 2.5rem 2rem;
                border-radius: 24px;
            }

            .logo-icon {
                width: 70px;
                height: 70px;
                font-size: 1.75rem;
            }

            .logo-text {
                font-size: 1.75rem;
            }

            .features {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .feature-item {
                display: flex;
                align-items: center;
                gap: 1rem;
                text-align: left;
                padding: 1rem 1.25rem;
            }

            .feature-item i {
                margin-bottom: 0;
                font-size: 1.25rem;
            }
        }
    </style>
</head>

<body>
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <h1 class="logo-text">Transloggi</h1>
                <p class="logo-subtitle">Painel Administrativo</p>
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
                    <label class="form-label" for="username">
                        <i class="fas fa-user"></i>
                        Usuário
                    </label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" class="form-input"
                            placeholder="Digite seu usuário" required autocomplete="username">
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        <i class="fas fa-lock"></i>
                        Senha
                    </label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" class="form-input"
                            placeholder="Digite sua senha" required autocomplete="current-password">
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>

                <button type="submit" name="login" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Entrar no Painel
                </button>
            </form>

            <div class="divider">
                <span>Recursos disponíveis</span>
            </div>

            <div class="features">
                <div class="feature-item">
                    <i class="fas fa-truck"></i>
                    <span>Rastreamento</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-robot"></i>
                    <span>Bot WhatsApp</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Relatórios</span>
                </div>
            </div>

            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Voltar para o site
            </a>
        </div>
    </div>
</body>

</html>