@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-backends.ps1" %*
