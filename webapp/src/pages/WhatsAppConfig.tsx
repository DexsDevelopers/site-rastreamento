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
    const [resetLoading, setResetLoading] = useState(false);
    const [pairPhone, setPairPhone] = useState('');
    const [pairCode, setPairCode] = useState<string | null>(null);
    const [pairLoading, setPairLoading] = useState(false);
    const [pairError, setPairError] = useState<string | null>(null);

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

    const handlePairByPhone = async () => {
        const digits = pairPhone.replace(/\D/g, '');
        if (digits.length < 10) {
            setPairError('Informe um número válido com DDD (ex: 11999999999)');
            return;
        }
        setPairLoading(true);
        setPairCode(null);
        setPairError(null);
        try {
            const res = await axios.post('/api/admin/bot/pair', { phone: digits });
            if (res.data.success) {
                setPairCode(res.data.code);
            } else {
                setPairError(res.data.message || 'Erro ao gerar código');
            }
        } catch {
            setPairError('Erro de conexão com o servidor');
        } finally {
            setPairLoading(false);
        }
    };

    const handleRestart = async () => {
        if (status?.connected && !window.confirm('Deseja reiniciar a sessão e desconectar o WhatsApp atual?')) return;
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

    const handleReset = async () => {
        if (!window.confirm('Isso vai APAGAR a sessão atual e gerar um novo QR para escanear. Continuar?')) return;
        setResetLoading(true);
        try {
            const res = await axios.post('/api/admin/bot/reset');
            alert(res.data.message || 'Sessão limpa! Aguarde o novo QR Code.');
            setQrBase64(null);
            setTimeout(fetchStatus, 3000);
            setTimeout(fetchQR, 5000);
        } catch (err) {
            alert('Erro ao limpar sessão.');
        } finally {
            setResetLoading(false);
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
                        <div style={{ padding: '8px 0' }}>
                            <div style={{ textAlign: 'center', marginBottom: '24px' }}>
                                <AlertTriangle size={36} color="#f59e0b" style={{ marginBottom: '10px', opacity: 0.6 }} />
                                <p style={{ color: 'var(--text-secondary)', lineHeight: '1.6', margin: 0, fontSize: '0.9rem' }}>
                                    Escaneie o QR Code ao lado <strong>ou</strong> vincule pelo número abaixo.
                                </p>
                            </div>

                            {/* Vincular por número */}
                            <div style={{ background: 'rgba(37,99,235,0.07)', border: '1px solid rgba(37,99,235,0.2)', borderRadius: '14px', padding: '18px', marginBottom: '16px' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '12px' }}>
                                    <Smartphone size={16} color="#60a5fa" />
                                    <span style={{ fontSize: '0.85rem', fontWeight: 700, color: '#60a5fa' }}>Vincular por número (sem câmera)</span>
                                </div>
                                <div style={{ display: 'flex', gap: '8px' }}>
                                    <input
                                        type="tel"
                                        placeholder="DDD + número (ex: 11999999999)"
                                        value={pairPhone}
                                        onChange={e => { setPairPhone(e.target.value); setPairCode(null); setPairError(null); }}
                                        style={{ flex: 1, background: '#111827', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '9px', padding: '10px 14px', color: '#fff', fontSize: '0.95rem', outline: 'none' }}
                                    />
                                    <button
                                        onClick={handlePairByPhone}
                                        disabled={pairLoading}
                                        style={{ padding: '10px 16px', borderRadius: '9px', background: 'linear-gradient(135deg,#2563EB,#1D4ED8)', border: 'none', color: '#fff', fontWeight: 700, cursor: 'pointer', whiteSpace: 'nowrap', fontSize: '0.85rem' }}
                                    >
                                        {pairLoading ? '...' : 'Gerar Código'}
                                    </button>
                                </div>

                                {pairError && (
                                    <p style={{ color: '#f87171', fontSize: '0.8rem', margin: '8px 0 0 0' }}>{pairError}</p>
                                )}

                                {pairCode && (
                                    <div style={{ marginTop: '14px' }}>
                                        <div style={{ background: '#0f172a', border: '2px solid #2563EB', borderRadius: '12px', padding: '16px 20px', textAlign: 'center' }}>
                                            <span style={{ fontSize: '2.2rem', fontWeight: 900, letterSpacing: '4px', color: '#60a5fa', fontFamily: 'monospace', display: 'block', marginBottom: '8px' }}>{pairCode}</span>
                                            <div style={{ fontSize: '0.75rem', color: '#94a3b8', lineHeight: '1.5', textAlign: 'left' }}>
                                                <strong style={{ color: '#f0f0f0' }}>Como usar:</strong><br />
                                                1. Abra o WhatsApp no celular<br />
                                                2. Vá em <strong>⋮ → Dispositivos vinculados</strong><br />
                                                3. Toque em <strong>"Vincular dispositivo"</strong><br />
                                                4. Toque em <strong>"Vincular com número"</strong><br />
                                                5. Digite o código acima<br />
                                                <span style={{ color: '#f59e0b', marginTop: '6px', display: 'block' }}>⏱ Expira em ~60s — não feche esta tela!</span>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div style={{ display: 'flex', gap: '8px' }}>
                                <button
                                    onClick={handleRestart}
                                    disabled={actionLoading}
                                    className="btn-primary"
                                    style={{ flex: 1, background: 'rgba(239,68,68,0.15)', border: '1px solid rgba(239,68,68,0.3)', color: '#f87171', fontSize: '0.82rem', padding: '10px' }}
                                >
                                    {actionLoading ? '...' : '🔄 Novo QR'}
                                </button>
                                <button
                                    onClick={handleReset}
                                    disabled={resetLoading}
                                    style={{ flex: 1, background: 'rgba(239,68,68,0.25)', border: '1px solid rgba(239,68,68,0.5)', color: '#ff4444', borderRadius: '10px', fontSize: '0.82rem', padding: '10px', fontWeight: 700, cursor: 'pointer' }}
                                >
                                    {resetLoading ? '...' : '🗑️ Limpar Sessão'}
                                </button>
                            </div>
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
