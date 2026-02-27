import { useNavigate } from 'react-router-dom';
import { Lock, Mail, ArrowRight } from 'lucide-react';

const Login = () => {
    const navigate = useNavigate();

    const handleLogin = (e: React.FormEvent) => {
        e.preventDefault();
        // Navegar de forma forçada para visualizar o painel agora.
        // Mais tarde, ligaremos na API.
        navigate('/dashboard');
    };

    return (
        <div style={styles.container}>
            {/* Background elements */}
            <div style={styles.blurCircle1}></div>
            <div style={styles.blurCircle2}></div>

            {/* Glassmorphism Panel */}
            <div style={styles.glassPanel} className="glass-panel animate-fade-in">
                <div style={styles.logoContainer}>
                    <div style={styles.logoIcon}>
                        <div style={styles.innerLogoShape}></div>
                    </div>
                    <h1 style={styles.title}>
                        <span className="text-gradient">Loggi</span> Admin
                    </h1>
                    <p style={styles.subtitle}>Acesso restrito &bull; Plataforma Premium</p>
                </div>

                <form onSubmit={handleLogin} style={styles.form}>
                    <div style={styles.inputGroup}>
                        <label style={styles.label}>Loggi ID ou E-mail</label>
                        <div style={styles.inputWrapper}>
                            <Mail size={18} color="var(--text-secondary)" style={styles.inputIcon} />
                            <input
                                type="text"
                                placeholder="Ex: admin@transloggi.site"
                                className="input-field"
                                style={styles.paddedInput}
                                required
                            />
                        </div>
                    </div>

                    <div style={styles.inputGroup}>
                        <div style={styles.labelRow}>
                            <label style={styles.label}>Senha Segura</label>
                            <a href="#" style={styles.forgotPass}>Esqueci minha senha</a>
                        </div>
                        <div style={styles.inputWrapper}>
                            <Lock size={18} color="var(--text-secondary)" style={styles.inputIcon} />
                            <input
                                type="password"
                                placeholder="••••••••"
                                className="input-field"
                                style={styles.paddedInput}
                                required
                            />
                        </div>
                    </div>

                    <button type="submit" className="btn-primary" style={styles.loginBtn}>
                        Entrar no Painel <ArrowRight size={18} />
                    </button>
                </form>

                <div style={styles.footer}>
                    Status do Sistema: <span className="badge badge-success">Operacional</span>
                </div>
            </div>
        </div>
    );
};

const styles = {
    container: {
        height: '100vh',
        width: '100vw',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        position: 'absolute' as const,
        top: 0,
        left: 0,
        background: 'var(--bg-primary)',
        overflow: 'hidden',
    },
    blurCircle1: {
        position: 'absolute' as const,
        width: '500px',
        height: '500px',
        background: 'var(--accent-glow)',
        borderRadius: '50%',
        top: '-150px',
        right: '-100px',
        filter: 'blur(100px)',
    },
    blurCircle2: {
        position: 'absolute' as const,
        width: '400px',
        height: '400px',
        background: 'rgba(139, 92, 246, 0.15)',
        borderRadius: '50%',
        bottom: '-100px',
        left: '-100px',
        filter: 'blur(80px)',
    },
    glassPanel: {
        width: '100%',
        maxWidth: '440px',
        padding: '48px 40px',
        zIndex: 10,
        position: 'relative' as const,
        display: 'flex',
        flexDirection: 'column' as const,
        gap: '32px',
        boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.5)',
    },
    logoContainer: {
        textAlign: 'center' as const,
        display: 'flex',
        flexDirection: 'column' as const,
        alignItems: 'center',
        gap: '12px',
    },
    logoIcon: {
        width: '64px',
        height: '64px',
        borderRadius: 'var(--radius-lg)',
        background: 'var(--accent-gradient)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        boxShadow: '0 8px 16px var(--accent-glow)',
    },
    innerLogoShape: {
        width: '24px',
        height: '24px',
        background: 'white',
        borderRadius: '4px',
        transform: 'rotate(45deg)',
    },
    title: {
        fontSize: '2rem',
        margin: 0,
    },
    subtitle: {
        color: 'var(--text-secondary)',
        fontSize: '0.9rem',
    },
    form: {
        display: 'flex',
        flexDirection: 'column' as const,
        gap: '20px',
    },
    inputGroup: {
        display: 'flex',
        flexDirection: 'column' as const,
        gap: '8px',
    },
    labelRow: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    label: {
        fontSize: '0.9rem',
        fontWeight: 500,
        color: 'var(--text-secondary)',
    },
    forgotPass: {
        fontSize: '0.8rem',
        color: 'var(--accent-primary)',
        textDecoration: 'none',
    },
    inputWrapper: {
        position: 'relative' as const,
        display: 'flex',
        alignItems: 'center',
    },
    inputIcon: {
        position: 'absolute' as const,
        left: '16px',
        zIndex: 2,
    },
    paddedInput: {
        paddingLeft: '44px',
    },
    loginBtn: {
        marginTop: '12px',
        padding: '14px',
        fontSize: '1rem',
    },
    footer: {
        textAlign: 'center' as const,
        fontSize: '0.8rem',
        color: 'var(--text-secondary)',
        marginTop: '16px',
        borderTop: '1px solid var(--border-glass)',
        paddingTop: '24px',
    }
};

export default Login;
