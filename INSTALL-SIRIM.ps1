param(
    [string]$ProjectPath = (Join-Path (Split-Path -Parent $PSScriptRoot) "SIRIM")
)
$ErrorActionPreference = "Stop"
$KitPath = $PSScriptRoot

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    Write-Host "ERROR: Composer no está disponible en el PATH. Instala/activa Composer y vuelve a ejecutar." -ForegroundColor Red
    exit 1
}
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "ERROR: PHP no está disponible en el PATH de esta terminal." -ForegroundColor Red
    exit 1
}

$ProjectPath = [System.IO.Path]::GetFullPath($ProjectPath)
if (-not (Test-Path (Join-Path $ProjectPath "artisan"))) {
    if (Test-Path $ProjectPath) {
        Write-Host "ERROR: La carpeta destino ya existe y no parece un proyecto Laravel: $ProjectPath" -ForegroundColor Red
        Write-Host "Usa una carpeta vacía diferente o haz una copia de seguridad. No se borró nada."
        exit 1
    }
    $parent = Split-Path -Parent $ProjectPath
    $leaf = Split-Path -Leaf $ProjectPath
    if (-not (Test-Path $parent)) { New-Item -ItemType Directory -Path $parent -Force | Out-Null }
    Push-Location $parent
    try { composer create-project laravel/laravel $leaf "12.*"; if ($LASTEXITCODE -ne 0) { throw "composer create-project falló." } }
    finally { Pop-Location }
}

if (-not (Test-Path (Join-Path $ProjectPath "artisan"))) {
    throw "No se encontró artisan en $ProjectPath. La instalación base no se completó."
}

Write-Host "Copiando archivos de SIRIM a $ProjectPath ..." -ForegroundColor Cyan
Copy-Item -Path (Join-Path $KitPath "app\*") -Destination (Join-Path $ProjectPath "app") -Recurse -Force
Copy-Item -Path (Join-Path $KitPath "bootstrap\app.php") -Destination (Join-Path $ProjectPath "bootstrap\app.php") -Force
Copy-Item -Path (Join-Path $KitPath "routes\web.php") -Destination (Join-Path $ProjectPath "routes\web.php") -Force
Copy-Item -Path (Join-Path $KitPath "resources\views\*") -Destination (Join-Path $ProjectPath "resources\views") -Recurse -Force
Copy-Item -Path (Join-Path $KitPath "database\migrations\*.php") -Destination (Join-Path $ProjectPath "database\migrations") -Force
Copy-Item -Path (Join-Path $KitPath "database\seeders\DatabaseSeeder.php") -Destination (Join-Path $ProjectPath "database\seeders\DatabaseSeeder.php") -Force
Copy-Item -Path (Join-Path $KitPath "README.md") -Destination (Join-Path $ProjectPath "README-SIRIM.md") -Force

Write-Host "\nArchivos copiados. SIGUE estos pasos:" -ForegroundColor Green
Write-Host "1) Crea la base MySQL: CREATE DATABASE sirim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
Write-Host "2) Edita .env en: $ProjectPath"
Write-Host "   DB_CONNECTION=mysql; DB_HOST=127.0.0.1; DB_PORT=3306; DB_DATABASE=sirim; DB_USERNAME=root; DB_PASSWORD=tu_clave"
Write-Host "3) Abre una terminal en $ProjectPath y ejecuta: php artisan key:generate"
Write-Host "4) Ejecuta: php artisan migrate --seed"
Write-Host "5) Ejecuta: php artisan serve y abre http://127.0.0.1:8000"
Write-Host "Los usuarios de demostración y contraseñas están en README-SIRIM.md."
