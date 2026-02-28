import { Shield, Star, Rocket, RefreshCcw } from 'lucide-react';
import Header from '../components/Header';
import Footer from '../components/Footer';

const LoggiPro = () => {

    return (
        <div className="lp-page">
            <style>
                {`
                .lp-page { background: var(--bg-primary); min-height: 100vh; font-family: 'Outfit', sans-serif; color: white; position: relative; overflow-x: hidden; }
                .bg-mesh { position: fixed; inset: 0; background-image: radial-gradient(at 0% 100%, hsla(339,49%,20%,0.2) 0, transparent 50%), radial-gradient(at 100% 0%, hsla(225,39%,20%,0.2) 0, transparent 50%); z-index: 0; pointer-events: none; }
                .site-header { position: fixed; top: 0; left: 0; right: 0; padding: 20px 5%; z-index: 100; transition: all 0.3s; }
                .header-glass { background: rgba(10, 10, 12, 0.6); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.05); padding: 12px 32px; border-radius: 24px; display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; box-shadow: 0 8px 32px rgba(0,0,0,0.2); }
                .logo-link { display: flex; align-items: center; gap: 12px; text-decoration: none; color: white; }
                .logo-box { width: 36px; height: 36px; background: linear-gradient(135deg, #ef4444, #f97316); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(239, 68, 68, 0.4); }
                .logo-name { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px; }
                .desktop-nav { display: none; align-items: center; gap: 32px; }
                @media(min-width: 900px) { .desktop-nav { display: flex; } }
                .nav-item { color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: all 0.3s; }
                .nav-item:hover, .nav-item.active { color: white; }
                .nav-login-btn { padding: 10px 24px; background: linear-gradient(135deg, #ef4444, #f97316); border-radius: 12px; color: white; text-decoration: none; font-weight: 700; font-size: 0.85rem; box-shadow: 0 4px 16px rgba(239, 68, 68, 0.3); }
                .mobile-toggle { display: block; background: none; border: none; color: white; cursor: pointer; padding: 8px; }
                @media(min-width: 900px) { .mobile-toggle { display: none; } }
                .mobile-nav { position: absolute; top: calc(100% + 10px); left: 5%; right: 5%; background: rgba(10, 10, 12, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 24px; display: flex; flexDirection: column; gap: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
                .mobile-nav a { color: white; text-decoration: none; font-size: 1.1rem; font-weight: 600; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
                .mobile-nav a:last-child { border-bottom: none; padding-bottom: 0; }
                
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
                .site-footer a { color: rgba(255,255,255,0.3); text-decoration: none; margin: 0 10px; transition: color 0.3s; }
                .site-footer a:hover { color: white; }
                `}
            </style>

            <div className="bg-mesh"></div>

            <Header />

            <section className="page-hero">
                <div className="page-badge">
                    <Star size={12} /> Assinatura Premium
                </div>
                <h1 className="page-title">Assine o <span className="gradient-word">Loggi Pro</span></h1>
                <p className="page-desc">
                    O clube de benefícios oficial para quem envia e recebe pacotes todos os dias. Tenha fretes com desconto massivo e prioridade total na malha.
                </p>
            </section>

            <div className="pricing-cards">
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
