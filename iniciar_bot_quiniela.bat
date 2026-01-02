@echo off
title BOT QUINIELA - AUTOMATIZACION
color 0A

echo ========================================================
echo   BOT DE ACTUALIZACION AUTOMATICA - QUINIELA PRO
echo   Este script mantendra tu sistema vivo y actualizado.
echo   No cierres esta ventana (puedes minimizarla).
echo ========================================================
echo.

:loop
echo [%TIME%] Iniciando actualizacion masiva de ligas y puntos...
echo --------------------------------------------------------

:: Ejecutamos el comando maestro de Laravel
C:\App_Quiniela\laragon\bin\php\php-8.3.28-Win32-vs16-x64\php.exe artisan system:auto-update

echo --------------------------------------------------------
echo [%TIME%] Actualizacion terminada.
echo.
echo Dormire por 60 minutos antes de la siguiente actualizacion...
echo Si quieres forzar una actualizacion, cierra y abre este archivo.
echo.

:: Esperar 3600 segundos (1 hora)
timeout /t 3600

:: Volver al inicio
goto loop
