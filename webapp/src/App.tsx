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

// Autenticação Falsa provisória para visualização
const isAuthenticated = () => true;

function App() {
  const isAuth = isAuthenticated();

  return (
    <Router>
      <div className="app-container" style={{ display: 'flex', minHeight: '100vh', background: 'var(--bg-primary)' }}>
        <Routes>
          {/* Site Público (Sem Sidebar) */}
          <Route path="/" element={<Home />} />
          <Route path="/login" element={<Login />} />
          <Route path="/rastreio" element={<Tracking />} />

          {/* Área Administrativa (Com Sidebar) */}
          <Route path="/dashboard" element={<AdminLayout isAuth={isAuth}><Dashboard /></AdminLayout>} />
          <Route path="/pedidos" element={<AdminLayout isAuth={isAuth}><Orders /></AdminLayout>} />
          <Route path="/clientes" element={<AdminLayout isAuth={isAuth}><Clients /></AdminLayout>} />
          <Route path="/debug-db" element={<AdminLayout isAuth={isAuth}><DatabaseDebug /></AdminLayout>} />

          <Route path="/entregadores" element={<AdminLayout isAuth={isAuth}><Placeholder title="Entregadores" /></AdminLayout>} />
          <Route path="/whatsapp" element={<AdminLayout isAuth={isAuth}><Placeholder title="Configuração Bot" /></AdminLayout>} />
          <Route path="/relatorios" element={<AdminLayout isAuth={isAuth}><Placeholder title="Relatórios" /></AdminLayout>} />
          <Route path="/configuracoes" element={<AdminLayout isAuth={isAuth}><Placeholder title="Configurações" /></AdminLayout>} />

          <Route path="*" element={<Navigate to="/" />} />
        </Routes>
      </div>
    </Router>
  );
}

const AdminLayout = ({ children, isAuth }: { children: React.ReactNode, isAuth: boolean }) => {
  if (!isAuth) return <Navigate to="/login" />;
  return (
    <div style={{ display: 'flex', width: '100%', minHeight: '100vh' }}>
      <Sidebar />
      <main className="main-content" style={{ flex: 1, padding: '20px', position: 'relative', zIndex: 1 }}>
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
