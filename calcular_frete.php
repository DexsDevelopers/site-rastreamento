<?php
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loggi - Calcular Frete</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/mobile_menu.css">
    <style>
        .calc-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0055FF 0%, #003399 100%);
            padding: 2rem;
            padding-top: 6rem;
            /* Space for header */
        }

        .calc-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            padding: 2.5rem;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 600px;
        }

        .calc-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .calc-header h1 {
            color: #111827;
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .calc-header p {
            color: #4b5563;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s;
            background: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: #0055FF;
            box-shadow: 0 0 0 4px rgba(0, 85, 255, 0.1);
        }

        .btn-calc {
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-calc:hover {
            background-color: #0044CC;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 85, 255, 0.4);
        }

        /* Results Section */
        #results-area {
            display: none;
            /* Hidden by default */
            margin-top: 2rem;
            animation: fadeIn 0.5s ease-out;
        }

        .result-option {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .result-option:hover {
            border-color: #0055FF;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .result-option.featured {
            border: 2px solid #0055FF;
            background: #f0f7ff;
        }

        .result-option.featured::before {
            content: 'RECOMENDADO';
            position: absolute;
            top: 0;
            right: 0;
            background: #0055FF;
            color: white;
            font-size: 0.7rem;
            font-weight: 800;
            padding: 4px 8px;
            border-bottom-left-radius: 8px;
        }

        .option-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .option-icon {
            width: 48px;
            height: 48px;
            background: #eef2ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0055FF;
            font-size: 1.5rem;
        }

        .option-details h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .option-details p {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .option-price {
            text-align: right;
        }

        .price-value {
            display: block;
            font-size: 1.25rem;
            font-weight: 800;
            color: #1f2937;
        }

        .price-free {
            color: #16A34A;
            /* Green */
        }

        .select-btn {
            background: #e5e7eb;
            color: #374151;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 0.5rem;
            transition: 0.2s;
        }

        .result-option:hover .select-btn {
            background: #d1d5db;
        }

        .result-option.featured .select-btn {
            background: #0055FF;
            color: white;
        }

        .result-option.featured .select-btn:hover {
            background: #0044CC;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <!-- Header Glassmorphism -->
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

    <div class="calc-container">
        <div class="calc-card">
            <div class="calc-header">
                <h1>Simular Frete</h1>
                <p>Calcule o prazo e valor para sua entrega</p>
            </div>

            <form id="calcForm" onsubmit="calculateShipping(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label for="cep_origem">CEP de Origem</label>
                        <input type="text" id="cep_origem" class="form-control" placeholder="00000-000" maxlength="9"
                            required>
                    </div>
                    <div class="form-group">
                        <label for="cep_destino">CEP de Destino</label>
                        <input type="text" id="cep_destino" class="form-control" placeholder="00000-000" maxlength="9"
                            required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="peso">Peso Estimado (kg)</label>
                        <select id="peso" class="form-control">
                            <option value="0.3">Até 300g (Envelope)</option>
                            <option value="1">Até 1kg</option>
                            <option value="2">Até 2kg</option>
                            <option value="5">Até 5kg</option>
                            <option value="10">Até 10kg</option>
                            <option value="30">Acima de 10kg</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="formato">Formato</label>
                        <select id="formato" class="form-control">
                            <option value="box">Caixa / Pacote</option>
                            <option value="envelope">Envelope</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-calc" id="btnCalc">
                    <i class="fas fa-calculator"></i> Calcular Frete
                </button>
            </form>

            <div id="results-area">
                <h3 style="margin-bottom: 1rem; color: #374151; font-size: 1.1rem;">Opções de Envio:</h3>

                <!-- Frete Grátis -->
                <div class="result-option">
                    <div class="option-info">
                        <div class="option-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="option-details">
                            <h3>Econômico</h3>
                            <p>Prazo: 5 a 7 dias úteis</p>
                            <p style="font-size: 0.8rem; color: #16A34A; margin-top: 4px;"><i class="fas fa-check"></i>
                                Rastreamento incluso</p>
                        </div>
                    </div>
                    <div class="option-price">
                        <span class="price-value price-free">GRÁTIS</span>
                        <button class="select-btn" onclick="selectOption('free')">Selecionar</button>
                    </div>
                </div>

                <!-- Expresso 29,90 -->
                <div class="result-option featured">
                    <div class="option-info">
                        <div class="option-icon" style="color: #0055FF;">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="option-details">
                            <h3>Loggi Express</h3>
                            <p>Prazo: <strong>3 dias úteis</strong></p>
                            <p style="font-size: 0.8rem; color: #0055FF; margin-top: 4px;"><i class="fas fa-star"></i>
                                Prioridade máxima</p>
                        </div>
                    </div>
                    <div class="option-price">
                        <span class="price-value">R$ 29,90</span>
                        <button class="select-btn" onclick="selectOption('express')">Contratar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#cep_origem').mask('00000-000');
            $('#cep_destino').mask('00000-000');
        });

        function mobileMenuToggle() {
            const mm = document.getElementById('mobileMenu');
            mm.classList.toggle('active');
        }

        function calculateShipping(e) {
            e.preventDefault();
            const btn = document.getElementById('btnCalc');
            const results = document.getElementById('results-area');

            // Validate
            const cep1 = document.getElementById('cep_origem').value;
            const cep2 = document.getElementById('cep_destino').value;

            if (cep1.length < 9 || cep2.length < 9) {
                alert('Por favor, preencha os CEPs corretamente.');
                return;
            }

            // Loading state
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculando...';
            btn.disabled = true;
            results.style.display = 'none';

            // Fake delay for realism
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-calculator"></i> Calcular Novamente';
                btn.disabled = false;
                results.style.display = 'block';

                // Scroll to results
                results.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 1500);
        }

        function selectOption(type) {
            if (type === 'express') {
                // Redirect to payment or express signup
                window.location.href = 'solicitar_express_checkout.php'; // Or just alert/modal for now
                alert('Você selecionou entrega Expressa! Redirecionando para pagamento...');
            } else {
                alert('Você selecionou entrega Grátis! Redirecionando para cadastro...');
                window.location.href = 'cadastro_envio.php';
            }
        }

        // Mobile Menu Toggle (Reused from index)
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('active');
        }
    </script>
</body>

</html>