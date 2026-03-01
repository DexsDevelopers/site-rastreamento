import { useEffect, useState } from 'react';
import { Package, Search, MessageCircle, FileText, Phone, ChevronDown, MapPin, QrCode, Shield, ArrowRight } from 'lucide-react';
import { Link } from 'react-router-dom';
import Header from '../components/Header';
import Footer from '../components/Footer';

const HelpCenter = () => {
    const [openFaq, setOpenFaq] = useState<number | null>(null);

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

    const categories = [
        { icon: <Package size={24} />, title: 'Rastreamento', desc: 'Como localizar seu pedido e entender os status.', link: '/rastreio' },
        { icon: <FileText size={24} />, title: 'Fazer um Pedido', desc: 'Crie seu envio online e gere etiquetas.', link: '/pedido' },
        { icon: <MessageCircle size={24} />, title: 'Para Você', desc: 'Soluções pessoais de entrega e envio.', link: '/para-voce' },
        { icon: <Phone size={24} />, title: 'Para Empresas', desc: 'Soluções corporativas e API.', link: '/para-empresas' },
    ];

    const faqs = [
        { q: 'Como rastrear meu pacote?', a: 'Acesse a página de Rastreio, insira o código de rastreamento fornecido pelo remetente e sua cidade. Clique em "Rastrear" para ver o status atualizado da sua encomenda em tempo real.' },
        { q: 'Onde encontro o código de rastreamento?', a: 'O código de rastreamento é enviado pelo remetente do pacote, geralmente por e-mail, SMS ou WhatsApp. Ele também pode estar na nota fiscal ou comprovante de postagem. O formato é alfanumérico (letras e números).' },
        { q: 'Quanto tempo leva para entregar?', a: 'O prazo de entrega varia conforme a modalidade escolhida e a região de destino. Entregas Loggi Express podem chegar em até 24h para a mesma cidade. Para outras regiões, o prazo é de 3 a 7 dias úteis.' },
        { q: 'O que significa cada status do rastreio?', a: 'Postado: o pacote foi entregue à Loggi. Em trânsito: está sendo transportado. Saiu para entrega: está com o entregador na sua região. Entregue: foi entregue com sucesso no endereço de destino.' },
        { q: 'Como pagar a taxa de entrega?', a: 'Se houver taxa pendente, ela aparecerá ao rastrear o pacote. Você pode pagar via PIX diretamente na página de rastreamento. O QR Code é gerado automaticamente e a confirmação é instantânea.' },
        { q: 'Posso alterar o endereço de entrega?', a: 'Alterações de endereço só podem ser feitas antes do pacote sair para entrega. Entre em contato com o remetente ou acesse nossa central de ajuda para solicitar a alteração.' },
        { q: 'O que fazer se meu pacote não chegou?', a: 'Verifique o status no rastreio. Se constar "Entregue" mas você não recebeu, entre em contato conosco em até 48h. Abriremos uma investigação e resolveremos seu caso.' },
        { q: 'A Loggi entrega em todo o Brasil?', a: 'Sim! A Loggi possui a maior malha logística do país, atendendo todas as capitais e a grande maioria das cidades do interior. Consulte a disponibilidade para sua região na hora do envio.' },
    ];

    const steps = [
        { icon: <QrCode size={24} />, title: 'Pegue seu código', desc: 'Receba o código de rastreamento do remetente via email, SMS ou WhatsApp.' },
        { icon: <Search size={24} />, title: 'Cole o código', desc: 'Acesse a página de Rastreio e cole o código no campo de busca junto com sua cidade.' },
        { icon: <MapPin size={24} />, title: 'Acompanhe em tempo real', desc: 'Veja cada etapa da jornada do seu pacote com atualizações automáticas.' },
        { icon: <Shield size={24} />, title: 'Receba com segurança', desc: 'Sua encomenda chega protegida e rastreada do início ao fim.' },
    ];

    return (
        <div className="help-page">
            <style>
                {`
                .help-page { background: var(--bg-primary); min-height: 100vh; font-family: 'Inter', sans-serif; color: var(--text-primary); overflow-x: hidden; position: relative; }
                .bg-mesh { position: fixed; inset: 0; background-image: radial-gradient(at 0% 0%, rgba(0,85,255,0.04) 0, transparent 50%), radial-gradient(at 100% 0%, rgba(59,130,246,0.03) 0, transparent 50%); z-index: 0; pointer-events: none; }
                
                .hero { padding: 160px 5% 60px; text-align: center; max-width: 800px; margin: 0 auto; position: relative; z-index: 1; }
                .hero-title { font-size: clamp(2rem, 5vw, 3rem); font-weight: 900; margin-bottom: 16px; color: var(--text-primary); font-family: 'Outfit', sans-serif; }
                .hero-subtitle { color: var(--text-secondary); font-size: clamp(1rem, 2vw, 1.1rem); margin-bottom: 32px; }
                .search-box { position: relative; max-width: 600px; margin: 0 auto; }
                .search-input { width: 100%; padding: 20px 20px 20px 60px; background: rgba(255,255,255,0.65); border: 1px solid rgba(255,255,255,0.8); border-radius: 20px; color: var(--text-primary); font-size: 1.1rem; outline: none; transition: 0.3s; backdrop-filter: blur(12px); box-shadow: 0 8px 32px rgba(0,40,120,0.06); }
                .search-input:focus { border-color: #0055ff; background: #fff; box-shadow: 0 0 0 3px rgba(0,85,255,0.1); }
                .search-input::placeholder { color: var(--text-muted); }
                .search-icon { position: absolute; left: 24px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
                
                .section { padding: 40px 5%; max-width: 1200px; margin: 0 auto; position: relative; z-index: 10; }
                .section-title { font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 800; margin-bottom: 12px; font-family: 'Outfit', sans-serif; }
                .section-sub { color: var(--text-secondary); margin-bottom: 32px; font-size: 1rem; }
                
                .grid-categories { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
                .cat-card { background: rgba(255,255,255,0.55); border: 1px solid rgba(255,255,255,0.7); padding: 32px; border-radius: 24px; transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1); cursor: pointer; text-decoration: none; color: inherit; backdrop-filter: blur(16px); box-shadow: 0 4px 16px rgba(0,40,120,0.04); transform-style: preserve-3d; display: block; position: relative; overflow: hidden; }
                .cat-card::after { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--accent-gradient); transform: scaleX(0); transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1); transform-origin: left; }
                .cat-card:hover { background: rgba(255,255,255,0.82); border-color: rgba(0,85,255,0.15); transform: translateY(-6px) rotateX(2deg); box-shadow: 0 24px 48px rgba(0,40,120,0.08); }
                .cat-card:hover::after { transform: scaleX(1); }
                .cat-icon { width: 50px; height: 50px; background: rgba(0, 85, 255, 0.06); border-radius: 14px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; color: #0055ff; transition: all 0.4s; }
                .cat-card:hover .cat-icon { transform: scale(1.1); }
                .cat-card h3 { font-size: 1.15rem; margin-bottom: 8px; font-weight: 700; font-family: 'Outfit', sans-serif; }
                .cat-card p { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5; margin-bottom: 16px; }
                .cat-link { color: #0055ff; font-weight: 700; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; transition: gap 0.3s; }
                .cat-card:hover .cat-link { gap: 12px; }

                .steps-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; counter-reset: step; }
                .step-card { text-align: center; padding: 32px 20px; border-radius: 24px; background: rgba(255,255,255,0.5); border: 1px solid rgba(255,255,255,0.6); position: relative; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
                .step-card:hover { transform: translateY(-4px); box-shadow: 0 16px 32px rgba(0,40,120,0.06); }
                .step-num { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #0055ff, #3b82f6); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem; margin: 0 auto 16px; font-family: 'Outfit', sans-serif; }
                .step-icon { width: 52px; height: 52px; border-radius: 16px; background: rgba(0, 85, 255, 0.06); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #0055ff; }
                .step-card h4 { font-size: 1rem; font-weight: 700; margin-bottom: 8px; font-family: 'Outfit', sans-serif; }
                .step-card p { color: var(--text-secondary); font-size: 0.85rem; line-height: 1.5; }

                .faq-list { display: flex; flex-direction: column; gap: 12px; max-width: 800px; margin: 0 auto; }
                .faq-item { background: rgba(255,255,255,0.55); border: 1px solid rgba(255,255,255,0.7); border-radius: 16px; overflow: hidden; transition: all 0.3s; }
                .faq-item.open { border-color: rgba(0, 85, 255, 0.15); box-shadow: 0 8px 24px rgba(0,40,120,0.04); }
                .faq-q { padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: 600; font-size: 0.95rem; transition: all 0.3s; gap: 16px; }
                .faq-q:hover { color: #0055ff; }
                .faq-chevron { transition: transform 0.3s; flex-shrink: 0; color: var(--text-muted); }
                .faq-item.open .faq-chevron { transform: rotate(180deg); color: #0055ff; }
                .faq-a { padding: 0 24px 20px; color: var(--text-secondary); line-height: 1.7; font-size: 0.9rem; }

                .reveal { opacity: 0; transform: translateY(30px); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
                .reveal-active { opacity: 1; transform: translateY(0); }
                .delay-1 { transition-delay: 0.1s; }
                .delay-2 { transition-delay: 0.2s; }
                .delay-3 { transition-delay: 0.3s; }
                .delay-4 { transition-delay: 0.4s; }
                
                @media (max-width: 768px) {
                    .hero { padding: 120px 20px 40px; }
                    .hero-title { font-size: 2rem; }
                    .steps-grid { grid-template-columns: 1fr 1fr; gap: 16px; }
                    .grid-categories { grid-template-columns: 1fr; }
                    .section { padding: 32px 16px; }
                }
                @media (max-width: 480px) {
                    .steps-grid { grid-template-columns: 1fr; }
                }
                `}
            </style>

            <div className="bg-mesh"></div>

            <Header />

            <section className="hero reveal">
                <h1 className="hero-title">Como podemos ajudar?</h1>
                <p className="hero-subtitle">Encontre respostas rápidas, instruções e suporte para qualquer dúvida.</p>
                <div className="search-box">
                    <Search className="search-icon" size={24} />
                    <input type="text" className="search-input" placeholder="Busque por 'onde está meu pedido', 'como rastrear', etc..." />
                </div>
            </section>

            {/* Categorias com Links */}
            <section className="section">
                <div className="grid-categories">
                    {categories.map((cat, i) => (
                        <Link key={i} to={cat.link} className={`cat-card reveal delay-${i + 1}`}>
                            <div className="cat-icon">{cat.icon}</div>
                            <h3>{cat.title}</h3>
                            <p>{cat.desc}</p>
                            <span className="cat-link">Acessar <ArrowRight size={14} /></span>
                        </Link>
                    ))}
                </div>
            </section>

            {/* Como Rastrear - Passo a Passo */}
            <section className="section reveal">
                <h2 className="section-title" style={{ textAlign: 'center' }}>Como rastrear seu pacote</h2>
                <p className="section-sub" style={{ textAlign: 'center' }}>Siga os passos abaixo para acompanhar sua encomenda</p>
                <div className="steps-grid">
                    {steps.map((step, i) => (
                        <div key={i} className={`step-card reveal delay-${i + 1}`}>
                            <div className="step-num">{i + 1}</div>
                            <div className="step-icon">{step.icon}</div>
                            <h4>{step.title}</h4>
                            <p>{step.desc}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* Perguntas Frequentes */}
            <section className="section reveal">
                <h2 className="section-title" style={{ textAlign: 'center' }}>Perguntas Frequentes</h2>
                <p className="section-sub" style={{ textAlign: 'center' }}>Respostas para as dúvidas mais comuns dos nossos clientes</p>
                <div className="faq-list">
                    {faqs.map((faq, i) => (
                        <div key={i} className={`faq-item ${openFaq === i ? 'open' : ''}`}>
                            <div className="faq-q" onClick={() => setOpenFaq(openFaq === i ? null : i)}>
                                {faq.q}
                                <ChevronDown size={18} className="faq-chevron" />
                            </div>
                            {openFaq === i && <div className="faq-a">{faq.a}</div>}
                        </div>
                    ))}
                </div>
            </section>

            {/* CTA */}
            <section className="section" style={{ textAlign: 'center' }}>
                <div className="reveal" style={{ background: 'rgba(255,255,255,0.55)', border: '1px solid rgba(255,255,255,0.7)', padding: 'clamp(32px, 6vw, 48px)', borderRadius: '28px', backdropFilter: 'blur(16px)', boxShadow: '0 8px 32px rgba(0,40,120,0.04)' }}>
                    <h2 style={{ fontSize: 'clamp(1.4rem, 3vw, 2rem)', marginBottom: '16px', fontFamily: 'Outfit, sans-serif' }}>Ainda precisa de ajuda?</h2>
                    <p style={{ color: 'var(--text-secondary)', marginBottom: '32px' }}>Nosso time de suporte está disponível 24/7 para te atender.</p>
                    <Link to="/rastreio" className="btn-primary" style={{ padding: '16px 40px', fontSize: '1rem' }}>Rastrear meu pacote</Link>
                </div>
            </section>

            <Footer />
        </div>
    );
};

export default HelpCenter;

