<?php
require_once __DIR__ . '/reminders_backend.php';

function telegram_command_help(): string
{
    return "Study reminder commands:
"
        . "/reminders — future reminders (use /reminders 2 for the next page)
"
        . "/lead ID 60 — notify 60, 30, 15, 5 or 0 minutes before a Telegram session
"
        . "/edit ID YYYY-MM-DD HH:MM — change the study start time
"
        . "/delete ID — delete a reminder

"
        . "Use the reminder ID from /reminders. Times use the device time zone saved with that reminder.";
}

function telegram_reminder_command(int $uid, string $text): string
{
    if (!preg_match('~^/([a-z]+)(?:@[a-z0-9_]+)?(?:[[:space:]]+(.*))?$~is', trim($text), $match)) {
        return 'Use /help to see the study reminder commands.';
    }
    $command = strtolower($match[1]);
    $args = trim($match[2] ?? '');
    if ($command === 'help' || $command === 'start') return telegram_command_help();
    if ($command === 'reminders') {
        $page = $args === '' ? 1 : filter_var($args, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        if ($page === false) return 'Use /reminders or /reminders 2.';
        $st = db()->prepare('SELECT * FROM study_reminders WHERE user_id = ? AND cancelled_at_utc IS NULL
            AND scheduled_at_utc > UTC_TIMESTAMP() ORDER BY scheduled_at_utc, id LIMIT 6 OFFSET ' . (($page - 1) * 5));
        $st->execute([$uid]);
        $rows = $st->fetchAll();
        if (!$rows) return 'No future study reminders on this page.';
        $more = count($rows) > 5;
        $lines = ["Future study reminders — page " . $page];
        foreach (array_slice($rows, 0, 5) as $row) {
            $start = (new DateTimeImmutable($row['scheduled_at_utc'], new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone($row['time_zone']));
            $lines[] = '#' . $row['id'] . ' · ' . $row['title'] . "
" . $start->format('j M Y, H:i')
                . ' · ' . $row['time_zone'] . "
" . $row['duration_minutes'] . ' minutes · '
                . ($row['delivery_method'] === 'telegram' ? 'Telegram · ' . $row['lead_minutes'] . ' minutes before' : 'Calendar');
        }
        if ($more) $lines[] = 'Next page: /reminders ' . ($page + 1);
        return implode("

", $lines);
    }
    if (!in_array($command, ['lead', 'delete', 'edit'], true)) return telegram_command_help();
    $parts = preg_split('/[[:space:]]+/', $args);
    $id = filter_var($parts[0] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) return 'Enter a reminder ID from /reminders. Use /help for command examples.';
    $st = db()->prepare('SELECT * FROM study_reminders WHERE id = ? AND user_id = ? AND cancelled_at_utc IS NULL');
    $st->execute([$id, $uid]);
    $row = $st->fetch();
    if (!$row) return 'Reminder not found in your account.';
    try {
        if ($command === 'delete') {
            if (count($parts) !== 1) return 'Use /delete ID.';
            delete_study_reminder($uid, $id);
            return 'Reminder #' . $id . ' deleted.'
                . ($row['delivery_method'] === 'calendar' ? ' Remove any exported copy from your calendar too.' : '');
        }
        $fields = reminder_fields_from_row($row);
        if ($command === 'lead') {
            if (count($parts) !== 2 || !in_array($parts[1], ['0', '5', '15', '30', '60'], true)) {
                return 'Use /lead ID 60, 30, 15, 5 or 0.';
            }
            if ($row['delivery_method'] !== 'telegram') return 'Advance Telegram alerts apply to Telegram reminders. Choose a Telegram reminder ID.';
            $fields[7] = (int) $parts[1];
            save_study_reminder($fields, $uid, $id);
            return 'Reminder #' . $id . ' will notify you ' . ($fields[7] ? $fields[7] . ' minutes before study starts.' : 'when study starts.');
        }
        if (count($parts) !== 3) return 'Use /edit ID YYYY-MM-DD HH:MM, for example /edit ' . $id . ' 2026-12-01 19:00.';
        $input = $parts[1] . ' ' . $parts[2];
        $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $input, new DateTimeZone($row['time_zone']));
        if (!$start || $start->format('Y-m-d H:i') !== $input) return 'Enter a valid date and time in YYYY-MM-DD HH:MM format.';
        $fields[1] = $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        save_study_reminder($fields, $uid, $id);
        return 'Reminder #' . $id . ' moved to ' . $start->format('j M Y, H:i') . ' (' . $row['time_zone'] . ').'
            . ($row['delivery_method'] === 'calendar' ? ' Update the exported calendar event too.' : '');
    } catch (ReminderException $error) {
        return $error->getMessage();
    }
}