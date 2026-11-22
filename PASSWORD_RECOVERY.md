# Telegram password recovery

Import database_migration_password_reset.sql for existing databases. Fresh installs use database.sql.
Users must connect their Telegram account in Study reminders while they can still sign in.
Forgot password on the login page explains how to send /resetpassword to that previously linked bot.
The worker returns a single-use link valid for 15 minutes. Requests are limited to one per two minutes.
Only hashes of random tokens are stored. Links use a URL fragment to keep the secret out of HTTP access logs.
Resetting the password invalidates other sessions, pending Telegram connection links and the recovery token.
Existing users will need to log in once after this update because sessions now bind to the password hash.

Set APP_BASE_URL to the deployment HTTPS URL (including /AIStudyHelper when applicable), or add app_base_url to ignored telegram.local.php.
The default http://localhost/AIStudyHelper only works on the XAMPP computer. Mobile recovery requires a reachable HTTPS deployment.
Never derive reset links from the incoming Host header. Run the Telegram worker against the same database as the website.
