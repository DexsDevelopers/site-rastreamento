import React from 'react';
import { Truck, Shield, Search, Zap, Smartphone, ArrowRight, CheckCircle, Globe, Ship } from 'lucide-react';
import { Link } from 'react-router-dom';

const Home: React.FC = () => {
    return (
        <div style={styles.container}>
            {/* Background Decorativo */}
            <div style={styles.bgGlow}></div>

            {/* Nav Minimalista */}
            <nav style={styles.nav}>
                <div style={styles.logo}>
                    <div style={styles.logoIcon}>
                        <Truck size={24} color="white" />
                    </div>
                    <span style={styles.logoText}>Loggi <span className="text-gradient">Premium</span></span>
                </div>
                <div style={styles.navLinks}>
                    <a href="#servicos" style={styles.navLink}>Serviços</a>
                    <a href="#rastreio" style={styles.navLink}>Rastreio</a>
                    <a href="#sobre" style={styles.navLink}>Sobre</a>
                    <Link to="/login" style={styles.loginBtn}>Portal Admin</Link>
                </div>
            </nav>

            {/* Hero Section */}
            <section style={styles.hero}>
                <div style={styles.heroContent}>
                    <div style={styles.badge} className="animate-fade">
                        <CheckCircle size={14} />
                        Sua encomenda em mãos seguras
                    </div>
                    <h1 style={styles.heroTitle} className="animate-fade">
                        Logística que <br />
                        <span className="text-gradient">Impulsiona</span> Negócios.
                    </h1>
                    <p style={styles.heroSubtitle} className="animate-fade">
                        Conectamos sua transportadora ao futuro com rastreamento dopaminérgico,
                        inteligência artificial e notificações instantâneas via WhatsApp.
                    </p>

                    <div style={styles.trackingContainer} id="rastreio" className="animate-fade">
                        <div style={styles.searchBar} className="glass-card">
                            <Search size={22} color="var(--accent-primary)" />
                            <input
                                type="text"
                                placeholder="Digite o código da encomenda (ex: LL123456789BR)"
                                style={styles.heroInput}
                            />
                            <button style={styles.trackBtn} className="btn-glow">
                                Rastrear Agora
                                <ArrowRight size={18} />
                            </button>
                        </div>
                    </div>
                </div>

                {/* Floating Elements (Visual) */}
                <div style={styles.floatElement1} className="animate-float glass-card">
                    <Zap size={20} color="var(--accent-primary)" />
                    <span>Entrega Ultra-Rápida</span>
                </div>
                <div style={styles.floatElement2} className="animate-float glass-card">
                    <Globe size={20} color="var(--accent-secondary)" />
                    <span>Cobertura Global</span>
                </div>
            </section>

            {/* Stats Preview */}
            <section style={styles.statsSection}>
                <div style={styles.statLine}></div>
                <div style={styles.statsGrid}>
                    <div style={styles.statItem}>
                        <span style={styles.statVal}>99%</span>
                        <span style={styles.statLab}>Sucesso nas Entregas</span>
                    </div>
                    <div style={styles.statItem}>
                        <span style={styles.statVal}>+1M</span>
                        <span style={styles.statLab}>Pacotes Rastreados</span>
                    </div>
                    <div style={styles.statItem}>
                        <span style={styles.statVal}>&lt;2h</span>
                        <span style={styles.statLab}>Tempo de Reação</span>
                    </div>
                </div>
            </section>

            {/* Features (Dopaminergic Design) */}
            <section id="servicos" style={styles.features}>
                <div style={styles.sectionHeader}>
                    <h2 style={styles.sectionTitle}>Tecnologia de <span className="text-gradient">Ponta</span></h2>
                    <p style={styles.sectionSubtitle}>Não é apenas uma entrega. É uma experiência conectada.</p>
                </div>

                <div style={styles.featureGrid}>
                    <div className="glass-card" style={styles.featureCard}>
                        <div style={{ ...styles.featureIcon, background: 'rgba(124, 77, 255, 0.1)' }}>
                            <Smartphone size={32} color="var(--accent-primary)" />
                        </div>
                        <h3 style={styles.cardTitle}>WhatsApp IA</h3>
                        <p style={styles.cardText}>Bot inteligente que avisa seu cliente no exato momento em que o entregador sai para rota.</p>
                    </div>

                    <div className="glass-card" style={styles.featureCard}>
                        <div style={{ ...styles.featureIcon, background: 'rgba(0, 229, 255, 0.1)' }}>
                            <Ship size={32} color="var(--accent-secondary)" />
                        </div>
                        <h3 style={styles.cardTitle}>Gestão de Cargas</h3>
                        <p style={styles.cardText}>Dashboard completo para você controlar frotas, motoristas e entregas pendentes com um clique.</p>
                    </div>

                    <div className="glass-card" style={styles.featureCard}>
                        <div style={{ ...styles.featureIcon, background: 'rgba(124, 77, 255, 0.1)' }}>
                            <Shield size={32} color="var(--accent-primary)" />
                        </div>
                        <h3 style={styles.cardTitle}>Seguro Integrado</h3>
                        <p style={styles.cardText}>Todas as encomendas são monitoradas e seguradas contra qualquer imprevisto logístico.</p>
                    </div>
                </div>
            </section>

            {/* CTA Final */}
            <section style={styles.ctaBanner} className="glass-card">
                <div style={styles.ctaContent}>
                    <h2 style={styles.ctaTitle}>Pronto para modernizar sua transportadora?</h2>
                    <p style={styles.ctaSubtitle}>Junte-se a centenas de empresas que já usam a Loggi Premium para crescer.</p>
                    <button style={styles.ctaBtn} className="btn-glow">Falar com um Especialista</button>
                </div>
            </section>

            {/* Footer Premium */}
            <footer style={styles.footer} id="sobre">
                <div style={styles.footerGrid}>
                    <div style={styles.footerBrand}>
                        <div style={styles.logo}>
                            <div style={styles.logoIcon}><Truck size={20} color="white" /></div>
                            <span style={styles.logoText}>Loggi</span>
                        </div>
                        <p style={styles.footerAbout}>
                            Transformando a última milha em uma experiência memorável para clientes e empresas.
                        </p>
                    </div>
                    <div style={styles.footerLinksGroup}>
                        <div style={styles.footerCol}>
                            <h4>Links</h4>
                            <a href="#">Serviços</a>
                            <a href="#">Rastreio</a>
                            <a href="#">Segurança</a>
                        </div>
                        <div style={styles.footerCol}>
                            <h4>Suporte</h4>
                            <a href="#">Central de Ajuda</a>
                            <a href="#">Documentação API</a>
                            <a href="#">Contato</a>
                        </div>
                    </div>
                </div>
                <div style={styles.copyright}>
                    © 2026 Loggi Premium Logística. Desenvolvido com ❤️ para empresas modernas.
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
        fontFamily: "'Outfit', sans-serif",
    },
    bgGlow: {
        position: 'fixed' as const,
        top: '-150px',
        right: '-150px',
        width: '600px',
        height: '600px',
        background: 'radial-gradient(circle, rgba(124, 77, 255, 0.1) 0%, transparent 70%)',
        zIndex: 0,
        pointerEvents: 'none' as const,
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
        width: '40px',
        height: '40px',
        background: 'var(--accent-primary)',
        borderRadius: '12px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        boxShadow: '0 8px 16px rgba(124, 77, 255, 0.3)',
    },
    logoText: {
        fontSize: '1.4rem',
        fontWeight: 800,
        letterSpacing: '-1px',
    },
    navLinks: {
        display: 'flex',
        alignItems: 'center',
        gap: '32px',
    },
    navLink: {
        color: 'var(--text-secondary)',
        textDecoration: 'none',
        fontWeight: 500,
        fontSize: '0.95rem',
        transition: 'color 0.3s',
        '&:hover': { color: 'var(--text-primary)' }
    },
    loginBtn: {
        padding: '10px 20px',
        background: 'rgba(255,255,255,0.05)',
        border: '1px solid var(--border-glass)',
        borderRadius: '12px',
        color: 'var(--text-primary)',
        textDecoration: 'none',
        fontWeight: 600,
        fontSize: '0.9rem',
        transition: 'all 0.3s',
    },
    hero: {
        padding: '120px 8% 160px',
        textAlign: 'center' as const,
        position: 'relative' as const,
        display: 'flex',
        flexDirection: 'column' as const,
        alignItems: 'center',
    },
    heroContent: {
        maxWidth: '850px',
        zIndex: 2,
    },
    badge: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: '8px',
        background: 'rgba(124, 77, 255, 0.08)',
        color: 'var(--accent-primary)',
        padding: '8px 16px',
        borderRadius: '100px',
        fontSize: '0.85rem',
        fontWeight: 600,
        marginBottom: '32px',
        border: '1px solid rgba(124, 77, 255, 0.2)',
    },
    heroTitle: {
        fontSize: '5rem',
        fontWeight: 900,
        lineHeight: 1.05,
        letterSpacing: '-3px',
        marginBottom: '24px',
    },
    heroSubtitle: {
        fontSize: '1.25rem',
        color: 'var(--text-secondary)',
        maxWidth: '700px',
        lineHeight: 1.6,
        marginBottom: '56px',
    },
    trackingContainer: {
        width: '100%',
        display: 'flex',
        justifyContent: 'center',
    },
    searchBar: {
        padding: '10px 10px 10px 24px',
        borderRadius: '24px',
        display: 'flex',
        alignItems: 'center',
        gap: '16px',
        width: '100%',
        maxWidth: '650px',
        boxShadow: '0 30px 60px rgba(0,0,0,0.5)',
    },
    heroInput: {
        flex: 1,
        background: 'transparent',
        border: 'none',
        color: 'var(--text-primary)',
        fontSize: '1.1rem',
        outline: 'none',
        padding: '10px 0',
    },
    trackBtn: {
        background: 'var(--accent-primary)',
        color: 'white',
        border: 'none',
        padding: '14px 28px',
        borderRadius: '18px',
        fontSize: '1rem',
        fontWeight: 700,
        cursor: 'pointer',
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
        boxShadow: '0 8px 20px rgba(124, 77, 255, 0.4)',
    },
    floatElement1: {
        position: 'absolute' as const,
        top: '20%',
        left: '5%',
        padding: '16px 24px',
        borderRadius: '20px',
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
        fontSize: '0.9rem',
        fontWeight: 600,
    },
    floatElement2: {
        position: 'absolute' as const,
        bottom: '20%',
        right: '5%',
        padding: '16px 24px',
        borderRadius: '20px',
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
        fontSize: '0.9rem',
        fontWeight: 600,
    },
    statsSection: {
        padding: '0 8%',
        marginBottom: '100px',
    },
    statLine: {
        height: '1px',
        background: 'linear-gradient(90deg, transparent, var(--border-glass), transparent)',
        marginBottom: '48px',
    },
    statsGrid: {
        display: 'flex',
        justifyContent: 'space-around',
    },
    statItem: {
        display: 'flex',
        flexDirection: 'column' as const,
        alignItems: 'center',
        gap: '4px',
    },
    statVal: {
        fontSize: '2.5rem',
        fontWeight: 800,
        color: 'var(--text-primary)',
    },
    statLab: {
        color: 'var(--text-secondary)',
        fontSize: '0.9rem',
        fontWeight: 500,
    },
    features: {
        padding: '100px 8%',
    },
    sectionHeader: {
        textAlign: 'center' as const,
        marginBottom: '72px',
    },
    sectionTitle: {
        fontSize: '3.5rem',
        fontWeight: 800,
        letterSpacing: '-2px',
        marginBottom: '16px',
    },
    sectionSubtitle: {
        fontSize: '1.2rem',
        color: 'var(--text-secondary)',
    },
    featureGrid: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
        gap: '32px',
    },
    featureCard: {
        padding: '40px',
        borderRadius: '28px',
        border: '1px solid var(--border-glass)',
    },
    featureIcon: {
        width: '72px',
        height: '72px',
        borderRadius: '20px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        marginBottom: '24px',
    },
    cardTitle: {
        fontSize: '1.5rem',
        fontWeight: 700,
        marginBottom: '16px',
    },
    cardText: {
        color: 'var(--text-secondary)',
        lineHeight: 1.6,
        fontSize: '1rem',
    },
    ctaBanner: {
        margin: '100px 8% 150px',
        padding: '80px 40px',
        borderRadius: '40px',
        textAlign: 'center' as const,
        background: 'linear-gradient(135deg, rgba(124, 77, 255, 0.1) 0%, rgba(0, 229, 255, 0.05) 100%)',
        border: '1px solid rgba(124, 77, 255, 0.2)',
    },
    ctaContent: {
        maxWidth: '600px',
        margin: '0 auto',
    },
    ctaTitle: {
        fontSize: '2.5rem',
        fontWeight: 800,
        marginBottom: '20px',
    },
    ctaSubtitle: {
        fontSize: '1.1rem',
        color: 'var(--text-secondary)',
        marginBottom: '40px',
    },
    ctaBtn: {
        padding: '16px 40px',
        background: 'var(--accent-primary)',
        color: 'white',
        border: 'none',
        borderRadius: '16px',
        fontSize: '1.1rem',
        fontWeight: 700,
        cursor: 'pointer',
    },
    footer: {
        padding: '100px 8% 40px',
        borderTop: '1px solid var(--border-glass)',
    },
    footerGrid: {
        display: 'flex',
        justifyContent: 'space-between',
        marginBottom: '80px',
    },
    footerBrand: {
        maxWidth: '350px',
    },
    footerAbout: {
        marginTop: '24px',
        color: 'var(--text-secondary)',
        lineHeight: 1.6,
    },
    footerLinksGroup: {
        display: 'flex',
        gap: '100px',
    },
    footerCol: {
        display: 'flex',
        flexDirection: 'column' as const,
        gap: '16px',
        h4: { marginBottom: '8px', color: 'var(--text-primary)' },
        a: {
            color: 'var(--text-secondary)',
            textDecoration: 'none',
            fontSize: '0.9rem',
            transition: 'color 0.3s'
        }
    },
    copyright: {
        textAlign: 'center' as const,
        color: 'rgba(255,255,255,0.2)',
        fontSize: '0.8rem',
    }
};

export default Home;
