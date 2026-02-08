<?php
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Loggi - Seja um Entregador</title>
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
                    <i class="fas fa-motorcycle" style="font-size: 2rem; color: #0055FF;"></i>
                </a>
                <h1>Cadastro de Entregador</h1>
                <p>Faça entregas e ganhe dinheiro com a Loggi</p>
            </div>

            <form action="index.php" method="GET"> <!-- Simulação -->
                <div class="form-group">
                    <label for="nome">Nome Completo</label>
                    <input type="text" id="nome" name="nome" class="form-control" placeholder="Seu nome" required>
                </div>
                <div class="form-group">
                    <label for="cpf">CPF</label>
                    <input type="text" id="cpf" name="cpf" class="form-control" placeholder="000.000.000-00" required
                        maxlength="14" oninput="maskCPF(this)">
                    <small id="cpf-error" style="color: #ff3333; display: none; margin-top: 5px;">CPF inválido</small>
                </div>
                <div class="form-group">
                    <label for="veiculo">Tipo de Veículo</label>
                    <select id="veiculo" name="veiculo" class="form-control">
                        <option value="moto">Moto</option>
                        <option value="carro">Carro</option>
                        <option value="van">Van</option>
                        <option value="bicicleta">Bicicleta</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="celular">Celular com WhatsApp</label>
                    <input type="tel" id="celular" name="celular" class="form-control" placeholder="(00) 00000-0000"
                        required>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: top; gap: 8px; font-weight: 400; font-size: 0.85rem;">
                        <input type="checkbox" required>
                        Concordo com os Termos de Uso.
                    </label>
                </div>

                <button type="submit" class="btn-full">Iniciar Cadastro</button>
            </form>

            <script>
                function maskCPF(input) {
                    let v = input.value.replace(/\D/g, "");
                    if (v.length > 11) v = v.slice(0, 11);
                    v = v.replace(/(\d{3})(\d)/, "$1.$2");
                    v = v.replace(/(\d{3})(\d)/, "$1.$2");
                    v = v.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
                    input.value = v;
                    validateCPFInput(input);
                }

                function validateCPFInput(input) {
                    const cpf = input.value.replace(/\D/g, '');
                    const errorSpan = document.getElementById('cpf-error');
                    const btn = document.querySelector('button[type="submit"]');

                    if (cpf.length === 11) {
                        if (!isValidCPF(cpf)) {
                            errorSpan.style.display = 'block';
                            input.style.borderColor = '#ff3333';
                            btn.disabled = true;
                            btn.style.opacity = '0.5';
                            btn.style.cursor = 'not-allowed';
                        } else {
                            errorSpan.style.display = 'none';
                            input.style.borderColor = '#d1d5db'; // Reset border
                            btn.disabled = false;
                            btn.style.opacity = '1';
                            btn.style.cursor = 'pointer';
                        }
                    }
                }

                function isValidCPF(cpf) {
                    if (typeof cpf !== "string") return false;
                    cpf = cpf.replace(/[\s.-]*/igm, '');
                    if (
                        !cpf ||
                        cpf.length != 11 ||
                        cpf == "00000000000" ||
                        cpf == "11111111111" ||
                        cpf == "22222222222" ||
                        cpf == "33333333333" ||
                        cpf == "44444444444" ||
                        cpf == "55555555555" ||
                        cpf == "66666666666" ||
                        cpf == "77777777777" ||
                        cpf == "88888888888" ||
                        cpf == "99999999999"
                    ) {
                        return false;
                    }
                    var soma = 0;
                    var resto;
                    for (var i = 1; i <= 9; i++)
                        soma = soma + parseInt(cpf.substring(i - 1, i)) * (11 - i);
                    resto = (soma * 10) % 11;
                    if ((resto == 10) || (resto == 11)) resto = 0;
                    if (resto != parseInt(cpf.substring(9, 10))) return false;
                    soma = 0;
                    for (var i = 1; i <= 10; i++)
                        soma = soma + parseInt(cpf.substring(i - 1, i)) * (12 - i);
                    resto = (soma * 10) % 11;
                    if ((resto == 10) || (resto == 11)) resto = 0;
                    if (resto != parseInt(cpf.substring(10, 11))) return false;
                    return true;
                }

                // Add submit event listener to prevent invalid submission
                document.querySelector('form').addEventListener('submit', function (e) {
                    const cpfInput = document.getElementById('cpf');
                    const cpf = cpfInput.value.replace(/\D/g, '');
                    if (!isValidCPF(cpf)) {
                        e.preventDefault();
                        document.getElementById('cpf-error').style.display = 'block';
                        cpfInput.style.borderColor = '#ff3333';
                        cpfInput.focus();
                    }
                });
            </script>

            <div class="auth-footer">
                Já é cadastrado? <a href="index.php">Voltar para o Início</a>
            </div>
            <div class="auth-footer" style="margin-top: 0.5rem;">
                <a href="cadastro_objetivo.php"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
        </div>
    </div>
</body>

</html>