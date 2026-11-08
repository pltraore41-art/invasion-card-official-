@echo off
cd /d "%~dp0"
if not exist env\Scripts\python.exe (
  echo Python 3.14 est requis. Creez un environnement virtuel puis installez requirements.txt.
  pause
  exit /b 1
)
env\Scripts\python.exe manage.py migrate
if errorlevel 1 exit /b 1
env\Scripts\python.exe manage.py runserver 127.0.0.1:8000
