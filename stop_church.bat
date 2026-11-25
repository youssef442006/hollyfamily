@echo off
title Stop Coptic Church System
cls

echo ===================================================
echo   Stopping Church Management System (Apache & PHP)
echo ===================================================
echo.

taskkill /f /im httpd.exe >nul 2>&1
echo [OK] Apache httpd stopped.

echo.
echo Note: MySQL in XAMPP was left running (or stop it from XAMPP Control Panel).
echo.
pause
