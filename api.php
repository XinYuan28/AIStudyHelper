<?php
/* JSON API used by studyhub.js.
   GET  api.php?action=list                      -> the user's notes (with file names) and folders
   GET  api.php?action=get&id=N                  -> one note's saved content
   POST api.php?action=create                    -> title, uploads[] (1-20 files, up to 100 MB), [folder_id or folder_name]
   POST api.php?action=create_folder             -> folder_name
   POST api.php?action=move_note                 -> id, folder_id (empty for Unsorted)
   POST api.php?action=rename                    -> id, title
   POST api.php?action=delete                    -> id            (soft delete: sets notes.deleted_at)
   POST api.php?action=save_notes                -> id, content, mode, [as_new, title]
   POST api.php?action=save_quiz                 -> note_id, total, correct, points, best_streak, difficulty, qtype, weak_areas
   GET  api.php?action=list_reminders
   GET  api.php?action=download_reminder_calendar&id=N -> iCalendar event with an alert
   POST api.php?action=create_reminder           -> title, scheduled_at, time_zone, delivery_method
   POST api.php?action=edit_reminder             -> id and the same fields as create_reminder
   POST api.php?action=cancel_reminder           -> id
   Reminder scheduled_at format: YYYY-MM-DDTHH:MM:SS+HH:MM (use +00:00 for UTC).
   Every POST needs the csrf token (same csrf_ok() the login form uses). */

ini_set('display_errors', '0'); // warnings must never break the JSON (set before anything is loaded)

require_once __DIR__ . '/auth.php';   // also loads db.php
require_once __DIR__ . '/extract.php';
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/quiz_backend.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/reminders_backend.php';
require_once __DIR__ . '/trash_backend.php';
require_once __DIR__ . '/folders_backend.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data = []): void
{
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// real errors go to the PHP error log; the browser only gets a friendly message
set_exception_handler(function (Throwable $e) {
    if ($e instanceof ReminderException) fail($e->status, $e->getMessage());
    error_log('[api.php] ' . $e->getMessage());
    fail(500, 'DEBUG: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
});

if (!is_logged_in()) {
    fail(401, 'Please log in again.');
}
$uid = current_user_id();
if ($uid < 1) {
    fail(401, 'Please log in again.');
}

$action = $_GET['action'] ?? '';
$writes = ['create', 'rename', 'delete', 'save_notes', 'save_quiz', 'generate_custom_quiz', 'grade_written',
    'create_folder', 'move_note', 'delete_folder_notes', 'delete_folder',
    'create_reminder', 'edit_reminder', 'cancel_reminder', 'delete_reminder',
    'connect_telegram', 'disconnect_telegram', 'restore_note', 'permanently_delete_note'];
if ($action === 'create' && empty($_FILES) && empty($_POST)
    && ($postLimit = php_size_bytes((string) ini_get('post_max_size'))) > 0
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $postLimit) {
    fail(413, 'The entire batch was rejected. Re-upload all files at smaller sizes.');
}
if (in_array($action, $writes, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail(405, 'Use POST.');
    }
    if (!csrf_ok()) {
        fail(403, 'Your session expired. Please refresh the page.');
    }
}

const ALLOWED_FILE = '/\.(pdf|docx|pptx|xlsx|odt|rtf|txt|md|csv|mp3|m4a|wav)$/i';
const MAX_FILES = 20;       // safety cap on files per note (same number as studyhub.js)
const MAX_TOTAL_MB = 100;   // all files of one note together (same number as studyhub.js)
const MAX_CONTENT = 200000; // characters of notes text

function clean_title(string $t): string
{
    return mb_substr(trim($t), 0, 255);
}

function folder_name(): string
{
    $value = $_POST['folder_name'] ?? null;
    $name = is_string($value) ? trim($value) : '';
    if ($name === '' || mb_strlen($name) > 100) {
        fail(400, 'Enter a folder name of up to 100 characters.');
    }
    return $name;
}

function create_folder(PDO $pdo, int $uid, string $name): int
{
    $st = $pdo->prepare('INSERT INTO folders (user_id, name) VALUES (?, ?)');
    $st->execute([$uid, $name]);
    return (int) $pdo->lastInsertId();
}

function selected_folder(int $uid): ?int
{
    if (($_POST['folder_id'] ?? '') === '') {
        return null;
    }
    $id = int_in('folder_id', 1, PHP_INT_MAX);
    $st = db()->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
    $st->execute([$id, $uid]);
    if (!$st->fetchColumn()) {
        fail(404, 'That folder could not be found.');
    }
    return $id;
}

function int_in(string $key, int $min, int $max): int
{
    $v = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    if ($v === false || $v === null || $v < $min || $v > $max) {
        fail(400, 'Invalid value for ' . $key . '.');
    }
    return $v;
}

// the note must belong to this user and not be deleted
function own_note(int $id, int $uid): ?array
{
    $st = db()->prepare('SELECT id, title, content FROM notes WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
    $st->execute([$id, $uid]);
    return $st->fetch() ?: null;
}

function reminder_fields(): array
{
    $title = $_POST['title'] ?? null;
    $method = $_POST['delivery_method'] ?? null;
    $time = $_POST['scheduled_at'] ?? null;
    $zone = $_POST['time_zone'] ?? null;
    $noteId = null;
    if (isset($_POST['note_id']) && $_POST['note_id'] !== '') {
        $noteId = int_in('note_id', 1, PHP_INT_MAX);
        $note = own_note($noteId, current_user_id());
        if (!$note) {
            fail(404, 'Study note not found.');
        }
        $title = $note['title'];
    }
    $description = $_POST['description'] ?? '';
    if (!is_string($description) || !mb_check_encoding($description, 'UTF-8') || mb_strlen($description, 'UTF-8') > 2000) {
        fail(400, 'Keep the reminder note within 2000 characters.');
    }

    if (!is_string($title) || trim($title) === '' || !mb_check_encoding($title, 'UTF-8')
        || mb_strlen(trim($title), 'UTF-8') > 255) {
        fail(400, 'Enter a reminder title of up to 255 characters.');
    }
    if (!in_array($method, ['calendar', 'telegram'], true)) {
        fail(400, 'Choose Calendar or Telegram.');
    }
    if ($method === 'telegram') {
        if (!telegram_configured()) {
            fail(503, 'Telegram reminders are awaiting bot setup.');
        }
        $connection = db()->prepare('SELECT user_id FROM telegram_connections WHERE user_id = ?');
        $connection->execute([current_user_id()]);
        if (!$connection->fetchColumn()) {
            fail(400, 'Connect your Telegram account before saving a Telegram reminder.');
        }
    }
    if (!is_string($zone) || !in_array($zone, DateTimeZone::listIdentifiers(), true)) {
        fail(400, 'Choose a valid time zone.');
    }
    if (!is_string($time) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}[+-][0-9]{2}:[0-9]{2}\z/', $time)) {
        fail(400, 'Enter a date and time with its UTC offset.');
    }
    $format = 'Y-m-d\TH:i:sP';
    $date = DateTimeImmutable::createFromFormat('!' . $format, $time);
    if (!$date || $date->setTimezone(new DateTimeZone($zone))->format($format) !== $time) {
        fail(400, 'The date, time, and UTC offset must match the selected time zone.');
    }
    $utc = $date->setTimezone(new DateTimeZone('UTC'));
    if ($utc->getTimestamp() <= time() || (int) $utc->format('Y') > 9999) {
        fail(400, 'Choose a future reminder date within the supported range.');
    }
    $duration = isset($_POST['duration_minutes']) ? int_in('duration_minutes', 1, 2147483647) : 30;
    if ((int) $utc->modify('+' . $duration . ' minutes')->format('Y') > 9999) {
        fail(400, 'Choose a shorter study period.');
    }
    $lead = isset($_POST['lead_minutes']) ? int_in('lead_minutes', 0, 60) : 0;
    if (!in_array($lead, [0, 5, 15, 30, 60], true)) fail(400, 'Choose a valid reminder time.');
    return [trim($title), $utc->format('Y-m-d H:i:s'), $zone, $method, $noteId, trim($description), $duration, $method === 'telegram' ? $lead : 0];
}

function reminder_calendar_file(int $id, string $title, string $scheduledAtUtc, string $description = '', int $duration = 30): string
{
    $start = new DateTimeImmutable($scheduledAtUtc, new DateTimeZone('UTC'));
    $title = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $title);
    $title = strtr($title, ["\\" => "\\\\", "\r\n" => "\\n", "\r" => "\\n", "\n" => "\\n",
        ';' => '\\;', ',' => '\\,']);
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//AI Study Helper//Study Reminders//EN',
        'BEGIN:VEVENT',
        'UID:study-reminder-' . $id . '@aistudyhelper',
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . $start->format('Ymd\THis\Z'),
        'DTEND:' . $start->modify('+' . $duration . ' minutes')->format('Ymd\THis\Z'),
        'SUMMARY:' . $title,
        'DESCRIPTION:' . strtr(
            preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $description),
            ["\\" => "\\\\", "\r\n" => "\\n", "\r" => "\\n", "\n" => "\\n", ';' => '\\;', ',' => '\\,']
        ),
        'BEGIN:VALARM',
        'ACTION:DISPLAY',
        'TRIGGER:PT0S',
        'DESCRIPTION:' . $title,
        'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR',
    ];

    $calendar = '';
    foreach ($lines as $line) {
        while (strlen($line) > 75) {
            $part = mb_strcut($line, 0, 75, 'UTF-8');
            $calendar .= $part . "\r\n";
            $line = ' ' . substr($line, strlen($part));
        }
        $calendar .= $line . "\r\n";
    }
    return $calendar;
}

switch ($action) {

    case 'list_trash':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail(405, 'Use GET.');
        $sort = $_GET['sort'] ?? 'newest';
        if (!in_array($sort, ['newest', 'oldest'], true)) fail(400, 'Choose a valid deletion-date sort order.');
        out(['notes' => list_trashed_notes($uid, $sort)]);

    case 'restore_note':
        $id = int_in('id', 1, PHP_INT_MAX);
        if (!restore_trashed_note($uid, $id)) {
            fail(404, 'This note is no longer in your Trash or its 10-day retention period has ended.');
        }
        out(['id' => $id]);

    case 'permanently_delete_note':
        $id = int_in('id', 1, PHP_INT_MAX);
        if (!permanently_delete_trashed_note($uid, $id)) {
            fail(404, 'This note is no longer in your Trash.');
        }
        out(['id' => $id]);


    case 'telegram_status':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            fail(405, 'Use GET.');
        }
        if (!telegram_configured()) {
            out(['connected' => false, 'configured' => false]);
        }
        $st = db()->prepare('SELECT user_id FROM telegram_connections WHERE user_id = ?');
        $st->execute([$uid]);
        out(['connected' => (bool) $st->fetchColumn(), 'configured' => true]);

    case 'connect_telegram':
        if (!telegram_configured()) {
            fail(503, 'Telegram reminders are awaiting bot setup.');
        }
        $token = bin2hex(random_bytes(24));
        $st = db()->prepare('INSERT INTO telegram_link_tokens (token_hash, user_id, expires_at_utc)
            VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL 10 MINUTE)
            ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at_utc = VALUES(expires_at_utc)');
        $st->execute([hash('sha256', $token), $uid]);
        out(['url' => 'https://t.me/' . rawurlencode(telegram_settings()['username']) . '?start=' . $token]);

    case 'disconnect_telegram':
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM telegram_link_tokens WHERE user_id = ?')->execute([$uid]);
            $pdo->prepare('DELETE FROM telegram_connections WHERE user_id = ?')->execute([$uid]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        out();

    case 'download_reminder_calendar':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            fail(405, 'Use GET.');
        }
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            fail(400, 'Invalid reminder ID.');
        }
        $st = db()->prepare(
            "SELECT title, scheduled_at_utc, description, duration_minutes FROM study_reminders
             WHERE id = ? AND user_id = ? AND delivery_method = 'calendar' AND cancelled_at_utc IS NULL"
        );
        $st->execute([$id, $uid]);
        $reminder = $st->fetch();
        if (!$reminder) {
            fail(404, 'Calendar reminder not found.');
        }
        $calendar = reminder_calendar_file($id, $reminder['title'], $reminder['scheduled_at_utc'], $reminder['description'] ?? '', (int) $reminder['duration_minutes']);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="study-reminder-' . $id . '.ics"');
        echo $calendar;
        exit;

    case 'list_reminders':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            fail(405, 'Use GET.');
        }
        $st = db()->prepare(
            "SELECT id, title, DATE_FORMAT(scheduled_at_utc, '%Y-%m-%dT%H:%i:%sZ') AS scheduled_at,
                    time_zone, delivery_method, telegram_sent_at_utc, note_id, description, duration_minutes, lead_minutes
             FROM study_reminders WHERE user_id = ? AND cancelled_at_utc IS NULL
             ORDER BY scheduled_at_utc, id"
        );
        $st->execute([$uid]);
        out(['reminders' => $st->fetchAll()]);

    case 'create_reminder':
        $fields = reminder_fields();
        $id = save_study_reminder($fields, $uid);
        http_response_code(201);
        out(['id' => $id]);

    case 'edit_reminder':
        $id = int_in('id', 1, PHP_INT_MAX);
        $fields = reminder_fields();
        save_study_reminder($fields, $uid, $id);
        out(['id' => $id]);

    case 'cancel_reminder':
    case 'delete_reminder':
        $id = int_in('id', 1, PHP_INT_MAX);
        delete_study_reminder($uid, $id);
        out(['id' => $id]);

    case 'list':
        $st = db()->prepare(
            'SELECT id, title, folder_id, UNIX_TIMESTAMP(updated_at) AS ts
               FROM notes
              WHERE user_id = ? AND deleted_at IS NULL
              ORDER BY updated_at DESC, id DESC'
        );
        $st->execute([$uid]);
        $notes = $st->fetchAll();

        $byNote = [];
        if ($notes) {
            $ids = array_column($notes, 'id');
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $fs  = db()->prepare("SELECT note_id, original_name FROM note_files WHERE note_id IN ($in) ORDER BY id");
            $fs->execute($ids);
            foreach ($fs->fetchAll() as $f) {
                $byNote[$f['note_id']][] = $f['original_name'];
            }
        }
        foreach ($notes as &$n) {
            $n['id']    = (int) $n['id'];
            $n['folder_id'] = $n['folder_id'] === null ? null : (int) $n['folder_id'];
            $n['ts']    = (int) $n['ts'];
            $n['files'] = $byNote[$n['id']] ?? [];
        }
        unset($n);
        $st = db()->prepare(
            'SELECT f.id, f.name, COUNT(n.id) AS note_count,
                    UNIX_TIMESTAMP(GREATEST(f.created_at, COALESCE(MAX(n.updated_at), f.created_at))) AS ts
               FROM folders f
               LEFT JOIN notes n ON n.folder_id = f.id AND n.user_id = f.user_id AND n.deleted_at IS NULL
              WHERE f.user_id = ?
              GROUP BY f.id, f.name, f.created_at
              ORDER BY ts DESC, f.id DESC'
        );
        $st->execute([$uid]);
        $folders = array_map(static fn (array $f): array => [
            'id' => (int) $f['id'],
            'name' => $f['name'],
            'note_count' => (int) $f['note_count'],
            'ts' => (int) $f['ts'],
        ], $st->fetchAll());
        out(['notes' => $notes, 'folders' => $folders]);

    case 'delete_folder_notes':
    case 'delete_folder':
        $folderId = int_in('folder_id', 1, PHP_INT_MAX);
        $deleted = delete_folder_notes($uid, $folderId, $action === 'delete_folder');
        out(['deleted_count' => $deleted]);

    case 'create_folder':
        $name = folder_name();
        try {
            $id = create_folder(db(), $uid, $name);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                fail(409, 'A folder with that name already exists.');
            }
            throw $e;
        }
        out(['id' => $id]);

    case 'move_note':
        $id = int_in('id', 1, PHP_INT_MAX);
        $folderId = selected_folder($uid);
        $st = db()->prepare('UPDATE notes SET folder_id = ? WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
        $st->execute([$folderId, $id, $uid]);
        if (!$st->rowCount() && !own_note($id, $uid)) {
            fail(404, 'That note could not be found.');
        }
        out();

    case 'generate_custom_quiz':
        set_time_limit(900);
        $noteId=int_in('note_id',1,PHP_INT_MAX);$note=own_note($noteId,$uid);
        if(!$note)fail(404,'That note could not be found.');
        $count=int_in('count',5,30);$difficulty=(string)($_POST['difficulty']??'medium');
        if(!in_array($difficulty,['easy','medium','hard'],true))fail(400,'Choose a valid difficulty.');
        try{$questions=generate_custom_questions((string)$note['content'],$count,$difficulty);}
        catch(AiException $e){fail(502,'Could not generate the complete unique question set. Please retry.');}
        $key=bin2hex(random_bytes(16));
        foreach(($_SESSION['quiz_runs']??[]) as $id=>$run)if($run['expires']<time())unset($_SESSION['quiz_runs'][$id]);
        if(count($_SESSION['quiz_runs']??[])>=5)array_shift($_SESSION['quiz_runs']);
        $_SESSION['quiz_runs'][$key]=['note_id'=>$noteId,'questions'=>$questions,'expires'=>time()+7200,'grades'=>[]];
        foreach($questions as $i=>&$q){$q['quiz_id']=$key;$q['question_index']=$i;}unset($q);
        out(['questions'=>$questions]);

    case 'grade_written':
        set_time_limit(150);
        $key=(string)($_POST['quiz_id']??'');$index=int_in('question_index',0,29);
        $run=$_SESSION['quiz_runs'][$key]??null;
        if(!$run||$run['expires']<time()||!isset($run['questions'][$index]))fail(409,'This quiz expired. Generate a new quiz.');
        if(!own_note($run['note_id'],$uid))fail(404,'That note could not be found.');
        $typed=$_POST['answer']??'';
        if(!is_string($typed)||trim($typed)===''||mb_strlen($typed)>4000)fail(400,'Enter an answer of up to 4000 characters.');
        $cache=$index.':'.hash('sha256',$typed);
        if(isset($run['grades'][$cache]))out($run['grades'][$cache]);
        try{$grade=grade_written_answer($run['questions'][$index],trim($typed));}
        catch(AiException $e){fail(502,'Could not mark this answer right now. Please try Check again.');}
        $_SESSION['quiz_runs'][$key]['grades'][$cache]=$grade;out($grade);

    case 'quiz_history':
        $st = db()->prepare(
            'SELECT topic, correct_answers, total_questions, points, marks_earned, marks_possible, created_at
             FROM quiz_results WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 20'
        );
        $st->execute([$uid]);
        out(['results' => $st->fetchAll()]);

    case 'get':
        $id = (int) ($_GET['id'] ?? 0);
        $st = db()->prepare('SELECT content, mode FROM notes WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
        $st->execute([$id, $uid]);
        $row = $st->fetch();
        if (!$row) {
            fail(404, 'That note could not be found.');
        }
        $quizStmt = db()->prepare('SELECT questions FROM note_quizzes WHERE note_id = ?');
        $quizStmt->execute([$id]);
        $savedQuestions = $quizStmt->fetchColumn();
        out([
            'content' => $row['content'],
            'mode' => $row['mode'],
            'questions' => $savedQuestions ? (json_decode($savedQuestions, true) ?: []) : [],
        ]);

    case 'create':
        $folderId = selected_folder($uid);
        $folderName = array_key_exists('folder_name', $_POST) ? folder_name() : null;
        if ($folderId !== null && $folderName !== null) {
            fail(400, 'Choose one destination for your notes.');
        }
        $uploads = $_FILES['uploads'] ?? null;
        if (!$uploads || !is_array($uploads['name'] ?? null)) {
            fail(400, 'Please choose at least one study file.');
        }
        $uploadCount = count($uploads['name']);
        if ($uploadCount < 1 || $uploadCount > MAX_FILES) {
            fail(400, 'Please choose between 1 and ' . MAX_FILES . ' files.');
        }
        $clean = [];
        $aiFiles = [];
        $allowedMimes = [
            'pdf' => 'application/pdf', 'txt' => 'text/plain', 'md' => 'text/plain',
            'csv' => 'text/csv', 'rtf' => 'text/rtf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav',
        ];
        foreach ($uploads['error'] as $i => $error) {
            if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                || ($uploads['size'][$i] ?? 0) > upload_file_limit()) {
                fail(413, 'A file exceeded the size limit. Re-upload the entire batch at smaller sizes.');
            }
        }
        if (array_sum($uploads['size']) > MAX_TOTAL_MB * 1024 ** 2) {
            fail(413, 'The batch exceeded ' . MAX_TOTAL_MB . ' MB. Re-upload all files at smaller sizes.');
        }
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($uploads['name'] as $i => $originalName) {
            $name = mb_substr(basename(str_replace('\\', '/', (string) $originalName)), 0, 255);
            $tmpName = (string) ($uploads['tmp_name'][$i] ?? '');
            $size = (int) ($uploads['size'][$i] ?? 0);
            $error = (int) ($uploads['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($error !== UPLOAD_ERR_OK || $name === '' || !preg_match(ALLOWED_FILE, $name) || !is_uploaded_file($tmpName)) {
                fail(400, 'One of the selected files could not be uploaded.');
            }
            if (in_array($ext, ['docx', 'pptx', 'xlsx', 'odt'], true) && !class_exists('ZipArchive')) {
                fail(503, 'Office document uploads require the PHP ZIP extension.');
            }
            $mime = $fileInfo->file($tmpName);
            if ($ext === 'pdf' || in_array($ext, ['mp3', 'm4a', 'wav'], true)) {
                if (($ext === 'pdf' && $mime !== 'application/pdf')
                    || ($ext !== 'pdf' && !str_starts_with((string) $mime, 'audio/')
                        && !($ext === 'm4a' && $mime === 'video/mp4'))) {
                    fail(400, $name . ' does not match its file extension.');
                }
                $payload = file_get_contents($tmpName);
                if ($payload === false || $payload === '') {
                    fail(400, 'One of the selected files could not be read.');
                }
                $aiMime = $allowedMimes[$ext];
            } else {
                $extracted = extract_text($tmpName, $ext);
                if ($extracted['text'] === '') {
                    fail(400, $name . ' does not contain readable text.');
                }
                $payload = $extracted['text'];
                $aiMime = 'text/plain';
            }
            $clean[] = [$name, max(0, $size)];
            $aiFiles[] = ['name' => $name, 'mime' => $aiMime, 'data' => $payload];
        }
        $title = clean_title((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $title = pathinfo($clean[0][0], PATHINFO_FILENAME) ?: 'Untitled notes';
        }

        try {
            $result = ai_generate($aiFiles);
        } catch (AiException $e) {
            fail(502, $e->getMessage());
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($folderName !== null) {
                $folderId = create_folder($pdo, $uid, $folderName);
            }
            $pdo->prepare("INSERT INTO notes (user_id, title, content, mode, folder_id) VALUES (?, ?, ?, 'general', ?)")
                ->execute([$uid, $title, $result['notes'], $folderId]);
            $noteId = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO note_files (note_id, original_name, size_bytes) VALUES (?, ?, ?)');
            foreach ($clean as [$name, $size]) {
                $ins->execute([$noteId, $name, $size]);
            }
            $quizStmt = $pdo->prepare('INSERT INTO note_quizzes (note_id, questions) VALUES (?, ?)');
            $quizStmt->execute([$noteId, json_encode($result['questions'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062 && $folderName !== null) {
                fail(409, 'A folder with that name already exists.');
            }
            throw $e;
        }
        out(['id' => $noteId, 'title' => $title, 'folder_id' => $folderId, 'files' => array_column($clean, 0), 'notes' => $result['notes'], 'questions' => $result['questions'], 'ts' => time()]);

    case 'rename':
        $id    = int_in('id', 1, PHP_INT_MAX);
        $title = clean_title((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            fail(400, 'The name can not be empty.');
        }
        $st = db()->prepare('UPDATE notes SET title = ? WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
        $st->execute([$title, $id, $uid]);
        if ($st->rowCount() < 1) {
            fail(404, 'That note could not be found.');
        }
        out(['title' => $title, 'ts' => time()]);

    case 'delete':
        $id = int_in('id', 1, PHP_INT_MAX);
        $st = db()->prepare('UPDATE notes SET deleted_at = NOW() WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
        $st->execute([$id, $uid]);
        if ($st->rowCount() < 1) {
            fail(404, 'That note could not be found.');
        }
        out();

    case 'save_notes':
        $content = (string) ($_POST['content'] ?? '');
        $mode    = ($_POST['mode'] ?? '') === 'personalised' ? 'personalised' : 'general';
        if (trim($content) === '' || mb_strlen($content) > MAX_CONTENT) {
            fail(400, 'The notes are empty or too long to save.');
        }
        $id   = int_in('id', 1, PHP_INT_MAX);
        $note = own_note($id, $uid);
        if (!$note) {
            fail(404, 'That note could not be found.');
        }

        if (!empty($_POST['as_new'])) {
            // notes made from quiz mistakes are saved as their own note, so the full notes are never overwritten
            $title = clean_title((string) ($_POST['title'] ?? '')) ?: clean_title($note['title'] . ' (focus notes)');
            db()->prepare("INSERT INTO notes (user_id, title, content, mode) VALUES (?, ?, ?, 'personalised')")
                ->execute([$uid, $title, $content]);
            out(['id' => (int) db()->lastInsertId(), 'title' => $title, 'ts' => time()]);
        }

        db()->prepare('UPDATE notes SET content = ?, mode = ? WHERE id = ? AND user_id = ?')
            ->execute([$content, $mode, $id, $uid]);
        out(['id' => $id, 'ts' => time()]);

    case 'save_quiz':
        $attemptKey=$_POST['attempt_key']??'';
        if(!is_string($attemptKey)||($attemptKey!==''&&!preg_match('/^[a-f0-9]{32}$/D',$attemptKey)))fail(400,'Invalid quiz attempt.');
        if($attemptKey!=='' && isset($_SESSION['saved_quiz_attempts'][$uid][$attemptKey]))out(['id'=>$_SESSION['saved_quiz_attempts'][$uid][$attemptKey]]);
        $noteId = int_in('note_id', 1, PHP_INT_MAX);
        $note   = own_note($noteId, $uid);
        if (!$note) {
            fail(404, 'That note could not be found.');
        }
        $total   = int_in('total', 1, 100);
        $correct = int_in('correct', 0, $total);
        $points  = int_in('points', 0, 1000000);
        $marksPossible=$total*10;
        $marksEarned=isset($_POST['marks_earned'])?int_in('marks_earned',0,$marksPossible):$correct*10;
        $streak  = int_in('best_streak', 0, $total);
        $diff    = strtolower((string) ($_POST['difficulty'] ?? ''));
        $diff    = in_array($diff, ['easy', 'medium', 'hard'], true) ? $diff : 'medium';
        $qtype = $_POST['qtype'] ?? 'mcq';
        if (!in_array($qtype, ['mcq', 'text', 'dual'], true)) fail(400, 'Choose a valid question type.');
        $sections = isset($_POST['sections']) ? int_in('sections', 1, 5) : 1;
        if ($sections > $total) fail(400, 'Choose no more sections than questions.');
        $weak    = mb_substr(trim((string) ($_POST['weak_areas'] ?? '')), 0, 2000);

        db()->prepare(
            'INSERT INTO quiz_results
                (user_id, note_id, topic, difficulty, question_type, total_questions, correct_answers, points, best_streak, weak_areas, section_count, marks_earned, marks_possible)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$uid, $noteId, mb_substr($note['title'], 0, 255), $diff, $qtype, $total, $correct, $points, $streak, $weak === '' ? null : $weak, $sections, $marksEarned, $marksPossible]);
        $savedId=(int)db()->lastInsertId();
        if($attemptKey!==''){
            $_SESSION['saved_quiz_attempts'][$uid][$attemptKey]=$savedId;
            if(count($_SESSION['saved_quiz_attempts'][$uid])>100)array_shift($_SESSION['saved_quiz_attempts'][$uid]);
        }
        out(['id' => $savedId]);

    default:
        fail(400, 'Unknown action.');
}
