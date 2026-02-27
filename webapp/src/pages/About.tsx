// src/pages/About.tsx
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Package, Menu, X, ArrowRight, TrendingUp, Users, Target } from 'lucide-react';

const About = () => {
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    return (
        <div className="about-page">
            <style>
                {`
                .about-page { background: var(--bg-primary); min-height: 100vh; font-family: 'Outfit', sans-serif; color: white; overflow-x: hidden; position: relative; }
                .bg-mesh { position: fixed; inset: 0; background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), radial-gradient(at 50% 0%, hsla(225,39%,30%,0.1) 0, transparent 50%), radial-gradient(at 100% 0%, hsla(339,49%,30%,0.1) 0, transparent 50%); z-index: 0; pointer-events: none; }
                
                .site-header { position: fixed; top: 0; left: 0; right: 0; padding: 20px 5%; z-index: 100; transition: all 0.3s; }
                .header-glass { background: rgba(10, 10, 12, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.05); padding: 12px 32px; border-radius: 24px; display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; box-shadow: 0 8px 32px rgba(0,0,0,0.2); }
                .logo-link { display: flex; align-items: center; gap: 12px; text-decoration: none; color: white; }
                .logo-box { width: 36px; height: 36px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4); }
                .logo-name { font-size: 1.4rem; font-weight: 800; }
                
                .desktop-nav { display: none; align-items: center; gap: 32px; }
                @media(min-width: 900px) { .desktop-nav { display: flex; } }
                .nav-item { color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: all 0.3s; }
                .nav-item:hover, .nav-item.active { color: white; }
                .nav-login-btn { padding: 10px 24px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 12px; color: white; text-decoration: none; font-weight: 700; font-size: 0.85rem; box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35); }
                
                .page-hero { padding: 160px 20px 100px; text-align: center; position: relative; z-index: 10; max-width: 900px; margin: 0 auto; }
                .hero-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 100px; font-size: 0.85rem; font-weight: 600; color: #818cf8; margin-bottom: 24px; }
                .hero-title { font-size: clamp(2.5rem, 5vw, 4.5rem); font-weight: 900; line-height: 1.1; letter-spacing: -2px; margin-bottom: 24px; }
                .hero-desc { color: rgba(255,255,255,0.6); font-size: 1.2rem; line-height: 1.7; margin: 0 auto 40px; }
                .gradient-word { background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                
                .story-grid { display: grid; grid-template-columns: 1fr; gap: 80px; max-width: 1200px; margin: 0 auto; padding: 40px 5%; position: relative; z-index: 10; }
                @media(min-width: 900px) { .story-grid { grid-template-columns: 1fr 1fr; align-items: center; } }
                
                .story-text h2 { font-size: 2.2rem; font-weight: 800; margin-bottom: 24px; }
                .story-text p { color: rgba(255,255,255,0.5); font-size: 1.1rem; line-height: 1.8; margin-bottom: 24px; }
                
                .glass-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 32px; padding: 40px; }
                
                .site-footer { text-align: center; padding: 40px 20px; color: rgba(255,255,255,0.3); font-size: 0.9rem; border-top: 1px solid rgba(255,255,255,0.05); margin-top: 80px;}
                .site-footer a { color: rgba(255,255,255,0.3); text-decoration: none; margin: 0 10px; transition: color 0.3s; }
                .site-footer a:hover { color: white; }
                `}
            </style>

            <div className="bg-mesh"></div>

            <header className="site-header">
                <div className="header-glass">
                    <Link to="/" className="logo-link">
                        <div className="logo-box">
                            <Package size={18} color="white" />
                        </div>
                        <span className="logo-name">loggi</span>
                    </Link>

                    <nav className="desktop-nav">
                        <Link to="/" className="nav-item">Início</Link>
                        <Link to="/para-voce" className="nav-item">Para você</Link>
                        <Link to="/para-empresas" className="nav-item">Para empresas</Link>
                        <Link to="/sobre" className="nav-item active">Sobre</Link>
                        <Link to="/entrar" className="nav-login-btn">Entrar</Link>
                    </nav>

                    <button className="mobile-toggle" onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}>
                        {isMobileMenuOpen ? <X size={24} /> : <Menu size={24} />}
                    </button>
                </div>
            </header>

            <section className="page-hero">
                <div className="hero-badge">A NOSSA JORNADA</div>
                <h1 className="hero-title">Redefinindo a <span className="gradient-word">logística<br />no Brasil</span></h1>
                <p className="hero-desc">
                    Tudo começou com uma ideia simples: por que a logística precisa ser complicada? Nossa missão é usar tecnologia para conectar pessoas e negócios através de entregas perfeitas.
                </p>
                <div style={{ display: 'flex', gap: '20px', justifyContent: 'center' }}>
                    <div className="glass-card" style={{ padding: '24px', flex: 1 }}>
                        <TrendingUp size={32} color="#818cf8" style={{ marginBottom: '16px' }} />
                        <h3 style={{ fontSize: '1.5rem', marginBottom: '8px' }}>1B+</h3>
                        <p style={{ color: 'rgba(255,255,255,0.5)', fontSize: '0.9rem' }}>Pacotes entregues</p>
                    </div>
                    <div className="glass-card" style={{ padding: '24px', flex: 1 }}>
                        <Users size={32} color="#c084fc" style={{ marginBottom: '16px' }} />
                        <h3 style={{ fontSize: '1.5rem', marginBottom: '8px' }}>50k+</h3>
                        <p style={{ color: 'rgba(255,255,255,0.5)', fontSize: '0.9rem' }}>Entregadores ativos</p>
                    </div>
                    <div className="glass-card" style={{ padding: '24px', flex: 1 }}>
                        <Target size={32} color="#34d399" style={{ marginBottom: '16px' }} />
                        <h3 style={{ fontSize: '1.5rem', marginBottom: '8px' }}>100%</h3>
                        <p style={{ color: 'rgba(255,255,255,0.5)', fontSize: '0.9rem' }}>De cobertura nacional</p>
                    </div>
                </div>
            </section>

            <section className="story-grid">
                <div className="story-text">
                    <h2>De uma startup para o coração do e-commerce.</h2>
                    <p>
                        Começamos como um pequeno serviço de motoboys em São Paulo. Vimos a dor das empresas que precisavam entregar no mesmo dia e de clientes cansados de esperar semanas por suas compras.
                    </p>
                    <p>
                        Construímos uma malha de ponta-a-ponta baseada em dados e software próprio. Hoje, nós decidimos a melhor rota para o caminhão e o caminho final da moto simultaneamente, garantindo a entrega perfeita.
                    </p>
                    <Link to="/para-empresas" className="nav-login-btn" style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', padding: '16px 28px', marginTop: '24px' }}>
                        Conheça os produtos <ArrowRight size={18} />
                    </Link>
                </div>
                <div style={{ position: 'relative', height: '100%', minHeight: '400px' }}>
                    <div style={{ position: 'absolute', inset: 0, borderRadius: '40px', background: 'url(https://images.unsplash.com/photo-1580674684081-7617fbf3d745?auto=format&fit=crop&q=80)', backgroundSize: 'cover', backgroundPosition: 'center', filter: 'grayscale(0.5)' }}></div>
                    <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(to top right, rgba(99,102,241,0.5), transparent)', borderRadius: '40px' }}></div>
                </div>
            </section>

            <footer className="site-footer">
                <p>&copy; 2026 Loggi Tecnologia LTDA.</p>
                <div style={{ marginTop: '16px' }}>
                    <Link to="/sobre">Sobre</Link>
                    <Link to="/para-voce">Para Você</Link>
                    <Link to="/para-empresas">Empresas</Link>
                    <Link to="/api-ecommerce">API</Link>
                    <Link to="/loggi-pro">Loggi Pro</Link>
                    <Link to="/carreiras">Carreiras</Link>
                    <Link to="/termos">Termos de Uso</Link>
                    <Link to="/ajuda">Ajuda</Link>
                </div>
            </footer>
        </div>
    );
};

export default About;
