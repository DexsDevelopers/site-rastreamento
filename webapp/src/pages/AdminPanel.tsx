import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import {
    Package, CheckCircle, DollarSign, Search, Plus,
    Edit, Trash2, Eye, MessageCircle, Download, X, Save,
    RefreshCw, Filter, BarChart2, Copy
} from 'lucide-react';

// ===== TIPOS =====
interface Rastreio {
    id: number;
    codigo: string;
    cidade: string;
    status_atual: string;
    titulo: string;
    subtitulo: string;
    data: string;
    cor: string;
    taxa_valor: string | null;
    taxa_pix: string | null;
    prioridade: number;
    data_entrega_prevista: string | null;
}

interface RastreioDetalhes {
    codigo: string;
    cidade: string;
    data_inicial: string;
    taxa_valor: string | null;
    taxa_pix: string | null;
    etapas: string[];
    cliente_nome: string | null;
    cliente_whatsapp: string | null;
    cliente_notificar: boolean;
    foto_url: string | null;
}

interface Stats {
    total: number;
    entregues: number;
    com_taxa: number;
    sem_taxa: number;
}

interface NovoRastreio {
    codigo: string;
    cidade: string;
    data_inicial: string;
    taxa_valor: string;
    taxa_pix: string;
    cliente_nome: string;
    cliente_whatsapp: string;
    cliente_notificar: boolean;
    etapas: { [k: string]: boolean };
}

// ===== HELPERS =====
const ETAPAS_MAP: { [k: string]: string } = {
    postado: '📦 Objeto postado',
    transito: '🚚 Em trânsito',
    distribuicao: '🏢 No centro de distribuição',
    entrega: '🚀 Saiu para entrega',
    entregue: '✅ Entregue',
};

const getStatusClass = (status: string) => {
    if (status.includes('Entregue')) return 'status-entregue';
    if (status.includes('Saiu')) return 'status-saiu';
    if (status.includes('trânsito')) return 'status-transito';
    if (status.includes('distribuição')) return 'status-distribuicao';
    return 'status-default';
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
};

// ===== NOTIFICAÇÃO TOAST =====
const Toast: React.FC<{ msg: string; type: 'success' | 'error' | 'info'; onClose: () => void }> = ({ msg, type, onClose }) => {
    useEffect(() => {
        const t = setTimeout(onClose, 4000);
        return () => clearTimeout(t);
    }, [onClose]);

    const colors = {
        success: { bg: 'rgba(34,197,94,0.15)', border: 'rgba(34,197,94,0.4)', text: '#22c55e' },
        error: { bg: 'rgba(239,68,68,0.15)', border: 'rgba(239,68,68,0.4)', text: '#ef4444' },
        info: { bg: 'rgba(59,130,246,0.15)', border: 'rgba(59,130,246,0.4)', text: '#3b82f6' },
    };
    const c = colors[type];
    return (
        <div style={{
            position: 'fixed', bottom: '24px', right: '24px', zIndex: 9999,
            background: c.bg, border: `1px solid ${c.border}`, borderRadius: '12px',
            padding: '16px 20px', color: c.text, fontWeight: 600, fontSize: '0.95rem',
            backdropFilter: 'blur(12px)', maxWidth: '380px',
            display: 'flex', alignItems: 'center', gap: '12px',
            animation: 'slideIn 0.3s ease', boxShadow: '0 8px 32px rgba(0,0,0,0.3)'
        }}>
            <span style={{ flex: 1 }}>{msg}</span>
            <button onClick={onClose} style={{ background: 'none', border: 'none', color: c.text, cursor: 'pointer', fontSize: '1.2rem', lineHeight: 1 }}>×</button>
        </div>
    );
};

// ===== MODAL BASE (Premium) =====
const Modal: React.FC<{ open: boolean; onClose: () => void; title: string; icon: React.ReactNode; children: React.ReactNode; maxWidth?: string }> = ({ open, onClose, title, icon, children, maxWidth = '640px' }) => {
    if (!open) return null;
    return (
        <div style={{
            position: 'fixed', inset: 0, zIndex: 1000, background: 'rgba(0,0,0,0.85)',
            backdropFilter: 'blur(12px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '16px',
            animation: 'fadeIn 0.3s ease'
        }} onClick={e => e.target === e.currentTarget && onClose()}>
            <div style={{
                background: 'rgba(20, 20, 25, 0.85)', border: '1px solid rgba(255,255,255,0.08)', borderRadius: '28px',
                width: '100%', maxWidth, maxHeight: '92vh', overflow: 'hidden',
                boxShadow: '0 24px 80px rgba(0,0,0,0.6), inset 0 1px 1px rgba(255,255,255,0.05)',
                display: 'flex', flexDirection: 'column', animation: 'modalSlide 0.4s cubic-bezier(0.16, 1, 0.3, 1)'
            }}>
                <div style={{
                    padding: '24px 32px', borderBottom: '1px solid rgba(255,255,255,0.06)',
                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                    background: 'rgba(255,255,255,0.02)'
                }}>
                    <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: '14px', fontSize: '1.25rem', fontWeight: 900, background: 'linear-gradient(135deg, #fff, #a5b4fc)', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent' }}>
                        <div style={{ width: '36px', height: '36px', borderRadius: '10px', background: 'rgba(99, 102, 241, 0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{icon}</div>
                        {title}
                    </h3>
                    <button onClick={onClose} style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.08)', color: '#888', cursor: 'pointer', padding: '10px', borderRadius: '12px', display: 'flex', alignItems: 'center', transition: '0.2s' }} onMouseEnter={e => e.currentTarget.style.color = '#fff'} onMouseLeave={e => e.currentTarget.style.color = '#888'}>
                        <X size={20} />
                    </button>
                </div>
                <div style={{ padding: '32px', overflowY: 'auto', flex: 1 }}>
                    {children}
                </div>
            </div>
        </div>
    );
};

// ===== COMPONENTE PRINCIPAL =====
const AdminPanel: React.FC = () => {
    const [rastreios, setRastreios] = useState<Rastreio[]>([]);
    const [stats, setStats] = useState<Stats>({ total: 0, entregues: 0, com_taxa: 0, sem_taxa: 0 });
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [filterType, setFilterType] = useState<'all' | 'com_taxa' | 'sem_taxa' | 'entregues'>('all');
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [toast, setToast] = useState<{ msg: string; type: 'success' | 'error' | 'info' } | null>(null);

    // Modais
    const [modalAdd, setModalAdd] = useState(false);
    const [modalEdit, setModalEdit] = useState(false);
    const [modalDetails, setModalDetails] = useState(false);
    const [modalBulkEdit, setModalBulkEdit] = useState(false);

    // Formulário Novo
    const defaultNovo: NovoRastreio = {
        codigo: '', cidade: '', data_inicial: new Date().toISOString().slice(0, 16),
        taxa_valor: '', taxa_pix: '', cliente_nome: '', cliente_whatsapp: '', cliente_notificar: true,
        etapas: { postado: true, transito: false, distribuicao: false, entrega: false, entregue: false }
    };
    const [novoForm, setNovoForm] = useState<NovoRastreio>(defaultNovo);

    // Dados de edição
    const [editData, setEditData] = useState<RastreioDetalhes | null>(null);
    const [detailsData, setDetailsData] = useState<RastreioDetalhes | null>(null);

    // Bulk edit
    const [bulkCidade, setBulkCidade] = useState('');
    const [bulkTaxaValor, setBulkTaxaValor] = useState('');
    const [bulkTaxaPix, setBulkTaxaPix] = useState('');

    const showToast = useCallback((msg: string, type: 'success' | 'error' | 'info' = 'info') => {
        setToast({ msg, type });
    }, []);

    // ===== CARREGAR DADOS =====
    const fetchData = useCallback(async () => {
        try {
            const [ordersRes, statsRes] = await Promise.all([
                axios.get('/api/admin/rastreios'),
                axios.get('/api/admin/stats'),
            ]);
            setRastreios(Array.isArray(ordersRes.data) ? ordersRes.data : []);
            setStats(statsRes.data || { total: 0, entregues: 0, com_taxa: 0, sem_taxa: 0 });
        } catch (err) {
            showToast('Erro ao carregar dados do servidor', 'error');
        } finally {
            setLoading(false);
        }
    }, [showToast]);

    useEffect(() => {
        fetchData();
        const interval = setInterval(fetchData, 30000);
        return () => clearInterval(interval);
    }, [fetchData]);

    // ===== FILTROS =====
    const filteredRastreios = rastreios.filter(r => {
        const matchSearch = !searchTerm ||
            (r.codigo?.toLowerCase().includes(searchTerm.toLowerCase())) ||
            (r.cidade?.toLowerCase().includes(searchTerm.toLowerCase())) ||
            (r.status_atual?.toLowerCase().includes(searchTerm.toLowerCase()));
        const matchFilter =
            filterType === 'all' ||
            (filterType === 'com_taxa' && r.taxa_valor && r.taxa_pix) ||
            (filterType === 'sem_taxa' && (!r.taxa_valor || !r.taxa_pix)) ||
            (filterType === 'entregues' && r.status_atual.includes('Entregue'));
        return matchSearch && matchFilter;
    });

    // ===== SELEÇÃO =====
    const toggleSelect = (codigo: string) => {
        setSelected(prev => {
            const n = new Set(prev);
            n.has(codigo) ? n.delete(codigo) : n.add(codigo);
            return n;
        });
    };

    const toggleSelectAll = () => {
        if (selected.size === filteredRastreios.length) {
            setSelected(new Set());
        } else {
            setSelected(new Set(filteredRastreios.map(r => r.codigo)));
        }
    };

    // ===== AÇÕES =====
    const handleAdd = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await axios.post('/api/admin/rastreios', novoForm);
            showToast(`✅ Rastreio ${novoForm.codigo} adicionado com sucesso!`, 'success');
            setModalAdd(false);
            setNovoForm(defaultNovo);
            fetchData();
        } catch (err: any) {
            showToast(err.response?.data?.error || 'Erro ao adicionar rastreio', 'error');
        }
    };

    const abrirEdicao = async (codigo: string) => {
        try {
            const res = await axios.get(`/api/admin/rastreios/${codigo}/detalhes`);
            setEditData(res.data);
            setModalEdit(true);
        } catch {
            showToast('Erro ao carregar dados do rastreio', 'error');
        }
    };

    const handleEdit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!editData) return;
        try {
            await axios.put(`/api/admin/rastreios/${editData.codigo}`, editData);
            showToast(`✅ Rastreio ${editData.codigo} atualizado!`, 'success');
            setModalEdit(false);
            fetchData();
        } catch (err: any) {
            showToast(err.response?.data?.error || 'Erro ao atualizar rastreio', 'error');
        }
    };

    const abrirDetalhes = async (codigo: string) => {
        try {
            const res = await axios.get(`/api/admin/rastreios/${codigo}/detalhes`);
            setDetailsData(res.data);
            setModalDetails(true);
        } catch {
            showToast('Erro ao carregar detalhes', 'error');
        }
    };

    const handleDelete = async (codigo: string) => {
        if (!window.confirm(`Tem certeza que deseja excluir o rastreio ${codigo}?`)) return;
        try {
            await axios.delete(`/api/admin/rastreios/${codigo}`);
            showToast(`🗑️ Rastreio ${codigo} excluído!`, 'success');
            fetchData();
        } catch {
            showToast('Erro ao excluir rastreio', 'error');
        }
    };

    const handleBulkDelete = async () => {
        if (selected.size === 0) return;
        if (!window.confirm(`Excluir ${selected.size} rastreio(s) selecionados?`)) return;
        try {
            await axios.post('/api/admin/rastreios/bulk-delete', { codigos: [...selected] });
            showToast(`🗑️ ${selected.size} rastreio(s) excluídos!`, 'success');
            setSelected(new Set());
            fetchData();
        } catch {
            showToast('Erro na exclusão em lote', 'error');
        }
    };

    const handleBulkEdit = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await axios.post('/api/admin/rastreios/bulk-edit', {
                codigos: [...selected],
                cidade: bulkCidade || undefined,
                taxa_valor: bulkTaxaValor || undefined,
                taxa_pix: bulkTaxaPix || undefined,
            });
            showToast(`✅ ${selected.size} rastreio(s) atualizados!`, 'success');
            setModalBulkEdit(false);
            setBulkCidade(''); setBulkTaxaValor(''); setBulkTaxaPix('');
            setSelected(new Set());
            fetchData();
        } catch {
            showToast('Erro na edição em lote', 'error');
        }
    };

    const enviarWhatsapp = async (codigo: string) => {
        try {
            showToast('Enviando mensagem WhatsApp...', 'info');
            const res = await axios.post(`/api/admin/rastreios/${codigo}/whatsapp`);
            showToast(res.data.message, res.data.success ? 'success' : 'error');
        } catch (err: any) {
            showToast(err.response?.data?.message || 'Erro ao enviar WhatsApp', 'error');
        }
    };

    const exportCSV = () => {
        const rows = filteredRastreios.map(r =>
            `"${r.codigo}","${r.cidade}","${r.status_atual}","${r.taxa_valor ? 'Pendente' : 'Sem taxa'}","${formatDate(r.data)}"`
        );
        const csv = 'Código,Cidade,Status,Taxa,Data\n' + rows.join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `rastreios_${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        showToast(`📥 ${filteredRastreios.length} rastreios exportados!`, 'success');
    };

    const copyTrackingLink = (codigo: string) => {
        const resetUrl = window.location.origin + window.location.pathname;
        const url = `${resetUrl}#/rastreio?codigo=${codigo}`;
        navigator.clipboard.writeText(url).then(() => {
            showToast('Link de rastreio copiado!', 'success');
        }).catch(() => {
            showToast('Erro ao copiar link', 'error');
        });
    };

    // ===== RENDER =====
    return (
        <div style={{ padding: '32px', animation: 'fadeIn 0.4s ease', maxWidth: '1600px' }}>
            <style>{`
                @keyframes slideIn { from { transform: translateX(40px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
                @keyframes modalSlide { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
                @keyframes fadeIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
                @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
                
                .text-gradient { background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                
                .admin-row { transition: all 0.2s; }
                .admin-row:hover { background: rgba(255,255,255,0.03) !important; }
                
                .admin-action-btn { 
                    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); 
                    border-radius: 10px; padding: 8px 12px; cursor: pointer; color: #888; 
                    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); 
                }
                .admin-action-btn:hover { background: rgba(255,255,255,0.08); color: #fff; transform: translateY(-1px); border-color: rgba(255,255,255,0.2); }
                
                .filter-btn-tab { 
                    padding: 8px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.06); 
                    background: rgba(255,255,255,0.02); color: #71717a; cursor: pointer; 
                    font-weight: 600; font-size: 0.85rem; transition: all 0.3s; 
                }
                .filter-btn-tab:hover { color: #fff; background: rgba(255,255,255,0.05); }
                .filter-btn-tab.active { background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; border-color: transparent; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
                
                .form-input { 
                    width: 100%; padding: 12px 18px; background: rgba(255,255,255,0.03); 
                    border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; 
                    color: #fff; font-size: 0.95rem; font-family: 'Inter', sans-serif; 
                    outline: none; transition: all 0.3s; box-sizing: border-box; 
                }
                .form-input:focus { border-color: #6366f1; background: rgba(99, 102, 241, 0.05); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
                .form-label { display: block; margin-bottom: 8px; color: #94a3b8; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; }
                .form-group-mb { margin-bottom: 20px; }
                
                .checkbox-row { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 12px; cursor: pointer; transition: background 0.2s; border: 1px solid transparent; }
                .checkbox-row:hover { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.05); }
                
                .status-entregue { color: #10b981; }
                .status-saiu { color: #f59e0b; }
                .status-transito { color: #6366f1; }
                .status-distribuicao { color: #a855f7; }
                .status-default { color: #71717a; }
                
                ::-webkit-scrollbar { width: 6px; height: 6px; }
                ::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
                ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 10px; }
                ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.15); }
            `}</style>

            {/* TOAST */}
            {toast && <Toast msg={toast.msg} type={toast.type} onClose={() => setToast(null)} />}

            {/* HEADER */}
            <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '40px', gap: '24px', flexWrap: 'wrap' }}>
                <div>
                    <h1 style={{ fontSize: '2.5rem', fontWeight: 900, margin: 0, letterSpacing: '-1.5px', fontFamily: 'Outfit, sans-serif' }}>
                        Painel <span className="text-gradient">Administrativo</span>
                    </h1>
                    <p style={{ color: '#64748b', marginTop: '6px', fontSize: '1.1rem', fontWeight: 500 }}>Gestão inteligente da maior malha logística do Brasil.</p>
                </div>
                <div style={{ display: 'flex', gap: '14px', flexWrap: 'wrap' }}>
                    <button onClick={() => fetchData()} className="admin-action-btn" title="Atualizar" style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '12px 20px' }}>
                        <RefreshCw size={18} /> Sincronizar
                    </button>
                    <button onClick={exportCSV} className="admin-action-btn" style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '12px 20px' }}>
                        <Download size={18} /> CSV
                    </button>
                    <button onClick={() => { setNovoForm(defaultNovo); setModalAdd(true); }} style={{
                        display: 'flex', alignItems: 'center', gap: '12px', padding: '14px 28px',
                        background: 'linear-gradient(135deg, #6366f1, #a855f7)', color: '#fff',
                        border: 'none', borderRadius: '16px', cursor: 'pointer', fontWeight: 800, fontSize: '1rem',
                        boxShadow: '0 8px 24px rgba(99, 102, 241, 0.4)', transition: '0.3s'
                    }} onMouseEnter={e => e.currentTarget.style.transform = 'translateY(-2px)'} onMouseLeave={e => e.currentTarget.style.transform = 'translateY(0)'}>
                        <Plus size={20} /> Novo Rastreio
                    </button>
                </div>
            </header>

            {/* STATS */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '16px', marginBottom: '28px' }}>
                <StatCard icon={<Package size={22} />} label="Total de Rastreios" value={loading ? '...' : stats.total} color="#0055ff" />
                <StatCard icon={<DollarSign size={22} />} label="Com Taxa Pendente" value={loading ? '...' : stats.com_taxa} color="#f59e0b" />
                <StatCard icon={<BarChart2 size={22} />} label="Sem Taxa" value={loading ? '...' : stats.sem_taxa} color="#a78bfa" />
                <StatCard icon={<CheckCircle size={22} />} label="Entregues" value={loading ? '...' : stats.entregues} color="#22c55e" />
            </div>

            {/* TABELA PRINCIPAL */}
            <div className="table-wrapper" style={{
                background: 'rgba(255,255,255,0.01)', border: '1px solid rgba(255,255,255,0.06)',
                borderRadius: '24px', overflowX: 'auto', backdropFilter: 'blur(20px)',
                boxShadow: '0 8px 32px rgba(0,0,0,0.2)'
            }}>
                {/* TOOLBAR */}
                <div style={{ padding: '16px 20px', borderBottom: '1px solid rgba(255,255,255,0.06)', display: 'flex', gap: '12px', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div style={{ display: 'flex', gap: '10px', alignItems: 'center', flex: 1, minWidth: '280px' }}>
                        <div style={{ position: 'relative', flex: 1, maxWidth: '320px' }}>
                            <Search size={16} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: '#555' }} />
                            <input
                                type="text"
                                placeholder="Buscar rastreio..."
                                value={searchTerm}
                                onChange={e => setSearchTerm(e.target.value)}
                                className="form-input"
                                style={{ paddingLeft: '38px' }}
                            />
                        </div>
                        <div style={{ display: 'flex', gap: '6px' }}>
                            {(['all', 'com_taxa', 'sem_taxa', 'entregues'] as const).map(f => (
                                <button key={f} className={`filter-btn-tab ${filterType === f ? 'active' : ''}`} onClick={() => setFilterType(f)}>
                                    {f === 'all' ? 'Todos' : f === 'com_taxa' ? 'Com Taxa' : f === 'sem_taxa' ? 'Sem Taxa' : 'Entregues'}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* BULK ACTIONS */}
                    {selected.size > 0 && (
                        <div style={{ display: 'flex', gap: '8px', alignItems: 'center', padding: '6px 14px', background: 'rgba(59,130,246,0.1)', borderRadius: '10px', border: '1px solid rgba(59,130,246,0.2)' }}>
                            <span style={{ color: '#3b82f6', fontSize: '0.9rem', fontWeight: 600 }}>{selected.size} selecionados</span>
                            <button onClick={() => setModalBulkEdit(true)} className="admin-action-btn" style={{ padding: '5px 10px', color: '#f59e0b', display: 'flex', alignItems: 'center', gap: '5px' }}>
                                <Edit size={14} /> Editar
                            </button>
                            <button onClick={handleBulkDelete} className="admin-action-btn" style={{ padding: '5px 10px', color: '#ef4444', display: 'flex', alignItems: 'center', gap: '5px' }}>
                                <Trash2 size={14} /> Excluir
                            </button>
                        </div>
                    )}
                </div>

                {/* TABLE */}
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ background: 'rgba(255,255,255,0.03)', borderBottom: '1px solid rgba(255,255,255,0.06)' }}>
                                <th style={{ ...thStyle, padding: '20px' }}>
                                    <input type="checkbox"
                                        checked={selected.size === filteredRastreios.length && filteredRastreios.length > 0}
                                        onChange={toggleSelectAll}
                                        style={{ accentColor: '#6366f1', width: '18px', height: '18px', cursor: 'pointer' }}
                                    />
                                </th>
                                <th style={{ ...thStyle, fontSize: '0.8rem', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.05em', color: '#94a3b8' }}>Código</th>
                                <th style={{ ...thStyle, fontSize: '0.8rem', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.05em', color: '#94a3b8' }}>Cidade</th>
                                <th style={{ ...thStyle, fontSize: '0.8rem', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.05em', color: '#94a3b8' }}>Status</th>
                                <th style={{ ...thStyle, fontSize: '0.8rem', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.05em', color: '#94a3b8' }}>Taxa</th>
                                <th style={{ ...thStyle, fontSize: '0.8rem', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.05em', color: '#94a3b8' }}>Última Atu.</th>
                                <th style={{ ...thStyle, textAlign: 'right', paddingRight: '24px', fontSize: '0.8rem', fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.05em', color: '#94a3b8' }}>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr>
                                    <td colSpan={7} style={{ padding: '48px', textAlign: 'center', color: '#555' }}>
                                        <div style={{ width: '28px', height: '28px', border: '3px solid rgba(0,85,255,0.2)', borderTop: '3px solid #0055ff', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 12px' }} />
                                        <div>Carregando rastreios...</div>
                                    </td>
                                </tr>
                            ) : filteredRastreios.length === 0 ? (
                                <tr>
                                    <td colSpan={7} style={{ padding: '48px', textAlign: 'center', color: '#555' }}>
                                        <Package size={40} style={{ marginBottom: '12px', opacity: 0.3 }} />
                                        <div>Nenhum rastreio encontrado</div>
                                    </td>
                                </tr>
                            ) : filteredRastreios.map(r => (
                                <tr key={r.id + r.codigo} className="admin-row" style={{ borderBottom: '1px solid rgba(255,255,255,0.04)', transition: 'background 0.2s' }}>
                                    <td style={{ ...tdStyle, padding: '20px' }}>
                                        <input type="checkbox" checked={selected.has(r.codigo)} onChange={() => toggleSelect(r.codigo)}
                                            style={{ accentColor: '#6366f1', width: '18px', height: '18px', cursor: 'pointer' }} />
                                    </td>
                                    <td style={{ ...tdStyle, fontFamily: 'JetBrains Mono, monospace', fontWeight: 800, color: '#fff', letterSpacing: '0.5px', fontSize: '1rem' }}>
                                        {r.codigo}
                                    </td>
                                    <td style={{ ...tdStyle, color: '#bbb' }}>{r.cidade}</td>
                                    <td style={tdStyle}>
                                        <span className={getStatusClass(r.status_atual)} style={{ fontSize: '0.9rem', fontWeight: 500 }}>
                                            ● {r.status_atual}
                                        </span>
                                    </td>
                                    <td style={tdStyle}>
                                        {r.taxa_valor && r.taxa_pix
                                            ? <span style={{ background: 'rgba(245,158,11,0.1)', color: '#f59e0b', border: '1px solid rgba(245,158,11,0.2)', padding: '6px 14px', borderRadius: '100px', fontSize: '0.8rem', fontWeight: 800, textTransform: 'uppercase' }}>Taxa Pendente</span>
                                            : <span style={{ background: 'rgba(16,185,129,0.1)', color: '#10b981', border: '1px solid rgba(16,185,129,0.2)', padding: '6px 14px', borderRadius: '100px', fontSize: '0.8rem', fontWeight: 800, textTransform: 'uppercase' }}>Sem Taxas</span>
                                        }
                                    </td>
                                    <td style={{ ...tdStyle, color: '#666', fontSize: '0.85rem' }}>{formatDate(r.data)}</td>
                                    <td style={{ ...tdStyle, textAlign: 'right', paddingRight: '24px' }}>
                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                                            <button onClick={() => abrirDetalhes(r.codigo)} className="admin-action-btn" title="Visualizar Detalhes"><Eye size={16} /></button>
                                            <button onClick={() => copyTrackingLink(r.codigo)} className="admin-action-btn" title="Copiar Link de Rastreio" style={{ color: '#8b5cf6', background: 'rgba(139,92,246,0.05)' }}><Copy size={16} /></button>
                                            <button onClick={() => abrirEdicao(r.codigo)} className="admin-action-btn" title="Editar Informações" style={{ color: '#f59e0b', background: 'rgba(245,158,11,0.05)' }}><Edit size={16} /></button>
                                            <button onClick={() => enviarWhatsapp(r.codigo)} className="admin-action-btn" title="Enviar WhatsApp" style={{ color: '#10b981', background: 'rgba(16,185,129,0.05)' }}><MessageCircle size={16} /></button>
                                            <button onClick={() => handleDelete(r.codigo)} className="admin-action-btn" title="Excluir Permanentemente" style={{ color: '#ef4444', background: 'rgba(239,68,68,0.05)' }}><Trash2 size={16} /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* FOOTER */}
                <div style={{ padding: '12px 20px', borderTop: '1px solid rgba(255,255,255,0.06)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', color: '#555', fontSize: '0.85rem' }}>
                    <span>{filteredRastreios.length} rastreio(s) exibido(s)</span>
                    {selected.size > 0 && <span style={{ color: '#3b82f6' }}>{selected.size} selecionado(s)</span>}
                </div>
            </div>

            {/* ===== MODAL ADICIONAR ===== */}
            <Modal open={modalAdd} onClose={() => setModalAdd(false)} title="Novo Rastreio" icon={<Plus size={18} color="#0055ff" />}>
                <form onSubmit={handleAdd}>
                    <div className="form-group-mb">
                        <label className="form-label">Código do Objeto *</label>
                        <input className="form-input" placeholder="AA123456789BR" required
                            value={novoForm.codigo}
                            onChange={e => setNovoForm(p => ({ ...p, codigo: e.target.value.toUpperCase() }))}
                            style={{ fontFamily: 'JetBrains Mono, monospace', letterSpacing: '1px' }} />
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                        <div className="form-group-mb">
                            <label className="form-label">Cidade de Origem/Destino *</label>
                            <input className="form-input" placeholder="São Paulo/SP" required
                                value={novoForm.cidade}
                                onChange={e => setNovoForm(p => ({ ...p, cidade: e.target.value }))} />
                        </div>
                        <div className="form-group-mb">
                            <label className="form-label">Data de Postagem *</label>
                            <input type="datetime-local" className="form-input" required
                                value={novoForm.data_inicial}
                                onChange={e => setNovoForm(p => ({ ...p, data_inicial: e.target.value }))} />
                        </div>
                    </div>

                    <div style={{ background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.07)', borderRadius: '10px', padding: '16px', marginBottom: '16px' }}>
                        <label className="form-label" style={{ marginBottom: '12px' }}>Cliente (Opcional)</label>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '10px' }}>
                            <input className="form-input" placeholder="Nome do cliente"
                                value={novoForm.cliente_nome}
                                onChange={e => setNovoForm(p => ({ ...p, cliente_nome: e.target.value }))} />
                            <input className="form-input" placeholder="WhatsApp (com DDD)" type="tel"
                                value={novoForm.cliente_whatsapp}
                                onChange={e => setNovoForm(p => ({ ...p, cliente_whatsapp: e.target.value }))} />
                        </div>
                        <label className="checkbox-row" style={{ fontSize: '0.9rem' }}>
                            <input type="checkbox" checked={novoForm.cliente_notificar}
                                onChange={e => setNovoForm(p => ({ ...p, cliente_notificar: e.target.checked }))}
                                style={{ accentColor: '#0055ff', width: '16px', height: '16px' }} />
                            Enviar notificação automática via WhatsApp
                        </label>
                    </div>

                    <div className="form-group-mb">
                        <label className="form-label">Fluxo Inicial de Rastreamento</label>
                        <div style={{ background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.07)', borderRadius: '10px', padding: '12px' }}>
                            {Object.entries(ETAPAS_MAP).map(([key, label]) => (
                                <label key={key} className="checkbox-row">
                                    <input type="checkbox" checked={!!novoForm.etapas[key]}
                                        onChange={e => setNovoForm(p => ({ ...p, etapas: { ...p.etapas, [key]: e.target.checked } }))}
                                        style={{ accentColor: '#0055ff', width: '16px', height: '16px' }} />
                                    <span>{label}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '20px' }}>
                        <div>
                            <label className="form-label">Valor da Taxa (opcional)</label>
                            <input className="form-input" type="number" placeholder="0.00" step="0.01" min="0"
                                value={novoForm.taxa_valor}
                                onChange={e => setNovoForm(p => ({ ...p, taxa_valor: e.target.value }))} />
                        </div>
                        <div>
                            <label className="form-label">Chave PIX (opcional)</label>
                            <input className="form-input" placeholder="Digite a chave PIX..."
                                value={novoForm.taxa_pix}
                                onChange={e => setNovoForm(p => ({ ...p, taxa_pix: e.target.value }))} />
                        </div>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                        <button type="button" onClick={() => setModalAdd(false)} style={{ padding: '10px 20px', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', color: '#aaa', cursor: 'pointer', fontWeight: 600 }}>
                            Cancelar
                        </button>
                        <button type="submit" style={{ padding: '10px 24px', background: 'linear-gradient(135deg,#0055ff,#180F33)', color: '#fff', border: 'none', borderRadius: '10px', cursor: 'pointer', fontWeight: 700, display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <Plus size={16} /> Criar Rastreio
                        </button>
                    </div>
                </form>
            </Modal>

            {/* ===== MODAL EDITAR ===== */}
            <Modal open={modalEdit} onClose={() => setModalEdit(false)} title="Editar Rastreio" icon={<Edit size={18} color="#f59e0b" />}>
                {editData && (
                    <form onSubmit={handleEdit}>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                            <div className="form-group-mb">
                                <label className="form-label">Código (não editável)</label>
                                <input className="form-input" value={editData.codigo} readOnly
                                    style={{ background: 'rgba(255,255,255,0.03)', color: '#555', cursor: 'not-allowed', fontFamily: 'JetBrains Mono, monospace' }} />
                            </div>
                            <div className="form-group-mb">
                                <label className="form-label">Cidade *</label>
                                <input className="form-input" required value={editData.cidade}
                                    onChange={e => setEditData(p => p ? ({ ...p, cidade: e.target.value }) : p)} />
                            </div>
                            <div className="form-group-mb" style={{ gridColumn: '1/-1' }}>
                                <label className="form-label">Nova Data Inicial *</label>
                                <input type="datetime-local" className="form-input" required value={editData.data_inicial}
                                    onChange={e => setEditData(p => p ? ({ ...p, data_inicial: e.target.value }) : p)} />
                            </div>
                        </div>

                        <div className="form-group-mb">
                            <label className="form-label">Etapas do Rastreamento</label>
                            <div style={{ background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.07)', borderRadius: '10px', padding: '12px' }}>
                                {Object.entries(ETAPAS_MAP).map(([key, label]) => (
                                    <label key={key} className="checkbox-row">
                                        <input type="checkbox"
                                            checked={editData.etapas.includes(key)}
                                            onChange={e => setEditData(p => {
                                                if (!p) return p;
                                                const etapas = e.target.checked
                                                    ? [...p.etapas, key]
                                                    : p.etapas.filter(et => et !== key);
                                                return { ...p, etapas };
                                            })}
                                            style={{ accentColor: '#0055ff', width: '16px', height: '16px' }} />
                                        {label}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                            <div>
                                <label className="form-label">Valor da Taxa</label>
                                <input className="form-input" type="number" placeholder="0.00" step="0.01"
                                    value={editData.taxa_valor || ''}
                                    onChange={e => setEditData(p => p ? ({ ...p, taxa_valor: e.target.value }) : p)} />
                            </div>
                            <div>
                                <label className="form-label">Chave PIX</label>
                                <input className="form-input" placeholder="Chave PIX..."
                                    value={editData.taxa_pix || ''}
                                    onChange={e => setEditData(p => p ? ({ ...p, taxa_pix: e.target.value }) : p)} />
                            </div>
                        </div>

                        <div style={{ background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.07)', borderRadius: '10px', padding: '16px', marginBottom: '16px' }}>
                            <label className="form-label" style={{ marginBottom: '12px' }}>Cliente</label>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '10px' }}>
                                <input className="form-input" placeholder="Nome do cliente"
                                    value={editData.cliente_nome || ''}
                                    onChange={e => setEditData(p => p ? ({ ...p, cliente_nome: e.target.value }) : p)} />
                                <input className="form-input" placeholder="WhatsApp (com DDD)"
                                    value={editData.cliente_whatsapp || ''}
                                    onChange={e => setEditData(p => p ? ({ ...p, cliente_whatsapp: e.target.value }) : p)} />
                            </div>
                            <label className="checkbox-row" style={{ fontSize: '0.9rem' }}>
                                <input type="checkbox" checked={editData.cliente_notificar}
                                    onChange={e => setEditData(p => p ? ({ ...p, cliente_notificar: e.target.checked }) : p)}
                                    style={{ accentColor: '#0055ff', width: '16px', height: '16px' }} />
                                Enviar atualizações no WhatsApp
                            </label>
                        </div>

                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                            <button type="button" onClick={() => setModalEdit(false)} style={{ padding: '10px 20px', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', color: '#aaa', cursor: 'pointer', fontWeight: 600 }}>
                                Cancelar
                            </button>
                            <button type="submit" style={{ padding: '10px 24px', background: 'linear-gradient(135deg,#0055ff,#180F33)', color: '#fff', border: 'none', borderRadius: '10px', cursor: 'pointer', fontWeight: 700, display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <Save size={16} /> Salvar Alterações
                            </button>
                        </div>
                    </form>
                )}
            </Modal>

            {/* ===== MODAL DETALHES (Estilo Dashboard Premium) ===== */}
            <Modal open={modalDetails} onClose={() => setModalDetails(false)} title="Detalhes do Rastreio" icon={<Eye size={20} color="#818cf8" />}>
                {detailsData && (
                    <div style={{ animation: 'fadeIn 0.4s ease' }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', marginBottom: '32px', background: 'rgba(255,255,255,0.02)', padding: '24px', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <InfoBlock label="Código de Rastreio" value={detailsData.codigo} mono />
                            <InfoBlock label="Cidade Atual" value={detailsData.cidade} />
                            {detailsData.cliente_nome && <InfoBlock label="Nome do Cliente" value={detailsData.cliente_nome} />}
                            {detailsData.cliente_whatsapp && <InfoBlock label="Número WhatsApp" value={detailsData.cliente_whatsapp} />}
                            <div style={{ gridColumn: '1/-1', height: '1px', background: 'rgba(255,255,255,0.06)', margin: '8px 0' }} />
                            {detailsData.taxa_valor ? (
                                <>
                                    <InfoBlock label="Valor da Taxa" value={`R$ ${parseFloat(detailsData.taxa_valor).toFixed(2)}`} color="#f59e0b" />
                                    <InfoBlock label="Chave PIX para Cobrança" value={detailsData.taxa_pix || '-'} />
                                </>
                            ) : (
                                <div style={{ gridColumn: '1/-1', padding: '12px', background: 'rgba(16, 185, 129, 0.08)', borderRadius: '12px', border: '1px solid rgba(16, 185, 129, 0.2)', color: '#10b981', fontSize: '0.9rem', fontWeight: 600, textAlign: 'center' }}>
                                    Objeto sem taxas pendentes
                                </div>
                            )}
                        </div>

                        <div style={{ marginBottom: '32px' }}>
                            <label className="form-label">Histórico de Movimentação</label>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginTop: '12px' }}>
                                {detailsData.etapas.length > 0 ? detailsData.etapas.map((etapa, i) => (
                                    <div key={i} style={{
                                        padding: '16px 20px', background: 'rgba(255,255,255,0.03)',
                                        borderRadius: '16px', border: '1px solid rgba(255,255,255,0.06)',
                                        fontSize: '0.95rem', display: 'flex', alignItems: 'center', gap: '14px'
                                    }}>
                                        <div style={{ width: '10px', height: '10px', borderRadius: '50%', background: i === detailsData.etapas.length - 1 ? '#6366f1' : 'rgba(255,255,255,0.2)', boxShadow: i === detailsData.etapas.length - 1 ? '0 0 12px #6366f1' : 'none' }} />
                                        {ETAPAS_MAP[etapa] || etapa}
                                    </div>
                                )) : (
                                    <div style={{ color: '#555', fontSize: '0.9rem', padding: '12px', textAlign: 'center' }}>Nenhuma etapa registrada</div>
                                )}
                            </div>
                        </div>

                        {detailsData.foto_url && (
                            <div style={{ marginBottom: '32px' }}>
                                <label className="form-label">Foto do Pacote</label>
                                <div style={{ position: 'relative', marginTop: '12px', borderRadius: '20px', overflow: 'hidden', border: '1px solid rgba(255,255,255,0.08)' }}>
                                    <img src={detailsData.foto_url} alt="Pacote" style={{ width: '100%', maxHeight: '300px', objectFit: 'cover' }} />
                                    <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(to top, rgba(0,0,0,0.6), transparent)' }} />
                                </div>
                            </div>
                        )}

                        <div style={{ display: 'flex', gap: '14px', flexWrap: 'wrap' }}>
                            <button onClick={() => { setModalDetails(false); abrirEdicao(detailsData.codigo); }}
                                style={{
                                    flex: 1, padding: '16px', background: 'rgba(255,255,255,0.04)',
                                    color: '#fff', border: '1px solid rgba(255,255,255,0.1)',
                                    borderRadius: '16px', cursor: 'pointer', fontWeight: 700,
                                    display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '10px',
                                    transition: '0.3s'
                                }} onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.08)'} onMouseLeave={e => e.currentTarget.style.background = 'rgba(255,255,255,0.04)'}>
                                <Edit size={18} /> Modificar Dados
                            </button>
                            <button onClick={() => { enviarWhatsapp(detailsData.codigo); setModalDetails(false); }}
                                style={{
                                    flex: 1.5, padding: '16px', background: 'linear-gradient(135deg, #10b981, #059669)',
                                    color: '#fff', border: 'none', borderRadius: '16px', cursor: 'pointer', fontWeight: 800,
                                    display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '10px',
                                    boxShadow: '0 8px 24px rgba(16, 185, 129, 0.3)', transition: '0.3s'
                                }} onMouseEnter={e => e.currentTarget.style.transform = 'translateY(-2px)'} onMouseLeave={e => e.currentTarget.style.transform = 'translateY(0)'}>
                                <MessageCircle size={18} /> Notificar por WhatsApp
                            </button>
                        </div>
                    </div>
                )}
            </Modal>

            {/* ===== MODAL EDIÇÃO EM LOTE ===== */}
            <Modal open={modalBulkEdit} onClose={() => setModalBulkEdit(false)} title={`Editar em Lote (${selected.size} rastreios)`} icon={<Filter size={18} color="#f59e0b" />} maxWidth="480px">
                <form onSubmit={handleBulkEdit}>
                    <p style={{ color: '#888', fontSize: '0.9rem', marginBottom: '16px' }}>Deixe os campos em branco para não alterar aquele dado.</p>
                    <div className="form-group-mb">
                        <label className="form-label">Nova Cidade</label>
                        <input className="form-input" placeholder="São Paulo/SP"
                            value={bulkCidade} onChange={e => setBulkCidade(e.target.value)} />
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '20px' }}>
                        <div>
                            <label className="form-label">Valor da Taxa</label>
                            <input className="form-input" type="number" placeholder="0.00" step="0.01"
                                value={bulkTaxaValor} onChange={e => setBulkTaxaValor(e.target.value)} />
                        </div>
                        <div>
                            <label className="form-label">Chave PIX</label>
                            <input className="form-input" placeholder="Chave PIX..."
                                value={bulkTaxaPix} onChange={e => setBulkTaxaPix(e.target.value)} />
                        </div>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                        <button type="button" onClick={() => setModalBulkEdit(false)} style={{ padding: '10px 20px', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', color: '#aaa', cursor: 'pointer', fontWeight: 600 }}>
                            Cancelar
                        </button>
                        <button type="submit" style={{ padding: '10px 24px', background: 'linear-gradient(135deg,#f59e0b,#d97706)', color: '#000', border: 'none', borderRadius: '10px', cursor: 'pointer', fontWeight: 700 }}>
                            Aplicar a {selected.size} rastreio(s)
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    );
};

// ===== COMPONENTES AUXILIARES (Premium) =====
const StatCard: React.FC<{ icon: React.ReactNode; label: string; value: number | string; color: string }> = ({ icon, label, value, color }) => (
    <div style={{
        background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)', borderRadius: '24px',
        padding: '28px 24px', position: 'relative', overflow: 'hidden', backdropFilter: 'blur(20px)',
        transition: 'all 0.4s cubic-bezier(0.16, 1, 0.3, 1)',
    }}
        onMouseEnter={e => {
            (e.currentTarget as HTMLDivElement).style.transform = 'translateY(-6px)';
            (e.currentTarget as HTMLDivElement).style.borderColor = `${color}44`;
            (e.currentTarget as HTMLDivElement).style.boxShadow = `0 20px 40px rgba(0,0,0,0.3), 0 0 0 1px ${color}11`;
        }}
        onMouseLeave={e => {
            (e.currentTarget as HTMLDivElement).style.transform = 'none';
            (e.currentTarget as HTMLDivElement).style.borderColor = 'rgba(255,255,255,0.06)';
            (e.currentTarget as HTMLDivElement).style.boxShadow = 'none';
        }}
    >
        <div style={{ position: 'absolute', top: 0, right: 0, width: '100px', height: '100px', background: `radial-gradient(circle at top right, ${color}15, transparent)`, borderRadius: '0 24px 0 0' }} />
        <div style={{ width: '52px', height: '52px', background: `${color}12`, border: `1px solid ${color}25`, borderRadius: '16px', display: 'flex', alignItems: 'center', justifyContent: 'center', color, marginBottom: '20px' }}>
            {icon}
        </div>
        <div style={{ fontSize: '2.4rem', fontWeight: 900, lineHeight: 1, marginBottom: '8px', color: '#fff', fontFamily: 'Outfit, sans-serif' }}>{value}</div>
        <div style={{ color: '#94a3b8', fontSize: '0.9rem', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.04em' }}>{label}</div>
    </div>
);

const InfoBlock: React.FC<{ label: string; value: string; mono?: boolean; color?: string }> = ({ label, value, mono, color }) => (
    <div>
        <div style={{ color: '#64748b', fontSize: '0.8rem', fontWeight: 800, marginBottom: '6px', textTransform: 'uppercase', letterSpacing: '0.05em' }}>{label}</div>
        <div style={{ color: color || '#f8fafc', fontFamily: mono ? 'JetBrains Mono, monospace' : 'Inter, sans-serif', wordBreak: 'break-all', fontSize: '1.05rem', fontWeight: 600 }}>{value}</div>
    </div>
);

const thStyle: React.CSSProperties = {
    padding: '12px 16px', fontSize: '0.75rem', textTransform: 'uppercase', letterSpacing: '0.06em',
    color: '#555', fontWeight: 700, textAlign: 'left', borderBottom: '1px solid rgba(255,255,255,0.06)',
    whiteSpace: 'nowrap',
};

const tdStyle: React.CSSProperties = {
    padding: '13px 16px', color: '#ddd', verticalAlign: 'middle',
};

export default AdminPanel;
