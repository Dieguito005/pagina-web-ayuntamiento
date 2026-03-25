# refactor.ps1
$ErrorActionPreference = 'Stop'

# 1. Crear directorios
$dirs = @("html", "css", "js", "admin", "docs")
foreach ($d in $dirs) {
    if (-not (Test-Path $d)) {
        New-Item -ItemType Directory -Force -Path $d | Out-Null
    }
}

# 2. Mover archivos a sus carpetas
Get-ChildItem -Filter *.html | Where-Object { $_.Name -ne "index.html" } | Move-Item -Destination "html" -Force
Get-ChildItem -Filter *.css | Move-Item -Destination "css" -Force
Get-ChildItem -Filter *.js | Move-Item -Destination "js" -Force
Get-ChildItem -Filter admin*.php | Move-Item -Destination "admin" -Force
if (Test-Path "enviar-correo.php") { Move-Item "enviar-correo.php" "api" -Force }

# 3. Actualizar rutas en archivos HTML
$htmlFiles = Get-ChildItem -Path "html" -Filter *.html
foreach ($file in $htmlFiles) {
    $content = Get-Content $file.FullName -Raw -Encoding UTF8
    
    # Actualizar links a css
    $content = [System.Text.RegularExpressions.Regex]::Replace($content, 'href="([^"]+?\.css)"', 'href="../css/$1"')
    # Actualizar src a js
    $content = [System.Text.RegularExpressions.Regex]::Replace($content, 'src="([^"]+?\.js)"', 'src="../js/$1"')
    
    # Actualizar links a php de admin
    $content = [System.Text.RegularExpressions.Regex]::Replace($content, 'href="admin((-[^"]+)?\.php)"', 'href="../admin/admin$1"')
    
    # Actualizar assets
    $content = [System.Text.RegularExpressions.Regex]::Replace($content, 'src="assets/', 'src="../assets/')
    $content = [System.Text.RegularExpressions.Regex]::Replace($content, 'href="assets/', 'href="../assets/')
    
    Set-Content -Path $file.FullName -Value $content -Encoding UTF8
}

# 4. Actualizar rutas en archivos JS
$jsFiles = Get-ChildItem -Path "js" -Filter *.js
foreach ($file in $jsFiles) {
    $content = Get-Content $file.FullName -Raw -Encoding UTF8
    
    # fetch a API
    $content = [System.Text.RegularExpressions.Regex]::Replace($content, "fetch\('api/", "fetch('../api/")
    $content = [System.Text.RegularExpressions.Regex]::Replace($content, 'fetch\(`api/', 'fetch(`../api/')
    
    # fetch a enviar-correo.php
    $content = [System.Text.RegularExpressions.Regex]::Replace($content, "fetch\('enviar-correo.php'", "fetch('../api/enviar-correo.php'")
    
    Set-Content -Path $file.FullName -Value $content -Encoding UTF8
}

# 5. Actualizar rutas en archivos PHP (Admin)
$adminPhpFiles = Get-ChildItem -Path "admin" -Filter *.php
foreach ($file in $adminPhpFiles) {
    $content = Get-Content $file.FullName -Raw -Encoding UTF8
    
    # conexion.php está en api/
    $content = [System.Text.RegularExpressions.Regex]::Replace($content, "require_once 'api/conexion.php'", "require_once '../api/conexion.php'")
    
    # Redirecciones a HTML
    $content = [System.Text.RegularExpressions.Regex]::Replace($content, 'href="home.html"', 'href="../html/home.html"')
    
    # CSS de admin-login y admin-dashboard
    $content = [System.Text.RegularExpressions.Regex]::Replace($content, 'href="home.css"', 'href="../css/home.css"')
    
    # Assets
    $content = [System.Text.RegularExpressions.Regex]::Replace($content, 'src="assets/', 'src="../assets/')
    
    # JS inclusion in admin-dashboard
    $content = [System.Text.RegularExpressions.Regex]::Replace($content, 'src="admin.js"', 'src="../js/admin.js"')
    
    Set-Content -Path $file.FullName -Value $content -Encoding UTF8
}

# 6. Crear index.html root
$rootIndex = @"
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0; url=html/home.html">
    <title>Redirigiendo...</title>
</head>
<body>
    <p>Si no eres redirigido, haz clic <a href="html/home.html">aquí para ir al inicio</a>.</p>
</body>
</html>
"@
Set-Content -Path "index.html" -Value $rootIndex -Encoding UTF8

Write-Output "Refactor complete."
