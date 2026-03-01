import React, { useEffect } from 'react';
import { Warehouse, GitBranch, ArrowRight, BarChart3, Rocket, TrendingUp, Users, Zap } from 'lucide-react';
import { Link } from 'react-router-dom';
import Header from '../components/Header';
import Footer from '../components/Footer';

const ParaEmpresas: React.FC = () => {
    useEffect(() => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal-active');
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

        return () => {
            observer.disconnect();
        };
    }, []);

    return (
        <div className="pe-page">
            <style>{`
                .pe-page { background: var(--bg-primary); color: var(--text-primary); min-height: 100vh; position: relative; overflow-x: hidden; font-family: 'Inter', sans-serif; }
                .pe-page * { box-sizing: border-box; }
                .bg-mesh {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background:
                        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(0, 85, 255, 0.06), transparent),
                        radial-gradient(ellipse 60% 40% at 80% 50%, rgba(59, 130, 246, 0.04), transparent),
                        radial-gradient(ellipse 50% 30% at 20% 80%, rgba(6, 182, 212, 0.03), transparent);
                }
                
                .reveal { opacity: 0; transform: translateY(30px) scale(0.95); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
                .reveal-active { opacity: 1; transform: translateY(0) scale(1); }
                .reveal-delay-1 { transition-delay: 0.1s; }
                .reveal-delay-2 { transition-delay: 0.2s; }
                .reveal-delay-3 { transition-delay: 0.3s; }

                .hero-section { position: relative; z-index: 1; padding: 60px 24px 40px; max-width: 1200px; margin: 0 auto; text-align: center; }
                .hero-glass { 
                    padding: 80px 48px; border-radius: 40px; 
                    background: rgba(255,255,255,0.65); backdrop-filter: blur(28px) saturate(1.5); 
                    border: 1px solid rgba(255,255,255,0.8); 
                    box-shadow: 0 20px 60px rgba(0,40,120,0.08), inset 0 1px 0 rgba(255,255,255,0.9); 
                    position: relative; overflow: hidden;
                    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .hero-glass:hover {
                    box-shadow: 0 30px 80px rgba(0,40,120,0.1);
                    transform: translateY(-4px);
                }
                .hero-glass::before { 
                    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; 
                    background: linear-gradient(90deg, #0055ff, #3b82f6, #06b6d4, #0055ff); 
                    background-size: 200% 100%; animation: gradient-flow 3s ease infinite;
                }
                @keyframes gradient-flow { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
                .gradient-text { background: linear-gradient(135deg, #0055ff, #3b82f6, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                
                .enterprise-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; max-width: 1200px; margin: 60px auto; padding: 0 24px; position: relative; z-index: 1; }
                .ent-card { 
                    padding: clamp(28px, 4vw, 40px); border-radius: 32px; 
                    background: rgba(255,255,255,0.55); backdrop-filter: blur(20px) saturate(1.3); 
                    border: 1px solid rgba(255,255,255,0.7); 
                    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1); 
                    box-shadow: 0 4px 16px rgba(0,40,120,0.04); 
                    transform-style: preserve-3d;
                    position: relative; overflow: hidden;
                }
                .ent-card::after {
                    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
                    background: var(--accent-gradient); transform: scaleX(0);
                    transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1); transform-origin: left;
                }
                .ent-card:hover { 
                    transform: translateY(-10px) rotateX(2deg) scale(1.01); 
                    background: rgba(255,255,255,0.82); 
                    border-color: rgba(0, 85, 255, 0.2); 
                    box-shadow: 0 30px 60px rgba(0,40,120,0.1); 
                }
                .ent-card:hover::after { transform: scaleX(1); }
                .ent-icon { 
                    width: clamp(52px, 8vw, 64px); height: clamp(52px, 8vw, 64px); border-radius: 20px; 
                    display: flex; align-items: center; justify-content: center; margin-bottom: 24px;
                    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                    position: relative;
                }
                .ent-icon::after {
                    content: ''; position: absolute; inset: -4px; border-radius: inherit;
                    background: inherit; opacity: 0.3; filter: blur(12px); z-index: -1;
                }
                .ent-card:hover .ent-icon { transform: scale(1.1) translateY(-2px); }
                .ent-card h3 { font-size: clamp(1.2rem, 2.5vw, 1.5rem); margin-bottom: 16px; font-weight: 800; font-family: 'Outfit', sans-serif; }
                .ent-card p { color: var(--text-secondary); line-height: 1.7; margin-bottom: 24px; }
                .ent-link {
                    color: #0055ff; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; 
                    text-decoration: none; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .ent-link:hover { gap: 14px; color: #3b82f6; }

                .stats-strip {
                    position: relative; z-index: 1;
                    max-width: 1200px; margin: 20px auto 60px; padding: 0 24px;
                    display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
                }
                .stat-pill {
                    padding: 24px; border-radius: 20px; text-align: center;
                    background: rgba(255,255,255,0.5); backdrop-filter: blur(16px);
                    border: 1px solid rgba(255,255,255,0.6);
                    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .stat-pill:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 16px 32px rgba(0,40,120,0.06);
                }
                .stat-pill-icon {
                    width: 44px; height: 44px; border-radius: 14px;
                    display: flex; align-items: center; justify-content: center;
                    margin: 0 auto 12px;
                }
                .stat-pill-value { font-size: 1.4rem; font-weight: 800; font-family: 'Outfit', sans-serif; color: var(--text-primary); }
                .stat-pill-label { font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px; }
                
                @media (max-width: 1024px) { .enterprise-grid { grid-template-columns: repeat(2, 1fr); } }
                @media (max-width: 768px) { 
                    .enterprise-grid { grid-template-columns: 1fr; padding: 0 16px; } 
                    .desktop-nav { display: none; } 
                    .hero-glass { padding: 48px 24px; border-radius: 28px; }
                    .hero-section { padding: 100px 20px 40px; }
                    .hero-section h1 { font-size: clamp(2rem, 7vw, 2.5rem) !important; letter-spacing: -1px !important; }
                    .hero-section p { font-size: 1rem !important; }
                    .stats-strip { grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
                    .stat-pill { padding: 16px; }
                    .stat-pill-value { font-size: 1.1rem; }
                    .ent-card:hover { transform: translateY(-4px) !important; }
                }
                @media (max-width: 480px) {
                    .hero-glass { padding: 36px 20px; }
                    .stats-strip { grid-template-columns: 1fr; }
                    .btn-primary { width: 100%; justify-content: center; }
                }
            `}</style>

            <div className="bg-mesh"></div>

            <Header />

            <section className="hero-section">
                <div className="hero-glass reveal">
                    <div style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', padding: '8px 18px', background: 'rgba(0, 85, 255, 0.06)', border: '1px solid rgba(0, 85, 255, 0.12)', borderRadius: '100px', fontSize: '0.8rem', fontWeight: 600, color: '#0055ff', marginBottom: '24px', textTransform: 'uppercase' as const }}><Rocket size={12} /> Soluções Corporativas</div>
                    <h1 style={{ fontSize: 'clamp(2.5rem, 6vw, 4rem)', fontWeight: 900, lineHeight: 1.1, letterSpacing: '-2px', marginBottom: '24px', fontFamily: 'Outfit, sans-serif' }}>Potencialize seu <span className="gradient-text">Ecommerce</span></h1>
                    <p style={{ color: 'var(--text-secondary)', fontSize: 'clamp(1rem, 2vw, 1.2rem)', maxWidth: '700px', margin: '0 auto 40px', lineHeight: 1.7 }}>Tecnologia de ponta e uma malha robusta para que você foque nas vendas enquanto cuidamos de toda a sua entrega.</p>
                    <Link to="/api-ecommerce" className="btn-primary" style={{ padding: '18px 48px', fontSize: '1.1rem', whiteSpace: 'nowrap', display: 'inline-flex' }}>Consultar API</Link>
                </div>
            </section>

            <div className="enterprise-grid">
                {[
                    { icon: <Warehouse size={32} color="#0055ff" />, bg: 'rgba(0, 85, 255, 0.06)', title: 'Coleta em Lote', desc: 'Agenciamos coletas diárias em seu CD ou loja, otimizando seu fluxo operacional de saída.' },
                    { icon: <GitBranch size={32} color="#3b82f6" />, bg: 'rgba(59, 130, 246, 0.06)', title: 'Integração ERP', desc: 'Conecte seu sistema (Tiny, Bling, etc) diretamente à Loggi para emissão automatizada de etiquetas.' },
                    { icon: <BarChart3 size={32} color="#06b6d4" />, bg: 'rgba(6, 182, 212, 0.06)', title: 'Logística Reversa', desc: 'Módulo completo para devoluções e trocas, garantindo a satisfação do seu cliente final.' }
                ].map((s, i) => (
                    <div key={i} className={`ent-card reveal reveal-delay-${i + 1}`}>
                        <div className="ent-icon" style={{ background: s.bg }}>{s.icon}</div>
                        <h3>{s.title}</h3>
                        <p>{s.desc}</p>
                        <Link to="/api-ecommerce" className="ent-link">Ver detalhes <ArrowRight size={16} /></Link>
                    </div>
                ))}
            </div>

            {/* Stats Strip */}
            <div className="stats-strip reveal">
                <div className="stat-pill">
                    <div className="stat-pill-icon" style={{ background: 'rgba(0, 85, 255, 0.06)' }}><TrendingUp size={22} color="#0055ff" /></div>
                    <div className="stat-pill-value">+500</div>
                    <div className="stat-pill-label">Empresas Parceiras</div>
                </div>
                <div className="stat-pill">
                    <div className="stat-pill-icon" style={{ background: 'rgba(16, 185, 129, 0.06)' }}><Users size={22} color="#10b981" /></div>
                    <div className="stat-pill-value">99.8%</div>
                    <div className="stat-pill-label">SLA de Entrega</div>
                </div>
                <div className="stat-pill">
                    <div className="stat-pill-icon" style={{ background: 'rgba(245, 158, 11, 0.06)' }}><Zap size={22} color="#f59e0b" /></div>
                    <div className="stat-pill-value">2x</div>
                    <div className="stat-pill-label">Mais Rápido</div>
                </div>
            </div>

            <Footer />
        </div>
    );
};

export default ParaEmpresas;
