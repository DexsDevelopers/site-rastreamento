import { useState, useEffect } from 'react';
import axios from 'axios';
import { Smartphone, RefreshCw, CheckCircle, XCircle, QrCode, LogOut, MessageSquare, AlertTriangle } from 'lucide-react';

interface BotStatus {
    connected: boolean;
    number?: string;
    pushname?: string;
    platform?: string;
    uptime?: string;
}

const WhatsAppConfig = () => {
    const [status, setStatus] = useState<BotStatus | null>(null);
    const [qrBase64, setQrBase64] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [actionLoading, setActionLoading] = useState(false);

    const fetchStatus = async () => {
        setLoading(true);
        try {
            const res = await axios.get('/api/admin/bot/status');
            if (res.data.success) {
                setStatus(res.data.status);
                // Se conectado, limpamos o QR
                if (res.data.status.connected) setQrBase64(null);
            } else {
                setStatus(null);
            }
        } catch (err) {
            console.error('Erro ao buscar status do bot', err);
            setStatus(null);
        } finally {
            setLoading(false);
        }
    };

    const fetchQR = async () => {
        try {
            const res = await axios.get('/api/admin/bot/qr');
            if (res.data.success && res.data.qr) {
                setQrBase64(res.data.qr);
            }
        } catch (err) {
            console.error('Erro ao buscar QR do bot', err);
        }
    };

    useEffect(() => {
        fetchStatus();
        const interval = setInterval(() => {
            // Se não estiver conectado, tenta buscar o QR constantemente
            if (status && !status.connected) {
                fetchQR();
            }
            // Atualiza status a cada 30s
            fetchStatus();
        }, 30000);

        return () => clearInterval(interval);
    }, [status?.connected]);

    // Busca QR inicial se não estiver conectado
    useEffect(() => {
        if (status && !status.connected) {
            fetchQR();
        }
    }, [status]);

    const handleRestart = async () => {
        if (!window.confirm('Deseja reiniciar a sessão do WhatsApp?')) return;
        setActionLoading(true);
        try {
            await axios.post('/api/admin/bot/restart');
            alert('Bot reiniciado com sucesso! Aguarde o novo QR code.');
            fetchStatus();
        } catch (err) {
            alert('Erro ao reiniciar bot.');
        } finally {
            setActionLoading(false);
        }
    };

    return (
        <div style={{ padding: '32px', maxWidth: '1000px', margin: '0 auto', animation: 'fadeIn 0.5s ease' }}>
            <header style={{ marginBottom: '40px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                    <h1 style={{ fontSize: '2.5rem', marginBottom: '8px' }}>Bot <span className="text-gradient">WhatsApp</span></h1>
                    <p style={{ color: 'var(--text-secondary)' }}>Gerencie a conexão do robô de notificações e autoatendimento.</p>
                </div>
                <button
                    onClick={fetchStatus}
                    className="btn-primary"
                    disabled={loading}
                    style={{ padding: '10px 20px', display: 'flex', alignItems: 'center', gap: '8px' }}
                >
                    <RefreshCw size={18} className={loading ? 'animate-spin' : ''} />
                    Atualizar Status
                </button>
            </header>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px' }}>
                {/* Painel de Status */}
                <div className="glass-panel" style={{ padding: '32px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '20px', marginBottom: '32px' }}>
                        <div style={{
                            width: '64px', height: '64px', borderRadius: '20px',
                            background: status?.connected ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            color: status?.connected ? 'var(--success)' : 'var(--danger)',
                            border: `1px solid ${status?.connected ? 'rgba(34, 197, 94, 0.2)' : 'rgba(239, 68, 68, 0.2)'}`
                        }}>
                            <Smartphone size={32} />
                        </div>
                        <div>
                            <h3 style={{ margin: 0, fontSize: '1.4rem' }}>Status da Conexão</h3>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginTop: '4px' }}>
                                {status?.connected ? (
                                    <>
                                        <CheckCircle size={16} color="var(--success)" />
                                        <span style={{ color: 'var(--success)', fontWeight: 700 }}>Conectado</span>
                                    </>
                                ) : (
                                    <>
                                        <XCircle size={16} color="var(--danger)" />
                                        <span style={{ color: 'var(--danger)', fontWeight: 700 }}>Desconectado</span>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>

                    {status?.connected ? (
                        <div style={{ display: 'grid', gap: '16px' }}>
                            <div style={infoRow}>
                                <span style={infoLabel}>Sessão:</span>
                                <span style={infoValue}>{status.pushname || 'Loggi Bot'}</span>
                            </div>
                            <div style={infoRow}>
                                <span style={infoLabel}>Número:</span>
                                <span style={infoValue}>{status.number || 'N/A'}</span>
                            </div>
                            <div style={infoRow}>
                                <span style={infoLabel}>Plataforma:</span>
                                <span style={infoValue}>{status.platform || 'WhatsApp Web'}</span>
                            </div>
                            <div style={infoRow}>
                                <span style={infoLabel}>Ativo há:</span>
                                <span style={infoValue}>{status.uptime || 'Recentemente'}</span>
                            </div>

                            <button
                                onClick={handleRestart}
                                disabled={actionLoading}
                                style={{
                                    marginTop: '20px', padding: '14px', borderRadius: '12px',
                                    background: 'rgba(239, 68, 68, 0.1)', color: 'var(--danger)',
                                    border: '1px solid rgba(239, 68, 68, 0.2)', cursor: 'pointer',
                                    fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px'
                                }}
                            >
                                <LogOut size={18} /> Desconectar Sessão
                            </button>
                        </div>
                    ) : (
                        <div style={{ textAlign: 'center', padding: '20px' }}>
                            <AlertTriangle size={48} color="#f59e0b" style={{ marginBottom: '16px', opacity: 0.5 }} />
                            <p style={{ color: 'var(--text-secondary)', lineHeight: '1.6' }}>
                                O bot está aguardando autenticação. Escaneie o QR Code ao lado usando seu aplicativo WhatsApp.
                            </p>
                            <button
                                onClick={fetchQR}
                                className="btn-primary"
                                style={{ marginTop: '20px', background: 'var(--accent-gradient)' }}
                            >
                                Gerar novo QR Code
                            </button>
                        </div>
                    )}
                </div>

                {/* QR Code */}
                <div className="glass-panel" style={{ padding: '32px', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center' }}>
                    {status?.connected ? (
                        <div style={{ textAlign: 'center' }}>
                            <div style={{
                                width: '200px', height: '200px', borderRadius: '24px',
                                background: 'rgba(34, 197, 94, 0.05)', border: '2px dashed rgba(34, 197, 94, 0.2)',
                                display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: '24px'
                            }}>
                                <CheckCircle size={80} color="var(--success)" style={{ opacity: 0.3 }} />
                            </div>
                            <h4 style={{ fontSize: '1.2rem', margin: '0 0 8px 0' }}>Dispositivo Pareado</h4>
                            <p style={{ color: 'var(--text-secondary)', fontSize: '0.9rem' }}>Nenhuma ação necessária.</p>
                        </div>
                    ) : qrBase64 ? (
                        <div style={{ textAlign: 'center' }}>
                            <div style={{
                                padding: '16px', background: '#fff', borderRadius: '20px',
                                display: 'inline-block', boxShadow: '0 20px 40px rgba(0,0,0,0.5)',
                                marginBottom: '24px'
                            }}>
                                <img src={qrBase64} alt="WhatsApp QR Code" style={{ width: '220px', height: '220px', display: 'block' }} />
                            </div>
                            <h4 style={{ fontSize: '1.2rem', margin: '0 0 12px 0' }}>Escaneie para conectar</h4>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: 'var(--text-secondary)', fontSize: '0.85rem', justifyContent: 'center' }}>
                                <RefreshCw size={14} className="animate-spin" />
                                <span>Atualiza automaticamente</span>
                            </div>
                        </div>
                    ) : (
                        <div style={{ textAlign: 'center' }}>
                            <div style={{
                                width: '220px', height: '220px', borderRadius: '24px',
                                background: 'rgba(255,255,255,0.02)', border: '2px dashed rgba(255,255,255,0.1)',
                                display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: '24px'
                            }}>
                                <QrCode size={64} style={{ opacity: 0.1 }} />
                            </div>
                            <h4 style={{ fontSize: '1.1rem', color: '#555' }}>Aguardando servidor...</h4>
                            <p style={{ color: '#444', fontSize: '0.85rem' }}>O QR Code aparecerá aqui.</p>
                        </div>
                    )}
                </div>
            </div>

            {/* Logs Rápidos? */}
            <div className="glass-panel" style={{ marginTop: '24px', padding: '24px' }}>
                <h3 style={{ display: 'flex', alignItems: 'center', gap: '10px', fontSize: '1.1rem', marginBottom: '16px' }}>
                    <MessageSquare size={18} color="var(--accent-primary)" /> Informações do Bot
                </h3>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: '20px' }}>
                    <div style={infoCard}>
                        <h4 style={infoCardTitle}>Notificações Automáticas</h4>
                        <p style={infoCardDesc}>O sistema envia mensagens quando o status do rastreio é atualizado pelo painel.</p>
                    </div>
                    <div style={infoCard}>
                        <h4 style={infoCardTitle}>Autoatendimento</h4>
                        <p style={infoCardDesc}>Clientes que enviarem mensagens serão respondidos com as opções de rastreio automático.</p>
                    </div>
                    <div style={infoCard}>
                        <h4 style={infoCardTitle}>Segurança Anti-Ban</h4>
                        <p style={infoCardDesc}>Sistema com delays humanos simulados e sufixos aleatórios para proteger seu chip.</p>
                    </div>
                </div>
            </div>

            <style>{`
                @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                .animate-spin { animation: spin 1s linear infinite; }
                @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
                .text-gradient { background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
            `}</style>
        </div>
    );
};

const infoRow = { display: 'flex', justifyContent: 'space-between', padding: '12px 0', borderBottom: '1px solid rgba(255,255,255,0.05)' };
const infoLabel = { color: 'var(--text-secondary)', fontSize: '0.9rem' };
const infoValue = { fontWeight: 600, color: 'var(--text-primary)' };

const infoCard = { padding: '16px', background: 'rgba(255,255,255,0.02)', borderRadius: '12px', border: '1px solid rgba(255,255,255,0.05)' };
const infoCardTitle = { fontSize: '0.95rem', margin: '0 0 8px 0', color: 'var(--accent-primary)' };
const infoCardDesc = { fontSize: '0.85rem', color: '#777', margin: 0, lineHeight: '1.4' };

export default WhatsAppConfig;
