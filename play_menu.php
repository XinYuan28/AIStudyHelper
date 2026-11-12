<?php
require 'auth.php';      // starts the session and loads db.php
require_login();         // not logged in -> index.php

$userName = $_SESSION['name'];
$initial  = strtoupper(mb_substr($userName, 0, 1));

// the note you were in on Study Hub (studyhub.js adds ?note=ID to the Game Quiz button), so the sidebar can take you back to it
$noteId = (int)($_GET['note'] ?? 0);
function hub_url($panel = '') {
    global $noteId;
    if (!$noteId) return 'studyhub.php';
    return 'studyhub.php?note=' . $noteId . ($panel ? '&panel=' . $panel : '');
}

$stmt = db()->prepare("SELECT id, title, created_at FROM notes WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC");
$stmt->execute([(int)$_SESSION['user_id']]);
$notes = $stmt->fetchAll();
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

        <link rel="stylesheet" href="studyhub.css">
        <link rel="icon" type="image/png" href="Images/AISNH_Cat coloredlogo(S).png">

        <script>
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || savedTheme === 'yellow') {
                document.documentElement.dataset.theme = savedTheme;
            }
        </script>

        <style>
            /* play menu only: small extras on top of studyhub.css */
            .crumbs a:hover { color: var(--primary); }
            .play-list { display: flex; flex-direction: column; gap: .7rem; }
            .note-actions form { display: contents; }
            .note-actions .btn svg { flex: none; }
            .play-empty { display: block; }
        </style>
    </head>

    <body>

        <!-- LOG OUT CONFIRM -->
        <div class="modal" id="logoutConfirmModal" role="dialog" aria-modal="true" aria-labelledby="logoutConfirmTitle" hidden>
            <div class="modal-box">
                <h3 id="logoutConfirmTitle">Log out?</h3>
                <p>Are you sure you want to log out of your account?</p>
                <div class="actions">
                    <button type="button" class="btn ghost small" id="logoutCancel">Cancel</button>
                    <button type="button" class="btn danger small" id="logoutConfirm">Log out</button>
                </div>
            </div>
        </div>

        <!-- TOP BAR -->
        <header class="topbar">
            <div class="topbar-left">
                <a href="studyhub.php"><img src="Images/AISNH_Cat coloredlogo(S).png" alt="AI Study Note Helper" class="logo"></a>
                <div class="crumbs">
                    <a href="studyhub.php">Home</a>
                    <span>&rsaquo;</span>
                    <span class="here">Game Quiz</span>
                </div>
            </div>
            <div class="topbar-right">
                <button class="icon-btn" id="themeToggle" aria-label="Change color theme">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
                    </svg>
                </button>
                <a href="logout.php" class="logout" id="logoutLink">Log out</a>
                <div class="user-wrap">
                    <button type="button" class="avatar" id="userMenuToggle" aria-label="Account menu" aria-haspopup="menu" aria-expanded="false" aria-controls="userMenu" title="<?= htmlspecialchars($userName) ?>"><?= htmlspecialchars($initial) ?></button>
                    <div class="user-menu" id="userMenu" role="menu" hidden>
                        <div class="user-menu-head"><small>Signed in as</small><b><?= htmlspecialchars($userName) ?></b></div>
                        <a href="profile.php" class="user-menu-item" role="menuitem">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 20c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5" /></svg>
                            Profile
                        </a>
                        <a href="security.php" class="user-menu-item" role="menuitem">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 5 6v5.5c0 4.4 3 8 7 9.5 4-1.5 7-5.1 7-9.5V6l-7-3z" /><path d="M9 12l2.2 2.2L15 10.5" /></svg>
                            Security
                        </a>
                        <a href="privacy.php" class="user-menu-item" role="menuitem">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2.5" /><path d="M9 8h6M9 12h6M9 16h3" /></svg>
                            Privacy policy
                        </a>
                        <a href="contact.php" class="user-menu-item" role="menuitem">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3" /><path d="M4 7.5l8 5.5 8-5.5" /></svg>
                            Contact us
                        </a>
                        <a href="faq.php" class="user-menu-item" role="menuitem">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M9.6 9.5a2.5 2.5 0 1 1 3.6 2.2c-.8.4-1.2.9-1.2 1.8M12 17h.01" /></svg>
                            FAQs
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="lesson">

            <!-- SIDEBAR (same buttons as Study Hub; these go back to Study Hub, Game Quiz is this page) -->
            <nav class="sidebar" aria-label="Features">
                <a href="<?= htmlspecialchars(hub_url('notes')) ?>" class="side-item">
                    <span class="side-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="3" width="16" height="18" rx="3" />
                            <path d="M8 8h8M8 12h8M8 16h5" />
                        </svg>
                    </span>Notes
                </a>
                <a href="play_menu.php<?= $noteId ? '?note=' . $noteId : '' ?>" class="side-item active" aria-current="page">
                    <span class="side-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2.5" y="7" width="19" height="11" rx="5" />
                            <path d="M7 10v5M4.5 12.5h5M15.5 11h.01M18 14h.01" />
                        </svg>
                    </span>Game Quiz
                </a>
                <a href="<?= htmlspecialchars(hub_url('custom')) ?>" class="side-item">
                    <span class="side-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 7h10M18 7h2M4 17h2M10 17h10" />
                            <circle cx="16" cy="7" r="2" />
                            <circle cx="8" cy="17" r="2" />
                        </svg>
                    </span>Customise Quiz
                </a>
                <a href="<?= htmlspecialchars(hub_url('tts')) ?>" class="side-item">
                    <span class="side-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 10v4h4l5 4V6L8 10H4z" />
                            <path d="M16.5 9a4 4 0 0 1 0 6M19 6.5a8 8 0 0 1 0 11" />
                        </svg>
                    </span>Text-to-Speech
                </a>
            </nav>

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

        <script>
            // theme toggle: light -> dark -> yellow -> light (same key studyhub.js reads)
            document.getElementById('themeToggle').addEventListener('click', function () {
                const order = [null, 'dark', 'yellow'];
                const current = document.documentElement.dataset.theme || null;
                const next = order[(order.indexOf(current) + 1) % order.length];
                if (next) {
                    document.documentElement.dataset.theme = next;
                } else {
                    delete document.documentElement.dataset.theme;
                }
                try { localStorage.setItem('theme', next || 'light'); } catch (e) {}
            });

            // avatar menu (Profile / Security / Privacy policy / Contact us / FAQs)
            const userMenuToggle = document.getElementById('userMenuToggle');
            const userMenuBox = document.getElementById('userMenu');
            function setUserMenu(open) {
                userMenuBox.hidden = !open;
                userMenuToggle.setAttribute('aria-expanded', String(open));
            }
            userMenuToggle.addEventListener('click', function () { setUserMenu(userMenuBox.hidden); });
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.user-wrap')) setUserMenu(false);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !userMenuBox.hidden) {
                    setUserMenu(false);
                    userMenuToggle.focus();
                }
            });

            // log out confirm (same popup as Study Hub)
            const logoutModal = document.getElementById('logoutConfirmModal');
            document.getElementById('logoutLink').addEventListener('click', function (e) {
                e.preventDefault();
                logoutModal.hidden = false;
            });
            document.getElementById('logoutCancel').addEventListener('click', function () {
                logoutModal.hidden = true;
            });
            document.getElementById('logoutConfirm').addEventListener('click', function () {
                window.location.href = 'logout.php';
            });
            logoutModal.addEventListener('click', function (e) {
                if (e.target === logoutModal) logoutModal.hidden = true;
            });
        </script>

    </body>

</html>