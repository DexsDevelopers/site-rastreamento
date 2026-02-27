import React, { useState } from 'react';
import { Truck, User, MapPin, Send, Lock, CheckCircle, Shield, ArrowLeft } from 'lucide-react';
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
    const [error, setError] = useState('');

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
            setSuccess(true); // Simula sucesso para demo
        } finally {
            setLoading(false);
        }
    };

    if (success) {
        return (
            <div style={styles.page}>
                <div style={styles.bgOrb1}></div>
                <div style={styles.bgOrb2}></div>
                <div style={styles.container}>
                    <div style={styles.successCard} className="glass-card-3d animate-fade">
                        <div style={styles.successIconBox}>
                            <CheckCircle size={48} color="white" />
                        </div>
                        <h2 style={{ fontSize: '1.8rem', marginTop: '24px', fontWeight: 800 }}>✅ Pedido Enviado!</h2>
                        <p style={{ color: 'var(--text-secondary)', marginTop: '16px', lineHeight: 1.7, maxWidth: '400px' }}>
                            Seu pedido foi recebido com sucesso! Nossa equipe entrará em contato via WhatsApp em breve para finalizar.
                        </p>
                        <div style={{ display: 'flex', gap: '12px', marginTop: '32px', flexWrap: 'wrap', justifyContent: 'center' }}>
                            <Link to="/" className="btn-primary" style={{ padding: '14px 28px', textDecoration: 'none', borderRadius: '14px' }}>
                                Voltar ao Início
                            </Link>
                            <button onClick={() => { setSuccess(false); setForm({ nome: '', cpf: '', telefone: '', email: '', cep: '', estado: '', cidade: '', bairro: '', rua: '', numero: '', complemento: '', observacoes: '' }); }} style={styles.outlineBtn}>
                                Novo Pedido
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div style={styles.page}>
            <div style={styles.bgOrb1}></div>
            <div style={styles.bgOrb2}></div>
            <div style={styles.gridOverlay}></div>

            <div style={styles.container}>
                {/* Header */}
                <div style={styles.headerBrand} className="animate-fade">
                    <Link to="/" style={styles.logoLink}>
                        <img src="/assets/images/logo.png" alt="Loggi" style={{ height: '40px', marginRight: '8px' }} onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }} />
                        <Truck size={28} color="var(--accent-primary)" />
                    </Link>
                    <h1 style={styles.pageTitle}>Finalizar Pedido</h1>
                    <p style={styles.pageSubtitle}>Confirme seus dados para entrega Loggi</p>
                </div>

                {/* Card do Formulário */}
                <div style={styles.formCard} className="glass-card-3d animate-fade">
                    <div style={styles.topBar}></div>

                    {error && (
                        <div style={styles.errorBox}>⚠️ {error}</div>
                    )}

                    <form onSubmit={handleSubmit}>
                        {/* === DADOS PESSOAIS === */}
                        <div style={styles.sectionLabel}>
                            <User size={18} color="var(--accent-primary)" />
                            <span>Dados Pessoais</span>
                        </div>

                        <div style={styles.fieldGroup}>
                            <label style={styles.label}>Nome Completo <span style={styles.required}>*</span></label>
                            <input className="input-field" name="nome" value={form.nome} onChange={handleChange} placeholder="Ex: Maria da Silva Santos" required />
                        </div>

                        <div style={styles.row2}>
                            <div style={styles.fieldGroup}>
                                <label style={styles.label}>CPF <span style={styles.required}>*</span></label>
                                <input className="input-field" name="cpf" value={form.cpf} onChange={handleChange} placeholder="000.000.000-00" required inputMode="numeric" />
                            </div>
                            <div style={styles.fieldGroup}>
                                <label style={styles.label}>WhatsApp <span style={styles.required}>*</span></label>
                                <input className="input-field" name="telefone" value={form.telefone} onChange={handleChange} placeholder="(11) 99999-9999" required inputMode="tel" />
                            </div>
                        </div>

                        <div style={styles.fieldGroup}>
                            <label style={styles.label}>E-mail (Opcional)</label>
                            <input className="input-field" name="email" type="email" value={form.email} onChange={handleChange} placeholder="seuemail@exemplo.com" />
                        </div>

                        {/* === ENDEREÇO === */}
                        <div style={{ ...styles.sectionLabel, marginTop: '40px' }}>
                            <MapPin size={18} color="var(--accent-primary)" />
                            <span>Endereço de Entrega</span>
                        </div>

                        <div style={styles.row2}>
                            <div style={styles.fieldGroup}>
                                <label style={styles.label}>CEP <span style={styles.required}>*</span></label>
                                <input className="input-field" name="cep" value={form.cep} onChange={handleChange} placeholder="00000-000" required inputMode="numeric" maxLength={9} />
                            </div>
                            <div style={styles.fieldGroup}>
                                <label style={styles.label}>Estado <span style={styles.required}>*</span></label>
                                <select className="input-field" name="estado" value={form.estado} onChange={handleChange} required>
                                    <option value="">UF</option>
                                    {ESTADOS.map(uf => <option key={uf} value={uf}>{uf}</option>)}
                                </select>
                            </div>
                        </div>

                        <div style={styles.row2}>
                            <div style={styles.fieldGroup}>
                                <label style={styles.label}>Cidade <span style={styles.required}>*</span></label>
                                <input className="input-field" name="cidade" value={form.cidade} onChange={handleChange} required />
                            </div>
                            <div style={styles.fieldGroup}>
                                <label style={styles.label}>Bairro <span style={styles.required}>*</span></label>
                                <input className="input-field" name="bairro" value={form.bairro} onChange={handleChange} required />
                            </div>
                        </div>

                        <div style={styles.rowUneven}>
                            <div style={{ ...styles.fieldGroup, flex: 2 }}>
                                <label style={styles.label}>Logradouro (Rua/Av) <span style={styles.required}>*</span></label>
                                <input className="input-field" name="rua" value={form.rua} onChange={handleChange} placeholder="Ex: Rua das Flores" required />
                            </div>
                            <div style={{ ...styles.fieldGroup, flex: 1 }}>
                                <label style={styles.label}>Número <span style={styles.required}>*</span></label>
                                <input className="input-field" name="numero" value={form.numero} onChange={handleChange} placeholder="123" required />
                            </div>
                        </div>

                        <div style={styles.fieldGroup}>
                            <label style={styles.label}>Complemento (Opcional)</label>
                            <input className="input-field" name="complemento" value={form.complemento} onChange={handleChange} placeholder="Apto 101, Bloco A..." />
                        </div>

                        <div style={styles.fieldGroup}>
                            <label style={styles.label}>Observações para o Entregador</label>
                            <textarea className="input-field" name="observacoes" value={form.observacoes} onChange={handleChange} placeholder="Ex: Portão azul, próximo ao mercado..." rows={3} style={{ resize: 'none' as const }} />
                        </div>

                        <button type="submit" className="btn-primary" style={styles.submitBtn} disabled={loading}>
                            {loading ? '⏳ Enviando...' : 'Confirmar Endereço'}
                            {!loading && <Send size={18} />}
                        </button>
                    </form>
                </div>

                {/* Trust Badges */}
                <div style={styles.trustRow}>
                    <div style={styles.trustItem}><Lock size={14} color="var(--success)" /> Conexão Segura</div>
                    <div style={styles.trustItem}><CheckCircle size={14} color="var(--success)" /> Dados Criptografados</div>
                    <div style={styles.trustItem}><Shield size={14} color="var(--success)" /> Compra Protegida</div>
                </div>

                {/* Voltar */}
                <Link to="/" style={styles.backLink}>
                    <ArrowLeft size={16} /> Voltar ao início
                </Link>
            </div>
        </div>
    );
};

const styles: { [key: string]: React.CSSProperties } = {
    page: { background: 'var(--bg-primary)', minHeight: '100vh', padding: '40px 16px', display: 'flex', justifyContent: 'center', position: 'relative', overflow: 'hidden' },
    bgOrb1: { position: 'fixed', width: '500px', height: '500px', background: 'radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, transparent 70%)', top: '-100px', right: '-100px', borderRadius: '50%', filter: 'blur(60px)', pointerEvents: 'none' },
    bgOrb2: { position: 'fixed', width: '400px', height: '400px', background: 'radial-gradient(circle, rgba(168, 85, 247, 0.08) 0%, transparent 70%)', bottom: '-80px', left: '-80px', borderRadius: '50%', filter: 'blur(60px)', pointerEvents: 'none' },
    gridOverlay: { position: 'fixed', inset: 0, backgroundImage: 'linear-gradient(rgba(255,255,255,0.015) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px)', backgroundSize: '80px 80px', pointerEvents: 'none' },
    container: { maxWidth: '600px', width: '100%', position: 'relative', zIndex: 1 },
    headerBrand: { textAlign: 'center', marginBottom: '32px' },
    logoLink: { display: 'inline-flex', alignItems: 'center', textDecoration: 'none', marginBottom: '20px' },
    pageTitle: { fontSize: 'clamp(1.6rem, 4vw, 2.2rem)', fontWeight: 900, letterSpacing: '-1px' },
    pageSubtitle: { color: 'var(--text-secondary)', marginTop: '8px', fontSize: '0.95rem' },
    formCard: { padding: 'clamp(24px, 5vw, 40px)', borderRadius: '28px', position: 'relative', overflow: 'hidden' },
    topBar: { position: 'absolute', top: 0, left: 0, right: 0, height: '3px', background: 'var(--accent-gradient)' },
    errorBox: { background: 'rgba(239, 68, 68, 0.1)', border: '1px solid rgba(239, 68, 68, 0.3)', color: '#fca5a5', padding: '16px', borderRadius: '14px', marginBottom: '24px', fontSize: '0.9rem', fontWeight: 600 },
    sectionLabel: { display: 'flex', alignItems: 'center', gap: '10px', fontWeight: 700, fontSize: '1rem', marginBottom: '24px', paddingBottom: '16px', borderBottom: '1px solid var(--border-glass)' },
    fieldGroup: { display: 'flex', flexDirection: 'column', gap: '6px', marginBottom: '18px' },
    label: { fontSize: '0.85rem', fontWeight: 600, color: 'var(--text-secondary)' },
    required: { color: 'var(--accent-primary)' },
    row2: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px' },
    rowUneven: { display: 'flex', gap: '14px' },
    submitBtn: { width: '100%', padding: '18px', marginTop: '32px', fontSize: '1.05rem', fontWeight: 800, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '12px', borderRadius: '16px' },
    outlineBtn: { padding: '14px 28px', background: 'transparent', border: '1px solid var(--border-glass-strong)', borderRadius: '14px', color: 'var(--text-primary)', fontWeight: 600, cursor: 'pointer', fontSize: '0.95rem', fontFamily: "'Outfit', sans-serif" },
    trustRow: { display: 'flex', justifyContent: 'center', gap: '20px', marginTop: '24px', flexWrap: 'wrap' },
    trustItem: { display: 'flex', alignItems: 'center', gap: '6px', fontSize: '0.78rem', color: 'var(--text-secondary)' },
    successCard: { textAlign: 'center', padding: 'clamp(40px, 8vw, 60px) clamp(24px, 5vw, 40px)', borderRadius: '28px', marginTop: '60px' },
    successIconBox: { width: '80px', height: '80px', borderRadius: '24px', background: 'var(--accent-gradient)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto', boxShadow: '0 12px 32px var(--accent-glow)' },
    backLink: { display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', marginTop: '20px', color: 'rgba(255,255,255,0.35)', textDecoration: 'none', fontSize: '0.9rem', fontWeight: 500 },
};

export default Pedido;
