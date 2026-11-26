<?php
require_once __DIR__ . '/db.php';
const PRIVACY_POLICY_VERSION = '2026-11-25';
function record_privacy_acceptance(int $uid): void {
    db()->prepare('INSERT INTO account_onboarding (user_id, privacy_version, privacy_accepted_at_utc) VALUES (?, ?, UTC_TIMESTAMP())
        ON DUPLICATE KEY UPDATE privacy_version=VALUES(privacy_version), privacy_accepted_at_utc=VALUES(privacy_accepted_at_utc)')
        ->execute([$uid, PRIVACY_POLICY_VERSION]);
}
function claim_telegram_welcome(int $uid): bool {
    $st=db()->prepare('UPDATE account_onboarding SET telegram_welcome_pending=0 WHERE user_id=? AND telegram_welcome_pending=1');
    $st->execute([$uid]); return $st->rowCount()>0;
}
