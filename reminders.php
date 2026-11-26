<?php
require 'auth.php';
require_login();
$accountEmail = (string) ($_SESSION['email'] ?? '');
$needsGooglePrompt = !preg_match('/@(gmail|googlemail)\.com$/iD', $accountEmail);
$noteId = (int) ($_GET['note'] ?? 0);
$fromNote = $noteId > 0;
$showSidebarToggle = !$fromNote;
$breadcrumbTitle = 'Study Reminders';
if ($fromNote) {
    $noteTitle = db()->prepare('SELECT title FROM notes WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
    $noteTitle->execute([$noteId, (int) $_SESSION['user_id']]);
    $breadcrumbTitle = $noteTitle->fetchColumn() ?: $breadcrumbTitle;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Study reminders | Study Helper</title>
    <link rel="icon" type="image/png" href="Images/AISNH_Cat coloredlogo(S).png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="studyhub.css?v=<?= filemtime(__DIR__ . '/studyhub.css') ?>">
    <link rel="stylesheet" href="reminders.css?v=<?= filemtime(__DIR__ . '/reminders.css') ?>">
    <script>
        const theme = localStorage.getItem('theme');
        if (theme === 'dark' || theme === 'yellow') document.documentElement.dataset.theme = theme;
    </script>
    <?php if (!$fromNote): ?>
        <script src="studyhub_sidebar.js?v=<?= filemtime(__DIR__ . '/studyhub_sidebar.js') ?>" defer></script>
    <?php endif; ?>
    <script src="studyhub_feature_header.js?v=<?= filemtime(__DIR__ . '/studyhub_feature_header.js') ?>" defer></script>
</head>
<body<?= $fromNote ? '' : ' class="home-active feature-mobile-menu"' ?>>
<?php if (!$fromNote) require __DIR__ . '/studyhub_sidebar.php'; ?>
<?php require __DIR__ . '/studyhub_feature_header.php'; ?>
<?php if (!$fromNote): ?>
    <?php
    $sidebarMobileMenu = true;
    require __DIR__ . '/studyhub_sidebar.php';
    unset($sidebarMobileMenu);
    ?>
<?php endif; ?>
<?php if ($fromNote): ?>
<div class="lesson">
    <?php require __DIR__ . '/studyhub_sidebar.php'; ?>
<?php endif; ?>
<main class="reminder-page<?= $fromNote ? ' lesson-main' : '' ?>">
    <header class="reminder-header"><span id="todayLabel"></span></header>
    <div class="reminder-layout">
        <section class="calendar-pane" aria-label="Study calendar">
            <div class="calendar-toolbar">
                <div class="calendar-heading"><h1 id="calendarMonth"></h1>
                <div class="calendar-date-picker"><label for="reminderDate">Date</label><input id="reminderDate" type="date" form="reminderForm" required></div></div>
                <div class="calendar-navigation"><button type="button" id="calendarToday">Today</button><button type="button" id="calendarPrevious" aria-label="Previous month">‹</button><button type="button" id="calendarNext" aria-label="Next month">›</button></div>
            </div>
            <div class="calendar-weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
            <div id="calendarDays" class="calendar-days" role="group" aria-label="Choose a date"></div>
            <p class="calendar-hint">Choose a day to see its study reminders.</p>
        </section>
        <section class="reminder-editor card">
            <form id="reminderForm">
                <h2 id="formTitle">Study reminders</h2>
                <p id="selectedDateLabel"></p>
                <div class="reminder-date-time">
                    <fieldset class="study-period">
                        <legend>Period of study</legend>
                        <div class="duration-inputs">
                            <div><label for="hours">Hours</label><input type="number" id="hours" name="hours" min="0" step="1" placeholder="0"></div>
                            <div><label for="minutes">Minutes</label><input type="number" id="minutes" name="minutes" min="0" max="59" step="1" placeholder="0"></div>
                        </div>
                    </fieldset>
                    <div class="study-start"><label for="reminderTime">Start time</label><input id="reminderTime" type="time" required></div>
                </div>
                <p id="sessionPreview" role="status" aria-live="polite"></p>
                <label for="reminderTitle">What will you study?</label>
                <select id="reminderTitle" required><option value="">Loading your notes…</option></select>
                <p id="noteSelectionStatus" role="status"></p>
                <label for="reminderDescription">Note (optional)</label>
                <textarea id="reminderDescription" rows="3" maxlength="2000"></textarea>
                <label for="reminderMethod">Remind me through</label>
                <select id="reminderMethod"><option value="calendar">Calendar</option><option value="telegram">Telegram</option></select>
                <fieldset id="googleAccountPrompt" class="google-account-prompt" hidden>
                    <legend>Do you have a Google account?</legend>
                    <label class="google-account-choice"><input type="checkbox" id="hasGoogleAccount"> Yes, I have a Google account</label>
                    <p>You can have a Google account with a non-Gmail email. Without one, leave this unchecked and use Add to calendar (.ics) with your calendar app.</p>
                </fieldset>
                <div id="reminderLeadField" hidden>
                    <label for="reminderLead">Telegram notification</label>
                    <select id="reminderLead">
                        <option value="0">At study start</option>
                        <option value="60">1 hour before</option>
                        <option value="30">30 minutes before</option>
                        <option value="15">15 minutes before</option>
                        <option value="5">5 minutes before</option>
                    </select>
                </div>
                <p id="methodHelp">After saving, add the event to your calendar and check its alert. Calendar copies need to be updated separately.</p>
                <button type="submit" class="btn" id="saveReminder">Add reminder</button>
                <button type="button" class="btn ghost" id="resetReminder" hidden>Cancel editing</button>
            </form>
            <p id="reminderStatus" role="status" aria-live="polite"></p>
            <h3>On this day</h3>
            <section aria-label="Reminders on selected day" id="reminderList"></section>
            <details class="telegram-settings" id="telegramConnection">
                <summary>Telegram connection</summary>
                <p id="telegramStatus" role="status"></p>
                <button type="button" class="btn" id="connectTelegram">Connect Telegram</button>
                <a id="telegramLink" class="telegram-open-link" hidden target="_blank" rel="noopener"><span>Open NotePawBot in Telegram</span><span aria-hidden="true">↗</span></a><p id="telegramStartHint" hidden>Press Start in Telegram to finish connecting your account.</p>
                <button type="button" class="btn ghost" id="checkTelegram">Check connection</button>
                <button type="button" class="btn ghost" id="disconnectTelegram" hidden>Disconnect</button>
            </details>
        </section>
    </div>
</main>
<?php if ($fromNote): ?>
</div>
<?php endif; ?>
<script>window.NEEDS_GOOGLE_ACCOUNT_PROMPT = <?= json_encode($needsGooglePrompt) ?>; window.STUDYHUB_CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="reminders.js?v=<?= filemtime(__DIR__ . '/reminders.js') ?>"></script>
</body>
</html>
