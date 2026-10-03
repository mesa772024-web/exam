@echo off
setlocal
cd /d "%~dp0"

set "PHP_EXE="
where php >nul 2>nul
if not errorlevel 1 set "PHP_EXE=php"
if not defined PHP_EXE if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE if exist "C:\laragon\bin\php\php-8.3.0-Win32-vs16-x64\php.exe" set "PHP_EXE=C:\laragon\bin\php\php-8.3.0-Win32-vs16-x64\php.exe"

if not defined PHP_EXE (
  echo PHP was not found.
  echo Install XAMPP or PHP 8.1+, then run this file again.
  echo See INSTALL_AR.md for the Arabic instructions.
  pause
  exit /b 1
)

echo.
echo Website: http://127.0.0.1:8000
echo Install: http://127.0.0.1:8000/install/
echo Admin:   http://127.0.0.1:8000/hyt-so-admin-a9/
echo LAN:     http://YOUR-192-IP:8000
echo.
"%PHP_EXE%" -S 0.0.0.0:8000 router.php
