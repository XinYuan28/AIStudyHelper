(() => {
    const root = document.documentElement;
    const themeBtn = document.getElementById("themeToggle");
    const THEMES = ['light', 'dark', 'yellow'];
    const svgOpen = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';
    // the button shows the theme you will switch TO: moon = dark, eye = eye-saver (yellow), sun = light
    const THEME_ICONS = {
        light: svgOpen + '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/></svg>',
        dark: svgOpen + '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>',
        yellow: svgOpen + '<path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>'
    };
    const THEME_NAMES = { light: 'Light', dark: 'Dark', yellow: 'Eye saver' };

    function updateThemeButton() {
        const t = root.dataset.theme || 'light';
        const next = THEMES[(THEMES.indexOf(t) + 1) % THEMES.length];
        themeBtn.innerHTML = THEME_ICONS[next];
        themeBtn.setAttribute('aria-label', 'Switch to ' + THEME_NAMES[next].toLowerCase() + ' mode');
    }
    themeBtn.addEventListener('click', () => {
        const cur = root.dataset.theme || 'light';
        const next = THEMES[(THEMES.indexOf(cur) + 1) % THEMES.length];
        if (next === 'light') {
            delete root.dataset.theme;
        } else {
            root.dataset.theme = next;
        }
        try { localStorage.setItem('theme', next); } catch {}
        updateThemeButton();
    });
    updateThemeButton();

    // avatar menu (Profile / Security / Privacy policy / Contact us / FAQs)
    const userMenuToggle = document.getElementById('userMenuToggle');
    const userMenuBox = document.getElementById('userMenu');
    function setUserMenu(open) {
        userMenuBox.hidden = !open;
        userMenuToggle.setAttribute('aria-expanded', String(open));
    }
    userMenuToggle.addEventListener('click', function () { setUserMenu(userMenuBox.hidden); });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.user-wrap')) setUserMenu(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !userMenuBox.hidden) {
            setUserMenu(false);
            userMenuToggle.focus();
        }
    });

    // log out confirm (same popup as Study Hub)
    const logoutModal = document.getElementById('logoutConfirmModal');
    document.getElementById('logoutLink').addEventListener('click', function (e) {
        e.preventDefault();
        logoutModal.hidden = false;
    });
    document.getElementById('logoutCancel').addEventListener('click', function () {
        logoutModal.hidden = true;
    });
    document.getElementById('logoutConfirm').addEventListener('click', function () {
        window.location.href = 'logout.php';
    });
    logoutModal.addEventListener('click', function (e) {
        if (e.target === logoutModal) logoutModal.hidden = true;
    });
})();
