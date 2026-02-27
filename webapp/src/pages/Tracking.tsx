import React, { useState } from 'react';
import { Search, Package, MapPin, Clock, CheckCircle2, AlertCircle, ArrowRight, Share2, Printer, Map } from 'lucide-react';

interface TrackingEvent {
    id: number;
    status: string;
    local: string;
    data: string;
    detalhes: string;
    completo: boolean;
}

const TrackingPage: React.FC = () => {
    const [codigo, setCodigo] = useState('');
    const [trackingData, setTrackingData] = useState<any>(null);
    const [loading, setLoading] = useState(false);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        if (!codigo) return;

        setLoading(true);
        // Simulação de busca para demonstrar o visual dopaminérgico
        setTimeout(() => {
            setTrackingData({
                codigo: codigo.toUpperCase(),
                status: 'Em Trânsito',
                ultimaAtualizacao: '27 Fev 2026 às 15:45',
                previsao: '01 Mar 2026',
                destinatário: 'Usuário Premium',
                origem: 'São Paulo, SP',
                destino: 'Rio de Janeiro, RJ',
                eventos: [
                    { id: 4, status: 'Em rota de entrega', local: 'Unidade RJ', data: '27 Fev 2026 - 15:45', detalhes: 'O objeto saiu para entrega ao destinatário', completo: false },
                    { id: 3, status: 'Em trânsito', local: 'São Paulo -> Rio', data: '26 Fev 2026 - 22:10', detalhes: 'Objeto encaminhado para Unidade de Tratamento', completo: true },
                    { id: 2, status: 'Postado', local: 'Agência Central', data: '26 Fev 2026 - 10:30', detalhes: 'Objeto recebido na agência de postagem', completo: true },
                    { id: 1, status: 'Pedido Criado', local: 'Sistema', data: '25 Fev 2026 - 18:00', detalhes: 'Informações enviadas para a transportadora', completo: true },
                ]
            });
            setLoading(false);
        }, 1500);
    };

    return (
        <div style={styles.container}>
            {/* Header / Search */}
            <div style={styles.heroSection}>
                <div style={styles.bgGlow}></div>
                <h1 style={styles.title} className="animate-fade">Rastreie sua <span className="text-gradient">Emoção.</span></h1>
                <p style={styles.subtitle} className="animate-fade">Cada atualização é um passo mais perto da sua felicidade.</p>

                <form onSubmit={handleSearch} style={styles.searchForm} className="animate-fade">
                    <div style={styles.searchBox} className="glass-card">
                        <Search size={24} color="var(--accent-primary)" />
                        <input
                            type="text"
                            placeholder="Insira seu código de 13 dígitos..."
                            style={styles.input}
                            value={codigo}
                            onChange={(e) => setCodigo(e.target.value)}
                        />
                        <button type="submit" style={styles.btn} className="btn-glow" disabled={loading}>
                            {loading ? 'Buscando...' : 'Localizar'}
                            <ArrowRight size={20} />
                        </button>
                    </div>
                </form>
            </div>

            {/* Results Section */}
            {trackingData && (
                <div style={styles.resultsContainer} className="animate-fade">
                    {/* Status Card Principal */}
                    <div style={styles.mainCard} className="glass-card">
                        <div style={styles.cardHeader}>
                            <div style={styles.badge}>
                                <div style={styles.pulse}></div>
                                {trackingData.status}
                            </div>
                            <div style={styles.trackingId}>#{trackingData.codigo}</div>
                        </div>


                        <div style={styles.quickInfoGrid}>
                            <div style={styles.infoItem}>
                                <Clock size={16} />
                                <span>Previsão: <strong>{trackingData.previsao}</strong></span>
                            </div>
                            <div style={styles.infoItem}>
                                <MapPin size={16} />
                                <span>Origem: <strong>{trackingData.origem}</strong></span>
                            </div>
                        </div>

                        {/* Barra de Progresso Visual */}
                        <div style={styles.progressSection}>
                            <div style={styles.progressBar}>
                                <div style={styles.progressFill}></div>
                                <div style={{ ...styles.dot, left: '0%', background: 'var(--accent-primary)' }}></div>
                                <div style={{ ...styles.dot, left: '33%', background: 'var(--accent-primary)' }}></div>
                                <div style={{ ...styles.dot, left: '66%', background: 'var(--accent-primary)', boxShadow: '0 0 15px var(--accent-primary)' }}></div>
                                <div style={{ ...styles.dot, left: '100%', background: 'rgba(255,255,255,0.2)' }}></div>
                            </div>
                            <div style={styles.progressLabels}>
                                <span>Coleta</span>
                                <span>Trânsito</span>
                                <span style={{ color: 'var(--accent-primary)' }}>Saiu p/ Entrega</span>
                                <span>Entregue</span>
                            </div>
                        </div>

                        <div style={styles.cardActions}>
                            <button style={styles.secondaryBtn}><Share2 size={16} /> Compartilhar</button>
                            <button style={styles.secondaryBtn}><Printer size={16} /> Imprimir</button>
                        </div>
                    </div>

                    {/* Timeline de Eventos */}
                    <div style={styles.timelineSection}>
                        <h3 style={styles.sectionTitle}>Histórico do Objeto</h3>
                        <div style={styles.timeline}>
                            {trackingData.eventos.map((evento: any, index: number) => (
                                <div key={evento.id} style={styles.timelineItem} className="animate-fade">
                                    <div style={styles.timelineIconContainer}>
                                        <div style={{
                                            ...styles.timelineLine,
                                            display: index === trackingData.eventos.length - 1 ? 'none' : 'block'
                                        }}></div>
                                        <div style={{
                                            ...styles.timelineIcon,
                                            background: index === 0 ? 'var(--accent-primary)' : 'rgba(255,255,255,0.05)',
                                            borderColor: index === 0 ? 'var(--accent-primary)' : 'var(--border-glass)'
                                        }}>
                                            {index === 0 ? <Truck size={14} color="white" /> : <CheckCircle2 size={14} color="var(--text-secondary)" />}
                                        </div>
                                    </div>
                                    <div style={{
                                        ...styles.timelineContent,
                                        background: index === 0 ? 'rgba(124, 77, 255, 0.05)' : 'transparent',
                                        border: index === 0 ? '1px solid rgba(124, 77, 255, 0.1)' : '1px solid transparent'
                                    }}>
                                        <div style={styles.timeHeader}>
                                            <span style={styles.eventData}>{evento.data}</span>
                                            <span style={styles.eventLocal}>{evento.local}</span>
                                        </div>
                                        <h4 style={{
                                            ...styles.eventStatus,
                                            color: index === 0 ? 'var(--accent-primary)' : 'var(--text-primary)'
                                        }}>{evento.status}</h4>
                                        <p style={styles.eventDesc}>{evento.detalhes}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {!trackingData && !loading && (
                <div style={styles.emptyState} className="animate-fade">
                    <div style={styles.emptyIcon}><Package size={60} color="rgba(255,255,255,0.1)" /></div>
                    <p>Aguardando seu código para iniciar a jornada...</p>
                </div>
            )}
        </div>
    );
};

const styles = {
    container: {
        background: 'var(--bg-primary)',
        color: 'var(--text-primary)',
        minHeight: '100vh',
        fontFamily: "'Outfit', sans-serif",
    },
    heroSection: {
        padding: '100px 5% 60px',
        textAlign: 'center' as const,
        position: 'relative' as const,
    },
    bgGlow: {
        position: 'absolute' as const,
        top: '0',
        left: '50%',
        transform: 'translateX(-50%)',
        width: '100%',
        maxWidth: '800px',
        height: '400px',
        background: 'radial-gradient(circle, rgba(124, 77, 255, 0.08) 0%, transparent 70%)',
        zIndex: 0,
        pointerEvents: 'none' as const,
    },
    title: {
        fontSize: '3.5rem',
        fontWeight: 900,
        marginBottom: '16px',
        letterSpacing: '-2px',
        position: 'relative' as const,
    },
    subtitle: {
        fontSize: '1.2rem',
        color: 'var(--text-secondary)',
        marginBottom: '48px',
        position: 'relative' as const,
    },
    searchForm: {
        maxWidth: '700px',
        margin: '0 auto',
        position: 'relative' as const,
        zIndex: 10,
    },
    searchBox: {
        display: 'flex',
        alignItems: 'center',
        padding: '10px 10px 10px 24px',
        borderRadius: '24px',
        gap: '16px',
    },
    input: {
        flex: 1,
        background: 'transparent',
        border: 'none',
        color: 'white',
        fontSize: '1.1rem',
        outline: 'none',
        textTransform: 'uppercase' as const,
    },
    btn: {
        background: 'var(--accent-primary)',
        color: 'white',
        border: 'none',
        padding: '14px 28px',
        borderRadius: '18px',
        fontSize: '1rem',
        fontWeight: 700,
        cursor: 'pointer',
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
    },
    resultsContainer: {
        padding: '0 5% 100px',
        maxWidth: '900px',
        margin: '0 auto',
    },
    mainCard: {
        padding: '40px',
        borderRadius: '32px',
        marginBottom: '48px',
        boxShadow: '0 40px 80px rgba(0,0,0,0.5)',
    },
    cardHeader: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: '32px',
    },
    badge: {
        background: 'rgba(0, 229, 255, 0.1)',
        color: 'var(--accent-secondary)',
        padding: '8px 20px',
        borderRadius: '100px',
        fontSize: '0.9rem',
        fontWeight: 800,
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
        border: '1px solid rgba(0, 229, 255, 0.2)',
    },
    pulse: {
        width: '8px',
        height: '8px',
        background: 'var(--accent-secondary)',
        borderRadius: '50%',
        animation: 'pulse-glow 2s infinite',
    },
    trackingId: {
        fontSize: '1.5rem',
        fontWeight: 700,
        color: 'rgba(255,255,255,0.4)',
        letterSpacing: '2px',
    },
    quickInfoGrid: {
        display: 'grid',
        gridTemplateColumns: '1fr 1fr',
        gap: '24px',
        marginBottom: '40px',
    },
    infoItem: {
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
        color: 'var(--text-secondary)',
        fontSize: '1rem',
    },
    progressSection: {
        marginBottom: '40px',
    },
    progressBar: {
        height: '4px',
        background: 'rgba(255,255,255,0.05)',
        borderRadius: '10px',
        position: 'relative' as const,
        marginBottom: '16px',
    },
    progressFill: {
        width: '66%',
        height: '100%',
        background: 'linear-gradient(90deg, var(--accent-primary), var(--accent-secondary))',
        borderRadius: '10px',
    },
    dot: {
        width: '12px',
        height: '12px',
        borderRadius: '50%',
        position: 'absolute' as const,
        top: '50%',
        transform: 'translate(-50%, -50%)',
        border: '2px solid var(--bg-primary)',
    },
    progressLabels: {
        display: 'flex',
        justifyContent: 'space-between',
        fontSize: '0.8rem',
        color: 'var(--text-secondary)',
        fontWeight: 600,
    },
    cardActions: {
        display: 'flex',
        gap: '16px',
        borderTop: '1px solid var(--border-glass)',
        paddingTop: '32px',
    },
    secondaryBtn: {
        background: 'rgba(255,255,255,0.03)',
        border: '1px solid var(--border-glass)',
        color: 'var(--text-primary)',
        padding: '10px 20px',
        borderRadius: '12px',
        fontSize: '0.9rem',
        fontWeight: 600,
        cursor: 'pointer',
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        transition: 'all 0.3s',
    },
    timelineSection: {
        padding: '0 20px',
    },
    sectionTitle: {
        fontSize: '1.5rem',
        fontWeight: 800,
        marginBottom: '32px',
    },
    timeline: {
        display: 'flex',
        flexDirection: 'column' as const,
    },
    timelineItem: {
        display: 'flex',
        gap: '24px',
    },
    timelineIconContainer: {
        display: 'flex',
        flexDirection: 'column' as const,
        alignItems: 'center',
        width: '28px',
    },
    timelineLine: {
        width: '2px',
        flex: 1,
        background: 'var(--border-glass)',
    },
    timelineIcon: {
        width: '28px',
        height: '28px',
        borderRadius: '50%',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 2,
        border: '1px solid',
    },
    timelineContent: {
        flex: 1,
        padding: '0 24px 40px',
        borderRadius: '20px',
    },
    timeHeader: {
        display: 'flex',
        justifyContent: 'space-between',
        marginBottom: '8px',
    },
    eventData: {
        fontSize: '0.85rem',
        color: 'var(--accent-primary)',
        fontWeight: 700,
    },
    eventLocal: {
        fontSize: '0.85rem',
        color: 'var(--text-secondary)',
    },
    eventStatus: {
        fontSize: '1.2rem',
        fontWeight: 800,
        marginBottom: '8px',
    },
    eventDesc: {
        fontSize: '0.95rem',
        color: 'var(--text-secondary)',
        lineHeight: 1.5,
    },
    emptyState: {
        textAlign: 'center' as const,
        padding: '80px 0',
        color: 'var(--text-secondary)',
    },
    emptyIcon: {
        marginBottom: '24px',
        animation: 'float 6s ease-in-out infinite',
    }
};

export default TrackingPage;
