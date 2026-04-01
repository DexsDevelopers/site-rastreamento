import React, { useState } from 'react';
import { Eye, Copy, Edit, MessageCircle, Trash2, MapPin, Check, Clock, Package, Truck, CheckCircle2, AlertTriangle, SearchX, Link2 } from 'lucide-react';

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
    isLoading?: boolean;
    onView: (code: string) => void;
    onCopy: (code: string) => void;
    onEdit: (code: string) => void;
    onNotify: (code: string) => void;
    onDelete: (code: string) => void;
}

const TrackingList: React.FC<TrackingListProps> = ({ trackings, isLoading = false, onView, onCopy, onEdit, onNotify, onDelete }) => {
    const [copiedCode, setCopiedCode] = useState<string | null>(null);
    const [copiedLink, setCopiedLink] = useState<string | null>(null);

    const getStatusInfo = (status: string) => {
        const s = (status || '').toLowerCase();
        if (s.includes('entregue')) return { color: '#10B981', bg: 'rgba(16, 185, 129, 0.1)', border: 'rgba(16, 185, 129, 0.2)', icon: <CheckCircle2 size={14} />, label: 'Entregue' };
        if (s.includes('saiu') || s.includes('rota')) return { color: '#3B82F6', bg: 'rgba(59, 130, 246, 0.1)', border: 'rgba(59, 130, 246, 0.2)', icon: <Truck size={14} />, label: 'Em Rota' };
        if (s.includes('trânsito') || s.includes('transito')) return { color: '#F59E0B', bg: 'rgba(245, 158, 11, 0.1)', border: 'rgba(245, 158, 11, 0.2)', icon: <Truck size={14} />, label: 'Trânsito' };
        if (s.includes('distribuição') || s.includes('centro')) return { color: '#8B5CF6', bg: 'rgba(139, 92, 246, 0.1)', border: 'rgba(139, 92, 246, 0.2)', icon: <Package size={14} />, label: 'Distribuição' };
        if (s.includes('retido')) return { color: '#EF4444', bg: 'rgba(239, 68, 68, 0.1)', border: 'rgba(239, 68, 68, 0.2)', icon: <AlertTriangle size={14} />, label: 'Retido' };
        if (s.includes('postado')) return { color: '#06B6D4', bg: 'rgba(6, 182, 212, 0.1)', border: 'rgba(6, 182, 212, 0.2)', icon: <Package size={14} />, label: 'Postado' };
        return { color: '#94A3B8', bg: 'rgba(148, 163, 184, 0.1)', border: 'rgba(148, 163, 184, 0.2)', icon: <Clock size={14} />, label: 'Pendente' };
    };

    const handleCopyCode = (codigo: string) => {
        navigator.clipboard.writeText(codigo);
        onCopy(codigo);
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
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            if (isNaN(d.getTime()) || d.getFullYear() < 2000) return '—';
            return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
        } catch { return '—'; }
    };

    const formatTime = (dateStr: string) => {
        try {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            if (isNaN(d.getTime()) || d.getFullYear() < 2000) return '';
            return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        } catch { return ''; }
    };

    // --- Loading Skeleton ---
    if (isLoading) {
        return (
            <div className="tl-container">
                <style dangerouslySetInnerHTML={{ __html: styles }} />
                <div className="tl-header">
                    <div>Código & Info</div>
                    <div>Localização</div>
                    <div>Status Atual</div>
                    <div>Cobrança</div>
                    <div style={{ textAlign: 'right' }}>Gestão</div>
                </div>
                {[1, 2, 3, 4, 5].map((i) => (
                    <div key={i} className="tl-row skeleton-row">
                        <div className="tl-cell"><div className="skeleton-box" style={{ width: '120px', height: '20px' }}></div><div className="skeleton-box" style={{ width: '80px', height: '14px', marginTop: '8px' }}></div></div>
                        <div className="tl-cell"><div className="skeleton-box" style={{ width: '100px', height: '16px' }}></div><div className="skeleton-box" style={{ width: '60px', height: '12px', marginTop: '6px' }}></div></div>
                        <div className="tl-cell"><div className="skeleton-box" style={{ width: '90px', height: '26px', borderRadius: '14px' }}></div></div>
                        <div className="tl-cell"><div className="skeleton-box" style={{ width: '60px', height: '16px' }}></div></div>
                        <div className="tl-cell" style={{ justifyContent: 'flex-end', display: 'flex', gap: '8px' }}>
                            <div className="skeleton-box" style={{ width: '32px', height: '32px', borderRadius: '8px' }}></div>
                            <div className="skeleton-box" style={{ width: '32px', height: '32px', borderRadius: '8px' }}></div>
                            <div className="skeleton-box" style={{ width: '32px', height: '32px', borderRadius: '8px' }}></div>
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    // --- Empty State ---
    if (trackings.length === 0) {
        return (
            <div className="tl-empty-state">
                <SearchX size={48} strokeWidth={1.5} />
                <h3>Nenhum rastreio encontrado</h3>
                <p>Não há dados para exibir com os filtros atuais. Tente ajustar sua busca ou cadastre um novo código de rastreamento.</p>
            </div>
        );
    }

    return (
        <div className="tl-container">
            <style dangerouslySetInnerHTML={{ __html: styles }} />

            {/* Desktop Header */}
            <div className="tl-header">
                <div>Rastreabilidade</div>
                <div>Destino</div>
                <div>Situação</div>
                <div>Financeiro</div>
                <div style={{ textAlign: 'right' }}>Ações de Gestão</div>
            </div>

            {/* List Body */}
            <div className="tl-body">
                {trackings.map((t) => {
                    const st = getStatusInfo(t.status_atual);
                    const isPaid = !t.taxa_valor || t.taxa_valor <= 0;
                    const isCodeCopied = copiedCode === t.codigo;
                    const isLinkCopied = copiedLink === t.codigo;

                    return (
                        <div key={t.id} className="tl-row group">
                            {/* --- COL 1: CODE & DATE --- */}
                            <div className="tl-cell tl-col-code">
                                <div className="tl-code-wrapper">
                                    <span className="tl-code-value">{t.codigo}</span>
                                    <div className="tl-micro-actions">
                                        <button
                                            className={`tl-icon-btn tl-tooltip-wrap ${isCodeCopied ? 'copied' : ''}`}
                                            onClick={() => handleCopyCode(t.codigo)}
                                            aria-label="Copiar código"
                                        >
                                            {isCodeCopied ? <Check size={13} /> : <Copy size={13} />}
                                            <span className="tl-tooltip">Copiar Código</span>
                                        </button>
                                        <button
                                            className={`tl-icon-btn tl-tooltip-wrap ${isLinkCopied ? 'copied' : ''}`}
                                            onClick={() => handleCopyLink(t.codigo)}
                                            aria-label="Copiar link público"
                                        >
                                            {isLinkCopied ? <Check size={13} /> : <Link2 size={13} />}
                                            <span className="tl-tooltip">Copiar Link</span>
                                        </button>
                                    </div>
                                </div>
                                <div className="tl-meta-text">
                                    <Clock size={11} /> {formatDate(t.data)} <span className="tl-dot">•</span> {formatTime(t.data)}
                                </div>
                            </div>

                            {/* --- COL 2: DESTINATION --- */}
                            <div className="tl-cell tl-col-dest">
                                <div className="tl-dest-icon-wrap">
                                    <MapPin size={15} />
                                </div>
                                <div className="tl-dest-info">
                                    <span className="tl-dest-city">{t.cidade || 'Não informado'}</span>
                                    <span className="tl-meta-text">Localidade atual</span>
                                </div>
                            </div>

                            {/* --- COL 3: STATUS --- */}
                            <div className="tl-cell tl-col-status">
                                <div className="tl-badge" style={{ background: st.bg, color: st.color, border: `1px solid ${st.border}` }}>
                                    {st.icon}
                                    <span>{st.label}</span>
                                </div>
                            </div>

                            {/* --- COL 4: FINANCE --- */}
                            <div className="tl-cell tl-col-finance">
                                {isPaid ? (
                                    <div className="tl-finance-paid">
                                        <div className="tl-dot-indicator green"></div>
                                        Pago
                                    </div>
                                ) : (
                                    <div className="tl-finance-pending">
                                        <div className="tl-dot-indicator orange"></div>
                                        R$ {Number(t.taxa_valor).toFixed(2).replace('.', ',')}
                                    </div>
                                )}
                            </div>

                            {/* --- COL 5: ACTIONS --- */}
                            <div className="tl-cell tl-col-actions">
                                <div className="tl-actions-group">
                                    <button onClick={() => onView(t.codigo)} className="tl-action-btn tl-tooltip-wrap" aria-label="Visualizar">
                                        <Eye size={16} />
                                        <span className="tl-tooltip">Visualizar Timeline</span>
                                    </button>
                                    <button onClick={() => onEdit(t.codigo)} className="tl-action-btn tl-tooltip-wrap" aria-label="Editar">
                                        <Edit size={16} />
                                        <span className="tl-tooltip">Editar Rastreio</span>
                                    </button>
                                    <button onClick={() => onNotify(t.codigo)} className="tl-action-btn notify tl-tooltip-wrap" aria-label="Notificar WhatsApp">
                                        <MessageCircle size={16} />
                                        <span className="tl-tooltip">Notificar Whatsapp</span>
                                    </button>
                                    <div className="tl-divider"></div>
                                    <button onClick={() => onDelete(t.codigo)} className="tl-action-btn danger tl-tooltip-wrap" aria-label="Excluir">
                                        <Trash2 size={16} />
                                        <span className="tl-tooltip">Excluir Registro</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

// ==========================================
// SAAS-GRADE CSS STYLES (Scoped to component)
// ==========================================
const styles = `
    /* Container & Base */
    .tl-container {
        width: 100%;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 20px;
        box-shadow: 0 4px 24px -4px rgba(0, 0, 0, 0.2);
        backdrop-filter: blur(12px);
        overflow: hidden;
        font-family: 'Outfit', -apple-system, sans-serif;
    }

    /* Grid Layout */
    .tl-header, .tl-row {
        display: grid;
        grid-template-columns: 1.8fr 1.5fr 1.2fr 1fr 1.5fr;
        gap: 16px;
        align-items: center;
    }

    .tl-header {
        padding: 16px 24px;
        background: rgba(255, 255, 255, 0.03);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        font-size: 0.75rem;
        font-weight: 700;
        color: #94A3B8;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .tl-row {
        padding: 18px 24px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        background: transparent;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }

    .tl-row:last-child {
        border-bottom: none;
    }

    /* Hover Micro-interactions */
    .tl-row:hover {
        background: rgba(255, 255, 255, 0.04);
        transform: translateY(-1px);
        box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.3);
        z-index: 2;
    }

    /* Common Cell Styles */
    .tl-cell {
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 0; /* Prevents flex blowout */
    }

    /* Code Column */
    .tl-code-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 4px;
    }
    
    .tl-code-value {
        font-family: 'JetBrains Mono', 'Fira Code', monospace;
        font-weight: 700;
        font-size: 0.95rem;
        color: #F8FAFC;
        letter-spacing: 0.5px;
    }

    .tl-micro-actions {
        display: flex;
        gap: 4px;
        opacity: 0.4;
        transition: opacity 0.2s;
    }
    
    .tl-row:hover .tl-micro-actions {
        opacity: 1;
    }

    /* Meta Text (Dates, Subtitles) */
    .tl-meta-text {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        color: #64748B;
        font-weight: 500;
    }
    
    .tl-dot {
        color: #334155;
        font-size: 10px;
    }

    /* Destination Column */
    .tl-col-dest {
        flex-direction: row;
        align-items: center;
        gap: 12px;
    }

    .tl-dest-icon-wrap {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        background: rgba(59, 130, 246, 0.1);
        color: #3B82F6;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .tl-dest-info {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .tl-dest-city {
        font-size: 0.9rem;
        font-weight: 600;
        color: #E2E8F0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Badges */
    .tl-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        white-space: nowrap;
        width: max-content;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    /* Finance Indicators */
    .tl-finance-paid, .tl-finance-pending {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        font-weight: 700;
    }
    
    .tl-finance-paid { color: #10B981; }
    .tl-finance-pending { color: #F59E0B; }

    .tl-dot-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    
    .tl-dot-indicator.green {
        background: #10B981;
        box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
    }
    
    .tl-dot-indicator.orange {
        background: #F59E0B;
        box-shadow: 0 0 8px rgba(245, 158, 11, 0.6);
    }

    /* Action Buttons */
    .tl-col-actions {
        align-items: flex-end;
    }

    .tl-actions-group {
        display: flex;
        align-items: center;
        gap: 6px;
        background: rgba(15, 23, 42, 0.4);
        padding: 4px;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .tl-divider {
        width: 1px;
        height: 16px;
        background: rgba(255, 255, 255, 0.1);
        margin: 0 2px;
    }

    .tl-action-btn, .tl-icon-btn {
        border: none;
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        color: #94A3B8;
    }

    .tl-icon-btn {
        width: 24px;
        height: 24px;
        border-radius: 6px;
    }

    .tl-icon-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #F8FAFC;
    }

    .tl-icon-btn.copied {
        background: rgba(16, 185, 129, 0.15);
        color: #10B981;
    }

    .tl-action-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
    }

    .tl-action-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #F8FAFC;
        transform: scale(1.05);
    }

    .tl-action-btn.notify:hover {
        background: rgba(16, 185, 129, 0.15);
        color: #10B981;
    }

    .tl-action-btn.danger:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #EF4444;
    }

    /* Tooltips */
    .tl-tooltip-wrap {
        position: relative;
    }

    .tl-tooltip {
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%) translateY(-8px);
        background: #0F172A;
        color: #F8FAFC;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        border: 1px solid rgba(255,255,255,0.1);
        z-index: 10;
    }

    .tl-tooltip::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border-width: 4px;
        border-style: solid;
        border-color: rgba(255,255,255,0.1) transparent transparent transparent;
    }

    @media (hover: hover) and (pointer: fine) {
        .tl-tooltip-wrap:hover .tl-tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(-4px);
        }
    }

    /* Empty State */
    .tl-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 80px 24px;
        background: rgba(255, 255, 255, 0.02);
        border: 1px dashed rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        text-align: center;
        color: #64748B;
    }

    .tl-empty-state svg {
        color: #334155;
        margin-bottom: 20px;
    }

    .tl-empty-state h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #E2E8F0;
        margin-bottom: 8px;
    }

    .tl-empty-state p {
        font-size: 0.9rem;
        max-width: 400px;
        line-height: 1.5;
    }

    /* Skeleton Loading */
    .skeleton-row {
        pointer-events: none;
    }

    .skeleton-box {
        background: linear-gradient(90deg, rgba(255,255,255,0.03) 25%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite linear;
        border-radius: 6px;
    }

    @keyframes shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Responsive Mobile Cards */
    @media (max-width: 960px) {
        .tl-header { display: none; }
        .tl-container { background: transparent; border: none; box-shadow: none; border-radius: 0; }
        .tl-tooltip { display: none !important; }
        
        .tl-row {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 16px;
            margin-bottom: 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .tl-row:last-child { margin-bottom: 0; }
        
        .tl-row:hover { transform: none; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }

        .tl-col-code { order: 1; padding-bottom: 12px; border-bottom: 1px dashed rgba(255,255,255,0.05); }
        .tl-micro-actions { opacity: 1; } /* Always show on mobile */
        
        .tl-col-status { order: 2; align-items: flex-start; margin-top: 4px; }
        
        .tl-col-dest { order: 3; }
        
        .tl-col-finance { order: 4; align-items: flex-start; }
        
        .tl-col-actions { order: 5; align-items: stretch; margin-top: 8px; }
        
        .tl-actions-group {
            justify-content: space-between;
            padding: 8px;
            border-radius: 16px;
            background: rgba(0,0,0,0.2);
        }
        
        .tl-action-btn { width: 44px; height: 44px; border-radius: 12px; background: rgba(255,255,255,0.03); }
    }
`;

export default TrackingList;

