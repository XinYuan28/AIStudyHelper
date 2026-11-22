(() => {
    const sidebar = document.getElementById('homeSidebar');
    const toggle = document.getElementById('homeSidebarToggle');
    const close = document.getElementById('homeSidebarClose');

    function setOpen(open) {
        sidebar.inert = !open;
        toggle.hidden = open;
        toggle.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('sb-open', open);
    }

    function changeOpen(open) {
        document.body.classList.add('sb-motion');
        setOpen(open);
        try { localStorage.setItem('homeSidebar', open ? 'open' : 'closed'); } catch {}
        (open ? sidebar : toggle).focus();
    }

    let saved = null;
    try { saved = localStorage.getItem('homeSidebar'); } catch {}
    const narrow = window.matchMedia('(max-width: 760px)').matches;
    setOpen(narrow ? saved === 'open' : saved !== 'closed');

    toggle.addEventListener('click', () => changeOpen(true));
    close.addEventListener('click', () => changeOpen(false));
    sidebar.addEventListener('keydown', event => {
        if (event.key === 'Escape') changeOpen(false);
    });
})();
