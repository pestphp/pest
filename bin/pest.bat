@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../pestphp/pest/bin/pest
php "%BIN_TARGET%" %*
