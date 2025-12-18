# Script PowerShell para organizar arquivos de debug/teste
# Move arquivos de debug para pasta debug/ mantendo estrutura

$debugFiles = @(
    "debug_*.php",
    "test_*.php",
    "teste_*.php",
    "verificar_*.php",
    "*_test.php",
    "base64_test.txt"
)

$debugDir = "debug"
if (-not (Test-Path $debugDir)) {
    New-Item -ItemType Directory -Path $debugDir | Out-Null
    Write-Host "✅ Pasta $debugDir criada"
}

$movedCount = 0
foreach ($pattern in $debugFiles) {
    $files = Get-ChildItem -Path . -Filter $pattern -File -ErrorAction SilentlyContinue
    foreach ($file in $files) {
        $destination = Join-Path $debugDir $file.Name
        if (-not (Test-Path $destination)) {
            Move-Item -Path $file.FullName -Destination $destination -Force
            Write-Host "📦 Movido: $($file.Name) -> $debugDir/"
            $movedCount++
        } else {
            Write-Host "⚠️  Arquivo já existe: $destination"
        }
    }
}

Write-Host "`n✅ Concluído! $movedCount arquivo(s) movido(s) para $debugDir/"
Write-Host "💡 Você pode revisar os arquivos e depois removê-los se necessário"

