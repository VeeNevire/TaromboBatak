@echo off
rem Menjalankan Laravel Reverse (Reverb) untuk chat realtime TaromboBatak.
cd /d C:\laragon\www\TaromboBatak
start "TaromboBatak Reverb" /min php artisan reverb:start
