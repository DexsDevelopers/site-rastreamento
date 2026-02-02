#!/bin/bash

echo "🔴 PARANDO O BOT..."
# Tenta parar o processo node index.js da pasta whatsapp-bot
pkill -f "node index.js"
sleep 3

echo "🧹 LIMPANDO SESSÃO WHATSAPP..."
cd /home/johan/Desktop/Link\ to\ Johan\ 7K/Documents/GitHub/site-rastreamento/whatsapp-bot

# Remove a pasta auth se existir
if [ -d "auth" ]; then
    rm -rf auth
    echo "✅ Pasta 'auth' removida."
else
    echo "⚠️ Pasta 'auth' não encontrada (já estava limpa?)"
fi

echo ""
echo "🔄 INICIANDO O BOT NOVAMENTE..."
echo "👉 AGUARDE O QR CODE APARECER NA TELA E ESCANEIE COM SEU WHATSAPP!"
echo ""

# Inicia o bot e mostra a saída no terminal para o usuário ver o QR Code
npm start
