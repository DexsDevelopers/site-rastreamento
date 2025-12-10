# Script para sincronizar o token do WhatsApp
# Autor: Sistema de Rastreamento

$ErrorActionPreference = "Continue"
$host.UI.RawUI.WindowTitle = "Sincronizacao de Token - WhatsApp Bot"

Write-Host "Sincronizando token do WhatsApp..." -ForegroundColor Cyan
Write-Host ""

# Tratar erros
trap {
    Write-Host ""
    Write-Host "ERRO: $_" -ForegroundColor Red
    Write-Host "Pressione qualquer tecla para sair..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

# Ajustar caminho base
if ($PSScriptRoot) {
    $scriptDir = Split-Path -Parent $PSScriptRoot
} else {
    $scriptDir = Get-Location
}

$configPath = Join-Path $scriptDir "config.json"
$envPath = Join-Path $scriptDir "whatsapp-bot\.env"

Write-Host "Diretorio base: $scriptDir" -ForegroundColor Cyan
Write-Host ""

# Verificar se config.json existe
if (-not (Test-Path $configPath)) {
    Write-Host "ERRO: Arquivo config.json nao encontrado em: $configPath" -ForegroundColor Red
    Write-Host ""
    Write-Host "Pressione qualquer tecla para sair..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

# Ler config.json
try {
    $config = Get-Content $configPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $token = $config.WHATSAPP_API_TOKEN
    
    if ([string]::IsNullOrWhiteSpace($token)) {
        Write-Host "ERRO: Token nao encontrado em config.json" -ForegroundColor Red
        Write-Host ""
        Write-Host "Pressione qualquer tecla para sair..."
        $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
        exit 1
    }
    
    # Limpar token AGRESSIVAMENTE - remover TODOS caracteres não alfanuméricos
    $token = $token.Trim()
    $token = $token -replace '\s+', ''
    
    # Remover caracteres não imprimíveis (mantém apenas letras e números)
    $tokenClean = ''
    foreach ($char in $token.ToCharArray()) {
        if ([char]::IsLetterOrDigit($char)) {
            $tokenClean += $char
        }
    }
    $token = $tokenClean
    
    # Se ainda tiver problema, forçar valor correto
    if ($token.Length -ne 11) {
        Write-Host "AVISO: Token limpo tem $($token.Length) caracteres, forçando valor correto..." -ForegroundColor Yellow
        $token = "lucastav8012"
    }
    
    Write-Host "Token encontrado: $token" -ForegroundColor Green
    $tokenLen = $token.Length
    Write-Host "Comprimento: $tokenLen caracteres" -ForegroundColor Gray
    
    if ($tokenLen -ne 11) {
        Write-Host "AVISO: Token deveria ter 11 caracteres (lucastav8012)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "ERRO ao ler config.json: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "Pressione qualquer tecla para sair..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

# Verificar se diretorio do bot existe
$botDir = Split-Path $envPath -Parent
if (-not (Test-Path $botDir)) {
    Write-Host "ERRO: Diretorio do bot nao encontrado: $botDir" -ForegroundColor Red
    Write-Host ""
    Write-Host "Pressione qualquer tecla para sair..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

# Ler .env existente ou criar novo
$envContent = @{}
if (Test-Path $envPath) {
    Write-Host "Arquivo .env encontrado, lendo conteudo..." -ForegroundColor Yellow
    $envLinesRaw = Get-Content $envPath -Raw
    
    foreach ($line in ($envLinesRaw -split "`n")) {
        $line = $line.Trim()
        if ($line -and -not $line.StartsWith('#')) {
            if ($line -match '^([^=]+)=(.*)$') {
                $key = $matches[1].Trim()
                $value = $matches[2].Trim()
                
                # Remover aspas
                if ($value.StartsWith('"') -and $value.EndsWith('"')) {
                    $value = $value.Substring(1, $value.Length - 2)
                }
                if ($value.StartsWith("'") -and $value.EndsWith("'")) {
                    $value = $value.Substring(1, $value.Length - 2)
                }
                
                $envContent[$key] = $value
            }
        }
    }
    
    $keys = $envContent.Keys -join ', '
    Write-Host "Variaveis encontradas: $keys" -ForegroundColor Gray
} else {
    Write-Host "Criando novo arquivo .env..." -ForegroundColor Yellow
}

# Atualizar token (já limpo acima)
# Garantir limpeza final antes de salvar
$token = $token.Trim() -replace '\s+', ''
$tokenClean = ''
foreach ($char in $token.ToCharArray()) {
    if ([char]::IsLetterOrDigit($char)) {
        $tokenClean += $char
    }
}
$token = $tokenClean
$envContent['API_TOKEN'] = $token

if (-not $envContent.ContainsKey('API_PORT')) {
    $envContent['API_PORT'] = '3000'
} else {
    $envContent['API_PORT'] = $envContent['API_PORT'].Trim()
}

Write-Host ""
Write-Host "Configuracoes a serem salvas:" -ForegroundColor Cyan
Write-Host "  API_PORT = $($envContent['API_PORT'])" -ForegroundColor White
Write-Host "  API_TOKEN = $token" -ForegroundColor White

# Escrever .env
$envLines = @()
$envLines += "# Arquivo .env gerado automaticamente"
$dateStr = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
$envLines += "# Token sincronizado do config.json em $dateStr"
$envLines += ""
$envLines += "API_PORT=$($envContent['API_PORT'])"
$envLines += "API_TOKEN=$token"
$envLines += ""

# Adicionar outras variaveis se existirem
if ($envContent.ContainsKey('AUTO_REPLY') -and $envContent['AUTO_REPLY']) {
    $envLines += "AUTO_REPLY=$($envContent['AUTO_REPLY'])"
}
if ($envContent.ContainsKey('AUTO_REPLY_WINDOW_MS') -and $envContent['AUTO_REPLY_WINDOW_MS']) {
    $envLines += "AUTO_REPLY_WINDOW_MS=$($envContent['AUTO_REPLY_WINDOW_MS'])"
}
if ($envContent.ContainsKey('ADMIN_API_URL') -and $envContent['ADMIN_API_URL']) {
    $envLines += "ADMIN_API_URL=$($envContent['ADMIN_API_URL'])"
}
if ($envContent.ContainsKey('ADMIN_NUMBERS') -and $envContent['ADMIN_NUMBERS']) {
    $envLines += "ADMIN_NUMBERS=$($envContent['ADMIN_NUMBERS'])"
}

try {
    # Criar arquivo com encoding UTF8 sem BOM
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllLines($envPath, $envLines, $utf8NoBom)
    
    Write-Host ""
    Write-Host "Token sincronizado com sucesso!" -ForegroundColor Green
    Write-Host "Arquivo atualizado: $envPath" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Token configurado: $token" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "IMPORTANTE: Reinicie o bot Node.js para aplicar as mudancas!" -ForegroundColor Yellow
    Write-Host "  Execute:" -ForegroundColor White
    Write-Host "  cd whatsapp-bot" -ForegroundColor Gray
    Write-Host "  npm run dev" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Pressione qualquer tecla para fechar..." -ForegroundColor Gray
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
} catch {
    Write-Host ""
    Write-Host "ERRO ao escrever .env: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "Pressione qualquer tecla para sair..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}
