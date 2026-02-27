import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Sidebar from './components/Sidebar';
import Dashboard from './pages/Dashboard';
import Login from './pages/Login';
import Orders from './pages/Orders';
import Clients from './pages/Clients';
import Home from './pages/Home';
import Tracking from './pages/Tracking';
import './index.css';

// Autenticação Falsa provisória para visualização
const isAuthenticated = () => true;

function App() {
  const isAuth = isAuthenticated();

  return (
    <Router>
      <div className="app-container">
        {/* Renderiza a Main ou o Site Público */}
        <Routes>
          {/* Site Público */}
          <Route path="/" element={<Home />} />
          <Route path="/login" element={<Login />} />
          <Route path="/rastreio" element={<Tracking />} />

          {/* Painel Administrativo (Protect logic) */}
          <Route path="/*" element={
            isAuth ? (
              <div style={{ display: 'flex', width: '100%' }}>
                <Sidebar />
                <main className="main-content">
                  <Routes>
                    <Route path="/dashboard" element={<Dashboard />} />
                    <Route path="/pedidos" element={<Orders />} />
                    <Route path="/clientes" element={<Clients />} />
                    {/* Placeholders */}
                    <Route path="/entregadores" element={<div style={{ padding: 40 }}><h1>Entregadores (Em breve)</h1></div>} />
                    <Route path="/whatsapp" element={<div style={{ padding: 40 }}><h1>Configuração do Bot (Em breve)</h1></div>} />
                    <Route path="/relatorios" element={<div style={{ padding: 40 }}><h1>Relatórios Gerenciais (Em breve)</h1></div>} />
                    <Route path="/configuracoes" element={<div style={{ padding: 40 }}><h1>Configurações do Sistema (Em breve)</h1></div>} />
                  </Routes>
                </main>
              </div>
            ) : <Navigate to="/login" />
          } />
        </Routes>
      </div>
    </Router>
  );
}


export default App;
