import { useState, useEffect } from 'react';
import { Shield, Globe, Database, Save, User, Key, Percent, RefreshCw } from 'lucide-react';
import axios from 'axios';

const API_BASE = import.meta.env.VITE_API_URL || '';

const Settings = () => {
    const [activeTab, setActiveTab] = useState('geral');
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);

    // Config State
    const [config, setConfig] = useState<any>({
        centavos_aleatorios: 'true',
        nome_empresa: 'Loggi Rastreamento'
    });

    useEffect(() => {
        fetchConfig();
    }, []);

    const fetchConfig = async () => {
        setLoading(true);
        try {
            const res = await axios.get(`${API_BASE}/api/admin/config`);
            if (res.data.success) {
                setConfig(res.data.config);
            }
        } catch (error) {
            console.error('Erro ao buscar config:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleSave = async (updates: any) => {
        setSaving(true);
        try {
            const res = await axios.post(`${API_BASE}/api/admin/config`, updates);
            if (res.data.success) {
                alert('Configurações salvas com sucesso!');
                fetchConfig();
            }
        } catch (error) {
            alert('Erro ao salvar configurações.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div style={{ padding: '32px', maxWidth: '1200px', margin: '0 auto', animation: 'fadeIn 0.5s ease' }}>
            <header style={{ marginBottom: '40px' }}>
                <h1 style={{ fontSize: '2.5rem', fontWeight: 900, marginBottom: '8px', letterSpacing: '-1.5px', fontFamily: 'Outfit, sans-serif' }}>
                    Configurações do <span className="text-gradient">Sistema</span>
                </h1>
                <p style={{ color: '#64748b', fontSize: '1.1rem' }}>Personalize as preferências do painel e segurança da sua conta.</p>
            </header>

            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(250px, 0.3fr) 1fr', gap: '32px' }}>
                {/* Menu Lateral */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                    <TabButton icon={<Globe size={18} />} label="Geral" active={activeTab === 'geral'} onClick={() => setActiveTab('geral')} />
                    <TabButton icon={<User size={18} />} label="Meu Perfil" active={activeTab === 'perfil'} onClick={() => setActiveTab('perfil')} />
                    <TabButton icon={<Percent size={18} />} label="Taxas e Pagamentos" active={activeTab === 'taxas'} onClick={() => setActiveTab('taxas')} />
                    <TabButton icon={<Database size={18} />} label="API & Integrações" active={activeTab === 'api'} onClick={() => setActiveTab('api')} />
                    <TabButton icon={<Shield size={18} />} label="Segurança" active={activeTab === 'seguranca'} onClick={() => setActiveTab('seguranca')} />
                </div>

                {/* Conteúdo Principal */}
                <div style={{
                    background: 'rgba(255,255,255,0.01)',
                    border: '1px solid rgba(255,255,255,0.06)',
                    borderRadius: '28px',
                    padding: '40px',
                    backdropFilter: 'blur(20px)',
                    boxShadow: '0 8px 32px rgba(0,0,0,0.2)'
                }}>
                    {loading ? (
                        <div style={{ padding: '48px', textAlign: 'center', color: '#64748b' }}>
                            <RefreshCw size={32} className="spin" style={{ marginBottom: '16px' }} />
                            <p>Carregando configurações...</p>
                        </div>
                    ) : (
                        <>
                            {activeTab === 'geral' && (
                                <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                    <h3 style={sectionTitle}>Preferências do Painel</h3>
                                    <div style={formGrid}>
                                        <div className="form-group">
                                            <label className="form-label">Nome da Empresa</label>
                                            <input
                                                className="input-field-premium"
                                                value={config.nome_empresa || ''}
                                                onChange={e => setConfig({ ...config, nome_empresa: e.target.value })}
                                            />
                                        </div>
                                    </div>
                                    <SaveButton onClick={() => handleSave({ nome_empresa: config.nome_empresa })} loading={saving} />
                                </div>
                            )}

                            {activeTab === 'taxas' && (
                                <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                    <h3 style={sectionTitle}>Taxas e Pagamentos</h3>
                                    <p style={{ color: '#64748b', fontSize: '0.95rem', marginBottom: '24px' }}>
                                        Configure o comportamento dos valores de cobrança no site.
                                    </p>

                                    <div style={{
                                        padding: '24px',
                                        background: 'rgba(99, 102, 241, 0.05)',
                                        border: '1px solid rgba(99, 102, 241, 0.15)',
                                        borderRadius: '20px',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        gap: '20px'
                                    }}>
                                        <div>
                                            <h4 style={{ margin: '0 0 6px 0', fontSize: '1.1rem' }}>Centavos Aleatórios</h4>
                                            <p style={{ margin: 0, color: '#64748b', fontSize: '0.9rem' }}>
                                                Adiciona centavos aleatórios (ex: R$ 29,47) para facilitar a identificação dos pagamentos PIX.
                                            </p>
                                        </div>
                                        <label className="switch">
                                            <input
                                                type="checkbox"
                                                checked={config.centavos_aleatorios === 'true'}
                                                onChange={e => {
                                                    const val = e.target.checked ? 'true' : 'false';
                                                    setConfig({ ...config, centavos_aleatorios: val });
                                                    handleSave({ centavos_aleatorios: val });
                                                }}
                                            />
                                            <span className="slider round"></span>
                                        </label>
                                    </div>
                                </div>
                            )}

                            {activeTab === 'perfil' && (
                                <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                    <h3 style={sectionTitle}>Meu Perfil</h3>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '24px', marginBottom: '32px' }}>
                                        <div style={{ width: '80px', height: '80px', borderRadius: '24px', background: 'rgba(99, 102, 241, 0.15)', border: '1px solid #6366f1', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '2rem', fontWeight: 900, color: '#fff' }}>
                                            A
                                        </div>
                                        <div>
                                            <button className="btn-secondary" style={{ padding: '8px 16px', fontSize: '0.85rem' }}>Alterar Foto</button>
                                            <p style={{ fontSize: '0.8rem', color: '#64748b', marginTop: '8px' }}>JPG ou PNG de até 2MB.</p>
                                        </div>
                                    </div>
                                    <div style={formGrid}>
                                        <div className="form-group"><label className="form-label">Nome de Exibição</label><input className="input-field-premium" defaultValue="Admin Principal" /></div>
                                        <div className="form-group"><label className="form-label">E-mail</label><input className="input-field-premium" defaultValue="admin@loggi.com" /></div>
                                    </div>
                                </div>
                            )}

                            {activeTab === 'api' && (
                                <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                    <h3 style={sectionTitle}>Configurações de API</h3>
                                    <p style={{ color: '#64748b', fontSize: '0.9rem', marginBottom: '24px' }}>Integração com gateway de pagamento e bot de WhatsApp.</p>

                                    <div className="form-group">
                                        <label className="form-label">WhatsApp API URL</label>
                                        <input className="input-field-premium" defaultValue="http://localhost:3000" />
                                    </div>
                                    <div className="form-group">
                                        <label className="form-label">API Token</label>
                                        <div style={{ position: 'relative' }}>
                                            <input type="password" title="token" className="input-field-premium" defaultValue="lucastav8012891283" style={{ paddingRight: '48px' }} />
                                            <Key size={18} style={{ position: 'absolute', right: '16px', top: '50%', transform: 'translateY(-50%)', color: '#444' }} />
                                        </div>
                                    </div>
                                </div>
                            )}

                            {activeTab === 'seguranca' && (
                                <div style={{ animation: 'fadeIn 0.3s ease' }}>
                                    <h3 style={sectionTitle}>Segurança</h3>
                                    <div className="form-group">
                                        <label className="form-label">Nova Senha</label>
                                        <input type="password" title="password" className="input-field-premium" />
                                    </div>
                                    <div className="form-group">
                                        <label className="form-label">Confirmar Nova Senha</label>
                                        <input type="password" title="confirm_password" className="input-field-premium" />
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>

            <style>{`
                @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
                .spin { animation: spin 2s linear infinite; }
                .text-gradient { background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                
                .input-field-premium {
                    width: 100%; padding: 14px 18px; background: rgba(255,255,255,0.03);
                    border: 1px solid rgba(255,255,255,0.08); border-radius: 14px;
                    color: #fff; font-size: 0.95rem; outline: none; transition: all 0.3s;
                }
                .input-field-premium:focus {
                    border-color: #6366f1; background: rgba(99, 102, 241, 0.05);
                    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
                }
                .form-label { display: block; margin-bottom: 8px; color: #94a3b8; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
                .form-group { margin-bottom: 24px; }
                
                .btn-primary { 
                    padding: 14px 28px; background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff;
                    border: none; border-radius: 14px; cursor: pointer; font-weight: 800; display: flex; align-items: center; gap: 8px;
                    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3); transition: 0.3s;
                }
                .btn-primary:active { transform: scale(0.98); }
                .btn-secondary {
                    padding: 10px 20px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
                    border-radius: 12px; color: #fff; cursor: pointer; font-weight: 600;
                }

                /* Switch */
                .switch { position: relative; display: inline-block; width: 50px; height: 26px; flex-shrink: 0; }
                .switch input { opacity: 0; width: 0; height: 0; }
                .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255,255,255,0.1); transition: .4s; }
                .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .4s; }
                input:checked + .slider { background-color: #6366f1; }
                input:checked + .slider:before { transform: translateX(24px); }
                .slider.round { border-radius: 34px; }
                .slider.round:before { border-radius: 50%; }
            `}</style>
        </div>
    );
};

const TabButton = ({ icon, label, active, onClick }: any) => (
    <button
        onClick={onClick}
        style={{
            display: 'flex', alignItems: 'center', gap: '12px', padding: '16px 20px', borderRadius: '16px',
            background: active ? 'rgba(99, 102, 241, 0.1)' : 'transparent',
            color: active ? '#fff' : '#64748b',
            border: active ? '1px solid rgba(99, 102, 241, 0.3)' : '1px solid transparent',
            cursor: 'pointer', textAlign: 'left', fontWeight: 700, fontSize: '0.95rem', transition: 'all 0.3s'
        }}
    >
        {icon}
        {label}
    </button>
);

const SaveButton = ({ onClick, loading }: any) => (
    <button className="btn-primary" onClick={onClick} disabled={loading} style={{ marginTop: '24px' }}>
        {loading ? <RefreshCw size={18} className="spin" /> : <Save size={18} />}
        {loading ? 'Salvando...' : 'Salvar Alterações'}
    </button>
);

const sectionTitle = { fontSize: '1.4rem', fontWeight: 900, marginBottom: '24px', borderBottom: '1px solid rgba(255,255,255,0.05)', paddingBottom: '16px', color: '#fff', fontFamily: 'Outfit, sans-serif' };
const formGrid = { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '24px' };

export default Settings;
