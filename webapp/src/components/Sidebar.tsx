import { NavLink } from 'react-router-dom';
import { Home, Package, Users, Settings, Smartphone, LogOut, BarChart3 } from 'lucide-react';

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
                <NavLink to="/pedidos" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
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
        height: 'calc(100vh - 32px)',
        margin: '16px 0 16px 16px',
        display: 'flex',
        flexDirection: 'column' as const,
        border: '1px solid var(--border-glass)',
        position: 'sticky' as const,
        top: '16px',
        borderRadius: 'var(--radius-lg)'
    },
    logoContainer: {
        padding: '32px 24px',
        borderBottom: '1px solid var(--border-glass)',
    },
    logoClass: {
        margin: 0,
        fontSize: '1.5rem',
    },
    nav: {
        flex: 1,
        padding: '24px 16px',
        display: 'flex',
        flexDirection: 'column' as const,
        gap: '8px',
    },
    link: {
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
        padding: '12px 16px',
        borderRadius: 'var(--radius-md)',
        color: 'var(--text-secondary)',
        textDecoration: 'none',
        transition: 'all 0.3s ease',
        fontWeight: 500,
    },
    linkActive: {
        background: 'var(--accent-glow)',
        color: 'var(--text-primary)',
        borderLeft: '3px solid var(--accent-primary)',
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
