<?php
require_once __DIR__ . '/password_recovery.php';
function telegram_command_help(): string {
    return "NotePawBot sends your scheduled Study Helper reminders. Create, edit and delete reminders on the website.\n\n/resetpassword - get a single-use password reset link for your linked account.";
}
function telegram_reminder_command(int $uid, string $text): string {
    if (preg_match('~^/resetpassword(?:@[a-z0-9_]+)?\s*$~iD', trim($text))) return issue_telegram_password_reset($uid);
    return telegram_command_help();
}
