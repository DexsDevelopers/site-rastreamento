import React from 'react';
import { QrCode, Satellite, Zap, ArrowRight, Truck, Package, MapPin, Clock, Shield, Heart, Menu, X } from 'lucide-react';
import { Link } from 'react-router-dom';

const ParaVoce: React.FC = () => {
    const [mobileMenu, setMobileMenu] = React.useState(false);

    return (
        <div className="pv-page">
            <style>{`
                .pv-page { background: #06060b; color: #fff; min-height: 100vh; position: relative; overflow-x: hidden; }
                .pv-page * { box-sizing: border-box; }
                .bg-mesh {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background:
                        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(99, 102, 241, 0.12), transparent),
                        radial-gradient(ellipse 50% 40% at 80% 50%, rgba(168, 85, 247, 0.06), transparent),
                        radial-gradient(ellipse 40% 30% at 20% 80%, rgba(6, 182, 212, 0.05), transparent);
                }
                .site-header {
                    position: sticky; top: 0; z-index: 100;
                    padding: 10px 24px;
                    background: transparent;
                }
                .header-glass {
                    max-width: 1280px; margin: 0 auto;
                    display: flex; justify-content: space-between; align-items: center;
                    padding: 14px 28px;
                    background: rgba(255, 255, 255, 0.03);
                    backdrop-filter: blur(24px) saturate(1.4);
                    -webkit-backdrop-filter: blur(24px) saturate(1.4);
                    border: 1px solid rgba(255,255,255,0.08);
                    border-radius: 20px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.04);
                }
                .logo-link { display: flex; align-items: center; gap: 10px; text-decoration: none; color: white; }
                .logo-box {
                    width: 38px; height: 38px;
                    background: linear-gradient(135deg, #6366f1, #a855f7);
                    border-radius: 12px;
                    display: flex; align-items: center; justify-content: center;
                    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
                }
                .logo-name { font-size: 1.4rem; font-weight: 800; font-family: 'Outfit', sans-serif; }
                .desktop-nav { display: flex; align-items: center; gap: 28px; }
                .nav-item { color: rgba(255,255,255,0.55); text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: color 0.2s; }
                .nav-item:hover { color: white; }
                .nav-item.active { color: #818cf8; }
                .nav-login-btn { padding: 10px 24px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 12px; color: white; text-decoration: none; font-weight: 700; font-size: 0.85rem; box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35); }
                .mobile-toggle { display: none; background: none; border: none; color: white; cursor: pointer; }
                .mobile-nav { display: flex; flex-direction: column; gap: 8px; padding: 12px 24px 20px; max-width: 1280px; margin: 4px auto 0; background: rgba(255,255,255,0.03); backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; }
                .mobile-nav a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 1rem; padding: 10px 0; font-weight: 500; }

                .page-hero {
                    position: relative; z-index: 1;
                    padding: 60px 24px 40px;
                    max-width: 1280px; margin: 0 auto;
                }
                .hero-glass {
                    padding: 60px 48px; border-radius: 32px;
                    background: rgba(255,255,255,0.02);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255,255,255,0.06);
                    text-align: center;
                    box-shadow: 0 16px 48px rgba(0,0,0,0.3);
                    position: relative; overflow: hidden;
                }
                .hero-glass::before {
                    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
                    background: linear-gradient(90deg, #6366f1, #a855f7, #22d3ee);
                }

                .services-section {
                    position: relative; z-index: 1;
                    padding: 0 24px 80px; max-width: 1280px; margin: 0 auto;
                }
                .services-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
                .service-card {
                    padding: 36px; border-radius: 24px;
                    background: rgba(255,255,255,0.02);
                    backdrop-filter: blur(16px);
                    border: 1px solid rgba(255,255,255,0.06);
                    display: flex; flex-direction: column; gap: 20px;
                    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                    transform-style: preserve-3d;
                }
                .service-card:hover {
                    background: rgba(255,255,255,0.05);
                    border-color: rgba(99, 102, 241, 0.3);
                    transform: translateY(-8px) rotateX(2deg);
                    box-shadow: 0 24px 48px rgba(0,0,0,0.4);
                }
                .service-icon {
                    width: 64px; height: 64px; border-radius: 20px;
                    display: flex; align-items: center; justify-content: center;
                }
                .service-card h3 { font-size: 1.3rem; font-weight: 700; }
                .service-card p { color: rgba(255,255,255,0.4); line-height: 1.7; font-size: 0.95rem; flex: 1; }
                .service-link {
                    color: #818cf8; text-decoration: none; font-weight: 700; font-size: 0.9rem;
                    display: inline-flex; align-items: center; gap: 6px; transition: gap 0.3s;
                }
                .service-link:hover { gap: 10px; color: #a5b4fc; }

                .benefits-section {
                    position: relative; z-index: 1;
                    padding: 80px 24px;
                    background: linear-gradient(180deg, transparent, rgba(99, 102, 241, 0.03), transparent);
                    border-top: 1px solid rgba(255,255,255,0.04);
                }
                .benefits-inner { max-width: 1280px; margin: 0 auto; }
                .benefits-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
                .benefit-card {
                    padding: 32px 24px; border-radius: 20px; text-align: center;
                    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);
                    transition: all 0.3s;
                }
                .benefit-card:hover { border-color: rgba(99, 102, 241, 0.25); transform: translateY(-4px); }
                .benefit-icon { margin: 0 auto 16px; width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; }
                .benefit-title { font-weight: 700; font-size: 1rem; margin-bottom: 8px; }
                .benefit-desc { color: rgba(255,255,255,0.4); font-size: 0.85rem; line-height: 1.5; }

                .site-footer {
                    position: relative; z-index: 1;
                    border-top: 1px solid rgba(255,255,255,0.04);
                    padding: 40px 24px 24px; text-align: center;
                    color: rgba(255,255,255,0.2); font-size: 0.8rem;
                }

                @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

                @media (max-width: 1024px) {
                    .services-grid { grid-template-columns: repeat(2, 1fr); }
                    .benefits-grid { grid-template-columns: repeat(2, 1fr); }
                }
                @media (max-width: 768px) {
                    .desktop-nav { display: none !important; }
                    .mobile-toggle { display: flex !important; }
                    .services-grid { grid-template-columns: 1fr; }
                    .benefits-grid { grid-template-columns: 1fr 1fr; }
                    .page-hero { padding: 50px 16px 40px; }
                    .services-section { padding: 0 16px 60px; }
                    .benefits-section { padding: 60px 16px; }
                }
                @media (max-width: 480px) {
                    .benefits-grid { grid-template-columns: 1fr; }
                }
            `}</style>

            <div className="bg-mesh"></div>

            {/* Header */}
            <header className="site-header">
                <div className="header-glass">
                    <Link to="/" className="logo-link">
                        <div className="logo-box"><Truck size={18} color="white" /></div>
                        <span className="logo-name">loggi</span>
                    </Link>
                    <nav className="desktop-nav" style={{ display: 'flex' }}>
                        <Link to="/" className="nav-item">Início</Link>
                        <Link to="/para-voce" className="nav-item active">Para você</Link>
                        <Link to="/para-empresas" className="nav-item">Para empresas</Link>
                        <Link to="/sobre" className="nav-item">Sobre</Link>
                        <Link to="/entrar" className="nav-login-btn">Entrar</Link>
                    </nav>
                    <button className="mobile-toggle" onClick={() => setMobileMenu(!mobileMenu)}>
                        {mobileMenu ? <X size={24} /> : <Menu size={24} />}
                    </button>
                </div>
                {mobileMenu && (
                    <nav className="mobile-nav">
                        <Link to="/" onClick={() => setMobileMenu(false)}>Início</Link>
                        <Link to="/para-voce" onClick={() => setMobileMenu(false)} style={{ color: '#818cf8', fontWeight: 700 }}>Para você</Link>
                        <Link to="/para-empresas" onClick={() => setMobileMenu(false)}>Para empresas</Link>
                        <Link to="/sobre" onClick={() => setMobileMenu(false)}>Sobre</Link>
                        <Link to="/entrar" onClick={() => setMobileMenu(false)}>Entrar</Link>
                    </nav>
                )}
            </header>

            {/* Hero */}
            <section className="page-hero" style={{ animation: 'fadeIn 0.7s ease' }}>
                <div className="hero-glass">
                    <div style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', padding: '8px 18px', background: 'rgba(99, 102, 241, 0.08)', border: '1px solid rgba(99, 102, 241, 0.2)', borderRadius: '100px', fontSize: '0.8rem', fontWeight: 600, color: '#a5b4fc', marginBottom: '24px', textTransform: 'uppercase' as const, letterSpacing: '0.05em' }}><Zap size={12} /> Soluções pessoais</div>
                    <h1 style={{ fontSize: 'clamp(2rem, 5vw, 3.2rem)', fontWeight: 900, lineHeight: 1.1, letterSpacing: '-2px', fontFamily: "'Outfit', sans-serif", marginBottom: '20px' }}>A Loggi entrega onde<br />você <span className="gradient-word">precisar</span></h1>
                    <p style={{ color: 'rgba(255,255,255,0.45)', fontSize: '1.05rem', lineHeight: 1.7, maxWidth: '600px', margin: '0 auto 32px' }}>Envie, rastreie e receba seus pacotes com a maior malha logística privada do Brasil à sua disposição.</p>
                    <Link to="/pedido" style={{ display: 'inline-flex', alignItems: 'center', gap: '10px', padding: '16px 36px', background: 'linear-gradient(135deg, #6366f1, #a855f7)', borderRadius: '16px', color: 'white', textDecoration: 'none', fontWeight: 700, fontSize: '1rem', boxShadow: '0 8px 32px rgba(99, 102, 241, 0.4)' }}><Package size={18} /> Enviar agora</Link>
                </div>
            </section>

            {/* Serviços */}
            <section className="services-section" style={{ animation: 'fadeIn 0.8s ease' }}>
                <div className="services-grid">
                    {[
                        { icon: <QrCode size={32} color="#818cf8" />, bg: 'rgba(99, 102, 241, 0.08)', title: 'Postagem simples', desc: 'Gere sua etiqueta em poucos cliques e poste em qualquer ponto parceiro próximo a você. Mais de 30 mil pontos disponíveis.', link: '/pedido', linkText: 'Começar agora' },
                        { icon: <Satellite size={32} color="#c084fc" />, bg: 'rgba(168, 85, 247, 0.08)', title: 'Monitoramento GPS', desc: 'Acompanhe cada curva da sua encomenda com tecnologia de rastreio via satélite. Notificações automáticas em tempo real.', link: '/', linkText: 'Rastrear pacote' },
                        { icon: <Zap size={32} color="#22d3ee" />, bg: 'rgba(6, 182, 212, 0.08)', title: 'Loggi Express', desc: 'Sua encomenda priorizada em nossa malha expressa para chegar ao destino em tempo recorde. Entrega em até 24h.', link: '#', linkText: 'Pedir urgência' },
                    ].map((c, i) => (
                        <div key={i} className="service-card">
                            <div className="service-icon" style={{ background: c.bg }}>{c.icon}</div>
                            <h3>{c.title}</h3>
                            <p>{c.desc}</p>
                            <Link to={c.link} className="service-link">{c.linkText} <ArrowRight size={14} /></Link>
                        </div>
                    ))}
                </div>
            </section>

            {/* Benefícios */}
            <section className="benefits-section">
                <div className="benefits-inner">
                    <h2 style={{ textAlign: 'center', fontSize: 'clamp(1.6rem, 3vw, 2.2rem)', fontWeight: 800, marginBottom: '48px', fontFamily: "'Outfit', sans-serif" }}>
                        Por que escolher a <span className="gradient-word">Loggi?</span>
                    </h2>
                    <div className="benefits-grid">
                        {[
                            { icon: <Clock size={24} color="#818cf8" />, bg: 'rgba(99, 102, 241, 0.08)', title: 'Entrega rápida', desc: 'Entregas em até 24h para envios locais e 3 dias úteis para todo o Brasil.' },
                            { icon: <Shield size={24} color="#c084fc" />, bg: 'rgba(168, 85, 247, 0.08)', title: 'Segurança total', desc: 'Seu pacote monitorado do início ao fim com seguro incluso.' },
                            { icon: <MapPin size={24} color="#22d3ee" />, bg: 'rgba(6, 182, 212, 0.08)', title: '+30 mil pontos', desc: 'A maior rede de pontos de postagem do Brasil.' },
                            { icon: <Package size={24} color="#34d399" />, bg: 'rgba(16, 185, 129, 0.08)', title: 'Frete grátis', desc: 'Para compras acima de R$ 49,90 em nossa plataforma.' },
                        ].map((b, i) => (
                            <div key={i} className="benefit-card">
                                <div className="benefit-icon" style={{ background: b.bg }}>{b.icon}</div>
                                <div className="benefit-title">{b.title}</div>
                                <div className="benefit-desc">{b.desc}</div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <footer className="site-footer">
                <p>© 2026 Loggi Tecnologia LTDA. Feito com <Heart size={12} fill="#ef4444" color="#ef4444" style={{ verticalAlign: 'middle' }} /> para o Brasil</p>
            </footer>
        </div>
    );
};

export default ParaVoce;
