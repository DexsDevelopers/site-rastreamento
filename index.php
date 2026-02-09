<?php
/**
 * Sistema de Rastreamento Loggi
 * Serviços Especializados de Entrega
 */

// Headers para evitar cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Debug - Remover em produção se necessário, mas útil agora
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir configurações
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/rastreio_media.php';

// Verificar conexão com banco PRIMEIRO
if (!isset($pdo) || $pdo === null) {
    die("❌ Erro: Não foi possível conectar ao banco de dados. Verifique as configurações em includes/db_connect.php");
}

// Função para obter configuração da homepage
function getHomepageConfig($pdo, $chave, $default = '')
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'homepage_config'");
        if ($stmt->rowCount() === 0) {
            return $default;
        }
        $result = fetchOne($pdo, "SELECT valor FROM homepage_config WHERE chave = ?", [$chave]);
        return $result && isset($result['valor']) ? $result['valor'] : $default;
    }
    catch (Exception $e) {
        return $default;
    }
}

// Carregar configurações da homepage
$nomeEmpresa = getHomepageConfig($pdo, 'nome_empresa', 'Loggi');
$tituloHero = getHomepageConfig($pdo, 'titulo_hero', 'O rastreio do seu envio é prático');
$descricaoHero = getHomepageConfig($pdo, 'descricao_hero', 'Acompanhe seu pedido em tempo real com a Loggi. Frete grátis para todo o Brasil.');

$codigo = $cidade = "";
$statusList = [];
$erroCidade = "";
$statusAtualTopo = "";
$temTaxa = false;
$tempoLimite = 24;
$fotoPedido = null;
$fotoPedidoSrc = null;
$expressValor = getDynamicConfig('EXPRESS_FEE_VALUE', 29.90);
$isExpress = false;
$autoLoadFromUrl = false;

// Auto-detecção via URL
if (isset($_GET['codigo']) && empty($_POST['ajax'])) {
    $codigo = strtoupper(trim($_GET['codigo']));

    // Se cidade vier na URL, usa ela
    if (isset($_GET['cidade']) && !empty($_GET['cidade'])) {
        $cidade = trim($_GET['cidade']);
        $autoLoadFromUrl = true;
    }
    else {
        // Tenta buscar cidade no banco automaticamente
        try {
            $stmt = $pdo->prepare("SELECT cidade FROM rastreios_status WHERE codigo = ? AND cidade IS NOT NULL AND cidade != '' ORDER BY data DESC LIMIT 1");
            $stmt->execute([$codigo]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                $cidade = $res['cidade'];
                $autoLoadFromUrl = true;
            }
        }
        catch (Exception $e) {
        // Silencioso
        }
    }
}

// Resposta AJAX
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: text/html; charset=UTF-8');

    $codigo = strtoupper(trim(sanitizeInput($_POST['codigo'])));
    $cidade = trim(sanitizeInput($_POST['cidade']));

    if (empty($codigo) || empty($cidade)) {
        $erroCidade = "Código e cidade são obrigatórios!";
    }
    else {
        try {
            $sql = "SELECT * FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo]);
            $results = $stmt->fetchAll();

            if (!empty($results)) {
                $rows = [];
                foreach ($results as $row) {
                    if (strtotime($row['data']) <= time()) {
                        $rows[] = $row;
                        if (strpos(strtolower($row['titulo']), 'distribuição') !== false)
                            break;
                    }
                }

                if (!empty($rows)) {
                    if (normalizeString($rows[0]['cidade']) === normalizeString($cidade)) {
                        $statusList = $rows;
                        foreach ($rows as $r) {
                            if (!empty($r['taxa_valor']) && !empty($r['taxa_pix']))
                                $temTaxa = true;
                            if (!empty($r['prioridade']))
                                $isExpress = true;
                        }
                        $statusAtualTopo = $temTaxa ? "Aguardando pagamento" : end($statusList)['status_atual'];
                        $fotoPedido = getRastreioFoto($pdo, $codigo);
                        if ($fotoPedido) {
                            $cacheBuster = @filemtime($fotoPedido['absolute']) ?: time();
                            $fotoPedidoSrc = $fotoPedido['url'] . '?v=' . $cacheBuster;
                        }
                    }
                    else {
                        $erroCidade = "A cidade informada não confere!";
                    }
                }
                else {
                    $erroCidade = "Código aguardando liberação.";
                }
            }
            else {
                $erroCidade = "Código inexistente!";
            }
        }
        catch (PDOException $e) {
            $erroCidade = "Erro interno: " . $e->getMessage();
        }
    }

    if (!empty($erroCidade)) {
        echo '<div class="results-container animate-fade-in"><div class="results-card" style="text-align:center; padding: 4rem; border-color: var(--error);"><i class="fas fa-exclamation-circle" style="font-size: 3rem; color: var(--error); margin-bottom: 1rem; display: block;"></i><h4 style="font-weight:900; color: var(--error);">' . $erroCidade . '</h4></div></div>';
        exit;
    }
    if (!empty($statusList)) {
?>
<div class="results-container">
    <div class="results-card animate-fade-in">
        <div class="status-header">
            <div class="card-icon">
                <?php
        $statusIcon = 'fa-box-open';
        $statusLower = strtolower($statusAtualTopo);
        if (strpos($statusLower, 'saiu') !== false || strpos($statusLower, 'entrega') !== false)
            $statusIcon = 'fa-truck-fast';
        elseif (strpos($statusLower, 'postado') !== false || strpos($statusLower, 'coletado') !== false)
            $statusIcon = 'fa-box';
        elseif (strpos($statusLower, 'entregue') !== false)
            $statusIcon = 'fa-house-chimney-check';
        elseif (strpos($statusLower, 'aguardando') !== false || strpos($statusLower, 'taxa') !== false || strpos($statusLower, 'pagamento') !== false)
            $statusIcon = 'fa-clock-rotate-left';
?>
                <i class="fas <?= $statusIcon?>"></i>
            </div>
            <h3>
                <?= htmlspecialchars($statusAtualTopo)?>
            </h3>
            <p><i class="fas fa-map-marker-alt"></i>
                <?= htmlspecialchars($cidade)?>
            </p>
        </div>

        <div class="timeline">
            <?php foreach ($statusList as $index => $etapa):
            $isLast = $index === count($statusList) - 1; // Last one is the most recent
            $activeClass = $isLast ? 'active' : '';
            $statusAttr = strtolower($etapa['titulo']);
?>
            <div class="timeline-item <?= $activeClass?>" data-status="<?= $statusAttr?>">
                <div class="timeline-marker"></div>
                <div class="timeline-content">
                    <h4>
                        <?= htmlspecialchars($etapa['titulo'])?>
                    </h4>
                    <p>
                        <?= htmlspecialchars($etapa['subtitulo'])?>
                    </p>
                    <small>
                        <?= date("d/m/Y H:i", strtotime($etapa['data']))?>
                    </small>

                    <?php if (!empty($etapa['taxa_valor']) && !empty($etapa['taxa_pix'])): ?>
                    <div class="pix-box reveal-on-scroll visible"
                        style="margin-top:2rem; background:var(--slate-50); padding:2rem; border-radius:24px; border: 1px solid var(--slate-200);">
                        <p style="font-weight:900; color:var(--secondary); margin-bottom:1rem; font-size: 1.25rem;">
                            Taxa de liberação: <span style="color: var(--primary);">R$
                                <?= number_format($etapa['taxa_valor'], 2, ',', '.')?>
                            </span>
                        </p>
                        <p style="font-size:1rem; color:var(--slate-600); margin-bottom:1.5rem; font-weight: 500;">
                            Realize o pagamento por PIX para liberar o envio.</p>
                        <div
                            style="background: white; padding: 1.25rem; border-radius: 16px; border: 2px dashed var(--slate-200); margin-bottom: 1.5rem;">
                            <code
                                style="word-break: break-all; font-family: monospace; font-size: 0.9rem; color: var(--secondary); font-weight: 700;"><?= htmlspecialchars($etapa['taxa_pix'])?></code>
                        </div>
                        <button
                            onclick="navigator.clipboard.writeText('<?= htmlspecialchars($etapa['taxa_pix'], ENT_QUOTES)?>'); alert('Chave PIX copiada!');"
                            style="width:100%; padding:1.25rem; background:var(--primary); color:white; border:none; border-radius:16px; font-weight:900; cursor:pointer; font-size: 1.1rem; transition: all 0.3s;"
                            class="cursor-pointer">
                            Copiar Chave PIX
                        </button>
                    </div>
                    <?php
            endif; ?>
                </div>
            </div>
            <?php
        endforeach; ?>
        </div>

        <?php if ($fotoPedido && $fotoPedidoSrc): ?>
        <div class="photo-proof-card" style="margin-top:4rem;">
            <p
                style="font-weight:900; margin-bottom:2rem; font-size: 1.3rem; color: var(--secondary); text-align: center;">
                <i class="fas fa-camera"></i> Registro Fotográfico do Objeto
            </p>
            <div class="image-card"
                style="transform: none; max-width: 600px; margin: 0 auto; padding: 1rem; border-radius: 32px;">
                <img src="<?= htmlspecialchars($fotoPedidoSrc)?>" alt="Foto do pedido" style="border-radius: 20px;">
            </div>
        </div>
        <?php
        endif; ?>

        <!-- Botão Acelerar Entrega -->
        <div
            style="margin-top: 2rem; margin-bottom: 2rem; text-align: center; border-top: 2px dashed #cbd5e1; padding-top: 2rem; padding-bottom: 1rem;">
            <button id="btn-express-upsell" onclick="openModal('modalExpressIntro')" class="express-btn express-confirm"
                style="background-color: #0096ff !important; color: white !important; font-size: 1.1rem; padding: 1rem 1.5rem; width: 90%; max-width: 400px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 8px 20px rgba(0, 150, 255, 0.4); text-transform: uppercase; letter-spacing: 0.5px; border-radius: 16px; border: none; font-weight: 800; cursor: pointer;">
                <i class="fas fa-bolt"></i> Acelerar por R$
                <?= number_format($expressValor, 2, ',', '.')?>
            </button>
            <p style="margin-top: 1rem; color: #64748b; font-size: 0.9rem; font-weight: 600;">Receba em até 3 dias úteis
            </p>
        </div>

    </div>
</div>
<?php
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loggi - Rastreamento Inteligente de Encomendas</title>
    <meta name="description"
        content="Acompanhe seu pedido em tempo real com a Loggi. Tecnologia de ponta para entregas rápidas e seguras em todo o Brasil.">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.png">
</head>

<body>
    <header class="header" id="mainHeader">
        <div class="container nav-container">
            <a href="index.php" class="logo">loggi</a>

            <div class="mobile-menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </div>

            <nav class="nav-links" id="navLinks">
                <a href="index.php">Início</a>
                <a href="#para-voce">Para você</a>
                <a href="#para-empresas">Para empresas</a>
                <a href="sobre.php">Sobre</a>
                <a href="login_cliente.php" class="btn-login">Entrar</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container hero-box">
            <div class="hero-content reveal-on-scroll">
                <h1 class="premium-title">
                    <?= htmlspecialchars($tituloHero)?>
                </h1>
                <p class="hero-desc">
                    <?= htmlspecialchars($descricaoHero)?>
                </p>
                <div class="hero-actions">
                    <a href="cadastro_objetivo.php" class="btn-cta primary">
                        <i class="fas fa-box"></i> Enviar agora
                    </a>
                    <a href="calcular_frete.php" class="btn-cta secondary">
                        <i class="fas fa-calculator"></i> Calcular frete
                    </a>
                </div>
                <div class="tracking-wrapper">
                    <form method="POST" action="index.php" class="tracking-form" id="trackForm">
                        <div class="input-group">
                            <i class="fas fa-barcode"></i>
                            <input type="text" name="codigo" placeholder="Código de rastreio" maxlength="12"
                                class="tracking-input" value="<?= htmlspecialchars($codigo)?>" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-city"></i>
                            <input type="text" name="cidade" placeholder="Sua cidade" class="tracking-input"
                                value="<?= htmlspecialchars($cidade)?>" required>
                        </div>
                        <button type="submit" class="btn-track cursor-pointer">
                            <i class="fas fa-search-location"></i> Rastrear agora
                        </button>
                    </form>
                    <?php if ($autoLoadFromUrl): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const trackForm = document.getElementById('trackForm');
                            if (trackForm) {
                                // Pequeno delay para garantir que o JS principal carregou
                                setTimeout(() => {
                                    trackForm.dispatchEvent(new Event('submit'));
                                }, 500);
                            }
                        });
                    </script>
                    <?php
endif; ?>
                </div>
            </div>
            <div class="hero-image reveal-on-scroll">
                <div class="image-card">
                    <img src="assets/images/hero_loggi.png" alt="Loggi Logistics Operations">
                </div>
            </div>
        </div>
    </section>

    <div id="ajaxResults" class="container results-anchor">
        <!-- Results injected via AJAX -->
    </div>

    <div class="container tabs-container">
        <div class="sections-nav">
            <div class="section-tab active cursor-pointer" onclick="switchSection('para-voce', this)">Para você</div>
            <div class="section-tab cursor-pointer" onclick="switchSection('para-empresas', this)">Para empresas</div>
        </div>
    </div>

    <section id="para-voce" class="marketing-section reveal-on-scroll">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">A Loggi entrega onde você precisar</h2>
                <p class="section-subtitle">A maior malha logística privada do Brasil à sua disposição.</p>
            </div>
            <div class="marketing-grid">
                <div class="marketing-card premium">
                    <div class="card-icon"><i class="fas fa-qrcode"></i></div>
                    <h3>Postagem simples</h3>
                    <p>Gere sua etiqueta em poucos cliques e poste em qualquer ponto parceiro próximo a você.</p>
                    <a href="cadastro_objetivo.php" class="card-link">Começar agora <i
                            class="fas fa-arrow-right"></i></a>
                </div>
                <div class="marketing-card premium">
                    <div class="card-icon"><i class="fas fa-satellite-dish"></i></div>
                    <h3>Monitoramento GPS</h3>
                    <p>Acompanhe cada curva da sua encomenda com tecnologia de rastreio via satélite em tempo real.</p>
                    <a href="#" class="card-link">Ver como funciona <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="marketing-card premium">
                    <div class="card-icon"><i class="fas fa-shipping-fast"></i></div>
                    <h3>Loggi Express</h3>
                    <p>Sua encomenda priorizada em nossa malha expressa para chegar ao destino em tempo recorde.</p>
                    <a href="solicitar_express_checkout.php" class="card-link">Pedir urgência <i
                            class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </section>

    <section id="para-empresas" class="marketing-section reveal-on-scroll"
        style="display:none; background:var(--slate-50);">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Logística inteligente para negócios</h2>
                <p class="section-subtitle">Potencialize suas vendas com a malha logística que mais cresce no país.</p>
            </div>
            <div class="marketing-grid">
                <div class="marketing-card premium">
                    <div class="card-icon"><i class="fas fa-warehouse"></i></div>
                    <h3>Coleta loggi</h3>
                    <p>Equipe dedicada para coletar seus envios diretamente no seu centro de distribuição.</p>
                </div>
                <div class="marketing-card premium">
                    <div class="card-icon"><i class="fas fa-project-diagram"></i></div>
                    <h3>API de Integração</h3>
                    <p>Conecte seu e-commerce diretamente com nosso sistema para automação total de fretes.</p>
                </div>
                <div class="marketing-card premium">
                    <div class="card-icon"><i class="fas fa-undo-alt"></i></div>
                    <h3>Reversa Facilitada</h3>
                    <p>Gestão completa de trocas e devoluções para encantar seus clientes no pós-venda.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="marketing-section reveal-on-scroll proof-section">
        <div class="container">
            <div class="proof-grid">
                <div class="proof-card">
                    <div class="proof-icon" style="font-size: 3rem; color: var(--accent); margin-bottom: 1rem;">
                        <i class="fas fa-smile-beam"></i>
                    </div>
                    <div class="proof-value">4.8/5</div>
                    <div class="proof-label">Satisfação dos Clientes</div>
                </div>
                <div class="proof-card highlight">
                    <div class="proof-icon" style="font-size: 4rem; color: var(--primary); margin-bottom: 1rem;">
                        <i class="fas fa-dolly-flatbed"></i>
                    </div>
                    <div class="proof-value">10M+</div>
                    <div class="proof-label">Entregas Realizadas</div>
                </div>
                <div class="proof-card">
                    <div class="proof-icon" style="font-size: 3rem; color: var(--secondary); margin-bottom: 1rem;">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <div class="proof-value">4.5k+</div>
                    <div class="proof-label">Cidades Atendidas</div>
                </div>
            </div>
        </div>
    </section>

    <section class="marketing-section reveal-on-scroll testimonials-section" style="background:var(--secondary);">
        <div class="container">
            <h2 class="section-title light" style="text-align: center; margin-bottom: 6rem;">Confiança de quem usa</h2>
            <div class="marketing-grid">
                <div class="testimonial-card">
                    <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                            class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p>"A tecnologia da Loggi é incomparável. Consigo gerir todos os meus envios com uma facilidade que
                        nunca tive antes."</p>
                    <div class="client-info">
                        <strong>Ricardo Mendes</strong>
                        <span>CEO, TechCommerce</span>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                            class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p>"O suporte é excelente e as entregas sempre dentro do prazo. Meus clientes estão muito mais
                        satisfeitos."</p>
                    <div class="client-info">
                        <strong>Juliana Costa</strong>
                        <span>Gerente Logística, ModaBR</span>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                            class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p>"Postar meus pacotes ficou 10x mais rápido com os Pontos Loggi. Recomendo para todos os
                        vendedores."</p>
                    <div class="client-info">
                        <strong>Felipe Silva</strong>
                        <span>Vendedor Platinum</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col brand">
                    <a href="index.php" class="logo">loggi</a>
                    <p>Reinventando a logística brasileira através de tecnologia própria e excelência operacional.</p>
                    <div class="social-links">
                        <a href="#" class="cursor-pointer"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="cursor-pointer"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="cursor-pointer"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Soluções</h4>
                    <ul>
                        <li><a href="#">Loggi para você</a></li>
                        <li><a href="#">Loggi para empresas</a></li>
                        <li><a href="#">E-commerce API</a></li>
                        <li><a href="#">Loggi Pro</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Sobre</h4>
                    <ul>
                        <li><a href="sobre.php">Nossa História</a></li>
                        <li><a href="#">Carreiras</a></li>
                        <li><a href="#">Central de Ajuda</a></li>
                        <li><a href="#">Termos de Uso</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>©
                    <?= date('Y')?> Loggi Tecnologia LTDA.
                </p>
                <p>Feito com <i class="fas fa-heart" style="color: var(--error);"></i> para o Brasil</p>
            </div>
        </div>
    </footer>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const navLinks = document.getElementById('navLinks');

        if (menuToggle && navLinks) {
            menuToggle.addEventListener('click', () => {
                navLinks.classList.toggle('active');
                const icon = menuToggle.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-bars');
                    icon.classList.toggle('fa-times');
                }
            });
        }

        function switchSection(targetId, tab) {
            document.querySelectorAll('.marketing-section[id]').forEach(sec => {
                sec.style.display = 'none';
            });
            const target = document.getElementById(targetId);
            if (target) target.style.display = 'block';

            document.querySelectorAll('.section-tab').forEach(t => t.classList.remove('active'));
            if (tab) tab.classList.add('active');
        }

        window.addEventListener('scroll', () => {
            const header = document.getElementById('mainHeader');
            if (header) {
                if (window.scrollY > 50) header.classList.add('scrolled');
                else header.classList.remove('scrolled');
            }
        });

        const trackForm = document.getElementById('trackForm');
        if (trackForm) {
            trackForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const btn = this.querySelector('button');
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
                btn.disabled = true;

                const formData = new FormData(this);
                formData.append('ajax', '1');

                fetch('index.php', { method: 'POST', body: formData })
                    .then(r => r.text())
                    .then(html => {
                        const resultsDiv = document.getElementById('ajaxResults');
                        if (resultsDiv) {
                            resultsDiv.innerHTML = html;
                            window.scrollTo({ top: resultsDiv.offsetTop - 120, behavior: 'smooth' });
                            // Re-run animation observer for new content
                            document.querySelectorAll('#ajaxResults .reveal-on-scroll').forEach(el => revealObserver.observe(el));
                        }
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                    })
                    .catch(() => {
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                    });
            });
        }

        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.reveal-on-scroll').forEach(el => revealObserver.observe(el));

        // Safety Fallback
        setTimeout(() => {
            document.querySelectorAll('.reveal-on-scroll').forEach(el => el.classList.add('visible'));
        }, 1000);
    </script>
    <!-- Modais Express -->
    <?php
$expressPixKey = getDynamicConfig('EXPRESS_PIX_KEY', 'financeiro@transloggi.site');
// Se $expressValor não estiver definido aqui (pois está no topo), pegar de novo
$expressValorModal = getDynamicConfig('EXPRESS_FEE_VALUE', 29.90);
?>
    <style>
        .express-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s;
        }

        .express-modal-content {
            background: white;
            padding: 2.5rem;
            border-radius: 24px;
            width: 90%;
            max-width: 420px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            transform: scale(0.95);
            animation: popIn 0.3s forwards;
            position: relative;
        }

        @keyframes popIn {
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .express-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .express-btn {
            padding: 1rem 1.5rem;
            border-radius: 16px;
            border: none;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.2s;
            font-size: 1rem;
        }

        .express-btn:active {
            transform: scale(0.95);
        }

        .express-confirm {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.4);
        }

        .express-cancel {
            background: var(--slate-100);
            color: var(--slate-600);
        }

        .pix-code-box {
            background: var(--slate-50);
            padding: 1.5rem;
            border-radius: 16px;
            border: 2px dashed var(--slate-200);
            margin: 1.5rem 0;
            word-break: break-all;
            font-family: monospace;
            font-weight: bold;
            color: var(--secondary);
        }
    </style>

    <div id="modalExpressIntro" class="express-modal-overlay">
        <div class="express-modal-content">
            <div
                style="width:80px; height:80px; background:var(--primary-light); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem;">
                <i class="fas fa-bolt" style="font-size: 2.5rem; color: var(--primary);"></i>
            </div>
            <h3 style="font-size: 1.8rem; margin-bottom: 1rem; color: var(--secondary);">Acelerar Entrega?</h3>
            <p style="color: var(--slate-500); line-height: 1.6;">Receba sua encomenda em até <strong>3 dias
                    úteis</strong> utilizando nossa malha expressa prioritária.</p>
            <div class="express-actions">
                <button onclick="closeModal('modalExpressIntro')" class="express-btn express-cancel">Agora não</button>
                <button onclick="closeModal('modalExpressIntro'); openModal('modalExpressPix')"
                    class="express-btn express-confirm">Sim, quero acelerar</button>
            </div>
        </div>
    </div>

    <div id="modalExpressPix" class="express-modal-overlay">
        <div class="express-modal-content">
            <h3 style="font-size: 1.5rem; color: var(--secondary); margin-bottom: 0.5rem;">Pagamento Express</h3>
            <p style="color: var(--slate-500);">Taxa única de liberação prioritária</p>

            <div style="font-size: 2.5rem; font-weight: 900; color: var(--primary); margin: 1.5rem 0;">
                R$
                <?= number_format($expressValorModal, 2, ',', '.')?>
            </div>

            <p style="font-size: 0.9rem; color: var(--slate-400); margin-bottom: 1rem;">Copie a chave PIX abaixo:</p>

            <div class="pix-code-box" id="pixCodeText">
                <?= htmlspecialchars($expressPixKey)?>
            </div>

            <button onclick="copyExpressPix()" class="express-btn express-confirm"
                style="width: 100%; margin-bottom: 1rem;">
                <i class="far fa-copy"></i> Copiar Chave PIX
            </button>
            <button onclick="closeModal('modalExpressPix')" class="express-btn express-cancel"
                style="background:transparent;">Fechar</button>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        function copyExpressPix() {
            const code = document.getElementById('pixCodeText').innerText.trim();
            navigator.clipboard.writeText(code).then(() => {
                alert('Chave PIX copiada com sucesso!');
            });
        }
    </script>
</body>