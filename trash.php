<?php
require 'auth.php';
require_login();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trash | Study Helper</title>
    <link rel="stylesheet" href="studyhub.css?v=<?= filemtime(__DIR__ . '/studyhub.css') ?>">
    <link rel="stylesheet" href="trash.css?v=<?= filemtime(__DIR__ . '/trash.css') ?>">
    <script>
        const theme = localStorage.getItem('theme');
        if (theme === 'dark' || theme === 'yellow') document.documentElement.dataset.theme = theme;
    </script>
</head>
<body>
<?php require __DIR__ . '/feature_sidebar.php'; ?>
<main class="trash-page">
    <header class="trash-header">
        <div>
            <h1>Trash</h1>
            <p>Deleted notes stay here for 10 days. Restore them or permanently delete them sooner.</p>
        </div>
        <a href="studyhub.php">Back to Study Hub</a>
    </header>
    <div class="trash-toolbar">
        <p id="trashCount" aria-live="polite"></p>
        <div>
            <label for="trashSort">Sort by deletion date</label>
            <select id="trashSort">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
            </select>
        </div>
    </div>
    <p id="trashStatus" class="trash-status" role="status" aria-live="polite"></p>
    <section id="trashList" aria-label="Deleted notes" aria-busy="true">
        <p class="trash-empty">Loading deleted notes…</p>
    </section>
</main>
<script>window.STUDYHUB_CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="trash.js?v=<?= filemtime(__DIR__ . '/trash.js') ?>"></script>
</body>
</html>