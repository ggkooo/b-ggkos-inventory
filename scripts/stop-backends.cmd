@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0stop-backends.ps1" -IncludeUnknownPortListeners %*
