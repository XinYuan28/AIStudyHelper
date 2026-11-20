<?php
require_once __DIR__ . '/db.php';

class ReminderException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

function save_study_reminder(array $fields, int $uid, ?int $id = null): int
{
    $fields[7] = $fields[7] ?? 0;
    if (!in_array($fields[7], [0, 5, 15, 30, 60], true)) {
        throw new ReminderException(400, 'Choose a reminder at the start or 5, 15, 30, or 60 minutes before.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $fields[1], new DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d H:i:s') !== $fields[1] || $date->getTimestamp() <= time()) {
        throw new ReminderException(400, 'Choose a future study date and time.');
    }
    if ($fields[6] < 1 || (int) $date->modify('+' . $fields[6] . ' minutes')->format('Y') > 9999) {
        throw new ReminderException(400, 'Choose a valid study period.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $lock->execute([$uid]);
        if (!$lock->fetchColumn()) throw new ReminderException(401, 'Please log in again.');
        $previous = null;
        if ($id !== null) {
            $owned = $pdo->prepare('SELECT * FROM study_reminders WHERE id = ? AND user_id = ?
                AND cancelled_at_utc IS NULL FOR UPDATE');
            $owned->execute([$id, $uid]);
            $previous = $owned->fetch();
            if (!$previous) throw new ReminderException(404, 'Reminder not found.');
        }
        $end = $date->modify('+' . $fields[6] . ' minutes')->format('Y-m-d H:i:s');
        $overlap = $pdo->prepare('SELECT id FROM study_reminders WHERE user_id = ?
            AND cancelled_at_utc IS NULL AND id <> ?
            AND scheduled_at_utc < ? AND DATE_ADD(scheduled_at_utc, INTERVAL duration_minutes MINUTE) > ?
            AND NOT (note_id <=> ? AND ? IS NOT NULL AND delivery_method <> ?)
            LIMIT 1 FOR UPDATE');
        $overlap->execute([$uid, $id ?? 0, $end, $fields[1], $fields[4], $fields[4], $fields[3]]);
        if ($overlap->fetchColumn()) {
            throw new ReminderException(409, 'This study period overlaps another revision session. Choose another start time or adjust the study period.');
        }
        if ($id === null) {
            $st = $pdo->prepare('INSERT INTO study_reminders
                (title, scheduled_at_utc, time_zone, delivery_method, note_id, description, duration_minutes, lead_minutes, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $st->execute([...$fields, $uid]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $scheduleChanged = $previous['scheduled_at_utc'] !== $fields[1]
                || $previous['delivery_method'] !== $fields[3] || (int) $previous['lead_minutes'] !== $fields[7];
            $sent = $scheduleChanged ? null : $previous['telegram_sent_at_utc'];
            $st = $pdo->prepare('UPDATE study_reminders SET title = ?, scheduled_at_utc = ?, time_zone = ?,
                delivery_method = ?, note_id = ?, description = ?, duration_minutes = ?, lead_minutes = ?,
                telegram_sent_at_utc = ? WHERE id = ? AND user_id = ?');
            $st->execute([...$fields, $sent, $id, $uid]);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function delete_study_reminder(int $uid, int $id): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$uid]);
        $st = $pdo->prepare('UPDATE study_reminders SET cancelled_at_utc = COALESCE(cancelled_at_utc, UTC_TIMESTAMP())
            WHERE id = ? AND user_id = ?');
        $st->execute([$id, $uid]);
        if (!$st->rowCount()) throw new ReminderException(404, 'Reminder not found.');
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function reminder_fields_from_row(array $row): array
{
    return [$row['title'], $row['scheduled_at_utc'], $row['time_zone'], $row['delivery_method'],
        $row['note_id'], $row['description'] ?? '', (int) $row['duration_minutes'], (int) $row['lead_minutes']];
}