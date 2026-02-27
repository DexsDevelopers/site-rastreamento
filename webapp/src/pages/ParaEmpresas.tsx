import React from 'react';
import { Warehouse, GitBranch, RotateCcw, Truck, TrendingUp, Layers, Heart, Menu, X } from 'lucide-react';
import { Link } from 'react-router-dom';

const ParaEmpresas: React.FC = () => {
    const [mobileMenu, setMobileMenu] = React.useState(false);

    return (
        <div className="pe-page">
            <style>{`
                .pe-page { background: #06060b; color: #fff; min-height: 100vh; position: relative; overflow-x: hidden; }
                .pe-page * { box-sizing: border-box; }
                .bg-mesh {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background:
                        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(168, 85, 247, 0.12), transparent),
                        radial-gradient(ellipse 50% 40% at 20% 60%, rgba(99, 102, 241, 0.06), transparent),
                        radial-gradient(ellipse 60% 30% at 80% 80%, rgba(6, 182, 212, 0.05), transparent);
                }
                .site-header {
                    position: sticky; top: 0; z-index: 100;
                    padding: 10px 24px;
                    background: transparent;
                }
                .header-glass {
                    max-width: 1280px; margin: 0 auto;
                    display: flex; justify-content: space-between; align-items: center;
                    padding: 14px 24px;
                    background: rgba(255, 255, 255, 0.03);
                    backdrop-filter: blur(24px) saturate(1.4);
                    -webkit-backdrop-filter: blur(24px) saturate(1.4);
                    border: 1px solid rgba(255,255,255,0.08);
                    border-radius: 20px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
                }
                .logo-link { display: flex; align-items: center; gap: 10px; text-decoration: none; color: white; }
                .logo-box { width: 38px; height: 38px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4); }
                .logo-name { font-size: 1.4rem; font-weight: 800; font-family: 'Outfit', sans-serif; }
                .desktop-nav { display: flex; align-items: center; gap: 28px; }
                .nav-item { color: rgba(255,255,255,0.55); text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: color 0.2s; }
                .nav-item:hover { color: white; }
                .nav-item.active { color: #c084fc; }
                .nav-login-btn { padding: 10px 24px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 12px; color: white; text-decoration: none; font-weight: 700; font-size: 0.85rem; }
                .mobile-toggle { display: none; background: none; border: none; color: white; cursor: pointer; }
                .mobile-nav { display: flex; flex-direction: column; gap: 8px; padding: 12px 24px 20px; max-width: 1280px; margin: 4px auto 0; background: rgba(255,255,255,0.03); backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; }
                .mobile-nav a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 1rem; padding: 10px 0; font-weight: 500; }

                .page-hero {
                    position: relative; z-index: 1;
                    padding: 60px 24px 40px; max-width: 1280px; margin: 0 auto;
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
                .page-badge {
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 8px 18px;
                    background: rgba(168, 85, 247, 0.08);
                    border: 1px solid rgba(168, 85, 247, 0.2);
                    border-radius: 100px;
                    font-size: 0.8rem; font-weight: 600; color: #c4b5fd;
                    margin-bottom: 24px; text-transform: uppercase; letter-spacing: 0.05em;
                }
                .page-title {
                    font-size: clamp(2rem, 5vw, 3.2rem);
                    font-weight: 900; line-height: 1.1; letter-spacing: -2px;
                    font-family: 'Outfit', sans-serif; margin-bottom: 20px;
                }
                .gradient-word {
                    background: linear-gradient(135deg, #818cf8, #c084fc, #22d3ee);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
                }
                .page-desc { color: rgba(255,255,255,0.45); font-size: 1.05rem; line-height: 1.7; max-width: 600px; margin: 0 auto 32px; }
                .hero-cta { display: inline-flex; align-items: center; gap: 10px; padding: 16px 36px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 16px; color: white; text-decoration: none; font-weight: 700; font-size: 1rem; box-shadow: 0 8px 32px rgba(99, 102, 241, 0.4); transition: all 0.3s; }
                .hero-cta:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(99, 102, 241, 0.5); }

                .content-section { position: relative; z-index: 1; padding: 40px 24px 80px; max-width: 1280px; margin: 0 auto; }
                .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
                .service-card {
                    padding: 36px; border-radius: 24px;
                    background: rgba(255,255,255,0.02); backdrop-filter: blur(16px);
                    border: 1px solid rgba(255,255,255,0.06);
                    display: flex; flex-direction: column; gap: 20px;
                    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                    transform-style: preserve-3d;
                }
                .service-card:hover { background: rgba(255,255,255,0.05); border-color: rgba(168, 85, 247, 0.3); transform: translateY(-8px) rotateX(2deg); box-shadow: 0 24px 48px rgba(0,0,0,0.4); }
                .service-icon { width: 64px; height: 64px; border-radius: 20px; display: flex; align-items: center; justify-content: center; }
                .service-card h3 { font-size: 1.3rem; font-weight: 700; }
                .service-card p { color: rgba(255,255,255,0.4); line-height: 1.7; font-size: 0.95rem; flex: 1; }

                .stats-section { position: relative; z-index: 1; padding: 40px 24px 80px; max-width: 1280px; margin: 0 auto; }
                .stats-title-glass {
                    padding: 32px 40px; border-radius: 24px;
                    background: rgba(255,255,255,0.02); backdrop-filter: blur(20px);
                    border: 1px solid rgba(255,255,255,0.06);
                    text-align: center; margin-bottom: 32px;
                }
                .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
                .stat-item {
                    padding: 28px 20px; border-radius: 20px; text-align: center;
                    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);
                    transition: all 0.3s;
                }
                .stat-item:hover { border-color: rgba(168, 85, 247, 0.25); transform: translateY(-4px); }
                .stat-val { font-size: 2rem; font-weight: 900; font-family: 'Outfit', sans-serif; background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
                .stat-label { color: rgba(255,255,255,0.4); font-size: 0.85rem; margin-top: 6px; }

                .site-footer { position: relative; z-index: 1; border-top: 1px solid rgba(255,255,255,0.04); padding: 40px 24px 24px; text-align: center; color: rgba(255,255,255,0.2); font-size: 0.8rem; }

                @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

                @media (max-width: 1024px) { .grid-3 { grid-template-columns: repeat(2, 1fr); } .stats-grid { grid-template-columns: repeat(2, 1fr); } }
                @media (max-width: 768px) {
                    .desktop-nav { display: none !important; }
                    .mobile-toggle { display: flex !important; }
                    .grid-3 { grid-template-columns: 1fr; }
                    .stats-grid { grid-template-columns: 1fr 1fr; }
                    .hero-glass { padding: 40px 24px; }
                    .stats-title-glass { padding: 24px 20px; }
                }
                @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
            `}</style>

            <div className="bg-mesh"></div>

            <header className="site-header">
                <div className="header-glass">
                    <Link to="/" className="logo-link">
                        <div className="logo-box"><Truck size={18} color="white" /></div>
                        <span className="logo-name">loggi</span>
                    </Link>
                    <nav className="desktop-nav" style={{ display: 'flex' }}>
                        <Link to="/" className="nav-item">Início</Link>
                        <Link to="/para-voce" className="nav-item">Para você</Link>
                        <Link to="/para-empresas" className="nav-item active">Para empresas</Link>
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
                        <Link to="/para-voce" onClick={() => setMobileMenu(false)}>Para você</Link>
                        <Link to="/para-empresas" onClick={() => setMobileMenu(false)} style={{ color: '#c084fc', fontWeight: 700 }}>Para empresas</Link>
                        <Link to="/sobre" onClick={() => setMobileMenu(false)}>Sobre</Link>
                        <Link to="/entrar" onClick={() => setMobileMenu(false)}>Entrar</Link>
                    </nav>
                )}
            </header>

            <section className="page-hero" style={{ animation: 'fadeIn 0.7s ease' }}>
                <div className="hero-glass">
                    <div className="page-badge"><TrendingUp size={12} /> Para negócios</div>
                    <h1 className="page-title">Logística inteligente<br />para <span className="gradient-word">negócios</span></h1>
                    <p className="page-desc">Potencialize suas vendas com a malha logística que mais cresce no país. API completa, coleta dedicada e gestão simplificada.</p>
                    <a href="#" className="hero-cta"><Layers size={18} /> Fale com um consultor</a>
                </div>
            </section>

            <section className="content-section" style={{ animation: 'fadeIn 0.8s ease' }}>
                <div className="grid-3">
                    {[
                        { icon: <Warehouse size={32} color="#818cf8" />, bg: 'rgba(99, 102, 241, 0.08)', title: 'Coleta dedicada', desc: 'Equipe presente no seu centro de distribuição para coletar e processar seus envios diariamente. Sem filas, sem burocracia.' },
                        { icon: <GitBranch size={32} color="#c084fc" />, bg: 'rgba(168, 85, 247, 0.08)', title: 'API de Integração', desc: 'Conecte seu e-commerce diretamente ao nosso sistema com API RESTful completa. Documentação clara e suporte técnico dedicado.' },
                        { icon: <RotateCcw size={32} color="#22d3ee" />, bg: 'rgba(6, 182, 212, 0.08)', title: 'Reversa Facilitada', desc: 'Gestão completa de trocas e devoluções. Seu cliente solicita, a gente coleta. Pós-venda que encanta e fideliza.' },
                    ].map((c, i) => (
                        <div key={i} className="service-card">
                            <div className="service-icon" style={{ background: c.bg }}>{c.icon}</div>
                            <h3>{c.title}</h3>
                            <p>{c.desc}</p>
                        </div>
                    ))}
                </div>
            </section>

            <section className="stats-section">
                <div className="stats-title-glass">
                    <h2 style={{ fontSize: 'clamp(1.6rem, 3vw, 2rem)', fontWeight: 800, fontFamily: "'Outfit', sans-serif" }}>
                        Números que <span className="gradient-word">impressionam</span>
                    </h2>
                </div>
                <div className="stats-grid">
                    {[
                        { val: '10M+', label: 'Entregas/mês' },
                        { val: '4.5k+', label: 'Cidades atendidas' },
                        { val: '99.2%', label: 'SLA de entrega' },
                        { val: '<2s', label: 'Tempo de cotação API' },
                    ].map((s, i) => (
                        <div key={i} className="stat-item">
                            <div className="stat-val">{s.val}</div>
                            <div className="stat-label">{s.label}</div>
                        </div>
                    ))}
                </div>
            </section>

            <footer className="site-footer">
                <p>© 2026 Loggi Tecnologia LTDA. Feito com <Heart size={12} fill="#ef4444" color="#ef4444" style={{ verticalAlign: 'middle' }} /> para o Brasil</p>
            </footer>
        </div>
    );
};

export default ParaEmpresas;
