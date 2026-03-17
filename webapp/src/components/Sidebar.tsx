import { NavLink } from 'react-router-dom';
import { Home, Package, Package2, Users, Settings, Smartphone, LogOut, BarChart3, Database, MessageSquare, X } from 'lucide-react';
import { SYSTEM_VERSION } from '../constants';

interface SidebarProps {
    mobileOpen?: boolean;
    closeMobile?: () => void;
    isCollapsed: boolean;
}

const Sidebar = ({ mobileOpen, closeMobile, isCollapsed }: SidebarProps) => {
    const sidebarWidth = isCollapsed ? '80px' : '280px';

    return (
        <aside
            style={{ ...styles.sidebar, width: sidebarWidth }}
            className={`glass-panel admin-sidebar ${mobileOpen ? 'open' : ''} ${isCollapsed ? 'collapsed' : ''}`}
        >
            <div style={styles.logoContainer}>
                {!isCollapsed && (
                    <h2 style={styles.logoClass}>
                        <span className="text-gradient">Loggi</span> Admin
                    </h2>
                )}
                {isCollapsed && (
                    <div style={{ display: 'flex', justifyContent: 'center', width: '100%', padding: '4px 0' }}>
                        <span style={{ fontWeight: 900, fontSize: '1.4rem', color: 'var(--accent-primary)' }}>L</span>
                    </div>
                )}
                {closeMobile && (
                    <button
                        className="mobile-close-btn"
                        onClick={closeMobile}
                        style={{
                            background: 'none',
                            border: 'none',
                            color: 'var(--text-primary)',
                            padding: '4px',
                            cursor: 'pointer'
                        }}
                    >
                        <X size={24} />
                    </button>
                )}
            </div>

            <nav style={styles.nav}>
                <NavLink onClick={closeMobile} title="Dashboard" to="/dashboard" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Home size={20} />
                    {!isCollapsed && <span>Dashboard</span>}
                </NavLink>
                <NavLink onClick={closeMobile} title="Status DB" to="/status" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Database size={20} />
                    {!isCollapsed && <span>Status DB</span>}
                </NavLink>
                <NavLink onClick={closeMobile} title="Rastreios" to="/admin" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Package2 size={20} />
                    {!isCollapsed && <span>Rastreios</span>}
                </NavLink>
                <NavLink onClick={closeMobile} title="Pedidos" to="/pedidos-pendentes" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Package size={20} />
                    {!isCollapsed && <span>Pedidos</span>}
                </NavLink>
                <NavLink onClick={closeMobile} title="Clientes" to="/clientes" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Users size={20} />
                    {!isCollapsed && <span>Clientes</span>}
                </NavLink>
                <NavLink onClick={closeMobile} title="Entregadores" to="/entregadores" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Users size={20} />
                    {!isCollapsed && <span>Entregadores</span>}
                </NavLink>
                <NavLink onClick={closeMobile} title="Bot WhatsApp" to="/whatsapp" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Smartphone size={20} />
                    {!isCollapsed && <span>Bot WhatsApp</span>}
                </NavLink>
                <NavLink onClick={closeMobile} title="Modelos" to="/whatsapp-templates" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <MessageSquare size={20} />
                    {!isCollapsed && <span>Modelos</span>}
                </NavLink>
                <NavLink onClick={closeMobile} title="Relatórios" to="/relatorios" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <BarChart3 size={20} />
                    {!isCollapsed && <span>Relatórios</span>}
                </NavLink>
                <NavLink onClick={closeMobile} title="Configurações" to="/configuracoes" style={({ isActive }) => isActive ? { ...styles.link, ...styles.linkActive } : styles.link}>
                    <Settings size={20} />
                    {!isCollapsed && <span>Configurações</span>}
                </NavLink>
            </nav>

            <div style={styles.footer}>
                <button style={styles.logoutBtn} title="Sair">
                    <LogOut size={20} />
                    {!isCollapsed && <span>Sair do Sistema</span>}
                </button>
                <div style={{
                    marginTop: '12px',
                    paddingTop: '12px',
                    borderTop: '1px solid rgba(0,0,0,0.03)',
                    textAlign: 'center',
                    fontSize: '0.7rem',
                    color: 'var(--text-muted)',
                    opacity: 0.8
                }}>
                    {isCollapsed ? `v${SYSTEM_VERSION}` : `Versão do Sistema: ${SYSTEM_VERSION}`}
                </div>
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
        background: 'rgba(255, 255, 255, 0.75)',
        backdropFilter: 'blur(24px)',
        borderRadius: 'var(--radius-lg)',
        boxShadow: '0 8px 32px rgba(0,40,120,0.08)',
        transition: 'width 0.4s cubic-bezier(0.16, 1, 0.3, 1)',
        position: 'relative' as const,
    },
    logoContainer: {
        padding: '16px 20px',
        borderBottom: '1px solid var(--border-glass)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        height: '64px', // Match header height
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
        justifyContent: 'center' as const,
        minHeight: '44px',
    },
    linkActive: {
        background: 'linear-gradient(90deg, rgba(0, 85, 255, 0.08) 0%, transparent 100%)',
        color: '#0055ff',
        borderLeft: '3px solid #0055ff',
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
