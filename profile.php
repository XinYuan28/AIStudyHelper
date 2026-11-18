<?php
// profile.php — Settings > Profile
// Uses the same auth.php / db.php helpers as studyhub.php.
// Assumed `users` columns: id, username, email, gender, birthday
require 'auth.php';
require_login();

$pdo    = db();
$userId = current_user_id();

$genders = ['female' => 'Female', 'male' => 'Male', 'other' => 'Other', 'prefer_not' => 'Prefer not to say'];
$errors  = [];

// Load current user
$stmt = $pdo->prepare('SELECT username, email, gender, birthday FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(404);
    exit('User not found.');
}

$form = $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $form['username'] = trim($_POST['username'] ?? '');
    $form['gender']   = $_POST['gender'] ?? '';
    $form['birthday'] = $_POST['birthday'] ?? '';

    // Validation
    // sign-up makes the username from the email (e.g. "John smith"), so spaces are allowed
    $nameLen = mb_strlen($form['username']);
    if ($nameLen < 1 || $nameLen > 50) {
        $errors['username'] = 'Username must be 1–50 characters.';
    } elseif (preg_match('/[\x00-\x1F\x7F<>]/u', $form['username'])) {
        $errors['username'] = 'Username cannot contain < > or special control characters.';
    }
    if (!array_key_exists($form['gender'], $genders)) {
        $errors['gender'] = 'Please choose an option.';
    }
    if ($form['birthday'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $form['birthday']);
        if (!$d || $d->format('Y-m-d') !== $form['birthday']) {
            $errors['birthday'] = 'Please enter a valid date.';
        } elseif ($d > new DateTime('today')) {
            $errors['birthday'] = 'Birthday cannot be in the future.';
        } elseif ($d < new DateTime('1900-01-01')) {
            $errors['birthday'] = 'Please enter a valid date.';
        }
    }

    if (!$errors) {
        try {
            $upd = $pdo->prepare('UPDATE users SET username = ?, gender = ?, birthday = ? WHERE id = ?');
            $upd->execute([
                $form['username'],
                $form['gender'],
                $form['birthday'] !== '' ? $form['birthday'] : null,
                $userId,
            ]);
            // Keep the session in step so Study Hub shows the new name straight away
            $_SESSION['name'] = $form['username'];
            // Post/Redirect/Get so refresh doesn't resubmit
            header('Location: profile.php?saved=1');
            exit;
        } catch (PDOException $e) {
            // 1062 = duplicate value (only happens if the username column has a UNIQUE index)
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
            $errors['username'] = 'That username is already taken.';
        }
    }
}

$success  = isset($_GET['saved']);
$userName = $user['username'];
$initial  = strtoupper(mb_substr($userName ?: 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Profile | AI Study Note Helper</title>

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
                    <span class="here">Profile</span>
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

            <!-- SIDEBAR (same style as the Study Hub features sidebar) -->
            <nav class="sidebar" aria-label="Settings">
                <a href="profile.php" class="side-item active" aria-current="page">
                    <span class="side-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4" /><path d="M4 20c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5" /></svg>
                    </span>Profile
                </a>
                <a href="security.php" class="side-item">
                    <span class="side-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v5.5c0 4.4 3 8 7 9.5 4-1.5 7-5.1 7-9.5V6l-7-3z" /><path d="M9 12l2.2 2.2L15 10.5" /></svg>
                    </span>Security
                </a>
                <a href="privacy.php" class="side-item">
                    <span class="side-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2.5" /><path d="M9 8h6M9 12h6M9 16h3" /></svg>
                    </span>Privacy
                </a>
                <a href="contact.php" class="side-item">
                    <span class="side-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3" /><path d="M4 7.5l8 5.5 8-5.5" /></svg>
                    </span>Contact
                </a>
                <a href="faq.php" class="side-item">
                    <span class="side-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M9.6 9.5a2.5 2.5 0 1 1 3.6 2.2c-.8.4-1.2.9-1.2 1.8M12 17h.01" /></svg>
                    </span>FAQs
                </a>
            </nav>

            <main class="lesson-main view">
                <div class="panel">
                    <h2 class="panel-title">Profile</h2>
                    <p class="lead">Update your personal details.</p>

                    <?php if ($success): ?>
                        <div class="feedback ok" role="status">Your profile has been updated.</div>
                    <?php endif; ?>

                    <form class="card form-grid" method="post" action="profile.php" novalidate>
                        <?= csrf_field() ?>

                        <div class="profile-head">
                            <div class="profile-avatar" aria-hidden="true"><?= h($initial) ?></div>
                            <div>
                                <b><?= h($userName) ?></b>
                                <small>Your details are only used inside AI Study Note Helper.</small>
                            </div>
                        </div>

                        <div class="field <?= isset($errors['username']) ? 'has-error' : '' ?>">
                            <label for="username">Username</label>
                            <input type="text" class="input" id="username" name="username" maxlength="50" required autocomplete="username" value="<?= h($form['username']) ?>">
                            <p class="field-hint">This is the name shown in your Study Hub.</p>
                            <?php if (isset($errors['username'])): ?><p class="field-error"><?= h($errors['username']) ?></p><?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="email">Email</label>
                            <input type="email" class="input" id="email" value="<?= h($form['email']) ?>" readonly aria-describedby="emailHint">
                            <p class="field-hint" id="emailHint">To change your email, go to <a href="security.php">Security</a>.</p>
                        </div>

                        <div class="field <?= isset($errors['gender']) ? 'has-error' : '' ?>">
                            <label id="genderLabel">Gender</label>
                            <div class="seg" role="radiogroup" aria-labelledby="genderLabel">
                                <?php foreach ($genders as $val => $label): ?>
                                    <input type="radio" name="gender" id="g_<?= h($val) ?>" value="<?= h($val) ?>" <?= $form['gender'] === $val ? 'checked' : '' ?>>
                                    <label for="g_<?= h($val) ?>"><?= h($label) ?></label>
                                <?php endforeach; ?>
                            </div>
                            <?php if (isset($errors['gender'])): ?><p class="field-error"><?= h($errors['gender']) ?></p><?php endif; ?>
                        </div>

                        <div class="field <?= isset($errors['birthday']) ? 'has-error' : '' ?>">
                            <label for="birthday">Birthday</label>
                            <input type="date" class="input" id="birthday" name="birthday" min="1900-01-01" max="<?= date('Y-m-d') ?>" value="<?= h($form['birthday']) ?>">
                            <?php if (isset($errors['birthday'])): ?><p class="field-error"><?= h($errors['birthday']) ?></p><?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <a href="profile.php" class="btn ghost">Cancel</a>
                            <button type="submit" class="btn">Save changes</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>

        <script>
            // Theme toggle: light -> dark -> yellow (eye saver) -> light, saved in localStorage like Study Hub
            (function () {
                var themes = ['light', 'dark', 'yellow'];
                document.getElementById('themeToggle').addEventListener('click', function () {
                    var current = document.documentElement.dataset.theme || 'light';
                    var next = themes[(themes.indexOf(current) + 1) % themes.length];
                    if (next === 'light') {
                        delete document.documentElement.dataset.theme;
                        localStorage.removeItem('theme');
                    } else {
                        document.documentElement.dataset.theme = next;
                        localStorage.setItem('theme', next);
                    }
                });

                // Avatar dropdown
                var toggle = document.getElementById('userMenuToggle');
                var menu = document.getElementById('userMenu');
                function closeMenu() { menu.hidden = true; toggle.setAttribute('aria-expanded', 'false'); }
                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var open = menu.hidden;
                    menu.hidden = !open;
                    toggle.setAttribute('aria-expanded', String(open));
                });
                document.addEventListener('click', function (e) {
                    if (!menu.hidden && !menu.contains(e.target)) closeMenu();
                });

                // Log out confirmation
                var modal = document.getElementById('logoutConfirmModal');
                document.getElementById('logoutLink').addEventListener('click', function (e) {
                    e.preventDefault();
                    modal.hidden = false;
                });
                document.getElementById('logoutCancel').addEventListener('click', function () { modal.hidden = true; });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') { closeMenu(); modal.hidden = true; }
                });
            })();
        </script>

    </body>

</html>