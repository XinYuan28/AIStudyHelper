<?php
require 'db.php';
require_login();
require 'extract.php';
require 'ai.php';

set_time_limit(270);

$uid = (int)$_SESSION['user_id'];

$zipMimes = ['application/zip', 'application/octet-stream'];
$allowed = [
    'pdf'  => ['application/pdf'],
    'txt'  => ['text/plain'],
    'md'   => ['text/plain', 'text/markdown', 'text/x-markdown'],
    'csv'  => ['text/csv', 'text/plain', 'application/csv'],
    'rtf'  => ['text/rtf', 'application/rtf', 'text/plain'],
    'docx' => array_merge(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $zipMimes),
    'pptx' => array_merge(['application/vnd.openxmlformats-officedocument.presentationml.presentation'], $zipMimes),
    'xlsx' => array_merge(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], $zipMimes),
    'odt'  => array_merge(['application/vnd.oasis.opendocument.text'], $zipMimes),
];
$acceptAttr = '.' . implode(',.', array_keys($allowed));
$typeList   = strtoupper(implode(', ', array_keys($allowed)));

$maxSize  = 10 * 1024 * 1024;
$maxFiles = 10;

$errors     = [];
$accepted   = [];
$skipped    = [];
$mode       = in_array($_GET['mode'] ?? '', ['general', 'personalised'], true)
    ? $_GET['mode']
    : '';
$savedNotes = '';
$title      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $mode = $_POST['mode'] ?? '';
    if (!in_array($mode, ['general', 'personalised'], true)) {
        $errors[] = 'Please choose general or personalised notes.';
    }

    $customTitle = mb_substr(trim($_POST['note_title'] ?? ''), 0, 100);

    if (!$errors) {
        foreach (['files', 'folder'] as $field) {
            if (empty($_FILES[$field]['name'])) continue;
            $f = $_FILES[$field];

            foreach ($f['name'] as $i => $name) {
                if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;

                $name = mb_substr(basename($name), 0, 255);
                if ($f['error'][$i] !== UPLOAD_ERR_OK) {
                    $skipped[] = "$name (upload error)";
                    continue;
                }

                $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'][$i]);

                if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) {
                    $skipped[] = "$name (type not supported)";
                    continue;
                }
                if ($f['size'][$i] > $maxSize) {
                    $skipped[] = "$name (over 10 MB)";
                    continue;
                }

                if ($ext === 'pdf') {
                    $payload = ['mime' => 'application/pdf', 'data' => file_get_contents($f['tmp_name'][$i])];
                    $preview = '';
                    $trimmed = false;
                } else {
                    $ex = extract_text($f['tmp_name'][$i], $ext);
                    if ($ex['text'] === '') {
                        $skipped[] = "$name (no readable text)";
                        continue;
                    }
                    $payload = ['mime' => 'text/plain', 'data' => $ex['text']];
                    $preview = mb_substr($ex['text'], 0, 150);
                    $trimmed = $ex['trimmed'];
                }

                $accepted[] = [
                    'name'    => $name,
                    'size'    => $f['size'][$i],
                    'preview' => $preview,
                    'trimmed' => $trimmed,
                    'mime'    => $payload['mime'],
                    'data'    => $payload['data'],
                ];
            }
        }

        if (!$accepted) {
            $errors[] = "No usable files received. Supported: $typeList.";
        } elseif (count($accepted) > $maxFiles) {
            $errors[] = "Please upload at most $maxFiles files at a time.";
            $accepted = [];
        }
    }

    if (!$errors) {
        try {
            $files = [];
            foreach ($accepted as $a) {
                $files[] = ['name' => $a['name'], 'mime' => $a['mime'], 'data' => $a['data']];
            }
            $result = ai_generate($files);

            $title = $customTitle !== ''
                ? $customTitle
                : mb_substr($result['title'], 0, 100);

            $stmt = $pdo->prepare('INSERT INTO notes (user_id, title, content, mode) VALUES (?, ?, ?, ?)');
            $stmt->execute([$uid, $title, $result['notes'], $mode]);
            $noteId = (int)$pdo->lastInsertId();

            $fileStmt = $pdo->prepare(
                'INSERT INTO note_files (note_id, original_name, size_bytes) VALUES (?, ?, ?)'
            );
            foreach ($accepted as $a) {
                $fileStmt->execute([$noteId, $a['name'], $a['size']]);
            }

            $_SESSION['quiz'] = [
                'note_id'   => $noteId,
                'topic'     => $title,
                'questions' => $result['questions'],
            ];

            if ($mode === 'personalised') {
                header('Location: quiz.php');
                exit;
            }
            $savedNotes = $result['notes'];
        } catch (Throwable $ex) {
            error_log('[upload.php] ' . $ex->getMessage());
            $errors[] = 'We could not generate your notes right now. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Upload study material</title>
<style>
  body { font-family: sans-serif; max-width: 560px; margin: 40px auto; padding: 0 16px; }
  button { padding: 10px 16px; cursor: pointer; }
  fieldset { margin: 14px 0; }
  input[type=text] { width: 100%; padding: 8px; box-sizing: border-box; }
  .error { color: #b00020; }
  .ok { color: #1b7f3b; }
  .muted { color: #666; font-size: 0.9em; }
</style>
</head>
<body>
<p><a href="homepage.php">&larr; Homepage</a></p>
<h2>Upload study material</h2>
<p class="muted">Your files are not stored. Only the notes generated from them are saved.</p>

<?php foreach ($errors as $err): ?>
  <p class="error"><?= e($err) ?></p>
<?php endforeach; ?>

<?php if ($skipped): ?>
  <p class="muted">Skipped: <?= e(implode(', ', $skipped)) ?></p>
<?php endif; ?>

<?php if ($savedNotes && !$errors): ?>
  <p class="ok">Received <?= count($accepted) ?> file(s) for <?= e($mode) ?> notes:</p>
  <ul>
    <?php foreach ($accepted as $a): ?>
      <li><?= e($a['name']) ?> (<?= round($a['size'] / 1024) ?> KB)<?= $a['trimmed'] ? ' - trimmed to fit' : '' ?>
        <?php if ($a['preview']): ?><br><span class="muted"><?= e($a['preview']) ?>...</span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <h3><?= e($title) ?></h3>
  <div style="white-space: pre-wrap"><?= e($savedNotes) ?></div>
  <p><a href="quiz.php">Take a quiz on these notes</a></p>
  <hr>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <label>Note name</label><br>
  <input type="text" name="note_title" maxlength="100" placeholder="e.g. Biology Chapter 3"><br><br>

  <label>Choose files</label><br>
  <input type="file" name="files[]" accept="<?= e($acceptAttr) ?>" multiple><br><br>

  <label>Choose a whole folder</label><br>
  <input type="file" name="folder[]" webkitdirectory multiple><br>

  <fieldset>
    <legend>Notes type</legend>
    <label><input type="radio" name="mode" value="general" <?= $mode === 'general' ? 'checked' : '' ?>> General notes</label><br>
    <label><input type="radio" name="mode" value="personalised" <?= $mode === 'personalised' ? 'checked' : '' ?>> Personalised notes (short quiz first)</label>
  </fieldset>

  <button type="submit">Upload</button>
</form>
</body>
</html>