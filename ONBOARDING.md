# Account onboarding
Import database_migration_onboarding.sql on existing installations before deploying these files. Included in database.sql for fresh installs.
Signup requires privacy_agree=1 on the server and records the policy version and UTC acceptance time. Returning users can log in without agreeing again.
New accounts receive one Telegram introduction on their first Study Hub page load. Existing accounts do not receive this popup.
For non-Gmail/non-Googlemail login emails, Calendar displays a Google account checkbox. Unchecked uses calendar-file export; checked also shows the Google Calendar link. Telegram does not require Google.
The bot command menu should contain only resetpassword. Private /start account linking and automatic scheduled study reminder delivery remain enabled.
