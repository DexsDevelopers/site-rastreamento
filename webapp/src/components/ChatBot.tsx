import { useState, useRef, useEffect } from 'react';
import { MessageCircle, X, Send, Bot } from 'lucide-react';

interface Message {
    role: 'bot' | 'user';
    text: string;
    time: string;
}

const knowledgeBase: { keywords: string[]; response: string }[] = [
    {
        keywords: ['rastrear', 'rastreio', 'rastreamento', 'codigo', 'código', 'localizar', 'onde está', 'onde esta', 'cadê', 'cade', 'encontrar pacote', 'como rastrear'],
        response: '📦 Para rastrear seu pacote:\n\n1. Acesse a página de **Rastreio** no menu\n2. Insira o **código de rastreamento** fornecido pelo remetente\n3. Digite sua **cidade**\n4. Clique em **Rastrear**\n\nO código é enviado por e-mail, SMS ou WhatsApp pelo remetente. Você verá todas as etapas da entrega em tempo real!'
    },
    {
        keywords: ['taxa', 'pagar', 'pagamento', 'pix', 'qr code', 'pendente', 'cobrança', 'cobrar', 'porque taxa', 'por que taxa', 'motivo taxa'],
        response: '💰 Sobre a **taxa de entrega**:\n\nAlguns envios possuem uma taxa de entrega que é cobrada do destinatário. Isso acontece quando o remetente opta pelo envio com **frete a cobrar** (FOB).\n\n**Como pagar:**\n1. Rastreie seu pacote normalmente\n2. Se houver taxa pendente, aparecerá um botão **"Pagar Taxa"**\n3. Clique e pague via **PIX** com QR Code\n4. A confirmação é **instantânea** e o pacote segue para entrega\n\nSe você acredita que a taxa é indevida, entre em contato com o remetente.'
    },
    {
        keywords: ['entrega', 'prazo', 'demora', 'quanto tempo', 'quando chega', 'previsão', 'estimativa', 'dias'],
        response: '🚚 **Prazos de entrega:**\n\n• **Loggi Express** (mesma cidade): até **24h**\n• **Regiões metropolitanas**: 1 a 3 dias úteis\n• **Capitais**: 2 a 5 dias úteis\n• **Interior**: 3 a 7 dias úteis\n\nO prazo pode variar conforme a modalidade escolhida pelo remetente e condições logísticas. Acompanhe em tempo real pelo rastreio!'
    },
    {
        keywords: ['status', 'postado', 'transito', 'trânsito', 'saiu para entrega', 'entregue', 'devolvido', 'etapa', 'significado'],
        response: '📋 **Significado dos status:**\n\n• **Postado** — O pacote foi entregue à Loggi\n• **Em trânsito** — Está sendo transportado entre centros\n• **Em rota de entrega** — Saiu com o entregador para seu endereço\n• **Entregue** — Foi entregue com sucesso\n• **Tentativa de entrega** — O entregador tentou mas não conseguiu entregar\n• **Devolvido** — O pacote está retornando ao remetente\n\nSe tiver dúvidas sobre um status específico, me pergunte!'
    },
    {
        keywords: ['endereço', 'endereco', 'alterar', 'mudar', 'trocar endereço', 'endereço errado'],
        response: '📍 **Alteração de endereço:**\n\nA alteração só pode ser feita **antes do pacote sair para entrega**.\n\nPara solicitar:\n1. Entre em contato com o **remetente** do pacote\n2. Ou acesse a **Central de Ajuda** e abra um chamado\n\n⚠️ Após o status "Saiu para entrega", não é mais possível alterar o endereço.'
    },
    {
        keywords: ['não recebi', 'nao recebi', 'não chegou', 'nao chegou', 'sumiu', 'perdeu', 'extraviado', 'extraviou'],
        response: '😟 **Pacote não recebido:**\n\nSe o rastreio mostra "Entregue" mas você não recebeu:\n\n1. Verifique com **vizinhos** ou **portaria**\n2. Confira se o endereço está correto no rastreio\n3. Entre em contato conosco em até **48 horas**\n\nAbriremos uma **investigação** e resolveremos seu caso. A Loggi garante a segurança de todas as entregas.'
    },
    {
        keywords: ['frete', 'calculo', 'cálculo', 'calcular', 'valor', 'preço', 'preco', 'custo', 'enviar', 'envio', 'mandar'],
        response: '📤 **Enviar um pacote:**\n\n1. Acesse **"Enviar agora"** na página inicial\n2. Preencha os dados de origem e destino\n3. Escolha a modalidade de entrega\n4. Gere a **etiqueta** e poste em um ponto Loggi\n\nO cálculo do frete é feito automaticamente com base no CEP, peso e dimensões. **Frete grátis** disponível para algumas modalidades!'
    },
    {
        keywords: ['api', 'integração', 'integracao', 'ecommerce', 'loja', 'sistema', 'bling', 'tiny', 'erp', 'empresa'],
        response: '🔗 **Integração API / E-commerce:**\n\nA Loggi oferece API completa para empresas:\n\n• **Geração automática** de etiquetas\n• **Webhooks** para atualização de status\n• **Integração com ERPs** (Tiny, Bling, etc.)\n• **Coleta em lote** diária\n• **Logística reversa** para devoluções\n\nAcesse a página **"API & Ecommerce"** no menu para mais detalhes e documentação técnica.'
    },
    {
        keywords: ['loggi pro', 'plano', 'assinatura', 'premium', 'benefício', 'beneficio', 'vantagem'],
        response: '⭐ **Loggi Pro:**\n\nNosso plano premium oferece:\n\n• **Frete grátis** ilimitado\n• **Prioridade** na entrega\n• **Rastreio avançado** com notificações\n• **Suporte prioritário** 24/7\n• **Cashback** em envios\n\nAcesse **"Loggi Pro"** no menu para conhecer os planos e preços!'
    },
    {
        keywords: ['suporte', 'atendimento', 'contato', 'telefone', 'email', 'falar', 'humano', 'vendedor', 'atendente', 'pessoa'],
        response: '👤 Entendi! Vou encaminhar você para um de nossos **atendentes humanos**.\n\n📱 **WhatsApp:** Entre em contato pelo nosso número oficial\n📧 **E-mail:** suporte@loggi.com\n📞 **Central:** 0800-LOG-GI00\n\nNosso time está disponível **24/7** para te atender. Obrigado por usar a Loggi! 💙'
    },
    {
        keywords: ['segurança', 'seguranca', 'seguro', 'proteção', 'protecao', 'confiável', 'confiavel'],
        response: '🔒 **Segurança Loggi:**\n\n• **Rastreamento completo** do início ao fim\n• **Seguro automático** em todos os envios\n• **Verificação de identidade** na entrega\n• **Dados criptografados** (SSL)\n• **Conformidade com LGPD**\n\nSua encomenda e seus dados estão protegidos conosco!'
    },
    {
        keywords: ['obrigado', 'obrigada', 'valeu', 'thanks', 'agradeço', 'agradeco'],
        response: '💙 De nada! Fico feliz em ajudar. Se tiver mais alguma dúvida, é só perguntar. A Loggi está aqui pra você! 🚀'
    },
    {
        keywords: ['oi', 'olá', 'ola', 'hey', 'eae', 'eai', 'bom dia', 'boa tarde', 'boa noite', 'hello'],
        response: 'Olá! 👋 Sou o assistente virtual da **Loggi**!\n\nPosso te ajudar com:\n• 📦 **Rastreamento** de pacotes\n• 💰 **Taxas** e pagamentos\n• 🚚 **Prazos** de entrega\n• 📤 **Envio** de pacotes\n• ❓ **Dúvidas** frequentes\n\nDigite sua pergunta que eu respondo! 😊'
    }
];

const quickOptions = [
    { label: '📦 Como rastrear?', message: 'Como rastrear meu pacote?' },
    { label: '💰 Taxa pendente', message: 'Por que tenho taxa pendente?' },
    { label: '🚚 Prazo de entrega', message: 'Quanto tempo demora pra entregar?' },
    { label: '👤 Falar com atendente', message: 'Quero falar com um atendente humano' },
];

function getResponse(input: string): string {
    const lower = input.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

    let bestMatch = { score: 0, response: '' };

    for (const item of knowledgeBase) {
        let score = 0;
        for (const kw of item.keywords) {
            const kwNorm = kw.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            if (lower.includes(kwNorm)) {
                score += kwNorm.length;
            }
        }
        if (score > bestMatch.score) {
            bestMatch = { score, response: item.response };
        }
    }

    if (bestMatch.score > 0) return bestMatch.response;

    return '🤔 Não encontrei uma resposta específica para sua pergunta.\n\nPosso te ajudar com:\n• Rastreamento de pacotes\n• Taxas e pagamentos\n• Prazos de entrega\n• Envio de pacotes\n• Informações sobre a Loggi\n\nSe preferir, digite **"atendente"** para falar com um humano. 👤';
}

function getTime(): string {
    return new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

const ChatBot = () => {
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState<Message[]>([
        { role: 'bot', text: 'Olá! 👋 Sou o assistente da **Loggi**! Como posso ajudar você hoje?', time: getTime() }
    ]);
    const [input, setInput] = useState('');
    const [isTyping, setIsTyping] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, isTyping]);

    useEffect(() => {
        if (isOpen) inputRef.current?.focus();
    }, [isOpen]);

    const sendMessage = (text?: string) => {
        const msg = text || input.trim();
        if (!msg) return;

        const userMsg: Message = { role: 'user', text: msg, time: getTime() };
        setMessages(prev => [...prev, userMsg]);
        setInput('');
        setIsTyping(true);

        setTimeout(() => {
            const response = getResponse(msg);
            const botMsg: Message = { role: 'bot', text: response, time: getTime() };
            setMessages(prev => [...prev, botMsg]);
            setIsTyping(false);
        }, 600 + Math.random() * 800);
    };

    const formatText = (text: string) => {
        return text.split('\n').map((line, i) => {
            const formatted = line
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>');
            return <div key={i} dangerouslySetInnerHTML={{ __html: formatted }} style={{ minHeight: line.trim() ? 'auto' : '8px' }} />;
        });
    };

    return (
        <>
            <style>{`
                .chatbot-fab {
                    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
                    width: 60px; height: 60px; border-radius: 50%;
                    background: linear-gradient(135deg, #0055ff, #3b82f6);
                    border: none; cursor: pointer; color: white;
                    display: flex; align-items: center; justify-content: center;
                    box-shadow: 0 8px 32px rgba(0, 85, 255, 0.35);
                    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .chatbot-fab:hover {
                    transform: scale(1.1);
                    box-shadow: 0 12px 40px rgba(0, 85, 255, 0.5);
                }
                .chatbot-fab .pulse-ring {
                    position: absolute; inset: -4px; border-radius: 50%;
                    border: 2px solid rgba(0, 85, 255, 0.4);
                    animation: pulse-ring 2s infinite;
                }
                @keyframes pulse-ring {
                    0% { transform: scale(1); opacity: 1; }
                    100% { transform: scale(1.5); opacity: 0; }
                }

                .chatbot-window {
                    position: fixed; bottom: 100px; right: 24px; z-index: 9999;
                    width: min(420px, calc(100vw - 32px)); height: min(600px, calc(100vh - 140px));
                    border-radius: 24px; overflow: hidden;
                    background: #fff;
                    border: 1px solid rgba(0, 85, 255, 0.1);
                    box-shadow: 0 24px 64px rgba(0, 40, 120, 0.15);
                    display: flex; flex-direction: column;
                    animation: chat-in 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                    transform-origin: bottom right;
                }
                @keyframes chat-in {
                    from { opacity: 0; transform: scale(0.9) translateY(20px); }
                    to { opacity: 1; transform: scale(1) translateY(0); }
                }

                .chat-header {
                    padding: 20px 24px; display: flex; align-items: center; justify-content: space-between;
                    background: linear-gradient(135deg, #0055ff, #3b82f6);
                    color: white; flex-shrink: 0;
                }
                .chat-header-info { display: flex; align-items: center; gap: 12px; }
                .chat-avatar {
                    width: 40px; height: 40px; border-radius: 12px;
                    background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;
                }
                .chat-header h3 { font-size: 1rem; font-weight: 700; margin: 0; font-family: 'Outfit', sans-serif; }
                .chat-header p { font-size: 0.75rem; opacity: 0.8; margin: 2px 0 0; }
                .chat-close {
                    background: rgba(255,255,255,0.15); border: none; color: white; width: 32px; height: 32px;
                    border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center;
                    transition: background 0.2s;
                }
                .chat-close:hover { background: rgba(255,255,255,0.3); }

                .chat-messages {
                    flex: 1; overflow-y: auto; padding: 20px 16px;
                    display: flex; flex-direction: column; gap: 12px;
                    background: #f8fafc;
                }
                .chat-messages::-webkit-scrollbar { width: 4px; }
                .chat-messages::-webkit-scrollbar-thumb { background: rgba(0,85,255,0.15); border-radius: 4px; }

                .msg { max-width: 85%; display: flex; flex-direction: column; gap: 4px; animation: msg-in 0.3s ease; }
                .msg-bot { align-self: flex-start; }
                .msg-user { align-self: flex-end; }
                @keyframes msg-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

                .msg-bubble {
                    padding: 14px 18px; border-radius: 18px; font-size: 0.88rem; line-height: 1.6;
                }
                .msg-bot .msg-bubble {
                    background: white; color: #1e293b;
                    border: 1px solid rgba(0, 85, 255, 0.08);
                    border-bottom-left-radius: 6px;
                    box-shadow: 0 2px 8px rgba(0,40,120,0.04);
                }
                .msg-user .msg-bubble {
                    background: linear-gradient(135deg, #0055ff, #3b82f6); color: white;
                    border-bottom-right-radius: 6px;
                }
                .msg-time { font-size: 0.7rem; color: #94a3b8; padding: 0 4px; }
                .msg-user .msg-time { text-align: right; }

                .typing-indicator {
                    padding: 14px 18px; background: white; border-radius: 18px; border-bottom-left-radius: 6px;
                    border: 1px solid rgba(0, 85, 255, 0.08); display: inline-flex; gap: 4px; align-self: flex-start;
                    box-shadow: 0 2px 8px rgba(0,40,120,0.04);
                }
                .typing-dot {
                    width: 8px; height: 8px; border-radius: 50%; background: #94a3b8;
                    animation: typing-bounce 1.4s infinite;
                }
                .typing-dot:nth-child(2) { animation-delay: 0.2s; }
                .typing-dot:nth-child(3) { animation-delay: 0.4s; }
                @keyframes typing-bounce {
                    0%, 60%, 100% { transform: translateY(0); }
                    30% { transform: translateY(-6px); }
                }

                .quick-options {
                    padding: 12px 16px; display: flex; gap: 8px; flex-wrap: wrap;
                    background: white; border-top: 1px solid rgba(0, 85, 255, 0.06);
                }
                .quick-btn {
                    padding: 8px 14px; border-radius: 20px; border: 1px solid rgba(0, 85, 255, 0.12);
                    background: rgba(0, 85, 255, 0.04); color: #0055ff; font-size: 0.78rem; font-weight: 600;
                    cursor: pointer; transition: all 0.2s; white-space: nowrap;
                }
                .quick-btn:hover { background: rgba(0, 85, 255, 0.1); border-color: rgba(0, 85, 255, 0.25); }

                .chat-input-area {
                    padding: 16px; display: flex; gap: 10px; align-items: center;
                    background: white; border-top: 1px solid rgba(0, 85, 255, 0.06);
                    flex-shrink: 0;
                }
                .chat-input {
                    flex: 1; padding: 12px 16px; border-radius: 14px;
                    border: 1px solid rgba(0, 85, 255, 0.1); background: #f8fafc;
                    font-size: 0.88rem; outline: none; transition: all 0.3s;
                    color: #1e293b; font-family: 'Inter', sans-serif;
                }
                .chat-input:focus { border-color: #0055ff; background: white; box-shadow: 0 0 0 3px rgba(0,85,255,0.08); }
                .chat-input::placeholder { color: #94a3b8; }
                .chat-send {
                    width: 44px; height: 44px; border-radius: 14px;
                    background: linear-gradient(135deg, #0055ff, #3b82f6);
                    border: none; color: white; cursor: pointer;
                    display: flex; align-items: center; justify-content: center;
                    transition: all 0.3s; flex-shrink: 0;
                }
                .chat-send:hover { transform: scale(1.05); box-shadow: 0 4px 16px rgba(0,85,255,0.3); }
                .chat-send:disabled { opacity: 0.5; cursor: default; transform: none; }

                @media (max-width: 480px) {
                    .chatbot-window {
                        bottom: 0; right: 0; left: 0;
                        width: 100%; height: 100vh;
                        border-radius: 0;
                    }
                    .chatbot-fab { bottom: 16px; right: 16px; width: 52px; height: 52px; }
                }
            `}</style>

            {!isOpen && (
                <button className="chatbot-fab" onClick={() => setIsOpen(true)} aria-label="Abrir chat de suporte">
                    <div className="pulse-ring"></div>
                    <MessageCircle size={26} />
                </button>
            )}

            {isOpen && (
                <div className="chatbot-window">
                    <div className="chat-header">
                        <div className="chat-header-info">
                            <div className="chat-avatar"><Bot size={22} /></div>
                            <div>
                                <h3>Loggi Assistente</h3>
                                <p>🟢 Online agora</p>
                            </div>
                        </div>
                        <button className="chat-close" onClick={() => setIsOpen(false)}><X size={18} /></button>
                    </div>

                    <div className="chat-messages">
                        {messages.map((msg, i) => (
                            <div key={i} className={`msg msg-${msg.role}`}>
                                <div className="msg-bubble">
                                    {msg.role === 'bot' ? formatText(msg.text) : msg.text}
                                </div>
                                <span className="msg-time">{msg.time}</span>
                            </div>
                        ))}
                        {isTyping && (
                            <div className="typing-indicator">
                                <div className="typing-dot"></div>
                                <div className="typing-dot"></div>
                                <div className="typing-dot"></div>
                            </div>
                        )}
                        <div ref={messagesEndRef} />
                    </div>

                    {messages.length <= 2 && (
                        <div className="quick-options">
                            {quickOptions.map((opt, i) => (
                                <button key={i} className="quick-btn" onClick={() => sendMessage(opt.message)}>
                                    {opt.label}
                                </button>
                            ))}
                        </div>
                    )}

                    <div className="chat-input-area">
                        <input
                            ref={inputRef}
                            className="chat-input"
                            placeholder="Digite sua mensagem..."
                            value={input}
                            onChange={e => setInput(e.target.value)}
                            onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } }}
                        />
                        <button className="chat-send" onClick={() => sendMessage()} disabled={!input.trim()}>
                            <Send size={18} />
                        </button>
                    </div>
                </div>
            )}
        </>
    );
};

export default ChatBot;
