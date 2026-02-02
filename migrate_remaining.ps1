# Second migration pass for remaining files
# These are mostly role-specific dashboards and authenticated pages

Write-Host "Processing remaining files..." -ForegroundColor Green

$remainingFiles = @(
    "resources\views\attendance\bulk_mark.blade.php",
    "resources\views\attendance\export.blade.php",
    "resources\views\attendance\reports.blade.php",
    "resources\views\bell-timing\daily.blade.php",
    "resources\views\bell-timing\weekly.blade.php",
    "resources\views\exam-papers\available.blade.php",
    "resources\views\exam-papers\create.blade.php",
    "resources\views\exam-papers\edit.blade.php",
    "resources\views\exam-papers\show.blade.php",
    "resources\views\exam-papers\upcoming.blade.php",
    "resources\views\notifications\index.blade.php",
    "resources\views\parent\lesson-plans\books-to-send.blade.php",
    "resources\views\parent\lesson-plans\index.blade.php",
    "resources\views\parent\lesson-plans\show.blade.php",
    "resources\views\parent\lesson-plans\weekly-overview.blade.php",
    "resources\views\parents\child-details.blade.php",
    "resources\views\parents\dashboard.blade.php",
    "resources\views\profile\two-factor-authentication.blade.php",
    "resources\views\student\admit-cards\index.blade.php",
    "resources\views\student\admit-cards\show.blade.php",
    "resources\views\student\daily-teaching-work\index.blade.php",
    "resources\views\student\results\index.blade.php",
    "resources\views\teacher\lesson-plans\create.blade.php",
    "resources\views\teacher\lesson-plans\edit.blade.php",
    "resources\views\teacher\lesson-plans\history.blade.php",
    "resources\views\teacher\lesson-plans\index.blade.php",
    "resources\views\teacher\results\enter-marks.blade.php",
    "resources\views\teacher\results\index.blade.php",
    "resources\views\teachers\biometric\dashboard.blade.php",
    "resources\views\teachers\biometric\notification-preferences.blade.php",
    "resources\views\teachers\dashboard.blade.php",
    "resources\views\users\create.blade.php",
    "resources\views\users\edit.blade.php",
    "resources\views\users\index.blade.php",
    "resources\views\users\show.blade.php",
    "resources\views\admin-dashboard.blade.php",
    "resources\views\parent-dashboard.blade.php"
)

$count = 0
foreach ($file in $remainingFiles) {
    if (Test-Path $file) {
        $content = Get-Content $file -Raw
        if ($content -match "@extends\('layouts\.app'\)") {
            $newContent = $content -replace "@extends\('layouts\.app'\)", "@extends('layouts.admin')"
            Set-Content -Path $file -Value $newContent -Encoding UTF8
            $count++
            Write-Host "Migrated: $(Split-Path $file -Leaf)" -ForegroundColor Cyan
        }
    }
}

Write-Host "`nSecond pass complete. Files migrated: $count" -ForegroundColor Green

# Clear caches again
Write-Host "Clearing caches..." -ForegroundColor Yellow
php artisan view:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear

Write-Host "All done!" -ForegroundColor Green