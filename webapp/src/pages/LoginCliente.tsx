import React, { useState } from 'react';
import { Truck, Mail, Lock, User, ArrowRight, Eye, EyeOff, Phone, ShieldCheck } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';

const LoginCliente: React.FC = () => {
    const [tab, setTab] = useState<'login' | 'cadastro'>('login');
    const [showPassword, setShowPassword] = useState(false);
    const navigate = useNavigate();
    const [form, setForm] = useState({ nome: '', email: '', telefone: '', senha: '', confirmarSenha: '' });

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
        // Por enquanto, navega direto
        navigate('/');
    };

    return (
        <div style={styles.page}>
            {/* Efeitos de fundo */}
            <div style={styles.bgOrb1}></div>
            <div style={styles.bgOrb2}></div>
            <div style={styles.bgOrb3}></div>
            <div style={styles.gridOverlay}></div>

            <div style={styles.container}>
                {/* Logo */}
                <Link to="/" style={styles.logoLink}>
                    <div style={styles.logoBox}>
                        <Truck size={22} color="white" />
                    </div>
                    <span style={styles.logoText}>loggi</span>
                </Link>

                {/* Card Principal */}
                <div style={styles.card} className="glass-card-3d">
                    {/* Borda gradient no topo */}
                    <div style={styles.topGradient}></div>

                    {/* Tabs */}
                    <div style={styles.tabs}>
                        <button
                            style={tab === 'login' ? styles.tabActive : styles.tabInactive}
                            onClick={() => setTab('login')}
                        >Entrar</button>
                        <button
                            style={tab === 'cadastro' ? styles.tabActive : styles.tabInactive}
                            onClick={() => setTab('cadastro')}
                        >Criar Conta</button>
                    </div>

                    <form onSubmit={handleSubmit} style={styles.form}>
                        {tab === 'cadastro' && (
                            <div style={styles.field}>
                                <label style={styles.label}>Nome Completo</label>
                                <div style={styles.inputWrap}>
                                    <User size={18} color="var(--text-secondary)" style={styles.inputIcon} />
                                    <input
                                        name="nome"
                                        value={form.nome}
                                        onChange={handleChange}
                                        placeholder="Seu nome completo"
                                        required
                                        className="input-field"
                                        style={styles.inputPadded}
                                    />
                                </div>
                            </div>
                        )}

                        <div style={styles.field}>
                            <label style={styles.label}>E-mail</label>
                            <div style={styles.inputWrap}>
                                <Mail size={18} color="var(--text-secondary)" style={styles.inputIcon} />
                                <input
                                    name="email"
                                    type="email"
                                    value={form.email}
                                    onChange={handleChange}
                                    placeholder="seuemail@exemplo.com"
                                    required
                                    className="input-field"
                                    style={styles.inputPadded}
                                />
                            </div>
                        </div>

                        {tab === 'cadastro' && (
                            <div style={styles.field}>
                                <label style={styles.label}>WhatsApp</label>
                                <div style={styles.inputWrap}>
                                    <Phone size={18} color="var(--text-secondary)" style={styles.inputIcon} />
                                    <input
                                        name="telefone"
                                        value={form.telefone}
                                        onChange={handleChange}
                                        placeholder="(11) 99999-9999"
                                        required
                                        className="input-field"
                                        style={styles.inputPadded}
                                    />
                                </div>
                            </div>
                        )}

                        <div style={styles.field}>
                            <div style={styles.labelRow}>
                                <label style={styles.label}>Senha</label>
                                {tab === 'login' && (
                                    <a href="#" style={styles.forgotLink}>Esqueci a senha</a>
                                )}
                            </div>
                            <div style={styles.inputWrap}>
                                <Lock size={18} color="var(--text-secondary)" style={styles.inputIcon} />
                                <input
                                    name="senha"
                                    type={showPassword ? 'text' : 'password'}
                                    value={form.senha}
                                    onChange={handleChange}
                                    placeholder="••••••••"
                                    required
                                    className="input-field"
                                    style={styles.inputPadded}
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    style={styles.eyeBtn}
                                >
                                    {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                                </button>
                            </div>
                        </div>

                        {tab === 'cadastro' && (
                            <div style={styles.field}>
                                <label style={styles.label}>Confirmar Senha</label>
                                <div style={styles.inputWrap}>
                                    <Lock size={18} color="var(--text-secondary)" style={styles.inputIcon} />
                                    <input
                                        name="confirmarSenha"
                                        type="password"
                                        value={form.confirmarSenha}
                                        onChange={handleChange}
                                        placeholder="••••••••"
                                        required
                                        className="input-field"
                                        style={styles.inputPadded}
                                    />
                                </div>
                            </div>
                        )}

                        <button type="submit" className="btn-primary" style={styles.submitBtn}>
                            {tab === 'login' ? 'Entrar na minha conta' : 'Criar minha conta'}
                            <ArrowRight size={18} />
                        </button>
                    </form>

                    {/* Divider */}
                    <div style={styles.divider}>
                        <span style={styles.dividerLine}></span>
                        <span style={styles.dividerText}>ou</span>
                        <span style={styles.dividerLine}></span>
                    </div>

                    {/* Switch tab */}
                    <p style={styles.switchText}>
                        {tab === 'login'
                            ? 'Ainda não tem conta? '
                            : 'Já possui uma conta? '}
                        <button
                            style={styles.switchBtn}
                            onClick={() => setTab(tab === 'login' ? 'cadastro' : 'login')}
                        >
                            {tab === 'login' ? 'Criar conta grátis' : 'Entrar agora'}
                        </button>
                    </p>

                    {/* Trust */}
                    <div style={styles.trustBar}>
                        <ShieldCheck size={14} color="var(--success)" />
                        <span>Seus dados estão protegidos com criptografia de ponta</span>
                    </div>
                </div>

                {/* Admin link discreto */}
                <Link to="/login" style={styles.adminLink}>
                    Acesso Administrativo →
                </Link>
            </div>
        </div>
    );
};

const styles: { [key: string]: React.CSSProperties } = {
    page: {
        minHeight: '100vh',
        width: '100vw',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'var(--bg-primary)',
        position: 'relative',
        overflow: 'hidden',
        padding: '20px',
    },
    bgOrb1: {
        position: 'absolute',
        width: '500px', height: '500px',
        background: 'radial-gradient(circle, rgba(99, 102, 241, 0.12) 0%, transparent 70%)',
        top: '-100px', right: '-100px',
        borderRadius: '50%',
        filter: 'blur(60px)',
        pointerEvents: 'none',
    },
    bgOrb2: {
        position: 'absolute',
        width: '400px', height: '400px',
        background: 'radial-gradient(circle, rgba(168, 85, 247, 0.1) 0%, transparent 70%)',
        bottom: '-80px', left: '-80px',
        borderRadius: '50%',
        filter: 'blur(60px)',
        pointerEvents: 'none',
    },
    bgOrb3: {
        position: 'absolute',
        width: '300px', height: '300px',
        background: 'radial-gradient(circle, rgba(6, 182, 212, 0.06) 0%, transparent 70%)',
        top: '50%', left: '50%',
        transform: 'translate(-50%, -50%)',
        borderRadius: '50%',
        filter: 'blur(80px)',
        pointerEvents: 'none',
    },
    gridOverlay: {
        position: 'absolute',
        inset: 0,
        backgroundImage: 'linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px)',
        backgroundSize: '60px 60px',
        pointerEvents: 'none',
    },
    container: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        zIndex: 10,
        width: '100%',
        maxWidth: '440px',
    },
    logoLink: {
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
        textDecoration: 'none',
        color: 'white',
        marginBottom: '40px',
    },
    logoBox: {
        width: '44px', height: '44px',
        background: 'var(--accent-gradient)',
        borderRadius: '14px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        boxShadow: '0 8px 24px var(--accent-glow)',
    },
    logoText: { fontSize: '1.6rem', fontWeight: 800, fontFamily: "'Outfit', sans-serif" },
    card: {
        width: '100%',
        padding: '40px 32px',
        borderRadius: '28px',
        position: 'relative',
        overflow: 'hidden',
    },
    topGradient: {
        position: 'absolute',
        top: 0, left: 0, right: 0,
        height: '3px',
        background: 'var(--accent-gradient)',
    },
    tabs: {
        display: 'flex',
        gap: '4px',
        padding: '4px',
        background: 'rgba(255,255,255,0.03)',
        borderRadius: '14px',
        marginBottom: '32px',
        border: '1px solid var(--border-glass)',
    },
    tabActive: {
        flex: 1,
        padding: '12px',
        background: 'var(--accent-primary)',
        border: 'none',
        borderRadius: '11px',
        color: 'white',
        fontWeight: 700,
        fontSize: '0.9rem',
        cursor: 'pointer',
        fontFamily: "'Outfit', sans-serif",
        transition: 'all 0.3s',
    },
    tabInactive: {
        flex: 1,
        padding: '12px',
        background: 'transparent',
        border: 'none',
        borderRadius: '11px',
        color: 'var(--text-secondary)',
        fontWeight: 500,
        fontSize: '0.9rem',
        cursor: 'pointer',
        fontFamily: "'Outfit', sans-serif",
        transition: 'all 0.3s',
    },
    form: { display: 'flex', flexDirection: 'column', gap: '20px' },
    field: { display: 'flex', flexDirection: 'column', gap: '8px' },
    label: { fontSize: '0.85rem', fontWeight: 500, color: 'var(--text-secondary)' },
    labelRow: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' },
    forgotLink: { fontSize: '0.8rem', color: 'var(--accent-primary)', textDecoration: 'none' },
    inputWrap: { position: 'relative', display: 'flex', alignItems: 'center' },
    inputIcon: { position: 'absolute', left: '16px', zIndex: 2, pointerEvents: 'none' },
    inputPadded: { paddingLeft: '46px' },
    eyeBtn: {
        position: 'absolute',
        right: '14px',
        background: 'none',
        border: 'none',
        color: 'var(--text-secondary)',
        cursor: 'pointer',
        zIndex: 2,
        padding: '4px',
    },
    submitBtn: {
        width: '100%',
        padding: '16px',
        fontSize: '1rem',
        fontWeight: 700,
        marginTop: '8px',
        borderRadius: '14px',
    },
    divider: {
        display: 'flex',
        alignItems: 'center',
        gap: '16px',
        margin: '24px 0',
    },
    dividerLine: { flex: 1, height: '1px', background: 'var(--border-glass)' },
    dividerText: { color: 'var(--text-secondary)', fontSize: '0.8rem' },
    switchText: {
        textAlign: 'center',
        color: 'var(--text-secondary)',
        fontSize: '0.9rem',
    },
    switchBtn: {
        background: 'none',
        border: 'none',
        color: 'var(--accent-primary)',
        cursor: 'pointer',
        fontWeight: 700,
        fontSize: '0.9rem',
    },
    trustBar: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '8px',
        marginTop: '24px',
        paddingTop: '20px',
        borderTop: '1px solid var(--border-glass)',
        color: 'var(--text-secondary)',
        fontSize: '0.75rem',
    },
    adminLink: {
        marginTop: '24px',
        fontSize: '0.8rem',
        color: 'rgba(255,255,255,0.2)',
        textDecoration: 'none',
        transition: 'color 0.2s',
    },
};

export default LoginCliente;
