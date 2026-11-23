<?php
$sidebarPage = basename($_SERVER['SCRIPT_NAME'] ?? 'studyhub.php');
$sidebarOnHub = $sidebarPage === 'studyhub.php';
$sidebarNote = $sidebarOnHub ? 0 : max(0, (int) ($_GET['note'] ?? 0));
$sidebarStudyFeature = in_array($sidebarPage, ['play_menu.php', 'reminders.php'], true);
$sidebarNoteFeature = $sidebarStudyFeature && $sidebarNote > 0;
$sidebarMobileMenu = $sidebarMobileMenu ?? false;
$sidebarCollapsible = $sidebarNoteFeature || $sidebarMobileMenu;
$sidebarNoteUrl = $sidebarNote ? '?note=' . $sidebarNote : '';
$sidebarNoteQuery = $sidebarNote ? 'note=' . $sidebarNote . '&amp;' : '';
$sidebarHomeFeature = $sidebarPage === 'trash.php' ||
    ($sidebarStudyFeature && !$sidebarNoteFeature);
?>
<?php if ($sidebarCollapsible): ?>
<div class="lesson-feature-menu<?= $sidebarMobileMenu ? ' feature-menu-mobile-only' : '' ?>">
    <button type="button" class="lesson-menu-toggle" id="lessonMenuToggle"
            aria-label="Study features" aria-expanded="false" aria-controls="lessonFeatureMenu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m6 9 6 6 6-6" />
        </svg>
    </button>
<?php endif; ?>
<nav class="sidebar<?= $sidebarCollapsible ? '' : ' home-sidebar' ?>"<?= $sidebarCollapsible ? ' id="lessonFeatureMenu"' : ' id="homeSidebar" tabindex="-1"' ?> aria-label="Study features">
    <a class="side-item<?= $sidebarOnHub ? ' home-feature-home' : '' ?>" href="studyhub.php<?= $sidebarNoteFeature ? $sidebarNoteUrl : '' ?>">
        <span class="side-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?php if ($sidebarNoteFeature): ?><rect x="4" y="3" width="16" height="18" rx="3" /><path d="M8 8h8M8 12h8M8 16h5" /><?php else: ?><path d="m3 10 9-7 9 7M5 9v11h5v-6h4v6h5V9" /><?php endif; ?></svg></span>
        <?= $sidebarNoteFeature ? 'Notes' : ($sidebarOnHub || $sidebarHomeFeature ? 'Home' : 'Study Hub') ?>
    </a>
    <?php if (!$sidebarOnHub && !$sidebarHomeFeature): ?>
    <a class="side-item" href="studyhub.php?<?= $sidebarNoteQuery ?>panel=game">
        <span class="side-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="4" width="14" height="17" rx="2.5" /><path d="M9.5 4V3h5v1M9 13l2.2 2.2L15.5 11" /></svg></span>
        Practice quiz
    </a>
    <?php endif; ?>
    <a class="side-item" href="play_menu.php<?= $sidebarNoteUrl ?>"<?= $sidebarPage === 'play_menu.php' ? ' aria-current="page"' : '' ?>>
        <span class="side-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2.5" y="7" width="19" height="11" rx="5" /><path d="M7 10v5M4.5 12.5h5M15.5 11h.01M18 14h.01" /></svg></span>
        Game Quiz
    </a>
    <?php if ($sidebarOnHub): ?>
    <button type="button" class="side-item" data-panel="custom">
    <?php else: ?>
    <a class="side-item" href="studyhub.php?<?= $sidebarNoteQuery ?>panel=custom">
    <?php endif; ?>
        <span class="side-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 7h10M18 7h2M4 17h2M10 17h10" /><circle cx="16" cy="7" r="2" /><circle cx="8" cy="17" r="2" /></svg></span>
        Customise Quiz
    <?= $sidebarOnHub ? '</button>' : '</a>' ?>
    <?php if (!$sidebarOnHub && !$sidebarHomeFeature): ?>
    <a class="side-item" href="studyhub.php?<?= $sidebarNoteQuery ?>panel=tts">
        <span class="side-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 10v4h4l5 4V6L8 10H4z" /><path d="M16.5 9a4 4 0 0 1 0 6M19 6.5a8 8 0 0 1 0 11" /></svg></span>
        Text-to-Speech
    </a>
    <?php endif; ?>
    <a class="side-item" href="reminders.php<?= $sidebarNoteUrl ?>"<?= $sidebarPage === 'reminders.php' ? ' aria-current="page"' : '' ?>>
        <span class="side-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 16v-5a6 6 0 0 1 12 0v5l1.5 2h-15z" /><path d="M10 20.5a2 2 0 0 0 4 0" /></svg></span>
        Study reminders
    </a>
    <?php if ($sidebarOnHub || $sidebarHomeFeature): ?>
        <a class="side-item" href="trash.php"<?= $sidebarPage === 'trash.php' ? ' aria-current="page"' : '' ?>>
            <span class="side-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3" /></svg></span>
            Trash
        </a>
    <?php endif; ?>
    <?php if ($sidebarOnHub): ?>
        <p id="featureMessage" class="feature-message" role="status"></p>
    <?php endif; ?>
    <?php if (!$sidebarCollapsible): ?>
        <button type="button" class="side-item home-sidebar-close" id="homeSidebarClose" aria-controls="homeSidebar" aria-label="Hide the menu">
            <span class="side-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="m6 6 12 12M18 6 6 18" /></svg></span>
        </button>
    <?php endif; ?>
</nav>
<?php if ($sidebarCollapsible): ?>
</div>
<?php endif; ?>
