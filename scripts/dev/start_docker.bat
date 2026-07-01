@echo off
setlocal

title HRM Docker Startup

echo ============================================
echo   HRM - Khoi dong bang Docker
echo ============================================

cd /d "%~dp0\..\.."

set "DOCKER_CMD=docker"
if exist "C:\Program Files\Docker\Docker\resources\bin\docker.exe" (
  set "DOCKER_CMD=C:\Program Files\Docker\Docker\resources\bin\docker.exe"
  set "PATH=C:\Program Files\Docker\Docker\resources\bin;%PATH%"
)

where "%DOCKER_CMD%" >nul 2>nul
if errorlevel 1 (
  echo [ERROR] Khong tim thay lenh docker.
  echo Hay cai Docker Desktop, mo app Docker Desktop, roi chay lai script nay.
  pause
  exit /b 1
)

if not exist "laravel_app\.env" (
  echo [INFO] Tao laravel_app\.env tu file Docker mau...
  copy /Y "laravel_app\.env.docker.example" "laravel_app\.env" >nul
)

echo [1/3] Build va khoi dong container...
"%DOCKER_CMD%" compose up -d --build
if errorlevel 1 (
  echo [ERROR] docker compose up that bai.
  pause
  exit /b 1
)

echo [2/3] Cai composer dependencies trong container web...
"%DOCKER_CMD%" compose exec web composer install
if errorlevel 1 (
  echo [ERROR] composer install that bai.
  pause
  exit /b 1
)

echo [3/3] Khoi tao Laravel key + clear cache...
"%DOCKER_CMD%" compose exec web php laravel_app/artisan key:generate --force
"%DOCKER_CMD%" compose exec web php laravel_app/artisan optimize:clear

echo.
echo Hoan tat. Truy cap: http://localhost:8080
echo Neu can migrate: docker compose exec web php laravel_app/artisan migrate --seed
echo.
pause
