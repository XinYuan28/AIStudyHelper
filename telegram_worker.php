<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/db.php';
require __DIR__ . '/telegram.php';
require __DIR__ . '/telegram_commands.php';

$pdo = db();
if (!telegram_configured()) {
    fwrite(STDERR, "Configure telegram.local.php or the Telegram environment variables first.
");
    exit(1);
}
if (!(int) $pdo->query("SELECT GET_LOCK('ai_study_telegram_worker', 0)")->fetchColumn()) exit;
try {
    $offset = (int) $pdo->query('SELECT next_update_id FROM telegram_worker_state WHERE id = 1')->fetchColumn();
    $updates = telegram_request('getUpdates', ['offset' => $offset, 'timeout' => 0, 'limit' => 20,
        'allowed_updates' => json_encode(['message'])])['result'];
    foreach ($updates as $update) {
        $message = $update['message'] ?? [];
        $reply = null;
        if (($message['chat']['type'] ?? '') === 'private') {
            $chatId = $message['chat']['id'];
            $text = $message['text'] ?? '';
            if (preg_match('~^/start(?:@[a-z0-9_]+)? ([a-f0-9]{48})$~iD', $text, $match)) {
                $pdo->beginTransaction();
                try {
                    $hash = hash('sha256', $match[1]);
                    $st = $pdo->prepare('SELECT user_id FROM telegram_link_tokens
                        WHERE token_hash = ? AND expires_at_utc > UTC_TIMESTAMP() FOR UPDATE');
                    $st->execute([$hash]);
                    $uid = $st->fetchColumn();
                    if ($uid) {
                        $pdo->prepare('DELETE FROM telegram_connections WHERE user_id = ? OR chat_id = ?')->execute([$uid, $chatId]);
                        $pdo->prepare('INSERT INTO telegram_connections (user_id, chat_id) VALUES (?, ?)')->execute([$uid, $chatId]);
                        $pdo->prepare('DELETE FROM telegram_link_tokens WHERE token_hash = ?')->execute([$hash]);
                        $reply = "NotePawBot is connected to your Study Helper account.

" . telegram_command_help();
                    } else {
                        $reply = 'This connection link expired or was already used. Open Study Helper and create a new Connect Telegram link.';
                    }
                    $pdo->commit();
                } catch (Throwable $error) {
                    $pdo->rollBack();
                    throw $error;
                }
            } else {
                $st = $pdo->prepare('SELECT user_id FROM telegram_connections WHERE chat_id = ?');
                $st->execute([$chatId]);
                $uid = $st->fetchColumn();
                $reply = $uid ? telegram_reminder_command((int) $uid, $text)
                    : 'To connect your account, open Study Helper > Study reminders > Telegram connection, click Connect Telegram, then open the link and press Start.';
            }
        }
        $pdo->prepare('UPDATE telegram_worker_state SET next_update_id = ? WHERE id = 1')
            ->execute([$update['update_id'] + 1]);
        if ($reply !== null) {
            try {
                telegram_request('sendMessage', ['chat_id' => $chatId, 'text' => $reply]);
            } catch (Throwable $error) {
                error_log('[telegram_worker] Could not send command response.');
            }
            usleep(1100000);
        }
    }
    $due = $pdo->query("SELECT r.id FROM study_reminders r JOIN telegram_connections c ON c.user_id = r.user_id
        WHERE r.delivery_method = 'telegram' AND r.cancelled_at_utc IS NULL AND r.telegram_sent_at_utc IS NULL
        AND r.scheduled_at_utc <= UTC_TIMESTAMP() + INTERVAL 1 HOUR
        AND DATE_SUB(r.scheduled_at_utc, INTERVAL r.lead_minutes MINUTE) <= UTC_TIMESTAMP()
        ORDER BY r.scheduled_at_utc LIMIT 30")->fetchAll();
    foreach ($due as $reminder) {
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("SELECT r.*, c.chat_id FROM study_reminders r
                JOIN telegram_connections c ON c.user_id = r.user_id
                WHERE r.id = ? AND r.delivery_method = 'telegram' AND r.cancelled_at_utc IS NULL
                AND r.telegram_sent_at_utc IS NULL
                AND DATE_SUB(r.scheduled_at_utc, INTERVAL r.lead_minutes MINUTE) <= UTC_TIMESTAMP() FOR UPDATE");
            $st->execute([$reminder['id']]);
            $row = $st->fetch();
            if ($row) {
                $local = (new DateTimeImmutable($row['scheduled_at_utc'], new DateTimeZone('UTC')))
                    ->setTimezone(new DateTimeZone($row['time_zone']));
                $heading = $row['lead_minutes'] ? 'Upcoming study session: ' : 'Time to study: ';
                telegram_request('sendMessage', ['chat_id' => $row['chat_id'],
                    'text' => $heading . $row['title'] . "
" . $local->format('j M Y, H:i T')
                        . ' for ' . $row['duration_minutes'] . ' minutes, until '
                        . $local->modify('+' . $row['duration_minutes'] . ' minutes')->format('j M Y, H:i T')
                        . ($row['lead_minutes'] ? "
Reminder set for " . $row['lead_minutes'] . ' minutes before study.' : '')
                        . (empty($row['description']) ? '' : "
" . $row['description'])]);
                $pdo->prepare('UPDATE study_reminders SET telegram_sent_at_utc = UTC_TIMESTAMP() WHERE id = ?')->execute([$row['id']]);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[telegram_worker] ' . $error->getMessage());
        }
        usleep(1100000);
    }
    $pdo->exec('DELETE FROM telegram_link_tokens WHERE expires_at_utc <= UTC_TIMESTAMP()');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "
");
    exit(1);
} finally {
    $pdo->query("SELECT RELEASE_LOCK('ai_study_telegram_worker')");
}