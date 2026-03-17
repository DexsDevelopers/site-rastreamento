import React from 'react';
import { RefreshCw, Download, Plus, Box, CheckCircle, AlertTriangle, Package } from 'lucide-react';

interface AdminHeaderProps {
    stats: {
        total: number;
        entregues: number;
        com_taxa: number;
        sem_taxa: number;
    };
    onRefresh: () => void;
    onExport: () => void;
    onAdd: () => void;
}

const AdminHeader: React.FC<AdminHeaderProps> = ({ stats, onRefresh, onExport, onAdd }) => {
    return (
        <header className="admin-header">
            <style>{`
                .admin-header {
                    margin-bottom: 32px;
                    animation: slideIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .header-top {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                    gap: 16px;
                    flex-wrap: wrap;
                    margin-bottom: 24px;
                }
                .title-group h1 {
                    font-size: 2.5rem;
                    font-weight: 900;
                    margin: 0;
                    letter-spacing: -1.5px;
                    font-family: 'Outfit', sans-serif;
                }
                .title-group p {
                    color: var(--text-secondary);
                    margin-top: 4px;
                    font-size: 0.95rem;
                    opacity: 0.8;
                }
                .action-group {
                    display: flex;
                    gap: 10px;
                }
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                }
                .stat-card {
                    background: var(--bg-glass);
                    border: 1px solid var(--border-glass);
                    border-radius: 16px;
                    padding: 20px;
                    display: flex;
                    align-items: center;
                    gap: 16px;
                    backdrop-filter: blur(10px);
                    transition: all 0.3s ease;
                }
                .stat-card:hover {
                    transform: translateY(-4px);
                    border-color: var(--accent-primary);
                    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                }
                .stat-icon {
                    width: 48px;
                    height: 48px;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .stat-info h3 {
                    margin: 0;
                    font-size: 0.85rem;
                    color: var(--text-secondary);
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }
                .stat-info p {
                    margin: 0;
                    font-size: 1.5rem;
                    font-weight: 800;
                    color: var(--text-primary);
                }
                
                @media (max-width: 768px) {
                    .title-group h1 { font-size: 1.8rem; }
                    .stats-grid { grid-template-columns: 1fr 1fr; }
                }
            `}</style>

            <div className="header-top">
                <div className="title-group">
                    <h1>Painel <span className="text-gradient">Administrativo</span></h1>
                    <p>Gestão inteligente da sua malha logística.</p>
                </div>
                <div className="action-group">
                    <button onClick={onRefresh} className="admin-action-btn" title="Atualizar">
                        <RefreshCw size={18} />
                    </button>
                    <button onClick={onExport} className="admin-action-btn" title="Exportar CSV">
                        <Download size={18} />
                    </button>
                    <button onClick={onAdd} className="btn-primary" style={{ borderRadius: '12px', padding: '10px 20px', gap: '8px' }}>
                        <Plus size={18} /> Novo Rastreio
                    </button>
                </div>
            </div>

            <div className="stats-grid">
                <div className="stat-card">
                    <div className="stat-icon" style={{ background: 'rgba(59, 130, 246, 0.1)', color: '#3b82f6' }}>
                        <Box size={24} />
                    </div>
                    <div className="stat-info">
                        <h3>Total Rastreios</h3>
                        <p>{stats.total}</p>
                    </div>
                </div>
                <div className="stat-card">
                    <div className="stat-icon" style={{ background: 'rgba(16, 185, 129, 0.1)', color: '#10b981' }}>
                        <CheckCircle size={24} />
                    </div>
                    <div className="stat-info">
                        <h3>Entregues</h3>
                        <p>{stats.entregues}</p>
                    </div>
                </div>
                <div className="stat-card">
                    <div className="stat-icon" style={{ background: 'rgba(245, 158, 11, 0.1)', color: '#f59e0b' }}>
                        <AlertTriangle size={24} />
                    </div>
                    <div className="stat-info">
                        <h3>Com Taxa</h3>
                        <p>{stats.com_taxa}</p>
                    </div>
                </div>
                <div className="stat-card">
                    <div className="stat-icon" style={{ background: 'rgba(107, 114, 128, 0.1)', color: '#6b7280' }}>
                        <Package size={24} />
                    </div>
                    <div className="stat-info">
                        <h3>Sem Taxa</h3>
                        <p>{stats.sem_taxa}</p>
                    </div>
                </div>
            </div>
        </header>
    );
};

export default AdminHeader;
