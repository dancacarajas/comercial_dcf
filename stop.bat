@echo off
chcp 65001 >nul
title Dança Carajás Captação - Parar ambiente Docker
cd /d "%~dp0"

REM ===================================================================
REM  Para os containers do ambiente local (mantem os dados do banco).
REM  Para ZERAR o banco tambem, rode: docker compose down -v
REM ===================================================================

echo.
echo  Parando containers do Danca Carajas Captacao...
echo.
docker compose down

echo.
echo  Containers parados. Os dados do banco foram preservados.
echo  (Para apagar o banco use: docker compose down -v)
echo.
pause
