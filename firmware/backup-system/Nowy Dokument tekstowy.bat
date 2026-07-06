@echo off
title Stacja Pogodowa - Tryb Awaryjny (Failover)
echo Uruchamianie procedury zapasowej pobierania danych...
node "%~dp0pogoda_backup.js"
pause