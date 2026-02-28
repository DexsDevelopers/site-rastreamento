import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import {
    ShoppingBag, Search, Check, X, Clock,
    MessageCircle, MapPin, User, Hash, RefreshCw
} from 'lucide-react';

interface PedidoPendente {
    id: number;
    nome: string;
    email: string;
    telefone: string;
    cpf: string;
    cep: string;
    rua: string;
    numero: string;
    bairro: string;
    cidade: string;
    estado: string;
    data_pedido: string;
    status: string;
}

const PedidosPendentes = () => {
    const [pedidos, setPedidos] = useState<PedidoPendente[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [toast, setToast] = useState<{ msg: string; type: 'success' | 'error' } | null>(null);
    const [processingId, setProcessingId] = useState<number | null>(null);
    const [trackingCodes, setTrackingCodes] = useState<{ [key: number]: string }>({});

    const showToast = useCallback((msg: string, type: 'success' | 'error') => {
        setToast({ msg, type });
        setTimeout(() => setToast(null), 3000);
    }, []);

    const fetchPedidos = useCallback(async () => {
        setLoading(true);
        try {
            const res = await axios.get('/api/admin/pedidos-pendentes');
            setPedidos(Array.isArray(res.data) ? res.data : []);
        } catch (err) {
            showToast('Erro ao carregar pedidos', 'error');
        } finally {
            setLoading(false);
        }
    }, [showToast]);

    useEffect(() => {
        fetchPedidos();
    }, [fetchPedidos]);

    const handleAprovar = async (id: number) => {
        const codigo = trackingCodes[id];
        if (!codigo) {
            showToast('Informe um código de rastreio', 'error');
            return;
        }

        setProcessingId(id);
        try {
            await axios.post(`/api/admin/pedidos-pendentes/${id}/aprovar`, { codigo_rastreio: codigo });
            showToast('Pedido aprovado com sucesso!', 'success');
            fetchPedidos();
        } catch (err: any) {
            showToast(err.response?.data?.error || 'Erro ao aprovar', 'error');
        } finally {
            setProcessingId(null);
        }
    };

    const handleRejeitar = async (id: number) => {
        if (!window.confirm('Tem certeza que deseja rejeitar este pedido?')) return;

        setProcessingId(id);
        try {
            await axios.post(`/api/admin/pedidos-pendentes/${id}/rejeitar`);
            showToast('Pedido rejeitado', 'success');
            fetchPedidos();
        } catch (err) {
            showToast('Erro ao rejeitar', 'error');
        } finally {
            setProcessingId(null);
        }
    };

    const handleCobrar = async (id: number) => {
        setProcessingId(id);
        try {
            const res = await axios.post(`/api/admin/pedidos-pendentes/${id}/cobrar`);
            if (res.data.success) {
                showToast(res.data.message, 'success');
            } else {
                showToast(res.data.message, 'error');
            }
        } catch (err) {
            showToast('Erro ao enviar cobrança', 'error');
        } finally {
            setProcessingId(null);
        }
    };

    const filtered = pedidos.filter(p =>
        p.nome?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        p.telefone?.includes(searchTerm)
    );

    return (
        <div style={{ padding: '24px', maxWidth: '1200px', margin: '0 auto', animation: 'fadeIn 0.5s ease' }}>
            {/* Toast */}
            {toast && (
                <div style={{
                    position: 'fixed', top: '24px', right: '24px', zIndex: 1000,
                    padding: '12px 24px', borderRadius: '8px',
                    background: toast.type === 'success' ? 'var(--success)' : 'var(--danger)',
                    color: '#fff', boxShadow: '0 8px 32px rgba(0,0,0,0.3)',
                    display: 'flex', alignItems: 'center', gap: '8px',
                    animation: 'slideIn 0.3s ease-out'
                }}>
                    {toast.type === 'success' ? <Check size={18} /> : <X size={18} />}
                    {toast.msg}
                </div>
            )}

            <header style={{ marginBottom: '32px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
                <div>
                    <h1 style={{ fontSize: '2rem', marginBottom: '4px' }}>Pedidos <span className="text-gradient">Pendentes</span></h1>
                    <p style={{ color: 'var(--text-secondary)' }}>Gerencie e aprove solicitações de envio.</p>
                </div>
                <div style={{ display: 'flex', gap: '12px', flex: '1', maxWidth: '400px' }}>
                    <div style={{ position: 'relative', flex: '1' }}>
                        <Search size={18} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-secondary)' }} />
                        <input
                            type="text"
                            placeholder="Buscar por nome ou telefone..."
                            className="input-field"
                            style={{ paddingLeft: '40px', width: '100%', marginBottom: 0 }}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                    <button onClick={fetchPedidos} className="btn-primary" style={{ padding: '10px' }} disabled={loading}>
                        <RefreshCw size={20} className={loading ? 'animate-spin' : ''} />
                    </button>
                </div>
            </header>

            {loading ? (
                <div style={{ padding: '100px', textAlign: 'center' }}>
                    <RefreshCw size={40} className="animate-spin" style={{ color: 'var(--accent-primary)' }} />
                    <p style={{ marginTop: '16px', color: 'var(--text-secondary)' }}>Carregando pedidos...</p>
                </div>
            ) : filtered.length === 0 ? (
                <div className="glass-panel" style={{ padding: '80px', textAlign: 'center' }}>
                    <ShoppingBag size={64} style={{ color: 'var(--text-secondary)', marginBottom: '24px', opacity: 0.3 }} />
                    <h3>Nenhum pedido pendente</h3>
                    <p style={{ color: 'var(--text-secondary)' }}>Todos os pedidos foram processados.</p>
                </div>
            ) : (
                <div style={{ display: 'grid', gap: '20px' }}>
                    {filtered.map(pedido => (
                        <div key={pedido.id} className="glass-panel" style={{ padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px', flexWrap: 'wrap', gap: '12px' }}>
                                <div style={{ display: 'flex', gap: '16px', alignItems: 'center' }}>
                                    <div style={{ width: '48px', height: '48px', borderRadius: '12px', background: 'rgba(99, 102, 241, 0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--accent-primary)' }}>
                                        <User size={24} />
                                    </div>
                                    <div>
                                        <h3 style={{ margin: 0, fontSize: '1.2rem' }}>{pedido.nome}</h3>
                                        <div style={{ fontSize: '0.85rem', color: 'var(--text-secondary)', display: 'flex', alignItems: 'center', gap: '12px', marginTop: '4px' }}>
                                            <span style={{ display: 'flex', alignItems: 'center', gap: '4px' }}><Clock size={14} /> {new Date(pedido.data_pedido).toLocaleString('pt-BR')}</span>
                                            <span style={{ display: 'flex', alignItems: 'center', gap: '4px' }}><Hash size={14} /> ID: #{pedido.id}</span>
                                        </div>
                                    </div>
                                </div>
                                <div style={{ display: 'flex', gap: '8px' }}>
                                    <button
                                        onClick={() => handleCobrar(pedido.id)}
                                        className="btn-primary"
                                        style={{ background: '#25D366', color: '#fff', border: 'none', padding: '8px 16px' }}
                                        disabled={!!processingId}
                                    >
                                        <MessageCircle size={18} style={{ marginRight: '8px' }} /> Cobrar WhatsApp
                                    </button>
                                </div>
                            </div>

                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: '20px', marginBottom: '24px' }}>
                                <div style={{ background: 'rgba(255,255,255,0.02)', padding: '16px', borderRadius: '12px' }}>
                                    <h4 style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--text-secondary)', marginBottom: '12px', letterSpacing: '0.05em' }}>Dados do Cliente</h4>
                                    <div style={{ display: 'grid', gap: '8px', fontSize: '0.9rem' }}>
                                        <div><b>Zap:</b> {pedido.telefone}</div>
                                        <div><b>Email:</b> {pedido.email || 'Não informado'}</div>
                                        <div><b>CPF:</b> {pedido.cpf}</div>
                                    </div>
                                </div>
                                <div style={{ background: 'rgba(255,255,255,0.02)', padding: '16px', borderRadius: '12px' }}>
                                    <h4 style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--text-secondary)', marginBottom: '12px', letterSpacing: '0.05em' }}>Endereço de Entrega</h4>
                                    <div style={{ fontSize: '0.9rem', lineHeight: '1.6' }}>
                                        <div style={{ display: 'flex', alignItems: 'start', gap: '8px' }}>
                                            <MapPin size={16} style={{ marginTop: '4px', flexShrink: 0, color: 'var(--accent-primary)' }} />
                                            <div>
                                                {pedido.rua}, {pedido.numero}<br />
                                                {pedido.bairro} - {pedido.cidade}/{pedido.estado}<br />
                                                CEP: {pedido.cep}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="tracking-action-box" style={{
                                background: 'rgba(10, 10, 15, 0.95)',
                                padding: '32px 20px',
                                borderRadius: '28px',
                                border: '2px solid var(--accent-primary)',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: '24px',
                                alignItems: 'stretch',
                                boxShadow: '0 20px 60px rgba(0,0,0,0.6)'
                            }}>
                                <div style={{ flex: 1, minWidth: '0' }}>
                                    <label style={{ fontSize: '1rem', color: '#fff', marginBottom: '16px', display: 'block', fontWeight: 900, textTransform: 'uppercase', letterSpacing: '0.1em' }}>
                                        ⚠️ Digite o Código Abaixo:
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="!! CODIGO DE RASTREIO !!"
                                        className="mega-input-admin"
                                        value={trackingCodes[pedido.id] || ''}
                                        onChange={(e) => setTrackingCodes({ ...trackingCodes, [pedido.id]: e.target.value.toUpperCase() })}
                                    />
                                </div>
                                <div className="tracking-btns" style={{ display: 'flex', gap: '16px', flexDirection: 'column' }}>
                                    <button
                                        disabled={!!processingId}
                                        onClick={() => handleAprovar(pedido.id)}
                                        className="btn-primary"
                                        style={{ height: '70px', fontSize: '1.3rem', fontWeight: 900, borderRadius: '20px', background: 'linear-gradient(135deg, #6366f1, #a855f7)' }}
                                    >
                                        {processingId === pedido.id ? 'PROCESSANDO...' : 'APROVAR AGORA'}
                                    </button>
                                    <button
                                        disabled={!!processingId}
                                        onClick={() => handleRejeitar(pedido.id)}
                                        style={{ background: 'transparent', color: '#ef4444', border: '1px solid #ef4444', padding: '14px', borderRadius: '12px', fontWeight: 700, cursor: 'pointer' }}
                                    >
                                        RECUSAR PEDIDO
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <style>{`
                @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
                .animate-spin { animation: spin 1s linear infinite; }
                @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
                .text-gradient { background: linear-gradient(135deg, var(--accent-primary), #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                
                /* Garantir que nada reduza o tamanho no mobile */
                .mega-input-admin {
                    height: 90px !important;
                    font-size: 2rem !important;
                }
            `}</style>
        </div>
    );
};

export default PedidosPendentes;
