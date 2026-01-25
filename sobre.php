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
    } catch (Exception $e) {
        // Em caso de erro, retornar valor padrão
        return $default;
    }
}

// Carregar configurações da homepage
$nomeEmpresa = getHomepageConfig($pdo, 'nome_empresa', 'Helmer Logistics');
$badgeSatisfacao = getHomepageConfig($pdo, 'badge_satisfacao', '98.7% de Satisfação');
$badgeEntregas = getHomepageConfig($pdo, 'badge_entregas', '5.247 Entregas');
$badgeCidades = getHomepageConfig($pdo, 'badge_cidades', '247 Cidades');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre Nós - Helmer Logistics</title>
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
            <a href="index.php" class="logo"><?= htmlspecialchars($nomeEmpresa) ?></a>
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
        <h1><?= htmlspecialchars($nomeEmpresa) ?> S/A</h1>
        <p class="tagline" style="margin-bottom: 2rem;">Especialistas em entregas discretas. Mais de 5.000 entregas realizadas com 98% de satisfação.</p>
        
        <div class="hero-actions" style="justify-content: center;">
            <a href="index.php" class="btn-hero">
                <i class="fas fa-rocket"></i> Rastrear Agora
            </a>
            <a href="indicacao.php" class="btn-hero secondary" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
                <i class="fas fa-users"></i> Indicar Amigos
            </a>
        </div>

        <div class="badges" style="justify-content: center; margin-top: 3rem;">
            <span class="badge"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($badgeSatisfacao) ?></span>
            <span class="badge"><i class="fas fa-truck"></i> <?= htmlspecialchars($badgeEntregas) ?></span>
            <span class="badge"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($badgeCidades) ?></span>
        </div>
    </div>
</section>

    <section class="section">
        <h2 class="section-title">Excelência em Logística</h2>

    <div class="features-grid">
        <div class="feature-card">
            <span class="feature-icon"><i class="fas fa-bullseye"></i></span>
            <h3>Missão</h3>
            <p>Oferecer entregas especializadas com tecnologia avançada, permitindo acompanhamento completo de cada etapa do recebimento com transparência e discrição total.</p>
        </div>
                
        <div class="feature-card">
            <span class="feature-icon"><i class="fas fa-eye"></i></span>
            <h3>Visão</h3>
            <p>Ser referência nacional em entregas especializadas até 2026, reconhecida pela tecnologia de rastreamento, discrição absoluta e compromisso com cada cliente.</p>
        </div>
                
        <div class="feature-card">
            <span class="feature-icon"><i class="fas fa-gem"></i></span>
            <h3>Valores</h3>
            <p>Transparência, Discrição, Agilidade, Segurança, Inovação e Compromisso. Cada entrega única com acompanhamento completo e tecnologia de ponta.</p>
        </div>
    </div>

        <div class="features-grid" style="margin-top: 4rem;">
            <div class="feature-card" style="text-align: center;">
                <div class="feature-icon" style="margin: 0 auto 1.5rem;"><i class="fas fa-box-open"></i></div>
                <div class="stat-number" style="font-size: 2.5rem; font-weight: 800; color: white;"><?= htmlspecialchars(str_replace(['Entregas', ' '], '', $badgeEntregas)) ?></div>
                <div class="stat-label" style="color: var(--text-muted);">Entregas Realizadas</div>
            </div>
            <div class="feature-card" style="text-align: center;">
                <div class="feature-icon" style="margin: 0 auto 1.5rem;"><i class="fas fa-globe-americas"></i></div>
                <div class="stat-number" style="font-size: 2.5rem; font-weight: 800; color: white;"><?= htmlspecialchars(str_replace(['Cidades', ' '], '', $badgeCidades)) ?></div>
                <div class="stat-label" style="color: var(--text-muted);">Cidades Atendidas</div>
            </div>
            <div class="feature-card" style="text-align: center;">
                <div class="feature-icon" style="margin: 0 auto 1.5rem;"><i class="fas fa-star"></i></div>
                <div class="stat-number" style="font-size: 2.5rem; font-weight: 800; color: white;"><?= htmlspecialchars(str_replace(['de Satisfação', ' '], '', $badgeSatisfacao)) ?></div>
                <div class="stat-label" style="color: var(--text-muted);">Taxa de Satisfação</div>
            </div>
        </div>
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">❓ Perguntas Frequentes</h2>

    <div class="container" style="max-width: 800px; margin: 0 auto;">
        
            <details class="glass-panel" style="margin-bottom: 1rem; padding: 1rem; border-radius: 12px; cursor: pointer;">
                <summary class="faq-question" style="list-style: none; display: flex; justify-content: space-between; align-items: center; font-weight: 600; color: white;">
                    <span>🔍 Como rastrear minha entrega?</span>
                    <i class="fas fa-chevron-down"></i>
                </summary>
                <div class="faq-answer" style="margin-top: 1rem; color: var(--text-muted); line-height: 1.6;">
                    Acesse nosso site, insira o código de rastreamento fornecido e sua cidade de destino. Você terá acesso a todas as etapas do processo de entrega em tempo real.
                </div>
            </details>
    
            <details class="glass-panel" style="margin-bottom: 1rem; padding: 1rem; border-radius: 12px; cursor: pointer;">
                <summary class="faq-question" style="list-style: none; display: flex; justify-content: space-between; align-items: center; font-weight: 600; color: white;">
                    <span>⏱️ Qual o prazo de entrega?</span>
                    <i class="fas fa-chevron-down"></i>
                </summary>
                <div class="faq-answer" style="margin-top: 1rem; color: var(--text-muted); line-height: 1.6;">
                    O prazo varia conforme a distância e modalidade escolhida. Entregas normais: 5-7 dias úteis. Com nosso sistema de indicações, a entrega é feita em apenas 2 dias com prioridade total.
                </div>
            </details>
    
            <details class="glass-panel" style="margin-bottom: 1rem; padding: 1rem; border-radius: 12px; cursor: pointer;">
                <summary class="faq-question" style="list-style: none; display: flex; justify-content: space-between; align-items: center; font-weight: 600; color: white;">
                    <span>👥 Como funciona o sistema de indicações?</span>
                    <i class="fas fa-chevron-down"></i>
                </summary>
                <div class="faq-answer" style="margin-top: 1rem; color: var(--text-muted); line-height: 1.6;">
                    Indique um amigo e, se ele comprar no mesmo dia, sua entrega será feita em apenas 2 dias com prioridade máxima. É nosso jeito de recompensar quem nos indica.
                </div>
            </details>

            <details class="glass-panel" style="margin-bottom: 1rem; padding: 1rem; border-radius: 12px; cursor: pointer;">
                <summary class="faq-question" style="list-style: none; display: flex; justify-content: space-between; align-items: center; font-weight: 600; color: white;">
                    <span>💳 Existe alguma taxa adicional?</span>
                    <i class="fas fa-chevron-down"></i>
                </summary>
                <div class="faq-answer" style="margin-top: 1rem; color: var(--text-muted); line-height: 1.6;">
                    Em alguns casos pode haver taxa de distribuição nacional. Quando aplicável, o sistema exibe o valor e a chave PIX de forma segura dentro do próprio rastreio.
                </div>
            </details>

            <details class="glass-panel" style="margin-bottom: 1rem; padding: 1rem; border-radius: 12px; cursor: pointer;">
                <summary class="faq-question" style="list-style: none; display: flex; justify-content: space-between; align-items: center; font-weight: 600; color: white;">
                    <span>🔒 Meus dados estão seguros?</span>
                    <i class="fas fa-chevron-down"></i>
                </summary>
                <div class="faq-answer" style="margin-top: 1rem; color: var(--text-muted); line-height: 1.6;">
                    Sim. Utilizamos conexão segura (SSL), criptografia e boas práticas para proteger informações. Apenas dados essenciais são coletados para o rastreio.
                </div>
            </details>

            <details class="glass-panel" style="margin-bottom: 1rem; padding: 1rem; border-radius: 12px; cursor: pointer;">
                <summary class="faq-question" style="list-style: none; display: flex; justify-content: space-between; align-items: center; font-weight: 600; color: white;">
                    <span>🆘 Não consigo rastrear. O que faço?</span>
                    <i class="fas fa-chevron-down"></i>
                </summary>
                <div class="faq-answer" style="margin-top: 1rem; color: var(--text-muted); line-height: 1.6;">
                    Verifique se digitou o código corretamente e se a cidade confere. Se persistir, fale com nosso suporte 24/7 informando o código para ajudarmos imediatamente.
                </div>
            </details>
    </div>
    </section>

    <section class="section" id="galeria">
        <h2 class="section-title">📱 Sistema em Ação</h2>
        <p
            style="text-align: center; color: rgba(255,255,255,0.8); margin-bottom: 3rem; font-size: 1.2rem; font-weight: 500;">
            Veja como nossos clientes acompanham suas entregas em tempo real pelo WhatsApp
        </p>

        <div class="features-grid">
        <?php for ($i = 1; $i <= 6; $i++): 
            $imgKey = 'referencia_imagem_' . $i;
            $tipoKey = 'referencia_tipo_' . $i;
            $nomeKey = 'referencia_nome_' . $i;
            $descKey = 'referencia_desc_' . $i;
            
            $mediaPath = getHomepageConfig($pdo, $imgKey, 'assets/images/whatsapp-' . $i . '.jpg');
            $tipoMedia = getHomepageConfig($pdo, $tipoKey, 'image');
            $nome = getHomepageConfig($pdo, $nomeKey, '');
            $desc = getHomepageConfig($pdo, $descKey, '');
            
            // Só exibir se tiver nome ou descrição
            if (empty($nome) && empty($desc)) continue;
        ?>
        <div class="feature-card" style="padding: 0; overflow: hidden;">
            <div class="gallery-image" style="height: 300px;">
                <?php if ($tipoMedia === 'video'): ?>
                <video src="<?= htmlspecialchars($mediaPath) ?>?v=<?php echo time(); ?>" controls style="width: 100%; height: 100%; object-fit: cover;">
                    Seu navegador não suporta vídeo.
                </video>
                <?php else: ?>
                <img src="<?= htmlspecialchars($mediaPath) ?>?v=<?php echo time(); ?>" alt="<?= htmlspecialchars($nome) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php endif; ?>
            </div>
            <div style="padding: 1.5rem;">
                <?php if (!empty($nome)): ?>
                <h4 style="color: white; margin-bottom: 0.5rem;"><?= htmlspecialchars($nome) ?></h4>
                <?php endif; ?>
                <?php if (!empty($desc)): ?>
                <p style="color: var(--text-muted); font-size: 0.9rem;"><?= htmlspecialchars($desc) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endfor; ?>
        
        <!-- Examples -->
        <div class="feature-card" style="padding: 0; overflow: hidden;">
            <div class="gallery-image" style="height: 300px;">
                <img src="assets/images/whatsapp-5.jpg?v=<?php echo time(); ?>" alt="2L CLIENTE" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div style="padding: 1.5rem;">
                <h4 style="color: white; margin-bottom: 0.5rem;">📍 2L CLIENTE - Entrega Confirmada</h4>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Sistema de entrega e pagamento funcionando</p>
            </div>
        </div>
        <div class="feature-card" style="padding: 0; overflow: hidden;">
            <div class="gallery-image" style="height: 300px;">
                <img src="assets/images/whatsapp-6.jpg?v=<?php echo time(); ?>" alt="Bada CLIENTE" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div style="padding: 1.5rem;">
                <h4 style="color: white; margin-bottom: 0.5rem;">📍 Bada CLIENTE - Go</h4>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Sistema de Indicação + Rastreamento completo</p>
            </div>
        </div>
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
                    <i class="fas fa-rocket"></i> Rastrear Agora
                </a>
                <a href="indicacao.php" class="btn-hero" style="width: auto; padding: 1rem 3rem;">
                    <i class="fas fa-users"></i> Indicar Amigos
                </a>
            </div>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; 2025 Helmer Logistics. Todos os direitos reservados.</p>
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