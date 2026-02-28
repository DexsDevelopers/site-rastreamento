import React, { useEffect } from 'react';
import { Warehouse, GitBranch, ArrowRight, BarChart3, Rocket } from 'lucide-react';
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
                .pe-page { background: #06060b; color: #fff; min-height: 100vh; position: relative; overflow-x: hidden; font-family: 'Outfit', sans-serif; }
                .pe-page * { box-sizing: border-box; }
                .bg-mesh {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background:
                        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(99, 102, 241, 0.15), transparent),
                        radial-gradient(ellipse 60% 40% at 80% 50%, rgba(168, 85, 247, 0.08), transparent),
                        radial-gradient(ellipse 50% 30% at 20% 80%, rgba(6, 182, 212, 0.06), transparent);
                }
                
                .reveal { opacity: 0; transform: translateY(30px) scale(0.95); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
                .reveal-active { opacity: 1; transform: translateY(0) scale(1); }
                .reveal-delay-1 { transition-delay: 0.1s; }
                .reveal-delay-2 { transition-delay: 0.2s; }

                .nav-login-btn { display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; }

                .hero-section { position: relative; z-index: 1; padding: 60px 24px 40px; max-width: 1200px; margin: 0 auto; text-align: center; }
                .hero-glass { padding: 80px 48px; border-radius: 40px; background: rgba(255,255,255,0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.06); box-shadow: 0 16px 48px rgba(0,0,0,0.3); }
                .gradient-text { background: linear-gradient(135deg, #34d399, #6366f1, #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                
                .enterprise-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; max-width: 1200px; margin: 60px auto; padding: 0 24px; position: relative; z-index: 1; }
                .ent-card { padding: 40px; border-radius: 32px; background: rgba(255,255,255,0.02); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.06); transition: all 0.4s; }
                .ent-card:hover { transform: translateY(-10px); background: rgba(255,255,255,0.04); border-color: rgba(52, 211, 153, 0.3); }
                .ent-icon { width: 64px; height: 64px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin-bottom: 24px; }
                .ent-card h3 { font-size: 1.5rem; margin-bottom: 16px; font-weight: 800; }
                .ent-card p { color: rgba(255,255,255,0.45); line-height: 1.7; margin-bottom: 24px; }
                
                @media (max-width: 1024px) { .enterprise-grid { grid-template-columns: repeat(2, 1fr); } }
                @media (max-width: 768px) { 
                    .enterprise-grid { grid-template-columns: 1fr; } 
                    .desktop-nav { display: none; } 
                    .hero-glass { padding: 48px 24px; }
                    .hero-section { padding: 100px 20px 40px; }
                    .hero-section h1 { font-size: 2.5rem !important; }
                    .hero-section p { font-size: 1rem !important; }
                    .cta-button { padding: 14px 32px !important; font-size: 0.95rem !important; width: 100%; display: block; text-align: center; }
                }
            `}</style>

            <div className="bg-mesh"></div>

            <Header />

            <section className="hero-section">
                <div className="hero-glass reveal">
                    <div style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', padding: '8px 18px', background: 'rgba(52, 211, 153, 0.08)', border: '1px solid rgba(52, 211, 153, 0.2)', borderRadius: '100px', fontSize: '0.8rem', fontWeight: 600, color: '#6ee7b7', marginBottom: '24px', textTransform: 'uppercase' as const }}><Rocket size={12} /> Soluções Corporativas</div>
                    <h1 style={{ fontSize: 'clamp(2.5rem, 6vw, 4rem)', fontWeight: 900, lineHeight: 1.1, letterSpacing: '-2px', marginBottom: '24px' }}>Potencialize seu <span className="gradient-text">Ecommerce</span></h1>
                    <p style={{ color: 'rgba(255,255,255,0.45)', fontSize: '1.2rem', maxWidth: '700px', margin: '0 auto 40px' }}>Tecnologia de ponta e uma malha robusta para que você foque nas vendas enquanto cuidamos de toda a sua entrega.</p>
                    <Link to="/api-ecommerce" className="nav-login-btn cta-button" style={{ padding: '18px 48px', fontSize: '1.1rem', background: 'linear-gradient(135deg, #10b981, #6366f1)', boxShadow: '0 8px 32px rgba(16, 185, 129, 0.3)', whiteSpace: 'nowrap', display: 'inline-block' }}>Consultar API</Link>
                </div>
            </section>

            <div className="enterprise-grid">
                {[
                    { icon: <Warehouse size={32} color="#10b981" />, bg: 'rgba(52, 211, 153, 0.1)', title: 'Coleta em Lote', desc: 'Agenciamos coletas diárias em seu CD ou loja, otimizando seu fluxo operacional de saída.' },
                    { icon: <GitBranch size={32} color="#6366f1" />, bg: 'rgba(99, 102, 241, 0.1)', title: 'Integração ERP', desc: 'Conecte seu sistema (Tiny, Bling, etc) diretamente à Loggi para emissão automatizada de etiquetas.' },
                    { icon: <BarChart3 size={32} color="#a855f7" />, bg: 'rgba(168, 85, 247, 0.1)', title: 'Logística Reversa', desc: 'Módulo completo para devoluções e trocas, garantindo a satisfação do seu cliente final.' }
                ].map((s, i) => (
                    <div key={i} className={`ent-card reveal reveal-delay-${i + 1}`}>
                        <div className="ent-icon" style={{ background: s.bg }}>{s.icon}</div>
                        <h3>{s.title}</h3>
                        <p>{s.desc}</p>
                        <Link to="/api-ecommerce" className="nav-item" style={{ color: '#10b981', fontWeight: 700, display: 'flex', alignItems: 'center', gap: '8px' }}>Ver detalhes <ArrowRight size={16} /></Link>
                    </div>
                ))}
            </div>

            <Footer />
        </div>
    );
};

export default ParaEmpresas;
