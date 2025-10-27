<?php
// Headers para evitar cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sobre Nós - Helmer Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
:root {
    --primary: #FF3333;
    --primary-dark: #CC0000;
    --secondary: #FF6600;
    --dark: #0A0A0A;
    --light: #FFF;
    --gradient: linear-gradient(135deg, #FF0000 0%, #FF6600 100%);
}
body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #0A0A0A 0%, #1A0000 100%);
    color: var(--light);
    line-height: 1.6;
}

/* Header */
.header {
    background: rgba(0,0,0,0.1);
    backdrop-filter: blur(30px);
    -webkit-backdrop-filter: blur(30px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 25px;
    position: sticky;
    top: 20px;
    z-index: 1000;
    margin: 0 20px;
}
.nav-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 70px;
}
.logo {
    font-size: 1.8rem;
    font-weight: 900;
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-decoration: none;
}
.nav-links {
    display: flex;
    gap: 2.5rem;
}
.nav-links a {
    color: rgba(255,255,255,0.95);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}
.nav-links a:hover {
    color: var(--primary);
}
.mobile-menu-toggle {
    display: none;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 12px;
    color: var(--light);
    font-size: 1.3rem;
    padding: 0.5rem;
    cursor: pointer;
}
.mobile-menu {
    display: none;
}
.mobile-menu.active {
    display: block;
}

/* Hero */
.hero {
    padding: 6rem 2rem;
    text-align: center;
    max-width: 1200px;
    margin: 0 auto;
}
.hero h1 {
    font-size: 4rem;
    font-weight: 900;
    margin-bottom: 2rem;
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.hero p {
    font-size: 1.3rem;
    color: rgba(255,255,255,0.8);
}

/* Sections */
.section {
    padding: 6rem 2rem;
    max-width: 1200px;
    margin: 0 auto;
}
.section-title {
    font-size: 3rem;
    font-weight: 800;
    text-align: center;
    margin-bottom: 4rem;
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* About Grid */
.about-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    align-items: center;
    margin-bottom: 6rem;
}
.about-content h3 {
    font-size: 2rem;
    color: var(--primary);
    margin-bottom: 1rem;
}
.about-content p {
    font-size: 1.1rem;
    color: rgba(255,255,255,0.8);
    margin-bottom: 2rem;
}
.about-image {
    text-align: center;
    font-size: 10rem;
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* Stats */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    margin-top: 4rem;
}
.stat-card {
    background: rgba(255,255,255,0.05);
    padding: 2rem;
    border-radius: 20px;
    text-align: center;
    border: 1px solid rgba(255,51,51,0.2);
}
.stat-number {
    font-size: 3rem;
    font-weight: 900;
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.stat-label {
    color: rgba(255,255,255,0.8);
    margin-top: 0.5rem;
}

/* FAQ */
.faq-item {
    background: rgba(255,255,255,0.05);
    margin-bottom: 1rem;
    border-radius: 15px;
    overflow: hidden;
    border: 1px solid rgba(255,51,51,0.2);
}
.faq-question {
    padding: 1.5rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    font-weight: 600;
}
.faq-answer {
    padding: 0 1.5rem;
    max-height: 0;
    overflow: hidden;
    transition: all 0.3s ease;
    color: rgba(255,255,255,0.8);
}
.faq-answer.active {
    padding: 1.5rem;
    max-height: 500px;
}

/* Gallery */
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    margin-top: 3rem;
}
.gallery-item {
    background: rgba(255,255,255,0.05);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid rgba(255,51,51,0.2);
}
.gallery-image {
    height: 250px;
    overflow: hidden;
}
.gallery-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.gallery-info {
    padding: 1.5rem;
}
.gallery-info h4 {
    color: var(--primary);
    margin-bottom: 0.5rem;
}
.gallery-info p {
    color: rgba(255,255,255,0.7);
    font-size: 0.9rem;
}

/* CTA */
.cta-section {
    text-align: center;
    padding: 6rem 2rem;
    background: rgba(255,51,51,0.1);
    border-radius: 30px;
    border: 2px solid var(--primary);
    margin: 6rem auto;
    max-width: 1000px;
}
.cta-title {
    font-size: 2.5rem;
    margin-bottom: 1.5rem;
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.btn-cta {
    display: inline-block;
    padding: 1rem 3rem;
    background: var(--gradient);
    color: var(--light);
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    margin: 0.5rem;
    transition: transform 0.3s;
}
.btn-cta:hover {
    transform: scale(1.05);
}

/* Footer */
.footer {
    text-align: center;
    padding: 3rem 2rem;
    color: rgba(255,255,255,0.6);
}

/* Mobile Menu */
@media (max-width: 768px) {
    .nav-links {
        display: none;
    }
    .mobile-menu-toggle {
        display: block;
    }
    .mobile-menu {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: rgba(0,0,0,0.95);
        backdrop-filter: blur(30px);
        padding: 1rem;
        margin: 0 20px;
        border-radius: 0 0 25px 25px;
    }
    .mobile-menu a {
        display: block;
        padding: 1rem;
        color: var(--light);
        text-decoration: none;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .hero h1 {
        font-size: 2rem;
    }
    .hero p {
        font-size: 1rem;
    }
    .about-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .gallery-grid {
        grid-template-columns: 1fr;
    }
    .section-title {
        font-size: 2rem;
    }
    .cta-title {
        font-size: 1.8rem;
    }
}
</style>
</head>
<body>

<header class="header">
    <div class="nav-container">
        <a href="index.php" class="logo">Helmer Logistics</a>
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
    <h1>Helmer Logistics S/A</h1>
    <p>Especialistas em entregas discretas. Mais de 5.000 entregas realizadas com 98% de satisfação. Nossos clientes acompanham cada etapa do recebimento com tecnologia avançada.</p>
</section>

<section class="section">
    <h2 class="section-title">Excelência em Logística</h2>
    
    <div class="about-grid">
        <div class="about-content">
            <h3>🏆 Missão</h3>
            <p>Oferecer serviços de entrega especializado através de tecnologia avançada, permitindo que nossos clientes acompanhem cada etapa do recebimento. Transformamos cada entrega em uma experiência transparente de confiança e discrição.</p>
            
            <h3>🚀 Visão</h3>
            <p>Ser a empresa de entregas especializadas número 1 do Brasil até 2026, reconhecida pela transparência no acompanhamento, discrição total e compromisso inabalável com cada cliente. Investimos constantemente em tecnologia e infraestrutura.</p>
            
            <h3>💎 Valores</h3>
            <p>Transparência total, Discrição absoluta, Agilidade extrema, Segurança garantida, Inovação constante e Compromisso genuíno. Cada entrega é tratada como única, com acompanhamento completo e tecnologia de ponta.</p>
        </div>
        <div class="about-image">
            <i class="fas fa-truck-fast"></i>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number">5.247</div>
            <div class="stat-label">Entregas Realizadas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">247</div>
            <div class="stat-label">Cidades Atendidas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">98.7%</div>
            <div class="stat-label">Taxa de Satisfação</div>
        </div>
    </div>
</section>

<section class="section">
    <h2 class="section-title">Perguntas Frequentes</h2>
    
    <div class="faq-item">
        <div class="faq-question" onclick="toggleFaq(this)">
            <span>Como rastrear minha entrega?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            Acesse nosso site, insira o código de rastreamento fornecido e sua cidade de destino. Você terá acesso a todas as etapas do processo de entrega em tempo real.
        </div>
    </div>
    
    <div class="faq-item">
        <div class="faq-question" onclick="toggleFaq(this)">
            <span>Qual o prazo de entrega?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            O prazo varia conforme a distância e modalidade escolhida. Entregas normais: 5-7 dias úteis. Com nosso sistema de indicações, a entrega é feita em apenas 2 dias com prioridade total.
        </div>
    </div>
    
    <div class="faq-item">
        <div class="faq-question" onclick="toggleFaq(this)">
            <span>Como funciona o sistema de indicações?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="faq-answer">
            Indique um amigo e, se ele comprar no mesmo dia, sua entrega será feita em apenas 2 dias com prioridade máxima. É nosso jeito de recompensar quem nos indica.
        </div>
    </div>
</section>

<section class="section">
    <h2 class="section-title">Referências do Sistema em Uso</h2>
    <p style="text-align: center; color: rgba(255,255,255,0.7); margin-bottom: 3rem;">
        Veja como nossos clientes utilizam o sistema de rastreamento através do WhatsApp
    </p>
    
    <div class="gallery-grid">
        <div class="gallery-item">
            <div class="gallery-image">
                <img src="assets/images/whatsapp-1.jpg?v=<?php echo time(); ?>" alt="Luiz Gabriel">
            </div>
            <div class="gallery-info">
                <h4>Luiz Gabriel - Petrópolis</h4>
                <p>Sistema de rastreamento básico funcionando perfeitamente</p>
            </div>
        </div>
        <div class="gallery-item">
            <div class="gallery-image">
                <img src="assets/images/whatsapp-2.jpg?v=<?php echo time(); ?>" alt="juuh santts">
            </div>
            <div class="gallery-info">
                <h4>juuh santts - Ubá</h4>
                <p>Monitoramento oficial com status detalhado</p>
            </div>
        </div>
        <div class="gallery-item">
            <div class="gallery-image">
                <img src="assets/images/whatsapp-3.jpg?v=<?php echo time(); ?>" alt="RKZIN">
            </div>
            <div class="gallery-info">
                <h4>RKZIN - Jardim Camburi</h4>
                <p>Sistema oficial de monitoramento em tempo real</p>
            </div>
        </div>
        <div class="gallery-item">
            <div class="gallery-image">
                <img src="assets/images/whatsapp-4.jpg?v=<?php echo time(); ?>" alt="Vitor João">
            </div>
            <div class="gallery-info">
                <h4>Vitor João - AdolfoSP</h4>
                <p>Monitoramento com interface integrada ao WhatsApp</p>
            </div>
        </div>
        <div class="gallery-item">
            <div class="gallery-image">
                <img src="assets/images/whatsapp-5.jpg?v=<?php echo time(); ?>" alt="2L CLIENTE">
            </div>
            <div class="gallery-info">
                <h4>2L CLIENTE - Entrega Confirmada</h4>
                <p>Sistema de entrega e pagamento funcionando</p>
            </div>
        </div>
        <div class="gallery-item">
            <div class="gallery-image">
                <img src="assets/images/whatsapp-6.jpg?v=<?php echo time(); ?>" alt="Bada CLIENTE">
            </div>
            <div class="gallery-info">
                <h4>Bada CLIENTE - Go</h4>
                <p>Sistema de Indicação + Rastreamento completo</p>
            </div>
        </div>
    </div>
</section>

<div class="cta-section">
    <h3 class="cta-title">Pronto para Começar?</h3>
    <p style="color: rgba(255,255,255,0.8); margin-bottom: 2rem; font-size: 1.2rem;">
        Rastreie suas encomendas ou indicar amigos para ganhar benefícios exclusivos!
    </p>
    <a href="index.php" class="btn-cta">
        <i class="fas fa-rocket"></i> Rastrear Agora
    </a>
    <a href="indicacao.php" class="btn-cta">
        <i class="fas fa-users"></i> Indicar Amigos
    </a>
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
    const answer = element.nextElementSibling;
    const isActive = answer.classList.contains('active');
    
    document.querySelectorAll('.faq-answer').forEach(item => {
        item.classList.remove('active');
    });
    
    if (!isActive) {
        answer.classList.add('active');
    }
}
</script>

</body>
</html>
