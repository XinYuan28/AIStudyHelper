<?php
// security.php — Settings > Security (change password, change email, delete account)
// `users` columns used: id, username, email, password (matches auth.php)
// Assumed child tables with a user_id column: notes, quiz_results
require 'auth.php';
require_login();

$pdo    = db();
$userId = current_user_id();

$stmt = $pdo->prepare('SELECT username, email, password FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(404);
    exit('User not found.');
}

$errors   = [];
$newEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action  = $_POST['action'] ?? '';
    $current = (string) ($_POST['current_password'] ?? '');
    $passOk  = password_verify($current, $user['password']);

    if ($action === 'password') {
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (!$passOk) {
            $errors['pw_current'] = 'Your current password is incorrect.';
        }
        if (mb_strlen($new) < 8) {
            $errors['pw_new'] = 'Use at least 8 characters.';
        } elseif (strlen($new) > 72) {
            $errors['pw_new'] = 'Password is too long (max 72 characters).';
        } elseif ($new === $current) {
            $errors['pw_new'] = 'Choose a password different from your current one.';
        }
        if ($new !== $confirm) {
            $errors['pw_confirm'] = 'Passwords do not match.';
        }

        if (!$errors) {
            $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $upd->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            $_SESSION['auth_password'] = hash('sha256', $pdo->query('SELECT password FROM users WHERE id = ' . (int) $userId)->fetchColumn());
            session_regenerate_id(true);
            header('Location: security.php?done=password');
            exit;
        }
    } elseif ($action === 'email') {
        $newEmail = normalize_email($_POST['new_email'] ?? '');

        if (!$passOk) {
            $errors['em_password'] = 'Your password is incorrect.';
        }
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($newEmail) > 120) {
            $errors['em_new'] = 'Please enter a valid email address.';
        } elseif ($newEmail === normalize_email($user['email'])) {
            $errors['em_new'] = 'That is already your email.';
        } else {
            $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $chk->execute([$newEmail, $userId]);
            if ($chk->fetch()) {
                $errors['em_new'] = 'That email is already used by another account.';
            }
        }

        if (!$errors) {
            $upd = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
            $upd->execute([$newEmail, $userId]);
            $_SESSION['email'] = $newEmail;
            header('Location: security.php?done=email');
            exit;
        }
    } elseif ($action === 'delete') {
        if (!$passOk) {
            $errors['del_password'] = 'Your password is incorrect.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM notes WHERE user_id = ?')->execute([$userId]);
                $pdo->prepare('DELETE FROM quiz_results WHERE user_id = ?')->execute([$userId]);
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
                $pdo->commit();

                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $p = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
                }
                session_destroy();
                header('Location: index.php?deleted=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors['del_general'] = 'Sorry, we could not delete your account. Please try again later.';
            }
        }
    }
}

$done = $_GET['done'] ?? '';
$doneMessages = [
    'password' => 'Your password has been changed.',
    'email'    => 'Your email has been updated.',
];

$userName = $user['username'];
$initial  = strtoupper(mb_substr($userName ?: 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Security | AI Study Note Helper</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">

        <link rel="stylesheet" href="studyhub.css?v=<?= filemtime(__DIR__ . '/studyhub.css') ?>">
        <link rel="stylesheet" href="profile.css">
        <link rel="icon" type="image/png" href="Images/AISNH_Cat coloredlogo(S).png">

        <script>
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || savedTheme === 'yellow') {
                document.documentElement.dataset.theme = savedTheme;
            }
        </script>
        <script src="studyhub_feature_header.js?v=<?= filemtime(__DIR__ . '/studyhub_feature_header.js') ?>" data-menu-only defer></script>
    </head>

    <body>

        <!-- CONFIRM CHANGE (used by all three forms) -->
        <div class="modal" id="actionModal" role="dialog" aria-modal="true" aria-labelledby="actionTitle" hidden>
            <div class="modal-box">
                <h3 id="actionTitle"></h3>
                <p id="actionText"></p>
                <div class="actions">
                    <button type="button" class="btn ghost small" id="actionCancel">Cancel</button>
                    <button type="button" class="btn small" id="actionOk">Confirm</button>
                </div>
            </div>
        </div>

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
        <header class="topbar feature-topbar">
            <div class="topbar-left">
                <a href="studyhub.php"><img src="Images/AISNH_Cat coloredlogo(S).png" alt="AI Study Note Helper" class="logo"></a>
                <div class="crumbs">
                    <a href="studyhub.php" class="home-link">Home</a>
                    <span>&rsaquo;</span>
                    <span class="here">Security</span>
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

            <!-- SIDEBAR -->
            <div class="lesson-feature-menu">
                <?php $showSettingsMenuToggle = true; require __DIR__ . '/studyhub_sidebar_toggle.php'; unset($showSettingsMenuToggle); ?>
                <nav class="sidebar" id="lessonFeatureMenu" aria-label="Settings">
                    <a href="profile.php" class="side-item">
                        <span class="side-icon">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4" /><path d="M4 20c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5" /></svg>
                        </span>Profile
                    </a>
                    <a href="security.php" class="side-item active" aria-current="page">
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
            </div>

            <main class="lesson-main view">
                <div class="panel">
                    <h2 class="panel-title">Security</h2>
                    <p class="lead">Manage your password, email and account.</p>

                    <?php if (isset($doneMessages[$done])): ?>
                        <div class="feedback ok" role="status"><?= h($doneMessages[$done]) ?></div>
                    <?php endif; ?>

                    <!-- CHANGE PASSWORD -->
                    <form class="card form-grid" method="post" action="security.php"
                        data-confirm-title="Change your password?"
                        data-confirm-text="You will use your new password the next time you log in."
                        data-confirm-label="Change password">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="password">
                        <h3>Change password</h3>

                        <div class="field <?= isset($errors['pw_current']) ? 'has-error' : '' ?>">
                            <label for="pwCurrent">Current password</label>
                            <input type="password" class="input" id="pwCurrent" name="current_password" required autocomplete="current-password">
                            <?php if (isset($errors['pw_current'])): ?><p class="field-error"><?= h($errors['pw_current']) ?></p><?php endif; ?>
                        </div>

                        <div class="field <?= isset($errors['pw_new']) ? 'has-error' : '' ?>">
                            <label for="pwNew">New password</label>
                            <input type="password" class="input" id="pwNew" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password">
                            <p class="field-hint">At least 8 characters.</p>
                            <?php if (isset($errors['pw_new'])): ?><p class="field-error"><?= h($errors['pw_new']) ?></p><?php endif; ?>
                        </div>

                        <div class="field <?= isset($errors['pw_confirm']) ? 'has-error' : '' ?>">
                            <label for="pwConfirm">Confirm new password</label>
                            <input type="password" class="input" id="pwConfirm" name="confirm_password" required autocomplete="new-password">
                            <?php if (isset($errors['pw_confirm'])): ?><p class="field-error"><?= h($errors['pw_confirm']) ?></p><?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn">Change password</button>
                        </div>
                    </form>

                    <!-- CHANGE EMAIL -->
                    <form class="card form-grid" method="post" action="security.php"
                        data-confirm-title="Change your email?"
                        data-confirm-text="Your account email will be changed to {new_email}."
                        data-confirm-label="Change email">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="email">
                        <h3>Change email</h3>
                        <p class="field-hint current-email">Current email: <b><?= h($user['email']) ?></b></p>

                        <div class="field <?= isset($errors['em_new']) ? 'has-error' : '' ?>">
                            <label for="emNew">New email</label>
                            <input type="email" class="input" id="emNew" name="new_email" required maxlength="120" autocomplete="email" value="<?= h($newEmail) ?>">
                            <?php if (isset($errors['em_new'])): ?><p class="field-error"><?= h($errors['em_new']) ?></p><?php endif; ?>
                        </div>

                        <div class="field <?= isset($errors['em_password']) ? 'has-error' : '' ?>">
                            <label for="emPassword">Current password</label>
                            <input type="password" class="input" id="emPassword" name="current_password" required autocomplete="current-password">
                            <p class="field-hint">We ask for your password to make sure it's really you.</p>
                            <?php if (isset($errors['em_password'])): ?><p class="field-error"><?= h($errors['em_password']) ?></p><?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn">Change email</button>
                        </div>
                    </form>

                    <!-- DELETE ACCOUNT -->
                    <form class="card form-grid danger-zone" method="post" action="security.php"
                        data-danger="1"
                        data-confirm-title="Delete your account?"
                        data-confirm-text="This permanently deletes your account, notes and quiz results. This cannot be undone."
                        data-confirm-label="Delete account">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <h3>Delete account</h3>
                        <p class="field-hint">Deleting your account permanently removes your profile, saved notes and quiz results. This cannot be undone.</p>

                        <?php if (isset($errors['del_general'])): ?><p class="field-error"><?= h($errors['del_general']) ?></p><?php endif; ?>

                        <div class="field <?= isset($errors['del_password']) ? 'has-error' : '' ?>">
                            <label for="delPassword">Current password</label>
                            <input type="password" class="input" id="delPassword" name="current_password" required autocomplete="current-password">
                            <?php if (isset($errors['del_password'])): ?><p class="field-error"><?= h($errors['del_password']) ?></p><?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn danger">Delete my account</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>

        <script>
            (function () {
                // Theme toggle: light -> dark -> yellow (eye saver) -> light
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
                var logoutModal = document.getElementById('logoutConfirmModal');
                document.getElementById('logoutLink').addEventListener('click', function (e) {
                    e.preventDefault();
                    logoutModal.hidden = false;
                });
                document.getElementById('logoutCancel').addEventListener('click', function () { logoutModal.hidden = true; });

                // Confirmation pop-up before password / email / delete changes
                var modal = document.getElementById('actionModal');
                var titleEl = document.getElementById('actionTitle');
                var textEl = document.getElementById('actionText');
                var okBtn = document.getElementById('actionOk');
                var pending = null;

                function closeAction() { modal.hidden = true; pending = null; }

                document.querySelectorAll('form[data-confirm-title]').forEach(function (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();

                        // passwords must match before we even ask
                        var np = form.querySelector('[name="new_password"]');
                        var cp = form.querySelector('[name="confirm_password"]');
                        if (np && cp) {
                            cp.setCustomValidity(np.value !== cp.value ? 'Passwords do not match.' : '');
                        }
                        if (!form.reportValidity()) return;

                        var text = form.dataset.confirmText;
                        var em = form.querySelector('[name="new_email"]');
                        if (em) text = text.replace('{new_email}', em.value.trim());

                        titleEl.textContent = form.dataset.confirmTitle;
                        textEl.textContent = text;
                        okBtn.textContent = form.dataset.confirmLabel;
                        okBtn.className = 'btn small' + (form.dataset.danger ? ' danger' : '');
                        pending = form;
                        modal.hidden = false;
                        okBtn.focus();
                    });
                });

                okBtn.addEventListener('click', function () {
                    if (pending) { var f = pending; closeAction(); f.submit(); }
                });
                document.getElementById('actionCancel').addEventListener('click', closeAction);
                modal.addEventListener('click', function (e) { if (e.target === modal) closeAction(); });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') { closeMenu(); logoutModal.hidden = true; closeAction(); }
                });

                // after a failed save, jump to the first error
                var firstError = document.querySelector('.field-error');
                if (firstError) firstError.scrollIntoView({ block: 'center' });
            })();
        </script>

    </body>

</html>