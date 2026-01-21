@echo off 
echo ===== COMPLETE DATABASE FIX ===== 
echo. 
echo Step 1: Dropping old tables... 
php artisan tinker --execute "DB::statement('DROP TABLE IF EXISTS students'); DB::statement('DROP TABLE IF EXISTS teachers'); echo 'Tables dropped';" 
