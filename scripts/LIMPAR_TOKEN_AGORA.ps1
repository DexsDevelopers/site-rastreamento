# Script URGENTE para limpar token completamente
# Remove TODOS os caracteres invisíveis e espaços

$ErrorActionPreference = "Continue"
$host.UI.RawUI.WindowTitle = "LIMPADOR DE TOKEN - WhatsApp Bot"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  LIMPEZA AGRESSIVA DE TOKEN" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

trap {
    Write-Host ""
    Write-Host "ERRO: $_" -ForegroundColor Red
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

Write-Host "Diretorio: $baseDir" -ForegroundColor Gray
Write-Host ""

# === PASSO 1: Ler e limpar token do config.json ===
Write-Host "1. Lendo config.json..." -ForegroundColor Cyan
try {
    $configContent = Get-Content $configPath -Raw -Encoding UTF8
    $config = $configContent | ConvertFrom-Json
    $tokenRaw = $config.WHATSAPP_API_TOKEN
    
    Write-Host "   Token original: [$tokenRaw]" -ForegroundColor Gray
    Write-Host "   Comprimento original: $($tokenRaw.Length) caracteres" -ForegroundColor Gray
    
    # Converter para bytes e mostrar
    $bytesOriginal = [System.Text.Encoding]::UTF8.GetBytes($tokenRaw)
    $hexBytes = ($bytesOriginal | ForEach-Object { '{0:X2}' -f $_ }) -join ' '
    Write-Host "   Bytes (hex): $hexBytes" -ForegroundColor DarkGray
    
    # Limpeza AGRESSIVA
    # 1. Trim normal
    $token = $tokenRaw.Trim()
    
    # 2. Remover TODOS os espaços (normais e não separáveis)
    $token = $token -replace '\s+', ''
    
    # 3. Remover caracteres não imprimíveis (exceto letras e números)
    $tokenClean = ''
    foreach ($char in $token.ToCharArray()) {
        if ([char]::IsLetterOrDigit($char)) {
            $tokenClean += $char
        }
    }
    
    $token = $tokenClean
    
    Write-Host "   Token limpo: [$token]" -ForegroundColor Green
    Write-Host "   Comprimento limpo: $($token.Length) caracteres" -ForegroundColor $(if ($token.Length -eq 11) { "Green" } else { "Red" })
    
    if ($token.Length -ne 11) {
        Write-Host ""
        Write-Host "   ERRO: Token limpo ainda tem $($token.Length) caracteres!" -ForegroundColor Red
        Write-Host "   Token esperado: lucastav8012 (11 caracteres)" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "   Vou forçar o valor correto..." -ForegroundColor Yellow
        
        # Forçar valor correto
        $token = "lucastav8012"
        Write-Host "   Token forçado: [$token]" -ForegroundColor Yellow
        Write-Host "   Comprimento: $($token.Length) caracteres" -ForegroundColor Green
    }
    
} catch {
    Write-Host "   ERRO ao ler config.json: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "Pressione qualquer tecla para sair..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

# === PASSO 2: Validar token ===
Write-Host ""
Write-Host "2. Validando token..." -ForegroundColor Cyan

if ($token -ne "lucastav8012") {
    Write-Host "   AVISO: Token não corresponde ao esperado!" -ForegroundColor Yellow
    Write-Host "   Esperado: lucastav8012" -ForegroundColor White
    Write-Host "   Obtido: $token" -ForegroundColor White
    
    $confirm = Read-Host "   Forçar token correto? (S/N)"
    if ($confirm -eq "S" -or $confirm -eq "s") {
        $token = "lucastav8012"
        Write-Host "   Token forçado para: $token" -ForegroundColor Green
    }
} else {
    Write-Host "   Token validado: $token" -ForegroundColor Green
}

# === PASSO 3: Ler .env existente ===
Write-Host ""
Write-Host "3. Preparando arquivo .env..." -ForegroundColor Cyan

$envVars = @{}
if (Test-Path $envPath) {
    Write-Host "   Arquivo .env encontrado, lendo..." -ForegroundColor Gray
    $envLinesRaw = Get-Content $envPath -Raw
    
    foreach ($line in ($envLinesRaw -split "`r?`n")) {
        $line = $line.Trim()
        if ($line -and -not $line.StartsWith('#')) {
            if ($line -match '^([^=]+)=(.*)$') {
                $key = $matches[1].Trim()
                $value = $matches[2].Trim()
                
                # Remover aspas
                if ($value.StartsWith('"') -and $value.EndsWith('"')) {
                    $value = $value.Substring(1, $value.Length - 2).Trim()
                }
                if ($value.StartsWith("'") -and $value.EndsWith("'")) {
                    $value = $value.Substring(1, $value.Length - 2).Trim()
                }
                
                # Limpar valor também
                $value = $value -replace '\s+', ''
                
                $envVars[$key] = $value
            }
        }
    }
} else {
    Write-Host "   Criando novo .env..." -ForegroundColor Yellow
}

# Garantir valores padrão
if (-not $envVars.ContainsKey('API_PORT')) {
    $envVars['API_PORT'] = '3000'
}

# Atualizar token (já limpo)
$envVars['API_TOKEN'] = $token

# === PASSO 4: Escrever .env limpo ===
Write-Host ""
Write-Host "4. Escrevendo .env limpo..." -ForegroundColor Cyan

$envLines = @()
$envLines += "# Arquivo .env - Token limpo e validado"
$envLines += "# Gerado em: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
$envLines += ""
$envLines += "API_PORT=$($envVars['API_PORT'])"
$envLines += "API_TOKEN=$token"
$envLines += ""

# Adicionar outras variáveis se existirem
$otherVars = @('AUTO_REPLY', 'AUTO_REPLY_WINDOW_MS', 'ADMIN_API_URL', 'ADMIN_NUMBERS')
foreach ($varName in $otherVars) {
    if ($envVars.ContainsKey($varName) -and $envVars[$varName]) {
        $envLines += "$varName=$($envVars[$varName])"
    }
}

try {
    # Criar arquivo com encoding UTF8 sem BOM
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllLines($envPath, $envLines, $utf8NoBom)
    
    Write-Host "   Arquivo .env criado com sucesso!" -ForegroundColor Green
    Write-Host "   Caminho: $envPath" -ForegroundColor Gray
    
    # Verificar o que foi escrito
    Write-Host ""
    Write-Host "5. Verificando token escrito..." -ForegroundColor Cyan
    $envContentCheck = Get-Content $envPath -Raw
    $tokenLine = ($envContentCheck -split "`r?`n" | Where-Object { $_ -match '^API_TOKEN=' })
    
    if ($tokenLine) {
        if ($tokenLine -match 'API_TOKEN=(.+)') {
            $tokenWritten = $matches[1].Trim()
            $bytesWritten = [System.Text.Encoding]::UTF8.GetBytes($tokenWritten)
            Write-Host "   Token no arquivo: [$tokenWritten]" -ForegroundColor Gray
            Write-Host "   Comprimento: $($tokenWritten.Length) caracteres" -ForegroundColor $(if ($tokenWritten.Length -eq 11) { "Green" } else { "Red" })
            $hexBytesWritten = ($bytesWritten | ForEach-Object { '{0:X2}' -f $_ }) -join ' '
            Write-Host "   Bytes (hex): $hexBytesWritten" -ForegroundColor DarkGray
            
            if ($tokenWritten.Length -eq 11) {
                Write-Host "   ✅ Token está correto!" -ForegroundColor Green
            } else {
                Write-Host "   ❌ Token ainda tem problema!" -ForegroundColor Red
            }
        }
    }
    
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  LIMPEZA CONCLUÍDA!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Token configurado: $token" -ForegroundColor Yellow
    Write-Host "Comprimento: $($token.Length) caracteres" -ForegroundColor $(if ($token.Length -eq 11) { "Green" } else { "Red" })
    Write-Host ""
    Write-Host "⚠️  AÇÃO NECESSÁRIA:" -ForegroundColor Yellow
    Write-Host "  1. Pare o bot Node.js (Ctrl+C no terminal)" -ForegroundColor White
    Write-Host "  2. Reinicie o bot: cd whatsapp-bot && npm run dev" -ForegroundColor White
    Write-Host "  3. Verifique nos logs: deve aparecer (11 chars)" -ForegroundColor White
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
