@echo off
setlocal enabledelayedexpansion

:: Define variables
set "pluginFile=foundations-for-bricks/foundations-for-bricks.php"   :: Path to main plugin file

:: Run PHP script to update plugin headers
php -f update_plugin_headers.php "%pluginFile%"

echo Plugin headers updated successfully!
pause
