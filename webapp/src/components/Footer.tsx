import React from 'react';
import { Link } from 'react-router-dom';
import { Heart } from 'lucide-react';

const Footer: React.FC = () => {
    return (
        <footer className="site-footer">
            <style>{`
                .site-footer {
                    background: rgba(10, 10, 12, 0.4);
                    backdrop-filter: blur(20px);
                    border-top: 1px solid rgba(255,255,255,0.05);
                    padding: 80px 5% 40px;
                    color: white;
                    position: relative;
                    z-index: 10;
                }
                .footer-inner {
                    max-width: 1200px;
                    margin: 0 auto;
                    display: grid;
                    grid-template-columns: 1.5fr 2fr;
                    gap: 80px;
                }
                .footer-brand-col p {
                    color: rgba(255,255,255,0.4);
                    margin-top: 24px;
                    line-height: 1.7;
                    font-size: 0.95rem;
                    max-width: 320px;
                }
                .footer-links-wrap {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 40px;
                }
                .footer-col h4 {
                    font-size: 1rem;
                    font-weight: 700;
                    margin-bottom: 24px;
                    color: white;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                .footer-col a {
                    display: block;
                    color: rgba(255,255,255,0.4);
                    text-decoration: none;
                    margin-bottom: 12px;
                    font-size: 0.95rem;
                    transition: all 0.3s;
                }
                .footer-col a:hover {
                    color: #818cf8;
                    transform: translateX(5px);
                }
                .footer-bottom {
                    max-width: 1200px;
                    margin: 80px auto 0;
                    padding-top: 40px;
                    border-top: 1px solid rgba(255,255,255,0.05);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    color: rgba(255,255,255,0.3);
                    font-size: 0.9rem;
                }
                .footer-bottom-info {
                    display: flex;
                    align-items: center;
                    gap: 24px;
                }
                @media (max-width: 900px) {
                    .footer-inner { grid-template-columns: 1fr; gap: 60px; }
                    .footer-links-wrap { grid-template-columns: repeat(2, 1fr); }
                }
                @media (max-width: 600px) {
                    .footer-links-wrap { grid-template-columns: 1fr; }
                    .footer-bottom { flex-direction: column; gap: 20px; text-align: center; }
                }

                .logo-link {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    text-decoration: none;
                    color: white;
                }
                .logo-box {
                    width: 36px;
                    height: 36px;
                    background: linear-gradient(135deg, #6366f1, #a855f7);
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
                }
                .logo-name {
                    font-size: 1.4rem;
                    font-weight: 800;
                    letter-spacing: -0.5px;
                }
            `}</style>

            <div className="footer-inner">
                <div className="footer-brand-col">
                    <Link to="/" className="logo-link">
                        <img src="/favicon.png" alt="Loggi Logo" style={{ width: '36px', height: '36px', borderRadius: '8px' }} />
                        <span className="logo-name">LOGGI</span>
                    </Link>
                    <p>
                        Reinventando a logística brasileira através de tecnologia própria e excelência operacional. A maior malha de entregas do país ao seu alcance.
                    </p>
                </div>

                <div className="footer-links-wrap">
                    <div className="footer-col">
                        <h4>Soluções</h4>
                        <Link to="/para-voce">Para você</Link>
                        <Link to="/para-empresas">Para empresas</Link>
                        <Link to="/api-ecommerce">API E-commerce</Link>
                        <Link to="/loggi-pro">Loggi Pro</Link>
                    </div>
                    <div className="footer-col">
                        <h4>Empresa</h4>
                        <Link to="/sobre">Nossa História</Link>
                        <Link to="/carreiras">Carreiras</Link>
                        <Link to="/termos">Termos de Uso</Link>
                    </div>
                    <div className="footer-col">
                        <h4>Suporte</h4>
                        <Link to="/ajuda">Central de Ajuda</Link>
                        <Link to="/rastreio">Rastrear Objeto</Link>
                        <Link to="/pedido">Fazer Pedido</Link>
                    </div>
                </div>
            </div>

            <div className="footer-bottom">
                <div className="footer-bottom-info">
                    <span>© 2026 Loggi Tecnologia LTDA.</span>
                    <Link to="/termos" style={{ color: 'inherit', textDecoration: 'none' }}>Privacidade</Link>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    Feito com <Heart size={14} fill="#ef4444" color="#ef4444" /> para o Brasil
                </div>
            </div>
        </footer>
    );
};

export default Footer;
