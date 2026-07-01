@echo off
title HRM Services

echo ============================================
echo   HRM - Khoi dong cac dich vu
echo ============================================

REM --- 1. Laravel Queue Worker (payroll + default) ---
echo [1/2] Khoi dong Laravel Queue Worker...
start "HRM Queue Worker" cmd /k "cd /d "%~dp0..\..\laravel_app" && php artisan queue:work --queue=payroll,default --sleep=3 --tries=2 --timeout=310 --max-time=3600"

REM Cho queue worker khoi dong truoc
timeout /t 2 /nobreak >nul

REM --- 2. AI Chatbot Service ---
echo [2/2] Khoi dong AI Chatbot Service (port 8001)...
start "HRM Chatbot Service" cmd /k "cd /d "%~dp0..\..\bot_service" && python -m uvicorn app:app --host 127.0.0.1 --port 8001 --reload"

echo.
echo Tat ca dich vu da khoi dong:
echo   - Queue Worker : cua so "HRM Queue Worker"
echo   - Chatbot API  : http://127.0.0.1:8001  (cua so "HRM Chatbot Service")
echo.
echo De dung tat ca: dong cac cua so cmd tuong ung.
pause