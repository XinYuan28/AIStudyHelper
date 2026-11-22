<?php
require 'auth.php';
require_login();
$showSidebarToggle = true;
$breadcrumbTitle = 'Trash';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trash | Study Helper</title>
    <link rel="icon" type="image/png" href="Images/AISNH_Cat coloredlogo(S).png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="studyhub.css?v=<?= filemtime(__DIR__ . '/studyhub.css') ?>">
    <link rel="stylesheet" href="trash.css?v=<?= filemtime(__DIR__ . '/trash.css') ?>">
    <script>
        const theme = localStorage.getItem('theme');
        if (theme === 'dark' || theme === 'yellow') document.documentElement.dataset.theme = theme;
    </script>
    <script src="studyhub_sidebar.js?v=<?= filemtime(__DIR__ . '/studyhub_sidebar.js') ?>" defer></script>
    <script src="studyhub_feature_header.js?v=<?= filemtime(__DIR__ . '/studyhub_feature_header.js') ?>" defer></script>
</head>
<body class="home-active">
<?php require __DIR__ . '/studyhub_sidebar.php'; ?>
<?php require __DIR__ . '/studyhub_feature_header.php'; ?>
<main class="trash-page">
    <header class="trash-header">
        <div>
            <h1>Trash</h1>
            <p>Deleted notes stay here for 10 days. Restore them or permanently delete them sooner.</p>
        </div>
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