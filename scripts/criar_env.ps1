# Criar .env com token correto
$envPath = Join-Path $PSScriptRoot "..\whatsapp-bot\.env"
$content = @"
# Arquivo .env - Token corrigido
API_PORT=3000
API_TOKEN=lucastav8012
"@

$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($envPath, $content, $utf8NoBom)

Write-Host "Arquivo .env criado em: $envPath"
Write-Host ""
Write-Host "Conteudo:"
Get-Content $envPath
Write-Host ""
Write-Host "Agora reinicie o bot Node.js:"
Write-Host "  1. Stop-Process -Name node -Force"
Write-Host "  2. cd whatsapp-bot"
Write-Host "  3. npm run dev"
