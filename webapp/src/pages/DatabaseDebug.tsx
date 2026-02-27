import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Database, CheckCircle, XCircle, RefreshCw, Server, ShieldCheck } from 'lucide-react';

const DatabaseDebug: React.FC = () => {
    const [status, setStatus] = useState<any>(null);
    const [loading, setLoading] = useState(true);
    const [envVars, setEnvVars] = useState<any>(null);

    const checkConnection = async () => {
        setLoading(true);
        try {
            const response = await axios.get('/api/db-check');
            setStatus(response.data);
        } catch (error: any) {
            setStatus({
                status: 'error',
                message: error.response?.data?.message || error.message
            });
        }
        setLoading(false);
    };

    useEffect(() => {
        checkConnection();
    }, []);

    return (
        <div style={styles.container}>
            <header style={styles.header}>
                <h1 style={styles.title}>Debug do <span className="text-gradient">Banco de Dados</span></h1>
                <p style={styles.subtitle}>Verificação técnica de conectividade com a Hostinger.</p>
            </header>

            <div style={styles.grid}>
                {/* Status Card */}
                <div className="glass-panel" style={styles.card}>
                    <div style={styles.cardHeader}>
                        <Database size={24} color="var(--accent-primary)" />
                        <h3>Status da Conexão</h3>
                    </div>

                    <div style={styles.statusDisplay}>
                        {loading ? (
                            <div style={styles.loadingGroup}>
                                <RefreshCw size={40} className="animate-spin" />
                                <p>Testando conexão...</p>
                            </div>
                        ) : status?.status === 'success' ? (
                            <div style={styles.successGroup}>
                                <CheckCircle size={60} color="var(--success)" />
                                <h2 style={{ color: 'var(--success)' }}>CONECTADO!</h2>
                                <p>O servidor Node.js está conversando perfeitamente com o MySQL.</p>
                                <div style={styles.debugInfo}>
                                    <strong>Resultado SQL:</strong> 1 + 1 = {status.result}
                                </div>
                            </div>
                        ) : (
                            <div style={styles.errorGroup}>
                                <XCircle size={60} color="var(--danger)" />
                                <h2 style={{ color: 'var(--danger)' }}>ERRO DE CONEXÃO</h2>
                                <p style={styles.errorMessage}>{status?.message || 'Erro desconhecido'}</p>
                                <div style={styles.helpBox}>
                                    <strong>Dica:</strong> Verifique se as credenciais no arquivo <code>backend/.env</code> estão corretas e se o IP do servidor está liberado no MySQL da Hostinger.
                                </div>
                            </div>
                        )}
                    </div>

                    <button
                        onClick={checkConnection}
                        style={styles.refreshBtn}
                        className="btn-primary"
                        disabled={loading}
                    >
                        <RefreshCw size={18} />
                        Tentar Novamente
                    </button>
                </div>

                {/* System Info */}
                <div className="glass-panel" style={styles.card}>
                    <div style={styles.cardHeader}>
                        <Server size={24} color="var(--accent-secondary)" />
                        <h3>Informações do Ambiente</h3>
                    </div>
                    <div style={styles.infoList}>
                        <div style={styles.infoItem}>
                            <span>Node.js Runtime</span>
                            <span className="badge badge-success">Ativo</span>
                        </div>
                        <div style={styles.infoItem}>
                            <span>Porta do Servidor</span>
                            <code>3000</code>
                        </div>
                        <div style={styles.infoItem}>
                            <span>Drives MySQL</span>
                            <span className="badge">mysql2/promise</span>
                        </div>
                        <div style={styles.infoItem}>
                            <span>Caminho Dist</span>
                            <code>backend/dist</code>
                        </div>
                    </div>

                    <div style={styles.safetyNotice}>
                        <ShieldCheck size={16} />
                        As senhas reais do banco de dados não são exibidas aqui por segurança.
                    </div>
                </div>
            </div>
        </div>
    );
};

const styles = {
    container: {
        padding: '40px',
        animation: 'fadeIn 0.5s ease',
    },
    header: {
        marginBottom: '40px',
    },
    title: {
        fontSize: '2.5rem',
        margin: 0,
    },
    subtitle: {
        color: 'var(--text-secondary)',
        marginTop: '8px',
    },
    grid: {
        display: 'grid',
        gridTemplateColumns: '1fr 1fr',
        gap: '32px',
    },
    card: {
        padding: '32px',
        borderRadius: '24px',
        display: 'flex',
        flexDirection: 'column' as const,
    },
    cardHeader: {
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
        marginBottom: '32px',
        borderBottom: '1px solid var(--border-glass)',
        paddingBottom: '16px',
    },
    statusDisplay: {
        flex: 1,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '20px 0',
        textAlign: 'center' as const,
    },
    loadingGroup: {
        color: 'var(--text-secondary)',
        display: 'flex',
        flexDirection: 'column' as const,
        alignItems: 'center',
        gap: '20px',
    },
    successGroup: {
        display: 'flex',
        flexDirection: 'column' as const,
        alignItems: 'center',
        gap: '16px',
    },
    errorGroup: {
        display: 'flex',
        flexDirection: 'column' as const,
        alignItems: 'center',
        gap: '16px',
    },
    errorMessage: {
        color: 'var(--text-secondary)',
        fontSize: '0.9rem',
    },
    debugInfo: {
        padding: '12px 24px',
        background: 'rgba(255,255,255,0.05)',
        borderRadius: '12px',
        fontSize: '0.9rem',
    },
    helpBox: {
        padding: '16px',
        background: 'rgba(239, 68, 68, 0.1)',
        border: '1px solid rgba(239, 68, 68, 0.2)',
        borderRadius: '12px',
        textAlign: 'left' as const,
        fontSize: '0.85rem',
        lineHeight: 1.5,
    },
    refreshBtn: {
        marginTop: '32px',
        alignSelf: 'center',
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
    },
    infoList: {
        display: 'flex',
        flexDirection: 'column' as const,
        gap: '16px',
    },
    infoItem: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        paddingBottom: '12px',
        borderBottom: '1px solid var(--border-glass)',
    },
    safetyNotice: {
        marginTop: '24px',
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        fontSize: '0.75rem',
        color: 'var(--text-secondary)',
    }
};

export default DatabaseDebug;
