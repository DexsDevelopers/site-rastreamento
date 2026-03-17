import { Plus, Edit, Eye, Save, MessageCircle } from 'lucide-react';
import Modal from '../Modal';

interface TrackingModalsProps {
    modalAdd: boolean;
    setModalAdd: (v: boolean) => void;
    modalEdit: boolean;
    setModalEdit: (v: boolean) => void;
    modalDetails: boolean;
    setModalDetails: (v: boolean) => void;

    novoForm: any;
    setNovoForm: React.Dispatch<React.SetStateAction<any>>;
    handleAdd: (e: React.FormEvent) => void;

    editData: any;
    setEditData: React.Dispatch<React.SetStateAction<any>>;
    handleEdit: (e: React.FormEvent) => void;

    detailsData: any;
    enviarWhatsapp: (code: string) => void;
    abrirEdicao: (code: string) => void;

    ETAPAS_MAP: Record<string, string>;
}

const TrackingModals: React.FC<TrackingModalsProps> = (props) => {
    const {
        modalAdd, setModalAdd, modalEdit, setModalEdit, modalDetails, setModalDetails,
        novoForm, setNovoForm, handleAdd,
        editData, setEditData, handleEdit,
        detailsData, enviarWhatsapp, abrirEdicao,
        ETAPAS_MAP
    } = props;

    const modalStyles = `
        .saas-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .saas-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .saas-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #94a3b8;
            letter-spacing: 0.02em;
        }
        .saas-input {
            background: #111827;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 12px 16px;
            color: #fff;
            font-size: 0.95rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
        }
        .saas-input:focus {
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
            background: #1e293b;
        }
        .saas-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 12px;
        }
        .btn-saas-secondary {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #94a3b8;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-saas-secondary:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }
        .btn-saas-primary {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            border: none;
            color: white;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        .btn-saas-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
        }
        .btn-saas-primary:active {
            transform: translateY(0);
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            background: rgba(255, 255, 255, 0.02);
            padding: 24px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .info-card {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .info-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.05em;
        }
        .info-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #f8fafc;
        }
        .btn-toggle-saas {
            flex: 1; padding: 10px; border-radius: 8px; fontSize: 0.8rem; fontWeight: 700;
            cursor: pointer; transition: all 0.2s; border: 1px solid;
            text-align: center;
        }
        .btn-toggle-saas.active {
            background: #2563EB; border-color: #3b82f6; color: #fff;
        }
        .btn-toggle-saas.inactive {
            background: #111827; border-color: rgba(255,255,255,0.05); color: #94a3b8;
        }
        .btn-toggle-edit.active {
            background: #F59E0B; border-color: #fbbf24; color: #fff;
        }
    `;

    return (
        <>
            <style>{modalStyles}</style>

            {/* MODAL ADICIONAR */}
            <Modal open={modalAdd} onClose={() => setModalAdd(false)} title="Novo Rastreio" icon={<Plus size={20} color="#2563EB" />}>
                <form onSubmit={handleAdd} className="saas-form">
                    <div className="saas-field">
                        <label className="saas-label">Código do Objeto</label>
                        <input className="saas-input" placeholder="EX: AA123456789BR" required
                            value={novoForm.codigo}
                            onChange={e => setNovoForm((p: any) => ({ ...p, codigo: e.target.value.toUpperCase() }))}
                            style={{ fontFamily: 'JetBrains Mono, monospace', letterSpacing: '1px' }} />
                    </div>

                    <div className="saas-field">
                        <label className="saas-label">Cidade de Destino</label>
                        <input className="saas-input" placeholder="São Paulo / SP" required
                            value={novoForm.cidade}
                            onChange={e => setNovoForm((p: any) => ({ ...p, cidade: e.target.value }))} />
                    </div>

                    <div className="saas-field">
                        <label className="saas-label">Tipo de Entrega</label>
                        <div style={{ display: 'flex', gap: '10px' }}>
                            {['NORMAL', 'EXPRESS'].map(t => (
                                <button key={t} type="button"
                                    onClick={() => setNovoForm((p: any) => ({ ...p, tipo_entrega: t }))}
                                    className={`btn-toggle-saas ${novoForm.tipo_entrega === t ? 'active' : 'inactive'}`}>
                                    {t === 'NORMAL' ? 'Standard (5 dias)' : 'Express (3 dias)'}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                        <div className="saas-field">
                            <label className="saas-label">Valor da Taxa (R$)</label>
                            <input className="saas-input" type="number" placeholder="0,00" step="0.01"
                                value={novoForm.taxa_valor}
                                onChange={e => setNovoForm((p: any) => ({ ...p, taxa_valor: e.target.value }))} />
                        </div>
                        <div className="saas-field">
                            <label className="saas-label">Chave PIX</label>
                            <input className="saas-input" placeholder="Chave para cobrança"
                                value={novoForm.taxa_pix}
                                onChange={e => setNovoForm((p: any) => ({ ...p, taxa_pix: e.target.value }))} />
                        </div>
                    </div>

                    <div className="saas-actions">
                        <button type="button" onClick={() => setModalAdd(false)} className="btn-saas-secondary">Cancelar</button>
                        <button type="submit" className="btn-saas-primary">Criar Rastreio</button>
                    </div>
                </form>
            </Modal>

            {/* MODAL EDITAR */}
            <Modal open={modalEdit} onClose={() => setModalEdit(false)} title="Editar Rastreio" icon={<Edit size={20} color="#F59E0B" />}>
                {editData && (
                    <form onSubmit={handleEdit} className="saas-form">
                        <div style={{ background: 'rgba(37, 99, 235, 0.1)', padding: '12px 16px', borderRadius: '12px', border: '1px solid rgba(37, 99, 235, 0.2)', marginBottom: '4px' }}>
                            <span className="saas-label" style={{ color: '#60a5fa' }}>Código do Objeto</span>
                            <div style={{ fontSize: '1.2rem', fontWeight: 800, color: '#fff', fontFamily: 'JetBrains Mono, monospace' }}>{editData.codigo}</div>
                        </div>

                        <div className="saas-field">
                            <label className="saas-label">Cidade de Destino</label>
                            <input className="saas-input" required value={editData.cidade}
                                onChange={e => setEditData((p: any) => p ? ({ ...p, cidade: e.target.value }) : p)} />
                        </div>

                        <div className="saas-field">
                            <label className="saas-label">Tipo de Entrega</label>
                            <div style={{ display: 'flex', gap: '10px' }}>
                                {['NORMAL', 'EXPRESS'].map(t => (
                                    <button key={t} type="button"
                                        onClick={() => setEditData((p: any) => ({ ...p, tipo_entrega: t }))}
                                        className={`btn-toggle-saas ${editData.tipo_entrega === t ? 'active btn-toggle-edit' : 'inactive'}`}>
                                        {t === 'NORMAL' ? 'Standard (5 dias)' : 'Express (3 dias)'}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                            <div className="saas-field">
                                <label className="saas-label">Valor da Taxa (R$)</label>
                                <input className="saas-input" type="number" step="0.01"
                                    value={editData.taxa_valor || ''}
                                    onChange={e => setEditData((p: any) => p ? ({ ...p, taxa_valor: e.target.value }) : p)} />
                            </div>

                            <div className="saas-field">
                                <label className="saas-label">Chave PIX</label>
                                <input className="saas-input" value={editData.taxa_pix || ''}
                                    onChange={e => setEditData((p: any) => p ? ({ ...p, taxa_pix: e.target.value }) : p)} />
                            </div>
                        </div>

                        <div className="saas-field">
                            <label className="saas-label">Gerenciar Etapas</label>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
                                {Object.entries(ETAPAS_MAP).map(([key, label]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        onClick={() => {
                                            const jaTem = editData.etapas.includes(label);
                                            const novas = jaTem
                                                ? editData.etapas.filter((x: string) => x !== label)
                                                : [...editData.etapas, label];
                                            setEditData((p: any) => ({ ...p, etapas: novas }));
                                        }}
                                        style={{
                                            padding: '10px',
                                            borderRadius: '8px',
                                            fontSize: '0.8rem',
                                            fontWeight: 600,
                                            textAlign: 'left',
                                            cursor: 'pointer',
                                            transition: 'all 0.2s',
                                            background: editData.etapas.includes(label) ? '#2563EB' : '#111827',
                                            border: '1px solid',
                                            borderColor: editData.etapas.includes(label) ? '#3b82f6' : 'rgba(255,255,255,0.05)',
                                            color: editData.etapas.includes(label) ? '#fff' : '#94a3b8'
                                        }}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                            <small style={{ color: '#64748b', marginTop: '4px' }}>Clique para ativar/desativar as etapas que o cliente verá.</small>
                        </div>

                        <div className="saas-actions">
                            <button type="button" onClick={() => setModalEdit(false)} className="btn-saas-secondary">Cancelar</button>
                            <button type="submit" className="btn-saas-primary" style={{ background: 'linear-gradient(135deg, #F59E0B, #D97706)' }}>
                                <Save size={18} /> Salvar Alterações
                            </button>
                        </div>
                    </form>
                )}
            </Modal>

            {/* MODAL DETALHES */}
            <Modal open={modalDetails} onClose={() => setModalDetails(false)} title="Detalhes do Objeto" icon={<Eye size={20} color="#2563EB" />}>
                {detailsData && (
                    <div className="saas-form">
                        <div className="details-grid">
                            <div className="info-card">
                                <span className="info-label">Identificador Único</span>
                                <span className="info-value" style={{ fontFamily: 'JetBrains Mono, monospace', color: '#2563EB' }}>{detailsData.codigo}</span>
                            </div>
                            <div className="info-card">
                                <span className="info-label">Cidade de Destino</span>
                                <span className="info-value">{detailsData.cidade}</span>
                            </div>
                            <div className="info-card">
                                <span className="info-label">Modalidade de Entrega</span>
                                <span className="info-value">{detailsData.tipo_entrega === 'EXPRESS' ? '🚀 Express (3 dias)' : '📦 Standard (5 dias)'}</span>
                            </div>
                            {Number(detailsData.taxa_valor) > 0 && (
                                <div className="info-card">
                                    <span className="info-label">Taxa de Importação</span>
                                    <span className="info-value" style={{ color: '#F59E0B' }}>R$ {detailsData.taxa_valor}</span>
                                </div>
                            )}
                        </div>

                        <div className="saas-actions" style={{ gap: '16px' }}>
                            <button onClick={() => { setModalDetails(false); abrirEdicao(detailsData.codigo); }} className="btn-saas-secondary" style={{ flex: 1 }}>
                                <Edit size={18} /> Editar
                            </button>
                            <button onClick={() => { enviarWhatsapp(detailsData.codigo); setModalDetails(false); }} className="btn-saas-primary" style={{ flex: 1.5, background: '#22C55E' }}>
                                <MessageCircle size={18} /> Notificar Cliente
                            </button>
                        </div>
                    </div>
                )}
            </Modal>
        </>
    );
};

export default TrackingModals;
