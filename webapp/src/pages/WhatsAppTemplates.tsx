import { useState, useEffect } from 'react';
import axios from 'axios';
import { MessageSquare, Save, RefreshCw, Info, AlertCircle, CheckCircle2 } from 'lucide-react';

const API_BASE = import.meta.env.VITE_API_URL || '';

interface Template {
    slug: string;
    titulo: string;
    mensagem: string;
}

const WhatsAppTemplates = () => {
    const [templates, setTemplates] = useState<Template[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

    const fetchTemplates = async () => {
        setLoading(true);
        try {
            const res = await axios.get(`${API_BASE}/api/admin/whatsapp-templates`);
            setTemplates(res.data);
        } catch (error) {
            console.error('Erro ao buscar templates:', error);
            setMessage({ type: 'error', text: 'Falha ao carregar modelos de mensagens.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchTemplates();
    }, []);

    const handleSave = async () => {
        setSaving(true);
        setMessage(null);
        try {
            await axios.post(`${API_BASE}/api/admin/whatsapp-templates`, { templates });
            setMessage({ type: 'success', text: 'Modelos salvos com sucesso!' });
        } catch (error) {
            console.error('Erro ao salvar templates:', error);
            setMessage({ type: 'error', text: 'Erro ao salvar os modelos.' });
        } finally {
            setSaving(false);
        }
    };

    const updateTemplate = (slug: string, newText: string) => {
        setTemplates(prev => prev.map(t => t.slug === slug ? { ...t, mensagem: newText } : t));
    };

    if (loading) {
        return (
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '300px' }}>
                <RefreshCw className="animate-spin" size={32} color="var(--accent-primary)" />
            </div>
        );
    }

    return (
        <div style={{ padding: '32px', maxWidth: '1000px', margin: '0 auto', animation: 'fadeIn 0.5s ease' }}>
            <header style={{ marginBottom: '40px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                    <h1 style={{ fontSize: '2.5rem', marginBottom: '8px' }}>Modelos de <span className="text-gradient">WhatsApp</span></h1>
                    <p style={{ color: 'var(--text-secondary)' }}>Personalize as mensagens automáticas que seus clientes recebem.</p>
                </div>
                <button
                    onClick={handleSave}
                    disabled={saving}
                    className="btn-primary"
                    style={{ padding: '12px 24px', display: 'flex', alignItems: 'center', gap: '8px' }}
                >
                    {saving ? <RefreshCw className="animate-spin" size={18} /> : <Save size={18} />}
                    {saving ? 'Salvando...' : 'Salvar Alterações'}
                </button>
            </header>

            {message && (
                <div style={{
                    padding: '16px',
                    borderRadius: '12px',
                    marginBottom: '24px',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '12px',
                    background: message.type === 'success' ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)',
                    border: `1px solid ${message.type === 'success' ? 'rgba(34, 197, 94, 0.2)' : 'rgba(239, 68, 68, 0.2)'}`,
                    color: message.type === 'success' ? '#22c55e' : '#ef4444'
                }}>
                    {message.type === 'success' ? <CheckCircle2 size={20} /> : <AlertCircle size={20} />}
                    {message.text}
                </div>
            )}

            <div style={{ display: 'grid', gap: '32px' }}>
                {templates.map(template => (
                    <div key={template.slug} className="glass-panel" style={{ padding: '32px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '20px' }}>
                            <div style={{ padding: '10px', borderRadius: '12px', background: 'var(--accent-glow)' }}>
                                <MessageSquare size={20} color="var(--accent-primary)" />
                            </div>
                            <h3 style={{ margin: 0, fontSize: '1.2rem', fontWeight: 700 }}>{template.titulo}</h3>
                        </div>

                        <div style={{ marginBottom: '16px' }}>
                            <label className="form-label" style={{ marginBottom: '8px', display: 'block' }}>Corpo da Mensagem</label>
                            <textarea
                                className="input-field"
                                style={{ minHeight: '150px', resize: 'vertical', fontFamily: 'monospace', fontSize: '0.9rem', lineHeight: '1.5' }}
                                value={template.mensagem}
                                onChange={(e) => updateTemplate(template.slug, e.target.value)}
                            />
                        </div>

                        <div style={{
                            padding: '16px',
                            background: 'rgba(255,255,255,0.02)',
                            borderRadius: '12px',
                            border: '1px solid rgba(255,255,255,0.05)'
                        }}>
                            <h4 style={{ margin: '0 0 12px 0', fontSize: '0.85rem', color: '#888', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                <Info size={14} /> Variáveis disponíveis:
                            </h4>
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
                                {template.slug === 'rastreio_update' ? (
                                    <>
                                        <VariableBadge name="{codigo}" desc="Código de rastreio" />
                                        <VariableBadge name="{status}" desc="Status atual" />
                                        <VariableBadge name="{subtitulo}" desc="Detalhes do status" />
                                    </>
                                ) : (
                                    <VariableBadge name="{nome}" desc="Nome do cliente" />
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <style>{`
                @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                .text-gradient { background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                .animate-spin { animation: spin 1s linear infinite; }
                @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
            `}</style>
        </div>
    );
};

const VariableBadge = ({ name, desc }: { name: string, desc: string }) => (
    <div style={{
        padding: '6px 12px',
        borderRadius: '8px',
        background: 'rgba(99, 102, 241, 0.05)',
        border: '1px solid rgba(99, 102, 241, 0.1)',
        fontSize: '0.8rem'
    }}>
        <code style={{ color: 'var(--accent-primary)', fontWeight: 700 }}>{name}</code>
        <span style={{ color: '#555', marginLeft: '6px' }}>— {desc}</span>
    </div>
);

export default WhatsAppTemplates;
