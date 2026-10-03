<?php
require 'auth.php';
require_login();
$userName = $_SESSION['name'];
$initial  = strtoupper(mb_substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Study Hub | AI Study Note Helper</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="studyhub.css">
    <link rel="icon" type="image/png" href="Images/AISNH_Cat coloredlogo(S).png">

    <script>
        if (localStorage.getItem('theme') === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    </script>
</head>

<body>

    <!-- TOP BAR -->
    <header class="topbar">
        <div class="topbar-left">
            <a href="studyhub.php" id="logoLink"><img src="Images/AISNH_Cat coloredlogo(S).png" alt="AI Study Note Helper" class="logo"></a>
            <div class="crumbs" id="crumbs" hidden>
                <button type="button" id="crumbHome">Home</button>
                <span>&rsaquo;</span>
                <span class="here" id="crumbTitle"></span>
            </div>
        </div>
        <div class="topbar-right">
            <button class="icon-btn" id="themeToggle" aria-label="Toggle dark mode">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
                </svg>
            </button>
            <a href="logout.php" class="logout" id="logoutLink">Log out</a>
            <div class="avatar" title="<?= htmlspecialchars($userName) ?>"><?= htmlspecialchars($initial) ?></div>
        </div>
    </header>

    <!-- USER HOME -->
    <main class="home view" id="viewHome">
        <img src="Images/note_cat.svg" alt="" class="home-cat">
        <h1 class="greeting" id="greeting">Hello, <?= htmlspecialchars($userName) ?>!</h1>
        <p class="greeting-sub">Upload your study materials and we'll take it from there.</p>

        <div class="notes-head">
            <h2>Notes</h2>
            <button type="button" class="btn small" id="uploadBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 16V4M6 10l6-6 6 6M4 20h16" />
                </svg>
                Upload materials
            </button>
        </div>
        <input type="file" id="fileInput" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.mp3,.m4a,.wav" hidden>
        <div class="note-list" id="noteList"></div>
    </main>

    <!-- LESSON -->
    <div class="lesson view" id="viewLesson" hidden>

        <!-- SIDEBAR -->
        <nav class="sidebar" aria-label="Features">
            <button type="button" class="side-item" data-panel="notes">
                <span class="side-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="3" width="16" height="18" rx="3" />
                        <path d="M8 8h8M8 12h8M8 16h5" />
                    </svg>
                </span>Notes
            </button>
            <button type="button" class="side-item" data-panel="game">
                <span class="side-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2.5" y="7" width="19" height="11" rx="5" />
                        <path d="M7 10v5M4.5 12.5h5M15.5 11h.01M18 14h.01" />
                    </svg>
                </span>Game Quiz
            </button>
            <button type="button" class="side-item" data-panel="custom">
                <span class="side-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 7h10M18 7h2M4 17h2M10 17h10" />
                        <circle cx="16" cy="7" r="2" />
                        <circle cx="8" cy="17" r="2" />
                    </svg>
                </span>Customise Quiz
            </button>
            <button type="button" class="side-item" data-panel="tts">
                <span class="side-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 10v4h4l5 4V6L8 10H4z" />
                        <path d="M16.5 9a4 4 0 0 1 0 6M19 6.5a8 8 0 0 1 0 11" />
                    </svg>
                </span>Text-to-Speech
            </button>
        </nav>

        <section class="lesson-main">

            <!-- START - GENERATE NOTES OR TAKE QUIZ -->
            <div class="panel start" id="panel-start">
                <img src="Images/note_cat.svg" alt="" class="home-cat">
                <h1 id="startTitle"></h1>
                <p class="sub">Your materials are ready. How would you like to start?</p>
                <div class="choices">
                    <button type="button" class="choice" id="chooseNotes">
                        <div class="ico"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="3" width="16" height="18" rx="3" />
                                <path d="M8 8h8M8 12h8M8 16h5" />
                            </svg></div>
                        <h3>Generate notes</h3>
                        <p>Turn your study materials into clear notes.</p>
                    </button>
                    <button type="button" class="choice" id="chooseQuiz">
                        <div class="ico"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2.5" y="7" width="19" height="11" rx="5" />
                                <path d="M7 10v5M4.5 12.5h5M15.5 11h.01M18 14h.01" />
                            </svg></div>
                        <h3>Take a quiz</h3>
                        <p>Test your understanding first. We'll make notes from what you got wrong.</p>
                    </button>
                </div>
            </div>

            <!-- NOTES -->
            <div class="panel" id="panel-notes" hidden>
                <h2 class="panel-title">Notes</h2>
                <p class="lead" id="notesLead"></p>
                <div class="card" id="notesBody"></div>
                <div class="actions">
                    <button type="button" class="btn ghost small" id="notesToTts">Listen to these notes</button>
                    <button type="button" class="btn ghost small" id="notesToQuiz">Test myself</button>
                </div>
            </div>

            <!-- GAME QUIZ -->
            <div class="panel" id="panel-game" hidden>
                <h2 class="panel-title">Game Quiz</h2>
                <p class="lead">Answer to earn points and build a streak.</p>
                <div id="quizArea"></div>
            </div>

            <!-- CUSTOM QUIZ -->
            <div class="panel" id="panel-custom" hidden>
                <h2 class="panel-title">Customise Quiz</h2>
                <p class="lead">Set up a quiz the way you want it.</p>
                <form class="card form-grid" id="customForm">
                    <div class="field">
                        <label>Number of questions</label>
                        <div class="seg">
                            <input type="radio" name="count" id="n3" value="3"><label for="n3">3</label>
                            <input type="radio" name="count" id="n5" value="5" checked><label for="n5">5</label>
                        </div>
                    </div>
                    <div class="field">
                        <label>Difficulty</label>
                        <div class="seg">
                            <input type="radio" name="level" id="d1" value="easy"><label for="d1">Easy</label>
                            <input type="radio" name="level" id="d2" value="medium" checked><label for="d2">Medium</label>
                            <input type="radio" name="level" id="d3" value="hard"><label for="d3">Hard</label>
                        </div>
                    </div>
                    <div class="field">
                        <label>Order</label>
                        <div class="seg">
                            <input type="radio" name="order" id="o1" value="in" checked><label for="o1">In order</label>
                            <input type="radio" name="order" id="o2" value="shuffle"><label for="o2">Shuffled</label>
                        </div>
                    </div>
                    <div><button type="submit" class="btn">Start quiz</button></div>
                </form>
            </div>

            <!-- TEXT TO SPEECH -->
            <div class="panel" id="panel-tts" hidden>
                <h2 class="panel-title">Text-to-Speech</h2>
                <p class="lead">Listen to your notes anywhere.</p>
                <div class="card">
                    <div class="tts-text" id="ttsText"></div>
                    <div class="tts-controls">
                        <button type="button" class="btn small" id="ttsPlay">Play</button>
                        <button type="button" class="btn ghost small" id="ttsPause">Pause</button>
                        <button type="button" class="btn ghost small" id="ttsStop">Stop</button>
                        <select id="ttsRate" aria-label="Speed">
                            <option value="0.8">0.8x</option>
                            <option value="1" selected>1x</option>
                            <option value="1.25">1.25x</option>
                            <option value="1.5">1.5x</option>
                        </select>
                    </div>
                    <p class="note-msg" id="ttsMsg" hidden></p>
                </div>
            </div>

        </section>
    </div>

    <script>
        /* SAMPLE CONTENT */
        const SAMPLE_NOTES = [{
                h: 'Key idea 1',
                p: 'A short summary of the first main concept from the uploaded material goes here.'
            },
            {
                h: 'Key idea 2',
                p: 'The second main concept, explained in plain language with the important terms.'
            },
            {
                h: 'Key idea 3',
                p: 'The third concept and how it connects to the ones before it.'
            },
            {
                h: 'Quick recap',
                p: 'A few lines that tie everything together so you can revise fast.'
            }
        ];

        const SAMPLE_QUESTIONS = [{
                q: 'Sample question 1: which option is correct?',
                options: ['Option A', 'Option B', 'Option C', 'Option D'],
                answer: 1,
                topic: 'Key idea 1',
                explain: 'Explanation for question 1 taken from your material.'
            },
            {
                q: 'Sample question 2: which option is correct?',
                options: ['Option A', 'Option B', 'Option C', 'Option D'],
                answer: 0,
                topic: 'Key idea 2',
                explain: 'Explanation for question 2 taken from your material.'
            },
            {
                q: 'Sample question 3: which option is correct?',
                options: ['Option A', 'Option B', 'Option C', 'Option D'],
                answer: 2,
                topic: 'Key idea 3',
                explain: 'Explanation for question 3 taken from your material.'
            },
            {
                q: 'Sample question 4: which option is correct?',
                options: ['Option A', 'Option B', 'Option C', 'Option D'],
                answer: 3,
                topic: 'Key idea 1',
                explain: 'Explanation for question 4 taken from your material.'
            },
            {
                q: 'Sample question 5: which option is correct?',
                options: ['Option A', 'Option B', 'Option C', 'Option D'],
                answer: 1,
                topic: 'Key idea 2',
                explain: 'Explanation for question 5 taken from your material.'
            }
        ];

        /* HELPERS */
        const $ = id => document.getElementById(id);

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
            const name = <?= json_encode($userName) ?>;
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
                e.appendChild(el('span', '', 'Click here or drop a file to upload your first study material.'));
                e.addEventListener('click', () => $('fileInput').click());
                ['dragover', 'dragenter'].forEach(t => e.addEventListener(t, ev => {
                    ev.preventDefault();
                    e.classList.add('drag');
                }));
                ['dragleave', 'drop'].forEach(t => e.addEventListener(t, () => e.classList.remove('drag')));
                e.addEventListener('drop', ev => {
                    ev.preventDefault();
                    if (ev.dataTransfer.files[0]) addLesson(ev.dataTransfer.files[0]);
                });
                box.appendChild(e);
                return;
            }
            lessons.slice().sort((a, b) => b.opened - a.opened).forEach(l => {
                const card = el('button', 'note-card');
                card.type = 'button';
                const ico = el('div', 'ico');
                ico.innerHTML = bookIcon;
                const info = el('div');
                info.appendChild(el('h3', '', l.title));
                info.appendChild(el('small', '', ago(l.opened)));
                card.append(ico, info, el('span', 'go', 'Open →'));
                card.addEventListener('click', () => openLesson(l.id));
                box.appendChild(card);
            });
        }

        $('uploadBtn').addEventListener('click', () => $('fileInput').click());
        $('fileInput').addEventListener('change', e => {
            if (e.target.files[0]) addLesson(e.target.files[0]);
            e.target.value = '';
        });

        function addLesson(file) {
            const title = file.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').trim() || 'Untitled notes';
            const l = {
                id: Date.now(),
                title: title,
                opened: Date.now()
            };
            lessons.push(l);
            save();
            openLesson(l.id);
        }

        /* NAVIGATION */
        const panels = ['start', 'notes', 'game', 'custom', 'tts'];

        function showHome() {
            stopSpeech();
            current = null;
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
            $('crumbTitle').textContent = current.title;
            $('crumbs').hidden = false;
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
            if (p === 'notes' && !notesText) buildNotes('full');
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
                    p: w.explain + ' Correct answer: ' + w.options[w.answer] + '.'
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
                const opts = el('div', 'opts');
                const fb = el('div', 'feedback');
                fb.hidden = true;
                const next = el('button', 'btn small', i === questions.length - 1 ? 'See results' : 'Next');
                next.type = 'button';
                next.hidden = true;
                next.style.marginTop = '1rem';

                q.options.forEach((text, idx) => {
                    const b = el('button', 'opt', text);
                    b.type = 'button';
                    b.addEventListener('click', () => {
                        opts.querySelectorAll('.opt').forEach(o => o.disabled = true);
                        const ok = idx === q.answer;
                        b.classList.add(ok ? 'correct' : 'wrong');
                        if (!ok) opts.children[q.answer].classList.add('correct');
                        if (ok) {
                            score += 10 + streak * 2;
                            streak++;
                            best = Math.max(best, streak);
                        } else {
                            streak = 0;
                            wrong.push(Object.assign({}, q, {
                                picked: idx
                            }));
                        }
                        fb.className = 'feedback ' + (ok ? 'ok' : 'no');
                        fb.textContent = ok ? 'Correct!' : 'Not quite.';
                        fb.appendChild(el('span', '', q.explain));
                        fb.hidden = false;
                        next.hidden = false;
                        next.focus();
                    });
                    opts.appendChild(b);
                });
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
                    const topics = [...new Set(wrong.map(w => w.topic))];
                    card.appendChild(el('p', '', 'Weak spots: ' + topics.join(', ')));
                    const review = el('div', 'review');
                    review.appendChild(el('h3', '', 'Questions to review'));
                    wrong.forEach(w => {
                        const it = el('div', 'review-item');
                        it.appendChild(el('b', '', w.q));
                        it.appendChild(el('small', '', 'You chose: ' + w.options[w.picked]));
                        it.appendChild(el('small', 'right', 'Correct: ' + w.options[w.answer]));
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
        $('customForm').addEventListener('submit', e => {
            e.preventDefault();
            const f = new FormData(e.target);
            let qs = SAMPLE_QUESTIONS.slice();
            if (f.get('order') === 'shuffle') qs.sort(() => Math.random() - .5);
            startQuiz(qs.slice(0, parseInt(f.get('count'), 10)));
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

        renderList();
    </script>
</body>

</html>