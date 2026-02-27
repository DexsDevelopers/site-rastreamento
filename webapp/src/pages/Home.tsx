import React, { useState } from 'react';
import { Truck, Shield, Search, Zap, ArrowRight, Globe } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';

const Home: React.FC = () => {
    const [codigo, setCodigo] = useState('');
    const navigate = useNavigate();

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        if (codigo) {
            navigate('/rastreio');
        }
    };

    return (
        <div style={styles.container}>
            {/* Background Decorativo */}
            <div style={styles.bgGlow}></div>
            <div style={styles.bgGlowSecondary}></div>

            {/* Nav Minimalista */}
            <nav style={styles.nav}>
                <div style={styles.logo}>
                    <div style={styles.logoIcon}>
                        <Truck size={20} color="white" />
                    </div>
                    <span style={styles.logoText}>Loggi <span className="text-gradient">Premium</span></span>
                </div>
                <div style={styles.navLinks}>
                    <a href="#servicos" style={styles.navLink}>Serviços</a>
                    <a href="#rastreio" style={styles.navLink}>Rastreio</a>
                    <Link to="/login" style={styles.loginBtn}>Acesso Admin</Link>
                </div>
            </nav>

            {/* Hero Section */}
            <section style={styles.hero}>
                <div style={styles.heroContent}>
                    <div style={styles.heroBadge} className="animate-fade">
                        <Shield size={14} />
                        Sua logística em mãos profissionais
                    </div>
                    <h1 style={styles.heroTitle} className="animate-fade">
                        Logística que <br />
                        <span className="text-gradient">Conecta</span> o Futuro.
                    </h1>
                    <p style={styles.heroSubtitle} className="animate-fade">
                        Rastreamento em tempo real com notificações inteligentes via WhatsApp
                        e inteligência artificial para otimização de rotas.
                    </p>

                    <div style={styles.trackingWrapper} id="rastreio" className="animate-fade">
                        <form onSubmit={handleSearch} style={styles.searchBar} className="glass-card">
                            <Search size={22} color="var(--accent-primary)" style={{ opacity: 0.7 }} />
                            <input
                                type="text"
                                placeholder="Digite seu código (ex: LP123...)"
                                style={styles.heroInput}
                                value={codigo}
                                onChange={(e) => setCodigo(e.target.value)}
                            />
                            <button type="submit" style={styles.trackBtn} className="btn-glow">
                                Rastrear
                                <ArrowRight size={18} />
                            </button>
                        </form>
                    </div>
                </div>

                {/* Floating Indicators */}
                <div style={styles.floatCard1} className="animate-float glass-card">
                    <Zap size={18} color="var(--accent-primary)" />
                    <span>Entrega Expressa ATIVA</span>
                </div>
                <div style={styles.floatCard2} className="animate-float glass-card">
                    <Globe size={18} color="var(--accent-secondary)" />
                    <span>99.8% de Precisão</span>
                </div>
            </section>

            {/* Stats Bar */}
            <section style={styles.statsBar}>
                <div style={styles.statBox}>
                    <span style={styles.statNum}>+50k</span>
                    <span style={styles.statTitle}>Entregas/Mês</span>
                </div>
                <div style={styles.statDivider}></div>
                <div style={styles.statBox}>
                    <span style={styles.statNum}>100%</span>
                    <span style={styles.statTitle}>Monitorado</span>
                </div>
                <div style={styles.statDivider}></div>
                <div style={styles.statBox}>
                    <span style={styles.statNum}>24/7</span>
                    <span style={styles.statTitle}>Suporte Ativo</span>
                </div>
            </section>

            {/* CTA Section */}
            <section id="servicos" style={styles.ctaWrapper}>
                <div style={styles.ctaCard} className="glass-card">
                    <h2 style={styles.ctaTitle}>Pronto para transformar sua operação?</h2>
                    <p style={styles.ctaText}>Junte-se à Loggi Premium e tenha controle total sobre seu fluxo logístico.</p>
                    <div style={styles.ctaActions}>
                        <button className="btn-primary" style={{ padding: '16px 32px' }}>Começar Agora</button>
                        <button style={styles.outlineBtn}>Ver Demonstração</button>
                    </div>
                </div>
            </section>

            {/* Simple Footer */}
            <footer style={styles.footer}>
                <div style={styles.footerContent}>
                    <div style={styles.footerBrand}>
                        <span style={styles.footerLogo}>Loggi <span style={{ color: 'var(--accent-primary)' }}>Premium</span></span>
                        <p>O padrão ouro em logística digital.</p>
                    </div>
                    <div style={styles.footerLinks}>
                        <a href="#">Privacidade</a>
                        <a href="#">Termos</a>
                        <a href="#">API</a>
                        <a href="#">Suporte</a>
                    </div>
                </div>
                <div style={styles.footerCopy}>
                    © 2026 Loggi Premium Logistics. Todos os direitos reservados.
                </div>
            </footer>
        </div>
    );
};

const styles = {
    container: {
        background: 'var(--bg-primary)',
        color: 'var(--text-primary)',
        minHeight: '100vh',
        overflowX: 'hidden' as const,
        position: 'relative' as const,
        fontFamily: "'Outfit', sans-serif",
    },
    bgGlow: {
        position: 'fixed' as const,
        top: '10%',
        left: '10%',
        width: '40vw',
        height: '40vw',
        background: 'radial-gradient(circle, rgba(99, 102, 241, 0.05) 0%, transparent 60%)',
        pointerEvents: 'none' as const,
        zIndex: 0,
    },
    bgGlowSecondary: {
        position: 'fixed' as const,
        bottom: '10%',
        right: '10%',
        width: '30vw',
        height: '30vw',
        background: 'radial-gradient(circle, rgba(139, 92, 246, 0.05) 0%, transparent 60%)',
        pointerEvents: 'none' as const,
        zIndex: 0,
    },
    nav: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: '24px 8%',
        zIndex: 100,
        position: 'relative' as const,
    },
    logo: {
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
    },
    logoIcon: {
        width: '36px',
        height: '36px',
        background: 'var(--accent-primary)',
        borderRadius: '10px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        boxShadow: '0 8px 20px rgba(99, 102, 241, 0.3)',
    },
    logoText: {
        fontSize: '1.25rem',
        fontWeight: 800,
        letterSpacing: '-0.5px',
    },
    navLinks: {
        display: 'flex',
        alignItems: 'center',
        gap: '32px',
    },
    navLink: {
        color: 'var(--text-secondary)',
        textDecoration: 'none',
        fontSize: '0.9rem',
        fontWeight: 500,
        transition: 'color 0.2s',
    },
    loginBtn: {
        padding: '10px 20px',
        background: 'rgba(255, 255, 255, 0.05)',
        border: '1px solid var(--border-glass)',
        borderRadius: '12px',
        color: 'var(--text-primary)',
        textDecoration: 'none',
        fontSize: '0.85rem',
        fontWeight: 600,
        transition: 'all 0.3s',
    },
    hero: {
        padding: '100px 8% 120px',
        display: 'flex',
        flexDirection: 'column' as const,
        alignItems: 'center',
        textAlign: 'center' as const,
        position: 'relative' as const,
    },
    heroContent: {
        maxWidth: '800px',
        zIndex: 2,
    },
    heroBadge: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: '8px',
        background: 'rgba(99, 102, 241, 0.1)',
        color: 'var(--accent-primary)',
        padding: '8px 16px',
        borderRadius: '100px',
        fontSize: '0.8rem',
        fontWeight: 600,
        marginBottom: '24px',
        border: '1px solid rgba(99, 102, 241, 0.2)',
    },
    heroTitle: {
        fontSize: 'clamp(2.5rem, 8vw, 4rem)',
        lineHeight: 1.1,
        marginBottom: '24px',
        letterSpacing: '-2px',
        fontWeight: 900,
    },
    heroSubtitle: {
        fontSize: '1.1rem',
        color: 'var(--text-secondary)',
        lineHeight: 1.6,
        maxWidth: '600px',
        margin: '0 auto 48px',
    },
    trackingWrapper: {
        width: '100%',
        maxWidth: '550px',
        margin: '0 auto',
    },
    searchBar: {
        display: 'flex',
        alignItems: 'center',
        padding: '8px 8px 8px 24px',
        borderRadius: '20px',
        gap: '12px',
        boxShadow: '0 20px 50px rgba(0, 0, 0, 0.5)',
        background: 'var(--bg-glass)',
        border: '1px solid var(--border-glass)',
    },
    heroInput: {
        flex: 1,
        background: 'transparent',
        border: 'none',
        color: 'white',
        fontSize: '1rem',
        outline: 'none',
        padding: '12px 0',
    },
    trackBtn: {
        background: 'var(--accent-primary)',
        color: 'white',
        border: 'none',
        padding: '12px 24px',
        borderRadius: '14px',
        fontWeight: 700,
        fontSize: '0.9rem',
        cursor: 'pointer',
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
    },
    floatCard1: {
        position: 'absolute' as const,
        top: '10%',
        left: '2%',
        padding: '12px 20px',
        borderRadius: '16px',
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
        fontSize: '0.75rem',
        fontWeight: 600,
        zIndex: 1,
        opacity: 0.8,
    },
    floatCard2: {
        position: 'absolute' as const,
        bottom: '15%',
        right: '2%',
        padding: '12px 20px',
        borderRadius: '16px',
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
        fontSize: '0.75rem',
        fontWeight: 600,
        zIndex: 1,
        opacity: 0.8,
    },
    statsBar: {
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        padding: '60px 8%',
        gap: '6vw',
        flexWrap: 'wrap' as const,
        borderTop: '1px solid var(--border-glass)',
        borderBottom: '1px solid var(--border-glass)',
        background: 'rgba(255, 255, 255, 0.01)',
        position: 'relative' as const,
        zIndex: 2,
    },
    statBox: {
        display: 'flex',
        flexDirection: 'column' as const,
        alignItems: 'center',
        gap: '4px',
    },
    statNum: {
        fontSize: '1.8rem',
        fontWeight: 800,
    },
    statTitle: {
        fontSize: '0.7rem',
        color: 'var(--text-secondary)',
        textTransform: 'uppercase' as const,
        letterSpacing: '1px',
    },
    statDivider: {
        width: '1px',
        height: '40px',
        background: 'var(--border-glass)',
    },
    ctaWrapper: {
        padding: '100px 8%',
        position: 'relative' as const,
        zIndex: 2,
    },
    ctaCard: {
        maxWidth: '1000px',
        margin: '0 auto',
        padding: '60px 40px',
        borderRadius: '32px',
        textAlign: 'center' as const,
        background: 'linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%)',
        border: '1px solid var(--border-glass)',
    },
    ctaTitle: {
        fontSize: '2.2rem',
        marginBottom: '20px',
        fontWeight: 800,
    },
    ctaText: {
        color: 'var(--text-secondary)',
        fontSize: '1rem',
        marginBottom: '40px',
        maxWidth: '600px',
        margin: '0 auto 40px',
    },
    ctaActions: {
        display: 'flex',
        justifyContent: 'center',
        gap: '16px',
        flexWrap: 'wrap' as const,
    },
    outlineBtn: {
        padding: '16px 32px',
        background: 'transparent',
        border: '1px solid var(--border-glass)',
        borderRadius: '12px',
        color: 'white',
        fontWeight: 600,
        cursor: 'pointer',
        transition: 'all 0.3s',
        fontSize: '0.9rem',
    },
    footer: {
        padding: '80px 8% 40px',
        borderTop: '1px solid var(--border-glass)',
        position: 'relative' as const,
        zIndex: 2,
    },
    footerContent: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'flex-start',
        flexWrap: 'wrap' as const,
        gap: '40px',
        marginBottom: '60px',
    },
    footerBrand: {
        maxWidth: '300px',
    },
    footerLogo: {
        fontSize: '1.4rem',
        fontWeight: 800,
        display: 'block',
        marginBottom: '16px',
    },
    footerLinks: {
        display: 'flex',
        gap: '32px',
        flexWrap: 'wrap' as const,
    },
    footerCopy: {
        textAlign: 'center' as const,
        color: 'rgba(255, 255, 255, 0.2)',
        fontSize: '0.75rem',
        borderTop: '1px solid var(--border-glass)',
        paddingTop: '32px',
    }
};

export default Home;
