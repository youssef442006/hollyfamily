@echo off
title Stop Church Management System
cls

echo ===================================================
echo   Stopping Church Management System...
echo ===================================================
echo.

echo Stopping Port 8000 (Backend)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8000 ^| findstr LISTENING 2^>nul') do (
    taskkill /f /pid %%a >nul 2>&1
)

echo Stopping Port 3000 (Frontend)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :3000 ^| findstr LISTENING 2^>nul') do (
    taskkill /f /pid %%a >nul 2>&1
)

echo.
echo [OK] All servers stopped successfully.
echo.
pause
