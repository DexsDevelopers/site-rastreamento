<?php
/**
 * Formulário de Pedido - Helmer Logistics
 * Página pública para clientes preencherem endereço
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/whatsapp_helper.php';

$success = false;
$error = '';
$whatsappEnviado = false;

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar e sanitizar dados
        $nome = sanitizeInput($_POST['nome'] ?? '');
        $telefone = sanitizeInput($_POST['telefone'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $cep = sanitizeInput($_POST['cep'] ?? '');
        $estado = sanitizeInput($_POST['estado'] ?? '');
        $cidade = sanitizeInput($_POST['cidade'] ?? '');
        $bairro = sanitizeInput($_POST['bairro'] ?? '');
        $rua = sanitizeInput($_POST['rua'] ?? '');
        $numero = sanitizeInput($_POST['numero'] ?? '');
        $complemento = sanitizeInput($_POST['complemento'] ?? '');
        $observacoes = sanitizeInput($_POST['observacoes'] ?? '');

        // Validações básicas
        if (
            empty($nome) || empty($telefone) || empty($cep) || empty($estado) ||
            empty($cidade) || empty($bairro) || empty($rua) || empty($numero)
        ) {
            throw new Exception('Por favor, preencha todos os campos obrigatórios.');
        }

        // Validar telefone
        $telefoneOriginal = $telefone;
        $telefone = preg_replace('/[^0-9]/', '', $telefone);
        if (strlen($telefone) < 10) {
            throw new Exception('Telefone inválido.');
        }

        // Validar CEP
        $cep = preg_replace('/[^0-9]/', '', $cep);
        if (strlen($cep) !== 8) {
            throw new Exception('CEP inválido. Digite apenas os 8 dígitos.');
        }

        // Inserir pedido pendente
        $sql = "INSERT INTO pedidos_pendentes 
                (nome, telefone, email, cep, estado, cidade, bairro, rua, numero, complemento, observacoes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nome,
            $telefone,
            $email,
            $cep,
            $estado,
            $cidade,
            $bairro,
            $rua,
            $numero,
            $complemento,
            $observacoes
        ]);

        $success = true;

        // Enviar mensagem de confirmação via WhatsApp
        try {
            $telefoneNormalizado = normalizePhoneToDigits($telefone);

            if ($telefoneNormalizado) {
                $mensagem = "🎉 *Olá, {$nome}!*\n\n";
                $mensagem .= "✅ Recebemos seu pedido com sucesso!\n\n";
                $mensagem .= "📦 *Endereço de entrega confirmado:*\n";
                $mensagem .= "{$rua}, {$numero}";
                if ($complemento)
                    $mensagem .= " - {$complemento}";
                $mensagem .= "\n{$bairro} - {$cidade}/{$estado}\n";
                $mensagem .= "CEP: " . substr($cep, 0, 5) . "-" . substr($cep, 5) . "\n\n";
                $mensagem .= "⏳ Nossa equipe entrará em contato em breve para finalizar seu pedido!\n\n";
                $mensagem .= "Obrigado pela preferência! 🚚";

                $resultado = sendWhatsappMessage($telefoneNormalizado, $mensagem);
                $whatsappEnviado = $resultado['success'];

                if (!$resultado['success']) {
                    writeLog("Falha ao enviar WhatsApp para pedido: " . ($resultado['error'] ?? 'Erro desconhecido'), 'WARNING');
                }
            }
        } catch (Exception $whatsappError) {
            writeLog("Erro ao enviar WhatsApp para pedido: " . $whatsappError->getMessage(), 'WARNING');
            // Não interrompe o fluxo se o WhatsApp falhar
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#FF3333">
    <title>Fazer Pedido - Helmer Logistics</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #EF4444;
            /* Vermelho vibrante */
            --primary-dark: #DC2626;
            --surface: #18181B;
            /* Zinco 900 */
            --surface-hover: #27272A;
            /* Zinco 800 */
            --background: #09090B;
            /* Zinco 950 */
            --text-main: #FFFFFF;
            --text-muted: #A1A1AA;
            /* Zinco 400 */
            --border: #27272A;
            --focus-ring: rgba(239, 68, 68, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
        }

        body {
            background-color: var(--background);
            background-image:
                radial-gradient(circle at 50% 0%, rgba(239, 68, 68, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 100%, rgba(239, 68, 68, 0.05) 0%, transparent 30%);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem;
            line-height: 1.5;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }

        .header-brand {
            text-align: center;
            margin-bottom: 2.5rem;
            animation: fadeInDown 0.6s ease;
        }

        .header-brand h1 {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #FFF 0%, #A1A1AA 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-brand p {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .checkout-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeInUp 0.6s ease;
            position: relative;
            overflow: hidden;
        }

        .checkout-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), transparent);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-main);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .section-title i {
            color: var(--primary);
        }

        .form-grid {
            display: grid;
            gap: 1.25rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .input-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        .input-group label span {
            color: var(--primary);
        }

        input,
        select,
        textarea {
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.2s;
            width: 100%;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
            background: var(--surface-hover);
        }

        input::placeholder,
        textarea::placeholder {
            color: #52525B;
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            width: 100%;
            padding: 1rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            margin-top: 2rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(239, 68, 68, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .trust-badges {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 2.5rem;
            opacity: 0.6;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Mobile specific */
        /* Mobile specific (Robust) */
        @media (max-width: 768px) {
            .form-row, .form-row-3 {
                grid-template-columns: 1fr;
            }
            .checkout-card {
                padding: 1.5rem;
            }
            .header-brand h1 {
                font-size: 1.75rem;
            }
            
            /* Gallery Mobile Fixes - Enforce single column and contained width */
            .referencias-section {
                padding: 0;
                margin-top: 3rem;
                width: 100% !important;
                max-width: 100vw !important;
                overflow: hidden !important; /* Prevent headers or images from spilling */
            }
            
            .gallery-grid {
                display: grid !important;
                grid-template-columns: 1fr !important; /* Force 1 column */
                gap: 1.5rem;
                padding: 0 1rem;
                width: 100% !important;
                box-sizing: border-box;
            }
            
            .gallery-item {
                width: 100% !important;
                margin: 0 auto;
                border-radius: 16px;
                overflow: hidden; /* Ensure image corners don't bleed */
            }
            
            .gallery-image {
                height: 220px !important; /* Fixed height for consistency */
                width: 100% !important;
            }
            
            .gallery-image img {
                width: 100% !important;
                height: 100% !important;
                object-fit: cover !important;
            }
            
            .stats-badges {
                flex-direction: column;
                align-items: stretch;
                padding: 0 1rem;
            }
            
            .stat-badge {
                justify-content: center;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header-brand">
            <h1>Finalizar Pedido</h1>
            <p>Entrega rápida e segura para todo o Brasil</p>
        </header>

        <div class="checkout-card">
            <form method="POST" id="pedidoForm" onsubmit="handleSubmit(event)">

                <div class="section-title">
                    <i class="fas fa-user-circle"></i> Dados Pessoais
                </div>

                <div class="form-grid">
                    <div class="input-group">
                        <label>Nome Completo <span>*</span></label>
                        <input type="text" name="nome" required autocomplete="name" placeholder="Ex: Maria Silva">
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label>WhatsApp <span>*</span></label>
                            <input type="tel" name="telefone" required placeholder="(11) 99999-9999" autocomplete="tel">
                        </div>

                        <div class="input-group">
                            <label>E-mail (Opcional)</label>
                            <input type="email" name="email" placeholder="email@exemplo.com" autocomplete="email">
                        </div>
                    </div>
                </div>

                <div class="section-title" style="margin-top: 2rem;">
                    <i class="fas fa-map-marker-alt"></i> Endereço
                </div>

                <div class="form-grid">
                    <div class="form-row">
                        <div class="input-group">
                            <label>CEP <span>*</span></label>
                            <input type="text" name="cep" id="cep" required maxlength="9" placeholder="00000-000">
                        </div>

                        <div class="input-group">
                            <label>Estado <span>*</span></label>
                            <select name="estado" id="estado" required>
                                <option value="" disabled selected>UF</option>
                                <option value="AC">AC</option>
                                <option value="AL">AL</option>
                                <option value="AP">AP</option>
                                <option value="AM">AM</option>
                                <option value="BA">BA</option>
                                <option value="CE">CE</option>
                                <option value="DF">DF</option>
                                <option value="ES">ES</option>
                                <option value="GO">GO</option>
                                <option value="MA">MA</option>
                                <option value="MT">MT</option>
                                <option value="MS">MS</option>
                                <option value="MG">MG</option>
                                <option value="PA">PA</option>
                                <option value="PB">PB</option>
                                <option value="PR">PR</option>
                                <option value="PE">PE</option>
                                <option value="PI">PI</option>
                                <option value="RJ">RJ</option>
                                <option value="RN">RN</option>
                                <option value="RS">RS</option>
                                <option value="RO">RO</option>
                                <option value="RR">RR</option>
                                <option value="SC">SC</option>
                                <option value="SP">SP</option>
                                <option value="SE">SE</option>
                                <option value="TO">TO</option>
                            </select>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Cidade <span>*</span></label>
                        <input type="text" name="cidade" id="cidade" required autocomplete="address-level2">
                    </div>

                    <div class="input-group">
                        <label>Bairro <span>*</span></label>
                        <input type="text" name="bairro" id="bairro" required autocomplete="address-level3">
                    </div>

                    <div class="form-row-3">
                        <div class="input-group">
                            <label>Rua/Avenida <span>*</span></label>
                            <input type="text" name="rua" id="rua" required autocomplete="street-address">
                        </div>

                        <div class="input-group">
                            <label>Número <span>*</span></label>
                            <input type="text" name="numero" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Complemento</label>
                        <input type="text" name="complemento" placeholder="Apto, Bloco, etc">
                    </div>

                    <div class="input-group">
                        <label>Observações de entrega</label>
                        <textarea name="observacoes" rows="2" placeholder="Ponto de referência, etc..."></textarea>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-check-circle"></i> Confirmar Pedido
                </button>

            </form>
        </div>

        <div class="trust-badges">
            <div class="trust-item"><i class="fas fa-lock"></i> Dados Seguros</div>
            <div class="trust-item"><i class="fas fa-check-circle"></i> Loja Verificada</div>
        </div>

    </div> <!-- Close .container -->

    <section class="referencias-section">
        <h2 class="section-title">📱 Sistema em Ação</h2>
        <p class="section-subtitle">
            Veja como nossos clientes acompanham suas entregas em tempo real pelo WhatsApp
        </p>

        <div class="gallery-grid">
            <div class="gallery-item">
                <div class="gallery-image">
                    <img src="assets/images/whatsapp-1.jpg" alt="Cliente Petrópolis" loading="lazy">
                </div>
                <div class="gallery-info">
                    <h4>📍 Luiz Gabriel - Petrópolis</h4>
                    <p>Sistema de rastreamento funcionando perfeitamente</p>
                </div>
            </div>
            <div class="gallery-item">
                <div class="gallery-image">
                    <img src="assets/images/whatsapp-2.jpg" alt="Cliente Ubá" loading="lazy">
                </div>
                <div class="gallery-info">
                    <h4>📍 juuh santts - Ubá</h4>
                    <p>Monitoramento oficial com status detalhado</p>
                </div>
            </div>
            <div class="gallery-item">
                <div class="gallery-image">
                    <img src="assets/images/whatsapp-3.jpg" alt="Cliente Jardim Camburi" loading="lazy">
                </div>
                <div class="gallery-info">
                    <h4>📍 RKZIN - Jardim Camburi</h4>
                    <p>Sistema oficial de monitoramento em tempo real</p>
                </div>
            </div>
            <div class="gallery-item">
                <div class="gallery-image">
                    <img src="assets/images/whatsapp-4.jpg" alt="Cliente AdolfoSP" loading="lazy">
                </div>
                <div class="gallery-info">
                    <h4>📍 Vitor João - AdolfoSP</h4>
                    <p>Monitoramento integrado ao WhatsApp</p>
                </div>
            </div>
            <div class="gallery-item">
                <div class="gallery-image">
                    <img src="assets/images/whatsapp-5.jpg" alt="Entrega Confirmada" loading="lazy">
                </div>
                <div class="gallery-info">
                    <h4>📍 2L CLIENTE - Entrega Confirmada</h4>
                    <p>Sistema de entrega e pagamento funcionando</p>
                </div>
            </div>
            <div class="gallery-item">
                <div class="gallery-image">
                    <img src="assets/images/whatsapp-6.jpg" alt="Cliente GO" loading="lazy">
                </div>
                <div class="gallery-info">
                    <h4>📍 Bada CLIENTE - GO</h4>
                    <p>Sistema de Indicação + Rastreamento completo</p>
                </div>
            </div>
        </div>

        <div class="stats-badges">
            <span class="stat-badge"><i class="fas fa-check-circle"></i> 98.7% de Satisfação</span>
            <span class="stat-badge"><i class="fas fa-truck"></i> 5.247 Entregas</span>
            <span class="stat-badge"><i class="fas fa-map-marker-alt"></i> 247 Cidades</span>
        </div>
    </section>

    <script>
        // Prevenir zoom no mobile
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);

        document.addEventListener('gesturestart', function (e) { e.preventDefault(); });
        document.addEventListener('gesturechange', function (e) { e.preventDefault(); });
        document.addEventListener('gestureend', function (e) { e.preventDefault(); });

        // Loading state do formulário
        function handleSubmit(e) {
            const form = document.getElementById('pedidoForm');
            const btn = document.getElementById('submitBtn');

            if (form.checkValidity()) {
                btn.classList.add('loading');
                btn.innerHTML = '<span style="opacity: 0;">Enviando...</span>';
            }
        }

        // Máscara de telefone
        document.querySelector('input[name="telefone"]').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                if (value.length <= 10) {
                    value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
                } else {
                    value = value.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
                }
                e.target.value = value;
            }
        });

        // Máscara de CEP e busca automática
        document.getElementById('cep').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 8) {
                value = value.replace(/^(\d{5})(\d{0,3}).*/, '$1-$2');
                e.target.value = value;

                // Buscar CEP quando tiver 8 dígitos
                if (value.replace(/\D/g, '').length === 8) {
                    buscarCEP(value.replace(/\D/g, ''));
                }
            }
        });

        function buscarCEP(cep) {
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(response => response.json())
                .then(data => {
                    if (!data.erro) {
                        document.getElementById('rua').value = data.logradouro || '';
                        document.getElementById('bairro').value = data.bairro || '';
                        document.getElementById('cidade').value = data.localidade || '';
                        document.getElementById('estado').value = data.uf || '';
                    }
                })
                .catch(err => console.log('Erro ao buscar CEP:', err));
        }

        // Processar sucesso/erro
        <?php if ($success): ?>
            Swal.fire({
                title: '✅ Pedido Enviado!',
                html: `
                    <div style="text-align: center; padding: 10px;">
                        <p style="font-size: 16px; margin-bottom: 15px;">
                            Seu pedido foi recebido com sucesso!
                        </p>
                        <?php if ($whatsappEnviado): ?>
                        <p style="font-size: 14px; color: #25D366; margin-bottom: 10px;">
                            <i class="fab fa-whatsapp"></i> Enviamos uma mensagem no seu WhatsApp! Nossa equipe entrará em contato em breve.
                        </p>
                        <?php else: ?>
                        <p style="font-size: 14px; color: #888;">
                            Aguarde nosso contato via WhatsApp. Nossa equipe entrará em contato em breve para finalizar seu pedido.
                        </p>
                        <?php endif; ?>
                    </div>
                `,
                icon: 'success',
                confirmButtonText: 'OK',
                background: '#1a1a1a',
                color: '#ffffff',
                confirmButtonColor: '#FF3333'
            }).then(() => {
                window.location.href = 'pedido.php';
            });
        <?php endif; ?>

        <?php if ($error): ?>
            Swal.fire({
                title: '❌ Erro',
                text: '<?= addslashes($error) ?>',
                icon: 'error',
                confirmButtonText: 'OK',
                background: '#1a1a1a',
                color: '#ffffff',
                confirmButtonColor: '#FF3333'
            });
        <?php endif; ?>
    </script>
</body>

</html>