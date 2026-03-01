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
                    .pd-page { background: var(--bg-primary); color: var(--text-primary); min-height: 100vh; font-family: 'Outfit', sans-serif; display: flex; flex-direction: column; }
                    .success-container { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 24px; position: relative; z-index: 1; }
                    .success-card { background: rgba(255,255,255,0.6); backdrop-filter: blur(32px); border: 1px solid rgba(255,255,255,0.8); border-radius: 40px; padding: 60px 40px; text-align: center; max-width: 500px; box-shadow: 0 16px 48px rgba(0,40,120,0.08); }
                    .gradient-text { background: linear-gradient(135deg, #0055ff, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                    .reveal { opacity: 0; transform: translateY(30px); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
                    .reveal-active { opacity: 1; transform: translateY(0); }
                `}</style>
                <Header />
                <div className="success-container">
                    <div className="success-card reveal">
                        <div style={{ width: 80, height: 80, background: 'linear-gradient(135deg, #0055ff, #3b82f6)', borderRadius: 24, display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 32px' }}>
                            <CheckCircle size={40} color="white" />
                        </div>
                        <h2 style={{ fontSize: '2.2rem', fontWeight: 900, marginBottom: 16 }}>Pedido <span className="gradient-text">Recebido!</span></h2>
                        <p style={{ color: 'var(--text-secondary)', lineHeight: 1.7, marginBottom: 40 }}>Obrigado por escolher a Loggi. Nossa central de envios já está processando suas informações.</p>
                        <button onClick={() => window.location.href = '#/'} className="btn-primary" style={{ padding: '16px 40px', border: 'none', cursor: 'pointer', fontWeight: 800 }}>Voltar ao Início</button>
                    </div>
                </div>
                <Footer />
            </div>
        );
    }

    return (
        <div className="pd-page">
            <style>{`
                .pd-page { background: var(--bg-primary); color: var(--text-primary); min-height: 100vh; position: relative; overflow-x: hidden; font-family: 'Outfit', sans-serif; }
                .pd-page * { box-sizing: border-box; }
                .bg-mesh {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background:
                        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(0, 85, 255, 0.06), transparent),
                        radial-gradient(ellipse 60% 40% at 80% 50%, rgba(59, 130, 246, 0.04), transparent);
                }
                
                .form-container { max-width: 600px; margin: 40px auto 100px; padding: 0 24px; position: relative; z-index: 1; padding-top: 100px; }
                .form-card { background: rgba(255,255,255,0.6); backdrop-filter: blur(32px); border: 1px solid rgba(255,255,255,0.8); border-radius: 40px; padding: 48px; box-shadow: 0 16px 48px rgba(0,40,120,0.08); position: relative; overflow: hidden; }
                .form-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #0055ff, #3b82f6); }
                .form-section-title { font-size: 1.1rem; font-weight: 800; display: flex; align-items: center; gap: 10px; margin-bottom: 24px; color: #0055ff; }
                
                .input-field { width: 100%; height: 52px; background: rgba(255,255,255,0.7); border: 1px solid rgba(0,85,255,0.08); border-radius: 14px; padding: 0 20px; color: var(--text-primary); outline: none; margin-bottom: 16px; transition: 0.3s; font-family: inherit; font-size: 0.95rem; }
                .input-field:focus { border-color: #0055ff; background: #fff; box-shadow: 0 0 0 3px rgba(0,85,255,0.1); }
                .input-field::placeholder { color: var(--text-muted); }
                .input-field option { background: #fff; color: var(--text-primary); padding: 10px; }
                
                .btn-submit { width: 100%; padding: 18px; background: linear-gradient(135deg, #0055ff, #3b82f6); border: none; border-radius: 18px; color: white; font-weight: 800; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 32px; box-shadow: 0 8px 24px rgba(0, 85, 255, 0.25); transition: all 0.3s; font-family: 'Outfit', sans-serif; }
                .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(0, 85, 255, 0.4); }
                .btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }
                
                .reveal { opacity: 0; transform: translateY(30px); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
                .reveal-active { opacity: 1; transform: translateY(0); }

                @media (max-width: 640px) {
                    .form-card { padding: 32px 20px; border-radius: 28px; }
                    .form-container { padding-top: 80px; }
                    .form-card .flex-row { flex-direction: column !important; gap: 0 !important; }
                }
            `}</style>

            <div className="bg-mesh"></div>

            <Header />

            <div className="form-container">
                <div className="form-card reveal">
                    <h2 style={{ fontSize: '2.2rem', fontWeight: 900, marginBottom: 8 }}>Finalizar Pedido</h2>
                    <p style={{ color: 'var(--text-secondary)', marginBottom: 40 }}>Preencha os dados abaixo para gerar seu envio.</p>

                    <form onSubmit={handleSubmit}>
                        <div className="form-section-title"><User size={18} /> Dados Pessoais</div>
                        <input className="input-field" name="nome" placeholder="Nome Completo" value={form.nome} onChange={handleChange} required />
                        <div style={{ display: 'flex', gap: 14 }} className="flex-row">
                            <input className="input-field" name="cpf" placeholder="CPF" value={form.cpf} onChange={handleChange} required />
                            <input className="input-field" name="telefone" placeholder="WhatsApp" value={form.telefone} onChange={handleChange} required />
                        </div>

                        <div className="form-section-title" style={{ marginTop: 24 }}><MapPin size={18} /> Endereço de Entrega</div>
                        <div style={{ display: 'flex', gap: 14 }} className="flex-row">
                            <input className="input-field" name="cep" placeholder="CEP" value={form.cep} onChange={handleChange} required />
                            <select className="input-field" name="estado" value={form.estado} onChange={handleChange} required>
                                <option value="">UF</option>
                                {ESTADOS.map(uf => <option key={uf} value={uf}>{uf}</option>)}
                            </select>
                        </div>
                        <div style={{ display: 'flex', gap: 14 }} className="flex-row">
                            <input className="input-field" name="cidade" placeholder="Cidade" value={form.cidade} onChange={handleChange} required />
                            <input className="input-field" name="bairro" placeholder="Bairro" value={form.bairro} onChange={handleChange} required />
                        </div>
                        <div style={{ display: 'flex', gap: 14 }} className="flex-row">
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
