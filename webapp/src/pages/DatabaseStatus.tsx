import { useState, useEffect } from 'react';
import axios from 'axios';
import { Database, CheckCircle, XCircle, RefreshCw, Table } from 'lucide-react';

interface TableStatus {
    name: string;
    exists: boolean;
    count?: number;
    error?: string;
}

interface DBStatus {
    connected: boolean;
    database?: string;
    tables?: TableStatus[];
    error?: string;
}

const DatabaseStatus = () => {
    const [status, setStatus] = useState<DBStatus | null>(null);
    const [loading, setLoading] = useState(true);

    const checkStatus = async () => {
        setLoading(true);
        try {
            const res = await axios.get('/api/admin/db-health');
            setStatus(res.data);
        } catch (err: any) {
            setStatus({
                connected: false,
                error: err.response?.data?.error || err.message
            });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        checkStatus();
    }, []);

    return (
        <div style={{ padding: '32px', maxWidth: '1000px', margin: '0 auto', animation: 'fadeIn 0.5s ease' }}>
            <header style={{ marginBottom: '32px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                    <h1 style={{ fontSize: '2.5rem', marginBottom: '8px' }}>Status do <span className="text-gradient">Sistema</span></h1>
                    <p style={{ color: 'var(--text-secondary)' }}>Verificação de integridade do banco de dados e tabelas.</p>
                </div>
                <button
                    onClick={checkStatus}
                    className="btn-primary"
                    disabled={loading}
                    style={{ padding: '10px 20px' }}
                >
                    <RefreshCw size={18} className={loading ? 'animate-spin' : ''} style={{ marginRight: '8px' }} />
                    Atualizar
                </button>
            </header>

            <div style={{ display: 'grid', gap: '24px' }}>
                {/* Status da Conexão */}
                <div className="glass-panel" style={{ padding: '24px', display: 'flex', alignItems: 'center', gap: '20px' }}>
                    <div style={{
                        width: '64px', height: '64px', borderRadius: '50%',
                        background: status?.connected ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        color: status?.connected ? 'var(--success)' : 'var(--danger)'
                    }}>
                        <Database size={32} />
                    </div>
                    <div>
                        <h3 style={{ margin: 0, fontSize: '1.2rem' }}>Conexão com Banco de Dados</h3>
                        <p style={{ margin: '4px 0 0', color: status?.connected ? 'var(--success)' : 'var(--danger)', fontWeight: 600 }}>
                            {status?.connected ? `Conectado: ${status.database}` : 'Desconectado'}
                        </p>
                        {status?.error && <p style={{ color: 'var(--danger)', fontSize: '0.9rem', marginTop: '8px' }}>{status.error}</p>}
                    </div>
                </div>

                {/* Status das Tabelas */}
                <div className="glass-panel" style={{ padding: '24px' }}>
                    <h3 style={{ marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <Table size={20} color="var(--accent-primary)" /> Integridade das Tabelas
                    </h3>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '16px' }}>
                        {status?.tables?.map((table) => (
                            <div key={table.name} style={{
                                padding: '20px',
                                background: 'rgba(255,255,255,0.02)',
                                borderRadius: '12px',
                                border: `1px solid ${table.exists ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)'}`
                            }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                                    <span style={{ fontWeight: 600, fontFamily: 'monospace', fontSize: '1.1rem' }}>{table.name}</span>
                                    {table.exists ? <CheckCircle color="var(--success)" size={20} /> : <XCircle color="var(--danger)" size={20} />}
                                </div>

                                {table.exists ? (
                                    <div style={{ fontSize: '0.9rem', color: 'var(--text-secondary)' }}>
                                        Registros encontrados: <b style={{ color: 'var(--text-primary)' }}>{table.count}</b>
                                    </div>
                                ) : (
                                    <div style={{ fontSize: '0.85rem', color: 'var(--danger)' }}>
                                        {table.error || 'Tabela não encontrada no banco de dados.'}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="glass-panel" style={{ padding: '24px', background: 'rgba(99, 102, 241, 0.05)', border: '1px solid var(--accent-glow)' }}>
                    <h4 style={{ margin: '0 0 12px 0', color: 'var(--accent-primary)' }}>Dica de Infraestrutura</h4>
                    <p style={{ margin: 0, fontSize: '0.9rem', color: 'var(--text-secondary)', lineHeight: '1.6' }}>
                        Se alguma tabela estiver faltando, verifique os scripts de migração ou se o usuário do banco de dados no <code>.env</code> tem permissões de leitura/escrita.
                    </p>
                </div>
            </div>

            <style>{`
                @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
                .animate-spin { animation: spin 1s linear infinite; }
                @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
            `}</style>
        </div>
    );
};

export default DatabaseStatus;
