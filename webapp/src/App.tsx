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
import DatabaseStatus from './pages/DatabaseStatus';
import './index.css';

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
          <Route path="/pedidos" element={<Orders />} />
          <Route path="/clientes" element={<Clients />} />
          <Route path="/status" element={<DatabaseStatus />} />
          <Route path="/debug-db" element={<DatabaseDebug />} />
          <Route path="/entregadores" element={<Placeholder title="Entregadores" />} />
          <Route path="/whatsapp" element={<Placeholder title="Configuração Bot" />} />
          <Route path="/relatorios" element={<Placeholder title="Relatórios" />} />
          <Route path="/configuracoes" element={<Placeholder title="Configurações" />} />
        </Route>

        {/* Fallback */}
        <Route path="*" element={<Navigate to="/" />} />
      </Routes>
    </Router>
  );
}

const AdminLayout = () => {
  return (
    <div className="app-container" style={{ display: 'flex', width: '100%', minHeight: '100vh', background: 'var(--bg-primary)' }}>
      <Sidebar />
      <main className="main-content" style={{ flex: 1, padding: '20px', overflowY: 'auto' }}>
        <Outlet />
      </main>
    </div>
  );
};

const Placeholder = ({ title }: { title: string }) => (
  <div style={{ padding: 40, animation: 'fadeIn 0.5s ease' }}>
    <h1 style={{ fontSize: '2.5rem', marginBottom: '16px' }}>{title} <span className="text-gradient">(Em breve)</span></h1>
    <p style={{ color: 'var(--text-secondary)' }}>Esta funcionalidade está sendo preparada.</p>
  </div>
);

export default App;
