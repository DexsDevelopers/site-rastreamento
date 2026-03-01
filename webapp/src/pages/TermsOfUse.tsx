import { ShieldCheck, ChevronRight } from 'lucide-react';
import Header from '../components/Header';
import Footer from '../components/Footer';

const TermsOfUse = () => {

    return (
        <div className="terms-page">
            <style>
                {`
                .terms-page { background: var(--bg-primary); min-height: 100vh; font-family: 'Outfit', sans-serif; color: var(--text-primary); overflow-x: hidden; position: relative; }
                .bg-mesh { position: fixed; inset: 0; background-image: radial-gradient(at 0% 100%, rgba(0,85,255,0.04) 0, transparent 50%), radial-gradient(at 100% 0%, rgba(59,130,246,0.03) 0, transparent 50%); z-index: 0; pointer-events: none; }
                
                .hero { padding: 160px 5% 40px; text-align: center; }
                .hero-title { font-size: 3rem; font-weight: 900; margin-bottom: 16px; }
                .hero-date { color: var(--text-muted); font-size: 0.9rem; }
                
                .content-section { padding: 40px 5% 80px; max-width: 900px; margin: 0 auto; position: relative; z-index: 10; }
                .terms-content { background: rgba(255,255,255,0.55); border: 1px solid rgba(255,255,255,0.7); border-radius: 32px; padding: 60px; backdrop-filter: blur(20px); box-shadow: 0 8px 32px rgba(0,40,120,0.06); }
                .terms-content h2 { font-size: 1.8rem; margin: 48px 0 24px; color: #0055ff; border-bottom: 1px solid rgba(0, 85, 255, 0.1); padding-bottom: 16px; }
                .terms-content h2:first-child { margin-top: 0; }
                .terms-content p { color: var(--text-secondary); line-height: 1.8; margin-bottom: 24px; font-size: 1.1rem; }
                .terms-content ul { list-style: none; padding: 0; margin-bottom: 32px; }
                .terms-content li { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 16px; color: var(--text-secondary); }
                .terms-content li svg { margin-top: 4px; flex-shrink: 0; color: #0055ff; }
                
                .footer-links a:hover { color: var(--text-primary); }
                `}
            </style>

            <div className="bg-mesh"></div>

            <Header />

            <section className="hero">
                <h1 className="hero-title">Termos de Uso</h1>
                <p className="hero-date">Última atualização: 27 de Fevereiro de 2026</p>
            </section>

            <section className="content-section">
                <div className="terms-content">
                    <h2>1. Aceitação dos Termos</h2>
                    <p>Ao utilizar os serviços da Loggi, você concorda integralmente com estes termos de uso. Nossa plataforma conecta remetentes, destinatários e entregadores independentes através de tecnologia de ponta para otimização de rotas e prazos.</p>

                    <h2>2. Nossos Serviços</h2>
                    <p>A Loggi oferece uma plataforma tecnológica que permite:</p>
                    <ul>
                        <li><ChevronRight size={18} /> Solicitação de coletas e entregas em tempo real.</li>
                        <li><ChevronRight size={18} /> Rastreamento detalhado de pacotes.</li>
                        <li><ChevronRight size={18} /> Gestão de logística para e-commerces via API.</li>
                        <li><ChevronRight size={18} /> Processamento de pagamentos de frete.</li>
                    </ul>

                    <h2>3. Responsabilidades</h2>
                    <p>O usuário é responsável pela veracidade das informações fornecidas, incluindo endereços de coleta e entrega, bem como pela declaração correta dos itens transportados, seguindo as normas de segurança e restrições legais vigentes.</p>

                    <h2>4. Privacidade e Segurança</h2>
                    <p>Tratamos seus dados com o mais alto rigor de segurança e em conformidade com a LGPD. Suas informações de localização e contato são utilizadas exclusivamente para a finalidade da prestação do serviço logístico.</p>

                    <div style={{ marginTop: '60px', padding: '32px', background: 'rgba(0, 85, 255, 0.04)', borderRadius: '24px', border: '1px solid rgba(0, 85, 255, 0.1)' }}>
                        <p style={{ margin: 0, display: 'flex', alignItems: 'center', gap: '12px', color: '#0055ff', fontWeight: 600 }}>
                            <ShieldCheck /> Segurança em primeiro lugar.
                        </p>
                    </div>
                </div>
            </section>

            <Footer />
        </div>
    );
};

export default TermsOfUse;
