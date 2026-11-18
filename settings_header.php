<?php
// settings_header.php — shared top of the Settings pages (head, top bar, sidebar).
// Set these BEFORE including it (after require 'auth.php'; require_login();):
//   $pageTitle   e.g. 'Privacy policy'
//   $activePage  'profile' | 'security' | 'privacy' | 'contact' | 'faq'
// Close the page with: require 'settings_footer.php';

// The email shown on the Contact / Privacy pages. Change it here once.
defined('SUPPORT_EMAIL') || define('SUPPORT_EMAIL', 'support@example.com');

$userName = (string) ($_SESSION['name'] ?? 'User');
$initial  = strtoupper(mb_substr($userName, 0, 1));

$settingsIcons = [
    'profile'  => '<circle cx="12" cy="8" r="4" /><path d="M4 20c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5" />',
    'security' => '<path d="M12 3 5 6v5.5c0 4.4 3 8 7 9.5 4-1.5 7-5.1 7-9.5V6l-7-3z" /><path d="M9 12l2.2 2.2L15 10.5" />',
    'privacy'  => '<rect x="5" y="3" width="14" height="18" rx="2.5" /><path d="M9 8h6M9 12h6M9 16h3" />',
    'contact'  => '<rect x="3" y="5" width="18" height="14" rx="3" /><path d="M4 7.5l8 5.5 8-5.5" />',
    'faq'      => '<circle cx="12" cy="12" r="9" /><path d="M9.6 9.5a2.5 2.5 0 1 1 3.6 2.2c-.8.4-1.2.9-1.2 1.8M12 17h.01" />',
];
// key => [avatar-menu label, sidebar label, link]
$settingsPages = [
    'profile'  => ['Profile', 'Profile', 'profile.php'],
    'security' => ['Security', 'Security', 'security.php'],
    'privacy'  => ['Privacy policy', 'Privacy', 'privacy.php'],
    'contact'  => ['Contact us', 'Contact', 'contact.php'],
    'faq'      => ['FAQs', 'FAQs', 'faq.php'],
];
$settingsSvg = function (string $key, int $size) use ($settingsIcons): string {
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $settingsIcons[$key] . '</svg>';
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | AI Study Note Helper</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="studyhub.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="icon" type="image/png" href="Images/AISNH_Cat coloredlogo(S).png">

    <script>
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark' || savedTheme === 'yellow') {
            document.documentElement.dataset.theme = savedTheme;
        }
    </script>
</head>

<body>

    <!-- LOG OUT CONFIRM -->
    <div class="modal" id="logoutConfirmModal" role="dialog" aria-modal="true" aria-labelledby="logoutConfirmTitle" hidden>
        <div class="modal-box">
            <h3 id="logoutConfirmTitle">Log out?</h3>
            <p>Are you sure you want to log out of your account?</p>
            <div class="actions">
                <button type="button" class="btn ghost small" id="logoutCancel">Cancel</button>
                <a href="logout.php" class="btn danger small" id="logoutConfirm">Log out</a>
            </div>
        </div>
    </div>

    <!-- TOP BAR -->
    <header class="topbar">
        <div class="topbar-left">
            <a href="studyhub.php"><img src="Images/AISNH_Cat coloredlogo(S).png" alt="AI Study Note Helper" class="logo"></a>
            <div class="crumbs">
                <a href="studyhub.php" class="home-link">Home</a>
                <span>&rsaquo;</span>
                <span class="here"><?= h($pageTitle) ?></span>
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
                <button type="button" class="avatar" id="userMenuToggle" aria-label="Account menu" aria-haspopup="menu" aria-expanded="false" aria-controls="userMenu" title="<?= h($userName) ?>"><?= h($initial) ?></button>
                <div class="user-menu" id="userMenu" role="menu" hidden>
                    <div class="user-menu-head"><small>Signed in as</small><b><?= h($userName) ?></b></div>
                    <?php foreach ($settingsPages as $key => $p): ?>
                        <a href="<?= h($p[2]) ?>" class="user-menu-item" role="menuitem"><?= $settingsSvg($key, 20) ?> <?= h($p[0]) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="lesson">

        <!-- SIDEBAR -->
        <nav class="sidebar" aria-label="Settings">
            <?php foreach ($settingsPages as $key => $p): ?>
                <a href="<?= h($p[2]) ?>" class="side-item<?= $activePage === $key ? ' active' : '' ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?>>
                    <span class="side-icon"><?= $settingsSvg($key, 26) ?></span><?= h($p[1]) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <main class="lesson-main view">
            <div class="panel">