<?php
require 'auth.php';

$mode  = 'signup'; 
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $email  = normalize_email(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
    $pass   = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $mode   = $action === 'login' ? 'login' : 'signup';

    if (!csrf_ok()) {
        $error = 'Your session expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($action === 'login') {
        if (login_blocked($email)) {
            $error = 'Too many wrong attempts. Please wait a few minutes and try again.';
        } else {
            $user = find_user($email);
            if ($user && password_verify($pass, $user['hash'])) {
                clear_login_attempts($email);
                login_user($email, $user);
                header('Location: studyhub.php');
                exit;
            }
            record_failed_login($email);
            $error = 'Incorrect email or password.';
        }
    } elseif ($action === 'signup') {
        if (strlen($pass) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (find_user($email)) {
            $error = 'That email already has an account. Try logging in.';
        } else {
            try {
                $user = create_user($email, $pass);
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) throw $e;
                $error = 'That email already has an account. Try logging in.';
                $user = null;
            }
            if ($user) {
                login_user($email, $user);
                header('Location: studyhub.php');
                exit;
            }
        }
    }
}

$loggedIn = is_logged_in();
$login    = $mode === 'login';
?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>AI Study Note Helper</title>

        <!-- GOOGLE FONTS -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">

        <!-- CSS -->
        <link rel="stylesheet" href="index.css">

        <!-- JS -->
        <script src="index.js" defer></script>

        <!-- FAVICON -->
        <link rel="icon" type="image/png" href="Images/AISNH_Cat coloredlogo(S).png">
    </head>

    <body>

        <!-- NAV BAR -->
        <header class="nav" id="top">
            <nav class="nav-links">
                <a href="index.php">
                    <img src="Images/AISNH_Cat coloredlogo(S).png" alt="AI Study Note Helper" class="logo">
                </a>
                <a href="#whyus">Why Us</a>
                <a href="#faq">FAQs</a>
            </nav>
            <div class="nav-right">
                <?php if ($loggedIn): ?>
                    <a href="logout.php" class="btn-outline">Log out</a>
                    <a href="studyhub.php" class="btn-fill">Go to Study Hub</a>
                <?php else: ?>
                    <a href="#signup" class="btn-outline" data-auth="login">Log in</a>
                    <a href="#signup" class="btn-fill" data-auth="signup">Get started</a>
                <?php endif; ?>
                <button class="theme-toggle" id="themeToggle" aria-label="Change color theme">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
                    </svg>
                </button>
            </div>
        </header>

        <!-- HERO SECTION-->
        <section class="hero">
            <div class="blob blob-pink"></div>
            <div class="blob blob-yellow-lg"></div>
            <div class="blob blob-yellow-sm"></div>

            <div class="hero-text">
                <h1>Welcome to your<br>AI Study Note Helper</h1>
                <p class="hero-sub">Turn your study materials into clear notes, audio and quizzes, and stay on track with reminders.</p>
                <?php if ($loggedIn): ?>
                    <a href="studyhub.php" class="btn-soft">Go to Study Hub</a>
                <?php else: ?>
                    <a href="#signup" class="btn-soft" data-auth="signup">Get started</a>
                <?php endif; ?>
            </div>

            <?php if ($loggedIn): ?>
            <!-- LOGGED IN: welcome card instead of the forms -->
            <div class="flip" id="signup">
                <div class="signup">
                    <h2>Hi, <?= h($_SESSION['name']) ?>!</h2>
                    <p class="signed-in">You're signed in. Pick up where you left off.</p>
                    <a href="studyhub.php" class="btn-solid btn-block">Go to Study Hub</a>
                </div>
            </div>
            <?php else: ?>
            <div class="flip<?= $login ? ' flipped' : '' ?>" id="signup">
                <div class="flip-inner">

                    <!-- CARD FLIP - SIGN-UP/LOGIN -->
                    <!-- FRONT: sign up -->
                    <form class="signup face front" id="signupFace" method="post" action="index.php#signup" <?= $login ? 'inert' : '' ?>>
                        <input type="hidden" name="action" value="signup">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <h2>Start studying for free</h2>
                        <?php if ($error && !$login): ?><p class="form-error" role="alert"><?= h($error) ?></p><?php endif; ?>
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="hello@mail.com" value="<?= !$login ? h($email) : '' ?>" required>
                        <label for="password">Password</label>
                        <div class="pw-wrap">
                            <input type="password" id="password" name="password" placeholder="At least 8 characters" minlength="8" required>
                            <button type="button" class="eye" data-toggle="password" aria-label="Show password">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>
                        <div class="divider"><span>Already a user? <a href="#" data-auth="login">Login!</a></span></div>
                        <button type="submit" class="btn-solid">Create account</button>
                    </form>

                    <!-- BACK: log in -->
                    <form class="signup face back" id="loginFace" method="post" action="index.php#signup" <?= $login ? '' : 'inert' ?>>
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <h2>Welcome back</h2>
                        <?php if ($error && $login): ?><p class="form-error" role="alert"><?= h($error) ?></p><?php endif; ?>
                        <label for="loginEmail">Email</label>
                        <input type="email" id="loginEmail" name="email" placeholder="hello@mail.com" value="<?= $login ? h($email) : '' ?>" required>
                        <label for="loginPassword">Password</label>
                        <div class="pw-wrap">
                            <input type="password" id="loginPassword" name="password" placeholder="Please enter your password" required>
                            <button type="button" class="eye" data-toggle="loginPassword" aria-label="Show password">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>
                        <div class="divider"><span>New here? <a href="#" data-auth="signup">Sign up!</a></span></div>
                        <button type="submit" class="btn-solid">Log in</button>
                        
                    </form>

                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- WHY US -->
        <section class="why" id="whyus">
            <h2 class="title pink">Why us?</h2>
            <div class="arches">
                <article class="arch">
                    <h3>Personalised Study Notes</h3>
                    <p>Generate your study materials into notes</p>
                    <img src="Images/notes_sum.svg" alt="Open notebook with a flower" class="doodle">
                </article>
                <article class="arch">
                    <h3>Gamified Quiz</h3>
                    <p>Not only boring quizzes, but quizzes on game!</p>
                    <img src="Images/game.svg" alt="Game controller" class="doodle">
                </article>
                <article class="arch">
                    <h3>Text-to-Speech</h3>
                    <p>Turn your notes into audio, so you can hear it anywhere</p>
                    <img src="Images/text-speech.svg" alt="Face speaking" class="doodle">
                </article>
                <article class="arch">
                    <h3>Study Reminders</h3>
                    <p>Remind you to study and complete your goal!</p>
                    <img src="Images/remind.svg" alt="Alarm clock" class="doodle">
                </article>
            </div>
        </section>

        <!-- FAQS -->
        <section class="faq" id="faq">
            <h2 class="title pink">FAQs</h2>
            <div class="faq-list">
                <details>
                    <summary>How do I delete my account?</summary>
                    <p>Answer</p>
                </details>
                <details>
                    <summary>Do you offer a premium or subscription plan for unlimited generations?</summary>
                    <p>Answer</p>
                </details>
                <details>
                    <summary>How can I update my personal information or email address?</summary>
                    <p>Answer</p>
                </details>
                <details>
                    <summary>What types of files can I upload to generate notes?</summary>
                    <p>Answer</p>
                </details>
                <details>
                    <summary>Can I edit the notes or flashcards after they are generated?</summary>
                    <p>Answer</p>
                </details>
                <details>
                    <summary>How do I share my generated study materials with my classmates?</summary>
                    <p>Answer</p>
                </details>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="footer">
            <div class="footer-inner">
                <div class="footer-brand">
                    <img src="Images/AISNH_Cat coloredlogo(S).png" alt="AI Study Note Helper logo" class="footer-logo">
                    <h3>AI Study Note Helper</h3>
                    <p>Turn your notes into summaries, audio and quizzes that feel like play.</p>
                </div>
                <div class="footer-col">
                    <h4>Explore</h4>
                    <?php if ($loggedIn): ?>
                        <a href="studyhub.php">Study Hub</a>
                        <a href="logout.php">Log out</a>
                    <?php else: ?>
                        <a href="#signup" data-auth="signup">Sign up</a>
                        <a href="#signup" data-auth="login">Log in</a>
                    <?php endif; ?>
                </div>
                <div class="footer-col">
                    <h4>Help</h4>
                    <a href="#faq">FAQs</a>
                    <a href="#">Contact us</a>
                    <a href="#">Privacy policy</a>
                </div>
            </div>
            <p class="footer-bottom">&copy; <span id="year"></span> AI Study Note Helper. All rights reserved.</p>
        </footer>

        <!-- BACK TO TOP BUTTON -->
        <button type="button" class="to-top" id="toTop" aria-label="Back to top">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 19V5M5 12l7-7 7 7" />
            </svg>
        </button>
    </body>
</html>