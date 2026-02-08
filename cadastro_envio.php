<?php
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loggi - Enviar Pacotes</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0055FF 0%, #003399 100%);
            padding: 2rem;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 480px;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-header h1 {
            color: #111827;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .auth-header p {
            color: #6b7280;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.15s ease-in-out;
        }

        .form-control:focus {
            outline: none;
            border-color: #0055FF;
            box-shadow: 0 0 0 3px rgba(0, 85, 255, 0.1);
        }

        .btn-full {
            width: 100%;
            padding: 0.875rem;
            background-color: #0055FF;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.15s;
        }

        .btn-full:hover {
            background-color: #0044CC;
        }

        .auth-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .auth-footer a {
            color: #0055FF;
            font-weight: 600;
            text-decoration: none;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="index.php" style="text-decoration: none; display: inline-block; margin-bottom: 1rem;">
                    <i class="fas fa-shipping-fast" style="font-size: 2rem; color: #0055FF;"></i>
                </a>
                <h1>Criar conta para enviar</h1>
                <p>Comece a enviar seus pacotes com a Loggi</p>
            </div>

            <form action="index.php" method="GET"> <!-- Simulação -->
                <div class="form-group">
                    <label for="nome">Nome Completo</label>
                    <input type="text" id="nome" name="nome" class="form-control" placeholder="Seu nome" required>
                </div>
                <div class="form-group">
                    <label for="email">E-mail corporativo ou pessoal</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="seu@email.com"
                        required>
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: top; gap: 8px; font-weight: 400; font-size: 0.85rem;">
                        <input type="checkbox" required>
                        Concordo com os Termos de Uso e Política de Privacidade da Loggi.
                    </label>
                </div>

                <button type="submit" class="btn-full">Criar conta grátis</button>
            </form>

            <div class="auth-footer">
                Já tem uma conta? <a href="login.php">Fazer login</a>
            </div>
            <div class="auth-footer" style="margin-top: 0.5rem;">
                <a href="cadastro_objetivo.php"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
        </div>
    </div>
</body>

</html>