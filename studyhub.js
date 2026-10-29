/* Study Hub logic. Needs sample-data.js, window.STUDYHUB_USER and window.STUDYHUB_CSRF (all loaded/set by studyhub.php first).
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
        Object.entries(fields || {}).forEach(([k, v]) => fd.append(k, v));
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

let lessons = []; // [{id, title, files: [names], updated: ms}] loaded from the notes table
let listState = 'loading'; // 'loading' | 'ready' | 'error'
let listError = '';
let current = null;
let notesText = '';

async function refreshList() {
    try {
        const r = await apiCall('list');
        lessons = r.notes.map(n => ({
            id: n.id,
            title: n.title,
            files: n.files,
            updated: n.ts * 1000
        }));
        listState = 'ready';
    } catch (err) {
        listState = 'error';
        listError = err.message;
    }
    renderList();
}

/* THEME & GREETING */
const root = document.documentElement;
$('themeToggle').addEventListener('click', () => {
    const dark = root.getAttribute('data-theme') === 'dark';
    dark ? root.removeAttribute('data-theme') : root.setAttribute('data-theme', 'dark');
    localStorage.setItem('theme', dark ? 'light' : 'dark');
});

(function greet() {
    const h = new Date().getHours();
    const part = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
    const name = window.STUDYHUB_USER;
    $('greeting').textContent = part + (name && name !== 'there' ? ', ' + name : '') + '!';
})();

/* HOME - LIST OF NOTES AND UPLOAD FILE*/
const bookIcon = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5.5C5 4.5 8 4.5 12 6c4-1.5 7-1.5 9-.5V19c-2-1-5-1-9 .5-4-1.5-7-1.5-9-.5V5.5zM12 6v13.5" /></svg>';

function renderList() {
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
    if (!lessons.length) {
        const e = el('div', 'empty');
        e.appendChild(el('b', '', 'No notes yet'));
        e.appendChild(el('span', '', 'Click here or drop your files (up to ' + MAX_TOTAL_MB + ' MB in total) to upload your first study material.'));
        e.addEventListener('click', () => pick(null));
        ['dragover', 'dragenter'].forEach(t => e.addEventListener(t, ev => {
            ev.preventDefault();
            e.classList.add('drag');
        }));
        ['dragleave', 'drop'].forEach(t => e.addEventListener(t, () => e.classList.remove('drag')));
        e.addEventListener('drop', ev => {
            ev.preventDefault();
            if (ev.dataTransfer.files.length) addLesson(ev.dataTransfer.files);
        });
        box.appendChild(e);
        return;
    }
    lessons.slice().sort((a, b) => b.updated - a.updated).forEach(l => {
        const row = el('div', 'note-row');
        const ico = el('div', 'ico');
        ico.innerHTML = bookIcon;
        const info = el('div', 'note-info');
        const head = el('div', 'note-head');
        head.appendChild(el('h3', '', l.title));
        if (l.files && l.files.length > 1) head.appendChild(el('span', 'file-badge', l.files.length + ' files'));
        info.appendChild(head);
        info.appendChild(el('small', '', ago(l.updated)));

        // a real link: opens the note in a new tab (so several notes can be open at once); Ctrl/middle-click work too
        const open = el('a', 'btn ghost small open-note-btn');
        open.href = '?note=' + encodeURIComponent(l.id);
        open.target = '_blank';
        open.rel = 'noopener';
        open.title = 'Open ' + l.title + ' in a new tab';
        open.setAttribute('aria-label', 'Open ' + l.title + ' in a new tab');
        open.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>';

        const edit = el('button', 'edit-btn');
        edit.type = 'button';
        edit.title = 'Rename note';
        edit.setAttribute('aria-label', 'Rename ' + l.title);
        edit.innerHTML = pencilIcon;
        edit.addEventListener('click', () => askRename(l));

        const conv = el('button', 'convert-btn');
        conv.type = 'button';
        conv.title = 'Convert to PDF, slides or text';
        conv.setAttribute('aria-label', 'Convert ' + l.title + ' to PDF, slides or text');
        conv.innerHTML = convertIcon;
        conv.addEventListener('click', () => convertNote(l, conv));

        const del = el('button', 'del-btn');
        del.type = 'button';
        del.title = 'Delete note';
        del.setAttribute('aria-label', 'Delete ' + l.title);
        del.innerHTML = trashIcon;
        del.addEventListener('click', () => askDelete(l));

        const acts = el('div', 'note-actions');
        acts.append(open, edit, conv, del);
        row.append(ico, info, acts);
        box.appendChild(row);
    });
}

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
let pendingDelete = null;

function askDelete(l) {
    pendingDelete = l;
    $('confirmText').textContent = '"' + l.title + '" and its notes will be removed. This can\'t be undone.';
    $('confirmModal').hidden = false;
    $('confirmCancel').focus();
}

function closeConfirm() {
    pendingDelete = null;
    $('confirmModal').hidden = true;
}
$('confirmCancel').addEventListener('click', closeConfirm);
$('confirmModal').addEventListener('click', e => {
    if (e.target === $('confirmModal')) closeConfirm();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !$('confirmModal').hidden) closeConfirm();
});
$('confirmOk').addEventListener('click', async () => {
    const l = pendingDelete;
    closeConfirm();
    if (!l) return;
    try {
        await apiCall('delete', {
            id: l.id
        }, 'POST'); // soft delete: the row stays in the table with deleted_at filled in
        lessons = lessons.filter(x => x.id !== l.id);
    } catch (err) {
        setHint(err.message, true);
    }
    renderList();
});

/* UPLOAD / GENERATE FROM HOME */
let pendingAction = null; 

function pick(action) {
    pendingAction = action;
    $('fileInput').click();
}
$('uploadBtn').addEventListener('click', () => pick(null));
$('homeNotes').addEventListener('click', () => pick('notes'));
$('homeQuiz').addEventListener('click', () => pick('quiz'));
$('fileInput').addEventListener('change', e => {
    const files = Array.from(e.target.files);
    const action = pendingAction;
    pendingAction = null;
    if (files.length) addLesson(files, action);
    e.target.value = '';
});

/* UPLOAD LIMITS: one note can have many files, but all its files together must stay under MAX_TOTAL_MB.
   (api.php has the same two numbers; change them in both places.) */
const MAX_TOTAL_MB = 100;
const MAX_FILES = 20; // only a safety net so one note does not get hundreds of files
const ALLOWED_EXT = /\.(pdf|docx?|pptx?|txt|mp3|m4a|wav)$/i;
const HINT_DEFAULT = 'Tip: you can add several files (PDF, Word, PowerPoint, text or audio), up to ' + MAX_TOTAL_MB + ' MB in total, to make one set of notes.';
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

function addLesson(fileList, action) {
    const files = Array.from(fileList || []);
    if (!files.length) return;
    // reject the whole selection (instead of quietly dropping files) so the user knows what happened
    if (files.some(f => !ALLOWED_EXT.test(f.name))) {
        setHint('Only PDF, Word, PowerPoint, text and audio files (mp3, m4a, wav) are supported.', true);
        return;
    }
    if (files.length > MAX_FILES) {
        setHint('You selected ' + files.length + ' files, but one note can have up to ' + MAX_FILES + '. Please pick fewer files.', true);
        return;
    }
    const total = files.reduce((a, f) => a + f.size, 0);
    if (total > MAX_TOTAL_MB * 1024 * 1024) {
        setHint('Your files add up to ' + fmtSize(total) + ', but one note can hold up to ' + MAX_TOTAL_MB + ' MB in total. Please pick fewer or smaller files.', true);
        return;
    }
    setHint(HINT_DEFAULT, false);
    askName(files.map(f => f.name), action, files.map(f => f.size));
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
    $('nameModal').hidden = false;
    $('nameInput').focus();
    $('nameInput').select();
}

function askName(names, action, sizes) {
    pendingRename = null;
    pendingUpload = {
        names: names,
        sizes: sizes || [],
        action: action
    };
    openNameBox('Name your notes', 'Continue', filesSummary(names, sizes), defaultName(names));
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
                l.title = title;
                l.updated = Date.now();
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
    closeName();
    createLesson(title, up.names, up.action, up.sizes);
});

// saves the note and its files in the database first, then opens it
async function createLesson(title, names, action, sizes) {
    let r;
    try {
        r = await apiCall('create', {
            title: title,
            files: JSON.stringify(names.map((n, i) => ({
                name: n,
                size: (sizes && sizes[i]) || 0
            })))
        }, 'POST');
    } catch (err) {
        setHint(err.message, true);
        return;
    }
    const l = {
        id: r.id,
        title: r.title,
        files: r.files,
        updated: Date.now()
    };
    lessons.unshift(l);
    openLesson(l.id, true);
    if (action === 'notes') buildNotes('full');
    else if (action === 'quiz') startQuiz(SAMPLE_QUESTIONS.slice());
}

/* NAVIGATION */
const panels = ['start', 'notes', 'game', 'custom', 'tts'];

// studyhub.php?note=5 opens note 5 straight away (that is the link the "open" button uses, in a new tab)
function setNoteUrl(id) {
    try {
        const u = new URL(location.href);
        if (id) u.searchParams.set('note', id);
        else u.searchParams.delete('note');
        history.replaceState(null, '', u);
    } catch (e) {}
}

function showHome() {
    stopSpeech();
    current = null;
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
    setNoteUrl(current.id);
    notesText = '';
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
            const stillHere = current && current.id === openedId && !$('panel-start').hidden && !notesText;
            if (stillHere && r.content) {
                renderNotes(parseNotes(r.content), r.mode === 'personalised' ? 'Focus notes made from your quiz mistakes.' : 'Generated from your study materials.', '');
                showPanel('notes');
            }
        }).catch(() => {}); // if this fails the user can still generate notes as usual
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
}

$('crumbHome').addEventListener('click', showHome);
$('logoLink').addEventListener('click', e => {
    if (current) {
        e.preventDefault();
        showHome();
    }
});

// only the sidebar buttons that open a panel (Game Quiz is now a plain link to play_menu.php)
document.querySelectorAll('.side-item[data-panel]').forEach(b => b.addEventListener('click', () => {
    const p = b.dataset.panel;
    // Nothing generated yet: let the user choose notes or quiz instead of forcing one
    if (p === 'notes' && !notesText && !$('quizArea').childElementCount) showPanel('start');
    else if (p === 'game' && !$('quizArea').childElementCount && !notesText) showPanel('start');
    else if (p === 'notes' && !notesText) buildNotes('full');
    else if (p === 'game' && !$('quizArea').childElementCount) startQuiz(SAMPLE_QUESTIONS.slice());
    else showPanel(p);
}));

$('chooseNotes').addEventListener('click', () => buildNotes('full'));
$('chooseQuiz').addEventListener('click', () => startQuiz(SAMPLE_QUESTIONS.slice()));
$('notesToTts').addEventListener('click', () => showPanel('tts'));
$('notesToQuiz').addEventListener('click', () => startQuiz(SAMPLE_QUESTIONS.slice()));

/* EXPORT NOTES: PDF, slides (.pptx) or plain text. Everything is made in the browser (libraries are in the lib folder). */
function notesForExport() {
    return Array.from($('notesBody').querySelectorAll('.note-sec')).map(s => {
        const h = s.querySelector('h3');
        const p = s.querySelector('p');
        return {
            h: h ? h.textContent : '',
            p: p ? p.textContent : ''
        };
    });
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
    'p{margin:0 0 5px}.li{position:relative;padding-left:22px;margin:0 0 3px}.li:before{content:"\\2022";position:absolute;left:8px}';

async function exportPdf(secs, title) {
    await loadLib('lib/html2canvas.min.js', () => window.html2canvas, 'PDF maker');
    await loadLib('lib/jspdf.umd.min.js', () => window.jspdf, 'PDF maker');

    const esc = s => s.replace(/[&<>"]/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;'
    }[c]));
    const sectionHtml = s => '<div class="blk"><h2>' + esc(s.h) + '</h2>' + bodyLines(s.p).map(l =>
        BULLET.test(l) ? '<div class="li">' + esc(l.replace(BULLET, '')) + '</div>' : '<p>' + esc(l) + '</p>').join('') + '</div>';
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
            const marks = Array.from(el.querySelectorAll('p,.li')).map(n => n.getBoundingClientRect().bottom - box.top);
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

    secs.forEach(sec => {
        slideChunks(sec.p).forEach((chunk, n) => {
            const sl = pptx.addSlide();
            sl.background = {
                color: 'FFFFFF'
            };
            sl.addShape(pptx.ShapeType.rect, {
                x: 0,
                y: 0,
                w: 0.25,
                h: 7.5,
                fill: {
                    color: CORAL
                },
                line: {
                    color: CORAL
                }
            });
            sl.addText(sec.h + (n ? ' (continued)' : ''), {
                x: 0.8,
                y: 0.45,
                w: 11.7,
                h: 1.0,
                fontSize: 28,
                bold: true,
                color: CORAL,
                valign: 'middle',
                fit: 'shrink'
            });
            if (chunk.length) {
                sl.addText(chunk.map(t => ({
                    text: t,
                    options: {
                        bullet: true,
                        breakLine: true
                    }
                })), {
                    x: 0.8,
                    y: 1.7,
                    w: 11.7,
                    h: 5.0,
                    fontSize: 20,
                    color: INK,
                    valign: 'top',
                    paraSpaceAfter: 8
                });
            }
            sl.slideNumber = {
                x: 12.3,
                y: 7.0,
                fontSize: 12,
                color: MUTED
            };
        });
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

function openExport(secs, title) {
    exportTarget = {
        secs: secs,
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
        } else exportTxt(secs, title);
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
    return String(content).split(/\n\s*\n/).map(b => b.trim()).filter(Boolean).map(b => {
        const nl = b.indexOf('\n');
        return nl === -1 ? {
            h: b,
            p: ''
        } : {
            h: b.slice(0, nl).trim(),
            p: b.slice(nl + 1).trim()
        };
    });
}

function serializeNotes(sections) {
    return sections.map(s => s.h + '\n' + s.p).join('\n\n');
}

// draws the notes card and prepares the text that Text-to-Speech reads
function renderNotes(sections, lead, tag) {
    const body = $('notesBody');
    body.textContent = '';
    $('notesLead').textContent = lead;
    if (tag) body.appendChild(el('span', 'tag', tag));
    sections.forEach(s => {
        const d = el('div', 'note-sec');
        d.appendChild(el('h3', '', s.h));
        if (s.p) d.appendChild(el('p', '', s.p));
        body.appendChild(d);
    });
    notesText = sections.map(s => s.p ? s.h + '. ' + s.p.replace(/^\s*[-•*]\s*/gm, '') : s.h).join('\n\n');
}

function showSaveMsg(text, warn) {
    const m = $('notesSaved');
    m.textContent = text;
    m.classList.toggle('warn', !!warn);
    m.hidden = false;
}

async function saveNotes(sections, personalised) {
    if (!current) return;
    const id = current.id;
    try {
        if (personalised) {
            // quiz-mistake notes become their own note, so the full notes are never overwritten
            await apiCall('save_notes', {
                id: id,
                as_new: 1,
                mode: 'personalised',
                title: current.title + ' (focus notes)',
                content: serializeNotes(sections)
            }, 'POST');
            showSaveMsg('Saved as a new note called "' + current.title + ' (focus notes)". You will find it on your home page.');
        } else {
            await apiCall('save_notes', {
                id: id,
                mode: 'general',
                content: serializeNotes(sections)
            }, 'POST');
            current.updated = Date.now();
            showSaveMsg('Saved to your notes.');
        }
    } catch (err) {
        showSaveMsg('Could not save these notes: ' + err.message, true);
    }
}

function buildNotes(mode, wrong) {
    const personalised = mode === 'mistakes' && wrong && wrong.length;
    let sections;
    if (personalised) {
        sections = wrong.map(w => ({
            h: w.topic + ': ' + w.q,
            p: w.explain + ' Correct answer: ' + correctText(w) + '.'
        }));
        renderNotes(sections, 'Focused notes based on the questions you answered wrongly.', 'From your quiz mistakes');
    } else {
        sections = SAMPLE_NOTES;
        renderNotes(sections, 'Generated from your study materials.', '');
    }
    $('notesSaved').hidden = true;
    showPanel('notes');
    saveNotes(sections, !!personalised);
}

/* GAME QUIZ AND CUSTOM QUIZ */
// meta = the Customise Quiz choices {difficulty: 'Easy'|'Medium'|'Hard', type: 'mcq'|'text'}; they are saved with the result
function startQuiz(questions, meta) {
    meta = Object.assign({
        difficulty: 'Medium',
        type: 'mcq'
    }, meta);
    const area = $('quizArea');
    let i = 0,
        score = 0,
        streak = 0,
        best = 0;
    const wrong = [];

    // A note can have several files. When it has more than one, each question should say which file it came from.
    const files = (current && current.files) || [];
    const multi = files.length > 1;
    // q.source = the file the question came from (the real generator should set this).
    // Until then, the sample questions are spread over the note's files so the layout can be tested.
    const fileOf = (q, n) => q.source || (multi ? files[n % files.length] : '');

    function renderQ() {
        area.textContent = '';
        const q = questions[i];
        const top = el('div', 'quiz-top');
        const bar = el('div', 'bar'),
            fill = el('i');
        fill.style.width = (i / questions.length * 100) + '%';
        bar.appendChild(fill);
        top.append(el('span', '', (i + 1) + ' / ' + questions.length), bar, el('span', 'chip', score + ' pts'), el('span', 'chip', 'Streak ' + streak));
        const card = el('div', 'card');
        card.appendChild(el('h3', 'q-text', q.q));
        const opts = el('div', q.type === 'text' ? 'text-answer' : 'opts');
        const fb = el('div', 'feedback');
        fb.hidden = true;
        const next = el('button', 'btn small', i === questions.length - 1 ? 'See results' : 'Next');
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
                weak_areas: weakNames.join(', ')
            }, 'POST').then(() => {
                msg.textContent = 'Your result is saved.';
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
        const pct = (input.value - input.min) / (input.max - input.min) * 100;
        input.style.setProperty('--fill', pct + '%');
        out.textContent = format(input.value);
    }
    input.addEventListener('input', update);
    update();
}
bindSlider($('numQuestions'), $('numValue'), v => v);
bindSlider($('difficulty'), $('diffValue'), v => DIFF_LABELS[v]);

// The sample pools only hold 5 questions each, so a bigger quiz could never be tested.
// Until the real generator exists, repeat the pool (renumbering "question 1" -> "question 6" ...) up to `count`.
function sampleQuestions(pool, count) {
    const out = [];
    for (let n = 0; n < count; n++) {
        const base = pool[n % pool.length];
        out.push(n < pool.length ? base : Object.assign({}, base, {
            q: base.q.replace(/\d+/, n + 1),
            explain: base.explain.replace(/\d+/, n + 1)
        }));
    }
    return out;
}

$('customForm').addEventListener('submit', e => {
    e.preventDefault();
    const f = new FormData(e.target);
    const settings = {
        count: parseInt(f.get('count'), 10),
        difficulty: DIFF_LABELS[f.get('difficulty')],
        type: f.get('qtype'), // 'mcq' or 'text'
        order: f.get('order')
    };
    // The sample pools are placeholders. When the real generator exists, send `settings`
    // to it (count, difficulty, type) and use the questions it returns instead.
    let qs = sampleQuestions(settings.type === 'text' ? SAMPLE_TEXT_QUESTIONS : SAMPLE_QUESTIONS, settings.count);
    if (settings.order === 'shuffle') qs.sort(() => Math.random() - .5);
    startQuiz(qs, {
        difficulty: settings.difficulty,
        type: settings.type
    });
});

/* TEXT-TO-SPEECH
   Play uses the Gemini voice: api/tts.php turns the notes into a WAV file (same file as Download audio).
   If Gemini cannot be reached, Play falls back to the browser's own voice so it never just fails. */
const synth = window.speechSynthesis;
let ttsAudio = null; // the <audio> playing the Gemini voice
let ttsBlob = null; // last WAV made + the text it was made from, so Play and Download never ask Gemini twice
let ttsBlobText = '';
let ttsBusy = ''; // '' | 'play' | 'download'
let ttsToken = 0; // bumped by Stop / leaving the panel, so a slow answer from Gemini is not played afterwards

function stopSpeech() {
    ttsToken++;
    if (synth) synth.cancel();
    if (ttsAudio) {
        ttsAudio.pause();
        ttsAudio.currentTime = 0;
    }
    if (ttsBusy === 'play') setTtsBusy('');
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
    if (!notesText) buildNotesSilently();
    $('ttsText').textContent = notesText;
    if (!ttsBusy) $('ttsMsg').hidden = true;
}

function buildNotesSilently() {
    notesText = SAMPLE_NOTES.map(s => s.h + '. ' + s.p).join('\n\n');
    const body = $('notesBody');
    if (!body.childElementCount) {
        $('notesLead').textContent = 'Generated from your study materials.';
        SAMPLE_NOTES.forEach(s => {
            const d = el('div', 'note-sec');
            d.appendChild(el('h3', '', s.h));
            d.appendChild(el('p', '', s.p));
            body.appendChild(d);
        });
    }
}

// asks api/tts.php for the Gemini voice (a WAV file). Throws an Error with a message the user can read.
async function getGeminiAudio() {
    const text = notesText;
    if (ttsBlob && ttsBlobText === text) return ttsBlob;
    const fd = new FormData();
    fd.append('csrf', window.STUDYHUB_CSRF);
    fd.append('text', text);
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
    ttsBlob = await res.blob();
    ttsBlobText = text;
    return ttsBlob;
}

function speakWithBrowser(reason) {
    if (!synth) {
        ttsMessage(reason, true);
        return;
    }
    synth.cancel();
    const u = new SpeechSynthesisUtterance(notesText);
    u.rate = parseFloat($('ttsRate').value);
    synth.speak(u);
    ttsMessage('The Gemini voice is not available right now, so the browser voice is reading instead. (' + reason + ')', true);
}

$('ttsPlay').addEventListener('click', async () => {
    if (ttsBusy) return;
    if (!notesText) fillTts();
    // paused in the middle: carry on from there
    if (ttsAudio && ttsAudio.paused && ttsAudio.currentTime > 0 && !ttsAudio.ended && ttsBlobText === notesText) {
        ttsAudio.play();
        return;
    }
    if (synth && synth.paused) {
        synth.resume();
        return;
    }
    stopSpeech();
    const mine = ttsToken;
    const cached = ttsBlob && ttsBlobText === notesText;
    setTtsBusy('play', cached ? '' : 'Getting the Gemini voice ready. The first time can take up to a minute for long notes.');
    let blob;
    try {
        blob = await getGeminiAudio();
    } catch (err) {
        if (mine !== ttsToken) return; // Stop was pressed meanwhile
        setTtsBusy('');
        speakWithBrowser(err.message);
        return;
    }
    if (mine !== ttsToken) return;
    setTtsBusy('');
    if (ttsAudio) URL.revokeObjectURL(ttsAudio.src);
    ttsAudio = new Audio(URL.createObjectURL(blob));
    ttsAudio.playbackRate = parseFloat($('ttsRate').value);
    try {
        await ttsAudio.play();
        $('ttsMsg').hidden = true;
    } catch (err) {
        // some browsers refuse to start sound after a long wait: the audio is ready, one more click plays it
        ttsMessage('Your audio is ready. Press Play to listen.');
    }
});
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
    if (!notesText) return;
    setTtsBusy('download', ttsBlob && ttsBlobText === notesText ? '' : 'Creating your audio file. This can take up to a minute for long notes.');
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
            openLesson(l.id);
        } else {
            setNoteUrl(0);
            $('viewHome').hidden = false;
            if (listState === 'ready') setHint('That note could not be found. It may have been deleted.', true);
        }
    });
} else {
    renderList(); // shows "Loading your notes…"
    refreshList(); // then fills the list from the notes table
}