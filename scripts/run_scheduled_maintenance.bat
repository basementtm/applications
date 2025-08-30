@echo off
REM Windows Task Scheduler script for Scheduled Maintenance
REM This script should be run every minute via Windows Task Scheduler

REM Change to the applications directory
cd /d "C:\Users\User\git\applications"

REM Run the scheduled maintenance script
php cron\scheduled_maintenance.php

REM Log the execution (optional)
echo %date% %time% - Scheduled maintenance cron executed >> logs\scheduled_maintenance.log
