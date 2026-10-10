@echo off
setlocal
cd /d "%~dp0"

where php >nul 2>nul
if %errorlevel%==0 (
    set "PHP_EXE=php"
) else if exist "C:\xampp\htdocs\webnam\php84\php.exe" (
    set "PHP_EXE=C:\xampp\htdocs\webnam\php84\php.exe"
) else if exist "C:\xampp\php\php.exe" (
    set "PHP_EXE=C:\xampp\php\php.exe"
) else (
    echo Khong tim thay PHP. Hay cai XAMPP hoac them php vao PATH.
    pause
    exit /b 1
)

echo Dang chay AI Trade Trainer tai http://127.0.0.1:8010/
echo Dung tat cua so nay neu ban dang muon mo app.
"%PHP_EXE%" -S 127.0.0.1:8010 -t "%~dp0"
pause
