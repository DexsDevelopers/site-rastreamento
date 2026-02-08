/**
 * Código Auto-Increment - Incrementa automaticamente o último número/letra do código
 * 
 * Funcionalidades:
 * - Salva o último código usado no localStorage
 * - Incrementa automaticamente ao adicionar novo código
 * - Botão para incrementar manualmente
 * - Detecta padrão (número ou letra) e incrementa corretamente
 */

(function() {
    'use strict';
    
    const STORAGE_KEY = 'helmer_last_codigo';
    const CODIGO_INPUT_ID = 'codigo';
    
    /**
     * Extrai o último número ANTES de sufixos fixos como "BR"
     * Retorna: { base: string, number: string, suffix: string }
     */
    function extractLastChar(codigo) {
        if (!codigo || codigo.length === 0) return null;
        
        // Primeiro, verifica se termina com sufixo de 2-3 letras MAIÚSCULAS (BR, US, etc.)
        // Se sim, extrai o número ANTES do sufixo
        const suffixMatch = codigo.match(/^(.+?)(\d+)([A-Z]{2,3})$/);
        if (suffixMatch) {
            const base = suffixMatch[1];        // Ex: "GH56YJ"
            const number = suffixMatch[2];      // Ex: "1492"
            const suffix = suffixMatch[3];      // Ex: "BR"
            
            // Validação: garante que o sufixo são apenas letras maiúsculas
            if (suffix && /^[A-Z]{2,3}$/.test(suffix) && number && base) {
                return {
                    base: base,
                    last: number,
                    suffix: suffix,
                    type: 'number_with_suffix'
                };
            }
        }
        
        // Se não encontrou sufixo, procura o último número no final
        const match = codigo.match(/^(.+?)(\d+)$/);
        if (match) {
            const numStr = match[2];
            const base = match[1];
            return {
                base: base,
                last: numStr,
                suffix: '',
                type: 'number'
            };
        }
        
        // Não tenta extrair letras - retorna null
        return null;
    }
    
    /**
     * Incrementa o último número (respeitando sufixo fixo como BR)
     */
    function incrementLastChar(extracted) {
        if (!extracted) return null;
        
        if (extracted.type === 'number_with_suffix') {
            // Incrementa o número mas mantém o sufixo (ex: GH56YJ1492BR -> GH56YJ1493BR)
            const num = parseInt(extracted.last, 10);
            const nextNum = num + 1;
            // Mantém o mesmo tamanho do número original (preserva zeros à esquerda)
            const nextStr = String(nextNum).padStart(extracted.last.length, '0');
            const resultado = extracted.base + nextStr + extracted.suffix;
            console.log('Incremento com sufixo:', {
                original: extracted.base + extracted.last + extracted.suffix,
                incrementado: resultado,
                base: extracted.base,
                numero_antigo: extracted.last,
                numero_novo: nextStr,
                sufixo: extracted.suffix
            });
            return resultado;
        } else if (extracted.type === 'number') {
            // Número simples sem sufixo
            const num = parseInt(extracted.last, 10);
            const nextNum = num + 1;
            const nextStr = String(nextNum).padStart(extracted.last.length, '0');
            return extracted.base + nextStr;
        } else {
            // Não deve chegar aqui - tipos válidos são 'number_with_suffix' e 'number'
            console.error('Tipo desconhecido no incremento:', extracted.type);
            return null;
        }
    }
    
    /**
     * Incrementa código automaticamente
     */
    function incrementCodigo() {
        const input = document.getElementById(CODIGO_INPUT_ID);
        if (!input) return;
        
        const currentValue = input.value.trim().toUpperCase();
        if (!currentValue) {
            // Se estiver vazio, tenta pegar do localStorage
            const lastCodigo = localStorage.getItem(STORAGE_KEY);
            if (lastCodigo) {
                input.value = incrementLastChar(extractLastChar(lastCodigo)) || '';
                input.dispatchEvent(new Event('input'));
                return;
            }
            return;
        }
        
        const extracted = extractLastChar(currentValue);
        if (extracted) {
            const nextCodigo = incrementLastChar(extracted);
            if (nextCodigo) {
                input.value = nextCodigo;
                input.dispatchEvent(new Event('input'));
                
                // Feedback visual
                if (typeof MessageManager !== 'undefined') {
                    MessageManager.info(`Código incrementado para: ${nextCodigo}`);
                }
            }
        } else {
            // Se não conseguiu extrair, adiciona "1" no final
            input.value = currentValue + '1';
            input.dispatchEvent(new Event('input'));
        }
    }
    
    /**
     * Salva código quando o formulário é submetido com sucesso
     */
    function saveLastCodigo(codigo) {
        if (codigo && codigo.trim()) {
            localStorage.setItem(STORAGE_KEY, codigo.trim().toUpperCase());
        }
    }
    
    /**
     * Carrega último código no input se estiver vazio
     */
    function loadLastCodigo() {
        const input = document.getElementById(CODIGO_INPUT_ID);
        if (!input) return;
        
        // Se o input já tem valor, não faz nada
        if (input.value.trim()) return;
        
        const lastCodigo = localStorage.getItem(STORAGE_KEY);
        if (lastCodigo) {
            // Sugere o próximo código
            const extracted = extractLastChar(lastCodigo);
            if (extracted) {
                const nextCodigo = incrementLastChar(extracted);
                if (nextCodigo) {
                    input.value = nextCodigo;
                    input.placeholder = `Último usado: ${lastCodigo} → Sugestão: ${nextCodigo}`;
                }
            }
        }
    }
    
    /**
     * Adiciona botão de incremento ao lado do input
     */
    function addIncrementButton() {
        const input = document.getElementById(CODIGO_INPUT_ID);
        if (!input) return;
        
        // Verifica se o botão já existe
        if (input.parentElement.querySelector('.btn-increment-codigo')) {
            return;
        }
        
        // Cria container se não existir
        let container = input.parentElement;
        if (!container.classList.contains('input-with-button')) {
            const newContainer = document.createElement('div');
            newContainer.className = 'input-with-button';
            newContainer.style.cssText = 'position: relative; display: flex; gap: 8px;';
            input.parentNode.insertBefore(newContainer, input);
            newContainer.appendChild(input);
            container = newContainer;
        }
        
        // Cria botão
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-increment-codigo';
        btn.innerHTML = '<i class="fas fa-plus"></i> +1';
        btn.title = 'Incrementar último número/letra do código';
        btn.style.cssText = `
            padding: 8px 12px;
            background: linear-gradient(135deg, #0055FF, #FF6600);
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 85, 255, 0.3);
        `;
        
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 6px 16px rgba(0, 85, 255, 0.4)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 12px rgba(0, 85, 255, 0.3)';
        });
        
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            incrementCodigo();
        });
        
        container.appendChild(btn);
        
        // Adiciona atalho de teclado (Ctrl/Cmd + Plus)
        input.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === '+' || e.key === '=')) {
                e.preventDefault();
                incrementCodigo();
            }
        });
    }
    
    /**
     * Monitora submissão do formulário para salvar código
     */
    function setupFormMonitoring() {
        const form = document.getElementById('addForm');
        if (!form) return;
        
        // Salvar código quando o formulário for submetido (antes do submit)
        form.addEventListener('submit', function(e) {
            const input = document.getElementById(CODIGO_INPUT_ID);
            if (input && input.value.trim()) {
                saveLastCodigo(input.value);
            }
        });
        
        // Também salvar quando houver sucesso na validação AJAX
        // (caso o formulário use validação antes do submit)
        const codigoInput = document.getElementById(CODIGO_INPUT_ID);
        if (codigoInput) {
            codigoInput.addEventListener('blur', function() {
                if (this.value.trim()) {
                    // Salva temporariamente (não persiste até submit bem-sucedido)
                    // Mas ajuda no caso de refresh acidental
                    localStorage.setItem(STORAGE_KEY + '_temp', this.value.trim().toUpperCase());
                }
            });
        }
    }
    
    /**
     * Inicialização
     */
    function init() {
        // Aguarda DOM estar pronto
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }
        
        // Aguarda um pouco para garantir que o input existe
        setTimeout(function() {
            addIncrementButton();
            loadLastCodigo();
            setupFormMonitoring();
            
            // Atualiza placeholder com dica
            const input = document.getElementById(CODIGO_INPUT_ID);
            if (input && !input.placeholder.includes('Ctrl')) {
                const currentPlaceholder = input.placeholder || 'Digite o código...';
                input.placeholder = `${currentPlaceholder} (Ctrl + Plus para incrementar)`;
            }
        }, 100);
    }
    
    // Inicializar
    init();
    
    // Exportar função para uso global se necessário
    window.incrementCodigo = incrementCodigo;
})();

