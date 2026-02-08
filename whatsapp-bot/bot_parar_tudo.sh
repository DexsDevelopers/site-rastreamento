#!/bin/bash
echo "Parando TODAS as sessões do bot no PM2..."
pm2 stop all
pm2 delete all
echo "Todas as sessões foram paradas e removidas da lista."
