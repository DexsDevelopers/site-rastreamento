/**
 * UI Enhancements - Melhorias de UX/UI
 * Loading states, mensagens de erro, confirmações
 */

// ===== LOADING STATES =====
const LoadingManager = {
    show(element, message = 'Carregando...') {
        // Remover loading anterior se existir
        this.hide(element);
        
        const loader = document.createElement('div');
        loader.className = 'loading-overlay';
        loader.innerHTML = `
            <div class="loading-spinner-container">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">${message}</span>
                </div>
                <span class="loading-text">${message}</span>
            </div>
        `;
        
        // Estilos inline para garantir funcionamento
        loader.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            border-radius: inherit;
        `;
        
        // Garantir que o elemento pai tenha position relative
        const computedStyle = window.getComputedStyle(element);
        if (computedStyle.position === 'static') {
            element.style.position = 'relative';
        }
        
        element.appendChild(loader);
        element.dataset.loading = 'true';
        
        return loader;
    },
    
    hide(element) {
        const loader = element.querySelector('.loading-overlay');
        if (loader) {
            loader.remove();
            delete element.dataset.loading;
        }
    },
    
    wrapAsync(fn, element, message = 'Carregando...') {
        return async (...args) => {
            const loader = this.show(element, message);
            try {
                const result = await fn(...args);
                return result;
            } finally {
                this.hide(element);
            }
        };
    }
};

// ===== MENSAGENS DE ERRO MELHORADAS =====
const MessageManager = {
    show(message, type = 'info', duration = 5000) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        
        const colors = {
            success: '#28a745',
            error: '#dc3545',
            warning: '#ffc107',
            info: '#17a2b8'
        };
        
        const toast = document.createElement('div');
        toast.className = `toast-message toast-${type}`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #fff;
            border-left: 4px solid ${colors[type]};
            border-radius: 8px;
            padding: 16px 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            max-width: 500px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        
        toast.innerHTML = `
            <i class="fas fa-${icons[type] || 'info-circle'}" style="color: ${colors[type]}; font-size: 20px;"></i>
            <span style="flex: 1; color: #333;">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()" style="
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #999;
                padding: 0;
                margin-left: 16px;
                line-height: 1;
            ">×</button>
        `;
        
        document.body.appendChild(toast);
        
        // Remover automaticamente
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
        
        return toast;
    },
    
    success(message, duration = 3000) {
        return this.show(message, 'success', duration);
    },
    
    error(message, duration = 5000) {
        return this.show(message, 'error', duration);
    },
    
    warning(message, duration = 4000) {
        return this.show(message, 'warning', duration);
    },
    
    info(message, duration = 3000) {
        return this.show(message, 'info', duration);
    }
};

// ===== CONFIRMAÇÕES =====
const ConfirmManager = {
    show(message, options = {}) {
        const {
            title = 'Tem certeza?',
            confirmText = 'Sim, confirmar',
            cancelText = 'Cancelar',
            confirmColor = '#dc3545',
            cancelColor = '#6c757d',
            onConfirm = () => {},
            onCancel = () => {}
        } = options;
        
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'confirm-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10001;
            `;
            
            const modal = document.createElement('div');
            modal.style.cssText = `
                background: #fff;
                border-radius: 12px;
                padding: 30px;
                max-width: 500px;
                width: 90%;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            `;
            
            modal.innerHTML = `
                <h3 style="margin: 0 0 16px 0; color: #333; font-size: 1.5rem;">${title}</h3>
                <p style="margin: 0 0 24px 0; color: #666; line-height: 1.5;">${message}</p>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button class="confirm-cancel" style="
                        padding: 10px 20px;
                        border: 1px solid ${cancelColor};
                        background: #fff;
                        color: ${cancelColor};
                        border-radius: 6px;
                        cursor: pointer;
                        font-size: 14px;
                        font-weight: 500;
                    ">${cancelText}</button>
                    <button class="confirm-ok" style="
                        padding: 10px 20px;
                        border: none;
                        background: ${confirmColor};
                        color: #fff;
                        border-radius: 6px;
                        cursor: pointer;
                        font-size: 14px;
                        font-weight: 500;
                    ">${confirmText}</button>
                </div>
            `;
            
            overlay.appendChild(modal);
            document.body.appendChild(overlay);
            
            const close = (confirmed) => {
                overlay.remove();
                if (confirmed) {
                    onConfirm();
                    resolve(true);
                } else {
                    onCancel();
                    resolve(false);
                }
            };
            
            modal.querySelector('.confirm-ok').addEventListener('click', () => close(true));
            modal.querySelector('.confirm-cancel').addEventListener('click', () => close(false));
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) close(false);
            });
        });
    }
};

// ===== AJAX HELPER COM LOADING E ERROR HANDLING =====
const AjaxHelper = {
    async request(url, options = {}) {
        const {
            method = 'GET',
            body = null,
            headers = {},
            showLoading = true,
            loadingElement = document.body,
            loadingMessage = 'Carregando...',
            showSuccess = false,
            successMessage = 'Operação realizada com sucesso!',
            showError = true,
            errorMessage = 'Erro ao processar solicitação'
        } = options;
        
        let loader = null;
        if (showLoading && loadingElement) {
            loader = LoadingManager.show(loadingElement, loadingMessage);
        }
        
        try {
            const fetchOptions = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    ...headers
                }
            };
            
            if (body && method !== 'GET') {
                fetchOptions.body = typeof body === 'string' ? body : JSON.stringify(body);
            }
            
            const response = await fetch(url, fetchOptions);
            const data = await response.json().catch(() => ({}));
            
            if (!response.ok) {
                throw new Error(data.message || errorMessage || `HTTP ${response.status}`);
            }
            
            if (showSuccess) {
                MessageManager.success(successMessage);
            }
            
            return { success: true, data };
            
        } catch (error) {
            if (showError) {
                MessageManager.error(error.message || errorMessage);
            }
            throw error;
        } finally {
            if (loader) {
                LoadingManager.hide(loadingElement);
            }
        }
    },
    
    get(url, options = {}) {
        return this.request(url, { ...options, method: 'GET' });
    },
    
    post(url, body, options = {}) {
        return this.request(url, { ...options, method: 'POST', body });
    },
    
    delete(url, options = {}) {
        return this.request(url, { ...options, method: 'DELETE' });
    },
    
    put(url, body, options = {}) {
        return this.request(url, { ...options, method: 'PUT', body });
    }
};

// ===== CSS ANIMATIONS =====
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
    
    .loading-spinner-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 16px;
    }
    
    .loading-text {
        color: #fff;
        font-size: 14px;
        font-weight: 500;
    }
    
    .spinner-border {
        width: 3rem;
        height: 3rem;
        border: 4px solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: spinner-rotate 0.75s linear infinite;
    }
    
    @keyframes spinner-rotate {
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Exportar para uso global
window.LoadingManager = LoadingManager;
window.MessageManager = MessageManager;
window.ConfirmManager = ConfirmManager;
window.AjaxHelper = AjaxHelper;

