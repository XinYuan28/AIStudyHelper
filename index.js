// Password show/hide (works for both faces)
document.querySelectorAll('[data-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.toggle);
        input.type = input.type === 'password' ? 'text' : 'password';
    });
});

// Flip card: sign up <-> log in (only when logged out)
const flip = document.getElementById('signup');
const signupFace = document.getElementById('signupFace');
const loginFace = document.getElementById('loginFace');
function setAuth(mode) {
    if (!signupFace) return;
    const login = mode === 'login';
    flip.classList.toggle('flipped', login);
    signupFace.inert = login;
    loginFace.inert = !login;
}
document.querySelectorAll('[data-auth]').forEach(el => {
    el.addEventListener('click', e => {
        setAuth(el.dataset.auth);
        if (el.getAttribute('href') === '#') e.preventDefault();
    });
});

// Color theme
const root = document.documentElement;
const themes = ['light', 'dark', 'yellow'];
const savedTheme = localStorage.getItem('theme');
if (savedTheme === 'dark' || savedTheme === 'yellow') {
    root.dataset.theme = savedTheme;
}

document.getElementById('themeToggle').addEventListener('click', () => {
    const current = root.dataset.theme || 'light';
    const next = themes[(themes.indexOf(current) + 1) % themes.length];
    if (next === 'light') {
        delete root.dataset.theme;
    } else {
        root.dataset.theme = next;
    }
    localStorage.setItem('theme', next);
});

// Footer year
document.getElementById("year").textContent = new Date().getFullYear();

// Back to top button
const toTop = document.getElementById('toTop');
function updateToTop() { toTop.classList.toggle('show', window.scrollY > 400); }
window.addEventListener('scroll', updateToTop, { passive: true });
updateToTop();
toTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
