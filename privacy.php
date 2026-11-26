<?php
// privacy.php — Settings > Privacy policy
// DRAFT: written from how the app currently works. Please review it before publishing.
require 'auth.php';


$pageTitle  = 'Privacy policy';
$activePage = 'privacy';
$publicPolicy = !is_logged_in();
if (!$publicPolicy) require 'settings_header.php';
else {
    defined('SUPPORT_EMAIL') || define('SUPPORT_EMAIL', 'support@example.com');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Privacy policy</title><link rel="stylesheet" href="studyhub.css"><link rel="stylesheet" href="recovery.css"></head><body><main class="recovery card"><a href="index.php#signup">Back to sign in</a>';
}
?>
                <h2 class="panel-title">Privacy policy</h2>
<?php require __DIR__ . '/privacy_content.php'; ?>
<?php if (!$publicPolicy) require 'settings_footer.php'; else echo '</main></body></html>'; ?>