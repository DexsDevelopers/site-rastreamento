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
        <div className="tracking-list-saas">
            <style>{`
                .tracking-list-saas {
                    width: 100%;
                    border-radius: 20px;
                    overflow: hidden;
                    background: rgba(255,255,255,0.02);
                    border: 1px solid var(--border-glass);
                }
                .list-header-saas {
                    display: grid;
                    grid-template-columns: 180px 1.5fr 1fr 1fr 200px;
                    padding: 16px 24px;
                    background: rgba(255,255,255,0.03);
                    border-bottom: 1px solid var(--border-glass);
                    font-size: 0.75rem;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    color: var(--text-secondary);
                }
                .row-saas {
                    display: grid;
                    grid-template-columns: 180px 1.5fr 1fr 1fr 200px;
                    padding: 16px 24px;
                    align-items: center;
                    border-bottom: 1px solid var(--border-glass);
                    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                    cursor: pointer;
                    background: transparent;
                }
                .row-saas:hover {
                    background: rgba(59, 130, 246, 0.04);
                    transform: scale(1.005);
                    box-shadow: inset 0 0 20px rgba(59, 130, 246, 0.02);
                    z-index: 1;
                }
                
                .code-group {
                    display: flex;
                    flex-direction: column;
                }
                .code-main {
                    font-weight: 800;
                    color: #fff;
                    font-size: 1rem;
                    letter-spacing: -0.5px;
                }
                .code-sub {
                    font-size: 0.75rem;
                    color: var(--text-secondary);
                }

                .dest-group {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .dest-icon {
                    width: 32px;
                    height: 32px;
                    border-radius: 8px;
                    background: rgba(59, 130, 246, 0.1);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: var(--accent-primary);
                }
                .dest-info {
                    display: flex;
                    flex-direction: column;
                }
                .dest-city {
                    font-weight: 600;
                    color: #f8fafc;
                }
                .dest-label {
                    font-size: 0.7rem;
                    color: var(--text-secondary);
                    text-transform: uppercase;
                }

                .status-saas {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 6px 12px;
                    border-radius: 12px;
                    width: fit-content;
                    font-size: 0.85rem;
                    font-weight: 700;
                }
                .status-postado { background: rgba(37, 99, 235, 0.1); color: #60a5fa; border: 1px solid rgba(37, 99, 235, 0.2); }
                .status-transito { background: rgba(245, 158, 11, 0.1); color: var(--status-warning); border: 1px solid rgba(245, 158, 11, 0.2); }
                .status-entregue { background: rgba(34, 197, 94, 0.1); color: var(--status-success); border: 1px solid rgba(34, 197, 94, 0.2); }
                
                .finance-badge-saas {
                    padding: 4px 10px;
                    border-radius: 8px;
                    font-size: 0.75rem;
                    font-weight: 800;
                    text-transform: uppercase;
                }
                .finance-pago { background: rgba(34, 197, 94, 0.2); color: var(--status-success); border: 1px solid rgba(34, 197, 94, 0.3); }
                .finance-pendente { background: rgba(245, 158, 11, 0.2); color: var(--status-warning); border: 1px solid rgba(245, 158, 11, 0.3); }

                .actions-saas {
                    display: flex;
                    gap: 8px;
                    justify-content: flex-end;
                }
                .action-btn-saas {
                    width: 36px;
                    height: 36px;
                    border-radius: 10px;
                    border: 1px solid var(--border-glass);
                    background: rgba(255,255,255,0.02);
                    color: var(--text-secondary);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .action-btn-saas:hover {
                    background: rgba(255,255,255,0.08);
                    color: #fff;
                    transform: translateY(-2px);
                }

                @media (max-width: 1000px) {
                    .list-header-saas { display: none; }
                    .row-saas {
                        grid-template-columns: 1fr 1fr;
                        grid-template-areas: "code actions" "dest finance" "status status";
                        gap: 16px;
                        padding: 20px;
                        border-radius: 16px;
                        margin: 12px;
                        background: rgba(255,255,255,0.02);
                        border: 1px solid var(--border-glass);
                    }
                    .code-group { grid-area: code; }
                    .actions-saas { grid-area: actions; }
                    .dest-group { grid-area: dest; }
                    .finance-badge-saas { grid-area: finance; justify-self: flex-end; }
                    .status-saas { grid-area: status; width: 100%; justify-content: center; }
                }
            `}</style>

            <div className="list-header-saas">
                <div>Identificador</div>
                <div>Destino Operacional</div>
                <div>Status</div>
                <div>Financeiro</div>
                <div style={{ textAlign: 'right' }}>Gestão</div>
            </div>

            {trackings.map((t) => {
                const status = getStatusInfo(t.status_atual);
                const isPaid = !t.taxa_valor || t.taxa_valor <= 0;

                return (
                    <div key={t.id} className="row-saas">
                        <div className="code-group">
                            <span className="code-main">{t.codigo}</span>
                            <span className="code-sub">UUID: {t.id}</span>
                        </div>

                        <div className="dest-group">
                            <div className="dest-icon"><MapPin size={16} /></div>
                            <div className="dest-info">
                                <span className="dest-city">{t.cidade}</span>
                                <span className="dest-label">Destino Final</span>
                            </div>
                        </div>

                        <div className="status-cell">
                            <div className={`status-saas ${status.class}`}>
                                <span>{status.icon}</span>
                                <span>{t.status_atual}</span>
                            </div>
                        </div>

                        <div className="finance-cell">
                            {isPaid ? (
                                <span className="finance-badge-saas finance-pago">● Pago</span>
                            ) : (
                                <span className="finance-badge-saas finance-pendente">● Pendente</span>
                            )}
                        </div>

                        <div className="actions-saas">
                            <button onClick={() => onView(t.codigo)} className="action-btn-saas" title="Visualizar"><Eye size={18} /></button>
                            <button onClick={() => onCopy(t.codigo)} className="action-btn-saas" title="Copiar Link"><Copy size={18} /></button>
                            <button onClick={() => onEdit(t.codigo)} className="action-btn-saas" title="Editar"><Edit size={18} /></button>
                            <button onClick={() => onNotify(t.codigo)} className="action-btn-saas" title="Notificar WhatsApp" style={{ color: '#10b981' }}><MessageCircle size={18} /></button>
                            <button onClick={() => onDelete(t.codigo)} className="action-btn-saas" title="Excluir" style={{ color: '#ef4444' }}><Trash2 size={18} /></button>
                        </div>
                    </div>
                );
            })}
        </div>
    );
};

export default TrackingList;
