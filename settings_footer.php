<?php
// settings_footer.php — closes what settings_header.php opened and adds the shared JS.
?>
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

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { closeMenu(); logoutModal.hidden = true; }
            });

            // after a failed send, jump to the first error
            var firstError = document.querySelector('.field-error');
            if (firstError) firstError.scrollIntoView({ block: 'center' });
        })();
    </script>

</body>

</html>