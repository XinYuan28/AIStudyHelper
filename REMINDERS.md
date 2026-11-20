# Study reminders

Open reminders.php from Study Hub. Calendar events can be downloaded as .ics files or opened in Google Calendar. Users must save the event and verify its notification settings. Imported events are independent copies, so edits and cancellations in Study Helper require updating the calendar copy.

## Telegram setup

1. Create a bot with Telegram's @BotFather using /newbot.
2. Store the token and username in telegram.local.php, which returns an array with token and username keys. This ignored file is denied through Apache. TELEGRAM_BOT_TOKEN and TELEGRAM_BOT_USERNAME environment variables take precedence when supplied. The username excludes @.
3. Run database_migration_reminders.sql and database_migration_telegram.sql against ai_study_note. Fresh installations can use database.sql.
4. The NotePaw Study Reminders task runs every minute using C:\xampp\php\php-win.exe and C:\xampp\htdocs\AIStudyHelper\telegram_worker.php. It runs without opening a console while the Windows user is logged in. MySQL and internet access must be available.
5. Use Connect Telegram on the reminders page, open the link, and press Start. The worker processes the connection on its next run. Check connection, then save a reminder a few minutes ahead.

The worker uses getUpdates polling. A webhook must not be configured for the same bot. PHP cURL and PDO MySQL must be enabled. MySQL, an internet connection, and the scheduled worker must remain running for Telegram delivery.

The worker retries failed sends on later runs. A network interruption after Telegram accepts a message but before the database records success can cause a duplicate. Each run handles up to 30 due reminders, so larger queues can be delayed. Calendar delivery requires a real phone test, and Telegram delivery requires a configured bot test.
