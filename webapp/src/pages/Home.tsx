import React, { useState } from 'react';
import { Truck, Search, Zap, ArrowRight, Globe, QrCode, Satellite, Package, Warehouse, GitBranch, RotateCcw, Smile, MapPinned, Star, Heart, Menu, X, Calculator } from 'lucide-react';
import { Link } from 'react-router-dom';

const API_BASE = import.meta.env.VITE_API_URL || '';

const Home: React.FC = () => {
    const [codigo, setCodigo] = useState('');
    const [cidade, setCidade] = useState('');
    const [loading, setLoading] = useState(false);
    const [trackResult, setTrackResult] = useState<any>(null);
    const [trackError, setTrackError] = useState('');
    const [activeTab, setActiveTab] = useState<'voce' | 'empresas'>('voce');
    const [mobileMenu, setMobileMenu] = useState(false);

    const handleSearch = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!codigo || !cidade) return;
        setLoading(true);
        setTrackError('');
        setTrackResult(null);

        try {
            const res = await fetch(`${API_BASE}/api/rastreio`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ codigo: codigo.toUpperCase(), cidade }),
            });
            const data = await res.json();
            if (data.success && data.etapas?.length > 0) {
                setTrackResult(data);
            } else {
                setTrackError(data.message || 'Código não encontrado.');
            }
        } catch {
            // Simulação para demonstrar o visual
            setTrackResult({
                statusAtual: 'Em Trânsito',
                cidade: cidade,
                etapas: [
                    { titulo: 'Objeto postado', subtitulo: 'Pacote recebido na agência', data: '2026-02-25 10:30:00', status_atual: 'Postado' },
                    { titulo: 'Em trânsito', subtitulo: 'Objeto encaminhado para unidade de tratamento', data: '2026-02-26 22:10:00', status_atual: 'Em trânsito' },
                    { titulo: 'Saiu para entrega', subtitulo: 'O objeto saiu para entrega ao destinatário', data: '2026-02-27 15:45:00', status_atual: 'Em rota' },
                ],
            });
        } finally {
            setLoading(false);
        }
    };

    const formatDate = (dateStr: string) => {
        const d = new Date(dateStr);
        return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    };

    const getStatusIcon = (status: string) => {
        const s = status.toLowerCase();
        if (s.includes('saiu') || s.includes('entrega') || s.includes('rota')) return '🚚';
        if (s.includes('trânsito') || s.includes('transito')) return '📦';
        if (s.includes('postado') || s.includes('coletado')) return '📬';
        if (s.includes('entregue')) return '✅';
        if (s.includes('aguardando') || s.includes('pagamento')) return '⏳';
        return '📍';
    };

    return (
        <div style={styles.page}>
            {/* Efeitos de fundo */}
            <div style={styles.bgOrb1}></div>
            <div style={styles.bgOrb2}></div>
            <div style={styles.gridOverlay}></div>

            {/* ===== HEADER ===== */}
            <header style={styles.header}>
                <div style={styles.headerInner}>
                    <Link to="/" style={styles.logo}>
                        <div style={styles.logoIcon}><Truck size={18} color="white" /></div>
                        <span style={styles.logoText}>loggi</span>
                    </Link>

                    {/* Desktop Nav */}
                    <nav style={styles.desktopNav}>
                        <Link to="/" style={styles.navLink}>Início</Link>
                        <a href="#para-voce" style={styles.navLink}>Para você</a>
                        <a href="#para-empresas" style={styles.navLink}>Para empresas</a>
                        <Link to="/sobre" style={styles.navLink}>Sobre</Link>
                        <Link to="/entrar" style={styles.navBtnLogin}>Entrar</Link>
                    </nav>

                    {/* Mobile Toggle */}
                    <button style={styles.menuBtn} onClick={() => setMobileMenu(!mobileMenu)}>
                        {mobileMenu ? <X size={24} /> : <Menu size={24} />}
                    </button>
                </div>

                {/* Mobile Nav */}
                {mobileMenu && (
                    <nav style={styles.mobileNav} className="animate-fade">
                        <Link to="/" style={styles.mobileLink} onClick={() => setMobileMenu(false)}>Início</Link>
                        <a href="#para-voce" style={styles.mobileLink} onClick={() => setMobileMenu(false)}>Para você</a>
                        <a href="#para-empresas" style={styles.mobileLink} onClick={() => setMobileMenu(false)}>Para empresas</a>
                        <Link to="/sobre" style={styles.mobileLink} onClick={() => setMobileMenu(false)}>Sobre</Link>
                        <Link to="/entrar" style={{ ...styles.mobileLink, color: 'var(--accent-primary)', fontWeight: 700 }} onClick={() => setMobileMenu(false)}>Entrar</Link>
                    </nav>
                )}
            </header>

            {/* ===== HERO ===== */}
            <section style={styles.hero}>
                <div style={styles.heroInner}>
                    <div style={styles.heroLeft} className="animate-fade">
                        <h1 style={styles.heroTitle}>
                            O rastreio do seu<br />envio é <span className="text-gradient">prático</span>
                        </h1>
                        <p style={styles.heroDesc}>
                            Acompanhe seu pedido em tempo real com a Loggi. Frete grátis para todo o Brasil.
                        </p>

                        <div style={styles.heroActions}>
                            <Link to="/pedido" className="btn-primary" style={styles.ctaBtn}>
                                <Package size={18} /> Enviar agora
                            </Link>
                            <Link to="/pedido" style={styles.ctaSecondary}>
                                <Calculator size={18} /> Calcular frete
                            </Link>
                        </div>

                        {/* Formulário de Rastreio */}
                        <form onSubmit={handleSearch} style={styles.trackForm}>
                            <div style={styles.trackFields}>
                                <div style={styles.trackFieldWrap}>
                                    <Search size={16} color="var(--accent-primary)" />
                                    <input
                                        type="text"
                                        placeholder="Código de rastreio"
                                        value={codigo}
                                        onChange={(e) => setCodigo(e.target.value.toUpperCase())}
                                        maxLength={12}
                                        required
                                        style={styles.trackInput}
                                    />
                                </div>
                                <div style={styles.trackFieldWrap}>
                                    <MapPinned size={16} color="var(--accent-primary)" />
                                    <input
                                        type="text"
                                        placeholder="Sua cidade"
                                        value={cidade}
                                        onChange={(e) => setCidade(e.target.value)}
                                        required
                                        style={styles.trackInput}
                                    />
                                </div>
                            </div>
                            <button type="submit" className="btn-primary" style={styles.trackSubmit} disabled={loading}>
                                {loading ? '⏳ Buscando...' : '🔍 Rastrear agora'}
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            {/* ===== RESULTADO DO RASTREIO ===== */}
            {trackError && (
                <div style={styles.resultArea} className="animate-fade">
                    <div style={styles.errorCard} className="glass-card-3d">
                        <span style={{ fontSize: '2.5rem' }}>❌</span>
                        <h3 style={{ color: 'var(--danger)' }}>{trackError}</h3>
                    </div>
                </div>
            )}

            {trackResult && (
                <div style={styles.resultArea} className="animate-fade">
                    <div style={styles.resultCard} className="glass-card-3d">
                        {/* Status Header */}
                        <div style={styles.statusHeader}>
                            <div style={styles.statusIconBox}>
                                <span style={{ fontSize: '2rem' }}>
                                    {getStatusIcon(trackResult.etapas[trackResult.etapas.length - 1]?.status_atual || '')}
                                </span>
                            </div>
                            <div>
                                <h3 style={styles.statusTitle}>{trackResult.etapas[trackResult.etapas.length - 1]?.status_atual || 'Em processamento'}</h3>
                                <p style={styles.statusCity}>📍 {cidade}</p>
                            </div>
                        </div>

                        {/* Timeline */}
                        <div style={styles.timeline}>
                            {trackResult.etapas.map((etapa: any, index: number) => {
                                const isLast = index === trackResult.etapas.length - 1;
                                return (
                                    <div key={index} style={styles.tlItem}>
                                        <div style={styles.tlMarkerCol}>
                                            <div style={{
                                                ...styles.tlDot,
                                                background: isLast ? 'var(--accent-primary)' : 'rgba(255,255,255,0.1)',
                                                boxShadow: isLast ? '0 0 16px var(--accent-glow)' : 'none',
                                            }}></div>
                                            {index < trackResult.etapas.length - 1 && <div style={styles.tlLine}></div>}
                                        </div>
                                        <div style={{
                                            ...styles.tlContent,
                                            background: isLast ? 'rgba(99, 102, 241, 0.06)' : 'transparent',
                                            border: isLast ? '1px solid rgba(99, 102, 241, 0.15)' : '1px solid transparent',
                                        }}>
                                            <h4 style={{ color: isLast ? 'var(--accent-primary)' : 'var(--text-primary)', fontWeight: 700 }}>
                                                {etapa.titulo}
                                            </h4>
                                            <p style={{ color: 'var(--text-secondary)', fontSize: '0.9rem', margin: '4px 0' }}>{etapa.subtitulo}</p>
                                            <small style={{ color: 'var(--accent-primary)', fontWeight: 600, fontSize: '0.8rem' }}>
                                                {formatDate(etapa.data)}
                                            </small>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Botão Express */}
                        <div style={styles.expressBox}>
                            <button className="btn-primary" style={styles.expressBtn}>
                                ⚡ Acelerar por R$ 29,90
                            </button>
                            <p style={{ color: 'var(--text-secondary)', fontSize: '0.85rem', marginTop: '8px' }}>Receba em até 3 dias úteis</p>
                        </div>
                    </div>
                </div>
            )}

            {/* ===== TABS: Para Você / Para Empresas ===== */}
            <div style={styles.tabsWrap}>
                <div style={styles.tabsBar}>
                    <button style={activeTab === 'voce' ? styles.tabActive : styles.tabBtn} onClick={() => setActiveTab('voce')}>Para você</button>
                    <button style={activeTab === 'empresas' ? styles.tabActive : styles.tabBtn} onClick={() => setActiveTab('empresas')}>Para empresas</button>
                </div>
            </div>

            {/* Para Você */}
            {activeTab === 'voce' && (
                <section id="para-voce" style={styles.section} className="animate-fade">
                    <div style={styles.sectionInner}>
                        <h2 style={styles.sectionTitle}>A Loggi entrega onde você precisar</h2>
                        <p style={styles.sectionSub}>A maior malha logística privada do Brasil à sua disposição.</p>
                        <div style={styles.grid3}>
                            {[
                                { icon: <QrCode size={28} color="var(--accent-primary)" />, title: 'Postagem simples', desc: 'Gere sua etiqueta em poucos cliques e poste em qualquer ponto parceiro próximo a você.', link: '/pedido', linkText: 'Começar agora' },
                                { icon: <Satellite size={28} color="var(--accent-secondary)" />, title: 'Monitoramento GPS', desc: 'Acompanhe cada curva da sua encomenda com tecnologia de rastreio via satélite em tempo real.', link: '#', linkText: 'Ver como funciona' },
                                { icon: <Zap size={28} color="#06b6d4" />, title: 'Loggi Express', desc: 'Sua encomenda priorizada em nossa malha expressa para chegar ao destino em tempo recorde.', link: '#', linkText: 'Pedir urgência' },
                            ].map((c, i) => (
                                <div key={i} className="glass-card-3d" style={styles.featureCard}>
                                    <div style={styles.featureIcon}>{c.icon}</div>
                                    <h3 style={styles.featureTitle}>{c.title}</h3>
                                    <p style={styles.featureDesc}>{c.desc}</p>
                                    <Link to={c.link} style={styles.featureLink}>{c.linkText} <ArrowRight size={14} /></Link>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* Para Empresas */}
            {activeTab === 'empresas' && (
                <section id="para-empresas" style={styles.section} className="animate-fade">
                    <div style={styles.sectionInner}>
                        <h2 style={styles.sectionTitle}>Logística inteligente para negócios</h2>
                        <p style={styles.sectionSub}>Potencialize suas vendas com a malha logística que mais cresce no país.</p>
                        <div style={styles.grid3}>
                            {[
                                { icon: <Warehouse size={28} color="var(--accent-primary)" />, title: 'Coleta loggi', desc: 'Equipe dedicada para coletar seus envios diretamente no seu centro de distribuição.' },
                                { icon: <GitBranch size={28} color="var(--accent-secondary)" />, title: 'API de Integração', desc: 'Conecte seu e-commerce diretamente com nosso sistema para automação total de fretes.' },
                                { icon: <RotateCcw size={28} color="#06b6d4" />, title: 'Reversa Facilitada', desc: 'Gestão completa de trocas e devoluções para encantar seus clientes no pós-venda.' },
                            ].map((c, i) => (
                                <div key={i} className="glass-card-3d" style={styles.featureCard}>
                                    <div style={styles.featureIcon}>{c.icon}</div>
                                    <h3 style={styles.featureTitle}>{c.title}</h3>
                                    <p style={styles.featureDesc}>{c.desc}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* ===== PROVA SOCIAL ===== */}
            <section style={styles.proofSection}>
                <div style={styles.grid3}>
                    <div className="glass-card-3d" style={styles.proofCard}>
                        <Smile size={40} color="var(--accent-primary)" />
                        <span style={styles.proofVal}>4.8/5</span>
                        <span style={styles.proofLabel}>Satisfação dos Clientes</span>
                    </div>
                    <div className="glass-card-3d" style={{ ...styles.proofCard, borderColor: 'rgba(99, 102, 241, 0.3)' }}>
                        <Package size={48} color="var(--accent-primary)" />
                        <span style={styles.proofVal}>10M+</span>
                        <span style={styles.proofLabel}>Entregas Realizadas</span>
                    </div>
                    <div className="glass-card-3d" style={styles.proofCard}>
                        <Globe size={40} color="var(--accent-secondary)" />
                        <span style={styles.proofVal}>4.5k+</span>
                        <span style={styles.proofLabel}>Cidades Atendidas</span>
                    </div>
                </div>
            </section>

            {/* ===== DEPOIMENTOS ===== */}
            <section style={styles.testimonialSection}>
                <div style={styles.sectionInner}>
                    <h2 style={{ ...styles.sectionTitle, textAlign: 'center' as const, marginBottom: '48px' }}>Confiança de <span className="text-gradient">quem usa</span></h2>
                    <div style={styles.grid3}>
                        {[
                            { name: 'Ricardo Mendes', role: 'CEO, TechCommerce', text: '"A tecnologia da Loggi é incomparável. Consigo gerir todos os meus envios com uma facilidade que nunca tive antes."' },
                            { name: 'Juliana Costa', role: 'Gerente Logística, ModaBR', text: '"O suporte é excelente e as entregas sempre dentro do prazo. Meus clientes estão muito mais satisfeitos."' },
                            { name: 'Felipe Silva', role: 'Vendedor Platinum', text: '"Postar meus pacotes ficou 10x mais rápido com os Pontos Loggi. Recomendo para todos os vendedores."' },
                        ].map((t, i) => (
                            <div key={i} className="glass-card-3d" style={styles.testimonialCard}>
                                <div style={styles.stars}>
                                    {[...Array(5)].map((_, j) => <Star key={j} size={14} fill="#f59e0b" color="#f59e0b" />)}
                                </div>
                                <p style={styles.testimonialText}>{t.text}</p>
                                <div>
                                    <strong>{t.name}</strong>
                                    <span style={{ display: 'block', color: 'var(--text-secondary)', fontSize: '0.85rem', marginTop: '4px' }}>{t.role}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ===== FOOTER ===== */}
            <footer style={styles.footer}>
                <div style={styles.footerInner}>
                    <div style={styles.footerBrandCol}>
                        <Link to="/" style={{ ...styles.logo, textDecoration: 'none', color: 'white' }}>
                            <div style={styles.logoIcon}><Truck size={16} color="white" /></div>
                            <span style={styles.logoText}>loggi</span>
                        </Link>
                        <p style={{ color: 'var(--text-secondary)', marginTop: '16px', lineHeight: 1.6, fontSize: '0.9rem' }}>
                            Reinventando a logística brasileira através de tecnologia própria e excelência operacional.
                        </p>
                    </div>
                    <div style={styles.footerLinksWrap}>
                        <div style={styles.footerCol}>
                            <h4 style={styles.footerColTitle}>Soluções</h4>
                            <a href="#" style={styles.footerLink}>Loggi para você</a>
                            <a href="#" style={styles.footerLink}>Loggi para empresas</a>
                            <a href="#" style={styles.footerLink}>E-commerce API</a>
                            <a href="#" style={styles.footerLink}>Loggi Pro</a>
                        </div>
                        <div style={styles.footerCol}>
                            <h4 style={styles.footerColTitle}>Sobre</h4>
                            <Link to="/sobre" style={styles.footerLink}>Nossa História</Link>
                            <a href="#" style={styles.footerLink}>Carreiras</a>
                            <a href="#" style={styles.footerLink}>Central de Ajuda</a>
                            <a href="#" style={styles.footerLink}>Termos de Uso</a>
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

/* ===== ESTILOS ===== */
const styles: { [key: string]: React.CSSProperties } = {
    page: { background: 'var(--bg-primary)', color: 'var(--text-primary)', minHeight: '100vh', position: 'relative', overflow: 'hidden' },
    bgOrb1: { position: 'fixed', width: '600px', height: '600px', background: 'radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, transparent 70%)', top: '-150px', right: '-150px', borderRadius: '50%', filter: 'blur(60px)', pointerEvents: 'none', zIndex: 0 },
    bgOrb2: { position: 'fixed', width: '500px', height: '500px', background: 'radial-gradient(circle, rgba(168, 85, 247, 0.06) 0%, transparent 70%)', bottom: '-100px', left: '-100px', borderRadius: '50%', filter: 'blur(60px)', pointerEvents: 'none', zIndex: 0 },
    gridOverlay: { position: 'fixed', inset: 0, backgroundImage: 'linear-gradient(rgba(255,255,255,0.015) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px)', backgroundSize: '80px 80px', pointerEvents: 'none', zIndex: 0 },

    // Header
    header: { position: 'sticky', top: 0, zIndex: 100, backdropFilter: 'blur(20px) saturate(1.2)', borderBottom: '1px solid var(--border-glass)', background: 'rgba(6, 6, 11, 0.85)' },
    headerInner: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', maxWidth: '1200px', margin: '0 auto', padding: '16px 24px' },
    logo: { display: 'flex', alignItems: 'center', gap: '10px', textDecoration: 'none', color: 'white' },
    logoIcon: { width: '36px', height: '36px', background: 'var(--accent-gradient)', borderRadius: '11px', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 4px 16px var(--accent-glow)' },
    logoText: { fontSize: '1.3rem', fontWeight: 800, fontFamily: "'Outfit', sans-serif" },
    desktopNav: { display: 'flex', alignItems: 'center', gap: '28px' },
    navLink: { color: 'var(--text-secondary)', textDecoration: 'none', fontSize: '0.9rem', fontWeight: 500 },
    navBtnLogin: { padding: '10px 22px', background: 'var(--accent-gradient)', borderRadius: '12px', color: 'white', textDecoration: 'none', fontWeight: 700, fontSize: '0.85rem', boxShadow: '0 4px 12px var(--accent-glow)' },
    menuBtn: { display: 'none', background: 'none', border: 'none', color: 'white', cursor: 'pointer' },
    mobileNav: { display: 'flex', flexDirection: 'column' as const, gap: '16px', padding: '16px 24px 24px', borderTop: '1px solid var(--border-glass)' },
    mobileLink: { color: 'var(--text-secondary)', textDecoration: 'none', fontSize: '1rem', fontWeight: 500, padding: '8px 0' },

    // Hero
    hero: { position: 'relative', zIndex: 1, padding: '80px 24px 60px', maxWidth: '1200px', margin: '0 auto' },
    heroInner: { display: 'flex', alignItems: 'center', gap: '60px', flexWrap: 'wrap' as const },
    heroLeft: { flex: 1, minWidth: '300px' },
    heroTitle: { fontSize: 'clamp(2rem, 5vw, 3.5rem)', fontWeight: 900, lineHeight: 1.1, letterSpacing: '-1.5px', marginBottom: '20px' },
    heroDesc: { color: 'var(--text-secondary)', fontSize: 'clamp(0.95rem, 2vw, 1.1rem)', lineHeight: 1.7, marginBottom: '32px', maxWidth: '550px' },
    heroActions: { display: 'flex', gap: '12px', marginBottom: '40px', flexWrap: 'wrap' as const },
    ctaBtn: { padding: '14px 28px', textDecoration: 'none', borderRadius: '14px', fontSize: '0.95rem', fontWeight: 700 },
    ctaSecondary: { display: 'inline-flex', alignItems: 'center', gap: '8px', padding: '14px 24px', border: '1px solid var(--border-glass-strong)', borderRadius: '14px', color: 'var(--text-primary)', textDecoration: 'none', fontWeight: 600, fontSize: '0.95rem', background: 'rgba(255,255,255,0.03)', transition: 'all 0.3s' },

    // Tracking Form
    trackForm: { maxWidth: '480px' },
    trackFields: { display: 'flex', flexDirection: 'column' as const, gap: '12px', marginBottom: '12px' },
    trackFieldWrap: { display: 'flex', alignItems: 'center', gap: '12px', background: 'rgba(255,255,255,0.03)', border: '1px solid var(--border-glass)', borderRadius: '14px', padding: '4px 16px', transition: 'border-color 0.3s' },
    trackInput: { flex: 1, background: 'transparent', border: 'none', color: 'white', padding: '14px 0', fontSize: '0.95rem', outline: 'none', fontFamily: "'Inter', sans-serif" },
    trackSubmit: { width: '100%', padding: '16px', fontSize: '1rem', fontWeight: 700, borderRadius: '14px' },

    // Result Area
    resultArea: { position: 'relative', zIndex: 1, maxWidth: '800px', margin: '0 auto', padding: '0 24px 60px' },
    errorCard: { padding: '48px', borderRadius: '28px', textAlign: 'center' as const, display: 'flex', flexDirection: 'column' as const, alignItems: 'center', gap: '16px' },
    resultCard: { padding: '32px', borderRadius: '28px' },
    statusHeader: { display: 'flex', alignItems: 'center', gap: '20px', marginBottom: '32px', padding: '24px', background: 'rgba(99, 102, 241, 0.04)', borderRadius: '20px', border: '1px solid rgba(99, 102, 241, 0.1)' },
    statusIconBox: { width: '64px', height: '64px', borderRadius: '20px', background: 'rgba(99, 102, 241, 0.08)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 },
    statusTitle: { fontSize: '1.3rem', fontWeight: 800 },
    statusCity: { color: 'var(--text-secondary)', marginTop: '4px', fontSize: '0.9rem' },

    // Timeline
    timeline: { display: 'flex', flexDirection: 'column' as const },
    tlItem: { display: 'flex', gap: '20px' },
    tlMarkerCol: { display: 'flex', flexDirection: 'column' as const, alignItems: 'center', width: '20px', flexShrink: 0 },
    tlDot: { width: '14px', height: '14px', borderRadius: '50%', border: '2px solid var(--border-glass)', flexShrink: 0, zIndex: 2 },
    tlLine: { width: '2px', flex: 1, background: 'var(--border-glass)', minHeight: '40px' },
    tlContent: { flex: 1, padding: '16px 20px', borderRadius: '16px', marginBottom: '12px' },

    // Express
    expressBox: { textAlign: 'center' as const, padding: '24px 0 0', marginTop: '24px', borderTop: '2px dashed var(--border-glass)' },
    expressBtn: { padding: '16px 32px', fontSize: '1rem', fontWeight: 800, borderRadius: '16px', background: 'linear-gradient(135deg, #0096ff, #6366f1)', boxShadow: '0 8px 24px rgba(0, 150, 255, 0.3)' },

    // Tabs
    tabsWrap: { position: 'relative', zIndex: 1, maxWidth: '1200px', margin: '0 auto', padding: '20px 24px 0' },
    tabsBar: { display: 'inline-flex', gap: '4px', padding: '4px', background: 'rgba(255,255,255,0.02)', borderRadius: '16px', border: '1px solid var(--border-glass)' },
    tabBtn: { padding: '12px 28px', background: 'transparent', border: 'none', color: 'var(--text-secondary)', cursor: 'pointer', borderRadius: '12px', fontWeight: 600, fontSize: '0.9rem', fontFamily: "'Outfit', sans-serif", transition: 'all 0.3s' },
    tabActive: { padding: '12px 28px', background: 'var(--accent-primary)', border: 'none', color: 'white', cursor: 'pointer', borderRadius: '12px', fontWeight: 700, fontSize: '0.9rem', fontFamily: "'Outfit', sans-serif", boxShadow: '0 4px 16px var(--accent-glow)' },

    // Sections
    section: { position: 'relative', zIndex: 1, padding: '60px 24px 80px' },
    sectionInner: { maxWidth: '1200px', margin: '0 auto' },
    sectionTitle: { fontSize: 'clamp(1.6rem, 4vw, 2.2rem)', fontWeight: 800, marginBottom: '12px', letterSpacing: '-0.5px' },
    sectionSub: { color: 'var(--text-secondary)', fontSize: '1rem', marginBottom: '48px' },
    grid3: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: '20px' },

    // Feature Cards
    featureCard: { padding: '32px', borderRadius: '24px', display: 'flex', flexDirection: 'column' as const, gap: '16px' },
    featureIcon: { width: '56px', height: '56px', borderRadius: '16px', background: 'rgba(99, 102, 241, 0.06)', display: 'flex', alignItems: 'center', justifyContent: 'center' },
    featureTitle: { fontSize: '1.15rem', fontWeight: 700 },
    featureDesc: { color: 'var(--text-secondary)', lineHeight: 1.6, fontSize: '0.9rem', flex: 1 },
    featureLink: { color: 'var(--accent-primary)', textDecoration: 'none', fontWeight: 700, fontSize: '0.85rem', display: 'flex', alignItems: 'center', gap: '6px' },

    // Proof
    proofSection: { position: 'relative', zIndex: 1, padding: '40px 24px 80px', maxWidth: '1200px', margin: '0 auto' },
    proofCard: { padding: '40px 24px', borderRadius: '24px', textAlign: 'center' as const, display: 'flex', flexDirection: 'column' as const, alignItems: 'center', gap: '12px' },
    proofVal: { fontSize: 'clamp(2rem, 5vw, 2.8rem)', fontWeight: 900 },
    proofLabel: { color: 'var(--text-secondary)', fontSize: '0.9rem' },

    // Testimonials
    testimonialSection: { position: 'relative', zIndex: 1, padding: '80px 24px', background: 'rgba(99, 102, 241, 0.02)', borderTop: '1px solid var(--border-glass)', borderBottom: '1px solid var(--border-glass)' },
    testimonialCard: { padding: '28px', borderRadius: '20px', display: 'flex', flexDirection: 'column' as const, gap: '16px' },
    stars: { display: 'flex', gap: '3px' },
    testimonialText: { color: 'var(--text-secondary)', lineHeight: 1.6, fontSize: '0.95rem', fontStyle: 'italic', flex: 1 },

    // Footer
    footer: { position: 'relative', zIndex: 1, borderTop: '1px solid var(--border-glass)', padding: '80px 24px 40px' },
    footerInner: { display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap' as const, gap: '40px', maxWidth: '1200px', margin: '0 auto 60px' },
    footerBrandCol: { maxWidth: '320px' },
    footerLinksWrap: { display: 'flex', gap: '60px', flexWrap: 'wrap' as const },
    footerCol: { display: 'flex', flexDirection: 'column' as const, gap: '12px' },
    footerColTitle: { fontWeight: 700, marginBottom: '4px', fontSize: '0.95rem' },
    footerLink: { color: 'var(--text-secondary)', textDecoration: 'none', fontSize: '0.85rem', transition: 'color 0.2s' },
    footerBottom: { textAlign: 'center' as const, color: 'rgba(255,255,255,0.25)', fontSize: '0.8rem', borderTop: '1px solid var(--border-glass)', paddingTop: '32px', maxWidth: '1200px', margin: '0 auto' },
};

export default Home;
