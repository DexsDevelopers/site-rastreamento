<?php
/**
 * Sistema de Rastreamento Loggi
 * Serviços Especializados de Entrega
 */

// Headers para evitar cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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
$badgeSatisfacao = getHomepageConfig($pdo, 'badge_satisfacao', 'Loggi para você');
$badgeEntregas = getHomepageConfig($pdo, 'badge_entregas', 'Loggi para empresas');
$badgeCidades = getHomepageConfig($pdo, 'badge_cidades', 'Ajudar');

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

// Verificar se há código na URL (GET) e buscar cidade automaticamente
if (isset($_GET['codigo']) && !isset($_POST['codigo'])) {
    $codigoFromUrl = strtoupper(trim(sanitizeInput($_GET['codigo'])));
    if (!empty($codigoFromUrl)) {
        try {
            $sql = "SELECT DISTINCT cidade FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? AND cidade IS NOT NULL AND cidade != '' LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigoFromUrl]);
            $row = $stmt->fetch();
            if ($row && !empty($row['cidade'])) {
                $codigo = $codigoFromUrl;
                $cidade = trim($row['cidade']);
                $autoLoadFromUrl = true;
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
                            $statusAtualTopo = $temTaxa ? "⏳ Aguardando pagamento da taxa" : end($statusList)['status_atual'];
                            $fotoPedido = getRastreioFoto($pdo, $codigo);
                            if ($fotoPedido) {
                                $cacheBuster = @filemtime($fotoPedido['absolute']) ?: time();
                                $fotoPedidoSrc = $fotoPedido['url'] . '?v=' . $cacheBuster;
                            }
                        }
                        else {
                            $erroCidade = "⚠️ A cidade informada não confere com este código!";
                        }
                    }
                    else {
                        $erroCidade = "⏳ Código aguardando liberação no sistema (Horário).";
                    }
                }
                else {
                    $erroCidade = "❌ Código inexistente!";
                }
            }
        }
        catch (PDOException $e) {
        }
    }
}

if (isset($_POST['codigo']) && isset($_POST['cidade'])) {
    $codigo = strtoupper(trim(sanitizeInput($_POST['codigo'])));
    $cidade = trim(sanitizeInput($_POST['cidade']));
    if (empty($codigo) || empty($cidade)) {
        $erroCidade = "❌ Código e cidade são obrigatórios!";
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
                        $statusAtualTopo = $temTaxa ? "⏳ Aguardando pagamento da taxa" : end($statusList)['status_atual'];
                        $fotoPedido = getRastreioFoto($pdo, $codigo);
                        if ($fotoPedido) {
                            $cacheBuster = @filemtime($fotoPedido['absolute']) ?: time();
                            $fotoPedidoSrc = $fotoPedido['url'] . '?v=' . $cacheBuster;
                        }
                    }
                    else {
                        $erroCidade = "⚠️ A cidade informada não confere com este código!";
                    }
                }
                else {
                    $erroCidade = "⏳ Código aguardando liberação no sistema (Horário).";
                }
            }
            else {
                $erroCidade = "❌ Código inexistente!";
            }
        }
        catch (PDOException $e) {
            $erroCidade = "❌ Erro interno. Tente novamente.";
        }
    }
}

// Resposta AJAX
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: text/html; charset=UTF-8');
    if (!empty($erroCidade)) {
        echo '<div class="results-container" style="background:white; padding:2rem; border-radius:24px; text-align:center; box-shadow:0 20px 40px rgba(0,0,0,0.1);"><div class="erro" style="color:#ef4444; font-weight:700;">' . $erroCidade . '</div></div>';
        exit;
    }
    if (!empty($statusList)) {
?>
<div class="results-container">
    <div class="results-card animate-fade-in"
        style="background:white; padding:3rem; border-radius:24px; box-shadow:0 20px 40px rgba(0,0,0,0.1); color: var(--text-main);">
        <div class="status-header" style="text-align:center; margin-bottom:2rem;">
            <span class="status-icon" style="font-size:3rem; display:block; margin-bottom:1rem;">📦</span>
            <h3 style="font-size:2rem; margin-bottom:0.5rem; font-weight:800;">
                <?= htmlspecialchars($statusAtualTopo)?>
            </h3>
            <p style="color:var(--text-muted);">
                <?= htmlspecialchars($cidade)?>
            </p>
        </div>
        <div class="timeline" style="max-width:600px; margin: 0 auto;">
            <?php foreach ($statusList as $index => $etapa):
            $isFirst = $index === 0;
            $activeClass = $isFirst ? 'active' : '';
?>
            <div class="timeline-item <?= $activeClass?>"
                style="padding-left:30px; border-left:2px solid #EEE; position:relative; margin-bottom:2rem;">
                <div class="timeline-marker"
                    style="position:absolute; left:-7px; top:5px; width:12px; height:12px; border-radius:50%; background:<?= $isFirst ? 'var(--primary)' : '#CCC'?>;">
                </div>
                <div class="timeline-content">
                    <h4 style="font-weight:800; color:var(--text-main);">
                        <?= htmlspecialchars($etapa['titulo'])?>
                    </h4>
                    <p style="font-size:0.9rem; color:var(--text-muted);">
                        <?= htmlspecialchars($etapa['subtitulo'])?>
                    </p>
                    <small style="color:var(--text-dim);"><i class="far fa-clock"></i>
                        <?= date("d/m/Y H:i", strtotime($etapa['data']))?>
                    </small>

                    <?php if (!empty($etapa['taxa_valor']) && !empty($etapa['taxa_pix'])): ?>
                    <div class="pix-box"
                        style="margin-top:1.5rem; background:#F8FAFC; padding:1.5rem; border-radius:12px; border-left:4px solid var(--primary);">
                        <p style="font-weight:700; color:var(--text-main); margin-bottom:0.5rem;">Total a pagar: R$
                            <?= number_format($etapa['taxa_valor'], 2, ',', '.')?>
                        </p>
                        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem;">Copie a chave PIX
                            abaixo para realizar o pagamento:</p>
                        <textarea readonly
                            style="width:100%; height:80px; padding:0.75rem; border:1px solid #EEE; border-radius:8px; font-family:monospace; font-size:0.8rem; margin-bottom:1rem; resize:none;"><?= htmlspecialchars($etapa['taxa_pix'])?></textarea>
                        <button
                            onclick="navigator.clipboard.writeText('<?= htmlspecialchars($etapa['taxa_pix'], ENT_QUOTES)?>')"
                            style="width:100%; padding:0.75rem; background:var(--primary); color:white; border:none; border-radius:8px; font-weight:700; cursor:pointer;">
                            <i class="far fa-copy"></i> Copiar Chave PIX
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
        <div class="photo-proof"
            style="margin-top:2rem; padding-top:2rem; border-top:1px solid #EEE; text-align:center;">
            <p style="font-weight:700; margin-bottom:1rem;"><i class="fas fa-camera"></i> Foto do seu pedido</p>
            <img src="<?= htmlspecialchars($fotoPedidoSrc)?>" alt="Foto do pedido"
                style="max-width:100%; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
        </div>
        <?php
        endif; ?>

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
    <title>Loggi - O rastreio do seu envio é prático</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>

<body>
    <header class="header" id="mainHeader">
        <div class="container nav-container">
            <a href="index.php" class="logo">loggi</a>
            <nav class="nav-links">
                <a href="index.php">Início</a>
                <a href="#para-voce">Para você</a>
                <a href="#para-empresas">Para empresas</a>
                <a href="sobre.php">Sobre</a>
                <a href="login.php" class="btn-login">Entrar</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container hero-box">
            <div class="hero-content reveal-on-scroll">
                <h1>
                    <?= htmlspecialchars($tituloHero)?>
                </h1>
                <p>
                    <?= htmlspecialchars($descricaoHero)?>
                </p>
                <div class="hero-actions">
                    <a href="cadastro_objetivo.php" class="btn-cta primary">Enviar agora</a>
                    <a href="https://www.loggi.com/precos/" class="btn-cta secondary">Calcular frete</a>
                </div>
                <div class="tracking-wrapper">
                    <form method="POST" action="index.php" class="tracking-form" id="trackForm">
                        <input type="text" name="codigo" placeholder="Código de rastreio" maxlength="12"
                            class="tracking-input" required value="<?= htmlspecialchars($codigo)?>">
                        <input type="text" name="cidade" placeholder="Cidade" class="tracking-input" required
                            value="<?= htmlspecialchars($cidade)?>">
                        <button type="submit" class="btn-track">Rastrear</button>
                    </form>
                </div>
            </div>
            <div class="hero-image reveal-on-scroll">
                <div class="image-card">
                    <img src="assets/images/hero_loggi.png" alt="Loggi Logistics">
                </div>
            </div>
        </div>
    </section>

    <div id="ajaxResults" class="container" style="margin-top: -50px; position: relative; z-index: 100;">
        <?php if (!empty($statusList)): ?>
            <div class="results-container">
                <div class="results-card animate-fade-in" style="background:white; padding:3rem; border-radius:24px; box-shadow:0 20px 40px rgba(0,0,0,0.1); color: var(--text-main);">
                    <div class="status-header" style="text-align:center; margin-bottom:2rem;">
                        <span class="status-icon" style="font-size:3rem; display:block; margin-bottom:1rem;">📦</span>
                        <h3 style="font-size:2rem; margin-bottom:0.5rem; font-weight:800;"><?= htmlspecialchars($statusAtualTopo) ?></h3>
                        <p style="color:var(--text-muted);"><?= htmlspecialchars($cidade) ?></p>
                        <?php if ($isExpress): ?>
                            <div style="margin-top:1rem;"><span class="badge" style="background:var(--primary); color:white; padding:0.5rem 1rem; border-radius:100px; font-weight:700;"><i class="fas fa-bolt"></i> Entrega Expressa</span></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="timeline" style="max-width:600px; margin: 0 auto;">
                        <?php foreach ($statusList as $index => $etapa): 
                            $isFirst = $index === 0;
                            $activeClass = $isFirst ? 'active' : '';
                        ?>
                            <div class="timeline-item <?= $activeClass ?>" style="padding-left:30px; border-left:2px solid #EEE; position:relative; margin-bottom:2rem;">
                                <div class="timeline-marker" style="position:absolute; left:-7px; top:5px; width:12px; height:12px; border-radius:50%; background:<?= $isFirst ? 'var(--primary)' : '#CCC' ?>;"></div>
                                <div class="timeline-content">
                                    <h4 style="font-weight:800; color:var(--text-main);"><?= htmlspecialchars($etapa['titulo']) ?></h4>
                                    <p style="font-size:0.9rem; color:var(--text-muted);"><?= htmlspecialchars($etapa['subtitulo']) ?></p>
                                    <small style="color:var(--text-dim);"><i class="far fa-clock"></i> <?= date("d/m/Y H:i", strtotime($etapa['data'])) ?></small>
                                    
                                    <?php if (!empty($etapa['taxa_valor']) && !empty($etapa['taxa_pix'])): ?>
                                        <div class="pix-box" style="margin-top:1.5rem; background:#F8FAFC; padding:1.5rem; border-radius:12px; border-left:4px solid var(--primary);">
                                            <p style="font-weight:700; color:var(--text-main); margin-bottom:0.5rem;">Total a pagar: R$ <?= number_format($etapa['taxa_valor'], 2, ',', '.') ?></p>
                                            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem;">Copie a chave PIX abaixo para realizar o pagamento:</p>
                                            <textarea readonly style="width:100%; height:80px; padding:0.75rem; border:1px solid #EEE; border-radius:8px; font-family:monospace; font-size:0.8rem; margin-bottom:1rem; resize:none;"><?= htmlspecialchars($etapa['taxa_pix']) ?></textarea>
                                            <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($etapa['taxa_pix'], ENT_QUOTES) ?>')" style="width:100%; padding:0.75rem; background:var(--primary); color:white; border:none; border-radius:8px; font-weight:700; cursor:pointer;">
                                                <i class="far fa-copy"></i> Copiar Chave PIX
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($fotoPedido && $fotoPedidoSrc): ?>
                        <div class="photo-proof" style="margin-top:2rem; padding-top:2rem; border-top:1px solid #EEE; text-align:center;">
                            <p style="font-weight:700; margin-bottom:1rem;"><i class="fas fa-camera"></i> Foto do seu pedido</p>
                            <img src="<?= htmlspecialchars($fotoPedidoSrc) ?>" alt="Foto do pedido" style="max-width:100%; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif (!empty($erroCidade)): ?>
            <div class="results-container" style="background:white; padding:2rem; border-radius:24px; text-align:center; box-shadow:0 20px 40px rgba(0,0,0,0.1);">
                <div class="erro" style="color:#ef4444; font-weight:700;"><?= htmlspecialchars($erroCidade) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Navigation Tabs -->
    <div class="container" style="background: white; border-radius: 20px 20px 0 0; margin-top: -30px; position: relative; z-index: 101;">
        <div class="sections-nav">
            <div class="section-tab active" onclick="switchSection('para-voce', this)">Para você</div>
            <div class="section-tab" onclick="switchSection('para-empresas', this)">Para empresas</div>
        </div>
    </div>

    <section id="para-voce" class="marketing-section reveal-on-scroll">
        <div class="container">
            <h2 style="text-align:center; font-size:2.5rem; font-weight:900; margin-bottom:4rem;">A Loggi entrega onde você precisar</h2>
            <div class="marketing-grid">
                <div class="marketing-card">
                    <i class="fas fa-barcode"></i>
                    <h3>Postagem simples</h3>
                    <p>Gere sua etiqueta em segundos e poste em qualquer um dos nossos milhares de pontos parceiros.</p>
                </div>
                <div class="marketing-card">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Rastreio real-time</h3>
                    <p>Notificações automáticas via WhatsApp em cada etapa do processo, da coleta à entrega final.</p>
                </div>
                <div class="marketing-card">
                    <i class="fas fa-bolt"></i>
                    <h3>Entrega prioritária</h3>
                    <p>Sua encomenda voa! Processamento expresso que garante a chegada no destino em tempo recorde.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="para-empresas" class="marketing-section reveal-on-scroll" style="display:none; background:#F8FAFC;">
        <div class="container">
            <h2 style="text-align:center; font-size:2.5rem; font-weight:900; margin-bottom:4rem;">Soluções logísticas para o seu negócio</h2>
            <div class="marketing-grid">
                <div class="marketing-card">
                    <i class="fas fa-boxes"></i>
                    <h3>Coleta programada</h3>
                    <p>Coletamos seus produtos diretamente no seu estoque, otimizando sua operação.</p>
                </div>
                <div class="marketing-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Dashboard completo</h3>
                    <p>Acompanhe métricas, custos e desempenho de todas as suas entregas em um só lugar.</p>
                </div>
                <div class="marketing-card">
                    <i class="fas fa-sync"></i>
                    <h3>Logística Reversa</h3>
                    <p>Trocas e devoluções simplificadas para garantir a melhor experiência ao seu cliente.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="marketing-section reveal-on-scroll">
        <div class="container">
            <div class="marketing-grid" style="text-align:center; gap:2rem;">
                <div>
                    <h3 style="font-size:3rem; font-weight:900; color:var(--primary);"><?= htmlspecialchars($badgeSatisfacao) ?></h3>
                    <p style="font-weight:600; color:var(--text-muted);">Satisfação garantida</p>
                </div>
                <div>
                    <h3 style="font-size:3rem; font-weight:900; color:var(--primary);"><?= htmlspecialchars($badgeEntregas) ?></h3>
                    <p style="font-weight:600; color:var(--text-muted);">Entregas realizadas</p>
                </div>
                <div>
                    <h3 style="font-size:3rem; font-weight:900; color:var(--primary);"><?= htmlspecialchars($badgeCidades) ?></h3>
                    <p style="font-weight:600; color:var(--text-muted);">Cidades atendidas</p>
                </div>
            </div>
        </div>
    </section>

    <section class="marketing-section reveal-on-scroll" style="background:var(--secondary);">
        <div class="container">
            <h2 style="text-align:center; font-size:2.5rem; font-weight:900; margin-bottom:4rem; color:white;">O que dizem nossos clientes</h2>
            <div class="marketing-grid">
                <div class="marketing-card" style="background:rgba(255,255,255,0.05); color:white; border:none;">
                    <p style="font-style:italic; margin-bottom:1.5rem;">"A Loggi transformou a logística da minha empresa. Entregas rápidas e rastreio impecável."</p>
                    <p><strong>- Maria Silva</strong>, E-commerce de Moda</p>
                </div>
                <div class="marketing-card" style="background:rgba(255,255,255,0.05); color:white; border:none;">
                    <p style="font-style:italic; margin-bottom:1.5rem;">"O melhor custo-benefício do mercado. Meus clientes adoram o rastreio via WhatsApp."</p>
                    <p><strong>- João Pereira</strong>, Vendedor Autônomo</p>
                </div>
                <div class="marketing-card" style="background:rgba(255,255,255,0.05); color:white; border:none;">
                    <p style="font-style:italic; margin-bottom:1.5rem;">"Postar encomendas ficou muito mais fácil com os pontos Loggi espalhados pela cidade."</p>
                    <p><strong>- Ana Santos</strong>, Usuária Casual</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h4 class="logo" style="color:var(--primary); margin-bottom:1.5rem; text-transform:lowercase;">loggi</h4>
                    <p>© <?= date('Y') ?> Todos os direitos reservados.</p>
                </div>
                <div class="footer-col">
                    <h4>Serviços</h4>
                    <ul>
                        <li>Para você</li>
                        <li>Para empresas</li>
                        <li>Loggi Pro</li>
                        <li>Loggi Envios</li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Empresa</h4>
                    <ul>
                        <li>Sobre nós</li>
                        <li>Carreiras</li>
                        <li>Blog</li>
                        <li>Ajuda</li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <script>
        function switchSection(targetId, tab) {
            document.querySelectorAll('.marketing-section[id]').forEach(sec => {
                sec.style.display = 'none';
            });
            document.getElementById(targetId).style.display = 'block';
            
            document.querySelectorAll('.section-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
        }

        window.addEventListener('scroll', () => {
            const header = document.getElementById('mainHeader');
            if (window.scrollY > 50) header.classList.add('scrolled');
            else header.classList.remove('scrolled');
        });

        document.getElementById('trackForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('ajax', '1');
            fetch('index.php', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(html => {
                    document.getElementById('ajaxResults').innerHTML = html;
                    window.scrollTo({ top: document.getElementById('ajaxResults').offsetTop - 100, behavior: 'smooth' });
                });
        });

        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('visible'); });
        }, { threshold: 0.1 });
        document.querySelectorAll('.reveal-on-scroll').forEach(el => revealObserver.observe(el));
    </script>
</body>

</html>