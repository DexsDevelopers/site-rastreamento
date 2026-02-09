<?php
// Headers para evitar cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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
        // Verificar se a tabela existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'homepage_config'");
        if ($stmt->rowCount() === 0) {
            return $default; // Tabela não existe ainda
        }

        $result = fetchOne($pdo, "SELECT valor FROM homepage_config WHERE chave = ?", [$chave]);
        return $result && isset($result['valor']) ? $result['valor'] : $default;
    }
    catch (Exception $e) {
        // Em caso de erro, retornar valor padrão
        return $default;
    }
}

// Carregar configurações da homepage
$nomeEmpresa = getHomepageConfig($pdo, 'nome_empresa', 'Loggi');
$badgeSatisfacao = getHomepageConfig($pdo, 'badge_satisfacao', '98.7% de Satisfação');
$badgeEntregas = getHomepageConfig($pdo, 'badge_entregas', '5.247 Entregas');
$badgeCidades = getHomepageConfig($pdo, 'badge_cidades', '247 Cidades');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre Nós - Loggi | Excelência em Logística Inteligente</title>
    <meta name="description"
        content="Conheça a história e a tecnologia por trás da Loggi. Reinventando a logística brasileira para conectar pessoas e negócios.">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
</head>

<body>
    <header class="header" id="mainHeader">
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <img src="assets/images/logo.png" alt="Loggi"
                    style="height: 42px; width: auto; vertical-align: middle; margin-right: 5px;">
                loggi
            </a>

            <div class="mobile-menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </div>

            <nav class="nav-links" id="navLinks">
                <a href="index.php">Início</a>
                <a href="index.php#para-voce">Para você</a>
                <a href="index.php#para-empresas">Para empresas</a>
                <a href="sobre.php">Sobre</a>
                <a href="login_cliente.php" class="btn-login">Entrar</a>
            </nav>
        </div>
    </header>

    <section class="hero" style="min-height: 80vh; padding-top: 15rem;">
        <div class="container hero-box">
            <div class="hero-content reveal-on-scroll">
                <h1 class="premium-title">
                    Conectamos o Brasil de ponta a ponta
                </h1>
                <p class="hero-desc">
                    A Loggi utiliza inteligência logística e tecnologia proprietária para tornar as entregas mais
                    rápidas, seguras e eficientes para todos os brasileiros.
                </p>
                <div class="hero-actions">
                    <a href="index.php" class="btn-cta primary">
                        <i class="fas fa-search-location"></i> Rastrear agora
                    </a>
                </div>
            </div>
            <div class="hero-image reveal-on-scroll" style="display: flex; flex-direction: column; gap: 2rem;">
                <div class="proof-card highlight" style="padding: 3rem; transform: rotate(-2deg);">
                    <div class="proof-value" style="font-size: 3.5rem;">+100M</div>
                    <div class="proof-label">Objetos Entregues</div>
                </div>
                <div class="proof-card highlight" style="padding: 3rem; transform: rotate(2deg); margin-left: 4rem;">
                    <div class="proof-value" style="font-size: 3.5rem;">4k+</div>
                    <div class="proof-label">Cidades Atendidas</div>
                </div>
            </div>
        </div>
    </section>

    <section class="marketing-section reveal-on-scroll" style="background: white;">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Nossa Tecnologia</h2>
                <p class="section-subtitle">O que nos torna diferentes é a forma como usamos a inovação para resolver
                    desafios logísticos complexos.</p>
            </div>
            <div class="marketing-grid">
                <div class="marketing-card premium">
                    <div class="card-icon"><i class="fas fa-microchip"></i></div>
                    <h3>Inteligência de Rotas</h3>
                    <p>Nossos algoritmos calculam as rotas mais eficientes em milisegundos, garantindo economia e
                        velocidade.</p>
                </div>
                <div class="marketing-card premium">
                    <div class="card-icon"><i class="fas fa-fingerprint"></i></div>
                    <h3>Segurança Total</h3>
                    <p>Monitoramento avançado e biometria em todas as etapas garantem que seu objeto chegue intacto ao
                        destino.</p>
                </div>
                <div class="marketing-card premium">
                    <div class="card-icon"><i class="fas fa-network-wired"></i></div>
                    <h3>Malha Integrada</h3>
                    <p>A maior rede de pontos de postagem e centros de distribuição conectada por um único sistema
                        inteligente.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="marketing-section reveal-on-scroll" style="background: var(--slate-50);">
        <div class="container">
            <h2 class="section-title" style="text-align: center; margin-bottom: 6rem;">Perguntas Frequentes</h2>
            <div style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem;">
                <details
                    style="background: white; padding: 2rem; border-radius: 24px; border: 1px solid var(--slate-200); cursor: pointer; transition: all 0.3s;">
                    <summary
                        style="font-weight: 800; font-size: 1.2rem; color: var(--secondary); list-style: none; display: flex; justify-content: space-between; align-items: center;">
                        Como faço para enviar um pacote?
                        <i class="fas fa-plus" style="font-size: 1rem; color: var(--primary);"></i>
                    </summary>
                    <p style="margin-top: 1.5rem; color: var(--slate-500); line-height: 1.7; font-size: 1.1rem;">
                        Basta acessar "Enviar agora" em nossa home, preencher os dados do objeto e realizar o pagamento.
                        Depois, é só levar sua etiqueta a um Ponto Loggi parceiro próximo a você.
                    </p>
                </details>

                <details
                    style="background: white; padding: 2rem; border-radius: 24px; border: 1px solid var(--slate-200); cursor: pointer; transition: all 0.3s;">
                    <summary
                        style="font-weight: 800; font-size: 1.2rem; color: var(--secondary); list-style: none; display: flex; justify-content: space-between; align-items: center;">
                        Qual o prazo de entrega padrão?
                        <i class="fas fa-plus" style="font-size: 1rem; color: var(--primary);"></i>
                    </summary>
                    <p style="margin-top: 1.5rem; color: var(--slate-500); line-height: 1.7; font-size: 1.1rem;">
                        Os prazos variam de acordo com a origem e o destino. Para envios locais, entregamos em até 24h.
                        Para envios nacionais expressos, o prazo médio é de 3 dias úteis.
                    </p>
                </details>

                <details
                    style="background: white; padding: 2rem; border-radius: 24px; border: 1px solid var(--slate-200); cursor: pointer; transition: all 0.3s;">
                    <summary
                        style="font-weight: 800; font-size: 1.2rem; color: var(--secondary); list-style: none; display: flex; justify-content: space-between; align-items: center;">
                        É possível acelerar uma entrega em curso?
                        <i class="fas fa-plus" style="font-size: 1rem; color: var(--primary);"></i>
                    </summary>
                    <p style="margin-top: 1.5rem; color: var(--slate-500); line-height: 1.7; font-size: 1.1rem;">
                        Sim! Ao realizar o rastreio no nosso site, caso seu objeto seja elegível, você verá o botão
                        "Acelerar Entrega". Siga as instruções para priorizar seu envio em nossa malha expressa.
                    </p>
                </details>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col brand">
                    <a href="index.php" class="logo">
                        <img src="assets/images/logo.png" alt="Loggi"
                            style="height: 38px; width: auto; filter: brightness(0) invert(1); vertical-align: middle; margin-right: 5px;">
                        loggi
                    </a>
                    <p>Reinventando a logística brasileira através de tecnologia própria e excelência operacional.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
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
        // Header Scroll
        window.addEventListener('scroll', () => {
            const header = document.getElementById('mainHeader');
            if (header) {
                if (window.scrollY > 50) header.classList.add('scrolled');
                else header.classList.remove('scrolled');
            }
        });

        // Mobile Menu
        const menuToggle = document.getElementById('menuToggle');
        const navLinks = document.getElementById('navLinks');
        if (menuToggle && navLinks) {
            menuToggle.addEventListener('click', () => {
                navLinks.classList.toggle('active');
                const icon = menuToggle.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            });
        }

        // Scroll Reveal
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.reveal-on-scroll').forEach(el => revealObserver.observe(el));
    </script>
</body>

</html>