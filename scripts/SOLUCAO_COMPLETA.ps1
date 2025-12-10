# SOLUÇÃO COMPLETA - Corrigir token em todas as etapas

$ErrorActionPreference = "Continue"
$host.UI.RawUI.WindowTitle = "SOLUÇÃO COMPLETA - Token WhatsApp"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  SOLUÇÃO COMPLETA - TOKEN WHATSAPP" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

trap {
    Write-Host ""
    Write-Host "ERRO: $_" -ForegroundColor Red
    Write-Host "Linha: $($_.InvocationInfo.ScriptLineNumber)" -ForegroundColor Red
    Write-Host "Pressione qualquer tecla para sair..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

# Caminhos
if ($PSScriptRoot) {
    $baseDir = Split-Path -Parent $PSScriptRoot
} else {
    $baseDir = Get-Location
}

$configPath = Join-Path $baseDir "config.json"
$envPath = Join-Path $baseDir "whatsapp-bot\.env"

Write-Host "PASSO 1: Corrigindo config.json..." -ForegroundColor Cyan
Write-Host ""

# Ler e corrigir config.json
$configContent = Get-Content $configPath -Raw -Encoding UTF8
$config = $configContent | ConvertFrom-Json

$tokenOriginal = $config.WHATSAPP_API_TOKEN
Write-Host "Token original: [$tokenOriginal]" -ForegroundColor Gray
Write-Host "Comprimento: $($tokenOriginal.Length) caracteres" -ForegroundColor Gray

# FORÇAR token correto
$config.WHATSAPP_API_TOKEN = "lucastav8012"
$newJson = $config | ConvertTo-Json -Depth 10

# Salvar com UTF8 sem BOM
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($configPath, $newJson, $utf8NoBom)

# Verificar
$configVerificado = Get-Content $configPath -Raw -Encoding UTF8 | ConvertFrom-Json
$tokenCorrigido = $configVerificado.WHATSAPP_API_TOKEN
$bytes = [System.Text.Encoding]::UTF8.GetBytes($tokenCorrigido)

Write-Host "Token corrigido: [$tokenCorrigido]" -ForegroundColor Green
Write-Host "Comprimento: $($tokenCorrigido.Length) caracteres" -ForegroundColor $(if ($tokenCorrigido.Length -eq 11) { "Green" } else { "Red" })
Write-Host "Bytes (hex): $(($bytes | ForEach-Object { '{0:X2}' -f $_ }) -join ' ')" -ForegroundColor DarkGray

if ($tokenCorrigido.Length -ne 11) {
    Write-Host ""
    Write-Host "ERRO: Token ainda tem problema!" -ForegroundColor Red
    Write-Host "Pressione qualquer tecla para sair..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

Write-Host ""
Write-Host "PASSO 2: Atualizando .env do bot..." -ForegroundColor Cyan
Write-Host ""

$token = "lucastav8012"

# Ler .env existente
$envVars = @{}
if (Test-Path $envPath) {
    $envLinesRaw = Get-Content $envPath -Raw
    foreach ($line in ($envLinesRaw -split "`r?`n")) {
        $line = $line.Trim()
        if ($line -and -not $line.StartsWith('#')) {
            if ($line -match '^([^=]+)=(.*)$') {
                $key = $matches[1].Trim()
                $value = $matches[2].Trim()
                
                if ($value.StartsWith('"') -and $value.EndsWith('"')) {
                    $value = $value.Substring(1, $value.Length - 2).Trim()
                }
                if ($value.StartsWith("'") -and $value.EndsWith("'")) {
                    $value = $value.Substring(1, $value.Length - 2).Trim()
                }
                
                $envVars[$key] = $value
            }
        }
    }
}

# Garantir valores
if (-not $envVars.ContainsKey('API_PORT')) {
    $envVars['API_PORT'] = '3000'
}
$envVars['API_TOKEN'] = $token

# Escrever .env
$envLines = @()
$envLines += "# Arquivo .env - Token corrigido"
$envLines += "# Gerado em: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
$envLines += ""
$envLines += "API_PORT=$($envVars['API_PORT'])"
$envLines += "API_TOKEN=$token"
$envLines += ""

$otherVars = @('AUTO_REPLY', 'AUTO_REPLY_WINDOW_MS', 'ADMIN_API_URL', 'ADMIN_NUMBERS')
foreach ($varName in $otherVars) {
    if ($envVars.ContainsKey($varName) -and $envVars[$varName]) {
        $envLines += "$varName=$($envVars[$varName])"
    }
}

[System.IO.File]::WriteAllLines($envPath, $envLines, $utf8NoBom)

Write-Host "Arquivo .env atualizado!" -ForegroundColor Green

# Verificar .env
$envContentCheck = Get-Content $envPath -Raw
$tokenLine = ($envContentCheck -split "`r?`n" | Where-Object { $_ -match '^API_TOKEN=' })
if ($tokenLine -match 'API_TOKEN=(.+)') {
    $tokenEnv = $matches[1].Trim()
    $bytesEnv = [System.Text.Encoding]::UTF8.GetBytes($tokenEnv)
    Write-Host "Token no .env: [$tokenEnv]" -ForegroundColor Gray
    Write-Host "Comprimento: $($tokenEnv.Length) caracteres" -ForegroundColor $(if ($tokenEnv.Length -eq 11) { "Green" } else { "Red" })
    Write-Host "Bytes (hex): $(($bytesEnv | ForEach-Object { '{0:X2}' -f $_ }) -join ' ')" -ForegroundColor DarkGray
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  ✅ CORREÇÃO CONCLUÍDA!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "PRÓXIMOS PASSOS OBRIGATÓRIOS:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Pare o bot Node.js:" -ForegroundColor White
Write-Host "   Stop-Process -Name node -Force" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Verifique que parou:" -ForegroundColor White
Write-Host "   Get-Process node" -ForegroundColor Gray
Write-Host "   (Não deve retornar nada)" -ForegroundColor DarkGray
Write-Host ""
Write-Host "3. Reinicie o bot:" -ForegroundColor White
Write-Host "   cd whatsapp-bot" -ForegroundColor Gray
Write-Host "   npm run dev" -ForegroundColor Gray
Write-Host ""
Write-Host "4. Verifique nos logs:" -ForegroundColor White
Write-Host "   Deve aparecer: (11 chars)" -ForegroundColor Green
Write-Host ""
Write-Host "5. Teste o envio novamente" -ForegroundColor White
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Pressione qualquer tecla para fechar..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
