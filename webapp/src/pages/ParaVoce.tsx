import React, { useEffect } from 'react';
import { QrCode, Satellite, Zap, ArrowRight, MapPin } from 'lucide-react';
import { Link } from 'react-router-dom';
import Header from '../components/Header';
import Footer from '../components/Footer';

const ParaVoce: React.FC = () => {
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
        <div className="pv-page">
            <style>{`
                .pv-page { background: var(--bg-primary); color: var(--text-primary); min-height: 100vh; position: relative; overflow-x: hidden; font-family: 'Outfit', sans-serif; }
                .pv-page * { box-sizing: border-box; }
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

                .hero-section { position: relative; z-index: 1; padding: 60px 24px 40px; max-width: 1200px; margin: 0 auto; text-align: center; }
                .hero-glass { padding: 80px 48px; border-radius: 40px; background: rgba(255,255,255,0.6); backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.8); box-shadow: 0 16px 48px rgba(0,40,120,0.08); position: relative; overflow: hidden; }
                .hero-glass::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #0055ff, #3b82f6, #06b6d4); }
                .gradient-text { background: linear-gradient(135deg, #0055ff, #3b82f6, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                
                .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; max-width: 1200px; margin: 60px auto; padding: 0 24px; position: relative; z-index: 1; }
                .service-card { padding: 40px; border-radius: 32px; background: rgba(255,255,255,0.55); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.7); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); box-shadow: 0 4px 16px rgba(0,40,120,0.04); transform-style: preserve-3d; }
                .service-card:hover { transform: translateY(-10px) rotateX(2deg); background: rgba(255,255,255,0.8); border-color: rgba(0, 85, 255, 0.2); box-shadow: 0 24px 48px rgba(0,40,120,0.1); }
                .service-icon { width: 64px; height: 64px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin-bottom: 24px; }
                .service-card h3 { font-size: 1.5rem; margin-bottom: 16px; font-weight: 800; }
                .service-card p { color: var(--text-secondary); line-height: 1.7; margin-bottom: 24px; }
                
                @media (max-width: 768px) { 
                    .desktop-nav { display: none; } 
                    .hero-glass { padding: 48px 24px; }
                    .hero-section { padding: 100px 20px 40px; }
                    .hero-section h1 { font-size: 2.5rem !important; }
                    .hero-section p { font-size: 1rem !important; }
                    .services-grid { grid-template-columns: 1fr; }
                }
            `}</style>

            <div className="bg-mesh"></div>

            <Header />

            <section className="hero-section">
                <div className="hero-glass reveal">
                    <div style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', padding: '8px 18px', background: 'rgba(0, 85, 255, 0.06)', border: '1px solid rgba(0, 85, 255, 0.12)', borderRadius: '100px', fontSize: '0.8rem', fontWeight: 600, color: '#0055ff', marginBottom: '24px', textTransform: 'uppercase' as const }}><Zap size={12} /> Soluções pessoais</div>
                    <h1 style={{ fontSize: 'clamp(2.5rem, 6vw, 4rem)', fontWeight: 900, lineHeight: 1.1, letterSpacing: '-2px', marginBottom: '24px' }}>Entregas para <span className="gradient-text">você</span></h1>
                    <p style={{ color: 'var(--text-secondary)', fontSize: '1.2rem', maxWidth: '700px', margin: '0 auto 40px' }}>Facilitamos o envio de seus objetos pessoais e compras online com segurança, rapidez e transparência total.</p>
                    <Link to="/pedido" className="btn-primary" style={{ padding: '18px 48px', fontSize: '1.1rem', whiteSpace: 'nowrap', display: 'inline-block' }}>Fazer um envio agora</Link>
                </div>
            </section>

            <div className="services-grid">
                {[
                    { icon: <QrCode size={32} color="#0055ff" />, bg: 'rgba(0, 85, 255, 0.06)', title: 'Geração de Etiqueta', desc: 'Crie etiquetas de envio online em segundos e poste em nossa rede de agências parceiras.', link: '/pedido' },
                    { icon: <Satellite size={32} color="#3b82f6" />, bg: 'rgba(59, 130, 246, 0.06)', title: 'Rastreio Web v3', desc: 'O sistema de rastreamento mais preciso do mercado, com atualizações em cada etapa da jornada.', link: '/rastreio' },
                    { icon: <MapPin size={32} color="#06b6d4" />, bg: 'rgba(6, 182, 212, 0.06)', title: 'Perto de Você', desc: 'Milhares de pontos de coleta e entrega espalhados pelo Brasil para sua conveniência.', link: '/ajuda' }
                ].map((s, i) => (
                    <div key={i} className={`service-card reveal reveal-delay-${i + 1}`}>
                        <div className="service-icon" style={{ background: s.bg }}>{s.icon}</div>
                        <h3>{s.title}</h3>
                        <p>{s.desc}</p>
                        <Link to={s.link} style={{ color: '#0055ff', fontWeight: 700, display: 'flex', alignItems: 'center', gap: '8px', textDecoration: 'none' }}>Saiba mais <ArrowRight size={16} /></Link>
                    </div>
                ))}
            </div>

            <Footer />
        </div>
    );
};

export default ParaVoce;
