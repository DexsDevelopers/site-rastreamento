@echo off
REM Arquivo batch para executar o script de sincronização
REM Duplo clique neste arquivo para executar

cd /d "%~dp0\.."
powershell -ExecutionPolicy Bypass -NoExit -File "%~dp0sync_whatsapp_token.ps1"
pause
