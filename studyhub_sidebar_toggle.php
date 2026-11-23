<?php if (!empty($showSettingsMenuToggle)): ?>
    <button type="button" class="lesson-menu-toggle" id="lessonMenuToggle"
            aria-label="Settings menu" aria-expanded="false" aria-controls="lessonFeatureMenu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m6 9 6 6 6-6" />
        </svg>
    </button>
<?php else: ?>
<button type="button" class="icon-btn" id="homeSidebarToggle" aria-controls="homeSidebar" aria-expanded="false" aria-label="Show the menu" hidden>
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 6.5h16M4 12h16M4 17.5h16" /></svg>
</button>
<?php endif; ?>
