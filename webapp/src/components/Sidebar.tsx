import { NavLink } from 'react-router-dom';
import { Home, Package, Package2, Users, Settings, Smartphone, LogOut, BarChart3, Database, MessageSquare } from 'lucide-react';

const Sidebar = () => {
    return (
        <aside style={styles.sidebar} className="glass-panel">
            <div style={styles.logoContainer}>
                <h2 style={styles.logoClass}>
                    <span className="text-gradient">Loggi</span> Admin
                </h2>
            </div>

            <nav style={styles.nav}>
                <NavLink to="/dashboard" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Home size={20} />
                    <span>Dashboard</span>
                </NavLink>
                <NavLink to="/status" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Database size={20} />
                    <span>Status DB</span>
                </NavLink>
                <NavLink to="/admin" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Package2 size={20} />
                    <span>Rastreios</span>
                </NavLink>
                <NavLink to="/pedidos-pendentes" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Package size={20} />
                    <span>Pedidos</span>
                </NavLink>
                <NavLink to="/clientes" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Users size={20} />
                    <span>Clientes</span>
                </NavLink>
                <NavLink to="/entregadores" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Users size={20} />
                    <span>Entregadores</span>
                </NavLink>
                <NavLink to="/whatsapp" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Smartphone size={20} />
                    <span>Bot WhatsApp</span>
                </NavLink>
                <NavLink to="/whatsapp-templates" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <MessageSquare size={20} />
                    <span>Modelos de Mensagens</span>
                </NavLink>
                <NavLink to="/relatorios" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <BarChart3 size={20} />
                    <span>Relatórios</span>
                </NavLink>
                <NavLink to="/configuracoes" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Settings size={20} />
                    <span>Configurações</span>
                </NavLink>
            </nav>

            <div style={styles.footer}>
                <button style={styles.logoutBtn}>
                    <LogOut size={20} />
                    <span>Sair do Sistema</span>
                </button>
            </div>
        </aside>
    );
};

const styles = {
    sidebar: {
        width: '280px',
        flexShrink: 0,
        height: 'calc(100vh - 32px)',
        margin: '16px 0 16px 16px',
        display: 'flex',
        flexDirection: 'column' as const,
        border: '1px solid var(--border-glass)',
        background: 'rgba(10, 10, 12, 0.65)',
        backdropFilter: 'blur(20px)',
        borderRadius: 'var(--radius-lg)'
    },
    logoContainer: {
        padding: '24px 20px',
        borderBottom: '1px solid var(--border-glass)',
    },
    logoClass: {
        margin: 0,
        fontSize: '1.5rem',
    },
    nav: {
        flex: 1,
        minHeight: 0,
        padding: '16px 14px',
        display: 'flex',
        flexDirection: 'column' as const,
        gap: '4px',
        overflowY: 'auto' as const,
    },
    link: {
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
        padding: '10px 14px',
        borderRadius: '8px',
        color: 'var(--text-secondary)',
        textDecoration: 'none',
        transition: 'all 0.2s cubic-bezier(0.4, 0, 0.2, 1)',
        fontWeight: 500,
        fontSize: '0.95rem',
        borderLeft: '3px solid transparent',
    },
    linkActive: {
        background: 'linear-gradient(90deg, rgba(99, 102, 241, 0.15) 0%, transparent 100%)',
        color: '#8b5cf6',
        borderLeft: '3px solid #6366f1',
        fontWeight: 600,
    },
    footer: {
        padding: '24px',
        borderTop: '1px solid var(--border-glass)',
    },
    logoutBtn: {
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
        background: 'transparent',
        border: 'none',
        color: 'var(--danger)',
        cursor: 'pointer',
        width: '100%',
        padding: '12px',
        borderRadius: 'var(--radius-md)',
        transition: 'background 0.3s',
        fontWeight: 500,
        fontSize: '1rem',
    }
};

export default Sidebar;
