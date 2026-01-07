@echo off
echo ========================================
echo  Iniciando Bot WhatsApp com 4GB RAM
echo ========================================
echo.

REM Verificar se Node.js está instalado
where node >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERRO: Node.js nao encontrado!
    echo Instale Node.js de https://nodejs.org
    pause
    exit /b 1
)

REM Iniciar bot com 4GB de memoria e GC exposto
node --max-old-space-size=4096 --expose-gc index.js

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ========================================
    echo  Bot encerrado com erro!
    echo ========================================
    pause
)

