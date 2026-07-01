@echo off
setlocal EnableExtensions EnableDelayedExpansion

REM Import .sql backups into MySQL databases (Windows/XAMPP friendly)
REM Usage:
REM   import_databases.bat "C:\path\to\database_backups\20260507_1015"
REM   import_databases.bat "C:\path\to\backup" quanlynhansu payroll attendance

if "%~1"=="" (
  echo [ERROR] Missing backup folder path.
  echo Usage: import_databases.bat "C:\path\to\backup_folder" [db1 db2 db3 ...]
  exit /b 1
)

set "BACKUP_DIR=%~1"
if not exist "%BACKUP_DIR%" (
  echo [ERROR] Backup folder not found: %BACKUP_DIR%
  exit /b 1
)

set "MYSQL_BIN=C:\xampp\mysql\bin"
set "MYSQL_HOST=127.0.0.1"
set "MYSQL_PORT=3306"
set "MYSQL_USER=root"
set "MYSQL_PASSWORD="

if not exist "%MYSQL_BIN%\mysql.exe" (
  echo [ERROR] Cannot find mysql.exe in "%MYSQL_BIN%".
  echo         Update MYSQL_BIN in this script if your MySQL path is different.
  exit /b 1
)

if "%~2"=="" (
  set "DB_LIST=quanlynhansu payroll attendance"
) else (
  shift
  set "DB_LIST=%*"
)

set "PASS_ARG="
if defined MYSQL_PASSWORD set "PASS_ARG=-p%MYSQL_PASSWORD%"

echo [INFO] Backup folder: %BACKUP_DIR%
echo [INFO] Databases: %DB_LIST%

for %%D in (%DB_LIST%) do (
  set "SQL_FILE=%BACKUP_DIR%\%%D.sql"
  if exist "!SQL_FILE!" (
    echo [INFO] Creating database %%D if not exists...
    "%MYSQL_BIN%\mysql.exe" -h %MYSQL_HOST% -P %MYSQL_PORT% -u %MYSQL_USER% %PASS_ARG% -e "CREATE DATABASE IF NOT EXISTS %%D CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
    if errorlevel 1 (
      echo [ERROR] Cannot create/check database %%D
      exit /b 1
    )

    echo [INFO] Importing %%D from !SQL_FILE! ...
    "%MYSQL_BIN%\mysql.exe" -h %MYSQL_HOST% -P %MYSQL_PORT% -u %MYSQL_USER% %PASS_ARG% %%D < "!SQL_FILE!"
    if errorlevel 1 (
      echo [ERROR] Failed to import %%D
      exit /b 1
    )
    echo [OK] Imported %%D
  ) else (
    echo [WARN] Skip %%D: missing !SQL_FILE!
  )
)

echo [DONE] Database import process completed.
exit /b 0
