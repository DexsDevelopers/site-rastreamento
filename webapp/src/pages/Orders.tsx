import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Package, Search, Plus, Filter, Eye, Edit, Trash2 } from 'lucide-react';

interface Order {
    id: number;
    codigo: string;
    cidade: string;
    status_atual: string;
    titulo: string;
    data: string;
    cor: string;
    prioridade: number;
}

const Orders: React.FC = () => {
    const [orders, setOrders] = useState<Order[]>([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        fetchOrders();
    }, []);

    const fetchOrders = async () => {
        try {
            const response = await axios.get('/api/orders');
            setOrders(response.data);
            setLoading(false);
        } catch (error) {
            console.error('Erro ao buscar pedidos:', error);
            setLoading(false);
        }
    };

    const filteredOrders = orders.filter(order =>
        order.codigo.toLowerCase().includes(searchTerm.toLowerCase()) ||
        order.titulo.toLowerCase().includes(searchTerm.toLowerCase()) ||
        order.cidade.toLowerCase().includes(searchTerm.toLowerCase())
    );

    return (
        <div style={styles.container}>
            <header style={styles.header}>
                <div>
                    <h1 style={styles.title}>Gerenciar <span className="text-gradient">Pedidos</span></h1>
                    <p style={styles.subtitle}>Acompanhe e gerencie todos os rastreios em tempo real.</p>
                </div>
                <button style={styles.addBtn} className="btn-primary">
                    <Plus size={20} />
                    Novo Pedido
                </button>
            </header>

            <div className="glass-panel" style={styles.tableContainer}>
                <div style={styles.toolbar}>
                    <div style={styles.searchBox}>
                        <Search size={18} style={styles.searchIcon} />
                        <input
                            type="text"
                            placeholder="Buscar por código, título ou cidade..."
                            style={styles.searchInput}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                    <button style={styles.filterBtn}>
                        <Filter size={18} />
                        Filtros
                    </button>
                </div>

                <div style={styles.tableWrapper}>
                    <table style={styles.table}>
                        <thead>
                            <tr style={styles.theadTr}>
                                <th style={styles.th}>CÓDIGO</th>
                                <th style={styles.th}>DATA</th>
                                <th style={styles.th}>ORIGEM/DESTINO</th>
                                <th style={styles.th}>STATUS</th>
                                <th style={styles.th}>PRIORIDADE</th>
                                <th style={styles.th}>AÇÕES</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr>
                                    <td colSpan={6} style={styles.loadingCell}>Carregando pedidos...</td>
                                </tr>
                            ) : filteredOrders.length === 0 ? (
                                <tr>
                                    <td colSpan={6} style={styles.loadingCell}>Nenhum pedido encontrado.</td>
                                </tr>
                            ) : (
                                filteredOrders.map(order => (
                                    <tr key={order.id} style={styles.tr}>
                                        <td style={styles.td}>
                                            <div style={styles.codeCell}>
                                                <Package size={16} color="var(--accent-primary)" />
                                                <strong>{order.codigo}</strong>
                                            </div>
                                        </td>
                                        <td style={styles.td}>
                                            {new Date(order.data).toLocaleDateString('pt-BR')}
                                        </td>
                                        <td style={styles.td}>{order.cidade}</td>
                                        <td style={styles.td}>
                                            <span style={{
                                                ...styles.statusBadge,
                                                backgroundColor: order.cor ? `${order.cor}22` : 'var(--accent-glow)',
                                                color: order.cor || 'var(--accent-primary)',
                                                borderColor: order.cor || 'var(--accent-primary)'
                                            }}>
                                                {order.status_atual}
                                            </span>
                                        </td>
                                        <td style={styles.td}>
                                            {order.prioridade ? (
                                                <span style={styles.priorityHigh}>Alta</span>
                                            ) : (
                                                <span style={styles.priorityNormal}>Normal</span>
                                            )}
                                        </td>
                                        <td style={styles.td}>
                                            <div style={styles.actions}>
                                                <button style={styles.actionBtn} title="Ver"><Eye size={16} /></button>
                                                <button style={styles.actionBtn} title="Editar"><Edit size={16} /></button>
                                                <button style={{ ...styles.actionBtn, color: 'var(--danger)' }} title="Excluir"><Trash2 size={16} /></button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
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
        fontSize: '1.1rem',
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
    tableContainer: {
        borderRadius: 'var(--radius-lg)',
        overflow: 'hidden',
    },
    toolbar: {
        padding: '24px',
        display: 'flex',
        justifyContent: 'space-between',
        gap: '24px',
        borderBottom: '1px solid var(--border-glass)',
    },
    searchBox: {
        flex: 1,
        position: 'relative' as const,
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
        padding: '12px 16px 12px 48px',
        background: 'rgba(255,255,255,0.05)',
        border: '1px solid var(--border-glass)',
        borderRadius: 'var(--radius-md)',
        color: 'var(--text-primary)',
        fontSize: '1rem',
        outline: 'none',
        transition: 'all 0.3s',
    },
    filterBtn: {
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        padding: '12px 20px',
        background: 'rgba(255,255,255,0.05)',
        border: '1px solid var(--border-glass)',
        borderRadius: 'var(--radius-md)',
        color: 'var(--text-primary)',
        cursor: 'pointer',
        fontWeight: 500,
    },
    tableWrapper: {
        overflowX: 'auto' as const,
    },
    table: {
        width: '100%',
        borderCollapse: 'collapse' as const,
        textAlign: 'left' as const,
    },
    theadTr: {
        background: 'rgba(255,255,255,0.02)',
    },
    th: {
        padding: '16px 24px',
        fontSize: '0.85rem',
        textTransform: 'uppercase' as const,
        letterSpacing: '0.05em',
        color: 'var(--text-secondary)',
        fontWeight: 600,
        borderBottom: '1px solid var(--border-glass)',
    },
    tr: {
        borderBottom: '1px solid var(--border-glass)',
        transition: 'background 0.3s',
        cursor: 'pointer',
    },
    td: {
        padding: '16px 24px',
        color: 'var(--text-primary)',
    },
    codeCell: {
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
    },
    statusBadge: {
        padding: '6px 14px',
        borderRadius: '20px',
        fontSize: '0.85rem',
        fontWeight: 600,
        border: '1px solid transparent',
    },
    priorityHigh: {
        color: 'var(--danger)',
        fontWeight: 600,
        display: 'flex',
        alignItems: 'center',
        gap: '6px',
    },
    priorityNormal: {
        color: 'var(--text-secondary)',
    },
    loadingCell: {
        padding: '48px',
        textAlign: 'center' as const,
        color: 'var(--text-secondary)',
    },
    actions: {
        display: 'flex',
        gap: '8px',
    },
    actionBtn: {
        padding: '8px',
        background: 'rgba(255,255,255,0.05)',
        border: 'none',
        borderRadius: '8px',
        color: 'var(--text-secondary)',
        cursor: 'pointer',
        transition: 'all 0.3s',
    }
};

export default Orders;
