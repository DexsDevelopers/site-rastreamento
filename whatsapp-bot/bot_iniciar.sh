cd "$(dirname "$0")"
echo "Iniciando o bot de WhatsApp (Rastreamento)..."
pm2 start index.js --name bot-rastreamento --node-args="--max-old-space-size=4096 --expose-gc"
pm2 save


echo "Bot Rastreamento iniciado em segundo plano."

read -p "Pressione Enter para ver o status..."
pm2 status
