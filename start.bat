@echo off
chcp 65001 >nul
title Dança Carajás Captação - Ambiente Docker
cd /d "%~dp0"

REM ===================================================================
REM  Dança Carajás Captação - inicia o ambiente local com Docker
REM  Sobe os containers (PHP+Apache, MariaDB e phpMyAdmin) e abre
REM  o navegador em http://localhost:8080
REM ===================================================================

set "APP_URL=http://localhost:8080"
set "PMA_URL=http://localhost:8082"

echo.
echo  ==============================================
echo   Danca Carajas Captacao - Ambiente Docker
echo  ==============================================
echo.

REM Verifica se o Docker esta disponivel no PATH
where docker >nul 2>&1
if errorlevel 1 (
    echo  [ERRO] Docker nao encontrado no PATH.
    echo  Instale o Docker Desktop e abra-o antes de rodar este script.
    echo.
    pause
    exit /b 1
)

REM Verifica se o Docker Desktop esta em execucao
docker info >nul 2>&1
if errorlevel 1 (
    echo  [ERRO] O Docker nao esta em execucao.
    echo  Abra o Docker Desktop, aguarde iniciar e rode novamente.
    echo.
    pause
    exit /b 1
)

echo  Subindo containers (pode demorar no primeiro build)...
echo.
docker compose up -d --build
if errorlevel 1 (
    echo.
    echo  [ERRO] Falha ao subir os containers. Veja a mensagem acima.
    echo.
    pause
    exit /b 1
)

echo.
echo  Aguardando o banco de dados ficar pronto...
REM Aguarda ate ~60s o servico de banco ficar "healthy"
set /a tries=0
:waitdb
for /f %%s in ('docker inspect -f "{{.State.Health.Status}}" dcc_db 2^>nul') do set "DBSTATUS=%%s"
if /i "%DBSTATUS%"=="healthy" goto ready
set /a tries+=1
if %tries% geq 30 goto ready
timeout /t 2 >nul
goto waitdb

:ready
echo.
echo  ==============================================
echo   Ambiente no ar!
echo  ==============================================
echo   Sistema......: %APP_URL%
echo   phpMyAdmin...: %PMA_URL%
echo.
echo   Login........: admin@dancacarajas.com
echo   Senha........: Mudar@123  (troca obrigatoria no 1o acesso)
echo.
echo   Parar........: docker compose down
echo   Zerar banco..: docker compose down -v
echo  ==============================================
echo.

REM Abre o navegador no sistema
start "" %APP_URL%/

echo  Pressione qualquer tecla para fechar esta janela.
echo  (Os containers continuam rodando em segundo plano.)
pause >nul
