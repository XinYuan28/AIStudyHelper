<?php
/* JSON API used by studyhub.js.
   GET  api.php?action=list                      -> the user's notes (with file names)
   GET  api.php?action=get&id=N                  -> one note's saved content
   POST api.php?action=create                    -> title, files (JSON [{name,size}]), 1-20 files, all together up to 100 MB
   POST api.php?action=rename                    -> id, title
   POST api.php?action=delete                    -> id            (soft delete: sets notes.deleted_at)
   POST api.php?action=save_notes                -> id, content, mode, [as_new, title]
   POST api.php?action=save_quiz                 -> note_id, total, correct, points, best_streak, difficulty, qtype, weak_areas
   Every POST needs the csrf token (same csrf_ok() the login form uses). */

require 'auth.php';
require 'db.php';

ini_set('display_errors', '0'); // warnings must never break the JSON
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
if (in_array($action, $writes, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail(405, 'Use POST.');
    }
    if (!csrf_ok()) {
        fail(403, 'Your session expired. Please refresh the page.');
    }
}

const ALLOWED_FILE = '/\.(pdf|docx?|pptx?|txt|mp3|m4a|wav)$/i';
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

    case 'get':
        $id = (int) ($_GET['id'] ?? 0);
        $st = db()->prepare('SELECT content, mode FROM notes WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
        $st->execute([$id, $uid]);
        $row = $st->fetch();
        if (!$row) {
            fail(404, 'That note could not be found.');
        }
        out(['content' => $row['content'], 'mode' => $row['mode']]);

    case 'create':
        $files = json_decode((string) ($_POST['files'] ?? '[]'), true);
        if (!is_array($files) || count($files) < 1 || count($files) > MAX_FILES) {
            fail(400, 'Please choose between 1 and ' . MAX_FILES . ' files.');
        }
        $clean = [];
        foreach ($files as $f) {
            $name = mb_substr(basename(str_replace('\\', '/', (string) ($f['name'] ?? ''))), 0, 255);
            if ($name === '' || !preg_match(ALLOWED_FILE, $name)) {
                fail(400, 'Only PDF, Word, PowerPoint, text and audio files (mp3, m4a, wav) are supported.');
            }
            $clean[] = [$name, max(0, (int) ($f['size'] ?? 0))];
        }
        // the sizes are what the browser reports; when real file uploads are added, check the uploaded sizes here too
        if (array_sum(array_column($clean, 1)) > MAX_TOTAL_MB * 1024 * 1024) {
            fail(400, 'Your files add up to more than ' . MAX_TOTAL_MB . ' MB. Please pick fewer or smaller files.');
        }
        $title = clean_title((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $title = pathinfo($clean[0][0], PATHINFO_FILENAME) ?: 'Untitled notes';
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO notes (user_id, title, mode) VALUES (?, ?, 'general')")->execute([$uid, $title]);
            $noteId = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO note_files (note_id, original_name, size_bytes) VALUES (?, ?, ?)');
            foreach ($clean as [$name, $size]) {
                $ins->execute([$noteId, $name, $size]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        out(['id' => $noteId, 'title' => $title, 'files' => array_column($clean, 0), 'ts' => time()]);

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
        if (trim($content) === '' || strlen($content) > MAX_CONTENT) {
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