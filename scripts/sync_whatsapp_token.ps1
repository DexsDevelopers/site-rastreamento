# Script para sincronizar o token do WhatsApp do config.json para o .env do bot
# Autor: Sistema de Rastreamento
# Data: 2025-01-27

Write-Host "🔄 Sincronizando token do WhatsApp..." -ForegroundColor Cyan

# Ajustar caminho base (sobe um nível, pois o script está em scripts/)
$scriptDir = Split-Path -Parent $PSScriptRoot
$configPath = Join-Path $scriptDir "config.json"
$envPath = Join-Path $scriptDir "whatsapp-bot\.env"

# Verificar se config.json existe
if (-not (Test-Path $configPath)) {
    Write-Host "❌ Arquivo config.json não encontrado em: $configPath" -ForegroundColor Red
    exit 1
}

# Ler config.json
try {
    $config = Get-Content $configPath -Raw | ConvertFrom-Json
    $token = $config.WHATSAPP_API_TOKEN
    
    if ([string]::IsNullOrWhiteSpace($token)) {
        Write-Host "❌ Token não encontrado em config.json (WHATSAPP_API_TOKEN)" -ForegroundColor Red
        exit 1
    }
    
    Write-Host "✅ Token encontrado no config.json: $token" -ForegroundColor Green
} catch {
    Write-Host "❌ Erro ao ler config.json: $_" -ForegroundColor Red
    exit 1
}

# Verificar se diretório do bot existe
$botDir = Split-Path $envPath -Parent
if (-not (Test-Path $botDir)) {
    Write-Host "❌ Diretório do bot não encontrado: $botDir" -ForegroundColor Red
    exit 1
}

# Ler .env existente ou criar novo
$envContent = @{}
if (Test-Path $envPath) {
    Write-Host "📄 Arquivo .env encontrado, lendo conteúdo..." -ForegroundColor Yellow
    Get-Content $envPath -Raw | ForEach-Object {
        # Dividir por linhas e processar cada uma
        $_.Split("`n") | ForEach-Object {
            $line = $_.Trim()
            if ($line -and -not $line.StartsWith('#')) {
                if ($line -match '^\s*([^#=]+)\s*=\s*(.+?)\s*$') {
                    $key = $matches[1].Trim()
                    $value = $matches[2].Trim(" `t`"'")
                    $envContent[$key] = $value
                }
            }
        }
    }
    Write-Host "   Variáveis encontradas: $($envContent.Keys -join ', ')" -ForegroundColor Gray
} else {
    Write-Host "📝 Criando novo arquivo .env..." -ForegroundColor Yellow
}

# Atualizar token (remover espaços e aspas)
$token = $token.Trim(" `t`"'")
$envContent['API_TOKEN'] = $token
$envContent['API_PORT'] = if ($envContent.ContainsKey('API_PORT')) { $envContent['API_PORT'].Trim() } else { '3000' }

Write-Host "📝 Configurações a serem salvas:" -ForegroundColor Cyan
Write-Host "   API_PORT = $($envContent['API_PORT'])" -ForegroundColor White
Write-Host "   API_TOKEN = $token" -ForegroundColor White

# Escrever .env com formato limpo (sem espaços extras, sem BOM)
$envLines = @()
$envLines += "# Arquivo .env gerado automaticamente"
$envLines += "# Token sincronizado do config.json em $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
$envLines += ""
$envLines += "API_PORT=$($envContent['API_PORT'])"
$envLines += "API_TOKEN=$token"
$envLines += ""

# Adicionar outras variáveis se existirem
$otherVars = @('AUTO_REPLY', 'AUTO_REPLY_WINDOW_MS', 'ADMIN_API_URL', 'ADMIN_NUMBERS')
foreach ($var in $otherVars) {
    if ($envContent.ContainsKey($var) -and $envContent[$var]) {
        $envLines += "$var=$($envContent[$var])"
    }
}

try {
    $envLines | Set-Content $envPath -Encoding UTF8
    Write-Host "✅ Token sincronizado com sucesso!" -ForegroundColor Green
    Write-Host "📁 Arquivo atualizado: $envPath" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Token configurado: $token" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "⚠️  IMPORTANTE: Reinicie o bot Node.js para aplicar as mudanças!" -ForegroundColor Yellow
    Write-Host "   Execute: cd whatsapp-bot && npm run dev" -ForegroundColor White
} catch {
    Write-Host "❌ Erro ao escrever .env: $_" -ForegroundColor Red
    exit 1
}
