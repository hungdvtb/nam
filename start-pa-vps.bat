@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start_pa_runner.ps1" -HostId vps %*
pause
