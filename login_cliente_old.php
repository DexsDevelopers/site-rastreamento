<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Preencha todos os campos.';
    }
    else {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && verifyHash($password, $user['senha'])) {
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nome'] = $user['nome'];
            header('Location: index.php');
            exit;
        }
        else {
            $error = 'Email ou senha incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar - Loggi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.png">
</head>

<body
    style="background: var(--slate-50); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem;">

    <div class="results-card animate-fade-in" style="max-width: 400px; width: 100%; padding: 3rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <a href="index.php"
                style="font-size: 2.5rem; font-weight: 900; color: var(--primary); text-decoration: none; letter-spacing: -2px;">loggi</a>
            <h2 style="margin-top: 1.5rem; color: var(--secondary); font-weight: 800;">Acesse sua conta</h2>
            <p style="color: var(--slate-500); margin-top: 0.5rem;">Gerencie seus envios e rastreios</p>
        </div>

        <?php if ($error): ?>
        <div
            style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600; text-align: center;">
            <i class="fas fa-exclamation-circle"></i>
            <?= $error?>
        </div>
        <?php
endif; ?>

        <form method="POST">
            <div style="margin-bottom: 1.25rem;">
                <label
                    style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--secondary); margin-bottom: 0.5rem;">E-mail</label>
                <div style="position: relative;">
                    <i class="fas fa-envelope"
                        style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--slate-400);"></i>
                    <input type="email" name="email" required
                        style="width: 100%; padding: 1rem 1rem 1rem 3rem; border-radius: 12px; border: 1px solid var(--slate-200); outline: none; transition: all 0.3s;"
                        placeholder="seu@email.com">
                </div>
            </div>

            <div style="margin-bottom: 2rem;">
                <label
                    style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--secondary); margin-bottom: 0.5rem;">Senha</label>
                <div style="position: relative;">
                    <i class="fas fa-lock"
                        style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--slate-400);"></i>
                    <input type="password" name="password" required
                        style="width: 100%; padding: 1rem 1rem 1rem 3rem; border-radius: 12px; border: 1px solid var(--slate-200); outline: none;"
                        placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="btn-cta primary"
                style="width: 100%; justify-content: center; padding: 1.1rem;">
                Entrar agora
            </button>
        </form>

        <div style="text-align: center; margin-top: 2rem; font-size: 0.9rem; color: var(--slate-500);">
            Ainda não tem conta? <a href="registrar_cliente.php"
                style="color: var(--primary); font-weight: 700; text-decoration: none;">Cadastre-se</a>
        </div>

        <div style="text-align: center; margin-top: 1rem;">
            <a href="index.php" style="font-size: 0.85rem; color: var(--slate-400); text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Voltar ao início
            </a>
        </div>
    </div>

</body>

</html>