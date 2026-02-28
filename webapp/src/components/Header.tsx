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
                .site-header { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); padding: 24px; }
                .site-header.scrolled { padding: 16px 24px; }
                .header-glass {
                    max-width: 1280px; margin: 0 auto;
                    display: flex; justify-content: space-between; align-items: center;
                    padding: 14px 28px; background: rgba(10, 10, 12, 0.6); backdrop-filter: blur(20px) saturate(1.8);
                    border: 1px solid rgba(255,255,255,0.08); border-radius: 24px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.4), inset 0 0 0 1px rgba(255,255,255,0.05);
                }
                .logo { display: flex; align-items: center; gap: 12px; text-decoration: none; color: white; font-size: 1.5rem; font-weight: 900; letter-spacing: -1px; }
                .logo img { width: 32px; height: 32px; filter: drop-shadow(0 0 8px rgba(99, 102, 241, 0.5)); }
                
                .desktop-nav { display: flex; align-items: center; gap: 8px; }
                .nav-link {
                    color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.9rem; font-weight: 600;
                    padding: 10px 18px; border-radius: 12px; transition: all 0.3s; position: relative;
                }
                .nav-link:hover { color: white; background: rgba(255,255,255,0.05); }
                .nav-link.active { color: #818cf8; background: rgba(99, 102, 241, 0.08); }
                
                .nav-login-btn {
                    margin-left: 12px; padding: 12px 24px; background: linear-gradient(135deg, #6366f1, #a855f7);
                    border: none; border-radius: 14px; color: white; text-decoration: none; font-weight: 800; font-size: 0.85rem;
                    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3); transition: all 0.3s;
                }
                .nav-login-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(99, 102, 241, 0.5); }
                
                .mobile-toggle { display: none; background: transparent; border: none; color: white; cursor: pointer; padding: 8px; border-radius: 12px; transition: 0.3s; }
                @media (max-width: 900px) {
                    .desktop-nav { display: none; }
                    .mobile-toggle { display: block; }
                }
                
                .mobile-menu-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); z-index: 2000; opacity: 0; pointer-events: none; transition: 0.4s; }
                .mobile-menu-overlay.open { opacity: 1; pointer-events: auto; }
                .mobile-menu-content {
                    position: fixed; right: 0; top: 0; bottom: 0; width: 300px; background: #0d0d0f; border-left: 1px solid rgba(255,255,255,0.1);
                    z-index: 2001; transform: translateX(100%); transition: 0.5s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; padding: 80px 24px 40px;
                }
                .mobile-menu-overlay.open + .mobile-menu-content { transform: translateX(0); }
                
                .mobile-nav-item {
                    color: white; text-decoration: none; font-size: 1.2rem; font-weight: 700; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05);
                    display: flex; justify-content: space-between; align-items: center;
                }
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
                            <div style={{ width: '20px', height: '20px', background: 'rgba(255,255,255,0.2)', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '10px' }}>
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
                <button style={{ position: 'absolute', top: '24px', right: '24px', background: 'transparent', border: 'none', color: 'white' }} onClick={() => setIsMobileMenuOpen(false)}>
                    <X size={32} />
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
