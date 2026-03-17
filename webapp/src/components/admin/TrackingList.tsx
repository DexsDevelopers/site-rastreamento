import React, { useState } from 'react';
import { Eye, Copy, Edit, MessageCircle, Trash2, MapPin, ExternalLink, Check, Clock, Package, Truck, CheckCircle2, AlertTriangle, MoreVertical, Link2 } from 'lucide-react';

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
    const [copiedCode, setCopiedCode] = useState<string | null>(null);
    const [copiedLink, setCopiedLink] = useState<string | null>(null);
    const [openMenu, setOpenMenu] = useState<string | null>(null);

    const getStatusInfo = (status: string) => {
        if (status.includes('Entregue')) return { color: '#22C55E', bg: 'rgba(34,197,94,0.12)', border: 'rgba(34,197,94,0.25)', icon: <CheckCircle2 size={14} />, label: 'Entregue' };
        if (status.includes('Saiu') || status.includes('rota')) return { color: '#3B82F6', bg: 'rgba(59,130,246,0.12)', border: 'rgba(59,130,246,0.25)', icon: <Truck size={14} />, label: 'Em Rota' };
        if (status.includes('trânsito') || status.includes('transito')) return { color: '#F59E0B', bg: 'rgba(245,158,11,0.12)', border: 'rgba(245,158,11,0.25)', icon: <Truck size={14} />, label: 'Trânsito' };
        if (status.includes('distribuição') || status.includes('centro')) return { color: '#8B5CF6', bg: 'rgba(139,92,246,0.12)', border: 'rgba(139,92,246,0.25)', icon: <Package size={14} />, label: 'Distribuição' };
        if (status.includes('retido') || status.includes('Retido')) return { color: '#EF4444', bg: 'rgba(239,68,68,0.12)', border: 'rgba(239,68,68,0.25)', icon: <AlertTriangle size={14} />, label: 'Retido' };
        if (status.includes('postado')) return { color: '#06B6D4', bg: 'rgba(6,182,212,0.12)', border: 'rgba(6,182,212,0.25)', icon: <Package size={14} />, label: 'Postado' };
        return { color: '#94A3B8', bg: 'rgba(148,163,184,0.12)', border: 'rgba(148,163,184,0.25)', icon: <Clock size={14} />, label: 'Pendente' };
    };

    const handleCopyCode = (codigo: string) => {
        navigator.clipboard.writeText(codigo);
        setCopiedCode(codigo);
        setTimeout(() => setCopiedCode(null), 2000);
    };

    const handleCopyLink = (codigo: string) => {
        const link = `${window.location.origin}/rastreio/${codigo}`;
        navigator.clipboard.writeText(link);
        setCopiedLink(codigo);
        setTimeout(() => setCopiedLink(null), 2000);
    };

    const formatDate = (dateStr: string) => {
        try {
            const d = new Date(dateStr);
            return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: '2-digit' });
        } catch { return '—'; }
    };

    const formatTime = (dateStr: string) => {
        try {
            const d = new Date(dateStr);
            return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        } catch { return ''; }
    };

    if (trackings.length === 0) {
        return (
            <div style={{ textAlign: 'center', padding: '80px 20px', color: '#64748B' }}>
                <Package size={48} style={{ marginBottom: '16px', opacity: 0.4 }} />
                <p style={{ fontSize: '1.1rem', fontWeight: 600 }}>Nenhum rastreio encontrado</p>
                <p style={{ fontSize: '0.85rem', marginTop: '4px' }}>Tente ajustar os filtros ou criar um novo rastreio.</p>
            </div>
        );
    }

    return (
        <div className="tl-modern">
            <style>{`
                .tl-modern { display: flex; flex-direction: column; gap: 0; }

                /* ===== DESKTOP TABLE ===== */
                .tl-table-head {
                    display: grid;
                    grid-template-columns: 2fr 1.2fr 1fr 0.8fr 1.5fr;
                    padding: 14px 24px;
                    background: rgba(255,255,255,0.03);
                    border: 1px solid rgba(255,255,255,0.05);
                    border-radius: 16px 16px 0 0;
                    font-size: 0.7rem;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 1.2px;
                    color: #64748B;
                }
                .tl-row {
                    display: grid;
                    grid-template-columns: 2fr 1.2fr 1fr 0.8fr 1.5fr;
                    padding: 16px 24px;
                    align-items: center;
                    border-bottom: 1px solid rgba(255,255,255,0.04);
                    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                    position: relative;
                }
                .tl-row:last-child { border-bottom: none; border-radius: 0 0 16px 16px; }
                .tl-row:hover {
                    background: rgba(37, 99, 235, 0.04);
                    box-shadow: inset 0 0 0 1px rgba(37, 99, 235, 0.08);
                }

                /* Code Cell */
                .tl-code-cell { display: flex; flex-direction: column; gap: 6px; }
                .tl-code-top { display: flex; align-items: center; gap: 8px; }
                .tl-code-text {
                    font-family: 'JetBrains Mono', 'Fira Code', monospace;
                    font-weight: 800;
                    font-size: 0.95rem;
                    color: #F1F5F9;
                    letter-spacing: 0.5px;
                }
                .tl-code-actions { display: flex; gap: 4px; }
                .tl-mini-btn {
                    width: 28px; height: 28px;
                    border-radius: 8px;
                    border: 1px solid rgba(255,255,255,0.08);
                    background: rgba(255,255,255,0.03);
                    color: #64748B;
                    display: flex; align-items: center; justify-content: center;
                    cursor: pointer;
                    transition: all 0.2s;
                    position: relative;
                }
                .tl-mini-btn:hover { background: rgba(37,99,235,0.15); color: #60A5FA; border-color: rgba(37,99,235,0.3); transform: translateY(-1px); }
                .tl-mini-btn.copied { background: rgba(34,197,94,0.2); color: #22C55E; border-color: rgba(34,197,94,0.3); }
                .tl-code-meta { font-size: 0.72rem; color: #475569; display: flex; align-items: center; gap: 6px; }

                /* Destination */
                .tl-dest { display: flex; align-items: center; gap: 10px; }
                .tl-dest-icon {
                    width: 34px; height: 34px; border-radius: 10px;
                    background: rgba(37,99,235,0.08);
                    display: flex; align-items: center; justify-content: center;
                    color: #3B82F6; flex-shrink: 0;
                }
                .tl-dest-info { display: flex; flex-direction: column; }
                .tl-dest-city { font-weight: 700; color: #E2E8F0; font-size: 0.9rem; }
                .tl-dest-date { font-size: 0.72rem; color: #64748B; }

                /* Status Pill */
                .tl-status-pill {
                    display: inline-flex; align-items: center; gap: 6px;
                    padding: 5px 12px; border-radius: 20px;
                    font-size: 0.78rem; font-weight: 700;
                    white-space: nowrap;
                    border: 1px solid;
                }

                /* Finance */
                .tl-finance { font-size: 0.82rem; font-weight: 700; }
                .tl-finance-paid { color: #22C55E; }
                .tl-finance-pending { color: #F59E0B; }

                /* Actions */
                .tl-actions { display: flex; gap: 6px; justify-content: flex-end; align-items: center; flex-wrap: nowrap; }
                .tl-action-btn {
                    width: 34px; height: 34px; border-radius: 10px;
                    border: 1px solid rgba(255,255,255,0.06);
                    background: rgba(255,255,255,0.02); color: #94A3B8;
                    display: flex; align-items: center; justify-content: center;
                    cursor: pointer; transition: all 0.2s; flex-shrink: 0;
                }
                .tl-action-btn:hover { background: rgba(255,255,255,0.08); color: #F1F5F9; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
                .tl-action-btn.danger:hover { background: rgba(239,68,68,0.15); color: #EF4444; }
                .tl-action-btn.success:hover { background: rgba(34,197,94,0.15); color: #22C55E; }
                .tl-action-btn.primary:hover { background: rgba(37,99,235,0.15); color: #3B82F6; }

                .tl-link-btn {
                    display: flex; align-items: center; gap: 6px;
                    padding: 6px 12px; border-radius: 8px;
                    border: 1px solid rgba(37,99,235,0.2);
                    background: rgba(37,99,235,0.08);
                    color: #60A5FA; font-size: 0.75rem; font-weight: 700;
                    cursor: pointer; transition: all 0.2s; white-space: nowrap;
                }
                .tl-link-btn:hover { background: rgba(37,99,235,0.18); transform: translateY(-1px); }
                .tl-link-btn.copied-link { background: rgba(34,197,94,0.15); color: #22C55E; border-color: rgba(34,197,94,0.3); }

                /* ===== MOBILE ===== */
                @media (max-width: 900px) {
                    .tl-table-head { display: none; }
                    .tl-modern { gap: 12px; }
                    .tl-row {
                        display: flex; flex-direction: column; gap: 14px;
                        padding: 20px;
                        background: rgba(255,255,255,0.02);
                        border: 1px solid rgba(255,255,255,0.06);
                        border-radius: 16px;
                    }
                    .tl-row:hover { transform: none; }
                    .tl-mobile-top {
                        display: flex; justify-content: space-between; align-items: flex-start; width: 100%;
                    }
                    .tl-mobile-middle {
                        display: flex; justify-content: space-between; align-items: center; width: 100%;
                        gap: 12px; flex-wrap: wrap;
                    }
                    .tl-mobile-bottom {
                        display: flex; gap: 8px; width: 100%; flex-wrap: wrap;
                        padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.04);
                    }
                    .tl-actions { width: 100%; justify-content: space-between; }
                    .tl-action-btn { flex: 1; max-width: 44px; height: 40px; }
                    .tl-link-btn { flex: 1; justify-content: center; padding: 10px; font-size: 0.8rem; }
                }

                /* ===== EXTRA SMALL ===== */
                @media (max-width: 480px) {
                    .tl-code-text { font-size: 0.85rem; }
                    .tl-dest-city { font-size: 0.82rem; }
                    .tl-mobile-bottom { flex-direction: column; }
                    .tl-actions { flex-wrap: wrap; }
                    .tl-link-btn { width: 100%; }
                }
            `}</style>

            {/* Desktop Header */}
            <div className="tl-table-head">
                <div>Código & Link</div>
                <div>Destino</div>
                <div>Status</div>
                <div>Cobrança</div>
                <div style={{ textAlign: 'right' }}>Ações</div>
            </div>

            {trackings.map((t) => {
                const st = getStatusInfo(t.status_atual);
                const isPaid = !t.taxa_valor || t.taxa_valor <= 0;
                const trackingLink = `${window.location.origin}/rastreio/${t.codigo}`;
                const isCopied = copiedCode === t.codigo;
                const isLinkCopied = copiedLink === t.codigo;

                return (
                    <div key={t.id} className="tl-row">
                        {/* === CODE CELL === */}
                        <div className="tl-code-cell">
                            <div className="tl-code-top">
                                <span className="tl-code-text">{t.codigo}</span>
                                <div className="tl-code-actions">
                                    <button
                                        className={`tl-mini-btn ${isCopied ? 'copied' : ''}`}
                                        onClick={() => handleCopyCode(t.codigo)}
                                        title="Copiar código"
                                    >
                                        {isCopied ? <Check size={12} /> : <Copy size={12} />}
                                    </button>
                                    <button
                                        className={`tl-mini-btn ${isLinkCopied ? 'copied' : ''}`}
                                        onClick={() => handleCopyLink(t.codigo)}
                                        title="Copiar link do cliente"
                                    >
                                        {isLinkCopied ? <Check size={12} /> : <Link2 size={12} />}
                                    </button>
                                </div>
                            </div>
                            <div className="tl-code-meta">
                                <Clock size={10} /> {formatDate(t.data)} às {formatTime(t.data)}
                            </div>
                        </div>

                        {/* === DESTINATION === */}
                        <div className="tl-dest">
                            <div className="tl-dest-icon"><MapPin size={16} /></div>
                            <div className="tl-dest-info">
                                <span className="tl-dest-city">{t.cidade || '—'}</span>
                                <span className="tl-dest-date">Destino final</span>
                            </div>
                        </div>

                        {/* === STATUS === */}
                        <div>
                            <div
                                className="tl-status-pill"
                                style={{ background: st.bg, color: st.color, borderColor: st.border }}
                            >
                                {st.icon} {st.label}
                            </div>
                        </div>

                        {/* === FINANCE === */}
                        <div className={`tl-finance ${isPaid ? 'tl-finance-paid' : 'tl-finance-pending'}`}>
                            {isPaid ? '● Pago' : `R$ ${Number(t.taxa_valor).toFixed(2).replace('.', ',')}`}
                        </div>

                        {/* === ACTIONS === */}
                        <div className="tl-actions">
                            <button
                                className={`tl-link-btn ${isLinkCopied ? 'copied-link' : ''}`}
                                onClick={() => handleCopyLink(t.codigo)}
                            >
                                {isLinkCopied ? <><Check size={13} /> Copiado!</> : <><ExternalLink size={13} /> Link Cliente</>}
                            </button>
                            <button onClick={() => onView(t.codigo)} className="tl-action-btn primary" title="Visualizar"><Eye size={16} /></button>
                            <button onClick={() => onEdit(t.codigo)} className="tl-action-btn" title="Editar"><Edit size={16} /></button>
                            <button onClick={() => onNotify(t.codigo)} className="tl-action-btn success" title="WhatsApp"><MessageCircle size={16} /></button>
                            <button onClick={() => onDelete(t.codigo)} className="tl-action-btn danger" title="Excluir"><Trash2 size={16} /></button>
                        </div>
                    </div>
                );
            })}
        </div>
    );
};

export default TrackingList;
