import React, { useState, useEffect } from 'react';
import { Search, Package, MapPin, CheckCircle2, ArrowRight, Share2, Printer, Truck } from 'lucide-react';
import Header from '../components/Header';
import Footer from '../components/Footer';

const TrackingPage: React.FC = () => {
    const [codigo, setCodigo] = useState('');
    const [trackingData, setTrackingData] = useState<any>(null);
    const [loading, setLoading] = useState(false);
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

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        if (!codigo) return;

        setLoading(true);
        setTimeout(() => {
            setTrackingData({
                codigo: codigo.toUpperCase(),
                status: 'Em rota de entrega',
                ultimaAtualizacao: '27 Fev 2026 às 15:45',
                previsao: '01 Mar 2026',
                destinatário: 'Usuário Premium',
                origem: 'São Paulo, SP',
                destino: 'Rio de Janeiro, RJ',
                etapaAtual: 3,
                eventos: [
                    { id: 4, status: 'Objeto saiu para entrega', local: 'Unidade RJ', data: '27 Fev 2026 - 15:45', detalhes: 'O objeto saiu para entrega ao destinatário', icon: <Truck size={20} /> },
                    { id: 3, status: 'Em trânsito', local: 'São Paulo -> Rio', data: '26 Fev 2026 - 22:10', detalhes: 'Objeto encaminhado para Unidade de Tratamento', icon: <Package size={20} /> },
                    { id: 2, status: 'Postado', local: 'Agência Central', data: '26 Fev 2026 - 10:30', detalhes: 'Objeto recebido na agência de postagem', icon: <MapPin size={20} /> },
                    { id: 1, status: 'Pedido Criado', local: 'Sistema', data: '25 Fev 2026 - 18:00', detalhes: 'Informações enviadas para a transportadora', icon: <CheckCircle2 size={20} /> },
                ]
            });
            setLoading(false);
        }, 1500);
    };

    return (
        <div className="tr-page">
            <style>{`
                .tr-page { background: #06060b; color: #fff; min-height: 100vh; position: relative; overflow-x: hidden; font-family: 'Outfit', sans-serif; }
                .tr-page * { box-sizing: border-box; }
                .bg-mesh {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background:
                        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(99, 102, 241, 0.15), transparent),
                        radial-gradient(ellipse 60% 40% at 80% 50%, rgba(168, 85, 247, 0.08), transparent),
                        radial-gradient(ellipse 50% 30% at 20% 80%, rgba(6, 182, 212, 0.06), transparent);
                }
                
                .reveal { opacity: 0; transform: translateY(30px) scale(0.95); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
                .reveal-active { opacity: 1; transform: translateY(0) scale(1); }

                .search-box-premium {
                    max-width: 600px; margin: 0 auto;
                    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1);
                    backdrop-filter: blur(20px); border-radius: 32px; padding: 12px;
                    display: flex; gap: 12px; align-items: center;
                }
                .search-input-premium { flex: 1; background: transparent; border: none; color: white; padding: 10px 20px; font-size: 1.1rem; outline: none; }
                .btn-track { padding: 14px 32px; background: linear-gradient(135deg, #6366f1, #a855f7); border: none; border-radius: 22px; color: white; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 10px; box-shadow: 0 8px 32px rgba(99, 102, 241, 0.4); }
                
                .tracking-container { max-width: 900px; margin: 0 auto 100px; padding: 0 24px; position: relative; z-index: 1; }
                .status-card { background: rgba(255,255,255,0.02); backdrop-filter: blur(32px); border: 1px solid rgba(255,255,255,0.08); border-radius: 40px; padding: 48px; margin-bottom: 40px; }
                .tl-item { display: flex; gap: 24px; margin-bottom: 32px; }
                .tl-point { width: 50px; height: 50px; border-radius: 16px; background: rgba(99, 102, 241, 0.1); display: flex; align-items: center; justify-content: center; color: #818cf8; flex-shrink: 0; position: relative; }
                .tl-line { position: absolute; top: 58px; left: 24px; width: 2px; height: calc(100% - 10px); background: rgba(255,255,255,0.05); }
                .tl-content { flex: 1; padding-bottom: 40px; }
                .tl-content h4 { font-size: 1.25rem; font-weight: 800; margin-bottom: 4px; }
                .tl-content p { color: rgba(255,255,255,0.4); line-height: 1.6; }
                
                .site-footer { border-top: 1px solid rgba(255,255,255,0.04); padding: 80px 24px 40px; text-align: center; }
                .footer-links { display: flex; flex-wrap: wrap; justify-content: center; gap: 24px; margin-top: 24px; }
                .footer-links a { color: rgba(255,255,255,0.3); text-decoration: none; transition: 0.3s; }
                .footer-links a:hover { color: white; }
                
                @media (max-width: 768px) { .desktop-nav { display: none; } .status-card { padding: 24px; } .search-box-premium { border-radius: 20px; flex-direction: column; padding: 20px; } .btn-track { width: 100%; } }
            `}</style>

            <div className="bg-mesh"></div>

            <Header />

            <section className="search-hero">
                <div className="reveal">
                    <h1 style={{ fontSize: 'clamp(2.5rem, 6vw, 4rem)', fontWeight: 900, marginBottom: '24px', letterSpacing: '-2px' }}>Onde está seu <span style={{ color: '#818cf8' }}>pacote?</span></h1>
                    <p style={{ color: 'rgba(255,255,255,0.4)', fontSize: '1.1rem', marginBottom: '48px' }}>Monitore sua entrega em tempo real com precisão de metros.</p>
                </div>

                <form onSubmit={handleSearch} className="search-box-premium reveal">
                    <Search size={24} color="#6366f1" />
                    <input type="text" className="search-input-premium" placeholder="Cole seu código de rastreio aqui..." value={codigo} onChange={e => setCodigo(e.target.value)} />
                    <button type="submit" className="btn-track" disabled={loading}>
                        {loading ? 'Processando...' : 'Localizar'} <ArrowRight size={20} />
                    </button>
                </form>
            </section>

            <div className="tracking-container">
                {trackingData ? (
                    <div className="reveal">
                        <div className="status-card">
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '40px' }}>
                                <div>
                                    <div style={{ display: 'inline-flex', padding: '6px 16px', background: 'rgba(52, 211, 153, 0.1)', border: '1px solid rgba(52, 211, 153, 0.2)', borderRadius: '100px', fontSize: '0.8rem', fontWeight: 800, color: '#34d399', marginBottom: '12px' }}>{trackingData.status}</div>
                                    <h2 style={{ fontSize: '1.8rem', fontWeight: 900 }}>{trackingData.codigo}</h2>
                                </div>
                                <div style={{ textAlign: 'right' }}>
                                    <p style={{ color: 'rgba(255,255,255,0.3)', fontSize: '0.8rem' }}>Previsão de Entrega</p>
                                    <p style={{ fontSize: '1.2rem', fontWeight: 800, color: '#818cf8' }}>{trackingData.previsao}</p>
                                </div>
                            </div>

                            <div style={{ display: 'flex', gap: '8px', marginBottom: '48px' }}>
                                {[1, 2, 3, 4].map(step => (
                                    <div key={step} style={{ flex: 1, height: '4px', background: step <= trackingData.etapaAtual ? '#818cf8' : 'rgba(255,255,255,0.05)', borderRadius: '2px', boxShadow: step === trackingData.etapaAtual ? '0 0 10px #818cf8' : 'none' }}></div>
                                ))}
                            </div>

                            <div className="timeline-list">
                                {trackingData.eventos.map((ev: any, i: number) => (
                                    <div key={i} className="tl-item">
                                        <div className="tl-point">
                                            {ev.icon}
                                            {i < trackingData.eventos.length - 1 && <div className="tl-line"></div>}
                                        </div>
                                        <div className="tl-content">
                                            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                                                <h4>{ev.status}</h4>
                                                <span style={{ fontSize: '0.8rem', color: '#818cf8', fontWeight: 700 }}>{ev.data}</span>
                                            </div>
                                            <p>{ev.detalhes}</p>
                                            <p style={{ fontSize: '0.8rem', marginTop: '4px' }}>📍 {ev.local}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div style={{ display: 'flex', gap: '16px', justifyContent: 'center' }}>
                            <button className="nav-item" style={{ padding: '12px 24px', background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)', borderRadius: '16px', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '8px' }}><Share2 size={16} /> Compartilhar</button>
                            <button className="nav-item" style={{ padding: '12px 24px', background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)', borderRadius: '16px', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '8px' }}><Printer size={16} /> Imprimir</button>
                        </div>
                    </div>
                ) : (
                    <div style={{ textAlign: 'center', padding: '60px 0', opacity: 0.3 }} className="reveal">
                        <Package size={80} style={{ marginBottom: '24px' }} />
                        <p>Digite o seu código para ver a mágica acontecer.</p>
                    </div>
                )}
            </div>

            <Footer />
        </div>
    );
};

export default TrackingPage;
