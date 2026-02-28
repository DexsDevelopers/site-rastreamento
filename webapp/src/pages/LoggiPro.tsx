import { useEffect } from 'react';
import { Shield, Star, Rocket, RefreshCcw } from 'lucide-react';
import Header from '../components/Header';
import Footer from '../components/Footer';

const LoggiPro = () => {
    useEffect(() => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal-active');
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
        return () => observer.disconnect();
    }, []);

    return (
        <div className="lp-page">
            <style>
                {`
                .lp-page { background: var(--bg-primary); min-height: 100vh; font-family: 'Outfit', sans-serif; color: white; position: relative; overflow-x: hidden; }
                .bg-mesh { position: fixed; inset: 0; background-image: radial-gradient(at 0% 100%, hsla(339,49%,20%,0.2) 0, transparent 50%), radial-gradient(at 100% 0%, hsla(225,39%,20%,0.2) 0, transparent 50%); z-index: 0; pointer-events: none; }
                .page-hero { padding: 160px 5% 80px; text-align: center; position: relative; z-index: 10; }
                .gradient-word { background: linear-gradient(135deg, #f87171, #fb923c); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                .page-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 100px; font-size: 0.8rem; font-weight: 600; color: #f87171; margin-bottom: 24px; text-transform: uppercase; letter-spacing: 0.05em; }
                .page-title { font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; line-height: 1.1; letter-spacing: -2px; margin-bottom: 24px; }
                .page-desc { color: rgba(255,255,255,0.45); font-size: 1.1rem; line-height: 1.7; max-width: 700px; margin: 0 auto 40px; }
                
                .pricing-cards { display: grid; grid-template-columns: 1fr; gap: 32px; max-width: 1000px; margin: 60px auto; position: relative; z-index: 10; padding: 0 5%; }
                @media(min-width: 768px) { .pricing-cards { grid-template-columns: repeat(2, 1fr); align-items: center; } }
                
                .price-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 40px; border-radius: 32px; scale: 0.95; transition: transform 0.3s; }
                .price-card.pro { background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.3); scale: 1.05; box-shadow: 0 20px 80px rgba(239, 68, 68, 0.15); }
                .price-card h3 { font-size: 1.8rem; margin-bottom: 8px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
                .price-card .price { font-size: 3rem; font-weight: 900; margin: 24px 0; }
                .price-card .price span { font-size: 1rem; color: rgba(255,255,255,0.4); font-weight: 500; }
                
                .features-list { list-style: none; padding: 0; margin: 32px 0; }
                .features-list li { margin-bottom: 16px; display: flex; align-items: center; gap: 12px; color: rgba(255,255,255,0.8); }
                
                .site-footer { text-align: center; padding: 40px 20px; color: rgba(255,255,255,0.3); font-size: 0.9rem; border-top: 1px solid rgba(255,255,255,0.05); margin-top: 80px;}

                .reveal { opacity: 0; transform: translateY(30px); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
                .reveal-active { opacity: 1; transform: translateY(0); }
                .delay-1 { transition-delay: 0.1s; }
                .delay-2 { transition-delay: 0.2s; }
                
                `}
            </style>

            <div className="bg-mesh"></div>

            <Header />

            <section className="page-hero reveal">
                <div className="page-badge">
                    <Star size={12} /> Assinatura Premium
                </div>
                <h1 className="page-title">Assine o <span className="gradient-word">Loggi Pro</span></h1>
                <p className="page-desc">
                    O clube de benefícios oficial para quem envia e recebe pacotes todos os dias. Tenha fretes com desconto massivo e prioridade total na malha.
                </p>
            </section>

            <div className="pricing-cards reveal delay-1">
                <div className="price-card">
                    <h3>Padrão</h3>
                    <p style={{ color: "rgba(255,255,255,0.5)" }}>Para envios casuais e compradores finais.</p>
                    <div className="price">Grátis</div>

                    <ul className="features-list">
                        <li><Shield size={18} color="#9ca3af" /> Rastreio na Web</li>
                        <li><Shield size={18} color="#9ca3af" /> Postagem em agências parceiras</li>
                        <li><Shield size={18} color="#9ca3af" /> Suporte em até 48h</li>
                        <li style={{ opacity: 0.3 }}><Shield size={18} color="#9ca3af" /> Descontos em Frete</li>
                    </ul>
                    <button className="nav-login-btn" style={{ width: '100%', padding: '16px', background: 'rgba(255,255,255,0.1)', boxShadow: 'none' }}>
                        Plano Atual
                    </button>
                </div>

                <div className="price-card pro">
                    <div className="page-badge" style={{ marginBottom: '16px', background: 'rgba(239, 68, 68, 0.2)' }}>
                        <Rocket size={12} /> Mais Popular
                    </div>
                    <h3>Loggi Pro</h3>
                    <p style={{ color: "rgba(255,255,255,0.5)" }}>Para e-commerces e envio de grande volume.</p>
                    <div className="price">R$ 59<span>/mês</span></div>

                    <ul className="features-list">
                        <li><Star size={18} color="#f87171" /> <strong>Tudo do Padrão, mais:</strong></li>
                        <li><Shield size={18} color="#f87171" /> Até 25% de desconto no frete nacional</li>
                        <li><RefreshCcw size={18} color="#f87171" /> API Express liberada</li>
                        <li><Shield size={18} color="#f87171" /> Gerente de conta exclusivo via WhatsApp</li>
                        <li><Shield size={18} color="#f87171" /> Emissão de múltiplas etiquetas</li>
                    </ul>
                    <button className="nav-login-btn" style={{ width: '100%', padding: '16px', fontSize: '1.1rem' }}>
                        Assinar o Pro Agora
                    </button>
                </div>
            </div>

            <Footer />
        </div>
    );
};

export default LoggiPro;
