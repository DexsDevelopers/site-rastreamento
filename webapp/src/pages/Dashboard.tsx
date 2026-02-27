import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Package, Users, Activity, CheckCircle, Smartphone } from 'lucide-react';

const Dashboard = () => {
    const [counts, setCounts] = useState({
        orders: 0,
        clients: 0,
        delivered: 0,
        loading: true
    });

    useEffect(() => {
        const fetchStats = async () => {
            try {
                const [ordersRes, clientsRes] = await Promise.all([
                    axios.get('/api/orders'),
                    axios.get('/api/clients')
                ]);

                // Simulação de entregues hoje (apenas para exemplo visual, ou filtrar se houver campo data)
                const deliveredToday = ordersRes.data.filter((o: any) => o.status === 'Entregue').length;

                setCounts({
                    orders: ordersRes.data.length,
                    clients: clientsRes.data.length,
                    delivered: deliveredToday,
                    loading: false
                });
            } catch (error) {
                console.error('Erro ao carregar estatísticas:', error);
                setCounts(prev => ({ ...prev, loading: false }));
            }
        };

        fetchStats();
        // Atualizar a cada 30 segundos
        const interval = setInterval(fetchStats, 30000);
        return () => clearInterval(interval);
    }, []);

    return (
        <div className="animate-fade">
            <header className="page-header">
                <h1 className="page-title">Painel <span className="text-gradient">Premium</span></h1>
                <p className="page-subtitle">Visão geral do sistema e automação de rastreio em tempo real.</p>
            </header>

            <div className="stats-grid">
                <div className="glass-panel stat-card">
                    <div className="stat-header">
                        <span>Total de Pedidos</span>
                        <div className="stat-icon"><Package size={20} /></div>
                    </div>
                    <div className="stat-value">{counts.loading ? '...' : counts.orders.toLocaleString()}</div>
                    <div style={{ color: 'var(--success)', fontSize: '0.85rem' }}>Dados Reais do Banco</div>
                </div>

                <div className="glass-panel stat-card">
                    <div className="stat-header">
                        <span>Entregues (Total)</span>
                        <div className="stat-icon" style={{ background: 'rgba(16, 185, 129, 0.1)', color: 'var(--success)' }}>
                            <CheckCircle size={20} />
                        </div>
                    </div>
                    <div className="stat-value">{counts.loading ? '...' : counts.delivered}</div>
                    <div style={{ color: 'var(--text-secondary)', fontSize: '0.85rem' }}>Atualizado agora</div>
                </div>

                <div className="glass-panel stat-card">
                    <div className="stat-header">
                        <span>Clientes Cadastrados</span>
                        <div className="stat-icon" style={{ background: 'rgba(245, 158, 11, 0.1)', color: 'var(--warning)' }}>
                            <Users size={20} />
                        </div>
                    </div>
                    <div className="stat-value">{counts.loading ? '...' : counts.clients.toLocaleString()}</div>
                    <div style={{ color: 'var(--success)', fontSize: '0.85rem' }}>Base de dados ativa</div>
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
                    <div style={{ color: 'var(--text-secondary)', fontSize: '0.85rem' }}>Hostinger Node.js 🚀</div>
                </div>
            </div>

            <div style={styles.botSection} className="glass-panel">
                <div style={styles.botHeader}>
                    <div style={styles.botTitleGroup}>
                        <Smartphone size={24} color="var(--accent-primary)" />
                        <h2>Integração WhatsApp Bot</h2>
                    </div>
                    <span className="badge badge-success">Ativo</span>
                </div>

                <p style={{ color: 'var(--text-secondary)', marginBottom: '24px' }}>
                    O bot de notificações está monitorando a fila de alterações de status.
                    Seu cliente receberá uma mensagem automática assim que você atualizar o rastreio.
                </p>

                <div style={styles.qrcodeArea}>
                    <div style={styles.qrPlaceholder}>
                        <Smartphone size={48} color="rgba(255,255,255,0.1)" />
                        <span style={{ marginTop: '16px', color: 'var(--text-secondary)', fontSize: '0.8rem' }}>QR Code disponível se a conexão cair</span>
                    </div>
                    <div style={styles.botActions}>
                        <button className="btn-primary" style={{ background: 'var(--accent-glow)', border: '1px solid var(--accent-primary)' }}>Gerar Nova Conexão</button>
                        <button className="btn-primary" style={{ background: 'rgba(255, 255, 255, 0.05)' }}>Ver Logs do Bot</button>
                        <button className="btn-primary" style={{ background: 'rgba(239, 68, 68, 0.1)', color: 'var(--danger)' }}>Parar Serviço</button>
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
        borderRadius: 'var(--radius-lg)',
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
        width: '200px',
        height: '200px',
        background: 'rgba(0,0,0,0.2)',
        border: '1px dashed var(--border-glass)',
        borderRadius: '20px',
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
        gap: '12px',
        flex: 1,
        minWidth: '240px',
    }
};

export default Dashboard;
