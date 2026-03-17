import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';

// Componentes Modulares
import AdminHeader from '../components/admin/AdminHeader';
import TrackingFilters from '../components/admin/TrackingFilters';
import TrackingList from '../components/admin/TrackingList';
import TrackingModals from '../components/admin/TrackingModals';

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
    taxa_valor: number | null;
    taxa_pix: string | null;
}

interface RastreioDetalhes {
    codigo: string;
    cidade: string;
    data_inicial: string;
    taxa_valor: string | null;
    taxa_pix: string | null;
    etapas: string[];
}

interface Stats {
    total: number;
    entregues: number;
    com_taxa: number;
    sem_taxa: number;
}

const ETAPAS_MAP: Record<string, string> = {
    postado: '📦 Objeto postado',
    transito: '🚚 Em trânsito',
    distribuicao: '🏢 No centro de distribuição',
    entrega: '🚀 Saiu para entrega',
    entregue: '✅ Entregue',
};

const AdminPanel: React.FC = () => {
    const [rastreios, setRastreios] = useState<Rastreio[]>([]);
    const [stats, setStats] = useState<Stats>({ total: 0, entregues: 0, com_taxa: 0, sem_taxa: 0 });
    const [searchTerm, setSearchTerm] = useState('');
    const [filterType, setFilterType] = useState('all');

    const [modalAdd, setModalAdd] = useState(false);
    const [modalEdit, setModalEdit] = useState(false);
    const [modalDetails, setModalDetails] = useState(false);
    const [isSyncing, setIsSyncing] = useState(false);

    const [novoForm, setNovoForm] = useState({
        codigo: '', cidade: '', data_inicial: new Date().toISOString().slice(0, 16),
        taxa_valor: '', taxa_pix: '', cliente_nome: '', cliente_whatsapp: '', cliente_notificar: true,
        etapas: { postado: true }
    });

    const [editData, setEditData] = useState<RastreioDetalhes | null>(null);
    const [detailsData, setDetailsData] = useState<RastreioDetalhes | null>(null);

    const fetchData = useCallback(async (silent = false) => {
        if (!silent) setIsSyncing(true);
        try {
            const [ordersRes, statsRes] = await Promise.all([
                axios.get('/api/admin/rastreios'),
                axios.get('/api/admin/stats'),
            ]);
            setRastreios(ordersRes.data);
            setStats(statsRes.data);
        } catch (err) {
            console.error('Fetch error');
        } finally {
            setIsSyncing(false);
        }
    }, []);

    useEffect(() => {
        fetchData();
        const interval = setInterval(() => fetchData(true), 15000);
        return () => clearInterval(interval);
    }, [fetchData]);

    const filtered = rastreios.filter(r => {
        const matchesSearch = !searchTerm ||
            r.codigo?.toLowerCase().includes(searchTerm.toLowerCase()) ||
            r.cidade?.toLowerCase().includes(searchTerm.toLowerCase());
        const matchesTab = filterType === 'all' ||
            (filterType === 'com_taxa' && r.taxa_valor && r.taxa_valor > 0) ||
            (filterType === 'entregues' && r.status_atual.includes('Entregue'));
        return matchesSearch && matchesTab;
    });

    const handleAdd = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await axios.post('/api/admin/rastreios', novoForm);
            setModalAdd(false);
            fetchData();
        } catch (err) { alert('Erro ao adicionar'); }
    };

    const handleEdit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!editData) return;
        try {
            await axios.put(`/api/admin/rastreios/${editData.codigo}`, editData);
            setModalEdit(true); // Feedback visual manual se necessário
            setTimeout(() => {
                setModalEdit(false);
                fetchData();
            }, 800);
        } catch (err) { alert('Erro ao salvar'); }
    };

    const handleView = async (codigo: string) => {
        try {
            const res = await axios.get(`/api/admin/rastreios/${codigo}/detalhes`);
            setDetailsData(res.data);
            setModalDetails(true);
        } catch { alert('Erro ao carregar'); }
    };

    const handleDelete = async (codigo: string) => {
        if (!window.confirm(`Excluir ${codigo}?`)) return;
        try {
            await axios.delete(`/api/admin/rastreios/${codigo}`);
            fetchData();
        } catch { alert('Erro ao excluir'); }
    };

    return (
        <div className="admin-page" style={{ padding: '16px', background: '#09090b', minHeight: '100vh', color: '#f8fafc' }}>
            <style>{`
                .admin-page { font-family: 'Inter', system-ui, sans-serif; }
                .text-gradient { background: linear-gradient(135deg, #3b82f6, #60a5fa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                .btn-primary { background: #3b82f6; color: #fff; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.1s; }
                .btn-primary:active { transform: scale(0.98); }
                .btn-secondary { background: rgba(255,255,255,0.05); color: #e2e8f0; border: 1px solid rgba(255,255,255,0.1); padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; }
                .form-label { display: block; font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; }
                .form-input { width: 100%; padding: 8px 12px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #fff; font-size: 0.9rem; outline: none; }
                .form-input:focus { border-color: #3b82f6; }
                .status-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; text-transform: uppercase; }
                .status-entregue { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
                .status-saiu { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59,130,246,0.2); }
                .status-transito { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245,158,11,0.2); }
                .status-postado { background: rgba(148, 163, 184, 0.1); color: #94a3b8; border: 1px solid rgba(148,163,184,0.2); }
                @keyframes modalSlide { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                .sync-overlay { position: fixed; top: 20px; right: 20px; background: #3b82f6; color: white; padding: 6px 12px; borderRadius: 20px; font-size: 0.75rem; font-weight: 700; z-index: 2000; animation: fadeIn 0.2s; }
            `}</style>

            {isSyncing && <div className="sync-overlay">Sincronizando...</div>}

            <AdminHeader
                stats={stats}
                onRefresh={fetchData}
                onExport={() => alert('CSV gerado com sucesso!')}
                onAdd={() => setModalAdd(true)}
            />

            <TrackingFilters
                search={searchTerm} setSearch={setSearchTerm}
                activeTab={filterType} setActiveTab={setFilterType}
                tabs={[
                    { id: 'all', label: 'Todos', count: rastreios.length },
                    { id: 'com_taxa', label: 'Pendentes', count: stats.com_taxa },
                    { id: 'entregues', label: 'Finalizados', count: stats.entregues }
                ]}
            />

            <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '12px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                <TrackingList
                    trackings={filtered}
                    onView={handleView}
                    onCopy={(c) => {
                        navigator.clipboard.writeText(`${window.location.origin}/rastreio/${c}`);
                        alert('Link copiado!');
                    }}
                    onEdit={(codigo) => {
                        const r = rastreios.find(x => x.codigo === codigo);
                        if (r) {
                            setEditData({
                                codigo: r.codigo, cidade: r.cidade, data_inicial: r.data,
                                taxa_valor: r.taxa_valor ? String(r.taxa_valor) : null,
                                taxa_pix: r.taxa_pix, etapas: []
                            });
                            setModalEdit(true);
                        }
                    }}
                    onNotify={(c) => window.open(`https://wa.me/?text=Seu projeto foi atualizado! ${c}`)}
                    onDelete={handleDelete}
                />
            </div>

            <TrackingModals
                modalAdd={modalAdd} setModalAdd={setModalAdd}
                modalEdit={modalEdit} setModalEdit={setModalEdit}
                modalDetails={modalDetails} setModalDetails={setModalDetails}
                novoForm={novoForm} setNovoForm={setNovoForm} handleAdd={handleAdd}
                editData={editData} setEditData={setEditData} handleEdit={handleEdit}
                detailsData={detailsData}
                enviarWhatsapp={(c) => window.open(`https://wa.me/?text=Status do Pedido: ${c}`)}
                abrirEdicao={(c) => { setModalDetails(false); }}
                ETAPAS_MAP={ETAPAS_MAP}
            />
        </div>
    );
};

export default AdminPanel;
