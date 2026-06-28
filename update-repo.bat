@echo off
cd /d "%~dp0"
echo ============================================
echo   Updating Nice Nail repo (inbox + notifications + PWA)
echo ============================================
git --version
if errorlevel 1 (echo Git not found. & pause & exit /b 1)
git init
git branch -M main
git add -A
git -c user.email=info@nailart.sa -c user.name=NiceNail commit -m "Add inbox, Telegram/Push notifications, PWA"
git remote remove origin 2>nul
git remote add origin https://github.com/bader07Asiri/nicenails.git
git push -u origin main --force
echo.
echo ============================================
echo   DONE - read messages above. Then Redeploy in Coolify.
echo ============================================
pause
