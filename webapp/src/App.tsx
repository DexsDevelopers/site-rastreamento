import { HashRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Sidebar from './components/Sidebar';
import Dashboard from './pages/Dashboard';
import Login from './pages/Login';
import Orders from './pages/Orders';
import Clients from './pages/Clients';
import Home from './pages/Home';
import Tracking from './pages/Tracking';
import DatabaseDebug from './pages/DatabaseDebug';
import './index.css';

function App() {
  return (
    <Router>
      <div className="app-container" style={{ display: 'flex', minHeight: '100vh', background: 'var(--bg-primary)' }}>
        <Routes>
          {/* Rotas Públicas (Sem Sidebar) */}
          <Route path="/" element={<Home />} />
          <Route path="/login" element={<Login />} />
          <Route path="/rastreio" element={<Tracking />} />

          {/* Rotas Administrativas (Com Sidebar) */}
          <Route path="/dashboard" element={<AdminLayout><Dashboard /></AdminLayout>} />
          <Route path="/pedidos" element={<AdminLayout><Orders /></AdminLayout>} />
          <Route path="/clientes" element={<AdminLayout><Clients /></AdminLayout>} />
          <Route path="/debug-db" element={<AdminLayout><DatabaseDebug /></AdminLayout>} />

          {/* Placeholders */}
          <Route path="/entregadores" element={<AdminLayout><Placeholder title="Entregadores" /></AdminLayout>} />
          <Route path="/whatsapp" element={<AdminLayout><Placeholder title="Configuração Bot" /></AdminLayout>} />
          <Route path="/relatorios" element={<AdminLayout><Placeholder title="Relatórios" /></AdminLayout>} />
          <Route path="/configuracoes" element={<AdminLayout><Placeholder title="Configurações" /></AdminLayout>} />

          {/* Fallback */}
          <Route path="*" element={<Navigate to="/" />} />
        </Routes>
      </div>
    </Router>
  );
}

// Layout que envolve as páginas internas com a Sidebar
const AdminLayout = ({ children }: { children: React.ReactNode }) => {
  return (
    <div style={{ display: 'flex', width: '100%', minHeight: '100vh' }}>
      <Sidebar />
      <main className="main-content" style={{ flex: 1, padding: '20px', overflowY: 'auto' }}>
        {children}
      </main>
    </div>
  );
};

const Placeholder = ({ title }: { title: string }) => (
  <div style={{ padding: 40, animation: 'fadeIn 0.5s ease' }}>
    <h1 style={{ fontSize: '2.5rem', marginBottom: '16px' }}>{title} <span className="text-gradient">(Em breve)</span></h1>
    <p style={{ color: 'var(--text-secondary)' }}>Esta funcionalidade está sendo preparada para sua conta Premium.</p>
  </div>
);

export default App;
