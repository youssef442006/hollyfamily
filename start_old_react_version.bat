@echo off
title Church Management System - Launcher
cls

echo ===================================================
echo     Church Management System
echo ===================================================
echo.

cd /d "%~dp0"

echo [1/3] Starting Backend (Port 8000)...
start "Church Backend" "%~dp0backend\run.bat"

echo [2/3] Starting Frontend (Port 3000)...
start "Church Frontend" "%~dp0frontend\run.bat"

echo.
echo ===================================================
echo   Servers are starting in separate windows!
echo ===================================================
echo.
echo   * Frontend: http://localhost:3000
echo   * API Docs: http://localhost:8000/docs
echo.
echo   Default Credentials:
echo     - Admin:   admin   / admin123
echo     - Priest:  priest  / priest123
echo     - Servant: servant / servant123
echo ===================================================
echo.
echo Opening browser in 4 seconds...
timeout /t 4 /nobreak >nul
start http://localhost:3000

echo.
echo Note: Please keep both server windows open.
pause
