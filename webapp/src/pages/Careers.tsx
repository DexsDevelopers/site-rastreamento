import { useEffect } from 'react';
import { Rocket, Heart, Globe, ArrowRight } from 'lucide-react';
import Header from '../components/Header';
import Footer from '../components/Footer';

const Careers = () => {
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
    const jobs = [
        { title: 'Engenheiro de Software Sênior', team: 'Tecnologia', location: 'Remoto / São Paulo' },
        { title: 'Gerente de Produto (Logística)', team: 'Produto', location: 'São Paulo' },
        { title: 'Analista de Dados', team: 'BI & Analytics', location: 'Remoto' },
        { title: 'Especialista de Atendimento', team: 'Operações', location: 'Barueri' },
    ];

    return (
        <div className="careers-page">
            <style>
                {`
                .careers-page { background: var(--bg-primary); min-height: 100vh; font-family: 'Outfit', sans-serif; color: white; overflow-x: hidden; position: relative; }
                .bg-mesh { position: fixed; inset: 0; background-image: radial-gradient(at 100% 100%, hsla(253,16%,7%,1) 0, transparent 50%), radial-gradient(at 0% 0%, hsla(225,39%,30%,0.1) 0, transparent 50%); z-index: 0; pointer-events: none; }
                
                .hero { padding: 160px 5% 80px; text-align: center; }
                .hero-title { font-size: clamp(2.5rem, 6vw, 5rem); font-weight: 900; line-height: 1; letter-spacing: -3px; margin-bottom: 24px; }
                .gradient-text { background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                
                .section { padding: 80px 5%; max-width: 1200px; margin: 0 auto; position: relative; z-index: 10; }
                .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 32px; }
                
                .culture-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 40px; border-radius: 32px; backdrop-filter: blur(10px); }
                .culture-card h3 { font-size: 1.5rem; margin: 20px 0 10px; }
                .culture-card p { color: rgba(255,255,255,0.5); line-height: 1.6; }
                
                .job-row { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 24px 32px; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; transition: all 0.3s; cursor: pointer; }
                .job-row:hover { background: rgba(255,255,255,0.05); transform: translateX(10px); border-color: var(--accent-primary); }
                .job-info h4 { font-size: 1.2rem; margin-bottom: 4px; }
                .job-info span { color: rgba(255,255,255,0.4); font-size: 0.9rem; }
                
                .site-footer { text-align: center; padding: 60px 20px; color: rgba(255,255,255,0.3); border-top: 1px solid rgba(255,255,255,0.05); }

                .reveal { opacity: 0; transform: translateY(30px); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
                .reveal-active { opacity: 1; transform: translateY(0); }
                .delay-1 { transition-delay: 0.1s; }
                .delay-2 { transition-delay: 0.2s; }
                .delay-3 { transition-delay: 0.3s; }
                
                `}
            </style>

            <div className="bg-mesh"></div>

            <Header />

            <section className="hero reveal">
                <h1 className="hero-title">Construa o futuro da <span className="gradient-text">logística</span></h1>
                <p style={{ color: 'rgba(255,255,255,0.5)', fontSize: '1.2rem', maxWidth: '700px', margin: '0 auto' }}>
                    Estamos em busca de mentes brilhantes para resolver os desafios mais complexos de movimentação de carga e dados no país.
                </p>
            </section>

            <section className="section">
                <div className="grid-3">
                    <div className="culture-card">
                        <Rocket color="#818cf8" size={32} />
                        <h3>Inovação Constante</h3>
                        <p>Não aceitamos o status quo. Se existe um jeito melhor de fazer, nós vamos encontrar e implementar.</p>
                    </div>
                    <div className="culture-card">
                        <Heart color="#f472b6" size={32} />
                        <h3>Pessoas em Primeiro</h3>
                        <p>Valorizamos a diversidade e o bem-estar. Flexibilidade é parte do nosso DNA.</p>
                    </div>
                    <div className="culture-card">
                        <Globe color="#34d399" size={32} />
                        <h3>Impacto Real</h3>
                        <p>Seu código ou estratégia afetará a vida de milhões de brasileiros todos os dias.</p>
                    </div>
                </div>
            </section>

            <section className="section">
                <h2 style={{ fontSize: '2.5rem', marginBottom: '40px' }}>Vagas Abertas</h2>
                <div className="jobs-list">
                    {jobs.map((job, i) => (
                        <div key={i} className={`job-row reveal delay-${(i % 3) + 1}`}>
                            <div className="job-info">
                                <h4>{job.title}</h4>
                                <span>{job.team} &bull; {job.location}</span>
                            </div>
                            <ArrowRight size={20} color="var(--accent-primary)" />
                        </div>
                    ))}
                </div>
            </section>

            <Footer />
        </div>
    );
};

export default Careers;
