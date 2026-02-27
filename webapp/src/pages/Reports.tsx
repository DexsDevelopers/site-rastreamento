import { useState, useEffect } from 'react';
import { BarChart3, TrendingUp, PieChart, Calendar, Download, Package, Users, DollarSign, ArrowUpRight, ArrowDownRight } from 'lucide-react';

interface ReportStats {
    totalOrders: number;
    deliveredOrders: number;
    pendingOrdersPortion: number;
    newClientsThisMonth: number;
    revenueValue: string;
    efficiency: number;
}

const Reports = () => {
    const [stats, setStats] = useState<ReportStats | null>(null);

    useEffect(() => {
        // Simulação de carregamento de dados para demonstração visual
        setTimeout(() => {
            setStats({
                totalOrders: 1247,
                deliveredOrders: 980,
                pendingOrdersPortion: 12, // %
                newClientsThisMonth: 156,
                revenueValue: '2.450,00',
                efficiency: 94.5
            });
        }, 1000);
    }, []);

    return (
        <div style={{ padding: '32px', maxWidth: '1200px', margin: '0 auto', animation: 'fadeIn 0.5s ease' }}>
            <header style={{ marginBottom: '40px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '20px' }}>
                <div>
                    <h1 style={{ fontSize: '2.5rem', marginBottom: '8px' }}>Relatórios & <span className="text-gradient">Análises</span></h1>
                    <p style={{ color: 'var(--text-secondary)' }}>Visualize o crescimento e a eficiência da sua operação logística.</p>
                </div>
                <div style={{ display: 'flex', gap: '12px' }}>
                    <button className="glass-panel" style={{ padding: '10px 20px', display: 'flex', alignItems: 'center', gap: '8px', border: '1px solid var(--border-glass)', cursor: 'pointer' }}>
                        <Calendar size={18} />
                        Últimos 30 dias
                    </button>
                    <button className="btn-primary" style={{ padding: '10px 20px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <Download size={18} />
                        Exportar PDF
                    </button>
                </div>
            </header>

            {/* Top Cards */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: '24px', marginBottom: '40px' }}>
                <ReportCard
                    icon={<Package color="#6366f1" />}
                    label="Entregas Realizadas"
                    value={stats?.deliveredOrders.toString() || '0'}
                    trend="+12%"
                    trendUp={true}
                />
                <ReportCard
                    icon={<Users color="#a855f7" />}
                    label="Novos Clientes"
                    value={stats?.newClientsThisMonth.toString() || '0'}
                    trend="+8%"
                    trendUp={true}
                />
                <ReportCard
                    icon={<DollarSign color="#22c55e" />}
                    label="Taxas Coletadas"
                    value={`R$ ${stats?.revenueValue || '0,00'}`}
                    trend="+25%"
                    trendUp={true}
                />
                <ReportCard
                    icon={<TrendingUp color="#3b82f6" />}
                    label="Eficiência Operacional"
                    value={`${stats?.efficiency || 0}%`}
                    trend="-1.2%"
                    trendUp={false}
                />
            </div>

            {/* Gráficos em placeholders visuais */}
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '24px' }}>
                <div className="glass-panel" style={{ padding: '32px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '32px' }}>
                        <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: '10px' }}>
                            <BarChart3 size={20} color="var(--accent-primary)" /> Volume de Entregas Mensal
                        </h3>
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '0.8rem', color: '#666' }}>
                                <div style={{ width: '10px', height: '10px', borderRadius: '50%', background: '#6366f1' }}></div> Realizado
                            </div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '0.8rem', color: '#666' }}>
                                <div style={{ width: '10px', height: '10px', borderRadius: '50%', background: 'rgba(255,255,255,0.05)' }}></div> Meta
                            </div>
                        </div>
                    </div>

                    {/* Visual de Gráfico */}
                    <div style={{ height: '240px', display: 'flex', alignItems: 'flex-end', gap: '16px', padding: '0 10px' }}>
                        {[40, 60, 45, 80, 55, 90, 70, 85, 60, 75, 95, 80].map((h, i) => (
                            <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '8px' }}>
                                <div style={{
                                    width: '100%',
                                    height: `${h}%`,
                                    background: i === 10 ? 'var(--accent-gradient)' : 'rgba(99, 102, 241, 0.15)',
                                    borderRadius: '6px 6px 2px 2px',
                                    border: i === 10 ? 'none' : '1px solid rgba(99, 102, 241, 0.1)',
                                    transition: 'all 0.3s ease',
                                    position: 'relative'
                                }} className="chart-bar">
                                    {i === 10 && <div style={{ position: 'absolute', top: '-25px', left: '50%', transform: 'translateX(-50%)', background: '#fff', color: '#000', fontSize: '10px', fontWeight: 800, padding: '2px 5px', borderRadius: '4px' }}>MAX</div>}
                                </div>
                                <span style={{ fontSize: '10px', color: '#444' }}>{['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'][i]}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="glass-panel" style={{ padding: '32px' }}>
                    <h3 style={{ margin: '0 0 32px 0', display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <PieChart size={20} color="var(--accent-primary)" /> Distribuição por Status
                    </h3>

                    <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                        <StatusProgress label="Entregue" value={78} color="#22c55e" />
                        <StatusProgress label="Em Trânsito" value={15} color="#3b82f6" />
                        <StatusProgress label="Pendentes" value={5} color="#f59e0b" />
                        <StatusProgress label="Extraviado/Erro" value={2} color="#ef4444" />
                    </div>

                    <div style={{ marginTop: '32px', padding: '16px', background: 'rgba(255,255,255,0.02)', borderRadius: '12px', textAlign: 'center' }}>
                        <p style={{ fontSize: '0.85rem', color: 'var(--text-secondary)', margin: 0 }}>
                            Total de <b>{stats?.totalOrders || 0}</b> registros processados este mês.
                        </p>
                    </div>
                </div>
            </div>

            <style>{`
                @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                .chart-bar:hover { filter: brightness(1.2); transform: scaleX(1.1); }
                .text-gradient { background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
            `}</style>
        </div>
    );
};

const ReportCard = ({ icon, label, value, trend, trendUp }: any) => (
    <div className="glass-panel" style={{ padding: '24px', position: 'relative', overflow: 'hidden' }}>
        <div style={{ width: '40px', height: '40px', borderRadius: '12px', background: 'rgba(255,255,255,0.03)', display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: '16px' }}>
            {icon}
        </div>
        <div style={{ fontSize: '0.85rem', color: '#666', fontWeight: 500, marginBottom: '6px' }}>{label}</div>
        <div style={{ display: 'flex', alignItems: 'baseline', gap: '10px' }}>
            <div style={{ fontSize: '1.8rem', fontWeight: 800 }}>{value}</div>
            <div style={{ display: 'flex', alignItems: 'center', fontSize: '0.8rem', fontWeight: 700, color: trendUp ? '#22c55e' : '#ef4444' }}>
                {trendUp ? <ArrowUpRight size={14} /> : <ArrowDownRight size={14} />}
                {trend}
            </div>
        </div>
    </div>
);

const StatusProgress = ({ label, value, color }: any) => (
    <div>
        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px', fontSize: '0.9rem' }}>
            <span style={{ color: '#bbb' }}>{label}</span>
            <span style={{ fontWeight: 700 }}>{value}%</span>
        </div>
        <div style={{ height: '6px', background: 'rgba(255,255,255,0.05)', borderRadius: '10px', overflow: 'hidden' }}>
            <div style={{ width: `${value}%`, height: '100%', background: color, borderRadius: '10px' }}></div>
        </div>
    </div>
);

export default Reports;
