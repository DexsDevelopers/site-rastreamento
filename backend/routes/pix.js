const express = require('express');
const router = express.Router();
const { getDB } = require('../db');

const PIXGO_API_KEY = 'pk_9073c62f8b397edc81e80d8675f6a6459916140064fbb2f3d653ba5b09dc9e3d';
const PIXGO_API_URL = 'https://pixgo.org/api/v1';

// ===== PIX API =====

router.post('/create', async (req, res) => {
    try {
        const { amount, description } = req.body;
        const response = await fetch(`${PIXGO_API_URL}/payment/create`, {
            method: 'POST',
            headers: {
                'x-api-key': PIXGO_API_KEY,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ amount: parseFloat(amount) || 29.90, description: description || 'Acelerar Entrega' })
        });
        const data = await response.json();

        if (data.success) {
            res.json(data);
        } else {
            console.error('[PIXGO ERROR]', data);
            res.status(400).json({ success: false, error: data.message || 'Erro na API PixGo' });
        }
    } catch (error) {
        console.error('[PIXGO CREATE ERROR]', error.message);
        res.status(500).json({ success: false, error: 'Erro no servidor' });
    }
});

router.get('/status/:id', async (req, res) => {
    const db = getDB();
    try {
        const { id } = req.params;
        const codigo = req.query.codigo?.toUpperCase().trim();

        const response = await fetch(`${PIXGO_API_URL}/payment/${id}/status`, {
            method: 'GET',
            headers: { 'x-api-key': PIXGO_API_KEY }
        });
        const data = await response.json();

        if (data.success && (data.status === 'PAID' || data.status === 'CONFIRMED' || data.data?.status === 'PAID') && codigo && db) {
            const cleanCodigo = codigo.toUpperCase().trim();

            const [existing] = await db.query(
                "SELECT id FROM rastreios_status WHERE codigo = ? AND titulo LIKE '%Pagamento Confirmado%'",
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

                console.log(`[AUTO-UPDATE] Pagamento confirmado e status atualizado para: ${cleanCodigo}`);
            }
        }

        res.json(data);
    } catch (error) {
        console.error('[PIXGO STATUS ERROR]', error.message);
        res.status(500).json({ success: false, error: 'Erro no servidor' });
    }
});

module.exports = router;
