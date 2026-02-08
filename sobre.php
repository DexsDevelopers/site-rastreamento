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
    <title>Sobre Nós - Loggi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>
</head>

<body>

    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <i class="fas fa-shipping-fast"></i>
                <?= htmlspecialchars($nomeEmpresa)?>
            </a>
            <nav class="nav-links">
                <a href="index.php">Início</a>
                <a href="sobre.php">Sobre</a>
            </nav>
            <button class="mobile-menu-toggle" onclick="toggleMenu()">
                <i class="fas fa-bars"></i>
            </button>
            <nav class="mobile-menu" id="mobileMenu">
                <a href="index.php">Início</a>
                <a href="sobre.php">Sobre</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container hero-content" style="text-align: center; align-items: center;">
            <h1>Conectamos o Brasil</h1>
            <p class="tagline" style="margin-bottom: 2rem;">A Loggi está em todo lugar. Entregamos tecnologia,
                rapidez e
                segurança para você e seu negócio.</p>

            <div class="hero-actions" style="justify-content: center;">
                <a href="index.php" class="btn-hero">
                    Rastrear Agora
                </a>
                <a href="cadastro_objetivo.php" class="btn-hero secondary">
                    Enviar agora
                </a>
            </div>

            <div class="badges" style="justify-content: center; margin-top: 3rem;">
                <span class="badge"><i class="fas fa-check-circle"></i> Loggi para você</span>
                <span class="badge"><i class="fas fa-truck"></i> Loggi para empresas</span>
                <span class="badge"><i class="fas fa-map-marker-alt"></i> Ajudar</span>
            </div>
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">Nossa Tecnologia</h2>

        <div class="features-grid">
            <div class="feature-card">
                <span class="feature-icon"><i class="fas fa-rocket"></i></span>
                <h3>Inovação</h3>
                <p>Usamos tecnologia de ponta para otimizar rotas e garantir a entrega mais rápida do Brasil.</p>
            </div>

            <div class="feature-card">
                <span class="feature-icon"><i class="fas fa-shield-alt"></i></span>
                <h3>Segurança</h3>
                <p>Seus pacotes são monitorados 24h por dia, do despacho até a porta do destino.</p>
            </div>

            <div class="feature-card">
                <span class="feature-icon"><i class="fas fa-globe-americas"></i></span>
                <h3>Capilaridade</h3>
                <p>Nossa rede logística alcança todas as regiões do país, conectando pessoas e negócios.</p>
            </div>
        </div>

        <div class="features-grid" style="margin-top: 4rem;">
            <div class="feature-card" style="text-align: center;">
                <div class="feature-icon" style="margin: 0 auto 1.5rem;"><i class="fas fa-box-open"></i></div>
                <div class="stat-number" style="font-size: 2.5rem; font-weight: 800; color: var(--primary);">+100M</div>
                <div class="stat-label" style="color: var(--text-muted);">Entregas Realizadas</div>
            </div>
            <div class="feature-card" style="text-align: center;">
                <div class="feature-icon" style="margin: 0 auto 1.5rem;"><i class="fas fa-globe-americas"></i></div>
                <div class="stat-number" style="font-size: 2.5rem; font-weight: 800; color: var(--primary);">4000+</div>
                <div class="stat-label" style="color: var(--text-muted);">Cidades Atendidas</div>
            </div>
            <div class="feature-card" style="text-align: center;">
                <div class="feature-icon" style="margin: 0 auto 1.5rem;"><i class="fas fa-star"></i></div>
                <div class="stat-number" style="font-size: 2.5rem; font-weight: 800; color: var(--primary);">4.8/5</div>
                <div class="stat-label" style="color: var(--text-muted);">Taxa de Satisfação</div>
            </div>
        </div>
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">❓ Perguntas Frequentes</h2>

        <div class="container" style="max-width: 800px; margin: 0 auto;">

            <details class="feature-card" style="margin-bottom: 1rem; padding: 1.5rem; cursor: pointer;">
                <summary class="faq-question"
                    style="list-style: none; display: flex; justify-content: space-between; align-items: center; font-weight: 700; color: var(--text-main);">
                    <span>🔍 Como rastrear minha entrega?</span>
                    <i class="fas fa-chevron-down"></i>
                </summary>
                <div class="faq-answer" style="margin-top: 1rem; color: var(--text-muted); line-height: 1.6;">
                    Acesse nosso site, insira o código de rastreamento fornecido e sua cidade de destino. Você terá
                    acesso a todas as etapas do processo de entrega em tempo real.
                </div>
            </details>

            <details class="feature-card" style="margin-bottom: 1rem; padding: 1.5rem; cursor: pointer;">
                <summary class="faq-question"
                    style="list-style: none; display: flex; justify-content: space-between; align-items: center; font-weight: 700; color: var(--text-main);">
                    <span>⏱️ Qual o prazo de entrega?</span>
                    <i class="fas fa-chevron-down"></i>
                </summary>
                <div class="faq-answer" style="margin-top: 1rem; color: var(--text-muted); line-height: 1.6;">
                    O prazo varia conforme a distância e modalidade escolhida. A Loggi oferece diversas opções,
                    desde
                    entregas no mesmo dia até envios nacionais econômicos.
                </div>
            </details>

            <details class="feature-card" style="margin-bottom: 1rem; padding: 1.5rem; cursor: pointer;">
                <summary class="faq-question"
                    style="list-style: none; display: flex; justify-content: space-between; align-items: center; font-weight: 700; color: var(--text-main);">
                    <span>🔒 Meus dados estão seguros?</span>
                    <i class="fas fa-chevron-down"></i>
                </summary>
                <div class="faq-answer" style="margin-top: 1rem; color: var(--text-muted); line-height: 1.6;">
                    Sim. A Loggi utiliza os mais altos padrões de segurança e criptografia para proteger suas
                    informações e garantir a privacidade dos dados de entrega.
                </div>
            </details>
        </div>
    </section>



    <div class="container" style="max-width: 900px; margin: 6rem auto;">
        <div class="search-card" style="text-align: center; border-color: var(--primary);">
            <h3 style="font-size: 2.2rem; margin-bottom: 1.5rem; justify-content: center;">Pronto para Começar?</h3>
            <p style="color: var(--text-muted); margin-bottom: 2.5rem; font-size: 1.1rem;">
                Rastreie suas encomendas ou indique amigos para ganhar benefícios exclusivos!
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="index.php" class="btn-primary" style="width: auto; padding: 1rem 3rem;">
                    Rastrear Agora
                </a>
                <a href="cadastro_objetivo.php" class="btn-hero" style="width: auto; padding: 1rem 3rem;">
                    Enviar agora
                </a>
            </div>
        </div>
    </div>

    <footer class="footer"
        style="text-align: center; padding: 4rem 0; background: #F8F9FA; border-top: 1px solid #E2E8F0;">
        <p>&copy; 2026 Loggi Technology. Todos os direitos reservados.</p>
    </footer>

    <script>
        function toggleMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('active');
        }

        function toggleFaq(element) {
            const faqItem = element.closest('.faq-item');
            const answer = element.nextElementSibling;
            const isActive = answer.classList.contains('active');

            document.querySelectorAll('.faq-item').forEach(item => {
                item.classList.remove('active');
                item.querySelector('.faq-answer').classList.remove('active');
            });

            if (!isActive) {
                faqItem.classList.add('active');
                answer.classList.add('active');
            }
        }
    </script>

</body>

</html>