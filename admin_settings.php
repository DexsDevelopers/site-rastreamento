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
        
        // Recarregar valores do config.json após salvar
        $currentFee = getDynamicConfig('EXPRESS_FEE_VALUE', getConfig('EXPRESS_FEE_VALUE', 29.90));
        $currentPix = getDynamicConfig('EXPRESS_PIX_KEY', getConfig('EXPRESS_PIX_KEY', 'pix@exemplo.com'));
        
        $message = 'Configurações salvas com sucesso.';
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
            <h1>Configurações — Entrega Expressa</h1>
            <a href="admin.php">⟵ Voltar ao painel</a>
        </div>
        <?php if ($message): ?>
            <div class="msg <?= $type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="express_fee_value">Valor da taxa (R$)</label>
                <input type="text" name="express_fee_value" id="express_fee_value" value="<?= htmlspecialchars(number_format((float)$currentFee, 2, ',', '')) ?>" inputmode="decimal" required>
            </div>
            <div class="form-group">
                <label for="express_pix_key">Chave PIX</label>
                <input type="text" name="express_pix_key" id="express_pix_key" value="<?= htmlspecialchars($currentPix) ?>" required>
            </div>
            <div class="actions">
                <button type="submit" name="salvar_settings_express">Salvar</button>
                <a href="index.php" target="_blank">Ver site</a>
            </div>
        </form>
        <p style="margin-top:16px;color:#aaa;">As configurações são salvas em <code>config.json</code> e entram em vigor imediatamente.</p>
    </div>
</body>
</html>


