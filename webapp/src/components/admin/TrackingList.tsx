import React from 'react';
import { Eye, Copy, Edit, MessageCircle, Trash2, MapPin } from 'lucide-react';

interface Tracking {
    id: number;
    codigo: string;
    cidade: string;
    status_atual: string;
    data: string;
    taxa_valor: number | null;
}

interface TrackingListProps {
    trackings: Tracking[];
    onView: (code: string) => void;
    onCopy: (code: string) => void;
    onEdit: (code: string) => void;
    onNotify: (code: string) => void;
    onDelete: (code: string) => void;
}

const TrackingList: React.FC<TrackingListProps> = ({ trackings, onView, onCopy, onEdit, onNotify, onDelete }) => {

    const getStatusInfo = (status: string) => {
        if (status.includes('Entregue')) return { class: 'status-entregue', icon: '✅' };
        if (status.includes('Saiu')) return { class: 'status-saiu', icon: '🚚' };
        if (status.includes('trânsito')) return { class: 'status-transito', icon: '🌐' };
        if (status.includes('postado')) return { class: 'status-postado', icon: '📦' };
        return { class: 'status-default', icon: 'ℹ️' };
    };

    return (
        <div className="tracking-list-container">
            <style>{`
                .tracking-list-container {
                    width: 100%;
                }
                .tracking-card-header {
                    display: grid;
                    grid-template-columns: 140px 1fr 180px 180px 160px;
                    padding: 10px 16px;
                    background: rgba(255,255,255,0.02);
                    border-bottom: 2px solid var(--border-glass-strong);
                    font-size: 0.75rem;
                    font-weight: 800;
                    text-transform: uppercase;
                    color: var(--text-secondary);
                    letter-spacing: 0.05em;
                }
                .tracking-card {
                    display: grid;
                    grid-template-columns: 140px 1fr 180px 180px 160px;
                    align-items: center;
                    padding: 8px 16px;
                    background: var(--bg-glass);
                    border-bottom: 1px solid var(--border-glass);
                    transition: all 0.1s ease-out;
                    cursor: default;
                    animation: fadeInRow 0.2s ease-out forwards;
                }
                @keyframes fadeInRow {
                    from { opacity: 0; transform: translateY(5px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .tracking-card:hover {
                    background: rgba(59, 130, 246, 0.05);
                }
                
                .card-code { font-weight: 800; color: #fff; font-size: 0.95rem; }
                .card-dest { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; }
                .card-status { display: flex; align-items: center; }
                .card-payment { font-weight: 700; }
                .card-actions { display: flex; gap: 6px; justify-content: flex-end; }

                .action-mini-btn {
                    padding: 6px;
                    border-radius: 6px;
                    border: 1px solid rgba(255,255,255,0.05);
                    background: rgba(255,255,255,0.03);
                    color: var(--text-secondary);
                    cursor: pointer;
                    transition: all 0.15s;
                }
                .action-mini-btn:hover {
                    background: rgba(255,255,255,0.1);
                    color: var(--text-primary);
                }
                
                .badge-pago { color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; border: 1px solid rgba(16, 185, 129, 0.2); }
                .badge-pendente { color: #f59e0b; background: rgba(245, 158, 11, 0.1); padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; border: 1px solid rgba(245, 158, 11, 0.2); }

                @media (max-width: 1000px) {
                    .tracking-card-header { display: none; }
                    .tracking-card {
                        grid-template-columns: 1fr 1fr;
                        grid-template-areas: "code actions" "dest status" "payment payment";
                        gap: 8px;
                        padding: 12px;
                        margin-bottom: 8px;
                        border: 1px solid var(--border-glass);
                        border-radius: 12px;
                    }
                    .card-code { grid-area: code; }
                    .card-actions { grid-area: actions; }
                    .card-dest { grid-area: dest; }
                    .card-status { grid-area: status; }
                    .card-payment { grid-area: payment; border-top: 1px solid var(--border-glass); padding-top: 8px; }
                }
            `}</style>

            <div className="tracking-card-header">
                <div>Código</div>
                <div>Destino</div>
                <div>Status</div>
                <div>Financeiro</div>
                <div style={{ textAlign: 'right' }}>Ações</div>
            </div>

            {trackings.map((t) => {
                const status = getStatusInfo(t.status_atual);
                const isPaid = !t.taxa_valor || t.taxa_valor <= 0;

                return (
                    <div key={t.id} className="tracking-card">
                        <div className="card-code">{t.codigo}</div>
                        <div className="card-dest">
                            <MapPin size={14} style={{ color: 'var(--accent-primary)' }} />
                            {t.cidade}
                        </div>
                        <div className="card-status">
                            <span className={`status-badge ${status.class}`}>
                                {status.icon} {t.status_atual}
                            </span>
                        </div>
                        <div className="card-payment">
                            {isPaid ? (
                                <span className="badge-pago">LIVRE / PAGO</span>
                            ) : (
                                <span className="badge-pendente">TAXA: R$ {Number(t.taxa_valor).toFixed(2)}</span>
                            )}
                        </div>
                        <div className="card-actions">
                            <button onClick={() => onView(t.codigo)} className="action-mini-btn" title="Ver"><Eye size={16} /></button>
                            <button onClick={() => onCopy(t.codigo)} className="action-mini-btn" title="Link"><Copy size={16} /></button>
                            <button onClick={() => onEdit(t.codigo)} className="action-mini-btn" title="Editar"><Edit size={16} /></button>
                            <button onClick={() => onNotify(t.codigo)} className="action-mini-btn" title="Whats" style={{ color: '#10b981' }}><MessageCircle size={16} /></button>
                            <button onClick={() => onDelete(t.codigo)} className="action-mini-btn" title="Excluir" style={{ color: '#ef4444' }}><Trash2 size={16} /></button>
                        </div>
                    </div>
                );
            })}
        </div>
    );
};

export default TrackingList;
