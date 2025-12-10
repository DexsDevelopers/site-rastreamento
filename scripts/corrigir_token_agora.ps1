# Script URGENTE para corrigir token e verificar
# Força a sincronização e verifica tudo

# Configurar para não fechar automaticamente
$ErrorActionPreference = "Continue"
$host.UI.RawUI.WindowTitle = "Correção de Token - WhatsApp Bot"

Write-Host "🚨 CORREÇÃO URGENTE DE TOKEN" -ForegroundColor Red
Write-Host ""

# Tratar erros
trap {
    Write-Host ""
    Write-Host "❌ ERRO: $_" -ForegroundColor Red
    Write-Host "Pressione qualquer tecla para sair..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

# Ajustar caminho base (sobe um nível, pois o script está em scripts/)
# Se executado diretamente (duplo clique), usar diretório atual
if ($PSScriptRoot) {
    $scriptDir = Split-Path -Parent $PSScriptRoot
} else {
    # Se não tem PSScriptRoot, assumir que está na raiz do projeto
    $scriptDir = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
    if (-not $scriptDir) {
        $scriptDir = Get-Location
    }
}

$configPath = Join-Path $scriptDir "config.json"
$envPath = Join-Path $scriptDir "whatsapp-bot\.env"

Write-Host "📁 Diretório base: $scriptDir" -ForegroundColor Cyan
Write-Host "📄 Config: $configPath" -ForegroundColor Gray
Write-Host "📄 .env: $envPath" -ForegroundColor Gray
Write-Host ""

# 1. Ler token do config.json
try {
    $config = Get-Content $configPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $token = $config.WHATSAPP_API_TOKEN.Trim()
    
    # Remover TODOS os espaços e caracteres invisíveis
    $token = $token -replace '\s+', ''  # Remove todos os espaços
    $token = $token.Trim()  # Trim novamente
    
    Write-Host "✅ Token do config.json: '$token'" -ForegroundColor Green
    Write-Host "   Comprimento: $($token.Length) caracteres" -ForegroundColor Gray
    
    if ($token.Length -ne 11) {
        Write-Host "   ⚠️  AVISO: Token deveria ter 11 caracteres (lucastav8012)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "❌ Erro ao ler config.json: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "Pressione qualquer tecla para sair..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

# 2. Criar/Atualizar .env com conteúdo EXATO (sem espaços extras)
# Garantir que o token está limpo
$tokenLimpo = $token.Trim() -replace '\s+', ''
$envLines = @(
    "# Arquivo .env - Token sincronizado automaticamente",
    "# Data: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')",
    "",
    "API_PORT=3000",
    "API_TOKEN=$tokenLimpo",
    ""
)

Write-Host "📝 Token que será salvo: '$tokenLimpo' ($($tokenLimpo.Length) chars)" -ForegroundColor Cyan

# 3. Escrever .env sem BOM, sem espaços extras
try {
    # Remove o arquivo existente primeiro para garantir limpeza
    if (Test-Path $envPath) {
        Remove-Item $envPath -Force
        Write-Host "🗑️  Arquivo .env antigo removido" -ForegroundColor Yellow
    }
    
    # Cria novo arquivo com encoding UTF8 sem BOM
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllLines($envPath, $envLines, $utf8NoBom)
    
    Write-Host "✅ Arquivo .env criado/atualizado com sucesso!" -ForegroundColor Green
    Write-Host "   Caminho: $envPath" -ForegroundColor Cyan
} catch {
    Write-Host "❌ Erro ao escrever .env: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "Pressione qualquer tecla para sair..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

# 4. Verificar o que foi escrito
Write-Host ""
Write-Host "🔍 VERIFICAÇÃO DO ARQUIVO CRIADO:" -ForegroundColor Cyan
$verificado = Get-Content $envPath -Raw -Encoding UTF8
Write-Host $verificado -ForegroundColor White

# 5. Extrair token do .env para comparação
$tokenEnvVerificado = $null
$linhas = $verificado -split "`n"
foreach ($linha in $linhas) {
    $linha = $linha.Trim()
    if ($linha -match '^API_TOKEN\s*=\s*(.+)$') {
        $tokenEnvVerificado = $matches[1].Trim(" `t`"'")
        break
    }
}

Write-Host ""
Write-Host "📊 COMPARAÇÃO:" -ForegroundColor Cyan
Write-Host "   Token config.json: '$token' (tamanho: $($token.Length))" -ForegroundColor White
Write-Host "   Token .env:        '$tokenEnvVerificado' (tamanho: $($tokenEnvVerificado.Length))" -ForegroundColor White

if ($token -eq $tokenEnvVerificado) {
    Write-Host "   ✅ Tokens são IDÊNTICOS!" -ForegroundColor Green
} else {
    Write-Host "   ❌ Tokens são DIFERENTES!" -ForegroundColor Red
    Write-Host ""
    Write-Host "   🔬 Análise hexadecimal:" -ForegroundColor Yellow
    $bytesJson = [System.Text.Encoding]::UTF8.GetBytes($token)
    $bytesEnv = [System.Text.Encoding]::UTF8.GetBytes($tokenEnvVerificado)
    Write-Host "   JSON: $($bytesJson | ForEach-Object { '{0:X2}' -f $_ } | Join-String -Separator ' ')" -ForegroundColor Gray
    Write-Host "   .env: $($bytesEnv | ForEach-Object { '{0:X2}' -f $_ } | Join-String -Separator ' ')" -ForegroundColor Gray
}

# 6. Verificar processos Node.js
Write-Host ""
Write-Host "🔍 VERIFICANDO PROCESSOS NODE.JS:" -ForegroundColor Cyan
$processos = Get-Process node -ErrorAction SilentlyContinue
if ($processos) {
    Write-Host "   ⚠️  Encontrados $($processos.Count) processo(s) Node.js rodando" -ForegroundColor Yellow
    Write-Host "   Para garantir que o bot use o novo token, você DEVE:" -ForegroundColor Yellow
    Write-Host "   1. Parar TODOS os processos Node.js" -ForegroundColor White
    Write-Host "   2. Reiniciar o bot: cd whatsapp-bot && npm run dev" -ForegroundColor White
} else {
    Write-Host "   ✅ Nenhum processo Node.js encontrado (bom para reiniciar)" -ForegroundColor Green
}

# 7. Instruções finais
Write-Host ""
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "📋 PRÓXIMOS PASSOS OBRIGATÓRIOS:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Pare o bot Node.js (Ctrl+C no terminal)" -ForegroundColor White
Write-Host ""
Write-Host "2. Certifique-se de que NÃO há processos Node.js rodando:" -ForegroundColor White
Write-Host "   Get-Process node" -ForegroundColor Gray
Write-Host ""
Write-Host "3. Se houver processos, termine-os:" -ForegroundColor White
Write-Host "   Stop-Process -Name node -Force" -ForegroundColor Gray
Write-Host ""
Write-Host "4. Reinicie o bot:" -ForegroundColor White
Write-Host "   cd whatsapp-bot" -ForegroundColor Gray
Write-Host "   npm run dev" -ForegroundColor Gray
Write-Host ""
Write-Host "5. Teste novamente em:" -ForegroundColor White
Write-Host "   http://seu-dominio/verificar_token_bot.php" -ForegroundColor Gray
Write-Host ""
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
