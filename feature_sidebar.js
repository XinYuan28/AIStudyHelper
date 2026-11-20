(() => {
    const toggle = document.getElementById('featureToggle');
    const sidebar = document.getElementById('featureSidebar');
    const close = document.getElementById('featureClose');
    const mobile = window.matchMedia('(max-width: 760px)');
    function setOpen(open, focus = false) {
        sidebar.hidden = !open;
        document.body.classList.toggle('features-open', open);
        document.body.classList.add('has-feature-sidebar');
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close features menu' : 'Open features menu');
        if (focus) (open ? close : toggle).focus();
    }
    setOpen(!mobile.matches);
    document.querySelectorAll('.feature-navigation a').forEach(link => {
        const target = new URL(link.href);
        if (target.pathname === location.pathname && !target.search) {
            link.setAttribute('aria-current', 'page');
        }
    });
    toggle.onclick = () => setOpen(sidebar.hidden, true);
    close.onclick = () => setOpen(false, true);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !sidebar.hidden) setOpen(false, true);
    });
    mobile.addEventListener('change', () => setOpen(!mobile.matches));
})();
