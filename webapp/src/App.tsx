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

import ScrollToTop from './components/ScrollToTop';

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
    </Router>
  );
}

const AdminLayout = () => {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  return (
    <div className="app-container" style={{ display: 'flex', width: '100%', height: '100vh', background: 'var(--bg-primary)', overflow: 'hidden' }}>

      {/* Botão Hambúrguer Mobile */}
      <button
        className="mobile-menu-btn"
        onClick={() => setMobileMenuOpen(true)}
        style={{ position: 'absolute', top: '24px', left: '24px', zIndex: 10, background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '12px', padding: '10px', color: '#fff', cursor: 'pointer', display: 'flex' }}
      >
        <Menu size={24} />
      </button>

      {/* Overlay Escuro no Mobile */}
      {mobileMenuOpen && (
        <div
          className="admin-sidebar-overlay"
          onClick={() => setMobileMenuOpen(false)}
          style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.6)', backdropFilter: 'blur(4px)', zIndex: 998 }}
        />
      )}

      {/* Sidebar com prop para controlar visibilidade/classes */}
      <Sidebar mobileOpen={mobileMenuOpen} closeMobile={() => setMobileMenuOpen(false)} />

      <main className="main-content" style={{ flex: 1, padding: '20px', overflowY: 'auto' }}>
        <Outlet />
      </main>
    </div>
  );
};

export default App;
