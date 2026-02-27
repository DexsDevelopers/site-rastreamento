// src/pages/Pedido.tsx
import React, { useState, useEffect } from 'react';
import { Truck, User, MapPin, Send, CheckCircle } from 'lucide-react';
import { Link } from 'react-router-dom';

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
        const handleScroll = () => { };
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

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
                    .pd-page { background: #06060b; color: #fff; min-height: 100vh; font-family: 'Outfit', sans-serif; display: flex; align-items: center; justify-content: center; }
                    .success-card { background: rgba(253, 253, 255, 0.02); backdrop-filter: blur(40px); border: 1px solid rgba(255,255,255,0.08); border-radius: 40px; padding: 60px 40px; text-align: center; max-width: 500px; }
                    .gradient-text { background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                `}</style>
                <div className="success-card">
                    <div style={{ width: 80, height: 80, background: 'linear-gradient(135deg, #6366f1, #a855f7)', borderRadius: 24, display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 32px' }}>
                        <CheckCircle size={40} color="white" />
                    </div>
                    <h2 style={{ fontSize: '2.2rem', fontWeight: 900, marginBottom: 16 }}>Pedido <span className="gradient-text">Recebido!</span></h2>
                    <p style={{ color: 'rgba(255,255,255,0.4)', lineHeight: 1.7, marginBottom: 40 }}>Obrigado por escolher a Loggi. Nossa central de envios já está processando suas informações.</p>
                    <Link to="/" style={{ padding: '16px 40px', background: 'white', color: 'black', borderRadius: 16, textDecoration: 'none', fontWeight: 800 }}>Voltar ao Início</Link>
                </div>
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
                
                .site-header { position: sticky; top: 0; z-index: 100; padding: 20px 24px; transition: all 0.3s; }
                .header-glass {
                    max-width: 1200px; margin: 0 auto;
                    display: flex; justify-content: space-between; align-items: center;
                    padding: 14px 28px; background: rgba(10, 10, 12, 0.4); backdrop-filter: blur(20px) saturate(1.8);
                    border: 1px solid rgba(255,255,255,0.08); border-radius: 24px;
                }
                
                .form-container { max-width: 600px; margin: 40px auto 100px; padding: 0 24px; position: relative; z-index: 1; }
                .form-card { background: rgba(255,255,255,0.02); backdrop-filter: blur(32px); border: 1px solid rgba(255,255,255,0.08); border-radius: 40px; padding: 48px; }
                .form-section-title { font-size: 1.1rem; font-weight: 800; display: flex; align-items: center; gap: 10px; margin-bottom: 24px; color: #818cf8; }
                
                .input-field { width: 100%; height: 52px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 0 20px; color: white; outline: none; margin-bottom: 16px; transition: 0.3s; }
                .input-field:focus { border-color: #818cf8; background: rgba(129, 140, 248, 0.04); }
                
                .btn-submit { width: 100%; padding: 18px; background: linear-gradient(135deg, #6366f1, #a855f7); border: none; border-radius: 18px; color: white; font-weight: 800; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 32px; box-shadow: 0 8px 32px rgba(99, 102, 241, 0.4); }
                
                .site-footer { border-top: 1px solid rgba(255,255,255,0.04); padding: 80px 24px 40px; text-align: center; color: rgba(255,255,255,0.3); }
            `}</style>

            <div className="bg-mesh"></div>

            <header className="site-header">
                <div className="header-glass">
                    <Link to="/" style={{ display: 'flex', alignItems: 'center', gap: 10, textDecoration: 'none', color: 'white' }}>
                        <div style={{ width: 38, height: 38, background: 'linear-gradient(135deg, #6366f1, #a855f7)', borderRadius: 12, display: 'flex', alignItems: 'center', justifyContent: 'center' }}><Truck size={18} /></div>
                        <span style={{ fontSize: '1.4rem', fontWeight: 800 }}>loggi</span>
                    </Link>
                    <Link to="/" style={{ color: 'rgba(255,255,255,0.4)', textDecoration: 'none', fontSize: '0.9rem' }}>Voltar</Link>
                </div>
            </header>

            <div className="form-container">
                <div className="form-card">
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
                                <option value="">UF</option>
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

            <footer className="site-footer">
                <p>© 2026 Loggi Tecnologia LTDA.</p>
            </footer>
        </div>
    );
};

export default Pedido;
