const { connectDB, getDB } = require('./db');
const axios = require('axios');

async function debug() {
    console.log('--- DIAGNÓSTICO DE RASTREIO ---');
    try {
        await connectDB();
        const db = getDB();

        const codigo = 'GH56YJ14140BR';
        console.log(`Testando código: ${codigo}`);

        const [rows] = await db.query(
            'SELECT * FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data ASC',
            [codigo]
        );

        if (rows.length === 0) {
            console.log('Código não encontrado no banco.');
            process.exit(0);
        }

        console.log(`Encontrado ${rows.length} etapas.`);
        let packageData = rows[0];

        const firstStageDate = new Date(rows[0].data);
        const now = new Date();
        const diffDays = Math.floor((now.getTime() - firstStageDate.getTime()) / (1000 * 60 * 60 * 24));

        console.log(`Dias decorridos: ${diffDays}`);

        const automationStages = [
            { day: 1, status: '🚚 Em trânsito' },
            { day: 2, status: '🏢 No centro de distribuição' }
        ];

        for (const stage of automationStages) {
            if (diffDays >= stage.day && !rows.some(r => r.status_atual === stage.status)) {
                console.log(`Simulando inserção de etapa: ${stage.status}`);
            }
        }

        if (packageData.tipo_entrega === 'NORMAL' && diffDays >= 3 && !packageData.taxa_paga) {
            console.log('Entrou na lógica de Taxa (Dia 3+)');
            const taxaStatus = '⚠️ Objeto retido - Aguardando regularização fiscal';
            if (!rows.some(r => r.status_atual === taxaStatus)) {
                console.log('Deveria inserir taxa Status');
            } else {
                console.log('Taxa Status já existe.');
            }
        }

        const [updatedRows] = await db.query('SELECT * FROM rastreios_status WHERE UPPER(TRIM(codigo)) = ? ORDER BY data ASC', [codigo]);
        const currentRows = updatedRows;
        const lastStatus = currentRows[currentRows.length - 1];
        packageData = currentRows[0];

        console.log('Último status:', lastStatus?.status_atual);

        const taxaRow = currentRows.find(r => (r.taxa_valor && parseFloat(r.taxa_valor) > 0) || r.taxa_pix);
        console.log('Linha de taxa encontrada:', taxaRow ? 'SIM' : 'NÃO');

        const responseData = {
            success: true,
            codigo: lastStatus?.codigo,
            taxa_valor: packageData.taxa_paga ? null : (taxaRow?.taxa_valor || null),
            taxa_pix: packageData.taxa_paga ? null : (taxaRow?.taxa_pix || null),
            tipo_entrega: packageData.tipo_entrega,
            taxa_paga: packageData.taxa_paga ? true : false
        };

        console.log('Resultado Simulado:', JSON.stringify(responseData, null, 2));
        console.log('✅ Tudo parece OK no script de debug.');

    } catch (err) {
        console.error('❌ ERRO DETECTADO NO LOGIC:', err);
    } finally {
        process.exit(0);
    }
}

debug();
