# Script para FORÇAR o token correto no config.json

$ErrorActionPreference = "Continue"
$host.UI.RawUI.WindowTitle = "FORÇAR TOKEN CORRETO - config.json"

Write-Host "Forçando token correto no config.json..." -ForegroundColor Cyan
Write-Host ""

trap {
    Write-Host ""
    Write-Host "ERRO: $_" -ForegroundColor Red
    Write-Host "Pressione qualquer tecla para sair..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

# Caminho
if ($PSScriptRoot) {
    $baseDir = Split-Path -Parent $PSScriptRoot
} else {
    $baseDir = Get-Location
}

$configPath = Join-Path $baseDir "config.json"

# Ler JSON
$jsonContent = Get-Content $configPath -Raw -Encoding UTF8
$config = $jsonContent | ConvertFrom-Json

# FORÇAR token correto
$config.WHATSAPP_API_TOKEN = "lucastav8012"

# Converter de volta para JSON
$newJson = $config | ConvertTo-Json -Depth 10

# Salvar com UTF8 sem BOM
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($configPath, $newJson, $utf8NoBom)

# Verificar
$configVerificado = Get-Content $configPath -Raw -Encoding UTF8 | ConvertFrom-Json
$token = $configVerificado.WHATSAPP_API_TOKEN
$bytes = [System.Text.Encoding]::UTF8.GetBytes($token)

Write-Host "Token atualizado: [$token]" -ForegroundColor Green
Write-Host "Comprimento: $($token.Length) caracteres" -ForegroundColor $(if ($token.Length -eq 11) { "Green" } else { "Red" })
Write-Host "Bytes: $(($bytes | ForEach-Object { '{0:X2}' -f $_ }) -join ' ')" -ForegroundColor Gray

if ($token.Length -eq 11) {
    Write-Host ""
    Write-Host "✅ Token corrigido com sucesso!" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "❌ Ainda há problema!" -ForegroundColor Red
}

Write-Host ""
Write-Host "Próximos passos:" -ForegroundColor Yellow
Write-Host "1. Execute: .\scripts\sync_whatsapp_token.ps1" -ForegroundColor White
Write-Host "2. Reinicie o bot Node.js" -ForegroundColor White
Write-Host ""
Write-Host "Pressione qualquer tecla para fechar..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
