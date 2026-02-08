<?php
// Configurações básicas e headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loggi - Escolha seu objetivo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body,
        html {
            height: 100%;
            overflow-x: hidden;
        }

        .container {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        /* Lado Esquerdo - Banner */
        .left-banner {
            flex: 1;
            background-color: #0055FF;
            background-image: url('assets/images/loggi_free_shipping_banner.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            display: none;
            /* Mobile first: escondido em telas pequenas */
        }

        /* Logo Loggi no topo esquerdo do banner azul */
        .banner-logo {
            position: absolute;
            top: 40px;
            left: 40px;
            color: white;
            font-size: 24px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10;
        }

        /* Lado Direito - Conteúdo */
        .right-content {
            flex: 1;
            background-color: #fff;
            padding: 40px;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .header-actions {
            display: flex;
            justify-content: space-between;
            /* Espalha itens */
            align-items: center;
            margin-bottom: 40px;
            width: 100%;
        }

        .back-button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 20px;
            color: #333;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .help-button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 20px;
            color: #333;
        }

        .content-wrapper {
            max-width: 480px;
            margin: 0 auto;
            width: 100%;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 32px;
            text-align: center;
        }

        .options-grid {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .option-card {
            display: flex;
            align-items: flex-start;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .option-card:hover {
            border-color: #0055FF;
            box-shadow: 0 4px 12px rgba(0, 85, 255, 0.1);
            transform: translateY(-2px);
        }

        .option-icon {
            font-size: 20px;
            color: #1f2937;
            margin-right: 16px;
            margin-top: 2px;
            width: 24px;
            text-align: center;
        }

        .option-card:hover .option-icon {
            color: #0055FF;
        }

        .option-content {
            flex: 1;
        }

        .option-title {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
            display: block;
        }

        .option-desc {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.4;
            display: block;
        }

        .login-footer {
            margin-top: 32px;
            text-align: center;
        }

        .login-link {
            color: #0055FF;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .login-link:hover {
            text-decoration: underline;
        }

        /* Desktop */
        @media (min-width: 768px) {
            .left-banner {
                display: block;
            }

            .content-wrapper {
                justify-content: center;
                /* Centraliza verticalmente no desktop */
            }

            h1 {
                text-align: left;
                /* Alinhado à esquerda no desktop para seguir layout */
                font-size: 28px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Lado Esquerdo (Banner) -->
        <div class="left-banner">
            <div class="banner-logo">
                <i class="fas fa-rabbit-fast"></i> <!-- Ícone aproximado -->
                <!-- Como fallback para o ícone customizado da loggi -->
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                    style="color: white;">
                    <path
                        d="M19 13C19 13 21 12 21 10C21 7 17.5 5.5 17.5 5.5C17.5 5.5 16 3 13.5 3C11 3 10.5 4 10.5 4L8.5 7.5L5 9C5 9 3 9.5 3 11.5C3 13.5 5 14 5 14L4 16H2V21H4C4 21.6 4.4 22 5 22H9C9.6 22 10 21.6 10 21H16C16 21.6 16.4 22 17 22H21C21.6 22 22 21.6 22 21H24V17H22L21 15.5L19 13Z"
                        fill="white" />
                </svg>
            </div>
        </div>

        <!-- Lado Direito (Opções) -->
        <div class="right-content">
            <div class="header-actions">
                <a href="index.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <button class="help-button" title="Ajuda">
                    <i class="far fa-question-circle"></i>
                </button>
            </div>

            <div class="content-wrapper">
                <h1>O que você deseja?</h1>

                <div class="options-grid">
                    <!-- Enviar pela Loggi -->
                    <a href="cadastro_envio.php" class="option-card">
                        <div class="option-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div class="option-content">
                            <span class="option-title">Enviar pela Loggi</span>
                            <span class="option-desc">Conheça e contrate serviços de entrega</span>
                        </div>
                    </a>

                    <!-- Acompanhar entrega -->
                    <a href="index.php" class="option-card">
                        <div class="option-icon">
                            <i class="fas fa-paper-plane"></i> <!-- Changed from location-arrow for variety -->
                        </div>
                        <div class="option-content">
                            <span class="option-title">Acompanhar entrega</span>
                            <span class="option-desc">Detalhes do pedido, mudanças e ajuda</span>
                        </div>
                    </a>

                    <!-- Ser ponto de coleta Loggi -->
                    <a href="cadastro_ponto.php" class="option-card">
                        <div class="option-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="option-content">
                            <span class="option-title">Ser ponto de coleta Loggi</span>
                            <span class="option-desc">Receba pacotes no seu ponto</span>
                        </div>
                    </a>

                    <!-- Ser entregador -->
                    <a href="cadastro_entregador.php" class="option-card">
                        <div class="option-icon">
                            <i class="fas fa-motorcycle"></i>
                        </div>
                        <div class="option-content">
                            <span class="option-title">Ser entregador</span>
                            <span class="option-desc">Você entrega pela Loggi</span>
                        </div>
                    </a>

                    <!-- Ser transportadora parceira -->
                    <a href="cadastro_transportadora.php" class="option-card">
                        <div class="option-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="option-content">
                            <span class="option-title">Ser transportadora parceira</span>
                            <span class="option-desc">Sua empresa entrega pela Loggi</span>
                        </div>
                    </a>
                </div>

                <div class="login-footer">
                    <a href="login.php" class="login-link">Voltar para Login</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>