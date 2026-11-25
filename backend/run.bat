@echo off
title Church Backend (Port 8000)
cd /d "%~dp0"
py -3.12 -m uvicorn app.main:app --reload --host 127.0.0.1 --port 8000
pause
