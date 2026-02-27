import { useState } from 'react';
import { Bell, Shield, Globe, Database, Save, User, Key } from 'lucide-react';

const Settings = () => {
    const [activeTab, setActiveTab] = useState('geral');

    return (
        <div style={{ padding: '32px', maxWidth: '1000px', margin: '0 auto', animation: 'fadeIn 0.5s ease' }}>
            <header style={{ marginBottom: '40px' }}>
                <h1 style={{ fontSize: '2.5rem', marginBottom: '8px' }}>Configurações do <span className="text-gradient">Sistema</span></h1>
                <p style={{ color: 'var(--text-secondary)' }}>Personalize as preferências do painel e segurança da sua conta.</p>
            </header>

            <div style={{ display: 'grid', gridTemplateColumns: '250px 1fr', gap: '32px' }}>
                {/* Menu Lateral de Configurações */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                    <TabButton icon={<Globe size={18} />} label="Geral" active={activeTab === 'geral'} onClick={() => setActiveTab('geral')} />
                    <TabButton icon={<User size={18} />} label="Meu Perfil" active={activeTab === 'perfil'} onClick={() => setActiveTab('perfil')} />
                    <TabButton icon={<Bell size={18} />} label="Notificações" active={activeTab === 'notificacoes'} onClick={() => setActiveTab('notificacoes')} />
                    <TabButton icon={<Shield size={18} />} label="Segurança" active={activeTab === 'seguranca'} onClick={() => setActiveTab('seguranca')} />
                    <TabButton icon={<Database size={18} />} label="API & Integrações" active={activeTab === 'api'} onClick={() => setActiveTab('api')} />
                </div>

                {/* Conteúdo Principal */}
                <div className="glass-panel" style={{ padding: '32px' }}>
                    {activeTab === 'geral' && (
                        <div style={{ animation: 'fadeIn 0.3s ease' }}>
                            <h3 style={sectionTitle}>Preferências do Painel</h3>
                            <div style={formGrid}>
                                <div className="form-group">
                                    <label className="form-label">Nome da Empresa</label>
                                    <input className="input-field" defaultValue="Loggi Rastreamento" />
                                </div>
                                <div className="form-group">
                                    <label className="form-label">Idioma</label>
                                    <select className="input-field" style={{ background: '#111', color: '#fff' }}>
                                        <option>Português (Brasil)</option>
                                        <option>English</option>
                                        <option>Español</option>
                                    </select>
                                </div>
                                <div className="form-group">
                                    <label className="form-label">Fuso Horário</label>
                                    <select className="input-field" style={{ background: '#111', color: '#fff' }}>
                                        <option>Brasília (GMT-3)</option>
                                        <option>UTC (GMT+0)</option>
                                    </select>
                                </div>
                            </div>
                            <button className="btn-primary" style={{ marginTop: '24px', padding: '12px 24px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <Save size={18} /> Salvar Alterações
                            </button>
                        </div>
                    )}

                    {activeTab === 'perfil' && (
                        <div style={{ animation: 'fadeIn 0.3s ease' }}>
                            <h3 style={sectionTitle}>Meu Perfil</h3>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '24px', marginBottom: '32px' }}>
                                <div style={{ width: '80px', height: '80px', borderRadius: '24px', background: 'var(--accent-glow)', border: '1px solid var(--accent-primary)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '2rem' }}>
                                    A
                                </div>
                                <div>
                                    <button className="glass-panel" style={{ padding: '8px 16px', fontSize: '0.85rem', cursor: 'pointer' }}>Alterar Foto</button>
                                    <p style={{ fontSize: '0.8rem', color: '#555', marginTop: '8px' }}>JPG ou PNG de até 2MB.</p>
                                </div>
                            </div>
                            <div style={formGrid}>
                                <div className="form-group"><label className="form-label">Nome de Exibição</label><input className="input-field" defaultValue="Admin Principal" /></div>
                                <div className="form-group"><label className="form-label">E-mail</label><input className="input-field" defaultValue="admin@loggi.com" /></div>
                            </div>
                        </div>
                    )}

                    {activeTab === 'api' && (
                        <div style={{ animation: 'fadeIn 0.3s ease' }}>
                            <h3 style={sectionTitle}>Configurações de API</h3>
                            <p style={{ color: '#666', fontSize: '0.9rem', marginBottom: '24px' }}>Estas chaves são usadas para comunicação entre o backend e o bot do WhatsApp.</p>

                            <div className="form-group">
                                <label className="form-label">WhatsApp API URL</label>
                                <input className="input-field" placeholder="https://api.seuservidor.com" defaultValue="https://transloggi.site" />
                            </div>
                            <div className="form-group">
                                <label className="form-label">API Token</label>
                                <div style={{ position: 'relative' }}>
                                    <input type="password" title="token" className="input-field" defaultValue="lucastav8012891283" style={{ paddingRight: '48px' }} />
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
                                <input type="password" title="password" className="input-field" />
                            </div>
                            <div className="form-group">
                                <label className="form-label">Confirmar Nova Senha</label>
                                <input type="password" title="confirm_password" className="input-field" />
                            </div>
                            <div style={{ marginTop: '24px', padding: '16px', borderRadius: '12px', background: 'rgba(239, 68, 68, 0.05)', border: '1px solid rgba(239, 68, 68, 0.1)' }}>
                                <label className="checkbox-row" style={{ color: 'var(--danger)' }}>
                                    <input type="checkbox" style={{ accentColor: 'var(--danger)' }} />
                                    Exigir autenticação de dois fatores (2FA) em cada login.
                                </label>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <style>{`
                @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                .text-gradient { background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
            `}</style>
        </div>
    );
};

const TabButton = ({ icon, label, active, onClick }: any) => (
    <button
        onClick={onClick}
        style={{
            display: 'flex', alignItems: 'center', gap: '12px', padding: '14px 20px', borderRadius: '12px',
            background: active ? 'var(--accent-glow)' : 'transparent',
            color: active ? 'var(--text-primary)' : 'var(--text-secondary)',
            border: active ? '1px solid var(--accent-primary)' : '1px solid transparent',
            cursor: 'pointer', textAlign: 'left', fontWeight: 600, transition: 'all 0.3s'
        }}
    >
        {icon}
        {label}
    </button>
);

const sectionTitle = { fontSize: '1.2rem', fontWeight: 700, marginBottom: '24px', borderBottom: '1px solid rgba(255,255,255,0.05)', paddingBottom: '12px' };
const formGrid = { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' };

export default Settings;
