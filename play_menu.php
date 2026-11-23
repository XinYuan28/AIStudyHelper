<?php
require 'auth.php';      // starts the session and loads db.php
require_login();         // not logged in -> index.php

$breadcrumbTitle = 'Game Quiz';
$noteId = (int) ($_GET['note'] ?? 0);
$showSidebarToggle = $noteId <= 0;

$stmt = db()->prepare("SELECT id, title, created_at FROM notes WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC");
$stmt->execute([(int)$_SESSION['user_id']]);
$notes = $stmt->fetchAll();
if (!$showSidebarToggle) {
    foreach ($notes as $note) {
        if ((int) $note['id'] === $noteId) {
            $breadcrumbTitle = $note['title'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Game Quiz | AI Study Note Helper</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">

        <link rel="stylesheet" href="studyhub.css?v=<?= filemtime(__DIR__ . '/studyhub.css') ?>">
        <link rel="icon" type="image/png" href="Images/AISNH_Cat coloredlogo(S).png">

        <script>
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || savedTheme === 'yellow') {
                document.documentElement.dataset.theme = savedTheme;
            }
        </script>

        <style>
            /* play menu only: small extras on top of studyhub.css */
            .play-list { display: flex; flex-direction: column; gap: .7rem; }
            .note-actions form { display: contents; }
            .note-actions .btn svg { flex: none; }
            .play-empty { display: block; }
        </style>
        <?php if ($showSidebarToggle): ?>
            <script src="studyhub_sidebar.js?v=<?= filemtime(__DIR__ . '/studyhub_sidebar.js') ?>" defer></script>
        <?php endif; ?>
        <script src="studyhub_feature_header.js?v=<?= filemtime(__DIR__ . '/studyhub_feature_header.js') ?>" defer></script>
    </head>

    <body<?= $showSidebarToggle ? ' class="home-active feature-mobile-menu"' : '' ?>>
        <?php if ($showSidebarToggle) require __DIR__ . '/studyhub_sidebar.php'; ?>

        <?php require __DIR__ . '/studyhub_feature_header.php'; ?>
        <?php if ($showSidebarToggle): ?>
            <?php
            $sidebarMobileMenu = true;
            require __DIR__ . '/studyhub_sidebar.php';
            unset($sidebarMobileMenu);
            ?>
        <?php endif; ?>

        <div class="lesson">
            <?php if (!$showSidebarToggle) require __DIR__ . '/studyhub_sidebar.php'; ?>

            <main class="lesson-main">
                <div class="panel view">
                    <h2 class="panel-title">Game Quiz</h2>
                    <p class="lead">Pick a note and a game. Answer to earn points and build a streak.</p>

                    <?php if (!$notes): ?>
                        <a href="studyhub.php" class="empty play-empty">
                            <b>No notes yet</b>
                            Upload your study materials on the home page first, then come back to play.
                        </a>
                    <?php else: ?>
                        <div class="play-list">
                            <?php foreach ($notes as $n): ?>
                                <div class="note-row">
                                    <div class="ico">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="4" y="3" width="16" height="18" rx="3" />
                                            <path d="M8 8h8M8 12h8M8 16h5" />
                                        </svg>
                                    </div>
                                    <div class="note-info">
                                        <h3><?= htmlspecialchars($n['title']) ?></h3>
                                        <small><?= htmlspecialchars(date('j M Y, g:i A', strtotime($n['created_at']))) ?></small>
                                    </div>
                                    <div class="note-actions">
                                        <?php /* POST + csrf: a game costs an AI call, so a link on another site must not be able to start one */ ?>
                                        <form method="post" action="api/generate_games.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="go" value="1">
                                            <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
                                            <input type="hidden" name="t" value="A">
                                            <button type="submit" class="btn small">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <rect x="3.5" y="3.5" width="7" height="7" rx="2" />
                                                    <rect x="13.5" y="3.5" width="7" height="7" rx="2" />
                                                    <rect x="3.5" y="13.5" width="7" height="7" rx="2" />
                                                    <rect x="13.5" y="13.5" width="7" height="7" rx="2" />
                                                </svg>
                                                Tile Match
                                            </button>
                                        </form>
                                        <form method="post" action="api/generate_games.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="go" value="1">
                                            <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
                                            <input type="hidden" name="t" value="B">
                                            <button type="submit" class="btn ghost small">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <rect x="2.5" y="6" width="19" height="12" rx="3" />
                                                    <path d="M6.5 10h.01M10 10h.01M14 10h.01M17.5 10h.01M8 14h8" />
                                                </svg>
                                                Typing Game
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>

    </body>

</html>