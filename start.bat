@echo off
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 (
  echo PHP 8.1 ou ulterieur est requis et doit etre present dans PATH.
  pause
  exit /b 1
)
if not exist vendor\autoload.php (
  where composer >nul 2>nul
  if errorlevel 1 (
    echo Composer est requis pour installer les dependances QR.
    pause
    exit /b 1
  )
  call composer install
  if errorlevel 1 exit /b 1
)
php -S 127.0.0.1:5000 -t public public\router.php
