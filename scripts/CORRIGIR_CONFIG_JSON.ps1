# Script para corrigir o config.json removendo caracteres invisíveis do token

$ErrorActionPreference = "Continue"
$host.UI.RawUI.WindowTitle = "CORRIGIR CONFIG.JSON - Remover Caracteres Invisiveis"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  CORREÇÃO DO CONFIG.JSON" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan
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

Write-Host "1. Lendo config.json..." -ForegroundColor Cyan

# Ler como texto raw primeiro
$jsonRaw = Get-Content $configPath -Raw -Encoding UTF8
$config = $jsonRaw | ConvertFrom-Json
$tokenOriginal = $config.WHATSAPP_API_TOKEN

Write-Host "   Token original: [$tokenOriginal]" -ForegroundColor Gray
Write-Host "   Comprimento: $($tokenOriginal.Length) caracteres" -ForegroundColor Gray

# Analisar bytes
$bytes = [System.Text.Encoding]::UTF8.GetBytes($tokenOriginal)
Write-Host ""
Write-Host "2. Analisando bytes individuais..." -ForegroundColor Cyan
for ($i = 0; $i -lt $bytes.Length; $i++) {
    $byte = $bytes[$i]
    $char = [char]$byte
    $hex = '{0:X2}' -f $byte
    
    if ($byte -lt 32 -or $byte -eq 127 -or ($byte -gt 127 -and $byte -lt 160)) {
        Write-Host "   Byte $i : 0x$hex = [CARACTERE INVISÍVEL/CONTROLE]" -ForegroundColor Red
    } elseif ([char]::IsWhiteSpace($char)) {
        Write-Host "   Byte $i : 0x$hex = [ESPAÇO]" -ForegroundColor Yellow
    } else {
        Write-Host "   Byte $i : 0x$hex = '$char'" -ForegroundColor Green
    }
}

# Criar token limpo - apenas letras e números
Write-Host ""
Write-Host "3. Criando token limpo..." -ForegroundColor Cyan
$tokenLimpo = ''
foreach ($char in $tokenOriginal.ToCharArray()) {
    if ([char]::IsLetterOrDigit($char)) {
        $tokenLimpo += $char
    }
}

# Se ainda tiver problema, forçar valor correto
if ($tokenLimpo.Length -ne 11 -or $tokenLimpo -ne "lucastav8012") {
    Write-Host "   Token limpo: [$tokenLimpo] (comprimento: $($tokenLimpo.Length))" -ForegroundColor Yellow
    Write-Host "   Forçando valor correto: lucastav8012" -ForegroundColor Yellow
    $tokenLimpo = "lucastav8012"
} else {
    Write-Host "   Token limpo: [$tokenLimpo]" -ForegroundColor Green
}

Write-Host "   Comprimento final: $($tokenLimpo.Length) caracteres" -ForegroundColor Green

# Atualizar config.json
Write-Host ""
Write-Host "4. Atualizando config.json..." -ForegroundColor Cyan

# Ler o JSON completo
$configData = Get-Content $configPath -Raw -Encoding UTF8 | ConvertFrom-Json

# Atualizar o token
$configData.WHATSAPP_API_TOKEN = $tokenLimpo

# Reescrever o JSON
$jsonOutput = $configData | ConvertTo-Json -Depth 10

# Salvar com UTF8 sem BOM
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($configPath, $jsonOutput, $utf8NoBom)

Write-Host "   Arquivo atualizado!" -ForegroundColor Green

# Verificar o que foi escrito
Write-Host ""
Write-Host "5. Verificando token no arquivo..." -ForegroundColor Cyan
$configVerificado = Get-Content $configPath -Raw -Encoding UTF8 | ConvertFrom-Json
$tokenVerificado = $configVerificado.WHATSAPP_API_TOKEN
$bytesVerificados = [System.Text.Encoding]::UTF8.GetBytes($tokenVerificado)

Write-Host "   Token verificado: [$tokenVerificado]" -ForegroundColor Gray
Write-Host "   Comprimento: $($tokenVerificado.Length) caracteres" -ForegroundColor $(if ($tokenVerificado.Length -eq 11) { "Green" } else { "Red" })
Write-Host "   Bytes (hex): $(($bytesVerificados | ForEach-Object { '{0:X2}' -f $_ }) -join ' ')" -ForegroundColor DarkGray

if ($tokenVerificado.Length -eq 11 -and $tokenVerificado -eq "lucastav8012") {
    Write-Host ""
    Write-Host "   ✅ Token corrigido com sucesso!" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "   ❌ Ainda há problema com o token!" -ForegroundColor Red
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  PRÓXIMOS PASSOS:" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Execute o script de sincronização:" -ForegroundColor White
Write-Host "   .\scripts\sync_whatsapp_token.ps1" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Pare e reinicie o bot Node.js:" -ForegroundColor White
Write-Host "   Stop-Process -Name node -Force" -ForegroundColor Gray
Write-Host "   cd whatsapp-bot" -ForegroundColor Gray
Write-Host "   npm run dev" -ForegroundColor Gray
Write-Host ""
Write-Host "3. Verifique nos logs: deve aparecer (11 chars)" -ForegroundColor White
Write-Host ""
Write-Host "Pressione qualquer tecla para fechar..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
