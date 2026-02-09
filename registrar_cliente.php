<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitizeInput($_POST['nome'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $telefone = sanitizeInput($_POST['telefone'] ?? '');

    if (empty($nome) || empty($email) || empty($password)) {
        $error = 'Preencha os campos obrigatórios.';
    }
    elseif (!isValidEmail($email)) {
        $error = 'Email inválido.';
    }
    else {
        // Verificar se já existe
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Este e-mail já está cadastrado.';
        }
        else {
            $hashed = secureHash($password);
            $codigo = 'CLI-' . strtoupper(substr(md5(uniqid()), 0, 8));

            $stmt = $pdo->prepare("INSERT INTO clientes (codigo, nome, email, telefone, senha) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$codigo, $nome, $email, $telefone, $hashed])) {
                $success = 'Conta criada com sucesso! Redirecionando...';
                echo "<script>setTimeout(() => { window.location.href = 'login_cliente.php'; }, 2000);</script>";
            }
            else {
                $error = 'Erro ao criar conta. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastre-se - Loggi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.png">
</head>

<body
    style="background: var(--slate-50); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem;">

    <div class="results-card animate-fade-in" style="max-width: 480px; width: 100%; padding: 3rem;">
        <div style="text-align: center; margin-bottom: 2.5rem;">
            <a href="index.php"
                style="font-size: 2.5rem; font-weight: 900; color: var(--primary); text-decoration: none; letter-spacing: -2px;">loggi</a>
            <h2 style="margin-top: 1.5rem; color: var(--secondary); font-weight: 800;">Crie sua conta</h2>
            <p style="color: var(--slate-500); margin-top: 0.5rem;">Junte-se a milhares de clientes satisfeitos</p>
        </div>

        <?php if ($error): ?>
        <div
            style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600; text-align: center;">
            <i class="fas fa-exclamation-circle"></i>
            <?= $error?>
        </div>
        <?php
endif; ?>

        <?php if ($success): ?>
        <div
            style="background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600; text-align: center;">
            <i class="fas fa-check-circle"></i>
            <?= $success?>
        </div>
        <?php
endif; ?>

        <form method="POST">
            <div style="margin-bottom: 1.25rem;">
                <label
                    style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--secondary); margin-bottom: 0.5rem;">Nome
                    Completo</label>
                <input type="text" name="nome" required
                    style="width: 100%; padding: 1rem; border-radius: 12px; border: 1px solid var(--slate-200); outline: none;"
                    placeholder="Seu nome completo">
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label
                    style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--secondary); margin-bottom: 0.5rem;">E-mail</label>
                <input type="email" name="email" required
                    style="width: 100%; padding: 1rem; border-radius: 12px; border: 1px solid var(--slate-200); outline: none;"
                    placeholder="seu@email.com">
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label
                    style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--secondary); margin-bottom: 0.5rem;">Telefone</label>
                <input type="tel" name="telefone"
                    style="width: 100%; padding: 1rem; border-radius: 12px; border: 1px solid var(--slate-200); outline: none;"
                    placeholder="(00) 00000-0000">
            </div>

            <div style="margin-bottom: 2rem;">
                <label
                    style="display: block; font-size: 0.85rem; font-weight: 700; color: var(--secondary); margin-bottom: 0.5rem;">Senha</label>
                <input type="password" name="password" required
                    style="width: 100%; padding: 1rem; border-radius: 12px; border: 1px solid var(--slate-200); outline: none;"
                    placeholder="Crie uma senha forte">
            </div>

            <button type="submit" class="btn-cta primary"
                style="width: 100%; justify-content: center; padding: 1.1rem;">
                Criar minha conta
            </button>
        </form>

        <div style="text-align: center; margin-top: 2rem; font-size: 0.9rem; color: var(--slate-500);">
            Já tem conta? <a href="login_cliente.php"
                style="color: var(--primary); font-weight: 700; text-decoration: none;">Entre aqui</a>
        </div>
    </div>

</body>

</html>