import { useState } from 'react';
import { HashRouter as Router, Routes, Route, Navigate, Outlet } from 'react-router-dom';
import { Menu } from 'lucide-react';
import Sidebar from './components/Sidebar';
import Dashboard from './pages/Dashboard';
import Login from './pages/Login';
import LoginCliente from './pages/LoginCliente';
import Orders from './pages/Orders';
import Clients from './pages/Clients';
import Home from './pages/Home';
import Tracking from './pages/Tracking';
import LoggiPro from './pages/LoggiPro';
import About from './pages/About';
import Careers from './pages/Careers';
import HelpCenter from './pages/HelpCenter';
import TermsOfUse from './pages/TermsOfUse';
import Pedido from './pages/Pedido';
import ParaVoce from './pages/ParaVoce';
import ParaEmpresas from './pages/ParaEmpresas';
import ApiEcommerce from './pages/ApiEcommerce';
import DatabaseDebug from './pages/DatabaseDebug';
import AdminPanel from './pages/AdminPanel';
import PedidosPendentes from './pages/PedidosPendentes';
import DatabaseStatus from './pages/DatabaseStatus';
import './index.css';

import Entregadores from './pages/Entregadores';
import WhatsAppConfig from './pages/WhatsAppConfig';
import Reports from './pages/Reports';
import Settings from './pages/Settings';
import WhatsAppTemplates from './pages/WhatsAppTemplates';

import Profile from './pages/Profile';
import ScrollToTop from './components/ScrollToTop';
import ChatBot from './components/ChatBot';

function App() {
  return (
    <Router>
      <ScrollToTop />
      <Routes>
        {/* Rotas Públicas */}
        <Route path="/" element={<Home />} />
        <Route path="/entrar" element={<LoginCliente />} />
        <Route path="/login" element={<Login />} />
        <Route path="/rastreio" element={<Tracking />} />
        <Route path="/sobre" element={<About />} />
        <Route path="/pedido" element={<Pedido />} />
        <Route path="/para-voce" element={<ParaVoce />} />
        <Route path="/para-empresas" element={<ParaEmpresas />} />
        <Route path="/api-ecommerce" element={<ApiEcommerce />} />
        <Route path="/loggi-pro" element={<LoggiPro />} />
        <Route path="/carreiras" element={<Careers />} />
        <Route path="/ajuda" element={<HelpCenter />} />
        <Route path="/termos" element={<TermsOfUse />} />
        <Route path="/perfil" element={<Profile />} />

        {/* Rotas Administrativas com Layout Fixo */}
        <Route element={<AdminLayout />}>
          <Route path="/dashboard" element={<Dashboard />} />
          <Route path="/admin" element={<AdminPanel />} />
          <Route path="/pedidos-pendentes" element={<PedidosPendentes />} />
          <Route path="/pedidos" element={<Orders />} />
          <Route path="/clientes" element={<Clients />} />
          <Route path="/status" element={<DatabaseStatus />} />
          <Route path="/debug-db" element={<DatabaseDebug />} />
          <Route path="/entregadores" element={<Entregadores />} />
          <Route path="/whatsapp" element={<WhatsAppConfig />} />
          <Route path="/relatorios" element={<Reports />} />
          <Route path="/configuracoes" element={<Settings />} />
          <Route path="/whatsapp-templates" element={<WhatsAppTemplates />} />
        </Route>

        {/* Fallback */}
        <Route path="*" element={<Navigate to="/" />} />
      </Routes>
      <ChatBot />
    </Router>
  );
}

const AdminLayout = () => {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [isCollapsed, setIsCollapsed] = useState(true); // Closed by default on desktop

  return (
    <div className="app-container" style={{ display: 'flex', flexDirection: 'column', width: '100%', height: '100vh', background: 'var(--bg-primary)', overflow: 'hidden' }}>

      {/* Top Bar para Mobile & Desktop Toggle */}
      <div className="admin-layout-header" style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: '12px 20px',
        background: 'rgba(10, 10, 12, 0.95)',
        borderBottom: '1px solid rgba(255,255,255,0.06)',
        zIndex: 90
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
          <button
            onClick={() => {
              if (window.innerWidth < 1024) {
                setMobileMenuOpen(true);
              } else {
                setIsCollapsed(!isCollapsed);
              }
            }}
            style={{
              background: 'rgba(255,255,255,0.05)',
              border: '1px solid rgba(255,255,255,0.1)',
              borderRadius: '8px',
              padding: '8px',
              color: '#fff',
              cursor: 'pointer',
              display: 'flex',
              alignItems: 'center'
            }}
            title="Menu"
          >
            <Menu size={20} />
          </button>
          <h2 style={{ margin: 0, fontSize: '1.2rem' }}>
            <span className="text-gradient">LOGGI</span> Admin
          </h2>
        </div>

        {/* Espaço para perfil ou outras infos na header se necessário */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
          {/* ... */}
        </div>
      </div>

      <div style={{ display: 'flex', flex: 1, overflow: 'hidden', position: 'relative', width: '100%' }}>
        {/* Overlay Escuro no Mobile */}
        {mobileMenuOpen && (
          <div
            className="admin-sidebar-overlay"
            onClick={() => setMobileMenuOpen(false)}
            style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.6)', backdropFilter: 'blur(4px)', zIndex: 998 }}
          />
        )}

        {/* Sidebar com props controladas pelo Layout */}
        <Sidebar
          mobileOpen={mobileMenuOpen}
          closeMobile={() => setMobileMenuOpen(false)}
          isCollapsed={isCollapsed}
          setIsCollapsed={setIsCollapsed}
        />

        <main className="main-content" style={{ flex: 1, padding: '20px', overflowY: 'auto', overflowX: 'hidden' }}>
          <Outlet />
        </main>
      </div>
    </div>
  );
};

export default App;
