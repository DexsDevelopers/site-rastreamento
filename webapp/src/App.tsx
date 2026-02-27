import { HashRouter as Router, Routes, Route, Navigate, Outlet } from 'react-router-dom';
import Sidebar from './components/Sidebar';
import Dashboard from './pages/Dashboard';
import Login from './pages/Login';
import LoginCliente from './pages/LoginCliente';
import Orders from './pages/Orders';
import Clients from './pages/Clients';
import Home from './pages/Home';
import Tracking from './pages/Tracking';
import Sobre from './pages/Sobre';
import Pedido from './pages/Pedido';
import ParaVoce from './pages/ParaVoce';
import ParaEmpresas from './pages/ParaEmpresas';
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

function App() {
  return (
    <Router>
      <Routes>
        {/* Rotas Públicas */}
        <Route path="/" element={<Home />} />
        <Route path="/entrar" element={<LoginCliente />} />
        <Route path="/login" element={<Login />} />
        <Route path="/rastreio" element={<Tracking />} />
        <Route path="/sobre" element={<Sobre />} />
        <Route path="/pedido" element={<Pedido />} />
        <Route path="/para-voce" element={<ParaVoce />} />
        <Route path="/para-empresas" element={<ParaEmpresas />} />

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
  return (
    <div className="app-container" style={{ display: 'flex', width: '100%', height: '100vh', background: 'var(--bg-primary)', overflow: 'hidden' }}>
      <Sidebar />
      <main className="main-content" style={{ flex: 1, padding: '20px', overflowY: 'auto' }}>
        <Outlet />
      </main>
    </div>
  );
};


export default App;
