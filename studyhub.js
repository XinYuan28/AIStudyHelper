/* Study Hub logic. Needs sample-data.js and window.STUDYHUB_USER (both loaded by studyhub.php first). */

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

function load() {
    try {
        return JSON.parse(localStorage.getItem('aisnh_lessons')) || [];
    } catch (e) {
        return [];
    }
}

function save() {
    try {
        localStorage.setItem('aisnh_lessons', JSON.stringify(lessons));
    } catch (e) {}
}

function ago(ts) {
    const m = Math.floor((Date.now() - ts) / 60000);
    if (m < 1) return 'Opened just now';
    if (m < 60) return 'Opened ' + m + ' min ago';
    const h = Math.floor(m / 60);
    if (h < 24) return 'Opened ' + h + (h === 1 ? ' hour ago' : ' hours ago');
    const d = Math.floor(h / 24);
    return 'Opened ' + d + (d === 1 ? ' day ago' : ' days ago');
}

let lessons = load();
let current = null; 
let notesText = ''; 

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
    if (!lessons.length) {
        const e = el('div', 'empty');
        e.appendChild(el('b', '', 'No notes yet'));
        e.appendChild(el('span', '', 'Click here or drop up to 4 files to upload your first study material.'));
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
    lessons.slice().sort((a, b) => b.opened - a.opened).forEach(l => {
        const row = el('div', 'note-row');
        const ico = el('div', 'ico');
        ico.innerHTML = bookIcon;
        const info = el('div', 'note-info');
        const head = el('div', 'note-head');
        head.appendChild(el('h3', '', l.title));
        if (l.files && l.files.length > 1) head.appendChild(el('span', 'file-badge', l.files.length + ' files'));
        info.appendChild(head);
        info.appendChild(el('small', '', ago(l.opened)));

        const open = el('button', 'btn ghost small open-note-btn');
        open.type = 'button';
        open.title = 'Open ' + l.title;
        open.setAttribute('aria-label', 'Open ' + l.title);
        open.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>';
        open.addEventListener('click', () => openLesson(l.id));

        const edit = el('button', 'edit-btn');
        edit.type = 'button';
        edit.title = 'Rename note';
        edit.setAttribute('aria-label', 'Rename ' + l.title);
        edit.innerHTML = pencilIcon;
        edit.addEventListener('click', () => askRename(l));

        const del = el('button', 'del-btn');
        del.type = 'button';
        del.title = 'Delete note';
        del.setAttribute('aria-label', 'Delete ' + l.title);
        del.innerHTML = trashIcon;
        del.addEventListener('click', () => askDelete(l));

        const acts = el('div', 'note-actions');
        acts.append(open, edit, del);
        row.append(ico, info, acts);
        box.appendChild(row);
    });
}

const pencilIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4" /></svg>';

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
$('confirmOk').addEventListener('click', () => {
    if (pendingDelete) {
        lessons = lessons.filter(x => x.id !== pendingDelete.id);
        save();
        renderList();
    }
    closeConfirm();
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

/* UP TO 4 FILES PER NOTE */
const MAX_FILES = 4;
const ALLOWED_EXT = /\.(pdf|docx?|pptx?|txt|mp3|m4a|wav)$/i;
const HINT_DEFAULT = $('uploadHint').textContent;
let hintTimer;

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
    if (files.length > MAX_FILES) {
        setHint('You can choose up to ' + MAX_FILES + ' files for one note, but you selected ' + files.length + '. Please pick ' + MAX_FILES + ' or fewer.', true);
        return;
    }
    if (files.some(f => !ALLOWED_EXT.test(f.name))) {
        setHint('Only PDF, Word, PowerPoint, text and audio files (mp3, m4a, wav) are supported.', true);
        return;
    }
    setHint(HINT_DEFAULT, false);
    askName(files.map(f => f.name), action);
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

function askName(names, action) {
    pendingRename = null;
    pendingUpload = {
        names: names,
        action: action
    };
    openNameBox('Name your notes', 'Continue', names.join(' · '), defaultName(names));
}

function askRename(l) {
    pendingUpload = null;
    pendingRename = l;
    openNameBox('Rename your notes', 'Save', (l.files || []).join(' · '), l.title);
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
$('nameForm').addEventListener('submit', e => {
    e.preventDefault();
    if (pendingRename) {
        // an empty name keeps the old one
        pendingRename.title = $('nameInput').value.trim() || pendingRename.title;
        save();
        closeName();
        renderList();
        return;
    }
    if (!pendingUpload) return;
    const up = pendingUpload;
    const title = $('nameInput').value.trim() || defaultName(up.names);
    closeName();
    createLesson(title, up.names, up.action);
});

function createLesson(title, names, action) {
    const l = {
        id: Date.now(),
        title: title,
        files: names,
        opened: Date.now()
    };
    lessons.push(l);
    save();
    openLesson(l.id);
    if (action === 'notes') buildNotes('full');
    else if (action === 'quiz') startQuiz(SAMPLE_QUESTIONS.slice());
}

/* NAVIGATION */
const panels = ['start', 'notes', 'game', 'custom', 'tts'];

function showHome() {
    stopSpeech();
    current = null;
    document.body.classList.add('home-active');
    $('viewLesson').hidden = true;
    $('crumbs').hidden = true;
    $('viewHome').hidden = false;
    renderList();
}

function openLesson(id) {
    current = lessons.find(l => l.id === id);
    if (!current) return;
    current.opened = Date.now();
    save();
    notesText = '';
    $('startTitle').textContent = current.title;
    $('startFiles').textContent = current.files && current.files.length ? current.files.join(' · ') : '';
    $('crumbTitle').textContent = current.title;
    $('crumbs').hidden = false;
    document.body.classList.remove('home-active');
    $('viewHome').hidden = true;
    $('viewLesson').hidden = false;
    $('quizArea').textContent = '';
    showPanel('start');
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

document.querySelectorAll('.side-item').forEach(b => b.addEventListener('click', () => {
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

/* NOTES */
function buildNotes(mode, wrong) {
    const body = $('notesBody');
    body.textContent = '';
    let sections;
    if (mode === 'mistakes' && wrong && wrong.length) {
        $('notesLead').textContent = 'Focused notes based on the questions you answered wrongly.';
        body.appendChild(el('span', 'tag', 'From your quiz mistakes'));
        sections = wrong.map(w => ({
            h: w.topic + ': ' + w.q,
            p: w.explain + ' Correct answer: ' + correctText(w) + '.'
        }));
    } else {
        $('notesLead').textContent = 'Generated from your study materials.';
        sections = SAMPLE_NOTES;
    }
    sections.forEach(s => {
        const d = el('div', 'note-sec');
        d.appendChild(el('h3', '', s.h));
        d.appendChild(el('p', '', s.p));
        body.appendChild(d);
    });
    notesText = sections.map(s => s.h + '. ' + s.p).join('\n\n');
    showPanel('notes');
}

/* GAME QUIZ AND CUSTOM QUIZ */
function startQuiz(questions) {
    const area = $('quizArea');
    let i = 0,
        score = 0,
        streak = 0,
        best = 0;
    const wrong = [];

    // A note can have up to 4 files. When it has more than one, each question should say which file it came from.
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
        again.addEventListener('click', () => startQuiz(questions));
        actions.append(gen, again);
        card.appendChild(actions);
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
    let qs = (settings.type === 'text' ? SAMPLE_TEXT_QUESTIONS : SAMPLE_QUESTIONS).slice();
    if (settings.order === 'shuffle') qs.sort(() => Math.random() - .5);
    startQuiz(qs.slice(0, settings.count));
});

/* TEXT-TO-SPEECH */
const synth = window.speechSynthesis;

function stopSpeech() {
    if (synth) synth.cancel();
}

function fillTts() {
    if (!notesText) buildNotesSilently();
    $('ttsText').textContent = notesText;
    const msg = $('ttsMsg');
    msg.hidden = !!synth;
    if (!synth) msg.textContent = 'Your browser does not support text-to-speech.';
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

$('ttsPlay').addEventListener('click', () => {
    if (!synth) return;
    if (synth.paused) {
        synth.resume();
        return;
    }
    synth.cancel();
    const u = new SpeechSynthesisUtterance(notesText);
    u.rate = parseFloat($('ttsRate').value);
    synth.speak(u);
});
$('ttsPause').addEventListener('click', () => {
    if (synth) synth.pause();
});
$('ttsStop').addEventListener('click', stopSpeech);
window.addEventListener('beforeunload', stopSpeech);

/* CAT VIDEO: people who prefer reduced motion see a still frame (the poster) instead of a loop */
if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('.cat-video').forEach(v => {
        v.removeAttribute('autoplay');
        v.pause();
    });
}

renderList();