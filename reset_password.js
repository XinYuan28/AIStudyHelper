(() => {
    const form = document.getElementById('resetForm');
    if (!form) return;
    const token = location.hash.slice(1);
    history.replaceState(null, '', location.pathname);
    if (/^[a-f0-9]{64}$/.test(token)) {
        document.getElementById('resetToken').value = token;
        form.hidden = false;
    }
})();
