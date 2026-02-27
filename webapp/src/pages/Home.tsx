import React, { useState } from 'react';
import { Truck, Search, Zap, ArrowRight, Globe, QrCode, Satellite, Package, Warehouse, GitBranch, RotateCcw, Smile, MapPinned, Star, Heart } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';

const Home: React.FC = () => {
    const [codigo, setCodigo] = useState('');
    const [cidade, setCidade] = useState('');
    const navigate = useNavigate();
    const [activeTab, setActiveTab] = useState<'voce' | 'empresas'>('voce');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        if (codigo) {
            navigate('/rastreio');
        }
    };

    return (
        <div style={styles.page}>
            {/* Header */}
            <header style={styles.header}>
                <div style={styles.headerInner}>
                    <Link to="/" style={styles.logo}>
                        <div style={styles.logoIcon}><Truck size={18} color="white" /></div>
                        <span style={styles.logoText}>loggi</span>
                    </Link>
                    <nav style={styles.nav}>
                        <Link to="/" style={styles.navLink}>Início</Link>
                        <a href="#para-voce" style={styles.navLink}>Para você</a>
                        <a href="#para-empresas" style={styles.navLink}>Para empresas</a>
                        <Link to="/sobre" style={styles.navLink}>Sobre</Link>
                        <Link to="/login" style={styles.navBtnLogin}>Entrar</Link>
                    </nav>
                </div>
            </header>

            {/* Hero Section */}
            <section style={styles.hero}>
                <div style={styles.heroBox}>
                    <div style={styles.heroContent} className="animate-fade">
                        <h1 style={styles.heroTitle}>
                            O rastreio do seu envio é prático
                        </h1>
                        <p style={styles.heroDesc}>
                            Acompanhe seu pedido em tempo real com a Loggi. Frete grátis para todo o Brasil.
                        </p>
                        <div style={styles.heroActions}>
                            <Link to="/pedido" style={styles.ctaPrimary} className="btn-primary">
                                <Package size={18} /> Enviar agora
                            </Link>
                        </div>

                        {/* Formulário de Rastreamento */}
                        <form onSubmit={handleSearch} style={styles.trackingForm}>
                            <div style={styles.inputRow}>
                                <div style={styles.inputWrap}>
                                    <Search size={18} color="var(--accent-primary)" />
                                    <input
                                        type="text"
                                        placeholder="Código de rastreio"
                                        style={styles.trackInput}
                                        value={codigo}
                                        onChange={(e) => setCodigo(e.target.value)}
                                        maxLength={12}
                                        required
                                        className="input-field"
                                    />
                                </div>
                                <div style={styles.inputWrap}>
                                    <MapPinned size={18} color="var(--accent-primary)" />
                                    <input
                                        type="text"
                                        placeholder="Sua cidade"
                                        style={styles.trackInput}
                                        value={cidade}
                                        onChange={(e) => setCidade(e.target.value)}
                                        required
                                        className="input-field"
                                    />
                                </div>
                            </div>
                            <button type="submit" style={styles.trackBtn} className="btn-primary">
                                <Search size={18} /> Rastrear agora
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            {/* Tabs: Para Você / Para Empresas */}
            <div style={styles.tabsContainer}>
                <div style={styles.tabsNav}>
                    <button
                        style={activeTab === 'voce' ? styles.tabActive : styles.tab}
                        onClick={() => setActiveTab('voce')}
                    >Para você</button>
                    <button
                        style={activeTab === 'empresas' ? styles.tabActive : styles.tab}
                        onClick={() => setActiveTab('empresas')}
                    >Para empresas</button>
                </div>
            </div>

            {/* Para Você */}
            {activeTab === 'voce' && (
                <section id="para-voce" style={styles.marketingSection} className="animate-fade">
                    <h2 style={styles.sectionTitle}>A Loggi entrega onde você precisar</h2>
                    <p style={styles.sectionSub}>A maior malha logística privada do Brasil à sua disposição.</p>
                    <div style={styles.cardsGrid}>
                        <div className="glass-card" style={styles.mktCard}>
                            <div style={styles.mktIcon}><QrCode size={28} color="var(--accent-primary)" /></div>
                            <h3>Postagem simples</h3>
                            <p style={styles.mktText}>Gere sua etiqueta em poucos cliques e poste em qualquer ponto parceiro próximo a você.</p>
                            <Link to="/pedido" style={styles.cardLink}>Começar agora <ArrowRight size={14} /></Link>
                        </div>
                        <div className="glass-card" style={styles.mktCard}>
                            <div style={styles.mktIcon}><Satellite size={28} color="var(--accent-secondary)" /></div>
                            <h3>Monitoramento GPS</h3>
                            <p style={styles.mktText}>Acompanhe cada curva da sua encomenda com tecnologia de rastreio via satélite em tempo real.</p>
                            <a href="#" style={styles.cardLink}>Ver como funciona <ArrowRight size={14} /></a>
                        </div>
                        <div className="glass-card" style={styles.mktCard}>
                            <div style={styles.mktIcon}><Zap size={28} color="var(--accent-primary)" /></div>
                            <h3>Loggi Express</h3>
                            <p style={styles.mktText}>Sua encomenda priorizada em nossa malha expressa para chegar ao destino em tempo recorde.</p>
                            <a href="#" style={styles.cardLink}>Pedir urgência <ArrowRight size={14} /></a>
                        </div>
                    </div>
                </section>
            )}

            {/* Para Empresas */}
            {activeTab === 'empresas' && (
                <section id="para-empresas" style={styles.marketingSection} className="animate-fade">
                    <h2 style={styles.sectionTitle}>Logística inteligente para negócios</h2>
                    <p style={styles.sectionSub}>Potencialize suas vendas com a malha logística que mais cresce no país.</p>
                    <div style={styles.cardsGrid}>
                        <div className="glass-card" style={styles.mktCard}>
                            <div style={styles.mktIcon}><Warehouse size={28} color="var(--accent-primary)" /></div>
                            <h3>Coleta loggi</h3>
                            <p style={styles.mktText}>Equipe dedicada para coletar seus envios diretamente no seu centro de distribuição.</p>
                        </div>
                        <div className="glass-card" style={styles.mktCard}>
                            <div style={styles.mktIcon}><GitBranch size={28} color="var(--accent-secondary)" /></div>
                            <h3>API de Integração</h3>
                            <p style={styles.mktText}>Conecte seu e-commerce diretamente com nosso sistema para automação total de fretes.</p>
                        </div>
                        <div className="glass-card" style={styles.mktCard}>
                            <div style={styles.mktIcon}><RotateCcw size={28} color="var(--accent-primary)" /></div>
                            <h3>Reversa Facilitada</h3>
                            <p style={styles.mktText}>Gestão completa de trocas e devoluções para encantar seus clientes no pós-venda.</p>
                        </div>
                    </div>
                </section>
            )}

            {/* Prova Social */}
            <section style={styles.proofSection}>
                <div style={styles.proofGrid}>
                    <div className="glass-card" style={styles.proofCard}>
                        <Smile size={40} color="var(--accent-primary)" />
                        <span style={styles.proofVal}>4.8/5</span>
                        <span style={styles.proofLabel}>Satisfação dos Clientes</span>
                    </div>
                    <div className="glass-card" style={{ ...styles.proofCard, border: '1px solid var(--accent-primary)' }}>
                        <Package size={48} color="var(--accent-primary)" />
                        <span style={styles.proofVal}>10M+</span>
                        <span style={styles.proofLabel}>Entregas Realizadas</span>
                    </div>
                    <div className="glass-card" style={styles.proofCard}>
                        <Globe size={40} color="var(--accent-secondary)" />
                        <span style={styles.proofVal}>4.5k+</span>
                        <span style={styles.proofLabel}>Cidades Atendidas</span>
                    </div>
                </div>
            </section>

            {/* Depoimentos */}
            <section style={styles.testimonialsSection}>
                <h2 style={{ ...styles.sectionTitle, color: 'white', marginBottom: '48px' }}>Confiança de quem usa</h2>
                <div style={styles.cardsGrid}>
                    {[
                        { name: 'Ricardo Mendes', role: 'CEO, TechCommerce', text: '"A tecnologia da Loggi é incomparável. Consigo gerir todos os meus envios com uma facilidade que nunca tive antes."' },
                        { name: 'Juliana Costa', role: 'Gerente Logística, ModaBR', text: '"O suporte é excelente e as entregas sempre dentro do prazo. Meus clientes estão muito mais satisfeitos."' },
                        { name: 'Felipe Silva', role: 'Vendedor Platinum', text: '"Postar meus pacotes ficou 10x mais rápido com os Pontos Loggi. Recomendo para todos os vendedores."' },
                    ].map((t, i) => (
                        <div key={i} className="glass-card" style={styles.testimonial}>
                            <div style={styles.stars}>
                                {[...Array(5)].map((_, j) => <Star key={j} size={14} fill="var(--warning)" color="var(--warning)" />)}
                            </div>
                            <p style={styles.testimonialText}>{t.text}</p>
                            <div>
                                <strong>{t.name}</strong>
                                <span style={{ display: 'block', color: 'var(--text-secondary)', fontSize: '0.85rem' }}>{t.role}</span>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            {/* Footer */}
            <footer style={styles.footer}>
                <div style={styles.footerInner}>
                    <div style={styles.footerBrand}>
                        <Link to="/" style={{ ...styles.logo, textDecoration: 'none', color: 'white' }}>
                            <div style={styles.logoIcon}><Truck size={16} color="white" /></div>
                            <span style={styles.logoText}>loggi</span>
                        </Link>
                        <p style={{ color: 'var(--text-secondary)', marginTop: '16px', lineHeight: 1.6 }}>
                            Reinventando a logística brasileira através de tecnologia própria e excelência operacional.
                        </p>
                    </div>
                    <div style={styles.footerLinks}>
                        <div style={styles.footerCol}>
                            <h4>Soluções</h4>
                            <a href="#">Loggi para você</a>
                            <a href="#">Loggi para empresas</a>
                            <a href="#">E-commerce API</a>
                            <a href="#">Loggi Pro</a>
                        </div>
                        <div style={styles.footerCol}>
                            <h4>Sobre</h4>
                            <Link to="/sobre">Nossa História</Link>
                            <a href="#">Carreiras</a>
                            <a href="#">Central de Ajuda</a>
                            <a href="#">Termos de Uso</a>
                        </div>
                    </div>
                </div>
                <div style={styles.footerBottom}>
                    <p>© 2026 Loggi Tecnologia LTDA.</p>
                    <p>Feito com <Heart size={14} fill="#ef4444" color="#ef4444" style={{ verticalAlign: 'middle' }} /> para o Brasil</p>
                </div>
            </footer>
        </div>
    );
};

const styles: { [key: string]: React.CSSProperties } = {
    page: { background: 'var(--bg-primary)', color: 'var(--text-primary)', minHeight: '100vh' },
    header: { padding: '20px 6%', position: 'sticky', top: 0, zIndex: 100, backdropFilter: 'blur(12px)', borderBottom: '1px solid var(--border-glass)', background: 'rgba(10, 10, 15, 0.8)' },
    headerInner: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', maxWidth: '1200px', margin: '0 auto' },
    logo: { display: 'flex', alignItems: 'center', gap: '10px', textDecoration: 'none', color: 'white' },
    logoIcon: { width: '34px', height: '34px', background: 'var(--accent-primary)', borderRadius: '10px', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 4px 12px rgba(99, 102, 241, 0.3)' },
    logoText: { fontSize: '1.3rem', fontWeight: 800 },
    nav: { display: 'flex', alignItems: 'center', gap: '28px' },
    navLink: { color: 'var(--text-secondary)', textDecoration: 'none', fontSize: '0.9rem', fontWeight: 500, transition: 'color 0.2s' },
    navBtnLogin: { padding: '10px 20px', background: 'var(--accent-primary)', borderRadius: '12px', color: 'white', textDecoration: 'none', fontWeight: 700, fontSize: '0.85rem' },
    hero: { padding: '80px 6% 60px', maxWidth: '1200px', margin: '0 auto' },
    heroBox: { display: 'flex', alignItems: 'center', gap: '60px', flexWrap: 'wrap' as const },
    heroContent: { flex: 1, minWidth: '320px' },
    heroTitle: { fontSize: 'clamp(2.2rem, 5vw, 3.2rem)', fontWeight: 900, lineHeight: 1.15, letterSpacing: '-1px', marginBottom: '20px' },
    heroDesc: { color: 'var(--text-secondary)', fontSize: '1.05rem', lineHeight: 1.6, marginBottom: '28px', maxWidth: '550px' },
    heroActions: { display: 'flex', gap: '16px', marginBottom: '40px', flexWrap: 'wrap' as const },
    ctaPrimary: { display: 'inline-flex', alignItems: 'center', gap: '10px', padding: '14px 28px', textDecoration: 'none', borderRadius: '14px', fontSize: '0.95rem', fontWeight: 700 },
    trackingForm: { maxWidth: '500px' },
    inputRow: { display: 'flex', flexDirection: 'column' as const, gap: '12px', marginBottom: '16px' },
    inputWrap: { display: 'flex', alignItems: 'center', gap: '12px', background: 'rgba(255,255,255,0.03)', border: '1px solid var(--border-glass)', borderRadius: '14px', padding: '4px 16px' },
    trackInput: { flex: 1, background: 'transparent', border: 'none', color: 'white', padding: '12px 0', fontSize: '0.95rem', outline: 'none' },
    trackBtn: { width: '100%', padding: '14px', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '10px', fontSize: '0.95rem', fontWeight: 700, borderRadius: '14px' },
    tabsContainer: { maxWidth: '1200px', margin: '0 auto', padding: '0 6%' },
    tabsNav: { display: 'flex', gap: '8px', padding: '8px', background: 'rgba(255,255,255,0.03)', borderRadius: '16px', border: '1px solid var(--border-glass)', width: 'fit-content' },
    tab: { padding: '12px 24px', background: 'transparent', border: 'none', color: 'var(--text-secondary)', cursor: 'pointer', borderRadius: '12px', fontWeight: 600, fontSize: '0.9rem', transition: 'all 0.2s' },
    tabActive: { padding: '12px 24px', background: 'var(--accent-primary)', border: 'none', color: 'white', cursor: 'pointer', borderRadius: '12px', fontWeight: 700, fontSize: '0.9rem' },
    marketingSection: { padding: '60px 6% 80px', maxWidth: '1200px', margin: '0 auto' },
    sectionTitle: { fontSize: '2rem', fontWeight: 800, marginBottom: '12px', letterSpacing: '-0.5px' },
    sectionSub: { color: 'var(--text-secondary)', fontSize: '1rem', marginBottom: '48px' },
    cardsGrid: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '24px' },
    mktCard: { padding: '32px', borderRadius: '24px', display: 'flex', flexDirection: 'column' as const, gap: '16px' },
    mktIcon: { width: '56px', height: '56px', borderRadius: '16px', background: 'rgba(99, 102, 241, 0.08)', display: 'flex', alignItems: 'center', justifyContent: 'center' },
    mktText: { color: 'var(--text-secondary)', lineHeight: 1.6, fontSize: '0.95rem' },
    cardLink: { color: 'var(--accent-primary)', textDecoration: 'none', fontWeight: 700, fontSize: '0.9rem', display: 'flex', alignItems: 'center', gap: '6px', marginTop: 'auto' },
    proofSection: { padding: '80px 6%', maxWidth: '1200px', margin: '0 auto' },
    proofGrid: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '24px' },
    proofCard: { padding: '40px', borderRadius: '24px', textAlign: 'center' as const, display: 'flex', flexDirection: 'column' as const, alignItems: 'center', gap: '12px' },
    proofVal: { fontSize: '2.5rem', fontWeight: 900 },
    proofLabel: { color: 'var(--text-secondary)', fontSize: '0.9rem' },
    testimonialsSection: { padding: '80px 6%', maxWidth: '1200px', margin: '0 auto', background: 'rgba(99, 102, 241, 0.03)', borderRadius: '32px', marginBottom: '60px', textAlign: 'center' as const },
    testimonial: { padding: '28px', borderRadius: '20px', textAlign: 'left' as const },
    stars: { display: 'flex', gap: '4px', marginBottom: '16px' },
    testimonialText: { color: 'var(--text-secondary)', lineHeight: 1.6, marginBottom: '20px', fontStyle: 'italic', fontSize: '0.95rem' },
    footer: { borderTop: '1px solid var(--border-glass)', padding: '80px 6% 40px' },
    footerInner: { display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap' as const, gap: '40px', maxWidth: '1200px', margin: '0 auto 60px' },
    footerBrand: { maxWidth: '320px' },
    footerLinks: { display: 'flex', gap: '80px', flexWrap: 'wrap' as const },
    footerCol: { display: 'flex', flexDirection: 'column' as const, gap: '12px' },
    footerBottom: { textAlign: 'center' as const, color: 'rgba(255,255,255,0.3)', fontSize: '0.8rem', borderTop: '1px solid var(--border-glass)', paddingTop: '32px', maxWidth: '1200px', margin: '0 auto' },
};

export default Home;
