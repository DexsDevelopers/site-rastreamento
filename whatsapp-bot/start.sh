#!/bin/bash

echo "========================================"
echo " Iniciando Bot WhatsApp com 4GB RAM"
echo "========================================"
echo ""

# Verificar se Node.js está instalado
if ! command -v node &> /dev/null; then
    echo "ERRO: Node.js não encontrado!"
    echo "Instale Node.js de https://nodejs.org"
    exit 1
fi

# Iniciar bot com 4GB de memória e GC exposto
node --max-old-space-size=4096 --expose-gc index.js

if [ $? -ne 0 ]; then
    echo ""
    echo "========================================"
    echo " Bot encerrado com erro!"
    echo "========================================"
fi

