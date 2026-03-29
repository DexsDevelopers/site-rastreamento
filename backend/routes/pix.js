const express = require('express');
const router = express.Router();
const { getDB } = require('../db');
const axios = require('axios');

const PIXGHOST_API_URL = process.env.PIXGHOST_API_URL || 'https://pixghost.site/api.php';
const PIXGHOST_API_KEY = process.env.PIXGHOST_API_KEY || '';

// Cache em memória: pix_id -> { codigo, amount, paid, created_at }
const pixPayments = new Map();

// Limpar pagamentos expirados a cada 5 minutos (expira em 25 min)
setInterval(() => {
    const now = Date.now();
    for (const [id, payment] of pixPayments) {
        if (now - payment.created_at > 25 * 60 * 1000) {
            pixPayments.delete(id);
        }
    }
}, 5 * 60 * 1000);

// ===== PIX API (PixGhost) =====

// Criar pagamento PIX
router.post('/create', async (req, res) => {
    try {
        const { amount, description, codigo } = req.body;
        const finalAmount = parseFloat(amount) || 29.90;

        const response = await axios.post(PIXGHOST_API_URL, {
            amount: finalAmount
        }, {
            headers: {
                'Authorization': `Bearer ${PIXGHOST_API_KEY}`,
                'Content-Type': 'application/json'
            },
            timeout: 10000
        });

        const ghost = response.data;

        if (ghost.success) {
            // Mapear resposta PixGhost para formato esperado pelo frontend
            const pixId = ghost.pix_id || ghost.id || `ghost_${Date.now()}`;

            // Guardar no cache para rastrear pagamento
            pixPayments.set(String(pixId), {
                codigo: codigo?.toUpperCase().trim() || null,
                amount: finalAmount,
                paid: false,
                created_at: Date.now()
            });

            console.log(`[PIXGHOST] PIX criado: ${pixId} | R$ ${finalAmount} | Código: ${codigo || 'N/A'}`);

            res.json({
                success: true,
                data: {
                    id: pixId,
                    payment_id: pixId,
                    amount: finalAmount,
                    qr_code: ghost.pix_code || ghost.qr_code || ghost.emv || '',
                    qr_image_url: ghost.qr_image || ghost.qr_image_url || '',
                    status: 'PENDING'
                }
            });
        } else {
            console.error('[PIXGHOST ERROR]', ghost);
            res.status(400).json({ success: false, error: ghost.error || ghost.message || 'Erro na API PixGhost' });
        }
    } catch (error) {
        console.error('[PIXGHOST CREATE ERROR]', error.response?.data || error.message);
        res.status(500).json({ success: false, error: 'Erro no servidor ao gerar PIX' });
    }
});

// Verificar status do pagamento (consultado pelo frontend via polling)
router.get('/status/:id', async (req, res) => {
    const db = getDB();
    try {
        const { id } = req.params;
        const codigo = req.query.codigo?.toUpperCase().trim();

        // Verificar no cache se o webhook já marcou como pago
        const cached = pixPayments.get(String(id));
        const isPaid = cached?.paid === true;

        if (isPaid && codigo && db) {
            await markAsPaid(db, codigo);
        }

        res.json({
            success: true,
            status: isPaid ? 'PAID' : 'PENDING',
            data: {
                status: isPaid ? 'PAID' : 'PENDING',
                pix_id: id
            }
        });
    } catch (error) {
        console.error('[PIXGHOST STATUS ERROR]', error.message);
        res.status(500).json({ success: false, error: 'Erro no servidor' });
    }
});

// Webhook recebido do PixGhost quando pagamento é confirmado
router.post('/webhook', async (req, res) => {
    const db = getDB();
    try {
        const data = req.body;
        console.log('[PIXGHOST WEBHOOK] Recebido:', JSON.stringify(data));

        const eventStatus = (data.status || '').toLowerCase();
        const isPaymentConfirmed = eventStatus === 'paid' || eventStatus === 'confirmed' || data.event === 'payment.completed';

        if (isPaymentConfirmed) {
            const pixId = String(data.pix_id || data.transaction_id || data.id || '');
            const cached = pixPayments.get(pixId);

            if (cached) {
                cached.paid = true;
                console.log(`[PIXGHOST WEBHOOK] Pagamento ${pixId} confirmado!`);

                // Se temos o código de rastreio vinculado, atualizar automaticamente
                if (cached.codigo && db) {
                    await markAsPaid(db, cached.codigo);
                }
            } else {
                console.log(`[PIXGHOST WEBHOOK] PIX ID ${pixId} não encontrado no cache local.`);
            }
        }

        res.status(200).json({ received: true });
    } catch (error) {
        console.error('[PIXGHOST WEBHOOK ERROR]', error.message);
        res.status(200).json({ received: true });
    }
});

// Função auxiliar: marcar rastreio como pago e inserir etapa de entrega
async function markAsPaid(db, codigo) {
    const cleanCodigo = codigo.toUpperCase().trim();

    const [existing] = await db.query(
        "SELECT id FROM rastreios_status WHERE codigo = ? AND titulo LIKE '%saiu para entrega%'",
        [cleanCodigo]
    );

    if (existing.length === 0) {
        const [rows] = await db.query("SELECT cidade, tipo_entrega FROM rastreios_status WHERE codigo = ? LIMIT 1", [cleanCodigo]);
        const cidade = rows.length > 0 ? rows[0].cidade : 'Centro de Distribuição';
        const tipoEntrega = rows.length > 0 ? rows[0].tipo_entrega : 'NORMAL';

        await db.query(
            "UPDATE rastreios_status SET taxa_valor = NULL, taxa_pix = NULL, taxa_paga = TRUE WHERE codigo = ?",
            [cleanCodigo]
        );

        await db.query(
            "INSERT INTO rastreios_status (codigo, cidade, status_atual, titulo, subtitulo, data, cor, tipo_entrega, taxa_paga) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, TRUE)",
            [
                cleanCodigo,
                cidade,
                '🚀 Objeto saiu para entrega',
                '🚀 Objeto saiu para entrega',
                'Pagamento confirmado! Seu pacote já foi liberado e está em rota de entrega para sua residência.',
                '#2563EB',
                tipoEntrega
            ]
        );

        console.log(`[PIXGHOST] Pagamento confirmado e status atualizado para: ${cleanCodigo}`);

        // [C] Auto-notificar cliente que pagamento foi confirmado e objeto liberado
        try {
            const bot = global._bot;
            if (bot && bot.isReady && bot.sendWhatsAppMessage && db) {
                const [clientRows] = await db.query(
                    'SELECT cliente_whatsapp, cliente_nome FROM rastreios_status WHERE codigo = ? AND cliente_whatsapp IS NOT NULL LIMIT 1',
                    [cleanCodigo]
                );
                if (clientRows.length && clientRows[0].cliente_whatsapp) {
                    const phone = String(clientRows[0].cliente_whatsapp).replace(/\D/g, '');
                    if (phone.length >= 10) {
                        await bot.sendWhatsAppMessage(phone,
                            `✅ *Pagamento Confirmado!*\n\n` +
                            `Olá${clientRows[0].cliente_nome ? `, *${clientRows[0].cliente_nome}*` : ''}!\n\n` +
                            `Seu pagamento foi aprovado e o objeto *${cleanCodigo}* foi liberado.\n\n` +
                            `🚀 *Status:* Objeto saiu para entrega\n` +
                            `Você receberá a entrega em breve.\n\n` +
                            `_Loggi — Rastreamento Inteligente_`
                        );
                    }
                }
            }
        } catch (e) { /* silencioso */ }
    }
}

module.exports = router;
