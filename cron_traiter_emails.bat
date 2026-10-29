@echo off
cd /d C:\xampp\htdocs\My_School
C:\xampp\php\php.exe bin\envoyer_emails.php >> logs\cron_emails.log 2>&1
