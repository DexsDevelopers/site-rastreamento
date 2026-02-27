import React from 'react';
import { Truck, Shield, Cpu, Network, MapPin, Star, ChevronDown, Heart } from 'lucide-react';
import { Link } from 'react-router-dom';

const Sobre: React.FC = () => {
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
                        <Link to="/sobre" style={{ ...styles.navLink, color: 'var(--accent-primary)' }}>Sobre</Link>
                        <Link to="/login" style={styles.navBtnLogin}>Entrar</Link>
                    </nav>
                </div>
            </header>

            {/* Hero */}
            <section style={styles.hero}>
                <div style={styles.heroContent}>
                    <h1 style={styles.heroTitle} className="animate-fade">
                        Conectamos o Brasil<br />de ponta a ponta
                    </h1>
                    <p style={styles.heroDesc} className="animate-fade">
                        A Loggi utiliza inteligência logística e tecnologia proprietária para tornar as entregas mais rápidas, seguras e eficientes para todos os brasileiros.
                    </p>
                    <Link to="/" style={styles.ctaBtn} className="btn-primary">
                        <MapPin size={18} /> Rastrear agora
                    </Link>
                </div>
                <div style={styles.heroStats}>
                    <div style={styles.statBig} className="glass-card animate-fade">
                        <span style={styles.statBigVal}>+100M</span>
                        <span style={styles.statBigLabel}>Objetos Entregues</span>
                    </div>
                    <div style={{ ...styles.statBig, transform: 'rotate(2deg)', marginLeft: '3rem' }} className="glass-card animate-fade">
                        <span style={styles.statBigVal}>4k+</span>
                        <span style={styles.statBigLabel}>Cidades Atendidas</span>
                    </div>
                </div>
            </section>

            {/* Tecnologia */}
            <section style={styles.section}>
                <h2 style={styles.sectionTitle}>Nossa <span className="text-gradient">Tecnologia</span></h2>
                <p style={styles.sectionSub}>O que nos torna diferentes é a forma como usamos a inovação para resolver desafios logísticos complexos.</p>
                <div style={styles.cardsGrid}>
                    <div className="glass-card" style={styles.card}>
                        <div style={{ ...styles.cardIcon, background: 'rgba(99, 102, 241, 0.1)' }}>
                            <Cpu size={28} color="var(--accent-primary)" />
                        </div>
                        <h3 style={styles.cardTitle}>Inteligência de Rotas</h3>
                        <p style={styles.cardText}>Nossos algoritmos calculam as rotas mais eficientes em milisegundos, garantindo economia e velocidade.</p>
                    </div>
                    <div className="glass-card" style={styles.card}>
                        <div style={{ ...styles.cardIcon, background: 'rgba(139, 92, 246, 0.1)' }}>
                            <Shield size={28} color="var(--accent-secondary)" />
                        </div>
                        <h3 style={styles.cardTitle}>Segurança Total</h3>
                        <p style={styles.cardText}>Monitoramento avançado e biometria em todas as etapas garantem que seu objeto chegue intacto.</p>
                    </div>
                    <div className="glass-card" style={styles.card}>
                        <div style={{ ...styles.cardIcon, background: 'rgba(99, 102, 241, 0.1)' }}>
                            <Network size={28} color="var(--accent-primary)" />
                        </div>
                        <h3 style={styles.cardTitle}>Malha Integrada</h3>
                        <p style={styles.cardText}>A maior rede de pontos de postagem e centros de distribuição conectada por um único sistema inteligente.</p>
                    </div>
                </div>
            </section>

            {/* FAQ */}
            <section style={styles.faqSection}>
                <h2 style={styles.sectionTitle}>Perguntas <span className="text-gradient">Frequentes</span></h2>
                <div style={styles.faqList}>
                    {[
                        { q: 'Como faço para enviar um pacote?', a: 'Basta acessar "Enviar agora" em nossa home, preencher os dados do objeto e realizar o pagamento. Depois, é só levar sua etiqueta a um Ponto Loggi parceiro próximo a você.' },
                        { q: 'Qual o prazo de entrega padrão?', a: 'Os prazos variam de acordo com a origem e o destino. Para envios locais, entregamos em até 24h. Para envios nacionais expressos, o prazo médio é de 3 dias úteis.' },
                        { q: 'É possível acelerar uma entrega em curso?', a: 'Sim! Ao realizar o rastreio no nosso site, caso seu objeto seja elegível, você verá o botão "Acelerar Entrega". Siga as instruções para priorizar seu envio em nossa malha expressa.' },
                    ].map((item, i) => (
                        <details key={i} style={styles.faqItem} className="glass-card">
                            <summary style={styles.faqQuestion}>
                                {item.q}
                                <ChevronDown size={18} />
                            </summary>
                            <p style={styles.faqAnswer}>{item.a}</p>
                        </details>
                    ))}
                </div>
            </section>

            {/* Depoimentos */}
            <section style={styles.testimonialSection}>
                <h2 style={{ ...styles.sectionTitle, color: 'white' }}>Confiança de quem usa</h2>
                <div style={styles.cardsGrid}>
                    {[
                        { name: 'Ricardo Mendes', role: 'CEO, TechCommerce', text: '"A tecnologia da Loggi é incomparável. Consigo gerir todos os meus envios com uma facilidade que nunca tive antes."' },
                        { name: 'Juliana Costa', role: 'Gerente Logística, ModaBR', text: '"O suporte é excelente e as entregas sempre dentro do prazo. Meus clientes estão muito mais satisfeitos."' },
                        { name: 'Felipe Silva', role: 'Vendedor Platinum', text: '"Postar meus pacotes ficou 10x mais rápido com os Pontos Loggi. Recomendo para todos os vendedores."' },
                    ].map((t, i) => (
                        <div key={i} className="glass-card" style={styles.testimonial}>
                            <div style={styles.stars}>
                                {[...Array(5)].map((_, j) => <Star key={j} size={16} fill="var(--accent-primary)" color="var(--accent-primary)" />)}
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
                    <div>
                        <span style={styles.footerBrand}>loggi</span>
                        <p style={{ color: 'var(--text-secondary)', marginTop: '12px' }}>Reinventando a logística brasileira através de tecnologia própria e excelência operacional.</p>
                    </div>
                    <div style={styles.footerLinks}>
                        <div style={styles.footerCol}>
                            <h4>Soluções</h4>
                            <a href="#">Loggi para você</a>
                            <a href="#">Loggi para empresas</a>
                            <a href="#">E-commerce API</a>
                        </div>
                        <div style={styles.footerCol}>
                            <h4>Sobre</h4>
                            <Link to="/sobre">Nossa História</Link>
                            <a href="#">Carreiras</a>
                            <a href="#">Central de Ajuda</a>
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
    header: { padding: '20px 6%', position: 'sticky' as const, top: 0, zIndex: 100, backdropFilter: 'blur(12px)', borderBottom: '1px solid var(--border-glass)' },
    headerInner: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', maxWidth: '1200px', margin: '0 auto' },
    logo: { display: 'flex', alignItems: 'center', gap: '10px', textDecoration: 'none', color: 'white' },
    logoIcon: { width: '34px', height: '34px', background: 'var(--accent-primary)', borderRadius: '10px', display: 'flex', alignItems: 'center', justifyContent: 'center' },
    logoText: { fontSize: '1.3rem', fontWeight: 800 },
    nav: { display: 'flex', alignItems: 'center', gap: '28px' },
    navLink: { color: 'var(--text-secondary)', textDecoration: 'none', fontSize: '0.9rem', fontWeight: 500 },
    navBtnLogin: { padding: '10px 20px', background: 'var(--accent-primary)', borderRadius: '12px', color: 'white', textDecoration: 'none', fontWeight: 700, fontSize: '0.85rem' },
    hero: { padding: '100px 6% 80px', display: 'flex', alignItems: 'center', gap: '60px', maxWidth: '1200px', margin: '0 auto', flexWrap: 'wrap' as const },
    heroContent: { flex: 1, minWidth: '320px' },
    heroTitle: { fontSize: 'clamp(2rem, 5vw, 3.5rem)', fontWeight: 900, lineHeight: 1.1, letterSpacing: '-1px', marginBottom: '24px' },
    heroDesc: { color: 'var(--text-secondary)', fontSize: '1.1rem', lineHeight: 1.6, marginBottom: '32px', maxWidth: '550px' },
    ctaBtn: { display: 'inline-flex', alignItems: 'center', gap: '10px', padding: '14px 28px', textDecoration: 'none', borderRadius: '14px', fontSize: '1rem', fontWeight: 700 },
    heroStats: { display: 'flex', flexDirection: 'column' as const, gap: '24px' },
    statBig: { padding: '40px', borderRadius: '28px', textAlign: 'center' as const },
    statBigVal: { display: 'block', fontSize: '3rem', fontWeight: 900, marginBottom: '8px' },
    statBigLabel: { color: 'var(--text-secondary)', fontSize: '0.9rem' },
    section: { padding: '100px 6%', maxWidth: '1200px', margin: '0 auto', textAlign: 'center' as const },
    sectionTitle: { fontSize: '2.5rem', fontWeight: 800, marginBottom: '16px', letterSpacing: '-1px' },
    sectionSub: { color: 'var(--text-secondary)', fontSize: '1.1rem', marginBottom: '60px', maxWidth: '600px', margin: '0 auto 60px' },
    cardsGrid: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '24px' },
    card: { padding: '32px', borderRadius: '24px', textAlign: 'left' as const },
    cardIcon: { width: '56px', height: '56px', borderRadius: '16px', display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: '20px' },
    cardTitle: { fontSize: '1.2rem', fontWeight: 700, marginBottom: '12px' },
    cardText: { color: 'var(--text-secondary)', lineHeight: 1.6, fontSize: '0.95rem' },
    faqSection: { padding: '80px 6%', maxWidth: '800px', margin: '0 auto' },
    faqList: { display: 'flex', flexDirection: 'column' as const, gap: '16px' },
    faqItem: { padding: '24px', borderRadius: '20px', cursor: 'pointer' },
    faqQuestion: { fontWeight: 700, fontSize: '1.1rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center', listStyle: 'none' },
    faqAnswer: { marginTop: '16px', color: 'var(--text-secondary)', lineHeight: 1.7 },
    testimonialSection: { padding: '100px 6%', maxWidth: '1200px', margin: '0 auto', textAlign: 'center' as const, background: 'rgba(99, 102, 241, 0.03)', borderRadius: '40px', marginBottom: '80px' },
    testimonial: { padding: '32px', borderRadius: '24px', textAlign: 'left' as const },
    stars: { display: 'flex', gap: '4px', marginBottom: '16px' },
    testimonialText: { color: 'var(--text-secondary)', lineHeight: 1.6, marginBottom: '20px', fontStyle: 'italic' },
    footer: { borderTop: '1px solid var(--border-glass)', padding: '80px 6% 40px' },
    footerInner: { display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap' as const, gap: '40px', maxWidth: '1200px', margin: '0 auto 60px' },
    footerBrand: { fontSize: '1.5rem', fontWeight: 800, display: 'block' },
    footerLinks: { display: 'flex', gap: '80px' },
    footerCol: { display: 'flex', flexDirection: 'column' as const, gap: '12px' },
    footerBottom: { textAlign: 'center' as const, color: 'rgba(255,255,255,0.3)', fontSize: '0.8rem', borderTop: '1px solid var(--border-glass)', paddingTop: '32px', maxWidth: '1200px', margin: '0 auto' },
};

export default Sobre;
