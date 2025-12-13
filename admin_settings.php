<?php
/**
 * Configurações de Entrega Expressa (Admin)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';

// Verificar login
if (!isset($_SESSION['logado'])) {
    header('Location: admin.php');
    exit;
}

$message = '';
$type = '';
$currentFee = getDynamicConfig('EXPRESS_FEE_VALUE', getConfig('EXPRESS_FEE_VALUE', 29.90));
$currentPix = getDynamicConfig('EXPRESS_PIX_KEY', getConfig('EXPRESS_PIX_KEY', 'pix@exemplo.com'));

// Configurações do formulário de pedidos
$pedidoPixKey = getDynamicConfig('PEDIDO_PIX_KEY', '');

// Salvar configurações do formulário de pedidos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_settings_pedido'])) {
    try {
        $pixPedido = isset($_POST['pedido_pix_key']) ? trim($_POST['pedido_pix_key']) : '';
        
        $savedPixPedido = setDynamicConfig('PEDIDO_PIX_KEY', $pixPedido);

        if (!$savedPixPedido) {
            throw new Exception('Erro ao salvar configurações. Verifique permissões do arquivo config.json.');
        }

        writeLog("Configurações do formulário de pedidos atualizadas: PIX={$pixPedido}", 'INFO');
        
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        clearstatcache(true, __DIR__ . '/config_custom.json');
        
        $pedidoPixKey = getDynamicConfig('PEDIDO_PIX_KEY', '');
        
        $message = "✅ Configurações do formulário de pedidos salvas com sucesso!";
        $type = 'success';
    } catch (Exception $e) {
        $message = $e->getMessage();
        $type = 'error';
        writeLog("Erro ao salvar configurações de pedidos: " . $e->getMessage(), 'ERROR');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_settings_express'])) {
    try {
        $fee = isset($_POST['express_fee_value']) ? trim($_POST['express_fee_value']) : '';
        $pix = isset($_POST['express_pix_key']) ? trim($_POST['express_pix_key']) : '';
        
        // Converter vírgula para ponto e remover espaços
        $fee = str_replace([',', ' '], ['.', ''], $fee);
        
        if ($fee === '' || !is_numeric($fee)) {
            throw new Exception('Informe um valor numérico válido para a taxa.');
        }
        if ($pix === '') {
            throw new Exception('Informe a chave PIX.');
        }

        // Persistir em config.json via helpers de config
        $feeFloat = (float)$fee;
        $savedFee = setDynamicConfig('EXPRESS_FEE_VALUE', $feeFloat);
        $savedPix = setDynamicConfig('EXPRESS_PIX_KEY', $pix);

        if (!$savedFee || !$savedPix) {
            throw new Exception('Erro ao salvar configurações. Verifique permissões do arquivo config.json.');
        }

        writeLog("Configurações de entrega expressa atualizadas: Valor={$feeFloat}, PIX={$pix}", 'INFO');
        
        // Limpar qualquer cache do opcode
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        // Forçar releitura do arquivo
        clearstatcache(true, __DIR__ . '/config_custom.json');
        
        // Recarregar valores do config.json após salvar
        $currentFee = getDynamicConfig('EXPRESS_FEE_VALUE', getConfig('EXPRESS_FEE_VALUE', 29.90));
        $currentPix = getDynamicConfig('EXPRESS_PIX_KEY', getConfig('EXPRESS_PIX_KEY', 'pix@exemplo.com'));
        
        // Verificar se realmente salvou
        if ($currentFee == $feeFloat && $currentPix == $pix) {
            $message = "✅ Configurações salvas e verificadas com sucesso!\n\nValor: R$ " . number_format($feeFloat, 2, ',', '') . "\nPIX: $pix";
        } else {
            $message = "⚠️ Configurações salvas, mas houve um problema na verificação. Recarregue a página.";
        }
        $type = 'success';
    } catch (Exception $e) {
        $message = $e->getMessage();
        $type = 'error';
        writeLog("Erro ao salvar configurações de entrega expressa: " . $e->getMessage(), 'ERROR');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Configurações - Entrega Expressa</title>
    <style>
        body { background:#0b0b0b; color:#fff; font-family: Arial, sans-serif; margin:0; padding:20px; }
        .container { max-width: 720px; margin: 0 auto; background:#161616; padding:24px; border-radius:12px; border:1px solid #2a2a2a; }
        h1 { margin-top:0; }
        .form-group { margin-bottom: 16px; }
        label { display:block; margin-bottom:6px; color:#ddd; }
        input[type="text"], input[type="number"] { width:100%; padding:12px; border-radius:8px; border:1px solid #333; background:#0f0f0f; color:#fff; }
        .actions { display:flex; gap:12px; }
        button { padding:12px 18px; border:none; border-radius:8px; cursor:pointer; font-weight:700; color:#fff; background:linear-gradient(90deg,#ff0000,#ff6600); }
        a { color:#fff; text-decoration:none; }
        .msg { padding:12px; border-radius:8px; margin-bottom:16px; }
        .success { background:#0a2915; border:1px solid #1a6b2d; }
        .error { background:#2a1111; border:1px solid #6b1a1a; }
        .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
        .topbar a { background:#252525; padding:8px 12px; border-radius:8px; border:1px solid #333 }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <meta name="robots" content="noindex,nofollow" />
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
</head>
<body>
    <div class="container">
        <div class="topbar">
            <h1>⚙️ Configurações</h1>
            <a href="admin.php">⟵ Voltar ao painel</a>
        </div>
        <?php if ($message): ?>
            <div class="msg <?= $type ?>"><?= nl2br(htmlspecialchars($message)) ?></div>
        <?php endif; ?>
        
        <!-- Configurações do Formulário de Pedidos -->
        <div style="margin-bottom: 30px; padding: 20px; background: #1a1a1a; border-radius: 12px; border: 1px solid #F59E0B;">
            <h2 style="margin-top: 0; color: #F59E0B; font-size: 1.2rem;">📦 Formulário de Pedidos</h2>
            <p style="color: #888; margin-bottom: 16px; font-size: 0.9rem;">
                Configurações da página <a href="pedido.php" target="_blank" style="color: #F59E0B;">pedido.php</a> - Quando o cliente preenche o endereço
            </p>
            <form method="POST">
                <div class="form-group">
                    <label for="pedido_pix_key">Chave PIX (enviada no WhatsApp)</label>
                    <input type="text" name="pedido_pix_key" id="pedido_pix_key" value="<?= htmlspecialchars($pedidoPixKey) ?>" placeholder="CPF, Email, Telefone ou Chave aleatória">
                </div>
                <p style="color: #666; font-size: 0.85rem; margin-bottom: 12px;">
                    💡 O valor do produto <strong>não é enviado</strong> automaticamente. Você envia manualmente pelo WhatsApp.
                </p>
                <div class="actions">
                    <button type="submit" name="salvar_settings_pedido" style="background: linear-gradient(90deg, #F59E0B, #D97706);">Salvar Configurações</button>
                </div>
            </form>
        </div>
        
        <!-- Configurações de Entrega Expressa -->
        <div style="padding: 20px; background: #1a1a1a; border-radius: 12px; border: 1px solid #ff3333;">
            <h2 style="margin-top: 0; color: #ff3333; font-size: 1.2rem;">🚀 Entrega Expressa</h2>
            <p style="color: #888; margin-bottom: 16px; font-size: 0.9rem;">
                Taxa cobrada para upgrade de entrega expressa
            </p>
            <form method="POST">
                <div class="form-group">
                    <label for="express_fee_value">Valor da taxa (R$)</label>
                    <input type="text" name="express_fee_value" id="express_fee_value" value="<?= htmlspecialchars(number_format((float)$currentFee, 2, ',', '')) ?>" inputmode="decimal" required>
                </div>
                <div class="form-group">
                    <label for="express_pix_key">Chave PIX (Entrega Expressa)</label>
                    <input type="text" name="express_pix_key" id="express_pix_key" value="<?= htmlspecialchars($currentPix) ?>" required>
                </div>
                <div class="actions">
                    <button type="submit" name="salvar_settings_express">Salvar</button>
                    <a href="index.php" target="_blank">Ver site</a>
                    <a href="debug_config.php" target="_blank" style="background:#333;padding:12px 18px;border-radius:8px;">🔍 Diagnóstico</a>
                </div>
            </form>
        </div>
        
        <p style="margin-top:20px;color:#4ade80;text-align:center;">✅ As configurações são salvas em <code>config_custom.json</code> e <strong>NÃO são sobrescritas</strong> por atualizações do sistema!</p>
    </div>
</body>
</html>


