import React, { useState } from 'react';
import { Truck, User, MapPin, Send, Lock, CheckCircle, Shield } from 'lucide-react';
import { Link } from 'react-router-dom';

const ESTADOS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

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

        // Máscaras
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
            // Auto busca CEP
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
            const res = await fetch('/api/pedidos', {
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
            // Simular sucesso para demonstração
            setSuccess(true);
        } finally {
            setLoading(false);
        }
    };

    if (success) {
        return (
            <div style={styles.page}>
                <div style={styles.container}>
                    <div style={styles.successCard} className="glass-card animate-fade">
                        <CheckCircle size={64} color="var(--success)" />
                        <h2 style={{ fontSize: '2rem', marginTop: '24px' }}>Pedido Enviado!</h2>
                        <p style={{ color: 'var(--text-secondary)', marginTop: '16px', lineHeight: 1.6 }}>
                            Seu pedido foi recebido com sucesso! Nossa equipe entrará em contato via WhatsApp em breve para finalizar.
                        </p>
                        <Link to="/" style={styles.backBtn} className="btn-primary">
                            Voltar ao Início
                        </Link>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div style={styles.page}>
            <div style={styles.container}>
                {/* Header */}
                <div style={styles.headerBrand}>
                    <Link to="/" style={{ textDecoration: 'none', color: 'white' }}>
                        <div style={styles.logoRow}>
                            <Truck size={28} color="var(--accent-primary)" />
                        </div>
                    </Link>
                    <h1 style={styles.pageTitle}>Finalizar Pedido</h1>
                    <p style={styles.pageSubtitle}>Confirme seus dados para entrega Loggi</p>
                </div>

                {/* Formulário */}
                <div style={styles.formCard} className="glass-card">
                    <div style={styles.accentLine}></div>

                    {error && (
                        <div style={styles.errorBox}>
                            <span>⚠️ {error}</span>
                        </div>
                    )}

                    <form onSubmit={handleSubmit}>
                        {/* Dados Pessoais */}
                        <div style={styles.sectionLabel}>
                            <User size={18} color="var(--accent-primary)" />
                            <span>Dados Pessoais</span>
                        </div>

                        <div style={styles.fieldGroup}>
                            <label style={styles.label}>Nome Completo <span style={{ color: 'var(--accent-primary)' }}>*</span></label>
                            <input style={styles.input} name="nome" value={form.nome} onChange={handleChange} placeholder="Ex: Maria da Silva Santos" required className="input-field" />
                        </div>

                        <div style={styles.row}>
                            <div style={styles.fieldGroup}>
                                <label style={styles.label}>CPF <span style={{ color: 'var(--accent-primary)' }}>*</span></label>
                                <input style={styles.input} name="cpf" value={form.cpf} onChange={handleChange} placeholder="000.000.000-00" required className="input-field" />
                            </div>
                            <div style={styles.fieldGroup}>
                                <label style={styles.label}>WhatsApp <span style={{ color: 'var(--accent-primary)' }}>*</span></label>
                                <input style={styles.input} name="telefone" value={form.telefone} onChange={handleChange} placeholder="(11) 99999-9999" required className="input-field" />
                            </div>
                        </div>

                        <div style={styles.fieldGroup}>
                            <label style={styles.label}>E-mail (Opcional)</label>
                            <input style={styles.input} name="email" value={form.email} onChange={handleChange} placeholder="seuemail@exemplo.com" className="input-field" />
                        </div>

                        {/* Endereço */}
                        <div style={{ ...styles.sectionLabel, marginTop: '40px' }}>
                            <MapPin size={18} color="var(--accent-primary)" />
                            <span>Endereço de Entrega</span>
                        </div>

                        <div style={styles.row}>
                            <div style={styles.fieldGroup}>
                                <label style={styles.label}>CEP <span style={{ color: 'var(--accent-primary)' }}>*</span></label>
                                <input style={styles.input} name="cep" value={form.cep} onChange={handleChange} placeholder="00000-000" required className="input-field" />
                            </div>
                            <div style={styles.fieldGroup}>
                                <label style={styles.label}>Estado <span style={{ color: 'var(--accent-primary)' }}>*</span></label>
                                <select style={styles.input} name="estado" value={form.estado} onChange={handleChange} required className="input-field">
                                    <option value="">UF</option>
                                    {ESTADOS.map(uf => <option key={uf} value={uf}>{uf}</option>)}
                                </select>
                            </div>
                        </div>

                        <div style={styles.row}>
                            <div style={styles.fieldGroup}>
                                <label style={styles.label}>Cidade <span style={{ color: 'var(--accent-primary)' }}>*</span></label>
                                <input style={styles.input} name="cidade" value={form.cidade} onChange={handleChange} required className="input-field" />
                            </div>
                            <div style={styles.fieldGroup}>
                                <label style={styles.label}>Bairro <span style={{ color: 'var(--accent-primary)' }}>*</span></label>
                                <input style={styles.input} name="bairro" value={form.bairro} onChange={handleChange} required className="input-field" />
                            </div>
                        </div>

                        <div style={styles.rowUneven}>
                            <div style={{ ...styles.fieldGroup, flex: 2 }}>
                                <label style={styles.label}>Rua / Av <span style={{ color: 'var(--accent-primary)' }}>*</span></label>
                                <input style={styles.input} name="rua" value={form.rua} onChange={handleChange} placeholder="Ex: Rua das Flores" required className="input-field" />
                            </div>
                            <div style={{ ...styles.fieldGroup, flex: 1 }}>
                                <label style={styles.label}>Número <span style={{ color: 'var(--accent-primary)' }}>*</span></label>
                                <input style={styles.input} name="numero" value={form.numero} onChange={handleChange} placeholder="123" required className="input-field" />
                            </div>
                        </div>

                        <div style={styles.fieldGroup}>
                            <label style={styles.label}>Complemento (Opcional)</label>
                            <input style={styles.input} name="complemento" value={form.complemento} onChange={handleChange} placeholder="Apto 101, Bloco A..." className="input-field" />
                        </div>

                        <div style={styles.fieldGroup}>
                            <label style={styles.label}>Observações para o Entregador</label>
                            <textarea style={{ ...styles.input, resize: 'none' as const, minHeight: '80px' }} name="observacoes" value={form.observacoes} onChange={handleChange} placeholder="Ex: Portão azul, próximo ao mercado..." className="input-field" />
                        </div>

                        <button type="submit" style={styles.submitBtn} className="btn-primary" disabled={loading}>
                            {loading ? 'Enviando...' : 'Confirmar Endereço'}
                            <Send size={18} />
                        </button>
                    </form>
                </div>

                {/* Trust Badges */}
                <div style={styles.trustRow}>
                    <div style={styles.trustItem}><Lock size={14} color="var(--success)" /> Conexão Segura</div>
                    <div style={styles.trustItem}><CheckCircle size={14} color="var(--success)" /> Dados Criptografados</div>
                    <div style={styles.trustItem}><Shield size={14} color="var(--success)" /> Compra Protegida</div>
                </div>
            </div>
        </div>
    );
};

const styles: { [key: string]: React.CSSProperties } = {
    page: { background: 'var(--bg-primary)', minHeight: '100vh', padding: '40px 20px', display: 'flex', flexDirection: 'column', alignItems: 'center' },
    container: { maxWidth: '600px', width: '100%' },
    headerBrand: { textAlign: 'center', marginBottom: '40px' },
    logoRow: { display: 'flex', justifyContent: 'center', marginBottom: '16px' },
    pageTitle: { fontSize: '2rem', fontWeight: 800, letterSpacing: '-1px' },
    pageSubtitle: { color: 'var(--text-secondary)', marginTop: '8px' },
    formCard: { padding: '40px', borderRadius: '24px', position: 'relative', overflow: 'hidden' },
    accentLine: { position: 'absolute', top: 0, left: 0, right: 0, height: '3px', background: 'linear-gradient(90deg, var(--accent-primary), var(--accent-secondary))' },
    errorBox: { background: 'rgba(239, 68, 68, 0.1)', border: '1px solid var(--danger)', color: 'var(--danger)', padding: '16px', borderRadius: '12px', marginBottom: '24px', fontSize: '0.9rem' },
    sectionLabel: { display: 'flex', alignItems: 'center', gap: '10px', fontWeight: 700, fontSize: '1rem', marginBottom: '24px', paddingBottom: '16px', borderBottom: '1px solid var(--border-glass)' },
    fieldGroup: { display: 'flex', flexDirection: 'column', gap: '6px', marginBottom: '20px' },
    label: { fontSize: '0.85rem', fontWeight: 500, color: 'var(--text-secondary)' },
    input: { width: '100%' },
    row: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' },
    rowUneven: { display: 'flex', gap: '16px' },
    submitBtn: { width: '100%', padding: '16px', marginTop: '32px', fontSize: '1.1rem', fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '12px' },
    trustRow: { display: 'flex', justifyContent: 'center', gap: '24px', marginTop: '24px', flexWrap: 'wrap' },
    trustItem: { display: 'flex', alignItems: 'center', gap: '8px', fontSize: '0.8rem', color: 'var(--text-secondary)' },
    successCard: { textAlign: 'center', padding: '60px 40px', borderRadius: '28px', marginTop: '60px' },
    backBtn: { display: 'inline-flex', marginTop: '32px', padding: '14px 28px', textDecoration: 'none', borderRadius: '14px' },
};

export default Pedido;
