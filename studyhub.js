/* Study Hub logic. Needs window.STUDYHUB_USER and window.STUDYHUB_CSRF (set by studyhub.php first).
   Notes, files and quiz results are saved in the database through api.php. */

/* HELPERS */
const $ = id => document.getElementById(id);

// lower-case, trim, collapse spaces and drop punctuation so "Answer 1." matches "answer 1"
function norm(s) {
    return String(s).toLowerCase().replace(/[^\p{L}\p{N}\s]/gu, '').replace(/\s+/g, ' ').trim();
}

function isTextCorrect(q, typed) {
    const t = norm(typed);
    return !!t && [q.answer].concat(q.accept || []).some(a => norm(a) === t);
}

// what to show as "the correct answer" for either question type
function correctText(q) {
    return q.type === 'text' ? q.answer : q.options[q.answer];
}

function el(tag, cls, text) {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
}

/* TALKING TO THE DATABASE (through api.php)
   GET for reading, POST (with the csrf token) for writing. Throws an Error with a friendly message. */
async function apiCall(action, fields, method) {
    let url = 'api.php?action=' + encodeURIComponent(action);
    const opts = {
        credentials: 'same-origin'
    };
    if (method === 'POST') {
        const fd = new FormData();
        fd.append('csrf', window.STUDYHUB_CSRF);
        Object.entries(fields || {}).forEach(([k, v]) => {
            if (Array.isArray(v)) v.forEach(item => fd.append(k + '[]', item));
            else fd.append(k, v);
        });
        opts.method = 'POST';
        opts.body = fd;
    } else if (fields) {
        url += '&' + new URLSearchParams(fields);
    }
    let res;
    try {
        res = await fetch(url, opts);
    } catch (e) {
        throw new Error('Could not reach the server. Please check your connection.');
    }
    let data = null;
    try {
        data = await res.json();
    } catch (e) {}
    if (res.status === 401) {
        window.location.href = 'index.php'; // logged out: back to the login page
        throw new Error('Please log in again.');
    }
    if (!res.ok || !data || !data.ok) throw new Error((data && data.error) || 'Something went wrong. Please try again.');
    return data;
}

function ago(ts) {
    const m = Math.max(0, Math.floor((Date.now() - ts) / 60000));
    if (m < 1) return 'Updated just now';
    if (m < 60) return 'Updated ' + m + ' min ago';
    const h = Math.floor(m / 60);
    if (h < 24) return 'Updated ' + h + (h === 1 ? ' hour ago' : ' hours ago');
    const d = Math.floor(h / 24);
    return 'Updated ' + d + (d === 1 ? ' day ago' : ' days ago');
}

let lessons = []; // [{id, title, files: [names], folderId, updated: ms}]
let folders = [];
let viewingFolders = false;
let activeFolderId = null;
const folderIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h7l2 2h9v12H3z"/></svg>';
let listState = 'loading'; // 'loading' | 'ready' | 'error'
let listError = '';
let current = null;
let notesText = '';
let speechText = '';
let speechDisplay = ''; // what the Text-to-Speech box shows (readable, not sent to the voice)
let speechPayload = ''; // same as speechText, but each table is wrapped in [[T0]]...[[/T0]] markers
let speechTables = [];  // the raw Markdown tables, sent to Gemini for narration
let currentQuestions = [];
let pendingPanel = ''; // set from ?panel= in the address, e.g. coming back from the Game Quiz page
let homeCustom = false; // true when Customise Quiz was opened from the HOME page (no note open yet): the "which note?" column is shown
let customNoteId = 0; // the saved note picked in that column
let customNotesText = ''; // its generated notes (kept so the note opens normally once the quiz starts)
let deleteMode = false; // true after "Delete" is chosen in a note's menu: checkboxes appear on every note
const selectedIds = new Set(); // ids of the notes ticked for deletion

// turns whatever the server sends into {q, options[], answer (index), topic, explain, type}
// tolerant on purpose: options may be a JSON string or option_a..option_d columns, and the answer may be an index, a letter (A-D) or the answer text
function normaliseQuestions(questions) {
    const raw = Array.isArray(questions) ? questions : [];
    const out = raw.map(q => {
        if (!q) return null;
        let options = q.options;
        if (typeof options === 'string') {
            try {
                options = JSON.parse(options);
            } catch (e) {
                options = null;
            }
        }
        if (!Array.isArray(options)) {
            options = ['a', 'b', 'c', 'd'].map(k => q['option_' + k] !== undefined ? q['option_' + k] : q['option' + k.toUpperCase()])
                .filter(o => o !== undefined && o !== null && o !== '');
        }
        options = options.map(String);
        let answer = q.answer !== undefined ? q.answer : (q.correct_answer !== undefined ? q.correct_answer : q.correct);
        if (typeof answer === 'string') {
            const t = answer.trim();
            if (/^[A-D]$/i.test(t)) answer = t.toUpperCase().charCodeAt(0) - 65;
            else if (/^\d+$/.test(t)) answer = Number(t);
            else answer = options.indexOf(t);
        }
        return {
            q: q.q || q.question || '',
            options: options,
            answer: Number(answer),
            topic: q.topic || 'General',
            explain: q.explain || q.explanation || '',
            type: q.type || 'mcq'
        };
    }).filter(q => q && q.q && q.options.length >= 2 && Number.isInteger(q.answer) && q.answer >= 0 && q.answer < q.options.length);
    if (raw.length && !out.length) console.warn('Study Hub: the server sent ' + raw.length + ' questions but none could be used. The first one was:', raw[0]);
    else if (!raw.length) console.warn('Study Hub: the server sent no quiz questions for this note.');
    return out;
}

async function refreshList() {
    try {
        const r = await apiCall('list');
        lessons = r.notes.map(n => ({
            id: n.id,
            title: n.title,
            files: n.files,
            folderId: n.folder_id,
            updated: n.ts * 1000
        }));
        folders = r.folders;
        if (activeFolderId !== null && !folders.some(f => f.id === activeFolderId)) {
            activeFolderId = null;
            viewingFolders = true;
        }
        listState = 'ready';
    } catch (err) {
        listState = 'error';
        listError = err.message;
    }
    renderList();
}

/* THEME & GREETING */
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
    localStorage.setItem('theme', next);
    updateThemeButton();
});
updateThemeButton();

/* USER MENU: the avatar in the top bar opens Profile / Security / Privacy policy / Contact us / FAQs */
const userMenuToggle = $('userMenuToggle');
const userMenuBox = $('userMenu');

function setUserMenu(open) {
    userMenuBox.hidden = !open;
    userMenuToggle.setAttribute('aria-expanded', String(open));
}
userMenuToggle.addEventListener('click', () => setUserMenu(userMenuBox.hidden));
document.addEventListener('click', e => {
    if (!e.target.closest('.user-wrap')) setUserMenu(false);
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !userMenuBox.hidden) {
        setUserMenu(false);
        userMenuToggle.focus();
    }
});

(function greet() {
    const h = new Date().getHours();
    const part = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
    const name = window.STUDYHUB_USER;
    $('greeting').textContent = part + (name && name !== 'there' ? ', ' + name : '') + '!';
})();

/* HOME - LIST OF NOTES AND UPLOAD FILE*/
const bookIcon = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5.5C5 4.5 8 4.5 12 6c4-1.5 7-1.5 9-.5V19c-2-1-5-1-9 .5-4-1.5-7-1.5-9-.5V5.5zM12 6v13.5" /></svg>';

function visibleNotes() {
    return activeFolderId === null ? lessons : lessons.filter(l => l.folderId === activeFolderId);
}

function showNoteList(foldersView, folderId = null) {
    viewingFolders = foldersView;
    activeFolderId = folderId;
    exitDeleteMode();
}

function renderList() {
    closeNoteMenus();
    const folder = folders.find(f => f.id === activeFolderId);
    $('noteListTitle').textContent = viewingFolders ? 'Folders' : folder?.name || 'Notes';
    $('folderBreadcrumb').hidden = !folder;
    $('folderBack').hidden = !folder || deleteMode;
    $('folderViewBtn').hidden = !!folder || deleteMode;
    $('folderViewLabel').textContent = viewingFolders ? 'Notes' : 'Folders';
    $('folderViewIcon').innerHTML = viewingFolders ? bookIcon : folderIcon;
    $('uploadBtn').hidden = viewingFolders || deleteMode;
    $('newFolderBtn').hidden = !viewingFolders || listState !== 'ready';
    const box = $('noteList');
    box.textContent = '';
    if (listState === 'loading') {
        box.appendChild(el('p', 'note-msg', 'Loading your notes…'));
        return;
    }
    if (listState === 'error') {
        const e = el('div', 'empty');
        e.appendChild(el('b', '', 'Could not load your notes'));
        e.appendChild(el('span', '', listError + ' Click here to try again.'));
        e.addEventListener('click', refreshList);
        box.appendChild(e);
        return;
    }
    if (viewingFolders) {
        if (!folders.length) box.appendChild(el('p', 'empty', 'No folders yet. Create one to organise your notes.'));
        folders.forEach(f => {
            const row = el('button', 'note-row folder-row');
            row.type = 'button';
            const ico = el('span', 'ico');
            ico.innerHTML = folderIcon;
            const info = el('span', 'note-info');
            info.append(el('strong', 'folder-row-name', f.name),
                el('small', '', f.note_count + (f.note_count === 1 ? ' note · ' : ' notes · ') + ago(f.ts * 1000)));
            row.append(ico, info, el('span', 'folder-chevron', '›'));
            row.addEventListener('click', () => {
                showNoteList(false, f.id);
                $('folderBack').focus();
            });
            box.appendChild(row);
        });
        return;
    }
    const notes = visibleNotes();
    if (!notes.length) {
        const e = el('div', 'empty');
        e.appendChild(el('b', '', folder ? 'This folder is empty' : 'No notes yet'));
        e.appendChild(el('span', '', 'Click here or drop your files (up to ' + MAX_TOTAL_MB + ' MB in total) to upload study materials.'));
        e.addEventListener('click', () => pick(null, activeFolderId));
        ['dragover', 'dragenter'].forEach(t => e.addEventListener(t, ev => {
            ev.preventDefault();
            e.classList.add('drag');
        }));
        ['dragleave', 'drop'].forEach(t => e.addEventListener(t, () => e.classList.remove('drag')));
        e.addEventListener('drop', ev => {
            ev.preventDefault();
            if (ev.dataTransfer.files.length) addLesson(ev.dataTransfer.files, null, activeFolderId);
        });
        box.appendChild(e);
        return;
    }
    const folderNames = new Map(folders.map(f => [f.id, f.name]));
    notes.slice().sort((a, b) => b.updated - a.updated).forEach(l => {
        const row = el('div', 'note-row');
        row.dataset.noteId = l.id;
        const ico = el('div', 'ico');
        ico.innerHTML = bookIcon;
        const info = el('div', 'note-info');
        const head = el('div', 'note-head');
        head.appendChild(el('h3', '', l.title));
        if (l.files && l.files.length > 1) head.appendChild(el('span', 'file-badge', l.files.length + ' files'));
        info.appendChild(head);
        const meta = el('div', 'note-meta');
        meta.appendChild(el('small', '', ago(l.updated)));
        const folderName = folderNames.get(l.folderId);
        if (folderName && !folder) {
            const tag = el('span', 'file-badge folder-tag');
            tag.innerHTML = folderIcon;
            tag.appendChild(el('span', '', folderName));
            tag.title = folderName;
            meta.appendChild(tag);
        }
        info.appendChild(meta);

        // a real link: opens the note in a new tab (so several notes can be open at once); Ctrl/middle-click work too
        const open = el('a', 'btn ghost small open-note-btn');
        open.href = '?note=' + encodeURIComponent(l.id);
        open.target = '_blank';
        open.rel = 'noopener';
        open.title = 'Open ' + l.title + ' in a new tab';
        open.setAttribute('aria-label', 'Open ' + l.title + ' in a new tab');
        open.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>';

        const edit = el('button', 'note-menu-item');
        edit.type = 'button';
        edit.setAttribute('role', 'menuitem');
        edit.innerHTML = pencilIcon;
        edit.appendChild(el('span', '', 'Rename'));
        edit.addEventListener('click', () => askRename(l));

        const conv = el('button', 'note-menu-item');
        conv.type = 'button';
        conv.setAttribute('role', 'menuitem');
        conv.innerHTML = convertIcon;
        conv.appendChild(el('span', '', 'Export to PDF…'));
        conv.addEventListener('click', () => convertNote(l, conv));

        const del = el('button', 'note-menu-item danger');
        del.type = 'button';
        del.setAttribute('role', 'menuitem');
        del.innerHTML = trashIcon;
        del.appendChild(el('span', '', 'Delete'));
        del.addEventListener('click', () => enterDeleteMode(l));

        const acts = el('div', 'note-actions');
        const menuWrap = el('div', 'note-menu-wrap');
        const menuToggle = el('button', 'note-menu-toggle');
        menuToggle.type = 'button';
        menuToggle.setAttribute('aria-label', 'More actions for ' + l.title);
        menuToggle.setAttribute('aria-haspopup', 'menu');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>';
        const menu = el('div', 'note-menu');
        menu.setAttribute('role', 'menu');
        menu.hidden = true;
        menu._noteMenuWrap = menuWrap;
        const move = el('button', 'note-menu-item');
        move.type = 'button';
        move.setAttribute('role', 'menuitem');
        move.innerHTML = folderIcon;
        move.appendChild(el('span', '', folder ? 'Remove from folder' : 'Move to folder'));
        move.addEventListener('click', () => {
            if (folder) askRemoveFromFolder(l, menuToggle);
            else openFolderDialog(l, menuToggle);
        });
        menu.append(edit, move, conv, del);
        menuToggle.addEventListener('click', () => {
            const willOpen = menu.hidden;
            closeNoteMenus();
            if (!willOpen) return;
            document.body.appendChild(menu);
            menu.hidden = false;
            menuToggle.setAttribute('aria-expanded', 'true');
            const rect = menuToggle.getBoundingClientRect();
            const menuWidth = menu.offsetWidth;
            const menuHeight = menu.offsetHeight;
            const left = Math.max(8, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8));
            const below = window.innerHeight - rect.bottom;
            const top = below >= menuHeight + 8 || rect.top < menuHeight + 8
                ? Math.min(rect.bottom + 8, window.innerHeight - menuHeight - 8)
                : rect.top - menuHeight - 8;
            menu.style.left = left + 'px';
            menu.style.top = Math.max(8, top) + 'px';
        });
        menu.addEventListener('click', closeNoteMenus);
        menuWrap.append(menuToggle, menu);
        acts.append(open, menuWrap);
        if (deleteMode) {
            // checkbox only appears after Delete is chosen
            const pick = el('label', 'select-box');
            const cb = el('input');
            cb.type = 'checkbox';
            cb.checked = selectedIds.has(l.id);
            cb.setAttribute('aria-label', 'Select ' + l.title + ' for deletion');
            cb.addEventListener('change', () => {
                if (cb.checked) selectedIds.add(l.id);
                else selectedIds.delete(l.id);
                row.classList.toggle('selected', cb.checked);
                updateDeleteBar();
            });
            const tick = el('span', 'select-tick');
            tick.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
            pick.append(cb, tick);
            acts.appendChild(pick);
            row.classList.toggle('selected', cb.checked);
        }
        row.append(ico, info, acts);
        box.appendChild(row);
    });
}

function closeNoteMenus() {
    document.querySelectorAll('.note-menu').forEach(menu => {
        menu.hidden = true;
        menu.style.left = '';
        menu.style.top = '';
        if (menu._noteMenuWrap) menu._noteMenuWrap.appendChild(menu);
    });
    document.querySelectorAll('.note-menu-toggle').forEach(button => button.setAttribute('aria-expanded', 'false'));
}

document.addEventListener('click', e => {
    if (e.target.closest('.note-menu') || e.target.closest('.note-menu-toggle')) return;
    closeNoteMenus();
});
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    closeNoteMenus();
});

const pencilIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4" /></svg>';

const convertIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" /><path d="M14 3v5h5" /><path d="M12 11.5v5.5M9.5 14.8 12 17.3l2.5-2.5" /></svg>';

/* CONVERT A SAVED NOTE (from the home list): loads its saved notes, then opens the same PDF / slides / text box */
let converting = false;
async function convertNote(l, btn) {
    if (converting) return;
    converting = true;
    btn.classList.add('busy');
    try {
        const r = await apiCall('get', {
            id: l.id
        });
        const secs = r.content ? parseNotes(r.content) : [];
        if (!secs.length) {
            setHint('"' + l.title + '" has no generated notes yet. Open it and generate notes first, then you can convert them.', true);
        } else {
            openExport(secs, l.title);
        }
    } catch (err) {
        setHint(err.message, true);
    }
    btn.classList.remove('busy');
    converting = false;
}

/* DELETE WITH CONFIRMATION */
const trashIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3" /></svg>';
let pendingDelete = []; // the notes waiting for the user's confirmation
let pendingFolderRemoval = null;
let confirmTrigger = null;

// "Delete" in a note's menu starts delete mode: checkboxes appear and that note is ticked already
function enterDeleteMode(l) {
    deleteMode = true;
    selectedIds.clear();
    if (l) selectedIds.add(l.id);
    syncDeleteMode();
}

function exitDeleteMode() {
    deleteMode = false;
    selectedIds.clear();
    syncDeleteMode();
}

function syncDeleteMode() {
    $('deleteBar').hidden = !deleteMode;
    updateDeleteBar();
    renderList();
}

function updateDeleteBar() {
    const n = selectedIds.size;
    $('deleteSelected').textContent = 'Delete (' + n + ')';
    $('deleteSelected').disabled = n === 0;
    $('deleteSelectAll').textContent = n && n === visibleNotes().length ? 'Clear all' : 'Select all';
}

$('deleteCancel').addEventListener('click', exitDeleteMode);
$('deleteSelectAll').addEventListener('click', () => {
    const notes = visibleNotes();
    if (selectedIds.size === notes.length) selectedIds.clear();
    else notes.forEach(l => selectedIds.add(l.id));
    updateDeleteBar();
    renderList();
});
$('deleteSelected').addEventListener('click', () => askDelete(lessons.filter(l => selectedIds.has(l.id))));

function askDelete(list) {
    if (!list.length) return;
    pendingDelete = list;
    if (list.length === 1) {
        $('confirmTitle').textContent = 'Delete this note?';
        $('confirmText').textContent = '"' + list[0].title + '" will move to Trash. You can restore it for 10 days before it is permanently deleted.';
    } else {
        $('confirmTitle').textContent = 'Delete ' + list.length + ' notes?';
        $('confirmText').textContent = 'These ' + list.length + ' notes will move to Trash. You can restore them for 10 days before they are permanently deleted.';
    }
    showConfirm('Delete', $('deleteSelected'));
}

function askRemoveFromFolder(note, trigger) {
    pendingFolderRemoval = note;
    $('confirmTitle').textContent = 'Remove this note from the folder?';
    $('confirmText').textContent = '"' + note.title + '" will move to Unsorted and remain in your Notes list.';
    showConfirm('Remove', trigger);
}

function showConfirm(label, trigger) {
    confirmTrigger = trigger;
    $('confirmOk').textContent = label;
    $('confirmModal').hidden = false;
    $('confirmCancel').focus();
}

function closeConfirm() {
    pendingDelete = [];
    pendingFolderRemoval = null;
    $('confirmModal').hidden = true;
    confirmTrigger?.focus();
    confirmTrigger = null;
}
$('confirmCancel').addEventListener('click', closeConfirm);
$('confirmModal').addEventListener('click', e => {
    if (e.target === $('confirmModal')) closeConfirm();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !$('confirmModal').hidden) closeConfirm();
});
$('confirmOk').addEventListener('click', async () => {
    const list = pendingDelete;
    const note = pendingFolderRemoval;
    closeConfirm();
    if (note) {
        await removeFromFolder(note);
        return;
    }
    if (!list.length) return;
    let failed = 0,
        lastError = '';
    for (const l of list) {
        try {
            await apiCall('delete', {
                id: l.id
            }, 'POST'); // soft delete: the row stays in the table with deleted_at filled in
            lessons = lessons.filter(x => x.id !== l.id);
            selectedIds.delete(l.id);
        } catch (err) {
            failed++;
            lastError = err.message;
        }
    }
    if (failed) {
        await refreshList();
        // keep delete mode open with the failed notes still ticked so the user can try again
        setHint(failed + (failed === 1 ? ' note' : ' notes') + ' could not be deleted. ' + lastError, true);
        updateDeleteBar();
        renderList();
    } else {
        exitDeleteMode();
        await refreshList();
    }
});

/* LOG OUT WITH CONFIRMATION */
const logoutModal = $('logoutConfirmModal');
const logoutLink = $('logoutLink');
logoutLink.addEventListener('click', e => {
    e.preventDefault();
    logoutModal.hidden = false;
    $('logoutCancel').focus();
});
$('logoutCancel').addEventListener('click', () => { logoutModal.hidden = true; });
logoutModal.addEventListener('click', e => {
    if (e.target === logoutModal) logoutModal.hidden = true;
});
$('logoutConfirm').addEventListener('click', () => {
    window.location.href = logoutLink.href;
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !logoutModal.hidden) logoutModal.hidden = true;
});

/* FOLDER NAVIGATION AND ACTIONS */
$('folderViewBtn').addEventListener('click', () => showNoteList(!viewingFolders));
$('folderBack').addEventListener('click', () => {
    const previousId = activeFolderId;
    showNoteList(true);
    const index = folders.findIndex(f => f.id === previousId);
    ($('noteList').children[index] || $('folderViewBtn')).focus();
});

function fillFolderOptions(select, folderId, allowCreate) {
    select.replaceChildren(new Option('No folder (Unsorted)', ''));
    folders.slice().sort((a, b) => a.name.localeCompare(b.name)).forEach(f => {
        select.add(new Option(f.name, String(f.id)));
    });
    if (allowCreate) select.add(new Option('+ Create new folder', 'new'));
    select.value = folders.some(f => f.id === folderId) ? String(folderId) : '';
}

let movingNote = null;
let folderDialogTrigger = null;

async function removeFromFolder(note) {
    try {
        await apiCall('move_note', { id: note.id, folder_id: '' }, 'POST');
    } catch (err) {
        setHint(err.message, true);
        return;
    }
    selectedIds.delete(note.id);
    await refreshList();
    updateDeleteBar();
    if (!$('folderBack').hidden) $('folderBack').focus();
}

function openFolderDialog(note, trigger) {
    movingNote = note;
    folderDialogTrigger = trigger;
    $('folderTitle').textContent = note ? 'Move to folder' : 'New folder';
    $('newFolderField').hidden = !!note;
    $('newFolderName').required = !note;
    $('newFolderName').value = '';
    $('moveFolderField').hidden = !note;
    $('folderSubmit').textContent = note ? 'Move' : 'Create';
    $('folderError').hidden = true;
    if (note) fillFolderOptions($('moveFolder'), note.folderId, false);
    $('folderModal').hidden = false;
    $(note ? 'moveFolder' : 'newFolderName').focus();
}

function closeFolderDialog() {
    if ($('folderSubmit').disabled) return;
    $('folderModal').hidden = true;
    folderDialogTrigger?.focus();
}

$('newFolderBtn').addEventListener('click', () => openFolderDialog(null, $('newFolderBtn')));
$('folderCancel').addEventListener('click', closeFolderDialog);
$('folderModal').addEventListener('click', e => {
    if (e.target === $('folderModal')) closeFolderDialog();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !$('folderModal').hidden) closeFolderDialog();
});
$('folderForm').addEventListener('submit', async e => {
    e.preventDefault();
    if ($('folderSubmit').disabled) return;
    $('folderSubmit').disabled = true;
    $('folderError').hidden = true;
    const note = movingNote;
    try {
        if (note) {
            await apiCall('move_note', { id: note.id, folder_id: $('moveFolder').value }, 'POST');
        } else {
            await apiCall('create_folder', { folder_name: $('newFolderName').value.trim() }, 'POST');
        }
        await refreshList();
        $('folderSubmit').disabled = false;
        closeFolderDialog();
        if (note) {
            const row = $('noteList').querySelector('[data-note-id="' + note.id + '"]');
            const fallback = activeFolderId === null ? $('folderViewBtn') : $('folderBack');
            (row?.querySelector('.note-menu-toggle') || fallback).focus();
        }
    } catch (err) {
        $('folderError').textContent = err.message;
        $('folderError').hidden = false;
    } finally {
        $('folderSubmit').disabled = false;
    }
});

/* UPLOAD / GENERATE FROM HOME */
let pendingAction = null;
let pendingFolderId = null;

function pick(action, folderId = null) {
    pendingAction = action;
    pendingFolderId = folderId;
    $('fileInput').click();
}
$('uploadBtn').addEventListener('click', () => pick(null, activeFolderId));
$('homeNotes').addEventListener('click', () => pick('notes'));
$('homeQuiz').addEventListener('click', () => pick('quiz'));
$('fileInput').addEventListener('change', e => {
    const files = Array.from(e.target.files);
    const action = pendingAction;
    pendingAction = null;
    if (files.length) addLesson(files, action, pendingFolderId);
    pendingFolderId = null;
    e.target.value = '';
});

/* UPLOAD LIMITS: one note can have many files, but all its files together must stay under MAX_TOTAL_MB.
   (api.php has the same two numbers; change them in both places.) */
const MAX_TOTAL_MB = 100;
const MAX_FILES = 20; // only a safety net so one note does not get hundreds of files
const ALLOWED_EXT = /\.(pdf|docx|pptx|xlsx|odt|rtf|txt|md|csv|mp3|m4a|wav)$/i;
const HINT_DEFAULT = 'Tip: you can add several files (PDF, Word, PowerPoint, spreadsheet, text or audio), up to ' + MAX_TOTAL_MB + ' MB in total, to make one set of notes.';
$('uploadHint').textContent = HINT_DEFAULT;
let hintTimer;

function fmtSize(bytes) {
    if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(bytes >= 10 * 1024 * 1024 ? 0 : 1) + ' MB';
    return Math.max(1, Math.round(bytes / 1024)) + ' KB';
}

// the line shown under a note's name: the file names, or just a count when there are many
function filesSummary(names, sizes) {
    if (!names || !names.length) return '';
    if (names.length <= 3) return names.join(' · ');
    const total = (sizes || []).reduce((a, b) => a + (b || 0), 0);
    return names.length + ' files' + (total ? ' · ' + fmtSize(total) + ' in total' : '');
}

// shows the tip under the buttons; a warning turns red and goes back to the tip after a few seconds
function setHint(msg, warn) {
    const h = $('uploadHint');
    h.textContent = msg;
    h.classList.toggle('warn', !!warn);
    clearTimeout(hintTimer);
    if (warn) hintTimer = setTimeout(() => setHint(HINT_DEFAULT, false), 6000);
}

function addLesson(fileList, action, folderId = null) {
    const files = Array.from(fileList || []);
    if (!files.length) return;
    // reject the whole selection (instead of quietly dropping files) so the user knows what happened
    if (files.some(f => !ALLOWED_EXT.test(f.name))) {
        setHint('Only PDF, Word, PowerPoint, spreadsheet, text and audio files are supported.', true);
        return;
    }
    if (files.length > MAX_FILES) {
        setHint('You selected ' + files.length + ' files, but one note can have up to ' + MAX_FILES + '. Please pick fewer files.', true);
        return;
    }
    if (files.some(file => file.size > window.STUDYHUB_UPLOAD_BYTES)) {
        setHint('A file exceeds ' + fmtSize(window.STUDYHUB_UPLOAD_BYTES) + '. Re-upload the entire batch at smaller sizes.', true);
        return;
    }
    const total = files.reduce((a, f) => a + f.size, 0);
    if (total > MAX_TOTAL_MB * 1024 * 1024) {
        setHint('Your files add up to ' + fmtSize(total) + ', but one note can hold up to ' + MAX_TOTAL_MB + ' MB in total. Re-upload the entire batch at smaller sizes.', true);
        return;
    }
    setHint(HINT_DEFAULT, false);
    askName(files.map(f => f.name), action, files.map(f => f.size), files, folderId);
}

/* NAME YOUR NOTES (shown after the files are picked) */
let pendingUpload = null; // a new upload waiting for its name
let pendingRename = null; // an existing note being renamed

// default name = first file's name without the extension
function defaultName(names) {
    return names[0].replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').trim() || 'Untitled notes';
}

function openNameBox(heading, okText, files, value) {
    $('nameTitle').textContent = heading;
    $('nameOk').textContent = okText;
    $('nameFiles').textContent = files;
    $('nameFiles').hidden = !files;
    $('nameInput').value = value;
    $('saveFolderFields').hidden = !pendingUpload;
    $('nameError').hidden = true;
    updateSaveFolderField();
    $('nameModal').hidden = false;
    $('nameInput').focus();
    $('nameInput').select();
}

function updateSaveFolderField() {
    const creating = !!pendingUpload && $('saveFolder').value === 'new';
    $('saveFolderNameField').hidden = !creating;
    $('saveFolderName').required = creating;
}
$('saveFolder').addEventListener('change', updateSaveFolderField);

function askName(names, action, sizes, files, folderId) {
    pendingRename = null;
    pendingUpload = {
        names: names,
        sizes: sizes || [],
        files: files || [],
        action: action
    };
    fillFolderOptions($('saveFolder'), folderId, true);
    $('saveFolderName').value = '';
    openNameBox('Name your notes', 'Save notes', filesSummary(names, sizes), defaultName(names));
}

function askRename(l) {
    pendingUpload = null;
    pendingRename = l;
    openNameBox('Rename your notes', 'Save', filesSummary(l.files), l.title);
}

function closeName() {
    pendingUpload = null;
    pendingRename = null;
    $('nameModal').hidden = true;
}
$('nameCancel').addEventListener('click', closeName);
$('nameModal').addEventListener('click', e => {
    if (e.target === $('nameModal')) closeName();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !$('nameModal').hidden) closeName();
});
$('nameForm').addEventListener('submit', async e => {
    e.preventDefault();
    if (pendingRename) {
        const l = pendingRename;
        const title = $('nameInput').value.trim() || l.title; // an empty name keeps the old one
        closeName();
        if (title !== l.title) {
            try {
                await apiCall('rename', {
                    id: l.id,
                    title: title
                }, 'POST');
                await refreshList();
            } catch (err) {
                setHint(err.message, true);
            }
        }
        renderList();
        return;
    }
    if (!pendingUpload) return;
    const up = pendingUpload;
    const title = $('nameInput').value.trim() || defaultName(up.names);
    const destination = $('saveFolder').value;
    const folder = destination === 'new'
        ? { folder_name: $('saveFolderName').value.trim() }
        : { folder_id: destination };
    $('nameModal').hidden = true;
    try {
        await createLesson(title, up, folder);
        closeName();
    } catch (err) {
        $('nameError').textContent = err.message;
        $('nameError').hidden = false;
        $('nameModal').hidden = false;
        $(destination === 'new' ? 'saveFolderName' : 'nameInput').focus();
    }
});

// saves the note and its files in the database first, then opens it
// small centred popup shown while the server saves the files and makes the note; it cannot be dismissed, so it closes itself when done
function showGenerating(title) {
    $('generatingName').textContent = title;
    $('generatingModal').hidden = false;
    document.querySelector('#generatingModal .modal-box').focus();
}

function hideGenerating() {
    $('generatingModal').hidden = true;
}

async function createLesson(title, upload, folder) {
    let r;
    showGenerating(title);
    try {
        r = await apiCall('create', {
            title: title,
            uploads: upload.files,
            ...folder
        }, 'POST');
    } finally {
        hideGenerating();
    }
    const l = {
        id: r.id,
        title: r.title,
        files: r.files,
        folderId: r.folder_id,
        updated: r.ts * 1000
    };
    lessons.unshift(l);
    currentQuestions = normaliseQuestions(r.questions);
    notesText = r.notes || '';
    openLesson(l.id, true);
    if (upload.action === 'notes') buildNotes('full');
    else if (upload.action === 'quiz') startQuiz(currentQuestions.slice(0, 8));
}

/* NAVIGATION */
const panels = ['start', 'notes', 'game', 'custom', 'tts'];

// studyhub.php?note=5 opens note 5 straight away (that is the link the "open" button uses, in a new tab)
function setNoteUrl(id, panel = '') {
    try {
        const u = new URL(location.href);
        if (id) u.searchParams.set('note', id);
        else u.searchParams.delete('note');
        if (panel) u.searchParams.set('panel', panel);
        else u.searchParams.delete('panel');
        history.replaceState(null, '', u);
    } catch (e) {}
}

function showHome() {
    stopSpeech();
    current = null;
    leaveHomeCustom();
    deleteMode = false;
    selectedIds.clear();
    $('deleteBar').hidden = true;
    setNoteUrl(0);
    document.body.classList.add('home-active');
    $('viewLesson').hidden = true;
    $('crumbs').hidden = true;
    $('viewHome').hidden = false;
    renderList();
    refreshList(); // pick up anything saved while inside a note (e.g. focus notes)
}

// fresh = just created, so there is nothing saved to load yet
function openLesson(id, fresh) {
    current = lessons.find(l => l.id === id);
    if (!current) return;
    leaveHomeCustom(); // opened inside a note: Customise Quiz has no "which note?" column
    setNoteUrl(current.id);
    const noteQuery = '?note=' + encodeURIComponent(current.id);
    $('sideGame').href = 'play_menu.php' + noteQuery;
    $('sideReminders').href = 'reminders.php' + noteQuery;
    speechText = '';
    speechDisplay = '';
    speechPayload = '';
    speechTables = [];
    if (!fresh) {
        notesText = '';
        currentQuestions = [];
    }
    $('notesBody').textContent = '';
    $('notesLead').textContent = '';
    $('notesSaved').hidden = true;
    $('startTitle').textContent = current.title;
    $('startFiles').textContent = filesSummary(current.files);
    $('crumbTitle').textContent = current.title;
    $('crumbs').hidden = false;
    document.body.classList.remove('home-active');
    $('viewHome').hidden = true;
    $('viewLesson').hidden = false;
    $('quizArea').textContent = '';
    showPanel('start');

    // an existing note that already has notes saved: show them straight away
    if (!fresh) {
        const openedId = current.id;
        apiCall('get', {
            id: openedId
        }).then(r => {
            if (!current || current.id !== openedId) return;
            const stillHere = !$('panel-start').hidden && !notesText;
            currentQuestions = normaliseQuestions(r.questions);
            if (!$('panel-custom').hidden) updateQuizCount();
            if (!notesText) notesText = r.content || '';
            const want = pendingPanel; // 'custom' or 'tts' when we came back from the Game Quiz page
            pendingPanel = '';
            if (stillHere && want === 'game') {
                startQuiz(currentQuestions.slice(0, 8));
                return;
            }
            if (stillHere && r.content) {
                renderNotes(parseNotes(r.content), r.mode === 'personalised' ? 'Focus notes made from your quiz mistakes.' : 'Generated from your study materials.', '');
                showPanel(want === 'custom' || want === 'tts' ? want : 'notes');
            } else if (stillHere && (want === 'custom' || want === 'tts')) {
                showPanel(want);
            }
        }).catch(err => {
            if (current && current.id === openedId) {
                $('startFiles').textContent = err.message;
            }
        });
    }
}

function showPanel(name) {
    if (name !== 'tts') stopSpeech();
    panels.forEach(p => $('panel-' + p).hidden = (p !== name));
    document.querySelectorAll('.side-item').forEach(b => b.classList.toggle('active', b.dataset.panel === name));
    window.scrollTo({
        top: 0
    });
    if (name === 'tts') fillTts();
    if (name === 'custom') updateQuizCount();
    if (name === 'game') loadQuizHistory();
}

/* CUSTOMISE QUIZ FROM THE HOME PAGE
   Opened from the home sidebar there is no note yet, so the form gets an extra first column: "which saved note?".
   Opened inside a note (note sidebar) that column stays hidden and the form is exactly as before. */
function fillCustomNotes() {
    const sel = $('customNote');
    const keep = customNoteId;
    sel.replaceChildren(el('option', '', lessons.length ? 'Choose a note…' : 'You have no saved notes yet'));
    sel.options[0].value = '';
    lessons.slice().sort((a, b) => b.updated - a.updated).forEach(l => {
        const o = el('option', '', l.title + (l.files && l.files.length > 1 ? ' (' + l.files.length + ' files)' : ''));
        o.value = String(l.id);
        sel.appendChild(o);
    });
    sel.value = keep && lessons.some(l => l.id === keep) ? String(keep) : '';
    sel.disabled = !lessons.length;
}

function openHomeCustom() {
    stopSpeech();
    current = null;
    homeCustom = true;
    customNoteId = 0;
    customNotesText = '';
    currentQuestions = [];
    notesText = '';
    $('customNoteField').hidden = false;
    $('viewLesson').classList.add('home-custom');
    document.body.classList.add('home-active', 'home-custom-active');
    setNoteUrl(0, 'custom');
    $('crumbTitle').textContent = 'Customise Quiz';
    $('crumbs').hidden = false;
    $('viewHome').hidden = true;
    $('viewLesson').hidden = false;
    fillCustomNotes();
    if (listState !== 'ready') refreshList().then(fillCustomNotes);
    showPanel('custom');
}

// back to the normal (note) version of the form
function leaveHomeCustom() {
    homeCustom = false;
    document.body.classList.remove('home-custom-active');
    customNoteId = 0;
    customNotesText = '';
    $('customNoteField').hidden = true;
    $('viewLesson').classList.remove('home-custom');
    $('customStart').disabled = false;
}

$('customNote').addEventListener('change', async () => {
    const id = Number($('customNote').value) || 0;
    customNoteId = id;
    customNotesText = '';
    currentQuestions = [];
    updateQuizCount();
    if (!id) return;
    const start = $('customStart');
    start.disabled = true;
    try {
        const r = await apiCall('get', { id: id });
        if (customNoteId !== id) return; // another note was picked while this one was loading
        currentQuestions = normaliseQuestions(r.questions);
        customNotesText = r.content || '';
        updateQuizCount();
    } catch (err) {
        if (customNoteId !== id) return;
        $('customMsg').textContent = err.message;
        $('customMsg').classList.add('warn');
        $('customMsg').hidden = false;
    }
    if (customNoteId === id) start.disabled = false;
});

$('crumbHome').addEventListener('click', showHome);
$('logoLink').addEventListener('click', e => {
    if (current || homeCustom) {
        e.preventDefault();
        showHome();
    }
});

// only the sidebar buttons that open a panel (Game Quiz is now a plain link to play_menu.php)
document.querySelectorAll('.side-item[data-panel]').forEach(b => b.addEventListener('click', event => {
    event.preventDefault();
    const p = b.dataset.panel;
    if (!current && p === 'custom') {
        // from the home page: open Customise Quiz with the "which saved note?" column
        const fm = $('featureMessage');
        if (fm) fm.textContent = '';
        openHomeCustom();
        return;
    }
    if (!current) {
        pendingPanel = p;
        $('featureMessage').textContent = 'Open your study materials to use this feature.';
        $('viewHome').scrollIntoView({ block: 'start' });
        return;
    }
    $('featureMessage').textContent = '';
    // Nothing generated yet: let the user choose notes or quiz instead of forcing one
    if (p === 'notes' && !notesText && !$('quizArea').childElementCount) showPanel('start');
    else if (p === 'game' && !$('quizArea').childElementCount && !notesText) showPanel('start');
    else if (p === 'notes' && !notesText) buildNotes('full');
    else if (p === 'game' && !$('quizArea').childElementCount) startQuiz(currentQuestions.slice(0, 8));
    else showPanel(p);
}));

$('chooseNotes').addEventListener('click', () => buildNotes('full'));
$('chooseQuiz').addEventListener('click', () => startQuiz(currentQuestions.slice(0, 8)));
$('notesToTts').addEventListener('click', () => showPanel('tts'));
$('notesToQuiz').addEventListener('click', () => startQuiz(currentQuestions.slice(0, 8)));

/* EXPORT NOTES: PDF, slides (.pptx) or plain text. Everything is made in the browser (libraries are in the lib folder). */
function notesForExport() {
    return currentSections.slice();
}

function exportName(title) {
    return (title || (current && current.title) || 'Study notes').replace(/[\\/:*?"<>|]+/g, '').trim() || 'study-notes';
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

const BULLET = /^\s*[-•*]\s*/;
const bodyLines = p => p.split('\n').map(l => l.trim()).filter(Boolean);

function exportTxt(secs, title) {
    const text = title + '\n' + '='.repeat(title.length) + '\n\n' + secs.map(s => s.h + (s.p ? '\n' + s.p : '')).join('\n\n') + '\n';
    downloadBlob(new Blob([text], {
        type: 'text/plain;charset=utf-8'
    }), exportName(title) + '.txt');
}

// Loads one of the helper libraries from the lib/ folder (kept inside the project, so no internet is needed).
function loadLib(src, isReady, what) {
    if (isReady()) return Promise.resolve();
    return new Promise((ok, fail) => {
        const s = document.createElement('script');
        s.src = src;
        s.onload = ok;
        s.onerror = () => {
            s.remove();
            fail(new Error('Could not load the ' + what + '. Please check that the lib folder (with ' + src.split('/').pop() + ') is next to studyhub.php.'));
        };
        document.head.appendChild(s);
    });
}

// PDF: made in the browser and downloaded straight away (no print window).
// Each section of the notes is drawn as a picture of the browser's own text, so every language works.
// The text inside such a PDF cannot be selected or searched.
const PDF_CSS = 'body{margin:0;width:680px;font-family:"Segoe UI",Inter,system-ui,-apple-system,sans-serif;color:#2b2226;line-height:1.55;font-size:15.3px;overflow-wrap:anywhere}' +
    'h1{font-size:32px;line-height:1.2;margin:0 0 6px;color:#c8424d}' +
    '.sub{color:#6b5f62;font-size:13.3px;margin:0;padding-bottom:12px;border-bottom:2px solid #c8424d}' +
    '.blk{padding:0 0 18px;background:#fff}' +
    'h2{font-size:18.7px;line-height:1.3;margin:0 0 6px}' +
    'p{margin:0 0 5px}.li{position:relative;padding-left:22px;margin:0 0 3px}.li:before{content:"\\2022";position:absolute;left:8px}' +
    'table{border-collapse:collapse;width:100%;margin:6px 0 10px;font-size:14px}th,td{border:1px solid #cfc6c8;padding:5px 8px;text-align:left;vertical-align:top}th{background:#f3e6e8}';

function exportHtml(secs, title) {
    const page = document.implementation.createHTMLDocument(title);
    const charset = page.createElement('meta');
    charset.setAttribute('charset', 'utf-8');
    page.head.prepend(charset);
    const style = page.createElement('style');
    style.textContent = 'body{font:1rem/1.6 system-ui;max-width:48rem;margin:2rem auto;padding:0 1rem}p{white-space:pre-wrap;overflow-wrap:anywhere}' +
        'table{border-collapse:collapse;width:100%;margin:.8rem 0}th,td{border:1px solid #bbb;padding:.4rem .6rem;text-align:left;vertical-align:top}th{background:#f3e6e8}';
    page.head.append(style);
    page.body.append(el('h1', '', title));
    secs.forEach(sec => {
        const section = el('section');
        section.append(el('h2', '', sec.h));
        const blocks = sec.blocks && sec.blocks.length ? sec.blocks : [{ type: 'text', p: sec.p }];
        blocks.forEach(b => {
            if (b.type === 'table') {
                const t = el('table');
                b.rows.forEach((r, i) => {
                    const tr = el('tr');
                    r.forEach(c => tr.append(el(i ? 'td' : 'th', '', c)));
                    t.append(tr);
                });
                section.append(t);
            } else {
                section.append(el('p', '', b.p));
            }
        });
        page.body.append(section);
    });
    downloadBlob(new Blob(['<!doctype html>\n', page.documentElement.outerHTML], { type: 'text/html;charset=utf-8' }), exportName(title) + '.html');
}

async function exportPdf(secs, title) {
    await loadLib('lib/html2canvas.min.js', () => window.html2canvas, 'PDF maker');
    await loadLib('lib/jspdf.umd.min.js', () => window.jspdf, 'PDF maker');

    const esc = s => s.replace(/[&<>"]/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;'
    }[c]));
    const lineHtml = l => BULLET.test(l) ? '<div class="li">' + esc(l.replace(BULLET, '')) + '</div>' : '<p>' + esc(l) + '</p>';
    const tableHtml = rows => '<table>' + rows.map((r, i) => '<tr>' + r.map(c => (i ? '<td>' : '<th>') + esc(c) + (i ? '</td>' : '</th>')).join('') + '</tr>').join('') + '</table>';
    const blockHtml = b => b.type === 'table' ? tableHtml(b.rows) : bodyLines(b.p).map(lineHtml).join('');
    const sectionHtml = s => '<div class="blk"><h2>' + esc(s.h) + '</h2>' +
        (s.blocks && s.blocks.length ? s.blocks.map(blockHtml).join('') : bodyLines(s.p).map(lineHtml).join('')) + '</div>';
    const html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' + PDF_CSS + '</style></head><body>' +
        '<div class="blk"><h1>' + esc(title) + '</h1><p class="sub">Study notes · AI Study Note Helper</p></div>' +
        secs.map(sectionHtml).join('') + '</body></html>';

    // the notes are laid out in a hidden frame of their own (680 px wide = the 180 mm between the page margins)
    const W = 680,
        PX = W / 180,
        M = 15,
        BOTTOM = 297 - M;
    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:' + W + 'px;height:1200px;border:0';
    document.body.appendChild(frame);
    try {
        const d = frame.contentDocument;
        d.open();
        d.write(html);
        d.close();
        const pdf = new window.jspdf.jsPDF({
            unit: 'mm',
            format: 'a4'
        });
        let y = M;

        for (const el of Array.from(d.querySelectorAll('.blk'))) {
            const box = el.getBoundingClientRect();
            const total = box.height;
            const canvas = await window.html2canvas(el, {
                scale: 1.6,
                backgroundColor: '#ffffff',
                logging: false,
                windowWidth: W,
                windowHeight: Math.ceil(total) + 20
            });
            const k = canvas.height / total; // canvas pixels per layout pixel
            // places where a long section may be cut between two pages: the bottoms of its paragraphs and bullets
            const marks = Array.from(el.querySelectorAll('p,.li,tr')).map(n => n.getBoundingClientRect().bottom - box.top);
            let off = 0;
            while (off < total - 0.5) {
                const avail = (BOTTOM - y) * PX; // layout pixels left on this page
                let cut;
                if (total - off <= avail) {
                    cut = total;
                } else {
                    const ok = marks.filter(m => m > off + 12 && m <= off + avail);
                    if (ok.length) cut = Math.max.apply(null, ok);
                    else if (y > M + 0.1) { // nothing fits in the space left: start a new page
                        pdf.addPage();
                        y = M;
                        continue;
                    } else cut = off + avail; // one block taller than a page: cut it
                }
                let img = canvas;
                if (off > 0 || cut < total) {
                    img = document.createElement('canvas');
                    img.width = canvas.width;
                    img.height = Math.max(1, Math.round((cut - off) * k));
                    const g = img.getContext('2d');
                    g.fillStyle = '#fff';
                    g.fillRect(0, 0, img.width, img.height);
                    g.drawImage(canvas, 0, Math.round(off * k), canvas.width, img.height, 0, 0, canvas.width, img.height);
                }
                const h = (cut - off) / PX;
                pdf.addImage(img.toDataURL('image/jpeg', 0.8), 'JPEG', M, y, 180, h);
                y += h;
                off = cut;
                if (off < total - 0.5) {
                    pdf.addPage();
                    y = M;
                }
            }
        }
        pdf.save(exportName(title) + '.pdf');
    } finally {
        frame.remove();
    }
}

// SLIDES: one title slide, then one slide per note section (a long section continues on the next slide)
function loadPptx() {
    return loadLib('lib/pptxgen.bundle.js', () => window.PptxGenJS, 'slide maker');
}

// splits a section's lines into slide-sized groups (about 9 lines of 20pt text fit one slide)
function slideChunks(p) {
    const chunks = [];
    let cur = [],
        used = 0;
    bodyLines(p).forEach(l => {
        const text = l.replace(BULLET, '');
        const cost = Math.ceil(text.length / 90);
        if (cur.length && used + cost > 9) {
            chunks.push(cur);
            cur = [];
            used = 0;
        }
        cur.push(text);
        used += cost;
    });
    if (cur.length || !chunks.length) chunks.push(cur);
    return chunks;
}

async function exportPptx(secs, title) {
    await loadPptx();
    const pptx = new PptxGenJS();
    pptx.layout = 'LAYOUT_WIDE'; // 13.33 x 7.5 inches
    pptx.title = title;
    const CORAL = 'C8424D',
        INK = '2B2226',
        MUTED = '6B5F62';

    const first = pptx.addSlide();
    first.background = {
        color: CORAL
    };
    first.addText(title, {
        x: 0.8,
        y: 2.3,
        w: 11.7,
        h: 1.7,
        fontSize: 40,
        bold: true,
        color: 'FFFFFF',
        valign: 'middle',
        fit: 'shrink'
    });
    first.addText('Study notes · AI Study Note Helper', {
        x: 0.8,
        y: 4.1,
        w: 11.7,
        h: 0.5,
        fontSize: 18,
        color: 'FFFFFF'
    });

        const newSlide = title => {
        const sl = pptx.addSlide();
        sl.background = { color: 'FFFFFF' };
        sl.addShape(pptx.ShapeType.rect, { x: 0, y: 0, w: 0.25, h: 7.5, fill: { color: CORAL }, line: { color: CORAL } });
        sl.addText(title, { x: 0.8, y: 0.45, w: 11.7, h: 1.0, fontSize: 28, bold: true, color: CORAL, valign: 'middle', fit: 'shrink' });
        sl.slideNumber = { x: 12.3, y: 7.0, fontSize: 12, color: MUTED };
        return sl;
    };

    secs.forEach(sec => {
        const blocks = sec.blocks && sec.blocks.length ? sec.blocks : [{ type: 'text', p: sec.p }];
        let n = 0; // slides made for this section so far
        blocks.forEach(b => {
            if (b.type === 'table') {
                const head = b.rows[0];
                const body = b.rows.slice(1);
                for (let k = 0; k < Math.max(body.length, 1); k += 7) { // 7 rows per slide, header repeated
                    const sl = newSlide(sec.h + (n++ ? ' (continued)' : ''));
                    const rows = [head.map(c => ({ text: c, options: { bold: true, color: 'FFFFFF', fill: { color: CORAL } } }))]
                        .concat(body.slice(k, k + 7).map(r => r.map(c => ({ text: c }))));
                    sl.addTable(rows, { x: 0.8, y: 1.7, w: 11.7, fontSize: 16, color: INK, valign: 'middle', border: { type: 'solid', pt: 1, color: 'CCCCCC' } });
                }
            } else if (b.p.trim()) {
                slideChunks(b.p).forEach(chunk => {
                    const sl = newSlide(sec.h + (n++ ? ' (continued)' : ''));
                    if (chunk.length) {
                        sl.addText(chunk.map(t => ({ text: t, options: { bullet: true, breakLine: true } })), {
                            x: 0.8, y: 1.7, w: 11.7, h: 5.0, fontSize: 20, color: INK, valign: 'top', paraSpaceAfter: 8
                        });
                    }
                });
            }
        });
        if (!n) newSlide(sec.h);
    });
    await pptx.writeFile({
        fileName: exportName(title) + '.pptx'
    });
}

function closeExport() {
    $('exportModal').hidden = true;
    $('exportMsg').hidden = true;
}
// what the export box will save: {secs, title}. Either the note that is open, or a saved note picked on the home page.
let exportTarget = null;

// splits Markdown into text blocks and table blocks, so exports can draw real tables
function plainMd(t) {
    return String(t).replace(/^#{1,6}\s+/gm, '').replace(/(\*\*|__|`)/g, '').replace(/\n{3,}/g, '\n\n').trim();
}

function splitBlocks(md) {
    const lines = String(md).split('\n');
    const blocks = [];
    let text = [];
    const flush = () => {
        if (text.length) blocks.push({ type: 'text', p: plainMd(text.join('\n')) });
        text = [];
    };
    for (let i = 0; i < lines.length; i++) {
        if (/^\s*\|/.test(lines[i])) {
            flush();
            let rows = [];
            while (i < lines.length && /^\s*\|/.test(lines[i])) {
                const cells = lines[i].trim().replace(/^\||\|$/g, '').split('|').map(c => c.trim());
                if (!cells.every(c => /^:?-{2,}:?$/.test(c))) rows.push(cells.map(plainMd)); // skip the --- row
                i++;
            }
            i--;
            if (rows.length) {
                const w = rows[0].length; // make every row the same width
                rows = rows.map(r => r.concat(Array(Math.max(0, w - r.length)).fill('')).slice(0, w));
                blocks.push({ type: 'table', rows: rows });
            }
        } else {
            text.push(lines[i]);
        }
    }
    flush();
    return blocks.filter(b => b.type === 'table' || b.p);
}

function openExport(secs, title) {
    exportTarget = {
        secs: secs.map(s => ({ h: s.h, p: stripMd(s.p), blocks: splitBlocks(s.p) })),
        title: title
    };
    $('exportTitle').textContent = 'Export "' + title + '"';
    $('exportMsg').hidden = true;
    $('exportModal').hidden = false;
}
$('notesExport').addEventListener('click', () => {
    const secs = notesForExport();
    if (secs.length) openExport(secs, (current && current.title) || 'Study notes');
});
$('exportCancel').addEventListener('click', closeExport);
$('exportModal').addEventListener('click', e => {
    if (e.target === $('exportModal')) closeExport();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !$('exportModal').hidden) closeExport();
});
document.querySelectorAll('#exportModal [data-fmt]').forEach(b => b.addEventListener('click', async () => {
    if (!exportTarget || !exportTarget.secs.length) return;
    const secs = exportTarget.secs;
    const title = exportTarget.title;
    const fmt = b.dataset.fmt;
    const msg = $('exportMsg');
    const all = document.querySelectorAll('#exportModal [data-fmt]');
    all.forEach(x => x.disabled = true);
    try {
        if (fmt === 'pptx' || fmt === 'pdf') {
            msg.textContent = fmt === 'pdf' ? 'Making your PDF…' : 'Making your slides…';
            msg.classList.remove('warn');
            msg.hidden = false;
            await (fmt === 'pdf' ? exportPdf(secs, title) : exportPptx(secs, title));
        } else if (fmt === 'html') exportHtml(secs, title);
        else exportTxt(secs, title);
        closeExport();
    } catch (err) {
        msg.textContent = err.message || 'Could not export. Please try again.';
        msg.classList.add('warn');
        msg.hidden = false;
    }
    all.forEach(x => x.disabled = false);
}));

/* NOTES
   In the notes.content column a note is plain text: sections separated by a blank line,
   the first line of each section is its heading and the lines after it are its body. */
function parseNotes(content) {
    const text = String(content).replace(/\r\n/g, '\n');
    if (/^##\s+/m.test(text)) { // new Markdown notes: one section per "## " heading
        const out = [];
        let cur = null;
        text.split('\n').forEach(line => {
            const m = line.match(/^##\s+(.*)$/);
            if (m) {
                cur = { h: m[1].trim(), p: '' };
                out.push(cur);
            } else if (cur) {
                cur.p += line + '\n';
            } else if (line.trim()) {
                cur = { h: 'Overview', p: line + '\n' };
                out.push(cur);
            }
        });
        out.forEach(s => { s.p = s.p.trim(); });
        return out;
    }
    // old plain-text notes: sections separated by a blank line
    return text.split(/\n\s*\n/).map(b => b.trim()).filter(Boolean).map(b => {
        const nl = b.indexOf('\n');
        return nl === -1 ? { h: b, p: '' } : { h: b.slice(0, nl).trim(), p: b.slice(nl + 1).trim() };
    });
}

function serializeNotes(sections) {
    return sections.map(s => s.h + '\n' + s.p).join('\n\n');
}

// draws the notes card and prepares the text that Text-to-Speech reads
let currentSections = []; // the sections now on screen (raw Markdown), used by Export

// Markdown -> safe HTML (falls back to plain text if the libraries are missing)
function mdToHtml(text) {
    if (window.marked && window.DOMPurify) {
        return DOMPurify.sanitize(marked.parse(text, { gfm: true, breaks: true }));
    }
    return text.replace(/[&<>]/g, c => '&#' + c.charCodeAt(0) + ';').replace(/\n/g, '<br>');
}

// Markdown -> plain text (for text-to-speech, export and the short summaries)
function stripMd(t) {
    return String(t)
        .replace(/^[ \t]*\|?[ \t:|-]+\|[ \t:|-]*$/gm, '') // table divider rows
        .replace(/^[ \t]*\|(.+)\|[ \t]*$/gm, (m, row) => row.split('|').map(c => c.trim()).filter(Boolean).join(' · '))
        .replace(/^#{1,6}\s+/gm, '')
        .replace(/(\*\*|__|`)/g, '')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

// Text-to-speech: Markdown -> plain text, with tables read out in a simple way
function speechMd(t, tables, heading) {
    const lines = String(t).split('\n');
    const out = [];
    for (let i = 0; i < lines.length; i++) {
        if (/^\s*\|/.test(lines[i])) {
            const raw = [], rows = [];
            while (i < lines.length && /^\s*\|/.test(lines[i])) {
                raw.push(lines[i].trim());
                rows.push(lines[i].trim().replace(/^\||\|$/g, '').split('|').map(c => c.trim()));
                i++;
            }
            i--;
            const head = rows[0] || [];
            const fb = ['Table with columns ' + head.join(', ') + '.'];
            rows.slice(2).forEach(r => {
                const rest = r.slice(1).map((c, n) => (head[n + 1] ? head[n + 1] + ': ' : '') + c).join('. ');
                fb.push(r[0] + '. ' + rest + '.');
            });
            if (tables) { // wrap the simple version in markers; the server swaps in the AI narration
                const n = tables.length;
                tables.push((heading ? 'Section: ' + heading + '\n' : '') + raw.join('\n'));
                out.push('[[T' + n + ']]\n' + fb.join('\n') + '\n[[/T' + n + ']]');
            } else {
                out.push(fb.join('\n'));
            }
        } else {
            out.push(lines[i]);
        }
    }
    return out.join('\n')
        .replace(/^#{1,6}\s+/gm, '')
        .replace(/^\s*[-•*]\s+(.+?)\s*$/gm, (m, x) => /[.!?:]$/.test(x) ? x : x + '.')
        .replace(/(\*\*|__|`)/g, '')
        .replace(/\p{Extended_Pictographic}\uFE0F?/gu, '')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

// Markdown -> readable text for the Text-to-Speech box (tables shown as one short line)
function displayMd(t) {
    const lines = String(t).split('\n');
    const out = [];
    for (let i = 0; i < lines.length; i++) {
        if (/^\s*\|/.test(lines[i])) {
            const head = lines[i].trim().replace(/^\||\|$/g, '').split('|').map(c => c.trim()).filter(Boolean);
            while (i < lines.length && /^\s*\|/.test(lines[i])) i++;
            i--;
            out.push('[Table: ' + head.join(', ') + ' (explained aloud)]');
        } else {
            out.push(lines[i]);
        }
    }
    return out.join('\n')
        .replace(/^#{1,6}[ \t]+/gm, '')
        .replace(/^[ \t]*[-*][ \t]+/gm, '• ')
        .replace(/(\*\*|__|`)/g, '')
        .replace(/\p{Extended_Pictographic}\uFE0F?/gu, '')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

function renderNotes(sections, lead, tag) {
    currentSections = sections;
    const body = $('notesBody');
    body.textContent = '';
    $('notesLead').textContent = lead;
    if (tag) body.appendChild(el('span', 'tag', tag));
    sections.forEach(s => {
        const d = el('div', 'note-sec');
        d.appendChild(el('h3', '', s.h));
        if (s.p) {
            const md = el('div', 'md');
            md.innerHTML = mdToHtml(s.p);
            d.appendChild(md);
        }
        body.appendChild(d);
    });
    const noEmoji = h => h.replace(/\p{Extended_Pictographic}\uFE0F?/gu, '').trim();
    speechTables = [];
    speechPayload = sections.map(s => {
        const h = noEmoji(s.h);
        return s.p ? h + '.\n' + speechMd(s.p, speechTables, h) : h;
    }).join('\n\n');
    speechDisplay = sections.map(s => {
        const h = noEmoji(s.h);
        return s.p ? h + '\n' + displayMd(s.p) : h;
    }).join('\n\n');
}

function showSaveMsg(text, warn) {
    const m = $('notesSaved');
    m.textContent = text;
    m.classList.toggle('warn', !!warn);
    m.hidden = false;
}

async function saveFocusNotes(sections) {
    if (!current) return;
    const { id, title } = current;
    const focusTitle = title + ' (focus notes)';
    try {
        const saved = await apiCall('save_notes', {
            id: id,
            as_new: 1,
            mode: 'personalised',
            title: focusTitle,
            content: serializeNotes(sections)
        }, 'POST');
        if (current && current.id === id) {
            showSaveMsg('Saved as a new note called "' + saved.title + '". You will find it on your home page.');
        }
    } catch (err) {
        if (current && current.id === id) {
            showSaveMsg('Could not save these notes: ' + err.message, true);
        }
    }
}

function buildNotes(mode, wrong) {
    const personalised = mode === 'mistakes' && wrong && wrong.length;
    let sections;
    if (personalised) {
        sections = wrong.map(w => ({
            h: w.topic + ': ' + w.q,
            p: 'Your answer: ' + (w.type === 'text' ? w.picked : w.options[w.picked]) + '\nCorrect answer: ' + correctText(w) + (w.explain ? '\n' + w.explain : '')
        }));
        renderNotes(sections, 'Focused notes based on the questions you answered wrongly.', 'From your quiz mistakes');
    } else {
        sections = notesText ? parseNotes(notesText) : [];
        renderNotes(sections, 'Generated from your study materials.', '');
    }
    $('notesSaved').hidden = true;
    showPanel('notes');
    if (personalised) saveFocusNotes(sections);
}

/* GAME QUIZ AND CUSTOM QUIZ */
// meta = the Customise Quiz choices {difficulty: 'Easy'|'Medium'|'Hard', type: 'mcq'|'text'}; they are saved with the result
function startQuiz(questions, meta) {
    meta = Object.assign({
        difficulty: 'Medium',
        type: 'mcq',
        sections: 1
    }, meta);
    if (!questions || !questions.length) {
        // stay where the person is and say why, instead of dropping them back on the start screen
        const msg = 'No quiz questions were found for this note yet, so a quiz cannot start.';
        if (!$('panel-custom').hidden) {
            updateQuizCount();
        } else {
            $('startFiles').textContent = msg;
            showPanel('start');
        }
        return;
    }
    meta.sections = Math.max(1, Math.min(5, Math.trunc(Number(meta.sections) || 1), questions.length));
    const area = $('quizArea');
    let i = 0,
        score = 0,
        streak = 0,
        best = 0;
    const wrong = [];

    // A note can have several files. When it has more than one, each question should say which file it came from.
    const files = (current && current.files) || [];
    const multi = files.length > 1;
    const fileOf = (q, n) => q.source || (multi ? files[n % files.length] : '');

    function renderQ() {
        area.textContent = '';
        const q = questions[i];
        const top = el('div', 'quiz-top');
        const bar = el('div', 'bar'),
            fill = el('i');
        fill.style.width = (i / questions.length * 100) + '%';
        bar.appendChild(fill);
        const section = Math.floor(i * meta.sections / questions.length) + 1;
        top.append(el('span', 'chip quiz-section', 'Section ' + section + ' of ' + meta.sections));
        top.append(el('span', '', (i + 1) + ' / ' + questions.length), bar, el('span', 'chip', score + ' pts'), el('span', 'chip', 'Streak ' + streak));
        const card = el('div', 'card');
        card.appendChild(el('h3', 'q-text', q.q));
        const opts = el('div', q.type === 'text' ? 'text-answer' : 'opts');
        const fb = el('div', 'feedback');
        fb.hidden = true;
        const nextSection = Math.floor((i + 1) * meta.sections / questions.length) + 1;
        const nextLabel = i === questions.length - 1 ? 'See results' : nextSection !== section ? 'Next section' : 'Next';
        const next = el('button', 'btn small', nextLabel);
        next.type = 'button';
        next.hidden = true;
        next.style.marginTop = '1rem';

        // shared by both question types
        function finish(ok, picked) {
            if (ok) {
                score += 10 + streak * 2;
                streak++;
                best = Math.max(best, streak);
            } else {
                streak = 0;
                wrong.push(Object.assign({}, q, {
                    picked: picked,
                    source: fileOf(q, i)
                }));
            }
            fb.className = 'feedback ' + (ok ? 'ok' : 'no');
            fb.textContent = ok ? 'Correct!' : (q.type === 'text' ? 'Not quite. Correct answer: ' + q.answer : 'Not quite.');
            fb.appendChild(el('span', '', q.explain));
            fb.hidden = false;
            next.hidden = false;
            next.focus();
        }

        if (q.type === 'text') {
            const input = el('input');
            input.type = 'text';
            input.placeholder = 'Type your answer';
            input.autocomplete = 'off';
            input.setAttribute('aria-label', 'Your answer');
            const check = el('button', 'btn small', 'Check');
            check.type = 'button';

            function submitText() {
                if (!input.value.trim()) {
                    input.focus();
                    return;
                }
                const ok = isTextCorrect(q, input.value);
                input.disabled = true;
                check.disabled = true;
                input.classList.add(ok ? 'correct' : 'wrong');
                finish(ok, input.value.trim());
            }
            check.addEventListener('click', submitText);
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitText();
                }
            });
            opts.append(input, check);
            setTimeout(() => input.focus(), 0);
        } else {
            q.options.forEach((text, idx) => {
                const b = el('button', 'opt', text);
                b.type = 'button';
                b.addEventListener('click', () => {
                    opts.querySelectorAll('.opt').forEach(o => o.disabled = true);
                    const ok = idx === q.answer;
                    b.classList.add(ok ? 'correct' : 'wrong');
                    if (!ok) opts.children[q.answer].classList.add('correct');
                    finish(ok, idx);
                });
                opts.appendChild(b);
            });
        }
        card.append(opts, fb, next);
        area.append(top, card);
        next.addEventListener('click', () => {
            i++;
            i < questions.length ? renderQ() : renderResult();
        });
    }

    function renderResult() {
        area.textContent = '';
        const right = questions.length - wrong.length;
        const card = el('div', 'card score');
        card.appendChild(el('span', 'tag', 'Quiz result'));
        card.appendChild(el('div', 'big', right + ' / ' + questions.length));
        card.appendChild(el('p', '', score + ' points · best streak ' + best));
        let weakNames = [];

        if (wrong.length) {
            /* WEAK TOPICS: count wrong answers per topic across ALL files of the note.
               The topics with the most wrong answers are the weakest. */
            const topicOf = q => q.topic || 'General';
            const stats = {};
            questions.forEach(q => {
                const t = topicOf(q);
                stats[t] = stats[t] || {
                    topic: t,
                    total: 0,
                    wrong: 0,
                    files: new Set()
                };
                stats[t].total++;
            });
            wrong.forEach(w => {
                const s = stats[topicOf(w)];
                s.wrong++;
                if (w.source) s.files.add(w.source);
            });
            const ranked = Object.values(stats)
                .filter(s => s.wrong > 0)
                .sort((a, b) => b.wrong - a.wrong || (b.wrong / b.total) - (a.wrong / a.total));
            const most = ranked[0].wrong;
            weakNames = ranked.map(s => s.topic); // weakest first; saved in quiz_results.weak_areas

            const weak = el('div', 'weak-topics');
            weak.appendChild(el('h3', '', 'Weak topics'));
            weak.appendChild(el('p', 'section-sub', multi ?
                'The topics you got wrong most often, across all ' + files.length + ' files in this note.' :
                'The topics you got wrong most often.'));
            ranked.forEach(s => {
                const row = el('div', 'topic-row' + (s.wrong === most ? ' top' : ''));
                const head = el('div', 'topic-head');
                head.appendChild(el('b', '', s.topic));
                if (s.wrong === most) head.appendChild(el('span', 'weakest', 'Weakest'));
                head.appendChild(el('small', '', s.wrong + ' of ' + s.total + ' wrong'));
                const bar = el('div', 'topic-bar');
                const fill = el('i');
                fill.style.width = Math.round(s.wrong / s.total * 100) + '%';
                bar.appendChild(fill);
                row.append(head, bar);
                if (multi && s.files.size) {
                    const from = el('div', 'topic-files');
                    from.appendChild(el('small', '', 'From:'));
                    s.files.forEach(n => from.appendChild(el('span', 'file-badge', n)));
                    row.appendChild(from);
                }
                weak.appendChild(row);
            });
            card.appendChild(weak);

            /* WEAK PARTS: the individual questions the student got wrong */
            const review = el('div', 'review');
            review.appendChild(el('h3', '', 'Weak parts'));
            review.appendChild(el('p', 'section-sub', 'The questions you got wrong.'));
            wrong.forEach(w => {
                const it = el('div', 'review-item');
                it.appendChild(el('b', '', w.q));
                it.appendChild(el('small', 'review-meta', topicOf(w) + (multi && w.source ? ' · ' + w.source : '')));
                it.appendChild(el('small', '', (w.type === 'text' ? 'You typed: ' + w.picked : 'You chose: ' + w.options[w.picked])));
                it.appendChild(el('small', 'right', 'Correct: ' + correctText(w)));
                review.appendChild(it);
            });
            card.appendChild(review);
        } else {
            card.appendChild(el('p', '', 'Perfect score! No mistakes to review.'));
        }

        const actions = el('div', 'actions');
        actions.style.justifyContent = 'center';
        const gen = el('button', 'btn', wrong.length ? 'Generate notes from my mistakes' : 'Generate full notes');
        gen.type = 'button';
        gen.addEventListener('click', () => buildNotes(wrong.length ? 'mistakes' : 'full', wrong));
        const again = el('button', 'btn ghost', 'Try again');
        again.type = 'button';
        again.addEventListener('click', () => startQuiz(questions, meta));
        actions.append(gen, again);
        card.appendChild(actions);

        // save this attempt in the quiz_results table
        if (current) {
            const msg = el('p', 'note-msg', 'Saving your result…');
            card.appendChild(msg);
            apiCall('save_quiz', {
                note_id: current.id,
                total: questions.length,
                correct: right,
                points: score,
                best_streak: best,
                difficulty: meta.difficulty.toLowerCase(),
                qtype: meta.type,
                sections: meta.sections,
                weak_areas: weakNames.join(', ')
            }, 'POST').then(() => {
                msg.textContent = 'Your result is saved.';
                loadQuizHistory();
            }).catch(err => {
                msg.textContent = 'Could not save this result: ' + err.message;
                msg.classList.add('warn');
            });
        }
        area.appendChild(card);
    }

    renderQ();
    showPanel('game');
}

/* CUSTOMIZE QUIZ */
const DIFF_LABELS = {
    1: 'Easy',
    2: 'Medium',
    3: 'Hard'
};

// paint the slider track up to the thumb and update the label next to the field name
function bindSlider(input, out, format) {
    function update() {
        const range = input.max - input.min;
        const pct = range ? (input.value - input.min) / range * 100 : 0;
        input.style.setProperty('--fill', pct + '%');
        out.textContent = format(input.value);
    }
    input.addEventListener('input', update);
    update();
}
bindSlider($('numQuestions'), $('numValue'), v => v);
bindSlider($('difficulty'), $('diffValue'), v => DIFF_LABELS[v]);
bindSlider($('numSections'), $('sectionValue'), v => v);

function updateDualQuestionCounts() {
    const dual = $('customForm').querySelector('[name="qtype"]:checked').value === 'dual';
    $('dualQuestionCounts').hidden = !dual;
    $('mcqCount').required = dual;
    $('mcqCount').disabled = !dual;
    $('writtenCount').required = dual;
    $('writtenCount').disabled = !dual;
    const total = Number($('numQuestions').value);
    $('mcqCount').max = Math.max(1, total - 1);
    $('writtenCount').max = Math.max(1, total - 1);
    $('dualCountHint').textContent = 'The two counts must add up to ' + total + ' questions.';
}
document.querySelectorAll('#customForm [name="qtype"]').forEach(input => input.addEventListener('change', updateDualQuestionCounts));
$('numQuestions').addEventListener('input', updateDualQuestionCounts);
updateDualQuestionCounts();

async function loadQuizHistory() {
    const list = $('quizHistory');
    try {
        const { results } = await apiCall('quiz_history');
        list.replaceChildren();
        if (!results.length) {
            list.append(el('li', '', 'No completed quizzes yet.'));
            return;
        }
        results.forEach(r => list.append(el('li', '', r.topic + ' · ' + r.correct_answers + '/' +
            r.total_questions + ' correct · ' + r.points + ' points · ' + r.created_at)));
    } catch (err) {
        list.replaceChildren(el('li', '', err.message));
    }
}

// the slider always runs 5 to 30; a quiz uses as many questions as the note actually has (up to the number chosen)
function updateQuizCount() {
    $('numQuestions').dispatchEvent(new Event('input'));
    const n = currentQuestions.length;
    const m = $('customMsg');
    if (homeCustom && !customNoteId) {
        m.textContent = lessons.length ? 'Choose a saved note first. Then you can set up the quiz.' : 'Upload some study materials first, then you can make a quiz from them.';
        m.classList.remove('warn');
        m.hidden = false;
        return;
    }
    if (!n) {
        m.textContent = 'No quiz questions were found for this note yet, so a quiz cannot start.';
        m.classList.add('warn');
    } else if (n < 30) {
        m.textContent = 'This note has ' + n + ' questions ready, so a quiz will use up to ' + n + '.';
        m.classList.remove('warn');
    } else {
        m.hidden = true;
        return;
    }
    m.hidden = false;
}

function customiseQuizQuestions(source, settings) {
    if (!Number.isInteger(settings.sections) || settings.sections < 1 || settings.sections > 5) throw new Error('Choose between 1 and 5 sections.');
    let questions = source.slice();
    if (settings.order === 'shuffle') {
        for (let i = questions.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [questions[i], questions[j]] = [questions[j], questions[i]];
        }
    }
    questions = questions.slice(0, settings.count);
    if (settings.type === 'dual') {
        if (!Number.isInteger(settings.mcq) || !Number.isInteger(settings.written)
            || settings.mcq < 1 || settings.written < 1 || settings.mcq + settings.written !== settings.count) {
            throw new Error('Multiple-choice and written counts must add up to ' + settings.count + '.');
        }
        if (questions.length < settings.count) {
            throw new Error('This note has ' + questions.length + ' questions. Lower the total before choosing the dual counts.');
        }
    }
    if (settings.sections > questions.length) {
        throw new Error('Choose no more sections than the number of available questions.');
    }
    return questions.map((question, index) => {
        const written = settings.type === 'text' || (settings.type === 'dual' && index >= settings.mcq);
        return Object.assign({}, question, {
            type: written ? 'text' : 'mcq',
            answer: written ? question.options[question.answer] : question.answer
        });
    });
}

$('customForm').addEventListener('submit', e => {
    e.preventDefault();
    const f = new FormData(e.target);
    const settings = {
        count: Number(f.get('count')),
        difficulty: DIFF_LABELS[f.get('difficulty')],
        type: f.get('qtype'),
        order: f.get('order'),
        sections: Number(f.get('sections')),
        mcq: Number(f.get('mcq_count')),
        written: Number(f.get('written_count'))
    };
    if (homeCustom && !customNoteId) {
        updateQuizCount();
        $('customNote').focus();
        return;
    }
    if (!currentQuestions.length) {
        updateQuizCount();
        return;
    }
    try {
        const questions = customiseQuizQuestions(currentQuestions, settings);
        if (homeCustom) {
            // the note is now chosen: open it like a normal note (so results, files and "notes from my mistakes" work), then start the quiz
            notesText = customNotesText;
            const picked = customNoteId;
            openLesson(picked, true);
            if (!current) return;
        }
        startQuiz(questions, { difficulty: settings.difficulty, type: settings.type, sections: settings.sections });
    } catch (error) {
        $('customMsg').textContent = error.message;
        $('customMsg').classList.add('warn');
        $('customMsg').hidden = false;
    }
});

/* TEXT-TO-SPEECH
   Play uses the Gemini voice: api/tts.php turns the notes into a WAV file (same file as Download audio).
   If Gemini cannot be reached, Play falls back to the browser's own voice so it never just fails. */
const synth = window.speechSynthesis;
let ttsAudio = null; // the <audio> playing the Gemini voice
let ttsBlob = null; // last WAV made + the text it was made from, so Play and Download never ask Gemini twice
let ttsBlobText = '';
let ttsBusy = ''; // '' | 'play' | 'download'
let ttsSourceText = '';
let ttsSelectedStart = null;
let ttsAudioText = '';
let ttsLastWordStart = null;
let ttsLastWordTime = 0;
let ttsTap = null;
let ttsPointerDown = null;
let ttsToken = 0; // bumped by Stop / leaving the panel, so a slow answer from Gemini is not played afterwards

function stopSpeech(resetPosition = true) {
    ttsToken++;
    if (synth) synth.cancel();
    if (ttsAudio) {
        ttsAudio.pause();
        ttsAudio.currentTime = 0;
    }
    if (ttsBusy === 'play') setTtsBusy('');
    if (resetPosition) {
        ttsSelectedStart = null;
        const selected = $('ttsText').querySelector('.tts-word.selected');
        if (selected) {
            selected.classList.remove('selected');
            selected.removeAttribute('aria-current');
        }
    }
}

function setTtsBusy(kind, text) {
    ttsBusy = kind;
    // the two buttons that wait for Gemini are icons, so "busy" shows a small spinner instead of changing the text
    ['ttsPlay', 'ttsDownload'].forEach(id => {
        $(id).disabled = !!kind;
        $(id).classList.toggle('busy', (kind === 'play' && id === 'ttsPlay') || (kind === 'download' && id === 'ttsDownload'));
    });
    const m = $('ttsMsg');
    m.classList.remove('warn');
    if (text) {
        m.textContent = text;
        m.hidden = false;
    }
}

function ttsMessage(text, warn) {
    const m = $('ttsMsg');
    m.textContent = text;
    m.classList.toggle('warn', !!warn);
    m.hidden = !text;
}

function fillTts() {
    const text = speechDisplay || speechText || notesText;
    const container = $('ttsText');
    if (text !== ttsSourceText || !container.childNodes.length) {
        stopSpeech();
        ttsSourceText = text;
        ttsTap = null;
        ttsPointerDown = null;
        container.replaceChildren();
        const segments = typeof Intl.Segmenter === 'function'
            ? new Intl.Segmenter(undefined, { granularity: 'word' }).segment(text)
            : Array.from(text.matchAll(/[\p{L}\p{N}][\p{L}\p{M}\p{N}'’\-]*|[^\p{L}\p{N}]+/gu),
                match => ({ segment: match[0], index: match.index, isWordLike: /^[\p{L}\p{N}]/u.test(match[0]) }));
        const fragment = document.createDocumentFragment();
        let firstWord = true;
        for (const segment of segments) {
            if (!segment.isWordLike) {
                fragment.append(document.createTextNode(segment.segment));
                continue;
            }
            const word = el('span', 'tts-word', segment.segment);
            word.dataset.start = segment.index;
            word.setAttribute('role', 'button');
            word.setAttribute('aria-label', 'Read from “' + segment.segment + '”');
            word.tabIndex = firstWord ? 0 : -1;
            firstWord = false;
            fragment.append(word);
        }
        container.append(fragment);
    }
    if (!ttsBusy) $('ttsMsg').hidden = true;
}

function selectedSpeechText() {
    return ttsSelectedStart === null ? (speechText || notesText) : ttsSourceText.slice(ttsSelectedStart);
}

function readFromTtsWord(word) {
    if (!word || ! $('ttsText').contains(word)) return;
    if (ttsBusy === 'download') {
        ttsMessage('Wait for the audio download to finish, then choose a word.');
        return;
    }
    const start = Number(word.dataset.start);
    const now = performance.now();
    // Touch browsers can emit a double-click after the pointer double-tap.
    if (start === ttsLastWordStart && now - ttsLastWordTime < 600) return;
    ttsLastWordStart = start;
    ttsLastWordTime = now;
    ttsSelectedStart = start;
    $('ttsText').querySelectorAll('.tts-word.selected').forEach(item => {
        item.classList.remove('selected');
        item.removeAttribute('aria-current');
    });
    word.classList.add('selected');
    word.setAttribute('aria-current', 'true');
    playTts(true);
}

$('ttsText').addEventListener('dblclick', event => {
    const word = event.target.closest('.tts-word');
    if (!word) return;
    event.preventDefault();
    readFromTtsWord(word);
});
$('ttsText').addEventListener('pointerdown', event => {
    if (event.pointerType !== 'touch') return;
    ttsPointerDown = { x: event.clientX, y: event.clientY, time: performance.now() };
});
$('ttsText').addEventListener('pointercancel', () => { ttsTap = null; ttsPointerDown = null; });
$('ttsText').addEventListener('pointerup', event => {
    if (event.pointerType !== 'touch') return;
    const down = ttsPointerDown;
    ttsPointerDown = null;
    const word = event.target.closest('.tts-word');
    const now = performance.now();
    if (!down || !word || now - down.time > 500 || Math.hypot(event.clientX - down.x, event.clientY - down.y) > 12) {
        ttsTap = null;
        return;
    }
    if (ttsTap && ttsTap.word === word && now - ttsTap.time < 450) {
        ttsTap = null;
        readFromTtsWord(word);
    } else {
        ttsTap = { word, time: now };
    }
});
$('ttsText').addEventListener('keydown', event => {
    const word = event.target.closest('.tts-word');
    if (!word) return;
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        readFromTtsWord(word);
        return;
    }
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const words = Array.from($('ttsText').querySelectorAll('.tts-word'));
    const index = words.indexOf(word);
    const nextIndex = event.key === 'Home' ? 0 : event.key === 'End' ? words.length - 1
        : Math.max(0, Math.min(words.length - 1, index + (event.key === 'ArrowRight' ? 1 : -1)));
    word.tabIndex = -1;
    words[nextIndex].tabIndex = 0;
    words[nextIndex].focus();
});

// asks api/tts.php for the Gemini voice (a WAV file). Throws an Error with a message the user can read.
async function getGeminiAudio(text = speechText || notesText, useStructuredPayload = true) {
    if (ttsBlob && ttsBlobText === text) return ttsBlob;
    const fd = new FormData();
    fd.append('csrf', window.STUDYHUB_CSRF);
    const usePayload = useStructuredPayload && speechText && speechPayload;
    fd.append('text', usePayload ? speechPayload : text);
    if (usePayload && speechTables.length) fd.append('tables', JSON.stringify(speechTables));
    let res;
    try {
        res = await fetch('api/tts.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        });
    } catch (e) {
        throw new Error('Could not reach the server. Please check your connection.');
    }
    if (res.status === 401) {
        window.location.href = 'index.php';
        throw new Error('Please log in again.');
    }
    if (!res.ok || (res.headers.get('Content-Type') || '').indexOf('audio') !== 0) {
        // if the server did not send a clear message, show what it did send so the problem can be found
        let m = 'Could not create the audio (server answered HTTP ' + res.status + ').';
        const body = await res.text();
        try {
            const d = JSON.parse(body);
            if (d && d.error) m = d.error;
        } catch (e) {
            const t = body.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200);
            if (t) m += ' ' + t;
        }
        throw new Error(m);
    }
    const blob = await res.blob();
    ttsBlob = blob;
    ttsBlobText = text;
    return blob;
}

function speakWithBrowser(reason, text = selectedSpeechText()) {
    if (!synth) {
        ttsMessage(reason, true);
        return;
    }
    synth.cancel();
    const u = new SpeechSynthesisUtterance(text);
    u.rate = parseFloat($('ttsRate').value);
    synth.speak(u);
    ttsMessage('The Gemini voice is not available right now, so the browser voice is reading instead. (' + reason + ')', true);
}

async function playTts(restart = false) {
    if (ttsBusy && !restart) return;
    if (!speechText && !notesText) fillTts();
    const text = selectedSpeechText();
    if (!text.trim()) return;
    // paused in the middle: carry on from there
    if (!restart && ttsAudio && ttsAudio.paused && ttsAudio.currentTime > 0 && !ttsAudio.ended && ttsAudioText === text) {
        ttsAudio.play().catch(() => ttsMessage('Press Play again to resume the audio.'));
        return;
    }
    if (!restart && synth && synth.paused) {
        synth.resume();
        return;
    }
    stopSpeech(false);
    const mine = ttsToken;
    const cached = ttsBlob && ttsBlobText === text;
    setTtsBusy('play', cached ? '' : 'Getting the Gemini voice ready. The first time can take up to a minute for long notes.');
    let blob;
    try {
        blob = await getGeminiAudio(text, ttsSelectedStart === null);
    } catch (err) {
        if (mine !== ttsToken) return; // Stop was pressed meanwhile
        setTtsBusy('');
        speakWithBrowser(err.message, text);
        return;
    }
    if (mine !== ttsToken) return;
    setTtsBusy('');
    if (ttsAudio) URL.revokeObjectURL(ttsAudio.src);
    ttsAudio = new Audio(URL.createObjectURL(blob));
    ttsAudioText = text;
    ttsAudio.playbackRate = parseFloat($('ttsRate').value);
    try {
        await ttsAudio.play();
        $('ttsMsg').hidden = true;
    } catch (err) {
        // some browsers refuse to start sound after a long wait: the audio is ready, one more click plays it
        ttsMessage('Your audio is ready. Press Play to listen.');
    }
}
$('ttsPlay').addEventListener('click', () => playTts());
$('ttsPause').addEventListener('click', () => {
    if (ttsAudio && !ttsAudio.paused) ttsAudio.pause();
    else if (synth) synth.pause();
});
$('ttsStop').addEventListener('click', stopSpeech);
$('ttsRate').addEventListener('change', () => {
    if (ttsAudio) ttsAudio.playbackRate = parseFloat($('ttsRate').value);
});

/* DOWNLOAD AUDIO: saves the same Gemini WAV file */
$('ttsDownload').addEventListener('click', async () => {
    if (ttsBusy) return;
    if (!notesText) fillTts();
    if (!speechText && !notesText) return;
    setTtsBusy('download', ttsBlob && ttsBlobText === (speechText || notesText) ? '' : 'Creating your audio file. This can take up to a minute for long notes.');
    try {
        const blob = await getGeminiAudio();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = exportName() + '.wav';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        setTtsBusy('');
        ttsMessage('Your audio file has been downloaded.');
    } catch (err) {
        setTtsBusy('');
        ttsMessage(err.message, true);
    }
});
window.addEventListener('beforeunload', stopSpeech);

/* CAT VIDEO: people who prefer reduced motion see a still frame (the poster) instead of a loop */
if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('.cat-video').forEach(v => {
        v.removeAttribute('autoplay');
        v.pause();
    });
}

const deepNote = parseInt(new URLSearchParams(location.search).get('note'), 10) || 0;
if (deepNote) {
    // opened from a note's "open" link: skip the home page and go straight to that note once the list is loaded
    $('viewHome').hidden = true;
    refreshList().then(() => {
        const l = lessons.find(x => x.id === deepNote);
        if (l) {
            const want = new URLSearchParams(location.search).get('panel');
            pendingPanel = ['game', 'custom', 'tts'].includes(want) ? want : '';
            openLesson(l.id);
        } else {
            setNoteUrl(0);
            $('viewHome').hidden = false;
            if (listState === 'ready') setHint('That note could not be found. It may have been deleted.', true);
        }
    });
} else {
    const requestedFeature = new URLSearchParams(location.search).get('panel');
    if (requestedFeature === 'custom') {
        // e.g. the "Customise Quiz" link in another page's sidebar: open it with the "which saved note?" column
        openHomeCustom();
    } else if (['game', 'tts'].includes(requestedFeature)) {
        pendingPanel = requestedFeature;
        $('featureMessage').textContent = 'Open your study materials to use this feature.';
    }
    renderList(); // shows "Loading your notes…"
    if (!homeCustom) refreshList(); // openHomeCustom already loads notes for its selector
}
