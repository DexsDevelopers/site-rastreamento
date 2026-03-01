import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Mail, Lock, User, ArrowRight, Eye, EyeOff, Phone, ShieldCheck } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import Header from '../components/Header';
import Footer from '../components/Footer';

const LoginCliente: React.FC = () => {
    const [tab, setTab] = useState<'login' | 'cadastro'>('login');
    const [showPassword, setShowPassword] = useState(false);
    const navigate = useNavigate();
    const [form, setForm] = useState({ nome: '', email: '', telefone: '', senha: '', confirmarSenha: '' });

    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const savedUser = localStorage.getItem('loggi_user_session');
        if (savedUser) navigate('/perfil');
    }, []);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        let { name, value } = e.target;
        if (name === 'telefone') {
            value = value.replace(/\D/g, '').slice(0, 11);
            if (value.length <= 10) value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            else value = value.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
        }
        setForm(prev => ({ ...prev, [name]: value }));
        setError('');
    };

    const validateForm = () => {
        if (!form.email.includes('@')) return 'E-mail inválido';
        if (form.senha.length < 4) return 'A senha deve ter pelo menos 4 caracteres';
        if (tab === 'cadastro') {
            if (!form.nome) return 'Nome é obrigatório';
            if (!form.telefone) return 'Telefone é obrigatório';
        }
        return null;
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        const err = validateForm();
        if (err) return setError(err);

        setLoading(true);
        setError('');

        try {
            if (tab === 'login') {
                const res = await axios.post('/api/auth/login', {
                    email: form.email,
                    senha: form.senha
                });
                if (res.data.success) {
                    localStorage.setItem('loggi_user_session', JSON.stringify(res.data.user));
                    navigate('/');
                }
            } else {
                const res = await axios.post('/api/auth/register', {
                    nome: form.nome,
                    email: form.email,
                    senha: form.senha,
                    whatsapp: form.telefone
                });
                if (res.data.success) {
                    setTab('login');
                    setError('Cadastro realizado! Faça login agora.');
                }
            }
        } catch (err: any) {
            setError(err.response?.data?.message || 'Erro ao conectar com o servidor');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="lc-page">
            <style>{`
                .lc-page { background: var(--bg-primary); color: var(--text-primary); min-height: 100vh; position: relative; overflow-x: hidden; font-family: 'Outfit', sans-serif; }
                .lc-page * { box-sizing: border-box; }
                .bg-mesh {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background:
                        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(0, 85, 255, 0.06), transparent),
                        radial-gradient(ellipse 60% 40% at 80% 50%, rgba(59, 130, 246, 0.04), transparent);
                }

                .login-container { max-width: 440px; margin: 40px auto 100px; padding: 0 24px; position: relative; z-index: 1; }
                .login-card {
                    background: rgba(255,255,255,0.65); backdrop-filter: blur(32px);
                    border: 1px solid rgba(255,255,255,0.8); border-radius: 32px;
                    padding: 48px; position: relative; overflow: hidden;
                    box-shadow: 0 16px 48px rgba(0, 40, 120, 0.08);
                }
                .login-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #0055ff, #3b82f6); }
                
                .lc-tabs { display: flex; gap: 4px; padding: 4px; background: rgba(0,85,255,0.04); border-radius: 14px; margin-bottom: 32px; border: 1px solid rgba(0,85,255,0.06); }
                .lc-tab-btn { flex: 1; padding: 12px; background: transparent; border: none; border-radius: 11px; color: var(--text-secondary); font-weight: 600; cursor: pointer; transition: all 0.3s; font-family: 'Outfit', sans-serif; }
                .lc-tab-btn.active { background: linear-gradient(135deg, #0055ff, #3b82f6); color: white; box-shadow: 0 4px 16px rgba(0, 85, 255, 0.25); }
                
                .lc-input-field {
                    width: 100%; background: rgba(255,255,255,0.7); border: 1px solid rgba(0,85,255,0.08);
                    border-radius: 14px; padding: 14px 16px 14px 44px; color: var(--text-primary); outline: none; transition: 0.3s;
                    font-family: 'Inter', sans-serif; font-size: 0.95rem;
                }
                .lc-input-field:focus { border-color: #0055ff; background: #fff; box-shadow: 0 0 0 3px rgba(0, 85, 255, 0.1); }
                .lc-input-field::placeholder { color: var(--text-muted); }
                .field-icon { position: absolute; left: 16px; top: 15px; color: var(--text-muted); }
                
                .btn-submit {
                    width: 100%; padding: 16px; background: linear-gradient(135deg, #0055ff, #3b82f6);
                    border: none; border-radius: 16px; color: white; font-weight: 800; font-size: 1rem;
                    cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;
                    margin-top: 24px; box-shadow: 0 8px 24px rgba(0, 85, 255, 0.25);
                    font-family: 'Outfit', sans-serif; transition: all 0.3s;
                }
                .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(0, 85, 255, 0.4); }
                .btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }
                
                .error-box { background: rgba(239, 68, 68, 0.06); border: 1px solid rgba(239, 68, 68, 0.15); color: #ef4444; padding: 12px; border-radius: 12px; font-size: 0.85rem; text-align: center; margin-bottom: 16px; font-weight: 600; }
                
                @media (max-width: 480px) { .login-card { padding: 32px 20px; border-radius: 24px; } }
            `}</style>

            <div className="bg-mesh"></div>
            <Header />

            <div className="login-container" style={{ paddingTop: '100px' }}>
                <div className="login-card">
                    <h2 style={{ fontSize: '1.8rem', fontWeight: 900, textAlign: 'center', marginBottom: '8px' }}>Bem-vindo</h2>
                    <p style={{ color: 'var(--text-secondary)', textAlign: 'center', marginBottom: '32px' }}>Acesse sua conta para gerenciar envios</p>

                    <div className="lc-tabs">
                        <button className={`lc-tab-btn ${tab === 'login' ? 'active' : ''}`} onClick={() => { setTab('login'); setError(''); }}>Entrar</button>
                        <button className={`lc-tab-btn ${tab === 'cadastro' ? 'active' : ''}`} onClick={() => { setTab('cadastro'); setError(''); }}>Criar Conta</button>
                    </div>

                    {error && <div className="error-box">{error}</div>}

                    <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        {tab === 'cadastro' && (
                            <div style={{ position: 'relative' }}>
                                <User className="field-icon" size={18} />
                                <input name="nome" value={form.nome} onChange={handleChange} className="lc-input-field" placeholder="Nome completo" required />
                            </div>
                        )}

                        <div style={{ position: 'relative' }}>
                            <Mail className="field-icon" size={18} />
                            <input name="email" type="email" value={form.email} onChange={handleChange} className="lc-input-field" placeholder="E-mail" required />
                        </div>

                        {tab === 'cadastro' && (
                            <div style={{ position: 'relative' }}>
                                <Phone className="field-icon" size={18} />
                                <input name="telefone" value={form.telefone} onChange={handleChange} className="lc-input-field" placeholder="WhatsApp (11) 99999-9999" required />
                            </div>
                        )}

                        <div style={{ position: 'relative' }}>
                            <Lock className="field-icon" size={18} />
                            <input name="senha" type={showPassword ? 'text' : 'password'} value={form.senha} onChange={handleChange} className="lc-input-field" placeholder="Senha" required />
                            <button type="button" onClick={() => setShowPassword(!showPassword)} style={{ position: 'absolute', right: '14px', top: '15px', background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer' }}>
                                {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                            </button>
                        </div>

                        <button type="submit" className="btn-submit" disabled={loading}>
                            {loading ? 'Aguarde...' : (tab === 'login' ? 'Entrar agora' : 'Criar minha conta')} <ArrowRight size={20} />
                        </button>
                    </form>

                    <div style={{ marginTop: '32px', textAlign: 'center', fontSize: '0.9rem', color: 'var(--text-secondary)' }}>
                        {tab === 'login' ? (
                            <>Não tem conta? <span onClick={() => { setTab('cadastro'); setError(''); }} style={{ color: '#0055ff', cursor: 'pointer', fontWeight: 700 }}>Cadastre-se grátis</span></>
                        ) : (
                            <>Já possui conta? <span onClick={() => { setTab('login'); setError(''); }} style={{ color: '#0055ff', cursor: 'pointer', fontWeight: 700 }}>Faça login</span></>
                        )}
                    </div>

                    <div style={{ marginTop: '40px', paddingTop: '24px', borderTop: '1px solid rgba(0,85,255,0.06)', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', color: 'var(--text-muted)', fontSize: '0.8rem' }}>
                        <ShieldCheck size={16} /> Protegido por Loggi Shield
                    </div>
                </div>
            </div>

            <Footer />
        </div>
    );
};

export default LoginCliente;
