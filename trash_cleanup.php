<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/trash_backend.php';
try {
    $count = purge_expired_notes();
    echo 'Expired notes permanently deleted: ' . $count . PHP_EOL;
} catch (Throwable $error) {
    error_log('[trash_cleanup] ' . $error->getMessage());
    exit(1);
}