import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Package, Search, MessageCircle, FileText, Phone } from 'lucide-react';

const HelpCenter = () => {
    const [scrollY, setScrollY] = useState(0);

    useEffect(() => {
        const handleScroll = () => setScrollY(window.scrollY);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const categories = [
        { icon: <Package size={24} />, title: 'Rastreamento', desc: 'Como localizar seu pedido e entender os status.' },
        { icon: <FileText size={24} />, title: 'Segunda Via e NF', desc: 'Baixe faturas e documentos fiscais.' },
        { icon: <MessageCircle size={24} />, title: 'Dúvidas Frequentes', desc: 'Respostas rápidas para perguntas comuns.' },
        { icon: <Phone size={24} />, title: 'Fale Conosco', desc: 'Canais de atendimento direto e chat.' },
    ];

    return (
        <div className="help-page">
            <style>
                {`
                .help-page { background: var(--bg-primary); min-height: 100vh; font-family: 'Outfit', sans-serif; color: white; overflow-x: hidden; position: relative; }
                .bg-mesh { position: fixed; inset: 0; background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), radial-gradient(at 100% 0%, hsla(225,39%,30%,0.1) 0, transparent 50%); z-index: 0; pointer-events: none; }
                
                .site-header { position: fixed; top: 0; left: 0; right: 0; padding: 20px 5%; z-index: 100; transition: all 0.3s; }
                .header-glass { background: rgba(10, 10, 12, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.05); padding: 12px 32px; border-radius: 24px; display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; }
                .logo-link { display: flex; align-items: center; gap: 12px; text-decoration: none; color: white; }
                .logo-box { width: 36px; height: 36px; background: var(--accent-gradient); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
                .logo-name { font-size: 1.4rem; font-weight: 800; }
                
                .desktop-nav { display: none; align-items: center; gap: 32px; }
                @media(min-width: 900px) { .desktop-nav { display: flex; } }
                .nav-item { color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: all 0.3s; }
                .nav-item:hover { color: white; }
                .nav-login-btn { padding: 10px 24px; background: var(--accent-gradient); border-radius: 12px; color: white; text-decoration: none; font-weight: 700; font-size: 0.85rem; }
                
                .hero { padding: 160px 5% 60px; text-align: center; max-width: 800px; margin: 0 auto; }
                .hero-title { font-size: 3rem; font-weight: 900; margin-bottom: 32px; }
                .search-box { position: relative; max-width: 600px; margin: 0 auto; }
                .search-input { width: 100%; padding: 20px 20px 20px 60px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; color: white; font-size: 1.1rem; outline: none; transition: 0.3s; }
                .search-input:focus { border-color: var(--accent-primary); background: rgba(255,255,255,0.08); }
                .search-icon { position: absolute; left: 24px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.3); }
                
                .section { padding: 60px 5%; max-width: 1200px; margin: 0 auto; position: relative; z-index: 10; }
                .grid-categories { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; }
                .cat-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 32px; border-radius: 24px; transition: 0.3s; cursor: pointer; text-decoration: none; color: inherit; }
                .cat-card:hover { background: rgba(255,255,255,0.05); border-color: var(--accent-primary); transform: translateY(-5px); }
                .cat-icon { width: 50px; height: 50px; background: rgba(99, 102, 241, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; color: #818cf8; }
                .cat-card h3 { font-size: 1.25rem; margin-bottom: 8px; font-weight: 700; }
                .cat-card p { color: rgba(255,255,255,0.4); font-size: 0.95rem; line-height: 1.5; }
                
                .site-footer { text-align: center; padding: 60px 20px; color: rgba(255,255,255,0.3); border-top: 1px solid rgba(255,255,255,0.05); }
                .footer-links { margin-top: 24px; display: flex; flex-wrap: wrap; justify-content: center; gap: 20px; }
                .footer-links a { color: inherit; text-decoration: none; transition: 0.3s; }
                .footer-links a:hover { color: white; }
                `}
            </style>

            <div className="bg-mesh"></div>

            <header className={`site-header ${scrollY > 50 ? 'scrolled' : ''}`}>
                <div className="header-glass">
                    <Link to="/" className="logo-link">
                        <div className="logo-box"><Package size={18} color="white" /></div>
                        <span className="logo-name">loggi</span>
                    </Link>
                    <nav className="desktop-nav">
                        <Link to="/" className="nav-item">Início</Link>
                        <Link to="/para-voce" className="nav-item">Para você</Link>
                        <Link to="/para-empresas" className="nav-item">Para empresas</Link>
                        <Link to="/sobre" className="nav-item">Sobre</Link>
                        <Link to="/entrar" className="nav-login-btn">Entrar</Link>
                    </nav>
                </div>
            </header>

            <section className="hero">
                <h1 className="hero-title">Como podemos ajudar?</h1>
                <div className="search-box">
                    <Search className="search-icon" size={24} />
                    <input type="text" className="search-input" placeholder="Busque por 'onde está meu pedido', 'fatura', etc..." />
                </div>
            </section>

            <section className="section">
                <div className="grid-categories">
                    {categories.map((cat, i) => (
                        <div key={i} className="cat-card">
                            <div className="cat-icon">{cat.icon}</div>
                            <h3>{cat.title}</h3>
                            <p>{cat.desc}</p>
                        </div>
                    ))}
                </div>
            </section>

            <section className="section" style={{ textAlign: 'center' }}>
                <div style={{ background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.1)', padding: '48px', borderRadius: '32px' }}>
                    <h2 style={{ fontSize: '2rem', marginBottom: '16px' }}>Ainda precisa de ajuda?</h2>
                    <p style={{ color: 'rgba(255,255,255,0.5)', marginBottom: '32px' }}>Nosso time de suporte está disponível 24/7 para te atender.</p>
                    <button className="nav-login-btn" style={{ padding: '16px 40px', fontSize: '1rem' }}>Iniciar Chat Online</button>
                </div>
            </section>

            <footer className="site-footer">
                <p>&copy; 2026 Loggi Tecnologia LTDA.</p>
                <div className="footer-links">
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

export default HelpCenter;
