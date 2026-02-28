// src/pages/LoginCliente.tsx
import React, { useState, useEffect } from 'react';
import { Truck, Mail, Lock, User, ArrowRight, Eye, EyeOff, Phone, ShieldCheck } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';

const LoginCliente: React.FC = () => {
    const [tab, setTab] = useState<'login' | 'cadastro'>('login');
    const [showPassword, setShowPassword] = useState(false);
    const [scrollY, setScrollY] = useState(0);
    const navigate = useNavigate();
    const [form, setForm] = useState({ nome: '', email: '', telefone: '', senha: '', confirmarSenha: '' });

    const [user, setUser] = useState<any>(null);
    const location = useLocation();

    useEffect(() => {
        const savedUser = localStorage.getItem('loggi_user_session');
        if (savedUser) setUser(JSON.parse(savedUser));

        const handleScroll = () => setScrollY(window.scrollY);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        let { name, value } = e.target;
        if (name === 'telefone') {
            value = value.replace(/\D/g, '').slice(0, 11);
            if (value.length <= 10) value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            else value = value.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
        }
        setForm(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Simulação de Login de Cliente
        const userData = {
            id: 'CL-' + Math.floor(Math.random() * 9999),
            nome: form.nome || 'Cliente Loggi',
            email: form.email,
            telefone: form.telefone || '(11) 99999-9999'
        };
        localStorage.setItem('loggi_user_session', JSON.stringify(userData));
        navigate('/');
    };

    return (
        <div className="lc-page">
            <style>{`
                .lc-page { background: #06060b; color: #fff; min-height: 100vh; position: relative; overflow-x: hidden; font-family: 'Outfit', sans-serif; }
                .lc-page * { box-sizing: border-box; }
                .bg-mesh {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background:
                        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(99, 102, 241, 0.15), transparent),
                        radial-gradient(ellipse 60% 40% at 80% 50%, rgba(168, 85, 247, 0.08), transparent),
                        radial-gradient(ellipse 50% 30% at 20% 80%, rgba(6, 182, 212, 0.06), transparent);
                }
                
                .site-header { position: sticky; top: 0; z-index: 100; padding: 20px 24px; transition: all 0.3s; }
                .site-header.scrolled { padding: 10px 24px; }
                .header-glass {
                    max-width: 1200px; margin: 0 auto;
                    display: flex; justify-content: space-between; align-items: center;
                    padding: 14px 28px; background: rgba(10, 10, 12, 0.4); backdrop-filter: blur(20px) saturate(1.8);
                    border: 1px solid rgba(255,255,255,0.08); border-radius: 24px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
                }
                .logo-link { display: flex; align-items: center; gap: 10px; text-decoration: none; color: white; }
                .logo-box { width: 38px; height: 38px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 12px; display: flex; align-items: center; justify-content: center; }

                .login-container { max-width: 440px; margin: 40px auto 100px; padding: 0 24px; position: relative; z-index: 1; }
                .login-card { background: rgba(255,255,255,0.02); backdrop-filter: blur(32px); border: 1px solid rgba(255,255,255,0.08); border-radius: 40px; padding: 48px; position: relative; overflow: hidden; }
                .login-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #6366f1, #a855f7); }
                
                .tabs { display: flex; gap: 4px; padding: 4px; background: rgba(255,255,255,0.03); border-radius: 14px; margin-bottom: 32px; border: 1px solid rgba(255,255,255,0.1); }
                .tab-btn { flex: 1; padding: 12px; background: transparent; border: none; border-radius: 11px; color: rgba(255,255,255,0.5); font-weight: 600; cursor: pointer; transition: all 0.3s; }
                .tab-btn.active { background: linear-gradient(135deg, #6366f1, #a855f7); color: white; box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3); }
                
                .input-field { width: 100%; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 14px 16px 14px 44px; color: white; outline: none; transition: 0.3s; }
                .input-field:focus { border-color: #818cf8; background: rgba(129, 140, 248, 0.04); box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.1); }
                .field-icon { position: absolute; left: 16px; top: 15px; color: rgba(255,255,255,0.3); }
                
                .btn-submit { width: 100%; padding: 16px; background: linear-gradient(135deg, #6366f1, #a855f7); border: none; border-radius: 16px; color: white; font-weight: 800; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 24px; box-shadow: 0 8px 32px rgba(99, 102, 241, 0.4); }
                
                .site-footer { border-top: 1px solid rgba(255,255,255,0.04); padding: 80px 24px 40px; text-align: center; }
                .footer-links { display: flex; flex-wrap: wrap; justify-content: center; gap: 24px; margin-top: 24px; }
                .footer-links a { color: rgba(255,255,255,0.3); text-decoration: none; }
                
                @media (max-width: 480px) { .login-card { padding: 32px 20px; } }
            `}</style>

            <div className="bg-mesh"></div>

            <header className={`site-header ${scrollY > 50 ? 'scrolled' : ''}`}>
                <div className="header-glass">
                    <Link to="/" className="logo-link">
                        <div className="logo-box"><Truck size={18} color="white" /></div>
                        <span className="logo-name">loggi</span>
                    </Link>
                    {user ? (
                        <Link to="/perfil" className="nav-login-btn" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <div style={{ width: '20px', height: '20px', background: 'rgba(255,255,255,0.2)', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '10px' }}>
                                {user.nome[0].toUpperCase()}
                            </div>
                            Olá, {user.nome.split(' ')[0]}
                        </Link>
                    ) : (
                        <Link to="/entrar" className="nav-login-btn">Entrar</Link>
                    )}
                </div>
            </header>

            <div className="login-container">
                <div className="login-card">
                    <h2 style={{ fontSize: '1.8rem', fontWeight: 900, textAlign: 'center', marginBottom: '8px' }}>Bem-vindo</h2>
                    <p style={{ color: 'rgba(255,255,255,0.4)', textAlign: 'center', marginBottom: '32px' }}>Acesse sua conta para gerenciar envios</p>

                    <div className="tabs">
                        <button className={`tab-btn ${tab === 'login' ? 'active' : ''}`} onClick={() => setTab('login')}>Entrar</button>
                        <button className={`tab-btn ${tab === 'cadastro' ? 'active' : ''}`} onClick={() => setTab('cadastro')}>Criar Conta</button>
                    </div>

                    <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        {tab === 'cadastro' && (
                            <div style={{ position: 'relative' }}>
                                <User className="field-icon" size={18} />
                                <input name="nome" value={form.nome} onChange={handleChange} className="input-field" placeholder="Nome completo" required />
                            </div>
                        )}

                        <div style={{ position: 'relative' }}>
                            <Mail className="field-icon" size={18} />
                            <input name="email" type="email" value={form.email} onChange={handleChange} className="input-field" placeholder="E-mail" required />
                        </div>

                        {tab === 'cadastro' && (
                            <div style={{ position: 'relative' }}>
                                <Phone className="field-icon" size={18} />
                                <input name="telefone" value={form.telefone} onChange={handleChange} className="input-field" placeholder="WhatsApp (11) 99999-9999" required />
                            </div>
                        )}

                        <div style={{ position: 'relative' }}>
                            <Lock className="field-icon" size={18} />
                            <input name="senha" type={showPassword ? 'text' : 'password'} value={form.senha} onChange={handleChange} className="input-field" placeholder="Senha" required />
                            <button type="button" onClick={() => setShowPassword(!showPassword)} style={{ position: 'absolute', right: '14px', top: '15px', background: 'none', border: 'none', color: 'rgba(255,255,255,0.3)', cursor: 'pointer' }}>
                                {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                            </button>
                        </div>

                        <button type="submit" className="btn-submit">
                            {tab === 'login' ? 'Entrar agora' : 'Criar minha conta'} <ArrowRight size={20} />
                        </button>
                    </form>

                    <div style={{ marginTop: '32px', textAlign: 'center', fontSize: '0.9rem', color: 'rgba(255,255,255,0.4)' }}>
                        {tab === 'login' ? (
                            <>Não tem conta? <span onClick={() => setTab('cadastro')} style={{ color: '#818cf8', cursor: 'pointer', fontWeight: 700 }}>Cadastre-se grátis</span></>
                        ) : (
                            <>Já possui conta? <span onClick={() => setTab('login')} style={{ color: '#818cf8', cursor: 'pointer', fontWeight: 700 }}>Faça login</span></>
                        )}
                    </div>

                    <div style={{ marginTop: '40px', paddingTop: '24px', borderTop: '1px solid rgba(255,255,255,0.05)', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', color: 'rgba(255,255,255,0.3)', fontSize: '0.8rem' }}>
                        <ShieldCheck size={16} /> Protegido por Loggi Shield
                    </div>
                </div>
            </div>

            <footer className="site-footer">
                <Link to="/" className="logo-link" style={{ justifyContent: 'center', marginBottom: '32px' }}>
                    <div className="logo-box"><Truck size={18} color="white" /></div>
                    <span className="logo-name">loggi</span>
                </Link>
                <div className="footer-links" style={{ fontSize: '0.8rem' }}>
                    <Link to="/sobre">Sobre</Link>
                    <Link to="/para-voce">Para Você</Link>
                    <Link to="/para-empresas">Empresas</Link>
                    <Link to="/api-ecommerce">API</Link>
                    <Link to="/loggi-pro">Loggi Pro</Link>
                    <Link to="/carreiras">Carreiras</Link>
                    <Link to="/termos">Termos de Uso</Link>
                    <Link to="/ajuda">Ajuda</Link>
                </div>
            </footer>
        </div>
    );
};

export default LoginCliente;
