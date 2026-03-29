// src/pages/Pedido.tsx
import React, { useState, useEffect } from 'react';
import { User, MapPin, Send, CheckCircle, Shield, Truck, Clock, Phone, Star, Lock, Package } from 'lucide-react';
import Header from '../components/Header';
import Footer from '../components/Footer';

const ESTADOS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
const API_BASE = import.meta.env.VITE_API_URL || '';

const Pedido: React.FC = () => {
    const [form, setForm] = useState({
        nome: '', cpf: '', telefone: '', email: '',
        cep: '', estado: '', cidade: '', bairro: '',
        rua: '', numero: '', complemento: '', observacoes: ''
    });
    const [loading, setLoading] = useState(false);
    const [success, setSuccess] = useState(false);
    const [, setError] = useState('');

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
    }, [success]);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
        let { name, value } = e.target;

        if (name === 'cpf') {
            value = value.replace(/\D/g, '').slice(0, 11);
            value = value.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        }
        if (name === 'telefone') {
            value = value.replace(/\D/g, '').slice(0, 11);
            if (value.length <= 10) value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            else value = value.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
        }
        if (name === 'cep') {
            value = value.replace(/\D/g, '').slice(0, 8);
            value = value.replace(/^(\d{5})(\d{0,3}).*/, '$1-$2');
            const cepClean = value.replace(/\D/g, '');
            if (cepClean.length === 8) {
                fetch(`https://viacep.com.br/ws/${cepClean}/json/`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.erro) {
                            setForm(prev => ({
                                ...prev,
                                rua: data.logradouro || prev.rua,
                                bairro: data.bairro || prev.bairro,
                                cidade: data.localidade || prev.cidade,
                                estado: data.uf || prev.estado,
                            }));
                        }
                    }).catch(() => { });
            }
        }

        setForm(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setLoading(true);

        try {
            const res = await fetch(`${API_BASE}/api/pedidos`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(form),
            });
            const data = await res.json();
            if (data.success) {
                setSuccess(true);
            } else {
                setError(data.message || 'Erro ao enviar pedido.');
                alert('Erro ao processar pedido: ' + (data.message || 'Erro no servidor'));
            }
        } catch (err: any) {
            console.error('Erro na submissão:', err);
            setError('Falha de conexão com o servidor.');
            alert('Não foi possível conectar ao servidor. Verifique se o sistema está online.');
        } finally {
            setLoading(false);
        }
    };

    const CSS = `
        .pd-page { background: #f0f4ff; color: #1e293b; min-height: 100vh; font-family: 'Outfit', sans-serif; display: flex; flex-direction: column; }
        .pd-page * { box-sizing: border-box; }

        /* LAYOUT */
        .pd-wrapper { flex: 1; display: flex; align-items: stretch; max-width: 1100px; margin: 0 auto; width: 100%; padding: 32px 20px 64px; gap: 32px; padding-top: 110px; }

        /* LEFT TRUST PANEL */
        .pd-trust { width: 340px; flex-shrink: 0; background: linear-gradient(160deg, #0a1628 0%, #0f2050 60%, #1a3a8f 100%); border-radius: 28px; padding: 40px 32px; color: #fff; display: flex; flex-direction: column; gap: 28px; box-shadow: 0 20px 60px rgba(0,40,120,0.25); position: relative; overflow: hidden; }
        .pd-trust::before { content: ''; position: absolute; top: -60px; right: -60px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(59,130,246,0.3), transparent 70%); pointer-events: none; }
        .pd-trust::after { content: ''; position: absolute; bottom: -40px; left: -40px; width: 160px; height: 160px; background: radial-gradient(circle, rgba(99,102,241,0.2), transparent 70%); pointer-events: none; }

        .pd-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
        .pd-brand-dot { width: 36px; height: 36px; background: linear-gradient(135deg, #3b82f6, #6366f1); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .pd-brand-name { font-size: 1.4rem; font-weight: 900; letter-spacing: -0.5px; }

        .pd-headline { font-size: 1.55rem; font-weight: 800; line-height: 1.3; margin: 0; }
        .pd-headline span { background: linear-gradient(90deg, #60a5fa, #a5b4fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .pd-sub { font-size: 0.88rem; color: rgba(255,255,255,0.6); line-height: 1.6; margin: -12px 0 0; }

        .pd-benefits { display: flex; flex-direction: column; gap: 14px; position: relative; z-index: 1; }
        .pd-benefit { display: flex; align-items: flex-start; gap: 14px; }
        .pd-benefit-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .pd-benefit-text strong { font-size: 0.9rem; display: block; margin-bottom: 2px; }
        .pd-benefit-text span { font-size: 0.78rem; color: rgba(255,255,255,0.55); }

        .pd-reviews { background: rgba(255,255,255,0.07); border-radius: 16px; padding: 16px 18px; border: 1px solid rgba(255,255,255,0.1); position: relative; z-index: 1; }
        .pd-stars { display: flex; gap: 3px; margin-bottom: 8px; }
        .pd-reviews-text { font-size: 0.82rem; color: rgba(255,255,255,0.7); line-height: 1.5; }
        .pd-reviews-count { font-size: 0.75rem; color: rgba(255,255,255,0.4); margin-top: 4px; }

        .pd-contact { background: rgba(59,130,246,0.15); border: 1px solid rgba(59,130,246,0.3); border-radius: 14px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; position: relative; z-index: 1; }
        .pd-contact-label { font-size: 0.73rem; color: rgba(255,255,255,0.5); }
        .pd-contact-number { font-size: 0.95rem; font-weight: 700; color: #93c5fd; }

        .pd-secure { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; color: rgba(255,255,255,0.4); margin-top: auto; position: relative; z-index: 1; }

        /* RIGHT FORM PANEL */
        .pd-form-panel { flex: 1; background: #fff; border-radius: 28px; padding: 44px 40px; box-shadow: 0 4px 40px rgba(0,40,120,0.08); border: 1px solid rgba(0,85,255,0.06); position: relative; overflow: hidden; }
        .pd-form-panel::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #0055ff, #6366f1, #3b82f6); }

        .pd-form-header { margin-bottom: 36px; }
        .pd-form-title { font-size: 1.8rem; font-weight: 900; color: #0f172a; margin: 0 0 6px; letter-spacing: -0.5px; }
        .pd-form-desc { font-size: 0.9rem; color: #64748b; margin: 0; }

        .pd-section-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #2563eb; display: flex; align-items: center; gap: 7px; margin-bottom: 16px; }
        .pd-section-label::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }

        .pd-field { margin-bottom: 14px; }
        .pd-label { font-size: 0.78rem; font-weight: 600; color: #475569; margin-bottom: 5px; display: block; }
        .pd-input { width: 100%; height: 48px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 0 16px; color: #0f172a; font-family: 'Outfit', sans-serif; font-size: 0.93rem; outline: none; transition: 0.2s; }
        .pd-input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .pd-input::placeholder { color: #cbd5e1; }
        .pd-input option { background: #fff; }

        .pd-row { display: flex; gap: 12px; }
        .pd-row .pd-field { flex: 1; }

        .pd-btn { width: 100%; padding: 16px; background: linear-gradient(135deg, #1d4ed8, #2563eb, #3b82f6); border: none; border-radius: 14px; color: white; font-weight: 800; font-size: 1.05rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 28px; box-shadow: 0 8px 24px rgba(37,99,235,0.3); transition: all 0.25s; font-family: 'Outfit', sans-serif; letter-spacing: 0.3px; }
        .pd-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 14px 32px rgba(37,99,235,0.4); }
        .pd-btn:active:not(:disabled) { transform: translateY(0); }
        .pd-btn:disabled { opacity: 0.65; cursor: not-allowed; }

        .pd-guarantee { display: flex; align-items: center; justify-content: center; gap: 20px; margin-top: 18px; }
        .pd-guarantee-item { display: flex; align-items: center; gap: 5px; font-size: 0.72rem; color: #94a3b8; }

        /* SUCCESS */
        .pd-success-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .pd-success-card { background: #fff; border-radius: 32px; padding: 60px 48px; text-align: center; max-width: 520px; width: 100%; box-shadow: 0 20px 60px rgba(0,40,120,0.1); border: 1px solid #e0eaff; }
        .pd-success-icon { width: 88px; height: 88px; background: linear-gradient(135deg, #1d4ed8, #3b82f6); border-radius: 28px; display: flex; align-items: center; justify-content: center; margin: 0 auto 28px; box-shadow: 0 12px 32px rgba(37,99,235,0.3); }
        .pd-success-title { font-size: 2rem; font-weight: 900; color: #0f172a; margin: 0 0 12px; }
        .pd-success-title span { background: linear-gradient(135deg, #1d4ed8, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .pd-success-desc { color: #64748b; line-height: 1.7; margin: 0 0 12px; font-size: 0.95rem; }
        .pd-success-contact { background: #f0f7ff; border-radius: 14px; padding: 14px 20px; margin: 24px 0 32px; font-size: 0.88rem; color: #1e40af; }
        .pd-success-btn { display: inline-block; padding: 14px 40px; background: linear-gradient(135deg, #1d4ed8, #3b82f6); border: none; border-radius: 14px; color: #fff; font-weight: 800; font-size: 1rem; cursor: pointer; font-family: 'Outfit', sans-serif; box-shadow: 0 8px 24px rgba(37,99,235,0.25); }

        /* RESPONSIVE */
        @media (max-width: 860px) {
            .pd-wrapper { flex-direction: column; padding-top: 90px; gap: 16px; }

            /* Formulário aparece PRIMEIRO no mobile */
            .pd-form-panel { order: 1; padding: 28px 20px; }
            .pd-form-title { font-size: 1.5rem; }

            /* Painel de confiança vira uma faixa compacta ABAIXO */
            .pd-trust { order: 2; width: 100%; padding: 20px; gap: 12px; }
            .pd-trust .pd-headline, .pd-trust .pd-sub, .pd-trust .pd-brand { display: none; }
            .pd-trust .pd-reviews, .pd-trust .pd-contact, .pd-trust .pd-secure { display: none; }
            .pd-benefits { flex-direction: row; flex-wrap: wrap; gap: 10px; }
            .pd-benefit { flex: 1 1 calc(50% - 5px); min-width: 130px; }
            .pd-benefit-icon { width: 32px; height: 32px; }
            .pd-benefit-text strong { font-size: 0.82rem; }
            .pd-benefit-text span { font-size: 0.72rem; }
        }
        @media (max-width: 540px) {
            .pd-row { flex-direction: column; gap: 0; }
            .pd-form-panel { padding: 24px 16px; }
        }
    `;

    if (success) {
        return (
            <div className="pd-page">
                <style>{CSS}</style>
                <Header />
                <div className="pd-success-wrap">
                    <div className="pd-success-card">
                        <div className="pd-success-icon"><CheckCircle size={44} color="white" /></div>
                        <h2 className="pd-success-title">Pedido <span>Confirmado!</span></h2>
                        <p className="pd-success-desc">
                            Recebemos seu pedido com sucesso! Nossa equipe já está analisando e em breve você receberá uma confirmação no seu WhatsApp.
                        </p>
                        <div className="pd-success-contact">
                            📲 Dúvidas? Fale conosco: <strong>(51) 99614-8568</strong>
                        </div>
                        <button className="pd-success-btn" onClick={() => window.location.href = '#/'}>Voltar ao Início</button>
                    </div>
                </div>
                <Footer />
            </div>
        );
    }

    return (
        <div className="pd-page">
            <style>{CSS}</style>
            <Header />

            <div className="pd-wrapper">
                {/* PAINEL ESQUERDO — CONFIANÇA */}
                <div className="pd-trust">
                    <div>
                        <div className="pd-brand">
                            <div className="pd-brand-dot"><Package size={18} color="#fff" /></div>
                            <span className="pd-brand-name">Loggi</span>
                        </div>
                        <h2 className="pd-headline">Entrega <span>rápida e segura</span> para todo Brasil</h2>
                        <p className="pd-sub">Preencha o formulário ao lado e nosso time cuidará de tudo para você.</p>
                    </div>

                    <div className="pd-benefits">
                        <div className="pd-benefit">
                            <div className="pd-benefit-icon" style={{ background: 'rgba(59,130,246,0.2)' }}><Truck size={20} color="#60a5fa" /></div>
                            <div className="pd-benefit-text">
                                <strong>Entrega Expressa</strong>
                                <span>Em até 3 dias úteis</span>
                            </div>
                        </div>
                        <div className="pd-benefit">
                            <div className="pd-benefit-icon" style={{ background: 'rgba(16,185,129,0.2)' }}><Shield size={20} color="#34d399" /></div>
                            <div className="pd-benefit-text">
                                <strong>Rastreio em Tempo Real</strong>
                                <span>Acompanhe cada etapa</span>
                            </div>
                        </div>
                        <div className="pd-benefit">
                            <div className="pd-benefit-icon" style={{ background: 'rgba(251,191,36,0.2)' }}><Clock size={20} color="#fbbf24" /></div>
                            <div className="pd-benefit-text">
                                <strong>Suporte 24h</strong>
                                <span>Sempre disponível</span>
                            </div>
                        </div>
                        <div className="pd-benefit">
                            <div className="pd-benefit-icon" style={{ background: 'rgba(99,102,241,0.2)' }}><Lock size={20} color="#a5b4fc" /></div>
                            <div className="pd-benefit-text">
                                <strong>100% Seguro</strong>
                                <span>Dados criptografados</span>
                            </div>
                        </div>
                    </div>

                    <div className="pd-reviews">
                        <div className="pd-stars">
                            {[1,2,3,4,5].map(i => <Star key={i} size={15} color="#fbbf24" fill="#fbbf24" />)}
                        </div>
                        <p className="pd-reviews-text">"Serviço excelente, meu pacote chegou antes do prazo e recebi atualizações em tempo real!"</p>
                        <p className="pd-reviews-count">— Maria S., Porto Alegre/RS · +4.200 pedidos entregues</p>
                    </div>

                    <div className="pd-contact">
                        <div style={{ width: 38, height: 38, background: 'rgba(59,130,246,0.2)', borderRadius: 10, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                            <Phone size={18} color="#60a5fa" />
                        </div>
                        <div>
                            <div className="pd-contact-label">Atendimento direto</div>
                            <div className="pd-contact-number">(51) 99614-8568</div>
                        </div>
                    </div>

                    <div className="pd-secure">
                        <Lock size={13} />
                        Conexão segura · Dados criptografados SSL
                    </div>
                </div>

                {/* PAINEL DIREITO — FORMULÁRIO */}
                <div className="pd-form-panel">
                    <div className="pd-form-header">
                        <h2 className="pd-form-title">Finalizar Pedido</h2>
                        <p className="pd-form-desc">Preencha os dados abaixo. O CEP preenche o endereço automaticamente.</p>
                    </div>

                    <form onSubmit={handleSubmit}>
                        <div className="pd-section-label"><User size={14} /> Dados Pessoais</div>

                        <div className="pd-field">
                            <label className="pd-label">Nome Completo *</label>
                            <input className="pd-input" name="nome" placeholder="Ex: João da Silva" value={form.nome} onChange={handleChange} required />
                        </div>
                        <div className="pd-row">
                            <div className="pd-field">
                                <label className="pd-label">CPF *</label>
                                <input className="pd-input" name="cpf" placeholder="000.000.000-00" value={form.cpf} onChange={handleChange} required />
                            </div>
                            <div className="pd-field">
                                <label className="pd-label">WhatsApp *</label>
                                <input className="pd-input" name="telefone" placeholder="(51) 99999-9999" value={form.telefone} onChange={handleChange} required />
                            </div>
                        </div>
                        <div className="pd-field">
                            <label className="pd-label">E-mail</label>
                            <input className="pd-input" name="email" type="email" placeholder="seu@email.com (opcional)" value={form.email} onChange={handleChange} />
                        </div>

                        <div className="pd-section-label" style={{ marginTop: 24 }}><MapPin size={14} /> Endereço de Entrega</div>

                        <div className="pd-row">
                            <div className="pd-field" style={{ flex: '1.2' }}>
                                <label className="pd-label">CEP *</label>
                                <input className="pd-input" name="cep" placeholder="00000-000" value={form.cep} onChange={handleChange} required />
                            </div>
                            <div className="pd-field" style={{ flex: '0.8' }}>
                                <label className="pd-label">Estado *</label>
                                <select className="pd-input" name="estado" value={form.estado} onChange={handleChange} required>
                                    <option value="">UF</option>
                                    {ESTADOS.map(uf => <option key={uf} value={uf}>{uf}</option>)}
                                </select>
                            </div>
                        </div>
                        <div className="pd-row">
                            <div className="pd-field">
                                <label className="pd-label">Cidade *</label>
                                <input className="pd-input" name="cidade" placeholder="Sua cidade" value={form.cidade} onChange={handleChange} required />
                            </div>
                            <div className="pd-field">
                                <label className="pd-label">Bairro *</label>
                                <input className="pd-input" name="bairro" placeholder="Seu bairro" value={form.bairro} onChange={handleChange} required />
                            </div>
                        </div>
                        <div className="pd-row">
                            <div className="pd-field" style={{ flex: 2 }}>
                                <label className="pd-label">Rua / Logradouro *</label>
                                <input className="pd-input" name="rua" placeholder="Nome da rua" value={form.rua} onChange={handleChange} required />
                            </div>
                            <div className="pd-field" style={{ flex: 1 }}>
                                <label className="pd-label">Número *</label>
                                <input className="pd-input" name="numero" placeholder="Nº" value={form.numero} onChange={handleChange} required />
                            </div>
                        </div>
                        <div className="pd-field">
                            <label className="pd-label">Complemento</label>
                            <input className="pd-input" name="complemento" placeholder="Apto, bloco, referência (opcional)" value={form.complemento} onChange={handleChange} />
                        </div>

                        <button type="submit" className="pd-btn" disabled={loading}>
                            {loading ? '⏳ Processando...' : <><Send size={18} /> Confirmar Pedido Agora</>}
                        </button>

                        <div className="pd-guarantee">
                            <div className="pd-guarantee-item"><Lock size={12} /> Dados seguros</div>
                            <div className="pd-guarantee-item"><Shield size={12} /> Sem compromisso</div>
                            <div className="pd-guarantee-item"><CheckCircle size={12} /> Confirmação por WhatsApp</div>
                        </div>
                    </form>
                </div>
            </div>

            <Footer />
        </div>
    );
};

export default Pedido;
