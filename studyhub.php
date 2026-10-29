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

    <body class="home-active">

        <!-- DELETE CONFIRM -->
        <div class="modal" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle" hidden>
            <div class="modal-box">
                <h3 id="confirmTitle">Delete this note?</h3>
                <p id="confirmText"></p>
                <div class="actions">
                    <button type="button" class="btn ghost small" id="confirmCancel">Cancel</button>
                    <button type="button" class="btn danger small" id="confirmOk">Delete</button>
                </div>
            </div>
        </div>

        <!-- NAME YOUR NOTES -->
        <div class="modal" id="nameModal" role="dialog" aria-modal="true" aria-labelledby="nameTitle" hidden>
            <form class="modal-box" id="nameForm">
                <h3 id="nameTitle">Name your notes</h3>
                <p id="nameFiles"></p>
                <input type="text" class="name-input" id="nameInput" maxlength="60" autocomplete="off" aria-label="Note name">
                <div class="actions">
                    <button type="button" class="btn ghost small" id="nameCancel">Cancel</button>
                    <button type="submit" class="btn small" id="nameOk">Continue</button>
                </div>
            </form>
        </div>

        <!-- EXPORT NOTES -->
        <div class="modal" id="exportModal" role="dialog" aria-modal="true" aria-labelledby="exportTitle" hidden>
            <div class="modal-box">
                <h3 id="exportTitle">Export your notes</h3>
                <p>Choose how you would like to save them.</p>
                <div class="export-list">
                    <button type="button" class="btn small" data-fmt="pdf">PDF document</button>
                    <button type="button" class="btn small" data-fmt="pptx">Slides (PowerPoint)</button>
                    <button type="button" class="btn ghost small" data-fmt="txt">Plain text</button>
                </div>
                <p class="note-msg" id="exportMsg" hidden></p>
                <div class="actions">
                    <button type="button" class="btn ghost small" id="exportCancel">Cancel</button>
                </div>
            </div>
        </div>

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
            <video class="home-cat cat-video" autoplay loop muted playsinline preload="auto" poster="Images/note_cat.svg" aria-hidden="true" tabindex="-1">
                <source src="Images/note-cat-gif.MP4" type="video/mp4">
            </video>
            <h1 class="greeting" id="greeting">Hello, <?= htmlspecialchars($userName) ?>!</h1>
            <p class="greeting-sub">Upload your study materials and we'll take it from there.</p>

            <div class="home-actions">
                <button type="button" class="btn" id="homeQuiz">Generate quiz</button>
                <button type="button" class="btn" id="homeNotes">Generate notes</button>
                <p class="upload-hint" id="uploadHint">Tip: you can choose up to 10 files (PDF, Word, PowerPoint, spreadsheet or text) to make one set of notes.</p>
            </div>

            <input type="file" id="fileInput" accept=".pdf,.md,.csv,.rtf,.docx,.pptx,.xlsx,.odt" multiple hidden>

            <section class="notes-box" aria-label="Notes">
                <div class="notes-head">
                    <h2>Notes</h2>
                    <button type="button" class="btn ghost small" id="uploadBtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 16V4M6 10l6-6 6 6M4 20h16" />
                        </svg>
                        Upload
                    </button>
                </div>
                <div class="note-list" id="noteList"></div>
            </section>
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
                <a href="play_menu.php" class="side-item">
                    <span class="side-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2.5" y="7" width="19" height="11" rx="5" />
                            <path d="M7 10v5M4.5 12.5h5M15.5 11h.01M18 14h.01" />
                        </svg>
                    </span>Game Quiz
                </a>
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
                    <video class="home-cat cat-video" autoplay loop muted playsinline preload="auto" poster="Images/note_cat.svg" aria-hidden="true" tabindex="-1">
                        <source src="Images/note-cat-gif.MP4" type="video/mp4">
                    </video>
                    <h1 id="startTitle"></h1>
                    <p class="file-list" id="startFiles"></p>
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
                    <p class="note-msg" id="notesSaved" hidden></p>
                    <div class="actions">
                        <button type="button" class="btn ghost small" id="notesToTts">Listen to these notes</button>
                        <button type="button" class="btn ghost small" id="notesToQuiz">Test myself</button>
                        <button type="button" class="btn ghost small" id="notesExport">Export notes</button>
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
                            <label for="numQuestions">
                                Number of questions <span class="val" id="numValue">10</span>
                            </label>
                            <input type="range" class="slider" id="numQuestions" name="count" min="5" max="30" step="5" value="10">
                            <div class="range-ticks" aria-hidden="true">
                                <span>5</span><span>10</span><span>15</span><span>20</span><span>25</span><span>30</span>
                            </div>
                        </div>
                        <div class="field">
                            <label for="difficulty">
                                Difficulty <span class="val" id="diffValue">Medium</span>
                            </label>
                            <input type="range" class="slider" id="difficulty" name="difficulty" min="1" max="3" step="1" value="2">
                            <div class="range-ticks" aria-hidden="true">
                                <span>Easy</span><span>Medium</span><span>Hard</span>
                            </div>
                        </div>
                        <div class="field">
                            <label>Type of questions</label>
                            <div class="seg">
                                <input type="radio" name="qtype" id="t1" value="mcq" checked><label for="t1">Multiple choice</label>
                                <input type="radio" name="qtype" id="t2" value="text"><label for="t2">Text (type the answer)</label>
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
                            <button type="button" class="btn ghost small" id="ttsDownload">Download audio</button>
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
            // passed from PHP to studyhub.js: the logged-in user's name and the token api.php checks on every save
            window.STUDYHUB_USER = <?= json_encode($userName) ?>;
            window.STUDYHUB_CSRF = <?= json_encode(csrf_token()) ?>;
        </script>
        <script src="sample-data.js"></script>
        <script src="studyhub.js"></script>

    </body>

</html>