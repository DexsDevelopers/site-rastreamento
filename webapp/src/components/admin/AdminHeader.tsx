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
                    margin-bottom: 48px;
                }
                .header-top {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                    gap: 24px;
                    flex-wrap: wrap;
                    margin-bottom: 32px;
                }
                .title-group h1 {
                    font-size: 2.8rem;
                    font-weight: 800;
                    margin: 0;
                    letter-spacing: -2px;
                    line-height: 1;
                }
                .subtitle {
                    color: var(--text-secondary);
                    margin-top: 12px;
                    font-size: 1.1rem;
                    font-weight: 500;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .status-indicator {
                    font-size: 0.8rem;
                    color: #10b981;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    background: rgba(16, 185, 129, 0.1);
                    padding: 4px 10px;
                    border-radius: 20px;
                    border: 1px solid rgba(16, 185, 129, 0.2);
                }
                .action-group {
                    display: flex;
                    gap: 12px;
                }
                .admin-action-btn-saas {
                    width: 44px;
                    height: 44px;
                    border-radius: 12px;
                    border: 1px solid var(--border-glass);
                    background: rgba(255,255,255,0.03);
                    color: var(--text-secondary);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .admin-action-btn-saas:hover {
                    background: rgba(255,255,255,0.08);
                    color: #fff;
                    border-color: rgba(255,255,255,0.2);
                }
                
                .stats-grid-saas {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                    gap: 24px;
                }
                .stat-card-saas {
                    padding: 24px;
                    display: flex;
                    flex-direction: column;
                    gap: 20px;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }
                .stat-card-saas:hover {
                    transform: translateY(-5px);
                    background: rgba(255,255,255,0.05);
                    border-color: rgba(255,255,255,0.15);
                }
                .stat-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                }
                .stat-icon-saas {
                    width: 52px;
                    height: 52px;
                    border-radius: 16px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.5rem;
                }
                .stat-info-saas h3 {
                    margin: 0;
                    font-size: 0.9rem;
                    color: var(--text-secondary);
                    font-weight: 600;
                }
                .stat-info-saas p {
                    margin: 8px 0 0;
                    font-size: 2.2rem;
                    font-weight: 800;
                    color: #fff;
                    letter-spacing: -1px;
                }
                .stat-footer {
                    font-size: 0.75rem;
                    color: var(--text-secondary);
                    opacity: 0.6;
                    display: flex;
                    align-items: center;
                    gap: 4px;
                }
            `}</style>

            <div className="header-top">
                <div className="title-group">
                    <h1 className="text-gradient">Painel Administrativo</h1>
                    <div className="subtitle">
                        Gestão inteligente da sua malha logística
                        <span className="status-indicator">
                            <span style={{ animation: 'pulse 2s infinite' }}>●</span> Online
                        </span>
                    </div>
                </div>
                <div className="action-group">
                    <button onClick={onRefresh} className="admin-action-btn-saas" title="Atualizar">
                        <RefreshCw size={20} />
                    </button>
                    <button onClick={onExport} className="admin-action-btn-saas" title="Exportar CSV">
                        <Download size={20} />
                    </button>
                    <button onClick={onAdd} className="btn-primary-saas">
                        <Plus size={20} /> Novo Rastreio
                    </button>
                </div>
            </div>

            <div className="stats-grid-saas">
                <div className="stat-card-saas glass-card">
                    <div className="stat-header">
                        <div className="stat-info-saas">
                            <h3>Total Rastreios</h3>
                            <p>{stats.total}</p>
                        </div>
                        <div className="stat-icon-saas" style={{ background: 'rgba(59, 130, 246, 0.1)', color: '#3b82f6', border: '1px solid rgba(59, 130, 246, 0.2)' }}>
                            <Box size={28} />
                        </div>
                    </div>
                    <div className="stat-footer">● Atualizado agora</div>
                </div>

                <div className="stat-card-saas glass-card">
                    <div className="stat-header">
                        <div className="stat-info-saas">
                            <h3>Entregues</h3>
                            <p>{stats.entregues}</p>
                        </div>
                        <div className="stat-icon-saas" style={{ background: 'rgba(16, 185, 129, 0.1)', color: '#10b981', border: '1px solid rgba(16, 185, 129, 0.2)' }}>
                            <CheckCircle size={28} />
                        </div>
                    </div>
                    <div className="stat-footer">● Atualizado agora</div>
                </div>

                <div className="stat-card-saas glass-card">
                    <div className="stat-header">
                        <div className="stat-info-saas">
                            <h3>Com Taxa</h3>
                            <p>{stats.com_taxa}</p>
                        </div>
                        <div className="stat-icon-saas" style={{ background: 'rgba(245, 158, 11, 0.1)', color: '#f59e0b', border: '1px solid rgba(245, 158, 11, 0.2)' }}>
                            <AlertTriangle size={28} />
                        </div>
                    </div>
                    <div className="stat-footer">● Atualizado agora</div>
                </div>

                <div className="stat-card-saas glass-card">
                    <div className="stat-header">
                        <div className="stat-info-saas">
                            <h3>Sem Taxa</h3>
                            <p>{stats.sem_taxa}</p>
                        </div>
                        <div className="stat-icon-saas" style={{ background: 'rgba(148, 163, 184, 0.1)', color: '#94a3b8', border: '1px solid rgba(148, 163, 184, 0.2)' }}>
                            <Package size={28} />
                        </div>
                    </div>
                    <div className="stat-footer">● Atualizado agora</div>
                </div>
            </div>
        </header>
    );
};

export default AdminHeader;
