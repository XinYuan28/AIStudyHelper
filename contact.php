<?php
// contact.php — Settings > Contact us
// Saves each message in the `contact_messages` table (see the CREATE TABLE in the chat).
require 'auth.php';
require_login();

$pdo    = db();
$userId = current_user_id();

$stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(404);
    exit('User not found.');
}

$subjects = [
    'question' => 'I have a question',
    'problem'  => 'Report a problem',
    'feedback' => 'Feedback or an idea',
    'account'  => 'Help with my account',
    'other'    => 'Something else',
];

$errors  = [];
$subject = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $subject = $_POST['subject'] ?? '';
    $message = trim((string) ($_POST['message'] ?? ''));

    if (!array_key_exists($subject, $subjects)) {
        $errors['subject'] = 'Please choose a topic.';
    }
    $len = mb_strlen($message);
    if ($len < 10) {
        $errors['message'] = 'Please write at least 10 characters so we can help.';
    } elseif ($len > 2000) {
        $errors['message'] = 'Your message is too long (max 2000 characters).';
    }

    if (!$errors) {
        try {
            // simple spam guard: max 5 messages per hour
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM contact_messages WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
            $cnt->execute([$userId]);
            if ((int) $cnt->fetchColumn() >= 5) {
                $errors['general'] = 'You have sent a few messages already. Please wait a while before sending another.';
            } else {
                $ins = $pdo->prepare('INSERT INTO contact_messages (user_id, email, subject, message) VALUES (?, ?, ?, ?)');
                $ins->execute([$userId, $user['email'], $subject, $message]);
                header('Location: contact.php?sent=1');
                exit;
            }
        } catch (Throwable $e) {
            $errors['general'] = 'Sorry, we could not send your message right now. Please try again later.';
        }
    }
}

$sent       = isset($_GET['sent']);
$pageTitle  = 'Contact us';
$activePage = 'contact';
require 'settings_header.php';
?>
                <h2 class="panel-title">Contact us</h2>
                <p class="lead">Questions, problems or ideas? We'd love to hear from you.</p>

                <?php if ($sent): ?>
                    <div class="feedback ok" role="status">Thank you! Your message has been sent. We'll reply to your account email.</div>
                <?php endif; ?>

                <form class="card form-grid" method="post" action="contact.php" novalidate>
                    <?= csrf_field() ?>
                    <h3>Send us a message</h3>

                    <?php if (isset($errors['general'])): ?><p class="field-error"><?= h($errors['general']) ?></p><?php endif; ?>

                    <div class="field">
                        <label for="replyTo">We'll reply to</label>
                        <input type="email" class="input" id="replyTo" value="<?= h($user['email']) ?>" readonly>
                    </div>

                    <div class="field <?= isset($errors['subject']) ? 'has-error' : '' ?>">
                        <label for="subject">Topic</label>
                        <select class="input" id="subject" name="subject" required>
                            <option value="" disabled <?= $subject === '' ? 'selected' : '' ?>>Select…</option>
                            <?php foreach ($subjects as $val => $label): ?>
                                <option value="<?= h($val) ?>" <?= $subject === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['subject'])): ?><p class="field-error"><?= h($errors['subject']) ?></p><?php endif; ?>
                    </div>

                    <div class="field <?= isset($errors['message']) ? 'has-error' : '' ?>">
                        <label for="message">Message</label>
                        <textarea class="input" id="message" name="message" rows="6" maxlength="2000" required placeholder="Tell us how we can help…"><?= h($message) ?></textarea>
                        <?php if (isset($errors['message'])): ?><p class="field-error"><?= h($errors['message']) ?></p><?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn">Send message</button>
                    </div>
                </form>

                <div class="card">
                    <h3>Prefer email?</h3>
                    <p class="field-hint">You can also reach us at <a href="mailto:<?= h(SUPPORT_EMAIL) ?>"><?= h(SUPPORT_EMAIL) ?></a>.</p>
                    <p class="field-hint">Looking for a quick answer? Check the <a href="faq.php">FAQs</a> first.</p>
                </div>
<?php require 'settings_footer.php'; ?>