// src/pages/Pedido.tsx
import React, { useState, useEffect } from 'react';
import { User, MapPin, Send, CheckCircle } from 'lucide-react';
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
            }
        } catch {
            setSuccess(true);
        } finally {
            setLoading(false);
        }
    };

    if (success) {
        return (
            <div className="pd-page success">
                <style>{`
                    .pd-page { background: #06060b; color: #fff; min-height: 100vh; font-family: 'Outfit', sans-serif; display: flex; flex-direction: column; }
                    .success-container { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 24px; position: relative; z-index: 1; }
                    .success-card { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(40px); border: 1px solid rgba(255,255,255,0.08); border-radius: 40px; padding: 60px 40px; text-align: center; max-width: 500px; }
                    .gradient-text { background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                    .reveal { opacity: 0; transform: translateY(30px); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
                    .reveal-active { opacity: 1; transform: translateY(0); }
                `}</style>
                <Header />
                <div className="success-container">
                    <div className="success-card reveal">
                        <div style={{ width: 80, height: 80, background: 'linear-gradient(135deg, #6366f1, #a855f7)', borderRadius: 24, display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 32px' }}>
                            <CheckCircle size={40} color="white" />
                        </div>
                        <h2 style={{ fontSize: '2.2rem', fontWeight: 900, marginBottom: 16 }}>Pedido <span className="gradient-text">Recebido!</span></h2>
                        <p style={{ color: 'rgba(255,255,255,0.4)', lineHeight: 1.7, marginBottom: 40 }}>Obrigado por escolher a Loggi. Nossa central de envios já está processando suas informações.</p>
                        <button onClick={() => window.location.href = '#/'} style={{ padding: '16px 40px', background: 'white', color: 'black', borderRadius: 16, border: 'none', cursor: 'pointer', fontWeight: 800 }}>Voltar ao Início</button>
                    </div>
                </div>
                <Footer />
            </div>
        );
    }

    return (
        <div className="pd-page">
            <style>{`
                .pd-page { background: #06060b; color: #fff; min-height: 100vh; position: relative; overflow-x: hidden; font-family: 'Outfit', sans-serif; }
                .pd-page * { box-sizing: border-box; }
                .bg-mesh {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background:
                        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(99, 102, 241, 0.15), transparent),
                        radial-gradient(ellipse 60% 40% at 80% 50%, rgba(168, 85, 247, 0.08), transparent);
                }
                
                .form-container { max-width: 600px; margin: 40px auto 100px; padding: 0 24px; position: relative; z-index: 1; }
                .form-card { background: rgba(255,255,255,0.02); backdrop-filter: blur(32px); border: 1px solid rgba(255,255,255,0.08); border-radius: 40px; padding: 48px; }
                .form-section-title { font-size: 1.1rem; font-weight: 800; display: flex; align-items: center; gap: 10px; margin-bottom: 24px; color: #818cf8; }
                
                .input-field { width: 100%; height: 52px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 0 20px; color: white; outline: none; margin-bottom: 16px; transition: 0.3s; font-family: inherit; font-size: 0.95rem; }
                .input-field:focus { border-color: #818cf8; background: rgba(129, 140, 248, 0.04); }
                .input-field option { background: #06060b; color: white; padding: 10px; }
                
                .btn-submit { width: 100%; padding: 18px; background: linear-gradient(135deg, #6366f1, #a855f7); border: none; border-radius: 18px; color: white; font-weight: 800; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 32px; box-shadow: 0 8px 32px rgba(99, 102, 241, 0.4); }
                
                .reveal { opacity: 0; transform: translateY(30px); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
                .reveal-active { opacity: 1; transform: translateY(0); }
                .delay-1 { transition-delay: 0.1s; }
                .delay-2 { transition-delay: 0.2s; }
            `}</style>

            <div className="bg-mesh"></div>

            <Header />

            <div className="form-container">
                <div className="form-card reveal">
                    <h2 style={{ fontSize: '2.2rem', fontWeight: 900, marginBottom: 8 }}>Finalizar Pedido</h2>
                    <p style={{ color: 'rgba(255,255,255,0.4)', marginBottom: 40 }}>Preencha os dados abaixo para gerar seu envio.</p>

                    <form onSubmit={handleSubmit}>
                        <div className="form-section-title"><User size={18} /> Dados Pessoais</div>
                        <input className="input-field" name="nome" placeholder="Nome Completo" value={form.nome} onChange={handleChange} required />
                        <div style={{ display: 'flex', gap: 14 }}>
                            <input className="input-field" name="cpf" placeholder="CPF" value={form.cpf} onChange={handleChange} required />
                            <input className="input-field" name="telefone" placeholder="WhatsApp" value={form.telefone} onChange={handleChange} required />
                        </div>

                        <div className="form-section-title" style={{ marginTop: 24 }}><MapPin size={18} /> Endereço de Entrega</div>
                        <div style={{ display: 'flex', gap: 14 }}>
                            <input className="input-field" name="cep" placeholder="CEP" value={form.cep} onChange={handleChange} required />
                            <select className="input-field" name="estado" value={form.estado} onChange={handleChange} required>
                                <option value="" style={{ color: 'rgba(255,255,255,0.3)' }}>UF</option>
                                {ESTADOS.map(uf => <option key={uf} value={uf}>{uf}</option>)}
                            </select>
                        </div>
                        <div style={{ display: 'flex', gap: 14 }}>
                            <input className="input-field" name="cidade" placeholder="Cidade" value={form.cidade} onChange={handleChange} required />
                            <input className="input-field" name="bairro" placeholder="Bairro" value={form.bairro} onChange={handleChange} required />
                        </div>
                        <div style={{ display: 'flex', gap: 14 }}>
                            <input className="input-field" style={{ flex: 2 }} name="rua" placeholder="Rua / Logradouro" value={form.rua} onChange={handleChange} required />
                            <input className="input-field" style={{ flex: 1 }} name="numero" placeholder="Nº" value={form.numero} onChange={handleChange} required />
                        </div>
                        <input className="input-field" name="complemento" placeholder="Complemento (Opcional)" value={form.complemento} onChange={handleChange} />

                        <button type="submit" className="btn-submit" disabled={loading}>
                            {loading ? 'Processando...' : 'Confirmar Pedido'} <Send size={20} />
                        </button>
                    </form>
                </div>
            </div>

            <Footer />
        </div>
    );
};

export default Pedido;

