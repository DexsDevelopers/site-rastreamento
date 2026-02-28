import { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Package, Menu, X, Truck } from 'lucide-react';

const Header = () => {
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
    const [scrollY, setScrollY] = useState(0);
    const location = useLocation();

    useEffect(() => {
        const handleScroll = () => setScrollY(window.scrollY);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const navItems = [
        { label: 'Início', path: '/' },
        { label: 'Para você', path: '/para-voce' },
        { label: 'Para empresas', path: '/para-empresas' },
        { label: 'Sobre', path: '/sobre' }
    ];

    const isActive = (path: string) => location.pathname === path;

    return (
        <header className={`site-header ${scrollY > 50 ? 'scrolled' : ''}`}>
            <style>
                {`
                .site-header { position: fixed; top: 0; left: 0; right: 0; padding: 20px 5%; z-index: 1000; transition: all 0.3s; }
                .site-header.scrolled { padding: 12px 5%; }
                
                .header-glass { 
                    max-width: 1200px; margin: 0 auto;
                    background: rgba(10, 10, 12, 0.4); backdrop-filter: blur(20px) saturate(1.8);
                    border: 1px solid rgba(255,255,255,0.08); border-radius: 24px;
                    padding: 12px 32px; display: flex; justify-content: space-between; align-items: center;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.05);
                }
                .scrolled .header-glass { background: rgba(10, 10, 12, 0.82); border-color: rgba(99, 102, 241, 0.2); }
                
                .logo-link { display: flex; align-items: center; gap: 12px; text-decoration: none; color: white; }
                .logo-box { width: 36px; height: 36px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4); }
                .logo-name { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px; }
                
                .desktop-nav { display: none; align-items: center; gap: 28px; }
                @media(min-width: 900px) { .desktop-nav { display: flex; } }
                
                .nav-item { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: all 0.3s; position: relative; }
                .nav-item:hover, .nav-item.active { color: white; }
                .nav-item.active::after { content: ''; position: absolute; bottom: -4px; left: 0; right: 0; height: 2px; background: #818cf8; border-radius: 2px; }
                
                .nav-login-btn { padding: 10px 24px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 12px; color: white; text-decoration: none; font-weight: 700; font-size: 0.85rem; box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35); transition: 0.3s; }
                .nav-login-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45); }
                
                .mobile-toggle { display: block; background: none; border: none; color: white; cursor: pointer; padding: 8px; border-radius: 10px; transition: 0.3s; }
                .mobile-toggle:hover { background: rgba(255,255,255,0.05); }
                @media(min-width: 900px) { .mobile-toggle { display: none; } }
                
                .mobile-menu-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); z-index: 2000; opacity: 0; pointer-events: none; transition: 0.3s; }
                .mobile-menu-overlay.open { opacity: 1; pointer-events: auto; }
                
                .mobile-menu-content { 
                    position: fixed; top: 0; right: -300px; bottom: 0; width: 300px; 
                    background: #0d0d0f; border-left: 1px solid rgba(255,255,255,0.1); 
                    z-index: 2001; padding: 40px; display: flex; flex-direction: column; gap: 24px;
                    transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .mobile-menu-overlay.open + .mobile-menu-content { right: 0; }
                
                .mobile-nav-item { color: white; text-decoration: none; font-size: 1.25rem; font-weight: 700; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; }
                .mobile-nav-item.active { color: #818cf8; }
                `}
            </style>

            <div className="header-glass">
                <Link to="/" className="logo-link">
                    <div className="logo-box">
                        <Truck size={18} color="white" />
                    </div>
                    <span className="logo-name">loggi</span>
                </Link>

                <nav className="desktop-nav">
                    {navItems.map(item => (
                        <Link key={item.path} to={item.path} className={`nav-item ${isActive(item.path) ? 'active' : ''}`}>
                            {item.label}
                        </Link>
                    ))}
                    <Link to="/entrar" className="nav-login-btn">Entrar</Link>
                </nav>

                <button className="mobile-toggle" onClick={() => setIsMobileMenuOpen(true)}>
                    <Menu size={24} />
                </button>
            </div>

            {/* Mobile Menu */}
            <div className={`mobile-menu-overlay ${isMobileMenuOpen ? 'open' : ''}`} onClick={() => setIsMobileMenuOpen(false)}></div>
            <div className={`mobile-menu-content`}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '40px' }}>
                    <span style={{ fontSize: '1.5rem', fontWeight: 900 }}>Menu</span>
                    <button onClick={() => setIsMobileMenuOpen(false)} style={{ background: 'none', border: 'none', color: 'white' }}>
                        <X size={28} />
                    </button>
                </div>
                {navItems.map(item => (
                    <Link key={item.path} to={item.path} className={`mobile-nav-item ${isActive(item.path) ? 'active' : ''}`} onClick={() => setIsMobileMenuOpen(false)}>
                        {item.label}
                    </Link>
                ))}
                <Link to="/entrar" className="nav-login-btn" style={{ textAlign: 'center', padding: '16px', fontSize: '1.1rem', marginTop: '20px' }} onClick={() => setIsMobileMenuOpen(false)}>
                    Entrar na Conta
                </Link>
            </div>
        </header>
    );
};

export default Header;
