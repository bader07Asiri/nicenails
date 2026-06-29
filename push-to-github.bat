@echo off
setlocal
cd /d "%~dp0"
set GIT_TERMINAL_PROMPT=0
set "LOG=%~dp0git-push-log.txt"
> "%LOG%" echo === git push log %DATE% %TIME% ===
git --version >> "%LOG%" 2>&1
git init >> "%LOG%" 2>&1
git branch -M main >> "%LOG%" 2>&1
git add -A >> "%LOG%" 2>&1
git -c user.email=info@nailart.sa -c user.name=NiceNail commit -m "Add recycle bin (soft-delete+restore), refunds & payment-aware delete guard, finance cleanup, inbox media + WhatsApp import" >> "%LOG%" 2>&1
git remote remove origin >> "%LOG%" 2>&1
git remote add origin https://github.com/bader07Asiri/nicenails.git >> "%LOG%" 2>&1
git push -u origin main --force >> "%LOG%" 2>&1
echo PUSH_EXIT=%errorlevel% >> "%LOG%"
endlocal
