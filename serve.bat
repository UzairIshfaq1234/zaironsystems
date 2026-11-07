@echo off
REM ZAIRON local launcher: website (/) and admin (/admin) on one port, plus the email worker and scheduler.
REM Double-click it, or run `serve` from this folder. Close the window (or press Ctrl+C) to stop everything.
cd /d "%~dp0"

REM Use php/node from PATH when available, otherwise the usual WAMP / Node.js install folders.
where php >nul 2>nul || set "PATH=C:\wamp64\bin\php\php8.4.15;%PATH%"
where npx >nul 2>nul || set "PATH=C:\Program Files\nodejs;%PATH%"

title ZAIRON
echo Website: http://127.0.0.1:8001     Admin: http://127.0.0.1:8001/admin
echo.
php artisan dev --inline
