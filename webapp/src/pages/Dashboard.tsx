import { Package, Users, Activity, CheckCircle, Smartphone } from 'lucide-react';

const Dashboard = () => {
    return (
        <div className="animate-fade-in">
            <header className="page-header">
                <h1 className="page-title">Dashboard <span className="text-gradient">Premium</span></h1>
                <p className="page-subtitle">Visão geral do sistema de rastreamento e automação Loggi.</p>
            </header>

            <div className="stats-grid">
                <div className="glass-panel stat-card">
                    <div className="stat-header">
                        <span>Total de Pedidos</span>
                        <div className="stat-icon"><Package size={20} /></div>
                    </div>
                    <div className="stat-value">1,284</div>
                    <div style={{ color: 'var(--success)', fontSize: '0.85rem' }}>+12% esta semana</div>
                </div>

                <div className="glass-panel stat-card">
                    <div className="stat-header">
                        <span>Entregues Hoje</span>
                        <div className="stat-icon" style={{ background: 'rgba(16, 185, 129, 0.1)', color: 'var(--success)' }}>
                            <CheckCircle size={20} />
                        </div>
                    </div>
                    <div className="stat-value">42</div>
                    <div style={{ color: 'var(--text-secondary)', fontSize: '0.85rem' }}>Automático</div>
                </div>

                <div className="glass-panel stat-card">
                    <div className="stat-header">
                        <span>Clientes Ativos</span>
                        <div className="stat-icon" style={{ background: 'rgba(245, 158, 11, 0.1)', color: 'var(--warning)' }}>
                            <Users size={20} />
                        </div>
                    </div>
                    <div className="stat-value">845</div>
                    <div style={{ color: 'var(--success)', fontSize: '0.85rem' }}>+5% este mês</div>
                </div>

                <div className="glass-panel stat-card">
                    <div className="stat-header">
                        <span>Status do Servidor</span>
                        <div className="stat-icon" style={{ background: 'rgba(99, 102, 241, 0.1)', color: 'var(--accent-primary)' }}>
                            <Activity size={20} />
                        </div>
                    </div>
                    <div className="stat-value" style={{ fontSize: '1.5rem', marginTop: '10px' }}>
                        <span className="badge badge-success">Online</span>
                    </div>
                    <div style={{ color: 'var(--text-secondary)', fontSize: '0.85rem' }}>Hostinger Ready 🚀</div>
                </div>
            </div>

            <div style={styles.botSection} className="glass-panel">
                <div style={styles.botHeader}>
                    <div style={styles.botTitleGroup}>
                        <Smartphone size={24} color="var(--accent-primary)" />
                        <h2>Conexão WhatsApp Bot</h2>
                    </div>
                    <span className="badge badge-success">Conectado</span>
                </div>

                <p style={{ color: 'var(--text-secondary)', marginBottom: '24px' }}>
                    O sistema está perfeitamente integrado à API do WhatsApp na Hostinger.
                    Você pode gerar um novo QR Code ou reiniciar o bot diretamente por aqui.
                </p>

                <div style={styles.qrcodeArea}>
                    <div style={styles.qrPlaceholder}>
                        {/* O QR Code real virá da API via WebSockets */}
                        <Smartphone size={48} color="rgba(255,255,255,0.2)" />
                        <span style={{ marginTop: '16px', color: 'var(--text-secondary)' }}>QR Code aparecerá aqui quando necessário</span>
                    </div>
                    <div style={styles.botActions}>
                        <button className="btn-primary">Gerar QR Code</button>
                        <button className="btn-primary" style={{ background: 'rgba(255, 255, 255, 0.1)' }}>Reiniciar Bot</button>
                        <button className="btn-primary" style={{ background: 'rgba(239, 68, 68, 0.2)', color: 'var(--danger)' }}>Desconectar</button>
                    </div>
                </div>
            </div>
        </div>
    );
};

const styles = {
    botSection: {
        padding: '32px',
        marginTop: '24px',
    },
    botHeader: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: '16px',
    },
    botTitleGroup: {
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
    },
    qrcodeArea: {
        display: 'flex',
        gap: '32px',
        alignItems: 'center',
        flexWrap: 'wrap' as const,
    },
    qrPlaceholder: {
        width: '240px',
        height: '240px',
        background: 'rgba(0,0,0,0.3)',
        border: '2px dashed var(--border-glass)',
        borderRadius: 'var(--radius-lg)',
        display: 'flex',
        flexDirection: 'column' as const,
        alignItems: 'center',
        justifyContent: 'center',
        textAlign: 'center' as const,
        padding: '24px',
    },
    botActions: {
        display: 'flex',
        flexDirection: 'column' as const,
        gap: '16px',
        flex: 1,
        minWidth: '200px',
    }
};

export default Dashboard;
