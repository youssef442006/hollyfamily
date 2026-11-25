@echo off
title Coptic Church System - XAMPP PHP Launcher
cls

echo ===================================================
echo     Coptic Church Management System (PHP + MySQL)
echo     نظام إدارة الكنيسة (XAMPP - PHP & MySQL)
echo ===================================================
echo.

:: 1. Start MySQL if not running
netstat -aon | findstr :3306 | findstr LISTENING >nul
if errorlevel 1 (
    echo [1/3] Starting MySQL from XAMPP...
    if exist "C:\xampp\mysql_start.bat" (
        start "" /b "C:\xampp\mysql_start.bat"
    )
    timeout /t 2 /nobreak >nul
) else (
    echo [1/3] MySQL is already running on port 3306 [OK]
)

:: 2. Start Apache if not running
netstat -aon | findstr :80 | findstr LISTENING >nul
if errorlevel 1 (
    echo [2/3] Starting Apache from XAMPP...
    if exist "C:\xampp\apache\bin\httpd.exe" (
        start "XAMPP Apache" /d "C:\xampp\apache\bin" "C:\xampp\apache\bin\httpd.exe"
    )
    timeout /t 2 /nobreak >nul
) else (
    echo [2/3] Apache is already running on port 80 [OK]
)

echo [3/3] Opening Church Management System in browser...
echo.
echo ===================================================
echo   System is ready!
echo   * URL:  http://localhost/church
echo ===================================================
echo.

timeout /t 2 /nobreak >nul
start http://localhost/church

echo You can minimize this window.
pause
