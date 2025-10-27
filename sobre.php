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
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>Sobre Nós - Helmer Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
:root {
    --primary: #FF3333; --primary-dark: #CC0000; --secondary: #FF6600;
    --dark: #0A0A0A; --dark-light: #1A1A1A; --light: #FFF; 
    --success: #16A34A; --gradient: linear-gradient(135deg, #FF0000 0%, #FF6600 100%);
    --glass: rgba(255,255,255,0.1); --glass-border: rgba(255,255,255,0.2);
    --shadow: 0 20px 40px rgba(0,0,0,0.3); --shadow-hover: 0 30px 60px rgba(255,51,51,0.2);
}
body { 
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
    background: linear-gradient(135deg, #0A0A0A 0%, #1A0000 50%, #0A0A0A 100%);
    color: var(--light); 
    line-height: 1.6; 
    overflow-x: hidden;
}

/* Animações */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeInLeft {
    from { opacity: 0; transform: translateX(-30px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes fadeInRight {
    from { opacity: 0; transform: translateX(30px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
@keyframes glow {
    0%, 100% { box-shadow: 0 0 20px rgba(255,51,51,0.3); }
    50% { box-shadow: 0 0 40px rgba(255,51,51,0.6); }
}

/* Header Moderno */
.header { 
    background: rgba(10, 10, 10, 0.95); 
    backdrop-filter: blur(20px); 
    border-bottom: 1px solid var(--glass-border); 
    position: sticky; 
    top: 0; 
    z-index: 1000; 
    padding: 1.5rem 0;
    animation: fadeInUp 0.8s ease-out;
}
.nav-container { 
    max-width: 1400px; 
    margin: 0 auto; 
    padding: 0 2rem; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
}
.logo { 
    font-size: 2rem; 
    font-weight: 900; 
    background: var(--gradient); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    text-decoration: none;
    transition: all 0.3s ease;
}
.logo:hover {
    transform: scale(1.05);
    filter: drop-shadow(0 0 20px rgba(255,51,51,0.5));
}
.nav-links { display: flex; gap: 3rem; }
.nav-links a { 
    color: rgba(255,255,255,0.8); 
    text-decoration: none; 
    font-weight: 600; 
    font-size: 1.1rem;
    transition: all 0.3s ease;
    position: relative;
}
.nav-links a:hover { 
    color: var(--primary); 
    transform: translateY(-2px);
}
.nav-links a::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--gradient);
    transition: width 0.3s ease;
}
.nav-links a:hover::after {
    width: 100%;
}

/* Hero Section Moderno */
.hero { 
    padding: 8rem 2rem; 
    text-align: center; 
    background: linear-gradient(180deg, rgba(10,10,10,0.8) 0%, rgba(26,0,0,0.6) 100%);
    position: relative;
    overflow: hidden;
}
.hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 50% 50%, rgba(255,51,51,0.1) 0%, transparent 70%);
    pointer-events: none;
}
.hero h1 { 
    font-size: 5rem; 
    font-weight: 900; 
    margin-bottom: 2rem;
    background: var(--gradient); 
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: fadeInUp 1s ease-out 0.2s both;
    text-shadow: 0 0 30px rgba(255,51,51,0.3);
}
.hero p { 
    font-size: 1.8rem; 
    color: rgba(255,255,255,0.8); 
    max-width: 900px;
    margin: 0 auto;
    animation: fadeInUp 1s ease-out 0.4s both;
    font-weight: 300;
    line-height: 1.8;
}

/* Container e Seções */
.container { max-width: 1400px; margin: 0 auto; padding: 0 2rem; }
.section { padding: 8rem 0; position: relative; }
.section-title { 
    font-size: 4rem; 
    font-weight: 900; 
    margin-bottom: 3rem;
    text-align: center; 
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: fadeInUp 0.8s ease-out;
    position: relative;
}
.section-title::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 4px;
    background: var(--gradient);
    border-radius: 2px;
}

/* About Section Moderno */
.about-grid { 
    display: grid; 
    grid-template-columns: 1fr 1fr; 
    gap: 6rem; 
    align-items: center;
    margin-top: 4rem;
}
.about-content h3 { 
    font-size: 2.5rem; 
    color: var(--primary); 
    margin-bottom: 1.5rem;
    font-weight: 800;
    animation: fadeInLeft 0.8s ease-out;
}
.about-content p { 
    font-size: 1.3rem; 
    color: rgba(255,255,255,0.9); 
    margin-bottom: 2rem;
    line-height: 1.8;
    animation: fadeInLeft 0.8s ease-out 0.2s both;
}
.about-image { 
    background: var(--glass); 
    border: 1px solid var(--glass-border);
    border-radius: 30px; 
    padding: 4rem; 
    text-align: center;
    backdrop-filter: blur(20px);
    animation: fadeInRight 0.8s ease-out 0.4s both;
    transition: all 0.3s ease;
}
.about-image:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-hover);
}
.about-image i { 
    font-size: 10rem; 
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: pulse 2s infinite;
}

/* Stats Modernos */
.stats-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
    gap: 3rem; 
    margin-top: 6rem; 
}
.stat-card { 
    background: var(--glass); 
    border: 1px solid var(--glass-border);
    border-radius: 25px; 
    padding: 3rem 2rem; 
    text-align: center;
    backdrop-filter: blur(20px);
    transition: all 0.4s ease;
    position: relative;
    overflow: hidden;
    animation: fadeInUp 0.8s ease-out;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.6s ease;
}
.stat-card:hover::before {
    left: 100%;
}
.stat-card:hover { 
    transform: translateY(-15px) scale(1.02); 
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
}
.stat-number { 
    font-size: 4rem; 
    font-weight: 900; 
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 1rem;
    animation: glow 2s infinite;
}
.stat-label { 
    color: rgba(255,255,255,0.8); 
    font-size: 1.2rem;
    font-weight: 600;
}

/* FAQ Moderno */
.faq-list { max-width: 1000px; margin: 0 auto; }
.faq-item { 
    background: var(--glass); 
    border: 1px solid var(--glass-border);
    border-radius: 20px; 
    margin-bottom: 1.5rem; 
    overflow: hidden;
    backdrop-filter: blur(20px);
    transition: all 0.3s ease;
    animation: fadeInUp 0.8s ease-out;
}
.faq-item:hover {
    transform: translateX(10px);
    box-shadow: var(--shadow);
}
.faq-question { 
    padding: 2rem; 
    cursor: pointer; 
    display: flex;
    justify-content: space-between; 
    align-items: center;
    font-weight: 700; 
    font-size: 1.2rem;
    transition: all 0.3s ease;
    color: var(--light);
}
.faq-question:hover { 
    background: rgba(255,51,51,0.1); 
    color: var(--primary);
}
.faq-answer { 
    padding: 0 2rem; 
    max-height: 0; 
    overflow: hidden;
    transition: all 0.4s ease; 
    color: rgba(255,255,255,0.8);
    font-size: 1.1rem;
    line-height: 1.7;
}
.faq-answer.active { 
    padding: 2rem; 
    max-height: 500px; 
}
.faq-icon { 
    transition: all 0.3s ease;
    font-size: 1.5rem;
    color: var(--primary);
}
.faq-icon.active { 
    transform: rotate(180deg); 
}

/* References Modernos */
.ref-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 3rem; 
    margin-top: 4rem; 
}
.ref-card { 
    background: var(--glass); 
    border: 1px solid var(--glass-border);
    border-radius: 25px; 
    padding: 3rem; 
    backdrop-filter: blur(20px);
    transition: all 0.4s ease;
    animation: fadeInUp 0.8s ease-out;
    position: relative;
    overflow: hidden;
}
.ref-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}
.ref-card:hover::before {
    transform: scaleX(1);
}
.ref-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
}
.ref-header { 
    display: flex; 
    align-items: center; 
    gap: 1.5rem; 
    margin-bottom: 1.5rem; 
}
.ref-avatar { 
    width: 80px; 
    height: 80px; 
    border-radius: 50%;
    background: var(--gradient); 
    display: flex; 
    align-items: center;
    justify-content: center; 
    font-size: 2rem; 
    color: white;
    box-shadow: 0 10px 30px rgba(255,51,51,0.3);
}
.ref-name { 
    font-weight: 700; 
    font-size: 1.3rem;
    color: var(--light);
}
.ref-role { 
    color: var(--primary); 
    font-size: 1rem;
    font-weight: 600;
}
.ref-stars { 
    color: #ffcc00; 
    margin-bottom: 1.5rem;
    font-size: 1.2rem;
}
.ref-text { 
    color: rgba(255,255,255,0.9); 
    font-style: italic;
    font-size: 1.1rem;
    line-height: 1.7;
}

/* WhatsApp Gallery Moderna */
.whatsapp-gallery { margin-top: 6rem; }
.gallery-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 3rem; 
    margin-top: 3rem; 
}
.gallery-item { 
    background: var(--glass); 
    border: 1px solid var(--glass-border);
    border-radius: 25px; 
    padding: 2rem; 
    transition: all 0.4s ease;
    backdrop-filter: blur(20px);
    animation: fadeInUp 0.8s ease-out;
    position: relative;
    overflow: hidden;
}
.gallery-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}
.gallery-item:hover::before {
    transform: scaleX(1);
}
.gallery-item:hover { 
    transform: translateY(-15px) scale(1.02); 
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
}
.gallery-image { 
    width: 100%; 
    height: 250px; 
    background: rgba(255,255,255,0.1);
    border-radius: 15px; 
    margin-bottom: 1.5rem; 
    display: flex;
    align-items: center; 
    justify-content: center; 
    position: relative; 
    overflow: hidden;
    border: 2px solid var(--glass-border);
    transition: all 0.3s ease;
}
.gallery-image:hover {
    border-color: var(--primary);
    box-shadow: 0 10px 30px rgba(255,51,51,0.2);
}
.gallery-image img { 
    width: 100%; 
    height: 100%; 
    object-fit: cover; 
    border-radius: 12px;
    transition: all 0.3s ease;
}
.gallery-image:hover img {
    transform: scale(1.05);
}
.gallery-info h4 { 
    color: var(--primary); 
    font-size: 1.3rem; 
    margin-bottom: 0.8rem;
    font-weight: 700;
}
.gallery-info p { 
    color: rgba(255,255,255,0.8); 
    font-size: 1rem; 
    margin-bottom: 1rem;
    line-height: 1.6;
}
.gallery-status { 
    background: rgba(22,163,74,0.2); 
    border: 1px solid rgba(22,163,74,0.4);
    border-radius: 12px; 
    padding: 0.8rem; 
    font-size: 0.9rem;
    color: #16A34A; 
    text-align: center;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.gallery-date { 
    color: rgba(255,255,255,0.6); 
    font-size: 0.9rem; 
    text-align: center;
    font-weight: 500;
}

/* CTA Section Moderno */
.cta-section { 
    background: var(--glass);
    border: 2px solid var(--primary); 
    border-radius: 40px;
    padding: 6rem 3rem; 
    text-align: center; 
    margin: 8rem 0;
    backdrop-filter: blur(20px);
    position: relative;
    overflow: hidden;
    animation: fadeInUp 0.8s ease-out;
}
.cta-section::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,51,51,0.1) 0%, transparent 70%);
    animation: pulse 4s infinite;
    pointer-events: none;
}
.cta-title { 
    font-size: 3.5rem; 
    font-weight: 900; 
    margin-bottom: 2rem;
    background: var(--gradient); 
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    position: relative;
    z-index: 1;
}
.cta-text { 
    font-size: 1.4rem; 
    color: rgba(255,255,255,0.8); 
    margin-bottom: 3rem;
    position: relative;
    z-index: 1;
}
.btn-cta { 
    padding: 1.5rem 4rem; 
    background: var(--gradient); 
    color: white;
    border: none; 
    border-radius: 15px; 
    font-size: 1.2rem;
    font-weight: 700; 
    cursor: pointer; 
    text-decoration: none;
    display: inline-block; 
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
    box-shadow: 0 10px 30px rgba(255,51,51,0.3);
}
.btn-cta:hover { 
    transform: translateY(-5px) scale(1.05); 
    box-shadow: 0 20px 40px rgba(255,51,51,0.5);
}

/* Footer Moderno */
.footer { 
    background: rgba(10,10,10,0.95); 
    padding: 4rem 0; 
    text-align: center;
    border-top: 1px solid var(--glass-border);
    backdrop-filter: blur(20px);
    margin-top: 6rem;
}
.footer p { 
    color: rgba(255,255,255,0.6); 
    font-size: 1.1rem;
    font-weight: 500;
}

/* Responsive Design Avançado */
@media (max-width: 1200px) {
    .hero h1 { font-size: 4rem; }
    .section-title { font-size: 3.5rem; }
    .about-grid { gap: 4rem; }
}

@media (max-width: 1024px) {
    .hero h1 { font-size: 3.5rem; }
    .hero p { font-size: 1.5rem; }
    .about-grid { grid-template-columns: 1fr; gap: 3rem; }
    .nav-links { gap: 2rem; }
    .stats-grid { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
}

@media (max-width: 768px) {
    .hero { padding: 6rem 1rem; }
    .hero h1 { font-size: 3rem; }
    .hero p { font-size: 1.3rem; }
    .section-title { font-size: 2.5rem; }
    .nav-links { display: none; }
    .about-image i { font-size: 6rem; }
    .stat-number { font-size: 3rem; }
    .cta-title { font-size: 2.5rem; }
    .cta-section { padding: 4rem 2rem; }
    .btn-cta { padding: 1.2rem 3rem; font-size: 1.1rem; }
    .gallery-grid { grid-template-columns: 1fr; }
    .ref-grid { grid-template-columns: 1fr; }
}

@media (max-width: 480px) {
    .hero h1 { font-size: 2.5rem; }
    .hero p { font-size: 1.1rem; }
    .section-title { font-size: 2rem; }
    .about-content h3 { font-size: 2rem; }
    .about-content p { font-size: 1.1rem; }
    .stat-card { padding: 2rem 1.5rem; }
    .ref-card { padding: 2rem; }
    .gallery-item { padding: 1.5rem; }
    .cta-title { font-size: 2rem; }
    .cta-text { font-size: 1.2rem; }
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
    </div>
</header>

<section class="hero">
    <h1>Helmer Logistics S/A</h1>
    <p>Líder nacional em logística inteligente. Mais de 5.000 entregas realizadas com 98% de satisfação. Conectamos o Brasil com tecnologia, agilidade e confiança total.</p>
</section>

<section class="section">
    <div class="container">
        <h2 class="section-title">Excelência em Logística</h2>
        <div class="about-grid">
            <div class="about-content">
                <h3>🏆 Missão</h3>
                <p>Revolucionar a logística brasileira através de tecnologia avançada, entregas expressas e atendimento humanizado. Transformamos cada encomenda em uma experiência única de confiança e satisfação.</p>
                
                <h3>🚀 Visão</h3>
                <p>Ser a transportadora número 1 do Brasil até 2026, reconhecida pela inovação, pontualidade absoluta e compromisso inabalável com cada cliente. Investimos constantemente em nossa equipe e infraestrutura.</p>
                
                <h3>💎 Valores</h3>
                <p>Transparência total, Agilidade extrema, Segurança garantida, Inovação constante e Compromisso genuíno. Cada encomenda é tratada como única, com carinho profissional e tecnologia de ponta.</p>
            </div>
            <div class="about-image">
                <i class="fas fa-truck-fast"></i>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background: rgba(255,255,255,0.02);">
    <div class="container">
        <h2 class="section-title">Nossos Resultados</h2>
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
                <div class="stat-number">24/7</div>
                <div class="stat-label">Suporte Premium</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">98.7%</div>
                <div class="stat-label">Taxa de Satisfação</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">2</div>
                <div class="stat-label">Dias Entrega Express</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">100%</div>
                <div class="stat-label">Rastreamento em Tempo Real</div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2 class="section-title">Perguntas Frequentes</h2>
        <div class="faq-list">
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>Como rastrear minha entrega?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Acesse nosso site, insira o código de rastreamento fornecido e sua cidade de destino. Você terá acesso a todas as etapas do processo de entrega em tempo real.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>Qual o prazo de entrega?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    O prazo varia conforme a distância e modalidade escolhida. Entregas normais: 5-7 dias úteis. Com nosso sistema de indicações, a entrega é feita em apenas 2 dias com prioridade total.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>Como funciona o sistema de indicações?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Indique um amigo e, se ele comprar no mesmo dia, sua entrega será feita em apenas 2 dias com prioridade máxima. É nosso jeito de recompensar quem nos indica.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>O que fazer se houver atraso na entrega?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Entre em contato com nosso suporte imediatamente. Trabalhamos para resolver qualquer imprevisto com agilidade e transparência. Nossa equipe está disponível 24/7.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>Vocês aceitam devolução?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Sim, trabalhamos com o envio de devoluções. Entre em contato conosco para agendar a coleta e providenciar o reenvio da mercadoria.
                </div>
            </div>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>Como entro em contato com o suporte?</span>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    Nossa equipe de atendimento está disponível 24 horas por dia, 7 dias por semana. Entre em contato através do nosso site ou telefone de suporte.
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background: rgba(255,255,255,0.02);">
    <div class="container">
        <h2 class="section-title">Depoimentos Reais</h2>
        <div class="ref-grid">
            <div class="ref-card">
                <div class="ref-header">
                    <div class="ref-avatar">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div>
                        <div class="ref-name">Maria Silva</div>
                        <div class="ref-role">CEO - E-commerce Premium</div>
                    </div>
                </div>
                <div class="ref-stars">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                </div>
                <div class="ref-text">
                    "Helmer Logistics revolucionou meu negócio! Entregas em 2 dias, rastreamento em tempo real e zero problemas. Minha taxa de satisfação dos clientes subiu 40%. Simplesmente excepcional!"
                </div>
            </div>
            <div class="ref-card">
                <div class="ref-header">
                    <div class="ref-avatar">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div>
                        <div class="ref-name">João Santos</div>
                        <div class="ref-role">Empresário - Importações</div>
                    </div>
                </div>
                <div class="ref-stars">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                </div>
                <div class="ref-text">
                    "Sistema de indicações é genial! Ganhei entrega prioritária e economizei 60% no frete. Atendimento 24/7 resolve qualquer problema na hora. Helmer é sinônimo de confiança!"
                </div>
            </div>
            <div class="ref-card">
                <div class="ref-header">
                    <div class="ref-avatar">
                        <i class="fas fa-gem"></i>
                    </div>
                    <div>
                        <div class="ref-name">Ana Costa</div>
                        <div class="ref-role">Cliente VIP - 3 Anos</div>
                    </div>
                </div>
                <div class="ref-stars">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                </div>
                <div class="ref-text">
                    "3 anos usando Helmer e nunca tive uma entrega atrasada! Suporte premium 24/7, tecnologia de ponta e equipe que realmente se importa. Recomendo para toda minha família!"
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background: rgba(255,255,255,0.02);">
    <div class="container">
        <h2 class="section-title">Referências do Sistema em Uso</h2>
        <p style="text-align: center; color: rgba(255,255,255,0.7); font-size: 1.1rem; margin-bottom: 2rem;">
            Veja como nossos clientes utilizam o sistema de rastreamento através do WhatsApp
        </p>
        <div class="whatsapp-gallery">
            <div class="gallery-grid">
                <div class="gallery-item">
                    <div class="gallery-image">
                        <img src="assets/images/whatsapp-1.jpg?v=<?php echo time(); ?>" alt="WhatsApp Luiz Gabriel Petrópolis" 
                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                    </div>
                    <div class="gallery-info">
                        <h4>Luiz Gabriel - Petrópolis</h4>
                        <p>Sistema de rastreamento básico funcionando perfeitamente</p>
                        <div class="gallery-status">✅ Objeto Postado</div>
                        <div class="gallery-date">29/09/2025 - 14:26</div>
                    </div>
                </div>
                <div class="gallery-item">
                    <div class="gallery-image">
                        <img src="assets/images/whatsapp-2.jpg?v=<?php echo time(); ?>" alt="WhatsApp juuh santts Ubá" 
                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                    </div>
                    <div class="gallery-info">
                        <h4>juuh santts - Ubá</h4>
                        <p>Monitoramento oficial com status detalhado</p>
                        <div class="gallery-status">✅ Recebido no Ponto</div>
                        <div class="gallery-date">17/10/2025 - 15:31</div>
                    </div>
                </div>
                <div class="gallery-item">
                    <div class="gallery-image">
                        <img src="assets/images/whatsapp-3.jpg?v=<?php echo time(); ?>" alt="WhatsApp RKZIN Jardim Camburi" 
                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                    </div>
                    <div class="gallery-info">
                        <h4>RKZIN - Jardim Camburi</h4>
                        <p>Sistema oficial de monitoramento em tempo real</p>
                        <div class="gallery-status">✅ Objeto Postado</div>
                        <div class="gallery-date">18/10/2025 - 16:05</div>
                    </div>
                </div>
                <div class="gallery-item">
                    <div class="gallery-image">
                        <img src="assets/images/whatsapp-4.jpg?v=<?php echo time(); ?>" alt="WhatsApp Vitor João AdolfoSP" 
                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                    </div>
                    <div class="gallery-info">
                        <h4>Vitor João - AdolfoSP</h4>
                        <p>Monitoramento com interface integrada ao WhatsApp</p>
                        <div class="gallery-status">✅ Recebido no Ponto</div>
                        <div class="gallery-date">21/10/2025 - 14:49</div>
                    </div>
                </div>
                <div class="gallery-item">
                    <div class="gallery-image">
                        <img src="assets/images/whatsapp-5.jpg?v=<?php echo time(); ?>" alt="WhatsApp 2L CLIENTE Entrega" 
                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                    </div>
                    <div class="gallery-info">
                        <h4>2L CLIENTE - Entrega Confirmada</h4>
                        <p>Sistema de entrega e pagamento funcionando</p>
                        <div class="gallery-status">✅ Entrega Realizada</div>
                        <div class="gallery-date">24/10/2025 - 13:03</div>
                    </div>
                </div>
                <div class="gallery-item">
                    <div class="gallery-image">
                        <img src="assets/images/whatsapp-6.jpg?v=<?php echo time(); ?>" alt="WhatsApp Bada CLIENTE Go" 
                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                    </div>
                    <div class="gallery-info">
                        <h4>Bada CLIENTE - Go</h4>
                        <p>Sistema de Indicação + Rastreamento completo</p>
                        <div class="gallery-status">✅ Sistema Ativo</div>
                        <div class="gallery-date">26/10/2025 - 23:01</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <div class="cta-section">
        <h3 class="cta-title">Pronto para Começar?</h3>
        <p class="cta-text">Rastreie suas encomendas ou indicar amigos para ganhar benefícios exclusivos!</p>
        <a href="index.php" class="btn-cta">
            <i class="fas fa-rocket"></i> Rastrear Agora
        </a>
        <a href="indicacao.php" class="btn-cta" style="background: transparent; border: 2px solid var(--primary); margin-left: 1rem;">
            <i class="fas fa-users"></i> Indicar Amigos
        </a>
    </div>
</div>

<footer class="footer">
    <div class="container">
        <p>&copy; 2025 Helmer Logistics. Todos os direitos reservados.</p>
    </div>
</footer>

<script>
function toggleFaq(element) {
    const answer = element.nextElementSibling;
    const icon = element.querySelector('.faq-icon');
    const isActive = answer.classList.contains('active');
    
    // Close all FAQs
    document.querySelectorAll('.faq-answer').forEach(item => item.classList.remove('active'));
    document.querySelectorAll('.faq-icon').forEach(item => item.classList.remove('active'));
    
    // Open clicked FAQ if it wasn't active
    if (!isActive) {
        answer.classList.add('active');
        icon.classList.add('active');
    }
}
</script>

</body>
</html>
