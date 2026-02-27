// src/pages/ApiEcommerce.tsx
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Package, Menu, X, Code, Terminal, Zap, ShieldCheck } from 'lucide-react';

const ApiEcommerce = () => {
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    return (
        <div className="pe-page">
            <style>
                {`
                .pe-page { background: var(--bg-primary); min-height: 100vh; font-family: 'Outfit', sans-serif; color: white; position: relative; overflow-x: hidden; }
                .bg-mesh { position: fixed; inset: 0; background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), radial-gradient(at 50% 0%, hsla(225,39%,30%,0.1) 0, transparent 50%), radial-gradient(at 100% 0%, hsla(339,49%,30%,0.1) 0, transparent 50%); z-index: 0; pointer-events: none; }
                .site-header { position: fixed; top: 0; left: 0; right: 0; padding: 20px 5%; z-index: 100; transition: all 0.3s; }
                .header-glass { background: rgba(10, 10, 12, 0.6); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.05); padding: 12px 32px; border-radius: 24px; display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; box-shadow: 0 8px 32px rgba(0,0,0,0.2); }
                .logo-link { display: flex; align-items: center; gap: 12px; text-decoration: none; color: white; }
                .logo-box { width: 36px; height: 36px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4); }
                .logo-name { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px; }
                .desktop-nav { display: none; align-items: center; gap: 32px; }
                @media(min-width: 900px) { .desktop-nav { display: flex; } }
                .nav-item { color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: all 0.3s; }
                .nav-item:hover, .nav-item.active { color: white; }
                .nav-login-btn { padding: 10px 24px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 12px; color: white; text-decoration: none; font-weight: 700; font-size: 0.85rem; }
                .mobile-toggle { display: block; background: none; border: none; color: white; cursor: pointer; padding: 8px; }
                @media(min-width: 900px) { .mobile-toggle { display: none; } }
                .mobile-nav { position: absolute; top: calc(100% + 10px); left: 5%; right: 5%; background: rgba(10, 10, 12, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 24px; display: flex; flexDirection: column; gap: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
                .mobile-nav a { color: white; text-decoration: none; font-size: 1.1rem; font-weight: 600; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
                .mobile-nav a:last-child { border-bottom: none; padding-bottom: 0; }
                
                .page-hero { padding: 160px 5% 80px; text-align: center; position: relative; z-index: 10; }
                .hero-glass { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 40px; padding: 60px 20px; max-width: 1000px; margin: 0 auto; backdrop-filter: blur(10px); position: relative; overflow: hidden; }
                .gradient-word { background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                .page-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; background: rgba(168, 85, 247, 0.1); border: 1px solid rgba(168, 85, 247, 0.2); border-radius: 100px; font-size: 0.8rem; font-weight: 600; color: #c084fc; margin-bottom: 24px; text-transform: uppercase; letter-spacing: 0.05em; }
                .page-title { font-size: clamp(2.5rem, 5vw, 4rem); font-weight: 900; line-height: 1.1; letter-spacing: -2px; margin-bottom: 24px; }
                .page-desc { color: rgba(255,255,255,0.45); font-size: 1.1rem; line-height: 1.7; max-width: 600px; margin: 0 auto 40px; }
                
                .content-section { padding: 40px 5% 80px; max-width: 1200px; margin: 0 auto; position: relative; z-index: 10; }
                .grid-2 { display: grid; grid-template-columns: 1fr; gap: 32px; }
                @media(min-width: 768px) { .grid-2 { grid-template-columns: repeat(2, 1fr); } }
                
                .api-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 40px; border-radius: 32px; transition: transform 0.3s; }
                .api-card:hover { transform: translateY(-5px); background: rgba(255,255,255,0.03); }
                .api-icon { width: 64px; height: 64px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin-bottom: 24px; }
                .api-card h3 { font-size: 1.5rem; margin-bottom: 16px; font-weight: 800; }
                .api-card p { color: rgba(255,255,255,0.45); line-height: 1.7; }
                
                .code-block { background: rgba(0,0,0,0.5); padding: 24px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); font-family: monospace; color: #a5b4fc; text-align: left; overflow-x: auto; margin-top: 40px; }
                
                .site-footer { text-align: center; padding: 40px 20px; color: rgba(255,255,255,0.3); font-size: 0.9rem; border-top: 1px solid rgba(255,255,255,0.05); }
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
                        <Link to="/sobre" className="nav-item">Sobre</Link>
                        <Link to="/entrar" className="nav-login-btn">Entrar</Link>
                    </nav>

                    <button className="mobile-toggle" onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}>
                        {isMobileMenuOpen ? <X size={24} /> : <Menu size={24} />}
                    </button>
                </div>

                {isMobileMenuOpen && (
                    <nav className="mobile-nav">
                        <Link to="/" onClick={() => setIsMobileMenuOpen(false)}>Início</Link>
                        <Link to="/para-voce" onClick={() => setIsMobileMenuOpen(false)}>Para você</Link>
                        <Link to="/para-empresas" onClick={() => setIsMobileMenuOpen(false)}>Para empresas</Link>
                        <Link to="/sobre" onClick={() => setIsMobileMenuOpen(false)}>Sobre</Link>
                        <Link to="/entrar" onClick={() => setIsMobileMenuOpen(false)} style={{ color: '#c084fc' }}>Entrar</Link>
                    </nav>
                )}
            </header>

            <section className="page-hero">
                <div className="hero-glass">
                    <div className="page-badge">
                        <Code size={12} /> Para Desenvolvedores
                    </div>
                    <h1 className="page-title">Integração <span className="gradient-word">Perfeita</span></h1>
                    <p className="page-desc">
                        Conecte seu sistema à maior malha logística do país em minutos. Nossa API RESTful foi desenhada para desenvolvedores apaixonados por performance.
                    </p>
                    <a href="#" className="nav-login-btn" style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', padding: '16px 32px', fontSize: '1rem' }}>
                        <Terminal size={18} /> Ver Documentação (Docs)
                    </a>

                    <div className="code-block">
                        <pre>
                            {`fetch('https://api.loggi.com/v1/shipping/calculate', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer YOUR_API_KEY',
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        origin: '01001-000',
        destination: '20040-002',
        weight: 1.5
    })
})`}
                        </pre>
                    </div>
                </div>
            </section>

            <section className="content-section">
                <div className="grid-2">
                    <div className="api-card">
                        <div className="api-icon" style={{ background: 'rgba(99, 102, 241, 0.1)' }}>
                            <Zap size={32} color="#818cf8" />
                        </div>
                        <h3>Milisegundos de Latência</h3>
                        <p>Infraestrutura global distribuída para garantir respostas em tempo real para cálculo de frete no checkout do seu e-commerce.</p>
                    </div>
                    <div className="api-card">
                        <div className="api-icon" style={{ background: 'rgba(168, 85, 247, 0.1)' }}>
                            <ShieldCheck size={32} color="#c084fc" />
                        </div>
                        <h3>Webhooks Seguros</h3>
                        <p>Receba atualizações de status de rastreamento em tempo real via Webhooks assinados criptograficamente para máxima segurança.</p>
                    </div>
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

export default ApiEcommerce;
