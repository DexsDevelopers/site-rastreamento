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
        if (empty($nome) || empty($telefone) || empty($cep) || empty($estado) || 
            empty($cidade) || empty($bairro) || empty($rua) || empty($numero)) {
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
            $nome, $telefone, $email, $cep, $estado, $cidade, 
            $bairro, $rua, $numero, $complemento, $observacoes
        ]);
        
        $success = true;
        
        // Enviar mensagem de confirmação via WhatsApp
        try {
            $telefoneNormalizado = normalizePhoneToDigits($telefone);
            
            if ($telefoneNormalizado) {
                // Buscar chave PIX das configurações
                $chavePix = getDynamicConfig('PEDIDO_PIX_KEY', '');
                
                $mensagem = "🎉 *Olá, {$nome}!*\n\n";
                $mensagem .= "✅ Recebemos seu pedido com sucesso!\n\n";
                $mensagem .= "📦 *Endereço de entrega confirmado:*\n";
                $mensagem .= "{$rua}, {$numero}";
                if ($complemento) $mensagem .= " - {$complemento}";
                $mensagem .= "\n{$bairro} - {$cidade}/{$estado}\n";
                $mensagem .= "CEP: " . substr($cep, 0, 5) . "-" . substr($cep, 5) . "\n\n";
                
                if ($chavePix) {
                    $mensagem .= "💳 *Chave PIX para pagamento:*\n";
                    $mensagem .= "`{$chavePix}`\n\n";
                }
                
                $mensagem .= "📸 Após o pagamento, envie o comprovante aqui nesta conversa.\n\n";
                $mensagem .= "⏳ Assim que confirmarmos, seu pedido será processado!\n\n";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fazer Pedido - Helmer Logistics</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #FF3333;
            --secondary-color: #FF6600;
            --success-color: #16A34A;
            --dark-bg: #0A0A0A;
            --card-bg: #1A1A1A;
            --border-color: #2A2A2A;
            --text-primary: #FFFFFF;
            --text-secondary: #cbd5e1;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0A0A0A 0%, #1A0000 100%);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 20px;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #FF3333 0%, #FF6600 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
        .form-card {
            background: linear-gradient(145deg, #1a1a1a 0%, #0f0f0f 100%);
            border: 1px solid rgba(255, 51, 51, 0.3);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .form-group label .required {
            color: var(--primary-color);
            margin-left: 3px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            background: #0f0f0f;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 51, 51, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 20px;
        }
        
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #FF3333 0%, #FF6600 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 51, 51, 0.4);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .info-box {
            background: rgba(255, 51, 51, 0.1);
            border: 1px solid rgba(255, 51, 51, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .info-box i {
            color: var(--primary-color);
            margin-right: 10px;
        }
        
        /* Seção de Referências */
        .referencias-section {
            max-width: 1200px;
            margin: 60px auto 40px;
            padding: 0 20px;
        }
        
        .section-title {
            font-size: 2rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #FF3333 0%, #FF6600 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .section-subtitle {
            text-align: center;
            color: rgba(255,255,255,0.7);
            margin-bottom: 2.5rem;
            font-size: 1rem;
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }
        
        .gallery-item {
            background: linear-gradient(135deg, rgba(255,51,51,0.08) 0%, rgba(255,102,0,0.08) 100%);
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid rgba(255,51,51,0.2);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .gallery-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #FF3333 0%, #FF6600 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .gallery-item:hover::before {
            transform: scaleX(1);
        }
        
        .gallery-item:hover {
            transform: translateY(-8px);
            border-color: var(--primary-color);
            box-shadow: 0 15px 35px rgba(255,51,51,0.25);
        }
        
        .gallery-image {
            height: 220px;
            overflow: hidden;
        }
        
        .gallery-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .gallery-item:hover .gallery-image img {
            transform: scale(1.08);
        }
        
        .gallery-info {
            padding: 1.25rem;
        }
        
        .gallery-info h4 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-size: 1rem;
            font-weight: 700;
        }
        
        .gallery-info p {
            color: rgba(255,255,255,0.75);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        /* Stats badges */
        .stats-badges {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 999px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,51,51,0.3);
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .stat-badge i {
            color: #FF6666;
        }
        
        @media (max-width: 768px) {
            .form-row,
            .form-row-3 {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .form-card {
                padding: 25px;
            }
            
            .gallery-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .section-title {
                font-size: 1.5rem;
            }
            
            .stats-badges {
                flex-direction: column;
                align-items: center;
            }
        }
        
        @media (min-width: 769px) and (max-width: 1024px) {
            .gallery-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-truck"></i> Fazer Pedido</h1>
            <p>Preencha seus dados para receber em casa</p>
        </div>
        
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <strong>Como funciona:</strong> Após preencher o formulário, você receberá as informações de pagamento via WhatsApp. 
            Assim que o pagamento for confirmado, seu pedido será processado com segurança!
        </div>
        
        <div class="form-card">
            <form method="POST" id="pedidoForm">
                <!-- Dados Pessoais -->
                <h3 style="margin-bottom: 20px; color: var(--primary-color);">
                    <i class="fas fa-user"></i> Dados Pessoais
                </h3>
                
                <div class="form-group">
                    <label>Nome Completo <span class="required">*</span></label>
                    <input type="text" name="nome" required autocomplete="name">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Telefone/WhatsApp <span class="required">*</span></label>
                        <input type="tel" name="telefone" required placeholder="(51) 99999-9999" autocomplete="tel">
                    </div>
                    
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" placeholder="seu@email.com" autocomplete="email">
                    </div>
                </div>
                
                <!-- Endereço -->
                <h3 style="margin-bottom: 20px; margin-top: 30px; color: var(--primary-color);">
                    <i class="fas fa-map-marker-alt"></i> Endereço de Entrega
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>CEP <span class="required">*</span></label>
                        <input type="text" name="cep" id="cep" required maxlength="9" placeholder="00000-000">
                    </div>
                    
                    <div class="form-group">
                        <label>Estado <span class="required">*</span></label>
                        <select name="estado" id="estado" required>
                            <option value="">Selecione</option>
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
                
                <div class="form-group">
                    <label>Cidade <span class="required">*</span></label>
                    <input type="text" name="cidade" id="cidade" required autocomplete="address-level2">
                </div>
                
                <div class="form-group">
                    <label>Bairro <span class="required">*</span></label>
                    <input type="text" name="bairro" id="bairro" required autocomplete="address-level3">
                </div>
                
                <div class="form-row-3">
                    <div class="form-group">
                        <label>Rua/Avenida <span class="required">*</span></label>
                        <input type="text" name="rua" id="rua" required autocomplete="street-address">
                    </div>
                    
                    <div class="form-group">
                        <label>Número <span class="required">*</span></label>
                        <input type="text" name="numero" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text" name="complemento" placeholder="Apto, Bloco, etc">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Observações</label>
                    <textarea name="observacoes" rows="3" placeholder="Pontos de referência, horário preferencial de entrega, etc"></textarea>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Enviar Pedido
                </button>
            </form>
        </div>
    </div>
    
    <!-- Seção de Referências -->
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
        // Máscara de telefone
        document.querySelector('input[name="telefone"]').addEventListener('input', function(e) {
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
        document.getElementById('cep').addEventListener('input', function(e) {
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
                            <i class="fab fa-whatsapp"></i> Enviamos uma mensagem no seu WhatsApp com as instruções de pagamento!
                        </p>
                        <?php else: ?>
                        <p style="font-size: 14px; color: #888;">
                            Aguarde nosso contato via WhatsApp com as informações de pagamento.
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





