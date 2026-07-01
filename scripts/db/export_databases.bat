@echo off
setlocal EnableExtensions EnableDelayedExpansion

REM Export one or many MySQL databases to .sql files (Windows/XAMPP friendly)
REM Usage:
REM   export_databases.bat
REM   export_databases.bat quanlynhansu payroll attendance

set "ROOT_DIR=%~dp0\..\.."
for %%I in ("%ROOT_DIR%") do set "ROOT_DIR=%%~fI"

set "MYSQL_BIN=C:\xampp\mysql\bin"
set "MYSQL_HOST=127.0.0.1"
set "MYSQL_PORT=3306"
set "MYSQL_USER=root"
set "MYSQL_PASSWORD="

set "TIMESTAMP=%date:~6,4%%date:~3,2%%date:~0,2%_%time:~0,2%%time:~3,2%"
set "TIMESTAMP=%TIMESTAMP: =0%"
set "OUT_DIR=%ROOT_DIR%\database_backups\%TIMESTAMP%"

if not exist "%MYSQL_BIN%\mysqldump.exe" (
  echo [ERROR] Cannot find mysqldump.exe in "%MYSQL_BIN%".
  echo         Update MYSQL_BIN in this script if your MySQL path is different.
  exit /b 1
)

if not exist "%OUT_DIR%" mkdir "%OUT_DIR%"

if "%~1"=="" (
  set "DB_LIST=quanlynhansu payroll attendance"
) else (
  set "DB_LIST=%*"
)

set "PASS_ARG="
if defined MYSQL_PASSWORD set "PASS_ARG=-p%MYSQL_PASSWORD%"

echo [INFO] Export directory: %OUT_DIR%
echo [INFO] Databases: %DB_LIST%

for %%D in (%DB_LIST%) do (
  set "OUT_FILE=%OUT_DIR%\%%D.sql"
  echo [INFO] Exporting %%D ...
  "%MYSQL_BIN%\mysqldump.exe" -h %MYSQL_HOST% -P %MYSQL_PORT% -u %MYSQL_USER% %PASS_ARG% --routines --triggers --events --single-transaction --quick --default-character-set=utf8mb4 %%D > "!OUT_FILE!"
  if errorlevel 1 (
    echo [ERROR] Failed to export %%D
    exit /b 1
  )
  echo [OK] %%D => !OUT_FILE!
)

echo [DONE] All database exports completed.
exit /b 0
