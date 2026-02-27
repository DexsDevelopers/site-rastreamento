import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import {
    Plus, Edit, Trash2, Search,
    RefreshCw, Check, X, Phone, Truck, User
} from 'lucide-react';

interface Entregador {
    id: number;
    nome: string;
    telefone: string;
    veiculo: string;
    status: 'disponivel' | 'em_rota' | 'indisponivel';
    data_cadastro: string;
}

const Entregadores = () => {
    const [entregadores, setEntregadores] = useState<Entregador[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [toast, setToast] = useState<{ msg: string; type: 'success' | 'error' } | null>(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [form, setForm] = useState({ nome: '', telefone: '', veiculo: '', status: 'disponivel' as 'disponivel' | 'em_rota' | 'indisponivel' });

    const showToast = useCallback((msg: string, type: 'success' | 'error') => {
        setToast({ msg, type });
        setTimeout(() => setToast(null), 3000);
    }, []);

    const fetchEntregadores = useCallback(async () => {
        setLoading(true);
        try {
            const res = await axios.get('/api/drivers');
            setEntregadores(Array.isArray(res.data) ? res.data : []);
        } catch (err) {
            showToast('Erro ao carregar entregadores', 'error');
        } finally {
            setLoading(false);
        }
    }, [showToast]);

    useEffect(() => {
        fetchEntregadores();
    }, [fetchEntregadores]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            if (editingId) {
                await axios.put(`/api/drivers/${editingId}`, form);
                showToast('Entregador atualizado!', 'success');
            } else {
                await axios.post('/api/drivers', form);
                showToast('Entregador cadastrado!', 'success');
            }
            setModalOpen(false);
            setEditingId(null);
            setForm({ nome: '', telefone: '', veiculo: '', status: 'disponivel' as 'disponivel' });
            fetchEntregadores();
        } catch (err) {
            showToast('Erro ao salvar entregador', 'error');
        }
    };

    const handleEdit = (ent: Entregador) => {
        setForm({ nome: ent.nome, telefone: ent.telefone, veiculo: ent.veiculo, status: ent.status });
        setEditingId(ent.id);
        setModalOpen(true);
    };

    const handleDelete = async (id: number) => {
        if (!window.confirm('Excluir este entregador?')) return;
        try {
            await axios.delete(`/api/drivers/${id}`);
            showToast('Entregador excluído', 'success');
            fetchEntregadores();
        } catch (err) {
            showToast('Erro ao excluir', 'error');
        }
    };

    const filtered = entregadores.filter(e =>
        e.nome.toLowerCase().includes(searchTerm.toLowerCase()) ||
        e.veiculo.toLowerCase().includes(searchTerm.toLowerCase())
    );

    return (
        <div style={{ padding: '24px', maxWidth: '1200px', margin: '0 auto', animation: 'fadeIn 0.5s ease' }}>
            {/* Toast */}
            {toast && (
                <div style={{
                    position: 'fixed', top: '24px', right: '24px', zIndex: 1100,
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
                    <h1 style={{ fontSize: '2rem', marginBottom: '4px' }}>Gestão de <span className="text-gradient">Entregadores</span></h1>
                    <p style={{ color: 'var(--text-secondary)' }}>Cadastre e gerencie sua frota de logística.</p>
                </div>
                <div style={{ display: 'flex', gap: '12px' }}>
                    <div style={{ position: 'relative' }}>
                        <Search size={18} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-secondary)' }} />
                        <input
                            type="text"
                            placeholder="Buscar entregador..."
                            className="input-field"
                            style={{ paddingLeft: '40px', width: '250px', marginBottom: 0 }}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                    <button onClick={() => { setForm({ nome: '', telefone: '', veiculo: '', status: 'disponivel' as 'disponivel' }); setEditingId(null); setModalOpen(true); }} className="btn-primary" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <Plus size={18} /> Novo Entregador
                    </button>
                </div>
            </header>

            {loading ? (
                <div style={{ padding: '100px', textAlign: 'center' }}>
                    <RefreshCw size={40} className="animate-spin" style={{ color: 'var(--accent-primary)' }} />
                </div>
            ) : filtered.length === 0 ? (
                <div className="glass-panel" style={{ padding: '80px', textAlign: 'center' }}>
                    <Truck size={64} style={{ color: 'var(--text-secondary)', marginBottom: '24px', opacity: 0.2 }} />
                    <h3>Nenhum entregador cadastrado</h3>
                </div>
            ) : (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '20px' }}>
                    {filtered.map(ent => (
                        <div key={ent.id} className="glass-panel" style={{ padding: '20px' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'start', marginBottom: '16px' }}>
                                <div style={{ display: 'flex', gap: '12px' }}>
                                    <div style={{ width: '40px', height: '40px', borderRadius: '10px', background: 'rgba(255,255,255,0.05)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                        <User size={20} style={{ color: 'var(--accent-primary)' }} />
                                    </div>
                                    <div>
                                        <h3 style={{ margin: 0, fontSize: '1.1rem' }}>{ent.nome}</h3>
                                        <span style={{
                                            fontSize: '0.75rem',
                                            padding: '2px 8px',
                                            borderRadius: '100px',
                                            background: ent.status === 'disponivel' ? 'rgba(34,197,94,0.1)' : 'rgba(245,158,11,0.1)',
                                            color: ent.status === 'disponivel' ? '#22c55e' : '#f59e0b',
                                            border: `1px solid ${ent.status === 'disponivel' ? 'rgba(34,197,94,0.2)' : 'rgba(245,158,11,0.2)'}`
                                        }}>
                                            {ent.status.replace('_', ' ').toUpperCase()}
                                        </span>
                                    </div>
                                </div>
                                <div style={{ display: 'flex', gap: '8px' }}>
                                    <button onClick={() => handleEdit(ent)} style={{ background: 'none', border: 'none', color: 'var(--text-secondary)', cursor: 'pointer' }}><Edit size={18} /></button>
                                    <button onClick={() => handleDelete(ent.id)} style={{ background: 'none', border: 'none', color: 'var(--danger)', cursor: 'pointer' }}><Trash2 size={18} /></button>
                                </div>
                            </div>
                            <div style={{ display: 'grid', gap: '10px', fontSize: '0.9rem', color: 'var(--text-secondary)' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}><Phone size={14} /> {ent.telefone}</div>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}><Truck size={14} /> {ent.veiculo}</div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Modal */}
            {modalOpen && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 1200, background: 'rgba(0,0,0,0.8)', backdropFilter: 'blur(5px)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <form onSubmit={handleSubmit} className="glass-panel" style={{ padding: '32px', width: '100%', maxWidth: '450px', border: '1px solid rgba(255,255,255,0.1)' }}>
                        <h2 style={{ marginBottom: '24px' }}>{editingId ? 'Editar' : 'Novo'} Entregador</h2>
                        <div className="form-group">
                            <label className="form-label">Nome Completo</label>
                            <input type="text" className="input-field" required value={form.nome} onChange={e => setForm({ ...form, nome: e.target.value })} />
                        </div>
                        <div className="form-group">
                            <label className="form-label">WhatsApp</label>
                            <input type="text" className="input-field" required value={form.telefone} onChange={e => setForm({ ...form, telefone: e.target.value })} />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Veículo</label>
                            <input type="text" className="input-field" required value={form.veiculo} onChange={e => setForm({ ...form, veiculo: e.target.value })} />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Status</label>
                            <select className="input-field" value={form.status} onChange={e => setForm({ ...form, status: e.target.value as any })} style={{ background: '#111', color: '#fff' }}>
                                <option value="disponivel">Disponível</option>
                                <option value="em_rota">Em Rota</option>
                                <option value="indisponivel">Indisponível</option>
                            </select>
                        </div>
                        <div style={{ display: 'flex', gap: '12px', marginTop: '24px' }}>
                            <button type="button" onClick={() => setModalOpen(false)} className="btn-primary" style={{ flex: 1, background: 'rgba(255,255,255,0.05)', color: '#fff' }}>Cancelar</button>
                            <button type="submit" className="btn-primary" style={{ flex: 1 }}>{editingId ? 'Salvar' : 'Cadastrar'}</button>
                        </div>
                    </form>
                </div>
            )}

            <style>{`
                @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
                .animate-spin { animation: spin 1s linear infinite; }
                @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
            `}</style>
        </div>
    );
};

export default Entregadores;
