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
}
body { font-family: 'Inter', sans-serif; background: var(--dark); color: var(--light); line-height: 1.6; }

/* Header */
.header { background: rgba(10, 10, 10, 0.95); backdrop-filter: blur(10px); 
          border-bottom: 1px solid rgba(255, 51, 51, 0.1); position: sticky; 
          top: 0; z-index: 1000; padding: 1rem 0; }
.nav-container { max-width: 1400px; margin: 0 auto; padding: 0 2rem; 
                 display: flex; justify-content: space-between; align-items: center; }
.logo { font-size: 1.5rem; font-weight: 800; background: var(--gradient); 
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
        text-decoration: none; }
.nav-links { display: flex; gap: 2rem; }
.nav-links a { color: rgba(255,255,255,0.8); text-decoration: none; 
               font-weight: 500; transition: 0.3s; }
.nav-links a:hover { color: var(--primary); }

/* Hero Section */
.hero { padding: 8rem 2rem; text-align: center; 
        background: linear-gradient(180deg, #0A0A0A 0%, #1A0000 100%); }
.hero h1 { font-size: 4rem; font-weight: 900; margin-bottom: 1rem;
           background: var(--gradient); -webkit-background-clip: text;
           -webkit-text-fill-color: transparent; }
.hero p { font-size: 1.5rem; color: rgba(255,255,255,0.7); max-width: 800px;
          margin: 0 auto; }

/* Content Sections */
.container { max-width: 1200px; margin: 0 auto; padding: 0 2rem; }
.section { padding: 5rem 0; }
.section-title { font-size: 3rem; font-weight: 800; margin-bottom: 2rem;
                 text-align: center; background: var(--gradient);
                 -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

/* About Section */
.about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; }
.about-content h3 { font-size: 2rem; color: var(--primary); margin-bottom: 1rem; }
.about-content p { font-size: 1.1rem; color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; }
.about-image { background: rgba(255,255,255,0.05); border-radius: 20px; 
               padding: 3rem; text-align: center; }
.about-image i { font-size: 8rem; background: var(--gradient);
                 -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

/* Stats */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
              gap: 2rem; margin-top: 4rem; }
.stat-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,51,51,0.2);
             border-radius: 20px; padding: 2rem; text-align: center;
             transition: transform 0.3s, box-shadow 0.3s; }
.stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(255,51,51,0.2); }
.stat-number { font-size: 3rem; font-weight: 800; background: var(--gradient);
               -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.stat-label { color: rgba(255,255,255,0.7); margin-top: 0.5rem; }

/* FAQ */
.faq-list { max-width: 900px; margin: 0 auto; }
.faq-item { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,51,51,0.2);
            border-radius: 15px; margin-bottom: 1rem; overflow: hidden; }
.faq-question { padding: 1.5rem; cursor: pointer; display: flex;
                justify-content: space-between; align-items: center;
                font-weight: 600; transition: 0.3s; }
.faq-question:hover { background: rgba(255,51,51,0.1); }
.faq-answer { padding: 0 1.5rem; max-height: 0; overflow: hidden;
              transition: max-height 0.3s ease; color: rgba(255,255,255,0.7); }
.faq-answer.active { padding: 1.5rem; max-height: 500px; }
.faq-icon { transition: transform 0.3s; }
.faq-icon.active { transform: rotate(180deg); }

/* References */
.ref-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem; margin-top: 3rem; }
.ref-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,51,51,0.2);
            border-radius: 15px; padding: 2rem; }
.ref-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
.ref-avatar { width: 60px; height: 60px; border-radius: 50%;
              background: var(--gradient); display: flex; align-items: center;
              justify-content: center; font-size: 1.5rem; color: white; }
.ref-name { font-weight: 600; font-size: 1.1rem; }
.ref-role { color: rgba(255,255,255,0.5); font-size: 0.9rem; }
.ref-stars { color: #ffcc00; margin-bottom: 1rem; }
.ref-text { color: rgba(255,255,255,0.8); font-style: italic; }

/* WhatsApp References Gallery */
.whatsapp-gallery { margin-top: 4rem; }
.gallery-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 2rem; margin-top: 2rem; }
.gallery-item { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,51,51,0.2);
                border-radius: 15px; padding: 1.5rem; transition: transform 0.3s, box-shadow 0.3s; }
.gallery-item:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(255,51,51,0.2); }
.gallery-image { width: 100%; height: 200px; background: rgba(255,255,255,0.1);
                 border-radius: 10px; margin-bottom: 1rem; display: flex;
                 align-items: center; justify-content: center; position: relative; overflow: hidden;
                 border: 2px solid rgba(255,51,51,0.3); }
.gallery-image img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
.gallery-image::before { content: '📱'; font-size: 3rem; opacity: 0.3; position: absolute; 
                       z-index: 1; display: none; }
.gallery-image::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
                       background: rgba(0,0,0,0.3); z-index: 2; display: none; }
.gallery-info h4 { color: var(--primary); font-size: 1.1rem; margin-bottom: 0.5rem; }
.gallery-info p { color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-bottom: 0.5rem; }
.gallery-status { background: rgba(22,163,74,0.2); border: 1px solid rgba(22,163,74,0.3);
                 border-radius: 8px; padding: 0.5rem; font-size: 0.8rem;
                 color: #16A34A; text-align: center; }
.gallery-date { color: rgba(255,255,255,0.5); font-size: 0.8rem; text-align: center; margin-top: 0.5rem; }

/* CTA Section */
.cta-section { background: linear-gradient(135deg, rgba(255,51,51,0.1), rgba(255,102,0,0.1));
               border: 2px solid var(--primary); border-radius: 30px;
               padding: 4rem 2rem; text-align: center; margin: 5rem 0; }
.cta-title { font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem;
             background: var(--gradient); -webkit-background-clip: text;
             -webkit-text-fill-color: transparent; }
.cta-text { font-size: 1.2rem; color: rgba(255,255,255,0.7); margin-bottom: 2rem; }
.btn-cta { padding: 1rem 3rem; background: var(--gradient); color: white;
           border: none; border-radius: 12px; font-size: 1.1rem;
           font-weight: 600; cursor: pointer; text-decoration: none;
           display: inline-block; transition: transform 0.3s, box-shadow 0.3s; }
.btn-cta:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(255,51,51,0.4); }

/* Footer */
.footer { background: #0A0A0A; padding: 3rem 0; text-align: center;
          border-top: 1px solid rgba(255,51,51,0.1); }
.footer p { color: rgba(255,255,255,0.5); }

/* Responsive */
@media (max-width: 1024px) {
    .hero h1 { font-size: 3rem; }
    .about-grid { grid-template-columns: 1fr; }
    .nav-links { gap: 1rem; }
}
@media (max-width: 768px) {
    .hero h1 { font-size: 2.5rem; }
    .hero p { font-size: 1.2rem; }
    .section-title { font-size: 2rem; }
    .nav-links { display: none; }
    .about-image i { font-size: 5rem; }
    .stat-number { font-size: 2rem; }
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
    <h1>Quem Somos</h1>
    <p>Referência em logística e entrega no Brasil, conectando pessoas e empresas com agilidade e segurança.</p>
</section>

<section class="section">
    <div class="container">
        <h2 class="section-title">Nossa História</h2>
        <div class="about-grid">
            <div class="about-content">
                <h3>Missão</h3>
                <p>Entregar soluções logísticas de excelência, conectando pessoas e negócios com agilidade, segurança e inovação. Transformamos a forma como você recebe suas encomendas.</p>
                
                <h3>Visão</h3>
                <p>Ser a transportadora de referência nacional, reconhecida pela qualidade, pontualidade e compromisso com cada entrega. Estamos sempre investindo em tecnologia e em nosso time.</p>
                
                <h3>Valores</h3>
                <p>Agilidade, Segurança, Transparência, Inovação e Compromisso com o cliente. Cada encomenda é tratada com carinho e profissionalismo.</p>
            </div>
            <div class="about-image">
                <i class="fas fa-truck-fast"></i>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background: rgba(255,255,255,0.02);">
    <div class="container">
        <h2 class="section-title">Nossos Números</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">5K+</div>
                <div class="stat-label">Entregas Realizadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">150+</div>
                <div class="stat-label">Cidades Atendidas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Atendimento</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">98%</div>
                <div class="stat-label">Satisfação do Cliente</div>
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
        <h2 class="section-title">O Que Nossos Clientes Dizem</h2>
        <div class="ref-grid">
            <div class="ref-card">
                <div class="ref-header">
                    <div class="ref-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="ref-name">Maria Silva</div>
                        <div class="ref-role">E-commerce</div>
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
                    "Melhor transportadora que já trabalhei! Entregas rápidas, rastreamento preciso e atendimento impecável. Recomendo de olhos fechados."
                </div>
            </div>
            <div class="ref-card">
                <div class="ref-header">
                    <div class="ref-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="ref-name">João Santos</div>
                        <div class="ref-role">Empresário</div>
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
                    "Sistema de indicações funcionou perfeitamente! Ganhei entrega prioritária e fiquei impressionado com a qualidade do serviço."
                </div>
            </div>
            <div class="ref-card">
                <div class="ref-header">
                    <div class="ref-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="ref-name">Ana Costa</div>
                        <div class="ref-role">Cliente VIP</div>
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
                    "Atendimento 24/7 faz toda diferença! Sempre que preciso, são rápidos e resolvem tudo com profissionalismo. Parabéns Helmer!"
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
