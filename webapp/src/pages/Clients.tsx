import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Search, Plus, Mail, Phone, MapPin, ExternalLink } from 'lucide-react';

interface Client {
    id: number;
    codigo: string;
    nome: string;
    email: string | null;
    telefone: string;
    cidade: string;
    total_indicacoes: number;
    total_compras: number;
}

const Clients: React.FC = () => {
    const [clients, setClients] = useState<Client[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        fetchClients();
    }, []);

    const fetchClients = async () => {
        try {
            const response = await axios.get('/api/clients');
            setClients(response.data);
            setLoading(false);
        } catch (error) {
            console.error('Erro ao buscar clientes:', error);
            setLoading(false);
        }
    };

    const filteredClients = clients.filter(client =>
        client.nome.toLowerCase().includes(searchTerm.toLowerCase()) ||
        client.codigo.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (client.email && client.email.toLowerCase().includes(searchTerm.toLowerCase()))
    );

    return (
        <div style={styles.container}>
            <header style={styles.header}>
                <div>
                    <h1 style={styles.title}>Painel de <span className="text-gradient">Clientes</span></h1>
                    <p style={styles.subtitle}>Gerencie sua base de clientes e acompanhe as indicações.</p>
                </div>
                <button style={styles.addBtn} className="btn-primary">
                    <Plus size={20} />
                    Novo Cliente
                </button>
            </header>

            <div style={styles.toolbar}>
                <div style={styles.searchBox}>
                    <Search size={18} style={styles.searchIcon} />
                    <input
                        type="text"
                        placeholder="Buscar por nome, código ou email..."
                        style={styles.searchInput}
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                    />
                </div>
            </div>

            <div style={styles.grid}>
                {loading ? (
                    <div style={styles.loading}>Carregando clientes...</div>
                ) : filteredClients.length === 0 ? (
                    <div style={styles.loading}>Nenhum cliente cadastrado.</div>
                ) : (
                    filteredClients.map(client => (
                        <div key={client.id} className="glass-panel" style={styles.card}>
                            <div style={styles.cardHeader}>
                                <div style={styles.avatar}>
                                    {client.nome.charAt(0)}
                                </div>
                                <div style={styles.clientInfo}>
                                    <h3 style={styles.clientName}>{client.nome}</h3>
                                    <span style={styles.clientCode}>#{client.codigo}</span>
                                </div>
                            </div>

                            <div style={styles.cardBody}>
                                <div style={styles.infoLine}>
                                    <Mail size={16} />
                                    <span>{client.email || 'N/A'}</span>
                                </div>
                                <div style={styles.infoLine}>
                                    <Phone size={16} />
                                    <span>{client.telefone || 'N/A'}</span>
                                </div>
                                <div style={styles.infoLine}>
                                    <MapPin size={16} />
                                    <span>{client.cidade || 'Não informada'}</span>
                                </div>
                            </div>

                            <div style={styles.cardFooter}>
                                <div style={styles.stat}>
                                    <div style={styles.statLabel}>Indicações</div>
                                    <div style={styles.statValue}>{client.total_indicacoes}</div>
                                </div>
                                <div style={styles.stat}>
                                    <div style={styles.statLabel}>Compras</div>
                                    <div style={styles.statValue}>{client.total_compras}</div>
                                </div>
                                <button style={styles.viewBtn}>
                                    <ExternalLink size={18} />
                                </button>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
};

const styles = {
    container: {
        padding: '32px',
        animation: 'fadeIn 0.5s ease-out',
    },
    header: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: '32px',
    },
    title: {
        fontSize: '2.5rem',
        margin: 0,
        fontWeight: 700,
    },
    subtitle: {
        color: 'var(--text-secondary)',
        marginTop: '8px',
    },
    addBtn: {
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        padding: '12px 24px',
        borderRadius: 'var(--radius-md)',
        fontSize: '1rem',
        fontWeight: 600,
    },
    toolbar: {
        marginBottom: '32px',
    },
    searchBox: {
        position: 'relative' as const,
        maxWidth: '500px',
    },
    searchIcon: {
        position: 'absolute' as const,
        left: '16px',
        top: '50%',
        transform: 'translateY(-50%)',
        color: 'var(--text-secondary)',
    },
    searchInput: {
        width: '100%',
        padding: '14px 16px 14px 48px',
        background: 'rgba(255,255,255,0.05)',
        border: '1px solid var(--border-glass)',
        borderRadius: 'var(--radius-md)',
        color: 'var(--text-primary)',
        fontSize: '1rem',
        outline: 'none',
        transition: 'all 0.3s',
    },
    grid: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
        gap: '24px',
    },
    loading: {
        gridColumn: '1 / -1',
        textAlign: 'center' as const,
        padding: '48px',
        color: 'var(--text-secondary)',
    },
    card: {
        padding: '24px',
        borderRadius: 'var(--radius-lg)',
        transition: 'transform 0.3s ease',
        cursor: 'pointer',
    },
    cardHeader: {
        display: 'flex',
        alignItems: 'center',
        gap: '16px',
        marginBottom: '20px',
    },
    avatar: {
        width: '56px',
        height: '56px',
        borderRadius: '16px',
        background: 'var(--accent-glow)',
        border: '1px solid var(--accent-primary)',
        color: 'var(--accent-primary)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: '1.5rem',
        fontWeight: 700,
    },
    clientInfo: {
        display: 'flex',
        flexDirection: 'column' as const,
    },
    clientName: {
        margin: 0,
        fontSize: '1.1rem',
        fontWeight: 600,
    },
    clientCode: {
        fontSize: '0.85rem',
        color: 'var(--accent-primary)',
        fontWeight: 500,
    },
    cardBody: {
        display: 'flex',
        flexDirection: 'column' as const,
        gap: '12px',
        marginBottom: '24px',
        padding: '16px',
        background: 'rgba(255,255,255,0.03)',
        borderRadius: '12px',
    },
    infoLine: {
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
        color: 'var(--text-secondary)',
        fontSize: '0.9rem',
    },
    cardFooter: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingTop: '16px',
        borderTop: '1px solid var(--border-glass)',
    },
    stat: {
        textAlign: 'center' as const,
    },
    statLabel: {
        fontSize: '0.75rem',
        color: 'var(--text-secondary)',
        textTransform: 'uppercase' as const,
        marginBottom: '4px',
    },
    statValue: {
        fontSize: '1.25rem',
        fontWeight: 700,
        color: 'var(--text-primary)',
    },
    viewBtn: {
        padding: '10px',
        background: 'transparent',
        border: '1px solid var(--border-glass)',
        borderRadius: '10px',
        color: 'var(--accent-primary)',
        cursor: 'pointer',
        transition: 'all 0.3s',
    }
};

export default Clients;
