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

    // Helper for Info Blocks in Details
    const InfoBlock = ({ label, value, mono, color }: any) => (
        <div>
            <label style={{ display: 'block', fontSize: '0.75rem', fontWeight: 800, color: 'var(--text-secondary)', textTransform: 'uppercase', marginBottom: '4px' }}>{label}</label>
            <div style={{ fontSize: '1rem', fontWeight: 700, color: color || 'var(--text-primary)', fontFamily: mono ? 'JetBrains Mono, monospace' : 'inherit' }}>{value || '-'}</div>
        </div>
    );

    return (
        <>
            {/* MODAL ADICIONAR */}
            <Modal open={modalAdd} onClose={() => setModalAdd(false)} title="Novo Rastreio" icon={<Plus size={18} color="var(--accent-primary)" />}>
                <form onSubmit={handleAdd}>
                    <div className="form-group-mb">
                        <label className="form-label">Código do Objeto *</label>
                        <input className="form-input" placeholder="AA123456789BR" required
                            value={novoForm.codigo}
                            onChange={e => setNovoForm((p: any) => ({ ...p, codigo: e.target.value.toUpperCase() }))}
                            style={{ fontFamily: 'JetBrains Mono, monospace', letterSpacing: '1px' }} />
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                        <div className="form-group-mb">
                            <label className="form-label">Cidade *</label>
                            <input className="form-input" placeholder="São Paulo/SP" required
                                value={novoForm.cidade}
                                onChange={e => setNovoForm((p: any) => ({ ...p, cidade: e.target.value }))} />
                        </div>
                        <div className="form-group-mb">
                            <label className="form-label">Data Postagem *</label>
                            <input type="datetime-local" className="form-input" required
                                value={novoForm.data_inicial}
                                onChange={e => setNovoForm((p: any) => ({ ...p, data_inicial: e.target.value }))} />
                        </div>
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '20px' }}>
                        <div>
                            <label className="form-label">Valor Taxa</label>
                            <input className="form-input" type="number" placeholder="0.00" step="0.01"
                                value={novoForm.taxa_valor}
                                onChange={e => setNovoForm((p: any) => ({ ...p, taxa_valor: e.target.value }))} />
                        </div>
                        <div>
                            <label className="form-label">Chave PIX</label>
                            <input className="form-input" placeholder="Chave..."
                                value={novoForm.taxa_pix}
                                onChange={e => setNovoForm((p: any) => ({ ...p, taxa_pix: e.target.value }))} />
                        </div>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                        <button type="button" onClick={() => setModalAdd(false)} className="btn-secondary">Cancelar</button>
                        <button type="submit" className="btn-primary">Criar Rastreio</button>
                    </div>
                </form>
            </Modal>

            {/* MODAL EDITAR */}
            <Modal open={modalEdit} onClose={() => setModalEdit(false)} title="Editar Rastreio" icon={<Edit size={18} color="#f59e0b" />}>
                {editData && (
                    <form onSubmit={handleEdit}>
                        <div className="form-group-mb">
                            <label className="form-label">Cidade *</label>
                            <input className="form-input" required value={editData.cidade}
                                onChange={e => setEditData((p: any) => p ? ({ ...p, cidade: e.target.value }) : p)} />
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '20px' }}>
                            <div>
                                <label className="form-label">Taxa</label>
                                <input className="form-input" type="number" step="0.01"
                                    value={editData.taxa_valor || ''}
                                    onChange={e => setEditData((p: any) => p ? ({ ...p, taxa_valor: e.target.value }) : p)} />
                            </div>
                            <div>
                                <label className="form-label">PIX</label>
                                <input className="form-input" value={editData.taxa_pix || ''}
                                    onChange={e => setEditData((p: any) => p ? ({ ...p, taxa_pix: e.target.value }) : p)} />
                            </div>
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                            <button type="button" onClick={() => setModalEdit(false)} className="btn-secondary">Cancelar</button>
                            <button type="submit" className="btn-primary" style={{ background: '#f59e0b' }}><Save size={16} /> Salvar</button>
                        </div>
                    </form>
                )}
            </Modal>

            {/* MODAL DETALHES */}
            <Modal open={modalDetails} onClose={() => setModalDetails(false)} title="Detalhes" icon={<Eye size={20} color="var(--accent-primary)" />}>
                {detailsData && (
                    <div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', marginBottom: '24px', background: 'rgba(255,255,255,0.03)', padding: '16px', borderRadius: '12px' }}>
                            <InfoBlock label="Código" value={detailsData.codigo} mono />
                            <InfoBlock label="Cidade" value={detailsData.cidade} />
                            {detailsData.taxa_valor > 0 && <InfoBlock label="Taxa" value={`R$ ${detailsData.taxa_valor}`} color="#f59e0b" />}
                        </div>
                        <div style={{ display: 'flex', gap: '10px' }}>
                            <button onClick={() => { setModalDetails(false); abrirEdicao(detailsData.codigo); }} className="btn-secondary" style={{ flex: 1 }}><Edit size={16} /> Editar</button>
                            <button onClick={() => { enviarWhatsapp(detailsData.codigo); setModalDetails(false); }} className="btn-primary" style={{ flex: 1.5, background: '#10b981' }}><MessageCircle size={16} /> WhatsApp</button>
                        </div>
                    </div>
                )}
            </Modal>
        </>
    );
};

export default TrackingModals;
