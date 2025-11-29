@echo off
echo Importing database schema...
mysql -h localhost -u u448791511_tamumbi -p"Mumbi@500#" u448791511_tamdb < database_schema.sql
if %errorlevel% equ 0 (
    echo Database imported successfully!
) else (
    echo Error importing database. Please check your MySQL installation and credentials.
)
pause
