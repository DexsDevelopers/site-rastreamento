import React, { useState } from 'react';
import { Package, User, ChevronRight, LogOut, Share2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import Header from '../components/Header';
import Footer from '../components/Footer';

const Profile: React.FC = () => {
    const navigate = useNavigate();
    const [activeTab, setActiveTab] = useState<'perfil' | 'pedidos' | 'rastreios'>('perfil');

    // Mock User Data (In reality, fetch from useAuth)
    const user = JSON.parse(localStorage.getItem('loggi_user_session') || '{"nome": "Usuário", "email": "usuario@exemplo.com", "telefone": "(11) 99999-9999"}');

    const handleLogout = () => {
        localStorage.removeItem('loggi_user_session');
        navigate('/');
    };

    const orders = [
        { id: 'ORD-77291', status: 'Entregue', data: '12/02/2026', valor: 'R$ 45,90', item: 'Smartphone G Pro' },
        { id: 'ORD-88122', status: 'Em trânsito', data: '26/02/2026', valor: 'R$ 12,50', item: 'Fone Bluetooth' },
    ];

    const tracks = [
        { id: 'GH56YJ14118BR', local: 'Belém, PA', status: 'Saiu para entrega' },
        { id: 'BR991223451LP', local: 'São Paulo, SP', status: 'Postado' },
    ];

    return (
        <div className="pr-page">
            <style>{`
                .pr-page { background: #06060b; color: #fff; min-height: 100vh; font-family: 'Outfit', sans-serif; padding-top: 120px; padding-bottom: 60px; }
                .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; display: grid; grid-template-columns: 340px 1fr; gap: 40px; }
                
                .side-card { background: rgba(255,255,255,0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.06); border-radius: 32px; padding: 40px 32px; height: fit-content; position: sticky; top: 120px; }
                .main-content-card { background: rgba(255,255,255,0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.06); border-radius: 32px; padding: 48px; min-height: 600px; }
                
                .profile-pic { width: 90px; height: 90px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 28px; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; margin: 0 auto 24px; box-shadow: 0 12px 32px rgba(99, 102, 241, 0.3); color: white; }
                
                .nav-menu { display: flex; flex-direction: column; gap: 10px; margin-top: 40px; }
                .nav-btn { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border: none; background: transparent; color: rgba(255,255,255,0.5); border-radius: 16px; cursor: pointer; transition: 0.3s; font-weight: 600; text-align: left; width: 100%; }
                .nav-btn:hover { background: rgba(255,255,255,0.04); color: white; }
                .nav-btn.active { background: rgba(99, 102, 241, 0.1); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.2); }
                
                .logout-btn { margin-top: 40px; color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.1); }
                .logout-btn:hover { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
                
                .item-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 20px; padding: 24px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; transition: 0.3s; cursor: pointer; }
                .item-card:hover { border-color: rgba(99, 102, 241, 0.3); background: rgba(255,255,255,0.05); transform: translateY(-2px); }
                
                @media (max-width: 1024px) {
                    .container { grid-template-columns: 1fr; gap: 24px; padding-top: 20px; }
                    .side-card { position: relative; top: 0; padding: 32px; width: 100%; }
                    .main-content-card { padding: 32px; width: 100%; }
                    .nav-menu { flex-direction: row; flex-wrap: wrap; justify-content: center; }
                    .nav-btn { width: auto; flex: 1; min-width: 150px; justify-content: center; }
                }

                @media (max-width: 640px) {
                    .nav-btn { min-width: 100%; }
                    .item-card { flex-direction: column; align-items: flex-start; gap: 20px; }
                    .item-card > div:last-child { text-align: left; width: 100%; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 16px; }
                }
            `}</style>

            <Header />

            <div className="container">
                <div className="side-card">
                    <div className="profile-pic">{user.nome[0].toUpperCase()}</div>
                    <div style={{ textAlign: 'center' }}>
                        <h2 style={{ fontSize: '1.4rem' }}>{user.nome}</h2>
                        <p style={{ color: 'rgba(255,255,255,0.4)', fontSize: '0.9rem' }}>{user.email}</p>
                    </div>

                    <div className="nav-menu">
                        <button className={`nav-btn ${activeTab === 'perfil' ? 'active' : ''}`} onClick={() => setActiveTab('perfil')}>
                            <User size={20} /> Meus Dados
                        </button>
                        <button className={`nav-btn ${activeTab === 'pedidos' ? 'active' : ''}`} onClick={() => setActiveTab('pedidos')}>
                            <Package size={20} /> Meus Pedidos
                        </button>
                        <button className={`nav-btn ${activeTab === 'rastreios' ? 'active' : ''}`} onClick={() => setActiveTab('rastreios')}>
                            <Share2 size={20} /> Rastreamentos
                        </button>
                        <button className="nav-btn logout-btn" onClick={handleLogout}>
                            <LogOut size={20} /> Sair da Conta
                        </button>
                    </div>
                </div>

                <div className="main-content-card">
                    {activeTab === 'perfil' && (
                        <div>
                            <h2 style={{ fontSize: '1.8rem', marginBottom: '8px' }}>Meus Dados</h2>
                            <p style={{ color: 'rgba(255,255,255,0.4)', marginBottom: '32px' }}>Gerencie suas informações de acesso e segurança.</p>

                            <div style={{ display: 'grid', gap: '20px' }}>
                                <div style={{ background: 'rgba(255,255,255,0.02)', padding: '24px', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.06)' }}>
                                    <div style={{ color: 'rgba(255,255,255,0.3)', fontSize: '0.8rem', marginBottom: '4px' }}>NOME COMPLETO</div>
                                    <div style={{ fontWeight: 700 }}>{user.nome}</div>
                                </div>
                                <div style={{ background: 'rgba(255,255,255,0.02)', padding: '24px', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.06)' }}>
                                    <div style={{ color: 'rgba(255,255,255,0.3)', fontSize: '0.8rem', marginBottom: '4px' }}>E-MAIL</div>
                                    <div style={{ fontWeight: 700 }}>{user.email}</div>
                                </div>
                                <div style={{ background: 'rgba(255,255,255,0.02)', padding: '24px', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.06)' }}>
                                    <div style={{ color: 'rgba(255,255,255,0.3)', fontSize: '0.8rem', marginBottom: '4px' }}>WHATSAPP</div>
                                    <div style={{ fontWeight: 700 }}>{user.telefone}</div>
                                </div>
                            </div>
                        </div>
                    )}

                    {activeTab === 'pedidos' && (
                        <div>
                            <h2 style={{ fontSize: '1.8rem', marginBottom: '8px' }}>Meus Pedidos</h2>
                            <p style={{ color: 'rgba(255,255,255,0.4)', marginBottom: '32px' }}>Histórico completo de encomendas entregues e pendentes.</p>
                            {orders.map(o => (
                                <div key={o.id} className="item-card">
                                    <div>
                                        <div style={{ color: '#818cf8', fontWeight: 800, fontSize: '0.8rem' }}>{o.id}</div>
                                        <div style={{ fontSize: '1.1rem', fontWeight: 700, margin: '4px 0' }}>{o.item}</div>
                                        <div style={{ fontSize: '0.85rem', color: 'rgba(255,255,255,0.4)' }}>Solicitado em {o.data}</div>
                                    </div>
                                    <div style={{ textAlign: 'right' }}>
                                        <div style={{ background: o.status.includes('Entregue') ? 'rgba(16, 185, 129, 0.1)' : 'rgba(99, 102, 241, 0.1)', color: o.status.includes('Entregue') ? '#10b981' : '#818cf8', padding: '6px 12px', borderRadius: '8px', fontSize: '0.8rem', fontWeight: 800, marginBottom: '8px' }}>{o.status}</div>
                                        <div style={{ fontWeight: 800 }}>{o.valor}</div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {activeTab === 'rastreios' && (
                        <div>
                            <h2 style={{ fontSize: '1.8rem', marginBottom: '8px' }}>Rastreamentos Ativos</h2>
                            <p style={{ color: 'rgba(255,255,255,0.4)', marginBottom: '32px' }}>Acompanhe em tempo real suas encomendas favoritas.</p>
                            {tracks.map(t => (
                                <div key={t.id} className="item-card" onClick={() => navigate(`/rastreio?codigo=${t.id}`)}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                                        <div style={{ width: '40px', height: '40px', background: 'rgba(99, 102, 241, 0.1)', borderRadius: '10px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                            <Package size={20} color="#818cf8" />
                                        </div>
                                        <div>
                                            <div style={{ fontWeight: 800, fontSize: '1.1rem' }}>{t.id}</div>
                                            <div style={{ fontSize: '0.85rem', color: 'rgba(255,255,255,0.4)' }}>Localização: {t.local}</div>
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: '#818cf8', fontWeight: 700 }}>
                                        {t.status} <ChevronRight size={18} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <Footer />
        </div>
    );
};

export default Profile;
