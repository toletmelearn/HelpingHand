# HelpingHand ERP Layout Migration Script
# Migrates all @extends('layouts.app') to proper layouts

Write-Host "Starting layout migration..." -ForegroundColor Green

# Counter
$adminCount = 0
$authCount = 0
$publicCount = 0
$errorCount = 0

# Process admin files (resources/views/admin/**/*.blade.php)
Write-Host "Processing admin files..." -ForegroundColor Yellow
Get-ChildItem -Path "resources\views\admin" -Recurse -Include "*.blade.php" | ForEach-Object {
    $content = Get-Content $_.FullName -Raw
    if ($content -match "@extends\('layouts\.app'\)") {
        $newContent = $content -replace "@extends\('layouts\.app'\)", "@extends('layouts.admin')"
        Set-Content -Path $_.FullName -Value $newContent -Encoding UTF8
        $adminCount++
        Write-Host "Migrated: $($_.Name)" -ForegroundColor Cyan
    }
}

# Process auth files (resources/views/auth/**/*.blade.php)
Write-Host "Processing auth files..." -ForegroundColor Yellow
Get-ChildItem -Path "resources\views\auth" -Recurse -Include "*.blade.php" | ForEach-Object {
    $content = Get-Content $_.FullName -Raw
    if ($content -match "@extends\('layouts\.app'\)") {
        $newContent = $content -replace "@extends\('layouts\.app'\)", "@extends('layouts.public')"
        Set-Content -Path $_.FullName -Value $newContent -Encoding UTF8
        $authCount++
        Write-Host "Migrated: $($_.Name)" -ForegroundColor Cyan
    }
}

# Process remaining public files
Write-Host "Processing other public files..." -ForegroundColor Yellow
$publicFiles = @(
    "resources\views\welcome.blade.php",
    "resources\views\home.blade.php",
    "resources\views\home\index.blade.php"
)

foreach ($file in $publicFiles) {
    if (Test-Path $file) {
        $content = Get-Content $file -Raw
        if ($content -match "@extends\('layouts\.app'\)") {
            $newContent = $content -replace "@extends\('layouts\.app'\)", "@extends('layouts.public')"
            Set-Content -Path $file -Value $newContent -Encoding UTF8
            $publicCount++
            Write-Host "Migrated: $(Split-Path $file -Leaf)" -ForegroundColor Cyan
        }
    }
}

# Process error files
Write-Host "Processing error files..." -ForegroundColor Yellow
$errorFiles = @(
    "resources\views\errors\403.blade.php",
    "resources\views\errors\404.blade.php"
)

foreach ($file in $errorFiles) {
    if (Test-Path $file) {
        $content = Get-Content $file -Raw
        if ($content -match "@extends\('layouts\.app'\)") {
            # For errors, we'll create standalone pages without layouts for maximum compatibility
            $newContent = $content -replace "@extends\('layouts\.app'\)`r?`n", ""
            $newContent = $newContent -replace "@section\('content'\)", "<!DOCTYPE html>`n<html>`n<head>`n<title>Error</title>`n<link href=`"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css`" rel=`"stylesheet`">`n</head>`n<body>`n<div class=`"container mt-5`">"
            $newContent = $newContent -replace "@endsection", "</div>`n<script src=`"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js`"></script>`n</body>`n</html>"
            Set-Content -Path $file -Value $newContent -Encoding UTF8
            $errorCount++
            Write-Host "Migrated: $(Split-Path $file -Leaf)" -ForegroundColor Cyan
        }
    }
}

# Summary
Write-Host "`n=== MIGRATION COMPLETE ===" -ForegroundColor Green
Write-Host "Admin files migrated: $adminCount" -ForegroundColor White
Write-Host "Auth files migrated: $authCount" -ForegroundColor White
Write-Host "Public files migrated: $publicCount" -ForegroundColor White
Write-Host "Error files migrated: $errorCount" -ForegroundColor White
Write-Host "Total files migrated: $($adminCount + $authCount + $publicCount + $errorCount)" -ForegroundColor White
Write-Host "`nClearing caches..." -ForegroundColor Yellow

# Clear caches
php artisan view:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear

Write-Host "Done!" -ForegroundColor Green