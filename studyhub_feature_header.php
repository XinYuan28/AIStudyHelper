<?php
$userName = $_SESSION['name'];
$initial = mb_strtoupper(mb_substr($userName, 0, 1));
?>
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
<header class="topbar feature-topbar">
    <div class="topbar-left">
        <div class="topbar-brand">
            <a href="studyhub.php"><img src="Images/AISNH_Cat coloredlogo(S).png" alt="AI Study Note Helper" class="logo"></a>
            <?php if ($showSidebarToggle) require __DIR__ . '/studyhub_sidebar_toggle.php'; ?>
        </div>
        <div class="crumbs">
            <a href="studyhub.php">Home</a>
            <span>&rsaquo;</span>
            <span class="here"><?= h($breadcrumbTitle) ?></span>
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
