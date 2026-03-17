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
            position: 'fixed', inset: 0, zIndex: 1000, background: 'rgba(5, 8, 15, 0.85)',
            backdropFilter: 'blur(20px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '16px',
            animation: 'fadeInSaas 0.3s ease-out'
        }} onClick={e => e.target === e.currentTarget && onClose()}>
            <style>{`
                @keyframes fadeInSaas { from { opacity: 0; } to { opacity: 1; } }
                @keyframes springIn {
                    0% { opacity: 0; transform: scale(0.95) translateY(10px); }
                    100% { opacity: 1; transform: scale(1) translateY(0); }
                }
                .modal-content-saas {
                    background: rgba(15, 23, 42, 0.95);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 24px;
                    box-shadow: 0 40px 100px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.05);
                    animation: springIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .modal-header-saas h3 {
                    background: linear-gradient(to right, #fff, #94a3b8);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                }
            `}</style>

            <div className="modal-content-saas" style={{
                width: '100%', maxWidth, maxHeight: '92vh', overflow: 'hidden',
                display: 'flex', flexDirection: 'column'
            }}>
                <div className="modal-header-saas" style={{
                    padding: '24px 32px', borderBottom: '1px solid rgba(255,255,255,0.06)',
                    display: 'flex', justifyContent: 'space-between', alignItems: 'center'
                }}>
                    <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: '14px', fontSize: '1.4rem', fontWeight: 800 }}>
                        <div style={{
                            width: '40px', height: '40px', borderRadius: '12px',
                            background: 'rgba(59, 130, 246, 0.1)', border: '1px solid rgba(59,130,246,0.2)',
                            display: 'flex', alignItems: 'center', justifyContent: 'center'
                        }}>{icon}</div>
                        {title}
                    </h3>
                    <button onClick={onClose} style={{
                        background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.1)',
                        color: '#94a3b8', cursor: 'pointer', padding: '10px', borderRadius: '12px',
                        display: 'flex', alignItems: 'center', transition: '0.2s'
                    }} onMouseOver={e => e.currentTarget.style.color = '#fff'}>
                        <X size={20} />
                    </button>
                </div>
                <div style={{ padding: '32px', overflowY: 'auto', flex: 1, color: '#f8fafc' }}>
                    {children}
                </div>
            </div>
        </div>
    );
};

export default Modal;
