<?php
$sidebarNote = max(0, (int) ($_GET['note'] ?? 0));
$sidebarHub = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'studyhub.php';
$sidebarQuery = $sidebarNote ? 'note=' . $sidebarNote . '&' : '';
?>
<link rel="stylesheet" href="feature_sidebar.css?v=<?= filemtime(__DIR__ . '/feature_sidebar.css') ?>">
<button type="button" class="feature-toggle" id="featureToggle" aria-controls="featureSidebar" aria-expanded="false" aria-label="Open features menu">☰</button>
<aside class="feature-sidebar" id="featureSidebar" hidden aria-label="Study features">
    <div class="feature-sidebar-heading">
        <strong>Study features</strong>
        <button type="button" id="featureClose" aria-label="Close features menu">×</button>
    </div>
    <nav class="feature-navigation" aria-label="Features">
        <a class="side-item" href="studyhub.php">Study Hub</a>
        <a class="side-item" href="studyhub.php?<?= htmlspecialchars($sidebarQuery) ?>panel=game" <?= $sidebarHub ? 'data-panel="game"' : '' ?>>Practice quiz</a>
        <a class="side-item" href="play_menu.php<?= $sidebarNote ? '?note=' . $sidebarNote : '' ?>" id="sideGame">Game Quiz</a>
        <a class="side-item" href="studyhub.php?<?= htmlspecialchars($sidebarQuery) ?>panel=custom" <?= $sidebarHub ? 'data-panel="custom"' : '' ?>>Customise Quiz</a>
        <a class="side-item" href="studyhub.php?<?= htmlspecialchars($sidebarQuery) ?>panel=tts" <?= $sidebarHub ? 'data-panel="tts"' : '' ?>>Text-to-Speech</a>
        <a class="side-item" href="reminders.php">Study reminders</a>
        <a class="side-item" href="trash.php">Trash</a>
    </nav>
    <p id="featureMessage" class="feature-message" role="status"></p>
</aside>
<script src="feature_sidebar.js?v=<?= filemtime(__DIR__ . '/feature_sidebar.js') ?>" defer></script>
