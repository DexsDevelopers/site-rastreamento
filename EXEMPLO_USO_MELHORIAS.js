/**
 * EXEMPLOS DE USO - Melhorias Implementadas
 * 
 * Este arquivo contém exemplos práticos de como usar os novos helpers
 * Copie e adapte para seu código
 */

// ============================================
// 1. LOADING STATES
// ============================================

// Exemplo 1: Loading simples
async function carregarRastreios() {
    const container = document.getElementById('lista-rastreios');
    
    // Mostrar loading
    LoadingManager.show(container, 'Carregando rastreios...');
    
    try {
        const response = await fetch('/api/rastreios');
        const data = await response.json();
        
        // Renderizar dados
        renderizarRastreios(data);
    } catch (error) {
        MessageManager.error('Erro ao carregar rastreios');
    } finally {
        // Ocultar loading
        LoadingManager.hide(container);
    }
}

// Exemplo 2: Loading automático com wrapper
async function salvarRastreio(dados) {
    const form = document.getElementById('form-rastreio');
    
    // Wrapper automático - mostra/oculta loading automaticamente
    const salvar = LoadingManager.wrapAsync(async () => {
        const response = await fetch('/api/criar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        });
        
        if (!response.ok) {
            throw new Error('Erro ao salvar');
        }
        
        return response.json();
    }, form, 'Salvando rastreio...');
    
    try {
        const result = await salvar();
        MessageManager.success('Rastreio criado com sucesso!');
        return result;
    } catch (error) {
        MessageManager.error(error.message);
        throw error;
    }
}

// ============================================
// 2. MENSAGENS (TOAST)
// ============================================

// Exemplo 1: Mensagens simples
function exemploMensagens() {
    // Sucesso
    MessageManager.success('Operação realizada com sucesso!');
    
    // Erro
    MessageManager.error('Erro ao processar solicitação');
    
    // Aviso
    MessageManager.warning('Atenção: Verifique os dados informados');
    
    // Informação
    MessageManager.info('Dica: Você pode usar este recurso para...');
}

// Exemplo 2: Mensagens customizadas
function exemploMensagensCustomizadas() {
    MessageManager.show('Mensagem customizada', 'success', 5000);
    MessageManager.show('Outra mensagem', 'error', 3000);
}

// ============================================
// 3. CONFIRMAÇÕES
// ============================================

// Exemplo 1: Confirmação simples
async function deletarRastreio(codigo) {
    const confirmed = await ConfirmManager.show(
        `Tem certeza que deseja deletar o rastreio ${codigo}?`,
        {
            title: 'Confirmar exclusão',
            confirmText: 'Sim, deletar',
            cancelText: 'Cancelar'
        }
    );
    
    if (confirmed) {
        try {
            await AjaxHelper.delete(`/api/deletar/${codigo}`, {
                showLoading: true,
                showSuccess: true,
                successMessage: 'Rastreio deletado com sucesso!'
            });
            
            // Recarregar lista
            carregarRastreios();
        } catch (error) {
            // Erro já foi mostrado pelo AjaxHelper
        }
    }
}

// Exemplo 2: Confirmação com callbacks
async function exemploConfirmacaoCallbacks() {
    await ConfirmManager.show(
        'Esta ação não pode ser desfeita!',
        {
            title: 'Atenção',
            onConfirm: () => {
                console.log('Ação confirmada!');
                executarAcao();
            },
            onCancel: () => {
                console.log('Ação cancelada');
            }
        }
    );
}

// ============================================
// 4. AJAX HELPER
// ============================================

// Exemplo 1: GET request
async function exemploGet() {
    try {
        const result = await AjaxHelper.get('/api/rastreios', {
            showLoading: true,
            loadingElement: document.getElementById('container'),
            loadingMessage: 'Carregando rastreios...',
            showSuccess: false,
            showError: true
        });
        
        console.log('Dados:', result.data);
        renderizarDados(result.data);
    } catch (error) {
        // Erro já foi mostrado automaticamente
        console.error('Erro:', error);
    }
}

// Exemplo 2: POST request
async function exemploPost(dados) {
    try {
        const result = await AjaxHelper.post('/api/criar', dados, {
            showLoading: true,
            loadingElement: document.getElementById('form'),
            showSuccess: true,
            successMessage: 'Rastreio criado com sucesso!',
            showError: true
        });
        
        console.log('Resultado:', result.data);
        
        // Limpar formulário ou redirecionar
        document.getElementById('form').reset();
    } catch (error) {
        // Erro já foi mostrado
    }
}

// Exemplo 3: DELETE request
async function exemploDelete(id) {
    // Primeiro confirmar
    const confirmed = await ConfirmManager.show(
        'Tem certeza que deseja deletar?'
    );
    
    if (!confirmed) return;
    
    try {
        await AjaxHelper.delete(`/api/deletar/${id}`, {
            showLoading: true,
            showSuccess: true,
            successMessage: 'Deletado com sucesso!'
        });
        
        // Recarregar ou remover elemento
        location.reload();
    } catch (error) {
        // Erro já foi mostrado
    }
}

// ============================================
// 5. EXEMPLO COMPLETO: FORMULÁRIO COM VALIDAÇÃO
// ============================================

async function exemploFormularioCompleto() {
    const form = document.getElementById('form-rastreio');
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Coletar dados
        const formData = new FormData(form);
        const dados = {
            codigo: formData.get('codigo'),
            cidade: formData.get('cidade'),
            telefone: formData.get('telefone'),
            email: formData.get('email')
        };
        
        // Validar no frontend (opcional, validação real deve ser no backend)
        if (!dados.codigo || dados.codigo.length < 3) {
            MessageManager.error('Código deve ter pelo menos 3 caracteres');
            return;
        }
        
        if (!dados.cidade || dados.cidade.length < 2) {
            MessageManager.error('Cidade é obrigatória');
            return;
        }
        
        // Enviar com loading e tratamento de erro
        try {
            const result = await AjaxHelper.post('/api/criar', dados, {
                showLoading: true,
                loadingElement: form,
                loadingMessage: 'Criando rastreio...',
                showSuccess: true,
                successMessage: 'Rastreio criado com sucesso!',
                showError: true
            });
            
            // Limpar formulário
            form.reset();
            
            // Recarregar lista
            carregarRastreios();
        } catch (error) {
            // Erro já foi mostrado
        }
    });
}

// ============================================
// 6. EXEMPLO COMPLETO: DELETE COM CONFIRMAÇÃO
// ============================================

function exemploDeleteCompleto() {
    // Adicionar listener em todos os botões de delete
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            
            const codigo = btn.dataset.codigo;
            const confirmed = await ConfirmManager.show(
                `Tem certeza que deseja deletar o rastreio ${codigo}?`,
                {
                    title: 'Confirmar exclusão',
                    confirmText: 'Sim, deletar',
                    cancelText: 'Cancelar',
                    confirmColor: '#dc3545'
                }
            );
            
            if (!confirmed) return;
            
            try {
                await AjaxHelper.delete(`/api/deletar/${codigo}`, {
                    showLoading: true,
                    loadingElement: btn.closest('tr'),
                    showSuccess: true,
                    successMessage: 'Rastreio deletado com sucesso!'
                });
                
                // Remover linha da tabela
                btn.closest('tr').remove();
            } catch (error) {
                // Erro já foi mostrado
            }
        });
    });
}

// ============================================
// 7. EXEMPLO: BULK DELETE
// ============================================

async function exemploBulkDelete() {
    // Coletar IDs selecionados
    const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked:not(#selectAll)');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) {
        MessageManager.warning('Selecione pelo menos um item para deletar');
        return;
    }
    
    const confirmed = await ConfirmManager.show(
        `Tem certeza que deseja deletar ${ids.length} item(ns)?`,
        {
            title: 'Confirmar exclusão em massa',
            confirmText: `Sim, deletar ${ids.length} item(ns)`,
            cancelText: 'Cancelar'
        }
    );
    
    if (!confirmed) return;
    
    try {
        await AjaxHelper.post('/api/bulk-delete', { ids }, {
            showLoading: true,
            loadingElement: document.getElementById('table-container'),
            showSuccess: true,
            successMessage: `${ids.length} item(ns) deletado(s) com sucesso!`
        });
        
        // Recarregar lista
        location.reload();
    } catch (error) {
        // Erro já foi mostrado
    }
}

// ============================================
// 8. EXEMPLO: FORMULÁRIO COM VALIDAÇÃO E AJAX
// ============================================

function exemploFormularioAjax() {
    const form = document.getElementById('form-rastreio');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Desabilitar botão
        submitBtn.disabled = true;
        
        // Coletar dados
        const formData = new FormData(form);
        const dados = Object.fromEntries(formData);
        
        try {
            const result = await AjaxHelper.post('/api/criar', dados, {
                showLoading: true,
                loadingElement: form,
                loadingMessage: 'Salvando...',
                showSuccess: true,
                successMessage: 'Salvo com sucesso!',
                showError: true
            });
            
            // Limpar formulário
            form.reset();
            
            // Fechar modal se estiver em um
            const modal = form.closest('.modal');
            if (modal) {
                bootstrap.Modal.getInstance(modal).hide();
            }
            
            // Recarregar dados se necessário
            if (typeof carregarDados === 'function') {
                carregarDados();
            }
        } catch (error) {
            // Erro já foi mostrado
        } finally {
            // Reabilitar botão
            submitBtn.disabled = false;
        }
    });
}

// ============================================
// INICIALIZAÇÃO
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    // Exemplos podem ser executados aqui
    console.log('UI Enhancements carregado!');
    console.log('LoadingManager:', LoadingManager);
    console.log('MessageManager:', MessageManager);
    console.log('ConfirmManager:', ConfirmManager);
    console.log('AjaxHelper:', AjaxHelper);
});

