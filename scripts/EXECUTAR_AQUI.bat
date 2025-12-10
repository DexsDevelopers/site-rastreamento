@echo off
REM Arquivo batch para executar o script PowerShell corretamente
REM Duplo clique neste arquivo para executar

cd /d "%~dp0\.."
powershell -ExecutionPolicy Bypass -NoExit -File "%~dp0corrigir_token_agora.ps1"
pause
