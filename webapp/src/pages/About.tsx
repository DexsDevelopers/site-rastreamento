import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRight, TrendingUp, Users, Target, ChevronDown } from 'lucide-react';
import Header from '../components/Header';
import Footer from '../components/Footer';

const About = () => {
    useEffect(() => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal-active');
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
        return () => observer.disconnect();
    }, []);

    return (
        <div className="about-page">
            <style>
                {`
                .about-page { background: var(--bg-primary); min-height: 100vh; font-family: 'Outfit', sans-serif; color: white; overflow-x: hidden; position: relative; }
                .bg-mesh { position: fixed; inset: 0; background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), radial-gradient(at 50% 0%, hsla(225,39%,30%,0.1) 0, transparent 50%), radial-gradient(at 100% 0%, hsla(339,49%,30%,0.1) 0, transparent 50%); z-index: 0; pointer-events: none; }
                
                .page-hero { padding: 160px 20px 100px; text-align: center; position: relative; z-index: 10; max-width: 900px; margin: 0 auto; }
                .hero-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 100px; font-size: 0.85rem; font-weight: 600; color: #818cf8; margin-bottom: 24px; }
                .hero-title { font-size: clamp(2.5rem, 5vw, 4.5rem); font-weight: 900; line-height: 1.1; letter-spacing: -2px; margin-bottom: 24px; }
                .hero-desc { color: rgba(255,255,255,0.6); font-size: 1.2rem; line-height: 1.7; margin: 0 auto 40px; }
                .gradient-word { background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                
                .story-grid { display: grid; grid-template-columns: 1fr; gap: 80px; max-width: 1200px; margin: 0 auto; padding: 40px 5%; position: relative; z-index: 10; }
                @media(min-width: 900px) { .story-grid { grid-template-columns: 1fr 1fr; align-items: center; } }
                
                .story-text h2 { font-size: 2.2rem; font-weight: 800; margin-bottom: 24px; }
                .story-text p { color: rgba(255,255,255,0.5); font-size: 1.1rem; line-height: 1.8; margin-bottom: 24px; }
                
                .glass-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 32px; padding: 40px; transition: all 0.3s; }
                .glass-card:hover { background: rgba(255,255,255,0.04); transform: translateY(-5px); }

                .faq-section { padding: 80px 24px; max-width: 800px; margin: 0 auto; position: relative; z-index: 10; }
                .faq-item { margin-bottom: 16px; border-radius: 20px; overflow: hidden; }
                .faq-question { padding: 24px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 1.1rem; }
                .faq-answer { padding: 0 24px 24px; color: rgba(255,255,255,0.5); line-height: 1.7; }
                
                .testimonials-section { padding: 80px 5%; max-width: 1200px; margin: 0 auto; position: relative; z-index: 10; }
                .testimonial-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 32px; border-radius: 24px; }
                .testimonial-text { font-style: italic; color: rgba(255,255,255,0.6); margin-bottom: 20px; line-height: 1.6; }

                details > summary { list-style: none; }
                details > summary::-webkit-details-marker { display: none; }

                .reveal { opacity: 0; transform: translateY(30px); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
                .reveal-active { opacity: 1; transform: translateY(0); }
                .delay-1 { transition-delay: 0.1s; }
                .delay-2 { transition-delay: 0.2s; }
                .delay-3 { transition-delay: 0.3s; }
                
                `}
            </style>

            <div className="bg-mesh"></div>

            <Header />

            <section className="page-hero reveal">
                <div className="hero-badge">A NOSSA JORNADA</div>
                <h1 className="hero-title">Redefinindo a <span className="gradient-word">logística<br />no Brasil</span></h1>
                <p className="hero-desc">
                    Tudo começou com uma ideia simples: por que a logística precisa ser complicada? Nossa missão é usar tecnologia para conectar pessoas e negócios através de entregas perfeitas.
                </p>
                <div style={{ display: 'flex', gap: '20px', justifyContent: 'center', flexWrap: 'wrap' }}>
                    <div className="glass-card" style={{ padding: '24px', flex: '1 1 200px' }}>
                        <TrendingUp size={32} color="#818cf8" style={{ marginBottom: '16px' }} />
                        <h3 style={{ fontSize: '1.5rem', marginBottom: '8px' }}>1B+</h3>
                        <p style={{ color: 'rgba(255,255,255,0.5)', fontSize: '0.9rem' }}>Pacotes entregues</p>
                    </div>
                    <div className="glass-card" style={{ padding: '24px', flex: '1 1 200px' }}>
                        <Users size={32} color="#c084fc" style={{ marginBottom: '16px' }} />
                        <h3 style={{ fontSize: '1.5rem', marginBottom: '8px' }}>50k+</h3>
                        <p style={{ color: 'rgba(255,255,255,0.5)', fontSize: '0.9rem' }}>Entregadores ativos</p>
                    </div>
                    <div className="glass-card" style={{ padding: '24px', flex: '1 1 200px' }}>
                        <Target size={32} color="#34d399" style={{ marginBottom: '16px' }} />
                        <h3 style={{ fontSize: '1.5rem', marginBottom: '8px' }}>100%</h3>
                        <p style={{ color: 'rgba(255,255,255,0.5)', fontSize: '0.9rem' }}>De cobertura nacional</p>
                    </div>
                </div>
            </section>

            <section className="story-grid">
                <div className="story-text">
                    <h2>De uma startup para o coração do e-commerce.</h2>
                    <p>
                        Começamos como um pequeno serviço de motoboys em São Paulo. Vimos a dor das empresas que precisavam entregar no mesmo dia e de clientes cansados de esperar semanas por suas compras.
                    </p>
                    <p>
                        Construímos uma malha de ponta-a-ponta baseada em dados e software próprio. Hoje, nós decidimos a melhor rota para o caminhão e o caminho final da moto simultaneamente, garantindo a entrega perfeita.
                    </p>
                    <Link to="/para-empresas" className="nav-login-btn" style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', padding: '16px 28px', marginTop: '24px' }}>
                        Conheça os produtos <ArrowRight size={18} />
                    </Link>
                </div>
                <div style={{ position: 'relative', height: '100%', minHeight: '400px' }}>
                    <div style={{ position: 'absolute', inset: 0, borderRadius: '40px', background: 'url(https://images.unsplash.com/photo-1580674684081-7617fbf3d745?auto=format&fit=crop&q=80)', backgroundSize: 'cover', backgroundPosition: 'center', filter: 'grayscale(0.5)' }}></div>
                    <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(to top right, rgba(99,102,241,0.5), transparent)', borderRadius: '40px' }}></div>
                </div>
            </section>

            <section className="faq-section">
                <h2 style={{ fontSize: '2rem', fontWeight: 800, marginBottom: '40px', textAlign: 'center' }}>Perguntas <span className="gradient-word">Frequentes</span></h2>
                {[
                    { q: 'Como faço para enviar um pacote?', a: 'Basta acessar "Enviar agora" em nossa home, preencher os dados do objeto e realizar o pagamento. Depois, é só levar sua etiqueta a um Ponto Loggi parceiro próximo a você.' },
                    { q: 'Qual o prazo de entrega padrão?', a: 'Os prazos variam de acordo com a origem e o destino. Para envios locais, entregamos em até 24h. Para envios nacionais expressos, o prazo médio é de 3 dias úteis.' },
                    { q: 'É possível acelerar uma entrega em curso?', a: 'Sim! Ao realizar o rastreio no nosso site, caso seu objeto seja elegível, você verá o botão "Acelerar Entrega". Siga as instruções para priorizar seu envio em nossa malha expressa.' },
                ].map((item, i) => (
                    <details key={i} className="faq-item glass-card" style={{ padding: 0 }}>
                        <summary className="faq-question">
                            {item.q}
                            <ChevronDown size={18} />
                        </summary>
                        <p className="faq-answer">{item.a}</p>
                    </details>
                ))}
            </section>

            <section className="testimonials-section">
                <h2 style={{ fontSize: '2rem', fontWeight: 800, marginBottom: '40px', textAlign: 'center' }}>Confiança de <span className="gradient-word">quem usa</span></h2>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '24px' }}>
                    {[
                        { name: 'Ricardo Mendes', role: 'CEO, TechCommerce', text: '"A tecnologia da Loggi é incomparável. Consigo gerir todos os meus envios com uma facilidade que nunca tive antes."' },
                        { name: 'Juliana Costa', role: 'Gerente Logística, ModaBR', text: '"O suporte é excelente e as entregas sempre dentro do prazo. Meus clientes estão muito mais satisfeitos."' },
                        { name: 'Felipe Silva', role: 'Vendedor Platinum', text: '"Postar meus pacotes ficou 10x mais rápido com os Pontos Loggi. Recomendo para todos os vendedores."' },
                    ].map((t, i) => (
                        <div key={i} className="testimonial-card">
                            <div style={{ display: 'flex', gap: '4px', marginBottom: '16px' }}>
                                {[...Array(5)].map((_, j) => <Star key={j} size={14} fill="#818cf8" color="#818cf8" />)}
                            </div>
                            <p className="testimonial-text">{t.text}</p>
                            <div>
                                <strong style={{ display: 'block', fontSize: '1.1rem' }}>{t.name}</strong>
                                <span style={{ color: 'rgba(255,255,255,0.4)', fontSize: '0.9rem' }}>{t.role}</span>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            <Footer />
        </div>
    );
};

const Star = ({ size, fill, color }: { size: number, fill: string, color: string }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill={fill} stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
);

export default About;


