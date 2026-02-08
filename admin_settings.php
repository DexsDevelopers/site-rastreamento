<?php
/**
 * Painel Admin - Configurações (Moderno)
 * Loggi
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth_helper.php';

requireLogin();

$message = '';
$type = '';
$currentFee = getDynamicConfig('EXPRESS_FEE_VALUE', getConfig('EXPRESS_FEE_VALUE', 29.90));
$currentPix = getDynamicConfig('EXPRESS_PIX_KEY', getConfig('EXPRESS_PIX_KEY', 'pix@exemplo.com'));
$pedidoPixKey = getDynamicConfig('PEDIDO_PIX_KEY', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['salvar_settings_express'])) {
        try {
            $fee = isset($_POST['express_fee_value']) ? trim($_POST['express_fee_value']) : '';
            $pix = isset($_POST['express_pix_key']) ? trim($_POST['express_pix_key']) : '';

            $fee = str_replace([',', ' '], ['.', ''], $fee);
            if ($fee === '' || !is_numeric($fee))
                throw new Exception('Valor da taxa inválido.');
            if ($pix === '')
                throw new Exception('Chave PIX obrigatória.');

            setDynamicConfig('EXPRESS_FEE_VALUE', (float)$fee);
            setDynamicConfig('EXPRESS_PIX_KEY', $pix);

            $currentFee = (float)$fee;
            $currentPix = $pix;

            $message = "Configurações de Expressa atualizadas!";
            $type = 'success';
        }
        catch (Exception $e) {
            $message = $e->getMessage();
            $type = 'error';
        }
    }
    elseif (isset($_POST['salvar_settings_pedido'])) {
        try {
            $pixPedido = isset($_POST['pedido_pix_key']) ? trim($_POST['pedido_pix_key']) : '';
            setDynamicConfig('PEDIDO_PIX_KEY', $pixPedido);
            $pedidoPixKey = $pixPedido;

            $message = "Configurações de Pedido atualizadas!";
            $type = 'success';
        }
        catch (Exception $e) {
            $message = $e->getMessage();
            $type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações | Loggi Admin</title>
    <meta name="theme-color" content="#0055FF">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <i class="fas fa-shipping-fast"></i> Loggi
                </div>
                <button class="mobile-close-btn" onclick="toggleSidebar()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <nav class="sidebar-menu">
                <div class="menu-label">Principal</div>
                <a href="index.php" class="nav-item"><i class="fas fa-home"></i> Página Inicial</a>
                <a href="admin.php" class="nav-item"><i class="fas fa-chart-pie"></i> Dashboard</a>
                <a href="admin_pedidos_pendentes.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Pedidos
                    Pendentes</a>
                <a href="admin_indicacoes.php" class="nav-item"><i class="fas fa-users"></i> Indicações</a>

                <div class="menu-label">Gestão</div>
                <a href="admin_homepage.php" class="nav-item"><i class="fas fa-pen-to-square"></i> Editar Site</a>
                <a href="admin_bot_config.php" class="nav-item"><i class="fas fa-robot"></i> Configuração Bot</a>
                <a href="admin_mensagens.php" class="nav-item"><i class="fas fa-message"></i> Mensagens WPP</a>

                <div class="menu-label">Configuração</div>
                <a href="admin_settings.php" class="nav-item active"><i class="fas fa-gear"></i> Ajustes Expressa</a>
            </nav>

            <div class="sidebar-footer">
                <a href="admin.php?logout=1" class="nav-item" style="color: var(--primary);"><i
                        class="fas fa-power-off"></i> Sair</a>
            </div>
        </aside>

        <!-- Overlay Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <button class="mobile-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title">
                        <h2>Configurações</h2>
                    </div>
                </div>
            </header>

            <div class="content-body" style="max-width: 800px; margin: 0 auto;">

                <!-- Pedidos Settings -->
                <div class="glass-panel" style="padding: 2rem; margin-bottom: 2rem;">
                    <h3
                        style="color: var(--text-main); margin-bottom: 1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <i class="fas fa-box-open" style="color: var(--warning);"></i> Formulário de Pedidos
                    </h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Chave PIX (Para pagamento do produto)</label>
                            <input type="text" name="pedido_pix_key" class="form-control"
                                value="<?= htmlspecialchars($pedidoPixKey)?>" placeholder="CNPJ, Email ou Telefone">
                            <small class="text-muted" style="display:block; margin-top:0.5rem;">Esta chave será enviada
                                ao cliente após o pedido.</small>
                        </div>
                        <div style="text-align:right;">
                            <button type="submit" name="salvar_settings_pedido" class="btn btn-primary">Salvar
                                Configuração</button>
                        </div>
                    </form>
                </div>

                <!-- Express Settings -->
                <div class="glass-panel" style="padding: 2rem;">
                    <h3
                        style="color: var(--text-main); margin-bottom: 1.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <i class="fas fa-rocket" style="color: var(--primary);"></i> Entrega Expressa
                    </h3>
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Valor da Taxa (R$)</label>
                                <input type="text" name="express_fee_value" class="form-control"
                                    value="<?= number_format($currentFee, 2, ',', '')?>">
                            </div>
                            <div class="form-group">
                                <label>Chave PIX (Para taxa expressa)</label>
                                <input type="text" name="express_pix_key" class="form-control"
                                    value="<?= htmlspecialchars($currentPix)?>">
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <button type="submit" name="salvar_settings_express" class="btn btn-primary">Atualizar
                                Taxa</button>
                        </div>
                    </form>
                </div>

            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) {
                const isActive = sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active', isActive);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay) {
                overlay.addEventListener('click', toggleSidebar);
            }

            <?php if ($message): ?>
                Swal.fire({
                    icon: '<?= $type?>',
                    title: '<?= $type == "success" ? "Sucesso" : "Erro"?>',
                    text: '<?= addslashes($message)?>',
                    background: '#1a1a1a',
                    color: '#fff',
                    confirmButtonColor: '#0055FF',
                    timer: 2000
                });
            <?php
endif; ?>
        });
    </script>
</body>

</html>