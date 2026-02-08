<?php
/**
 * Checkout/Solicitação da Entrega Expressa (Simulação)
 * Página de aterrissagem após o usuário escolher "Express" no cálculo de frete.
 */
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loggi Express - Checkout</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .checkout-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0055FF 0%, #003399 100%);
            padding: 2rem;
            padding-top: 6rem;
        }

        .checkout-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        .checkout-icon {
            font-size: 3rem;
            color: #0055FF;
            margin-bottom: 1.5rem;
            background: #eef2ff;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-left: auto;
            /* center */
            margin-right: auto;
        }

        .checkout-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: #1f2937;
        }

        .checkout-desc {
            color: #4b5563;
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .price-tag {
            font-size: 2.5rem;
            font-weight: 800;
            color: #0055FF;
            margin-bottom: 2rem;
            display: block;
        }

        .btn-pay {
            width: 100%;
            padding: 1rem;
            background-color: #0055FF;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0, 85, 255, 0.3);
            margin-bottom: 1rem;
        }

        .btn-pay:hover {
            background-color: #0044CC;
            transform: translateY(-2px);
        }

        .btn-back {
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-back:hover {
            color: #374151;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="checkout-container">
        <div class="checkout-card">
            <div class="checkout-icon">
                <i class="fas fa-bolt"></i>
            </div>
            <h1 class="checkout-title">Finalizar Entrega Expressa</h1>
            <p class="checkout-desc">
                Você escolheu a modalidade <strong>Loggi Express</strong> (3 dias).<br>
                Para confirmar, realize o pagamento da taxa de prioridade.
            </p>

            <span class="price-tag">R$ 29,90</span>

            <form action="cadastro_envio.php" method="GET">
                <!-- In a real app, this would go to a payment gateway first -->
                <button type="submit" class="btn-pay">
                    Ir para Pagamento <i class="fas fa-arrow-right" style="margin-left: 8px;"></i>
                </button>
            </form>

            <button class="btn-back" onclick="history.back()">Voltar</button>
        </div>
    </div>
</body>

</html>