# Script para verificar arquivos que precisam ser enviados para Hostinger
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "VERIFICAÇÃO DE DEPLOY - HOSTINGER" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Ajustar caminho base (sobe um nível, pois o script está em scripts/)
$scriptDir = Split-Path -Parent $PSScriptRoot
Set-Location $scriptDir

# Verificar commits não enviados
Write-Host "📦 Commits não enviados para GitHub:" -ForegroundColor Yellow
$commits = git log --oneline origin/main..HEAD 2>&1
if ($commits -and $commits.Count -gt 0) {
    Write-Host "  ⚠️ Há commits locais não enviados!" -ForegroundColor Red
    $commits | Select-Object -First 5 | ForEach-Object { Write-Host "    - $_" -ForegroundColor Gray }
    Write-Host ""
    Write-Host "  💡 Execute: git push origin main" -ForegroundColor Yellow
} else {
    Write-Host "  ✅ Todos os commits estão no GitHub" -ForegroundColor Green
}
Write-Host ""

# Arquivos modificados
Write-Host "📝 Arquivos modificados (não commitados):" -ForegroundColor Yellow
$modified = git status --short | Where-Object { $_ -match '^ M' }
if ($modified) {
    Write-Host "  ⚠️ Há arquivos modificados não commitados!" -ForegroundColor Red
    $modified | ForEach-Object { 
        $file = ($_ -replace '^ M\s+', '')
        if ($file -match '\.(php|json)$' -and $file -notmatch '^whatsapp-bot') {
            Write-Host "    - $file" -ForegroundColor Gray
        }
    }
    Write-Host ""
    Write-Host "  💡 Execute: git add . && git commit -m 'mensagem'" -ForegroundColor Yellow
} else {
    Write-Host "  ✅ Nenhum arquivo modificado" -ForegroundColor Green
}
Write-Host ""

# Arquivos importantes que precisam ser enviados
Write-Host "🚀 ARQUIVOS PRINCIPAIS PARA UPLOAD NA HOSTINGER:" -ForegroundColor Cyan
Write-Host ""

$arquivosImportantes = @(
    "admin.php",
    "includes/whatsapp_helper.php",
    "config.json",
    "test_whatsapp_manual.php"
)

foreach ($arquivo in $arquivosImportantes) {
    if (Test-Path $arquivo) {
        $lastModified = (Get-Item $arquivo).LastWriteTime
        Write-Host "  ✓ $arquivo" -ForegroundColor Green
        Write-Host "    Última modificação: $lastModified" -ForegroundColor Gray
    } else {
        Write-Host "  ✗ $arquivo (NÃO ENCONTRADO)" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "PRÓXIMOS PASSOS:" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Se há commits não enviados:" -ForegroundColor Yellow
Write-Host "   git push origin main" -ForegroundColor White
Write-Host ""
Write-Host "2. Fazer upload manual na Hostinger:" -ForegroundColor Yellow
Write-Host "   - Acesse cPanel > File Manager" -ForegroundColor White
Write-Host "   - Navegue até public_html/" -ForegroundColor White
Write-Host "   - Faça upload dos arquivos listados acima" -ForegroundColor White
Write-Host ""
Write-Host "3. IMPORTANTE: Atualizar config.json na Hostinger:" -ForegroundColor Yellow
Write-Host "   Altere WHATSAPP_API_URL para:" -ForegroundColor White
Write-Host "   https://lazaro-enforceable-finley.ngrok-free.dev" -ForegroundColor Cyan
Write-Host ""
Write-Host "4. Testar após upload:" -ForegroundColor Yellow
Write-Host "   https://seu-dominio.com/test_whatsapp_manual.php" -ForegroundColor White
Write-Host ""
