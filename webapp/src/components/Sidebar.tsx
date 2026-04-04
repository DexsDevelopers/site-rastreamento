import { NavLink, useNavigate } from 'react-router-dom';
import { Home, Package, Package2, Users, Smartphone, LogOut, BarChart3, Database, MessageSquare, X, Truck, Zap, Shield } from 'lucide-react';
import { SYSTEM_VERSION } from '../constants';

interface SidebarProps {
    mobileOpen?: boolean;
    closeMobile?: () => void;
    isCollapsed: boolean;
}

const navGroups = [
    {
        label: 'VISÃO GERAL',
        items: [
            { to: '/dashboard', icon: Home, label: 'Dashboard' },
            { to: '/relatorios', icon: BarChart3, label: 'Relatórios' },
        ]
    },
    {
        label: 'OPERAÇÕES',
        items: [
            { to: '/admin', icon: Package2, label: 'Rastreios' },
            { to: '/pedidos-pendentes', icon: Package, label: 'Pedidos' },
            { to: '/clientes', icon: Users, label: 'Clientes' },
            { to: '/entregadores', icon: Truck, label: 'Entregadores' },
        ]
    },
    {
        label: 'COMUNICAÇÃO',
        items: [
            { to: '/whatsapp', icon: Smartphone, label: 'Bot WhatsApp' },
            { to: '/whatsapp-templates', icon: MessageSquare, label: 'Modelos' },
        ]
    },
    {
        label: 'SISTEMA',
        items: [
            { to: '/status', icon: Database, label: 'Status DB' },
            { to: '/configuracoes', icon: Shield, label: 'Configurações' },
        ]
    },
];

const Sidebar = ({ mobileOpen, closeMobile, isCollapsed }: SidebarProps) => {
    const navigate = useNavigate();
    const sidebarWidth = isCollapsed ? '0px' : '260px';

    const handleLogout = () => {
        localStorage.removeItem('adminToken');
        navigate('/login');
    };

    return (
        <>
            <style>{`
                .sb-overlay { display:none; }
                @media (max-width: 900px) {
                    .admin-sidebar {
                        position: fixed !important;
                        top: 0 !important; left: 0 !important;
                        height: 100vh !important;
                        margin: 0 !important;
                        border-radius: 0 20px 20px 0 !important;
                        z-index: 1000;
                        transform: translateX(-100%);
                        transition: transform 0.3s cubic-bezier(0.16,1,0.3,1) !important;
                        width: 260px !important;
                        opacity: 1 !important;
                        visibility: visible !important;
                        pointer-events: auto !important;
                    }
                    .admin-sidebar.open {
                        transform: translateX(0) !important;
                    }
                    .sb-overlay { display: block; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; backdrop-filter: blur(2px); }
                }
                .sb-link { display:flex; align-items:center; gap:11px; padding:9px 12px; border-radius:10px; color:#94a3b8; text-decoration:none; font-weight:500; font-size:0.875rem; transition:all 0.18s ease; border:1px solid transparent; white-space:nowrap; }
                .sb-link:hover { background:rgba(255,255,255,0.06); color:#e2e8f0; border-color:rgba(255,255,255,0.07); }
                .sb-link.active { background:linear-gradient(135deg,rgba(59,130,246,0.2),rgba(99,102,241,0.15)); color:#60a5fa; border-color:rgba(59,130,246,0.3); font-weight:600; box-shadow:0 2px 12px rgba(59,130,246,0.15); }
                .sb-link.active svg { color:#60a5fa; }
                .sb-link svg { flex-shrink:0; opacity:0.85; }
                .sb-link:hover svg { opacity:1; }
                .sb-group-label { font-size:0.65rem; font-weight:700; letter-spacing:0.1em; color:#475569; padding:0 12px; margin:4px 0 2px; text-transform:uppercase; }
                .sb-logout:hover { background:rgba(239,68,68,0.12) !important; color:#f87171 !important; border-color:rgba(239,68,68,0.2) !important; }
                .sb-nav::-webkit-scrollbar { width:3px; }
                .sb-nav::-webkit-scrollbar-track { background:transparent; }
                .sb-nav::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.1); border-radius:10px; }
            `}</style>

            {mobileOpen && <div className="sb-overlay" onClick={closeMobile} />}

            <aside
                className={`admin-sidebar ${mobileOpen ? 'open' : ''}`}
                style={{
                    width: sidebarWidth,
                    flexShrink: 0,
                    height: 'calc(100vh - 24px)',
                    margin: '12px 0 12px 12px',
                    display: 'flex',
                    flexDirection: 'column',
                    background: 'linear-gradient(180deg, #0f172a 0%, #0d1424 60%, #0a0f1e 100%)',
                    borderRadius: '16px',
                    border: '1px solid rgba(255,255,255,0.07)',
                    boxShadow: '0 20px 60px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.05)',
                    overflow: 'hidden',
                    transition: 'width 0.35s cubic-bezier(0.16,1,0.3,1), opacity 0.3s, margin 0.35s',
                    opacity: isCollapsed ? 0 : 1,
                    visibility: isCollapsed ? 'hidden' : 'visible',
                    pointerEvents: isCollapsed ? 'none' : 'auto',
                    position: 'relative',
                }}
            >
                {/* Logo */}
                <div style={{ padding: '20px 18px 16px', borderBottom: '1px solid rgba(255,255,255,0.06)', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '10px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <div style={{ width: '34px', height: '34px', borderRadius: '10px', background: 'linear-gradient(135deg, #3b82f6, #6366f1)', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 4px 12px rgba(59,130,246,0.4)', flexShrink: 0 }}>
                            <Zap size={18} color="#fff" />
                        </div>
                        <div>
                            <div style={{ fontWeight: 800, fontSize: '1.05rem', color: '#f1f5f9', letterSpacing: '-0.02em', lineHeight: 1.1 }}>LOGGI</div>
                            <div style={{ fontSize: '0.62rem', color: '#64748b', fontWeight: 600, letterSpacing: '0.12em', textTransform: 'uppercase' }}>Admin Panel</div>
                        </div>
                    </div>
                    {closeMobile && (
                        <button onClick={closeMobile} style={{ background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.08)', borderRadius: '8px', width: '30px', height: '30px', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer', color: '#94a3b8' }}>
                            <X size={16} />
                        </button>
                    )}
                </div>

                {/* Nav */}
                <nav className="sb-nav" style={{ flex: 1, overflowY: 'auto', padding: '12px 10px', display: 'flex', flexDirection: 'column', gap: '2px' }}>
                    {navGroups.map(group => (
                        <div key={group.label} style={{ marginBottom: '6px' }}>
                            <div className="sb-group-label">{group.label}</div>
                            {group.items.map(({ to, icon: Icon, label }) => (
                                <NavLink
                                    key={to}
                                    to={to}
                                    onClick={closeMobile}
                                    className={({ isActive }) => `sb-link${isActive ? ' active' : ''}`}
                                >
                                    <Icon size={17} />
                                    <span>{label}</span>
                                </NavLink>
                            ))}
                        </div>
                    ))}
                </nav>

                {/* Footer */}
                <div style={{ padding: '12px 10px 16px', borderTop: '1px solid rgba(255,255,255,0.06)' }}>
                    <button
                        onClick={handleLogout}
                        className="sb-link sb-logout"
                        style={{ width: '100%', background: 'transparent', border: '1px solid transparent', cursor: 'pointer', color: '#ef4444' }}
                    >
                        <LogOut size={17} />
                        <span>Sair do Sistema</span>
                    </button>
                    <div style={{ marginTop: '10px', textAlign: 'center', fontSize: '0.62rem', color: '#334155', fontWeight: 500, letterSpacing: '0.05em' }}>
                        v{SYSTEM_VERSION}
                    </div>
                </div>
            </aside>
        </>
    );
};

export default Sidebar;
