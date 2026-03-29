import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';

const API_BASE = import.meta.env.VITE_API_URL || '';
axios.defaults.baseURL = API_BASE;

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
        tipo_entrega: 'NORMAL',
        etapas: { postado: true }
    });

    const [editData, setEditData] = useState<any>(null);
    const [detailsData, setDetailsData] = useState<any>(null);

    const handleEditDetails = async (codigo: string) => {
        try {
            const res = await axios.get(`/api/admin/rastreios/${codigo}/detalhes`);
            const d = res.data;
            setEditData({
                codigo: d.codigo,
                cidade: d.cidade,
                data_inicial: d.data_inicial,
                taxa_valor: d.taxa_valor ? String(d.taxa_valor) : null,
                taxa_pix: d.taxa_pix,
                tipo_entrega: d.tipo_entrega || 'NORMAL',
                etapas: Array.isArray(d.etapas) ? d.etapas : [],
                cliente_nome: d.cliente_nome || '',
                cliente_whatsapp: d.cliente_whatsapp || ''
            });
            setModalEdit(true);
        } catch (err) {
            alert('Erro ao carregar detalhes para edição');
        }
    };

    const fetchData = useCallback(async (silent = false) => {
        if (!silent) setIsSyncing(true);
        try {
            const [ordersRes, statsRes] = await Promise.all([
                axios.get('/api/admin/rastreios'),
                axios.get('/api/admin/stats'),
            ]);

            if (Array.isArray(ordersRes.data)) {
                setRastreios(ordersRes.data);
            } else {
                console.error('Rastreios response is not an array:', ordersRes.data);
                setRastreios([]);
            }

            if (statsRes.data && typeof statsRes.data === 'object' && 'total' in statsRes.data) {
                setStats(statsRes.data);
            } else {
                console.error('Stats response is malformed:', statsRes.data);
            }
        } catch (err) {
            console.error('Fetch error:', err);
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
            setNovoForm({
                codigo: '', cidade: '', data_inicial: new Date().toISOString().slice(0, 16),
                taxa_valor: '', taxa_pix: '', cliente_nome: '', cliente_whatsapp: '', cliente_notificar: true,
                tipo_entrega: 'NORMAL',
                etapas: { postado: true }
            });
        } catch (err) {
            alert('Erro ao salvar');
        }
    };

    const handleEdit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!editData) return;
        try {
            await axios.put(`/api/admin/rastreios/${editData.codigo}`, editData);
            setModalEdit(false);
            fetchData();
        } catch (err) {
            alert('Erro ao atualizar');
        }
    };

    const enviarWhatsappBot = async (codigo: string) => {
        try {
            const res = await axios.post(`/api/admin/rastreios/${codigo}/notificar`);
            if (res.data.success) {
                alert(`✅ Mensagem enviada com sucesso!\n${res.data.message}`);
            } else {
                const phone = prompt(
                    `⚠️ ${res.data.message}\n\nDigite o número do cliente para enviar agora (com DDD, ex: 11999999999):`,
                    ''
                );
                if (phone) {
                    const res2 = await axios.post(`/api/admin/rastreios/${codigo}/notificar`, { phone });
                    if (res2.data.success) {
                        alert(`✅ Mensagem enviada!\n${res2.data.message}`);
                    } else {
                        alert(`❌ ${res2.data.message}`);
                    }
                }
            }
        } catch {
            alert('❌ Erro ao enviar notificação. Verifique se o bot está conectado.');
        }
    };

    const handleDelete = async (codigo: string) => {
        if (!window.confirm(`Excluir rastreio ${codigo}?`)) return;
        try {
            await axios.delete(`/api/admin/rastreios/${codigo}`);
            fetchData();
        } catch (err) {
            alert('Erro ao deletar');
        }
    };

    const handleView = async (codigo: string) => {
        try {
            const res = await axios.get(`/api/admin/rastreios/${codigo}/detalhes`);
            setDetailsData(res.data);
            setModalDetails(true);
        } catch (err) {
            alert('Erro ao carregar detalhes');
        }
    };

    return (
        <div className="admin-layout" style={{ background: '#0B0F1A', minHeight: '100vh', color: '#E2E8F0', fontFamily: "'Outfit', sans-serif" }}>
            <style>{`
                :root {
                    --bg-main: #0B0F1A;
                    --bg-card: #111827;
                    --border-glass: rgba(255, 255, 255, 0.05);
                    --accent-primary: #2563EB;
                    --accent-hover: #1D4ED8;
                    --accent-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
                    
                    --status-success: #22C55E;
                    --status-warning: #F59E0B;
                    --status-error: #EF4444;

                    --text-primary: #f8fafc;
                    --text-secondary: #94a3b8;
                    --glow-blue: 0 8px 30px rgba(37, 99, 235, 0.2);
                }

                .admin-layout {
                    margin: -20px;
                    padding: clamp(24px, 4vw, 40px) clamp(16px, 3vw, 32px);
                    min-height: calc(100vh - 64px);
                    background-image: 
                        radial-gradient(circle at 0% 0%, rgba(37, 99, 235, 0.03) 0%, transparent 40%),
                        radial-gradient(circle at 100% 100%, rgba(37, 99, 235, 0.03) 0%, transparent 40%);
                }

                .admin-container {
                    max-width: 1300px;
                    margin: 0 auto;
                }

                .text-gradient {
                    background: linear-gradient(to right, #fff, #94a3b8);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                }

                .btn-primary-saas {
                    background: var(--accent-gradient);
                    color: white;
                    border: none;
                    padding: 12px 28px;
                    border-radius: 14px;
                    font-weight: 700;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    cursor: pointer;
                    box-shadow: var(--glow-blue);
                    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                }

                .btn-primary-saas:hover {
                    transform: translateY(-2px);
                    background: var(--accent-hover);
                    box-shadow: 0 12px 40px rgba(37, 99, 235, 0.4);
                }

                .btn-primary-saas:active {
                    transform: scale(0.98);
                }

                .glass-card {
                    background: var(--bg-card);
                    backdrop-filter: blur(12px);
                    border: 1px solid var(--border-glass);
                    border-radius: 20px;
                    box-shadow: 0 20px 50px rgba(0,0,0,0.3);
                }

                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                .animate-slide-up {
                    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }

                .sync-overlay { 
                    position: fixed; top: 30px; right: 30px; 
                    background: rgba(37, 99, 235, 0.9); 
                    color: white; padding: 10px 20px; 
                    borderRadius: 30px; font-size: 0.85rem; 
                    font-weight: 700; z-index: 2000; 
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255,255,255,0.1);
                    animation: slideInRight 0.3s ease-out;
                }

                @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

                ::-webkit-scrollbar { width: 8px; }
                ::-webkit-scrollbar-track { background: var(--bg-main); }
                ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
                ::-webkit-scrollbar-thumb:hover { background: #334155; }
            `}</style>

            <div className="admin-container animate-slide-up">
                {isSyncing && <div className="sync-overlay">Sincronizando sistemas...</div>}

                <AdminHeader
                    stats={stats}
                    onRefresh={fetchData}
                    onExport={() => alert('Exportação concluída.')}
                    onAdd={() => setModalAdd(true)}
                />

                <TrackingFilters
                    search={searchTerm} setSearch={setSearchTerm}
                    activeTab={filterType} setActiveTab={setFilterType}
                    tabs={[
                        { id: 'all', label: 'Todos', count: stats.total },
                        { id: 'com_taxa', label: 'Pendentes', count: stats.com_taxa },
                        { id: 'entregues', label: 'Finalizados', count: stats.entregues }
                    ]}
                />

                <div className="tracking-list-wrapper" style={{ marginTop: '24px' }}>
                    <TrackingList
                        trackings={filtered}
                        onView={handleView}
                        onCopy={(c) => {
                            navigator.clipboard.writeText(`${window.location.origin}/rastreio/${c}`);
                            alert('🔗 Link de rastreio copiado!');
                        }}
                        onEdit={handleEditDetails}
                        onNotify={enviarWhatsappBot}
                        onDelete={handleDelete}
                    />
                </div>
            </div>

            <TrackingModals
                modalAdd={modalAdd} setModalAdd={setModalAdd}
                modalEdit={modalEdit} setModalEdit={setModalEdit}
                modalDetails={modalDetails} setModalDetails={setModalDetails}
                novoForm={novoForm} setNovoForm={setNovoForm} handleAdd={handleAdd}
                editData={editData} setEditData={setEditData} handleEdit={handleEdit}
                detailsData={detailsData}
                enviarWhatsapp={enviarWhatsappBot}
                abrirEdicao={(codigo) => {
                    setModalDetails(false);
                    handleEditDetails(codigo);
                }}
                ETAPAS_MAP={ETAPAS_MAP}
            />
        </div>
    );
};

export default AdminPanel;
