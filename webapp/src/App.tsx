import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Sidebar from './components/Sidebar';
import Dashboard from './pages/Dashboard';
import Login from './pages/Login';
import './index.css';

// Autenticação Falsa provisória para visualização
const isAuthenticated = () => true;

function App() {
  return (
    <Router>
      <div className="app-container">
        {/* Se o usuário estiver autenticado, mostramos a sidebar */}
        {isAuthenticated() && <Sidebar />}

        <main className="main-content">
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route
              path="/"
              element={isAuthenticated() ? <Dashboard /> : <Navigate to="/login" />}
            />
            {/* Adicionaremos mais rotas aqui depois */}
          </Routes>
        </main>
      </div>
    </Router>
  );
}

export default App;
