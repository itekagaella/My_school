Set-Location "C:\xampp\htdocs\My_School"
& "C:\xampp\php\php.exe" "bin\envoyer_emails.php" | Out-File -Append "logs\cron_emails.log" -Encoding UTF8
