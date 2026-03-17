import React from 'react';
import { X } from 'lucide-react';

interface ModalProps {
    open: boolean;
    onClose: () => void;
    title: string;
    icon: React.ReactNode;
    children: React.ReactNode;
    maxWidth?: string;
}

const Modal: React.FC<ModalProps> = ({ open, onClose, title, icon, children, maxWidth = '640px' }) => {
    if (!open) return null;
    return (
        <div style={{
            position: 'fixed', inset: 0, zIndex: 1000, background: 'rgba(0,0,0,0.85)',
            backdropFilter: 'blur(12px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '16px',
            animation: 'fadeIn 0.3s ease'
        }} onClick={e => e.target === e.currentTarget && onClose()}>
            <div style={{
                background: 'rgba(255, 255, 255, 0.95)', border: '1px solid rgba(0,80,200,0.1)', borderRadius: '28px',
                width: '100%', maxWidth, maxHeight: '92vh', overflow: 'hidden',
                boxShadow: '0 24px 80px rgba(0,40,120,0.15), 0 0 0 1px rgba(0,80,200,0.05)',
                display: 'flex', flexDirection: 'column', animation: 'modalSlide 0.4s cubic-bezier(0.16, 1, 0.3, 1)'
            }}>
                <div style={{
                    padding: '24px 32px', borderBottom: '1px solid rgba(0,80,200,0.06)',
                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                    background: 'rgba(0,85,255,0.02)'
                }}>
                    <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: '14px', fontSize: '1.25rem', fontWeight: 900, background: 'linear-gradient(135deg, #0a1628, #0055ff)', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent' }}>
                        <div style={{ width: '36px', height: '36px', borderRadius: '10px', background: 'rgba(0, 85, 255, 0.08)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{icon}</div>
                        {title}
                    </h3>
                    <button onClick={onClose} style={{ background: 'rgba(0,85,255,0.04)', border: '1px solid rgba(0,80,200,0.08)', color: 'var(--text-secondary)', cursor: 'pointer', padding: '10px', borderRadius: '12px', display: 'flex', alignItems: 'center', transition: '0.2s' }}>
                        <X size={20} />
                    </button>
                </div>
                <div style={{ padding: '32px', overflowY: 'auto', flex: 1 }}>
                    {children}
                </div>
            </div>
        </div>
    );
};

export default Modal;
