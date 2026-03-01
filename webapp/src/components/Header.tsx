import { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Menu, X } from 'lucide-react';
import logoImg from '../assets/favicon.png';

const Header = () => {
    const [scrollY, setScrollY] = useState(0);
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
    const [user, setUser] = useState<any>(null);
    const location = useLocation();

    useEffect(() => {
        const checkUser = () => {
            const savedUser = localStorage.getItem('loggi_user_session');
            if (savedUser) setUser(JSON.parse(savedUser));
            else setUser(null);
        };
        checkUser();

        const handleScroll = () => setScrollY(window.scrollY);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, [location.pathname]);

    const navItems = [
        { label: 'Início', path: '/' },
        { label: 'Para Você', path: '/para-voce' },
        { label: 'Para Empresas', path: '/para-empresas' },
        { label: 'API', path: '/api-ecommerce' },
        { label: 'Loggi Pro', path: '/loggi-pro' },
        { label: 'Rastreio', path: '/rastreio' },
    ];

    return (
        <header className={`site-header ${scrollY > 50 ? 'scrolled' : ''}`}>
            <style>{`
                .site-header {
                    position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
                    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1); padding: 20px 24px;
                }
                .site-header.scrolled { padding: 10px 24px; }
                .header-glass {
                    max-width: 1280px; margin: 0 auto;
                    display: flex; justify-content: space-between; align-items: center;
                    padding: 12px 28px;
                    background: rgba(255, 255, 255, 0.65);
                    backdrop-filter: blur(24px) saturate(1.8);
                    -webkit-backdrop-filter: blur(24px) saturate(1.8);
                    border: 1px solid rgba(255,255,255,0.8);
                    border-radius: 20px;
                    box-shadow: 0 8px 32px rgba(0, 40, 120, 0.08), inset 0 1px 0 rgba(255,255,255,0.9);
                }
                .site-header.scrolled .header-glass {
                    background: rgba(255, 255, 255, 0.8);
                    box-shadow: 0 12px 40px rgba(0, 40, 120, 0.12);
                }
                .logo {
                    display: flex; align-items: center; gap: 12px;
                    text-decoration: none; color: var(--text-primary);
                    font-size: 1.6rem; font-weight: 900; letter-spacing: -1.5px;
                }
                .logo img {
                    width: 38px; height: 38px;
                    filter: drop-shadow(0 2px 8px rgba(0, 85, 255, 0.4));
                    transition: transform 0.3s ease;
                }
                .logo:hover img { transform: scale(1.1); }
                
                .desktop-nav { display: flex; align-items: center; gap: 8px; }
                .nav-link {
                    color: var(--text-secondary); text-decoration: none; font-size: 0.88rem; font-weight: 600;
                    padding: 8px 16px; border-radius: 12px; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); position: relative;
                }
                .nav-link::after {
                    content: ''; position: absolute; bottom: 4px; left: 50%; width: 0; height: 2px;
                    background: var(--accent-primary); border-radius: 2px;
                    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                    transform: translateX(-50%);
                }
                .nav-link:hover { color: var(--accent-primary); background: rgba(0, 85, 255, 0.05); }
                .nav-link:hover::after { width: 60%; }
                .nav-link.active { color: var(--accent-primary); background: rgba(0, 85, 255, 0.08); font-weight: 700; }
                .nav-link.active::after { width: 60%; }
                
                .nav-login-btn {
                    margin-left: 12px; padding: 10px 22px;
                    background: linear-gradient(135deg, #0055ff, #3b82f6);
                    border: none; border-radius: 14px; color: white; text-decoration: none;
                    font-weight: 700; font-size: 0.85rem;
                    box-shadow: 0 6px 20px rgba(0, 85, 255, 0.25); transition: all 0.3s;
                }
                .nav-login-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 28px rgba(0, 85, 255, 0.4);
                    color: white;
                }
                
                .mobile-toggle {
                    display: none; background: rgba(0, 85, 255, 0.06); border: 1px solid rgba(0, 85, 255, 0.1);
                    color: var(--accent-primary); cursor: pointer; padding: 8px; border-radius: 12px; transition: 0.3s;
                }
                @media (max-width: 900px) {
                    .desktop-nav { display: none; }
                    .mobile-toggle { display: block; }
                }
                
                .mobile-menu-overlay {
                    position: fixed; inset: 0; background: rgba(0,20,60,0.5); backdrop-filter: blur(10px);
                    z-index: 2000; opacity: 0; pointer-events: none; transition: 0.4s;
                }
                .mobile-menu-overlay.open { opacity: 1; pointer-events: auto; }
                .mobile-menu-content {
                    position: fixed; right: 0; top: 0; bottom: 0; width: 300px;
                    background: #fff; border-left: 1px solid rgba(0, 85, 255, 0.08);
                    z-index: 2001; transform: translateX(100%);
                    transition: 0.5s cubic-bezier(0.16, 1, 0.3, 1);
                    display: flex; flex-direction: column; padding: 80px 24px 40px;
                    box-shadow: -8px 0 32px rgba(0,20,60,0.1);
                }
                .mobile-menu-overlay.open + .mobile-menu-content { transform: translateX(0); }
                
                .mobile-nav-item {
                    color: var(--text-primary); text-decoration: none; font-size: 1.1rem; font-weight: 600;
                    padding: 18px 16px; border-bottom: 1px solid rgba(0, 85, 255, 0.06);
                    display: flex; justify-content: space-between; align-items: center;
                    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                    opacity: 0; transform: translateX(20px);
                }
                .mobile-menu-overlay.open ~ .mobile-menu-content .mobile-nav-item {
                    opacity: 1; transform: translateX(0);
                }
                .mobile-menu-overlay.open ~ .mobile-menu-content .mobile-nav-item:nth-child(1) { transition-delay: 0.05s; }
                .mobile-menu-overlay.open ~ .mobile-menu-content .mobile-nav-item:nth-child(2) { transition-delay: 0.1s; }
                .mobile-menu-overlay.open ~ .mobile-menu-content .mobile-nav-item:nth-child(3) { transition-delay: 0.15s; }
                .mobile-menu-overlay.open ~ .mobile-menu-content .mobile-nav-item:nth-child(4) { transition-delay: 0.2s; }
                .mobile-menu-overlay.open ~ .mobile-menu-content .mobile-nav-item:nth-child(5) { transition-delay: 0.25s; }
                .mobile-menu-overlay.open ~ .mobile-menu-content .mobile-nav-item:nth-child(6) { transition-delay: 0.3s; }
                .mobile-menu-overlay.open ~ .mobile-menu-content .mobile-nav-item:nth-child(7) { transition-delay: 0.35s; }
                .mobile-nav-item:hover { color: var(--accent-primary); background: rgba(0, 85, 255, 0.03); padding-left: 24px; }
            `}</style>

            <div className="header-glass">
                <Link to="/" className="logo">
                    <img src={logoImg} alt="Loggi" />
                    <span>loggi</span>
                </Link>

                <nav className="desktop-nav">
                    {navItems.map((item) => (
                        <Link key={item.path} to={item.path} className={`nav-link ${location.pathname === item.path ? 'active' : ''}`}>
                            {item.label}
                        </Link>
                    ))}
                    {user ? (
                        <Link to="/perfil" className="nav-login-btn" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <div style={{ width: '22px', height: '22px', background: 'rgba(255,255,255,0.25)', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '11px', fontWeight: 800 }}>
                                {user.nome[0].toUpperCase()}
                            </div>
                            Olá, {user.nome.split(' ')[0]}
                        </Link>
                    ) : (
                        <Link to="/entrar" className="nav-login-btn">Entrar</Link>
                    )}
                </nav>

                <button className="mobile-toggle" onClick={() => setIsMobileMenuOpen(true)}>
                    <Menu size={24} />
                </button>
            </div>

            <div className={`mobile-menu-overlay ${isMobileMenuOpen ? 'open' : ''}`} onClick={() => setIsMobileMenuOpen(false)}></div>
            <div className={`mobile-menu-content`}>
                <button style={{ position: 'absolute', top: '24px', right: '24px', background: 'transparent', border: 'none', color: '#0a1628', cursor: 'pointer' }} onClick={() => setIsMobileMenuOpen(false)}>
                    <X size={28} />
                </button>
                {navItems.map((item) => (
                    <Link key={item.path} to={item.path} className="mobile-nav-item" onClick={() => setIsMobileMenuOpen(false)}>
                        {item.label}
                    </Link>
                ))}
                {user ? (
                    <Link to="/perfil" className="mobile-nav-item" onClick={() => setIsMobileMenuOpen(false)}>
                        Minha Conta ({user.nome.split(' ')[0]})
                    </Link>
                ) : (
                    <Link to="/entrar" className="nav-login-btn" style={{ textAlign: 'center', padding: '16px', fontSize: '1.1rem', marginTop: '20px' }} onClick={() => setIsMobileMenuOpen(false)}>
                        Entrar na Conta
                    </Link>
                )}
            </div>
        </header>
    );
};

export default Header;
