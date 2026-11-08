<?php
/* JSON API used by studyhub.js.
   GET  api.php?action=list                      -> the user's notes (with file names)
   GET  api.php?action=get&id=N                  -> one note's saved content
    POST api.php?action=create                    -> title, uploads[] (real files), 1-20 files, all together up to 100 MB
   POST api.php?action=rename                    -> id, title
   POST api.php?action=delete                    -> id            (soft delete: sets notes.deleted_at)
   POST api.php?action=save_notes                -> id, content, mode, [as_new, title]
   POST api.php?action=save_quiz                 -> note_id, total, correct, points, best_streak, difficulty, qtype, weak_areas
   Every POST needs the csrf token (same csrf_ok() the login form uses). */

ini_set('display_errors', '0'); // warnings must never break the JSON (set before anything is loaded)

require_once __DIR__ . '/auth.php';   // also loads db.php
require_once __DIR__ . '/extract.php';
require_once __DIR__ . '/ai.php';

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
    error_log('[api.php] ' . $e->getMessage());
    fail(500, 'Something went wrong on the server. Please try again.');
});

if (!is_logged_in()) {
    fail(401, 'Please log in again.');
}
$uid = current_user_id();
if ($uid < 1) {
    fail(401, 'Please log in again.');
}

$action = $_GET['action'] ?? '';
$writes = ['create', 'rename', 'delete', 'save_notes', 'save_quiz'];
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
    $st = db()->prepare('SELECT id, title FROM notes WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
    $st->execute([$id, $uid]);
    return $st->fetch() ?: null;
}

switch ($action) {

    case 'list':
        $st = db()->prepare(
            'SELECT id, title, UNIX_TIMESTAMP(updated_at) AS ts
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
            $n['ts']    = (int) $n['ts'];
            $n['files'] = $byNote[$n['id']] ?? [];
        }
        unset($n);
        out(['notes' => $notes]);

    case 'quiz_history':
        $st = db()->prepare(
            'SELECT topic, correct_answers, total_questions, points, created_at
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
            $pdo->prepare("INSERT INTO notes (user_id, title, content, mode) VALUES (?, ?, ?, 'general')")->execute([$uid, $title, $result['notes']]);
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
            throw $e;
        }
        out(['id' => $noteId, 'title' => $title, 'files' => array_column($clean, 0), 'notes' => $result['notes'], 'questions' => $result['questions'], 'ts' => time()]);

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
        $noteId = int_in('note_id', 1, PHP_INT_MAX);
        $note   = own_note($noteId, $uid);
        if (!$note) {
            fail(404, 'That note could not be found.');
        }
        $total   = int_in('total', 1, 100);
        $correct = int_in('correct', 0, $total);
        $points  = int_in('points', 0, 1000000);
        $streak  = int_in('best_streak', 0, $total);
        $diff    = strtolower((string) ($_POST['difficulty'] ?? ''));
        $diff    = in_array($diff, ['easy', 'medium', 'hard'], true) ? $diff : 'medium';
        $qtype   = ($_POST['qtype'] ?? '') === 'text' ? 'text' : 'mcq';
        $weak    = mb_substr(trim((string) ($_POST['weak_areas'] ?? '')), 0, 2000);

        db()->prepare(
            'INSERT INTO quiz_results
                (user_id, note_id, topic, difficulty, question_type, total_questions, correct_answers, points, best_streak, weak_areas)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$uid, $noteId, mb_substr($note['title'], 0, 255), $diff, $qtype, $total, $correct, $points, $streak, $weak === '' ? null : $weak]);
        out(['id' => (int) db()->lastInsertId()]);

    default:
        fail(400, 'Unknown action.');
}