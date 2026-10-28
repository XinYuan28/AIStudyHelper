<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KeyForge · gamified typing trainer</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --bg: #faf7f2;
    --card: #fffdf9;
    --surface: #f5efe7;
    --text: #3b2e24;
    --muted: #6b5a4e;
    --soft: #8b7a6b;
    --border: #e5d9ce;
    --bdark: #d4c5b6;
    --ok: #8faa7b;
    --err: #c97b6b;
    --combo: #c9a87b;
    --r: 1.2rem;
    --rs: 0.6rem;
    --sans: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    --mono: 'SF Mono', 'JetBrains Mono', 'Fira Code', monospace;
}

body {
    background: var(--bg);
    font-family: var(--sans);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 1.5rem;
}

.app {
    max-width: 960px;
    width: 100%;
    background: var(--card);
    border-radius: 2rem;
    box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.15);
    overflow: hidden;
}

.inner {
    padding: 1.6rem 2rem;
}

/* header */
.hrow {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.4rem;
}

.logo {
    font-size: 1.6rem;
    font-weight: 700;
    background: linear-gradient(135deg, #5c4b3a, #8b735e);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.tabs {
    display: flex;
    gap: 0.5rem;
    background: var(--surface);
    padding: 0.25rem;
    border-radius: 3rem;
}

.tab {
    padding: 0.4rem 1rem;
    font-size: 0.8rem;
    font-weight: 540;
    border: none;
    background: transparent;
    border-radius: 2rem;
    cursor: pointer;
    color: var(--muted);
}

.tab.active {
    background: white;
    color: var(--text);
    box-shadow: 0 2px 8px rgba(0,0,0,0.04), 0 0 0 0.5px var(--border);
}

/* stats grid */
.sgrid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 0.7rem;
    margin-bottom: 1rem;
}

.sc {
    background: var(--surface);
    border-radius: var(--rs);
    padding: 0.5rem 0.7rem;
    border: 0.5px solid var(--border);
}

.sl {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: var(--soft);
}

.sv {
    font-size: 1.3rem;
    font-weight: 650;
    color: var(--text);
    line-height: 1.2;
}

.sv.g {
    color: var(--ok);
}
.sv.combo {
    color: var(--combo);
}

/* progress */
.pw {
    height: 4px;
    background: var(--border);
    border-radius: 8px;
    margin-bottom: 1rem;
}
.pb {
    height: 100%;
    background: linear-gradient(90deg, var(--ok), #b2c8a3);
    width: 0%;
    transition: width 0.08s linear;
    border-radius: 8px;
}

/* track */
#track {
    background: var(--surface);
    border-radius: var(--r);
    padding: 1.2rem;
    margin-bottom: 1rem;
    min-height: 95px;
    font-family: var(--mono);
    font-size: 1rem;
    line-height: 1.6;
    border: 1px solid var(--border);
    white-space: pre-wrap;
    word-break: break-word;
}
.char { color: var(--soft); }
.char.correct { color: var(--ok); font-weight: 500; }
.char.wrong { color: var(--err); text-decoration: underline; }
.char.cursor { border-left: 2px solid var(--text); margin-left: -1px; animation: blink 1s step-end infinite; }
@keyframes blink { 0%,100%{border-left-color:var(--text)} 50%{border-left-color:transparent} }

/* input */
.irow {
    display: flex;
    gap: 0.6rem;
    margin-bottom: 0.6rem;
    flex-wrap: wrap;
}
#ti {
    flex: 1;
    padding: 0.7rem 1rem;
    font-size: 0.9rem;
    font-family: var(--mono);
    border: 1px solid var(--bdark);
    border-radius: var(--rs);
    background: white;
    outline: none;
}
#ti:focus {
    border-color: var(--ok);
    box-shadow: 0 0 0 3px rgba(143,170,123,0.2);
}
.btn {
    padding: 0.5rem 1.2rem;
    background: white;
    border: 1px solid var(--bdark);
    border-radius: 2rem;
    font-size: 0.78rem;
    font-weight: 530;
    cursor: pointer;
    color: var(--muted);
}
.btn:hover {
    background: var(--surface);
    transform: translateY(-1px);
}

/* result overlay */
#ro {
    display: none;
    background: var(--surface);
    border-radius: var(--r);
    padding: 1.3rem;
    text-align: center;
    margin-bottom: 1rem;
    flex-direction: column;
    gap: 0.8rem;
}
.rg {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 0.8rem;
}
.rc {
    background: white;
    border: 0.5px solid var(--border);
    border-radius: var(--rs);
    padding: 0.6rem;
}
.rl {
    font-size: 0.65rem;
    text-transform: uppercase;
    color: var(--soft);
}
.rv {
    font-size: 1.2rem;
    font-weight: 600;
}
.hint {
    font-size: 0.7rem;
    color: var(--soft);
    margin: 0.2rem 0 0.7rem;
    background: #ede4d9;
    padding: 0.3rem 0.8rem;
    border-radius: 2rem;
    display: inline-block;
}

/* panel */
.panel {
    background: var(--surface);
    border-radius: var(--r);
    padding: 1rem;
    margin-top: 0.8rem;
    border: 1px solid var(--border);
}
textarea {
    width: 100%;
    height: 80px;
    font-size: 0.85rem;
    padding: 0.7rem;
    border: 1px solid var(--border);
    border-radius: var(--rs);
    resize: vertical;
    outline: none;
    background: white;
}
kbd {
    background: #ede4d9;
    padding: 0.1rem 0.4rem;
    border-radius: 5px;
    font-size: 0.6rem;
}
footer {
    font-size: 0.65rem;
    text-align: center;
    margin-top: 1rem;
    color: var(--soft);
    border-top: 0.5px solid var(--border);
    padding-top: 1rem;
}

/* leaderboard */
.leaderboard {
    margin-top: 1rem;
    background: var(--surface);
    border-radius: var(--r);
    padding: 0.8rem;
    border: 1px solid var(--border);
}
.leaderboard h4 {
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
    color: var(--text);
    display: flex;
    justify-content: space-between;
}
.lb-list {
    font-size: 0.7rem;
}
.lb-item {
    display: flex;
    justify-content: space-between;
    padding: 0.2rem 0;
    border-bottom: 0.5px solid var(--border);
}
.lb-rank {
    font-weight: 600;
    width: 2rem;
}
.lb-score {
    font-weight: 600;
    color: var(--ok);
    width: 3rem;
}
.lb-details {
    flex: 1;
    text-align: right;
    color: var(--soft);
}
.mute-btn {
    background: var(--surface);
    border: 1px solid var(--bdark);
    border-radius: 2rem;
    padding: 0.2rem 0.6rem;
    font-size: 0.7rem;
    cursor: pointer;
}
.help-btn {
    background: var(--surface);
    border: 1px solid var(--bdark);
    border-radius: 2rem;
    padding: 0.2rem 0.6rem;
    margin-left: 0.5rem;
    font-size: 0.7rem;
    cursor: pointer;
}

/* overlay */
.overlay {
    position: fixed;
    top:0;left:0;width:100%;height:100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}
.start-card {
    background: var(--card);
    max-width: 480px;
    width: 90%;
    border-radius: 2rem;
    padding: 1.8rem;
    text-align: center;
    border: 1px solid var(--border);
}
.start-card h2 {
    font-size: 1.7rem;
    margin-bottom: 0.75rem;
    background: linear-gradient(135deg,#5c4b3a,#8b735e);
    -webkit-background-clip:text;
    background-clip:text;
    color:transparent;
}
.instructions {
    text-align: left;
    margin: 1rem 0;
    background: var(--surface);
    padding: 1rem;
    border-radius: 1rem;
    font-size: 0.85rem;
    color: var(--muted);
}
.start-btn {
    background: var(--ok);
    color: white;
    border: none;
    padding: 0.7rem 1.8rem;
    border-radius: 3rem;
    font-weight: 600;
    cursor: pointer;
}
</style>
</head>
<body>
<div class="app"><div class="inner">
<div class="hrow">
  <span class="logo">⌨️ KeyForge</span>
  <div style="display: flex; gap: 0.5rem; align-items: center;">
    <div class="tabs">
      <button class="tab active" data-mode="sentences">Sentences</button>
      <button class="tab" data-mode="paragraph">Paragraph</button>
      <button class="tab" data-mode="code">Code</button>
      <button class="tab" data-mode="custom">Custom</button>
    </div>
    <button id="helpBtn" class="help-btn">📖 Help</button>
    <button id="muteBtn" class="mute-btn">🔊 Sound</button>
  </div>
</div>

<div class="sgrid">
  <div class="sc"><div class="sl">WPM</div><div class="sv" id="swpm">—</div></div>
  <div class="sc"><div class="sl">Accuracy</div><div class="sv" id="sacc">—</div></div>
  <div class="sc"><div class="sl">Time</div><div class="sv" id="stime">—</div></div>
  <div class="sc"><div class="sl">Streak</div><div class="sv" id="sstreak">0</div></div>
  <div class="sc"><div class="sl">Points</div><div class="sv g" id="spts">0</div></div>
  <div class="sc"><div class="sl">Combo</div><div class="sv combo" id="comboVal">0</div></div>
</div>
<div class="pw"><div class="pb" id="pb"></div></div>
<div id="track"></div>

<div id="ro">
  <h3 id="rtitle">Great run!</h3>
  <div class="rg">
    <div class="rc"><div class="rl">WPM</div><div class="rv" id="rwpm">—</div></div>
    <div class="rc"><div class="rl">Accuracy</div><div class="rv" id="racc">—</div></div>
    <div class="rc"><div class="rl">Time</div><div class="rv" id="rtime">—</div></div>
    <div class="rc"><div class="rl">Points</div><div class="rv" id="rpts">—</div></div>
  </div>
  <button class="btn" id="retryBtn">↻ Try again</button>
</div>

<div class="irow">
  <input type="text" id="ti" placeholder="click here & start typing…" autocomplete="off" spellcheck="false" disabled>
  <button class="btn" id="newBtn">New <kbd>Tab</kbd></button>
  <button class="btn" id="impBtn">📄 Import</button>
</div>
<div class="hint" id="hint"></div>

<div id="impPanel" class="panel" style="display:none">
  <textarea id="impTA" placeholder="Paste your own text here… (Custom mode)"></textarea>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <button class="btn" id="useImpBtn">Use this text</button>
    <button class="btn" id="uploadBtn">📂 Upload .txt</button>
    <input type="file" id="fileIn" accept=".txt" style="display:none">
  </div>
</div>

<!-- Leaderboard -->
<div class="leaderboard">
  <h4>🏆 Hall of Fame (top 5 scores) <span style="font-size:0.6rem; font-weight:normal;">points in one round</span></h4>
  <div id="leaderboardList" class="lb-list">—</div>
</div>
<footer>Combo increases on each correct character · Multiplier up to 5x at 80+ combo · Errors reset combo</footer>
</div></div>

<!-- start overlay -->
<div id="startOverlay" class="overlay">
  <div class="start-card">
    <h2>⌨️ KeyForge</h2>
    <div class="instructions">
      <p><strong>✨ GAMIFIED TYPING</strong></p>
      <ul>
        <li><strong>Combo multiplier:</strong> correct keystrokes build combo → higher points (up to 5x).</li>
        <li><strong>Sound effects</strong> – correct click, error buzz, victory fanfare.</li>
        <li><strong>Leaderboard</strong> – your best single-round scores saved locally.</li>
        <li>≥90% accuracy = base 20/30 pts × combo multiplier!</li>
        <li>Mistakes reset combo and reduce points.</li>
      </ul>
      <p><kbd>Tab</kbd> = new text · Mute button toggles sounds.</p>
    </div>
    <button id="startBtn" class="start-btn">▶ Start Typing & Unlock Sound</button>
  </div>
</div>

<script>
// ---------- TEXT POOLS ----------
// Sentences generated from the study notes (injected by play.php)
const sentencePool = <?= json_encode($sentencePool, JSON_HEX_TAG) ?>;
const codePool = [
    "const result = arr.filter(x => x > 0).map(x => x * 2);",
    "function greet(name) { return `Hello, ${name}!`; }",
    "for (let i = 0; i < items.length; i++) { console.log(items[i]); }",
    "const obj = { key: 'value', count: 42, active: true };",
    "import { useState, useEffect } from 'react';",
    "async function fetchData(url) { const res = await fetch(url); return res.json(); }"
];

function getRandomParagraph() {
    const count = Math.floor(Math.random() * 3) + 2;
    const shuffled = [...sentencePool];
    for (let i = shuffled.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }
    return shuffled.slice(0, count).join(' ');
}

let customTexts = [];
const TD = {
    sentences: [...sentencePool],
    paragraph: [],
    code: [...codePool],
    custom: []
};
function getTextForMode(m) {
    if (m === 'sentences') return TD.sentences[Math.floor(Math.random() * TD.sentences.length)];
    if (m === 'paragraph') return getRandomParagraph();
    if (m === 'code') return TD.code[Math.floor(Math.random() * TD.code.length)];
    if (m === 'custom') return TD.custom.length ? TD.custom[Math.floor(Math.random() * TD.custom.length)] : "No custom text — use Import.";
    return "Sample text.";
}

// ---------- GAME STATE ----------
let mode = 'sentences';
let text = '', typed = '', startTime = null, timerInt = null, done = false;
let streak = 0, totalPoints = 0, totalCorrect = 0, totalTyped = 0;
let combo = 0;           // consecutive correct characters
let currentMultiplier = 1;

// DOM elements
const track = document.getElementById('track');
const ti = document.getElementById('ti');
const pb = document.getElementById('pb');
const hint = document.getElementById('hint');
const ro = document.getElementById('ro');

// ---------- SOUND (Web Audio, unlocked on user gesture) ----------
let audioCtx = null;
let soundEnabled = true;
let soundUnlocked = false;

function initAudio() {
    if (audioCtx) return;
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
}
function playBeep(type) {
    if (!soundEnabled || !audioCtx || !soundUnlocked) return;
    const now = audioCtx.currentTime;
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    osc.type = 'sine';
    gain.gain.setValueAtTime(0.15, now);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.4);
    if (type === 'correct') {
        osc.frequency.setValueAtTime(880, now);
        osc.frequency.exponentialRampToValueAtTime(440, now + 0.2);
    } else if (type === 'error') {
        osc.frequency.setValueAtTime(220, now);
        osc.frequency.exponentialRampToValueAtTime(110, now + 0.3);
    } else if (type === 'fanfare') {
        // simple arpeggio
        osc.frequency.setValueAtTime(523.25, now);
        gain.gain.setValueAtTime(0.2, now);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.8);
        osc.frequency.exponentialRampToValueAtTime(659.25, now + 0.2);
        osc.frequency.exponentialRampToValueAtTime(783.99, now + 0.4);
    }
    osc.start();
    osc.stop(now + 0.6);
}

function unlockSound() {
    if (!audioCtx) initAudio();
    if (audioCtx && !soundUnlocked) {
        audioCtx.resume().then(() => {
            soundUnlocked = true;
            playBeep('correct'); // test sound
        }).catch(e => console.log);
    }
}

// mute toggle
document.getElementById('muteBtn').onclick = () => {
    soundEnabled = !soundEnabled;
    document.getElementById('muteBtn').innerHTML = soundEnabled ? '🔊 Sound' : '🔇 Muted';
};

// ---------- COMBO & MULTIPLIER ----------
function updateComboUI() {
    document.getElementById('comboVal').innerText = combo;
}
function resetCombo() {
    combo = 0;
    currentMultiplier = 1;
    updateComboUI();
}
function incrementCombo() {
    combo++;
    if (combo >= 80) currentMultiplier = 5;
    else if (combo >= 60) currentMultiplier = 4;
    else if (combo >= 40) currentMultiplier = 3;
    else if (combo >= 20) currentMultiplier = 2;
    else currentMultiplier = 1;
    updateComboUI();
}
function onCorrect() {
    if (soundEnabled && soundUnlocked) playBeep('correct');
    incrementCombo();
}
function onError() {
    if (soundEnabled && soundUnlocked) playBeep('error');
    resetCombo();
}

// ---------- TYPING CORE ----------
function renderTrack() {
    let html = '';
    for (let i = 0; i < text.length; i++) {
        let cls = 'char';
        if (i < typed.length) cls += typed[i] === text[i] ? ' correct' : ' wrong';
        else if (i === typed.length) cls += ' cursor';
        const ch = text[i] === ' ' ? '&nbsp;' : text[i];
        html += `<span class="${cls}">${ch}</span>`;
    }
    track.innerHTML = html;
    pb.style.width = text.length ? Math.min(100, (typed.length / text.length) * 100) + '%' : '0';
}

function wpm(ms) { const m = ms / 60000; return m < 0.001 ? 0 : Math.round((typed.trim().length / 5) / m); }
function accuracy() { return totalTyped === 0 ? 100 : Math.round((totalCorrect / totalTyped) * 100); }
function updateStats(ms) {
    document.getElementById('swpm').textContent = wpm(ms) || '—';
    document.getElementById('sacc').textContent = accuracy() + '%';
    document.getElementById('stime').textContent = Math.floor(ms / 1000) + 's';
}

function calculatePoints(w, acc) {
    let base = 0;
    if (acc >= 90) base = w >= 50 ? 30 : 20;
    const errors = totalTyped - totalCorrect;
    let pts = Math.max(0, base - errors * 2);
    // apply combo multiplier
    pts = Math.floor(pts * currentMultiplier);
    return pts;
}

function finishRound(ms) {
    clearInterval(timerInt);
    done = true;
    ti.disabled = true;
    const w = wpm(ms), acc = accuracy();
    let earned = calculatePoints(w, acc);
    totalPoints += earned;
    streak++;
    document.getElementById('sstreak').innerText = streak;
    document.getElementById('spts').innerText = totalPoints;
    document.getElementById('rwpm').innerText = w;
    document.getElementById('racc').innerText = acc + '%';
    document.getElementById('rtime').innerText = (ms/1000).toFixed(1) + 's';
    document.getElementById('rpts').innerHTML = '+' + earned;
    document.getElementById('rtitle').innerHTML = (w >= 50 ? '⚡ Blazing!' : (earned ? 'Good run!' : 'Keep practicing'));
    hint.innerHTML = `+${earned} pts · ${acc}% acc · ${w} WPM · ${currentMultiplier}x combo`;
    track.style.display = 'none';
    ro.style.display = 'flex';
    if (soundEnabled && soundUnlocked) playBeep('fanfare');
    // update leaderboard with this round's points
    addScoreToLeaderboard(earned, w, acc);
}

function newRound(resetText) {
    clearInterval(timerInt);
    startTime = null;
    typed = '';
    done = false;
    totalCorrect = 0;
    totalTyped = 0;
    resetCombo();
    if (resetText) {
        text = getTextForMode(mode);
    }
    ti.value = '';
    ti.disabled = false;
    ro.style.display = 'none';
    track.style.display = 'block';
    document.getElementById('swpm').textContent = '—';
    document.getElementById('sacc').textContent = '—';
    document.getElementById('stime').textContent = '—';
    if (mode === 'code') hint.textContent = 'Tip: brackets & symbols build dexterity';
    else hint.textContent = '';
    renderTrack();
    ti.focus();
}

ti.addEventListener('input', () => {
    if (done) return;
    const val = ti.value;
    if (!startTime && val.length) {
        startTime = Date.now();
        timerInt = setInterval(() => updateStats(Date.now() - startTime), 200);
    }
    const prevLen = typed.length;
    typed = val;
    if (typed.length > prevLen) {
        const idx = prevLen;
        if (idx < text.length) {
            totalTyped++;
            if (typed[idx] === text[idx]) {
                totalCorrect++;
                onCorrect();
            } else {
                onError();
            }
        }
    } else if (typed.length < prevLen) {
        // backspace – no combo change, but we don't increment
    }
    renderTrack();
    if (typed.length >= text.length) finishRound(Date.now() - startTime);
});

ti.addEventListener('keydown', e => { if (e.key === 'Tab') { e.preventDefault(); newRound(true); } });
document.getElementById('newBtn').onclick = () => newRound(true);
document.getElementById('retryBtn').onclick = () => newRound(false);

// mode switching
document.querySelectorAll('.tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        mode = btn.dataset.mode;
        streak = 0;
        document.getElementById('sstreak').innerText = '0';
        newRound(true);
    });
});

// import panel
document.getElementById('impBtn').onclick = () => {
    const p = document.getElementById('impPanel');
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
};
document.getElementById('useImpBtn').onclick = () => {
    const raw = document.getElementById('impTA').value.trim();
    if (!raw) { hint.textContent = 'paste some text first'; return; }
    const chunks = raw.match(/.{1,250}(\s|$)/g) || [raw];
    TD.custom = chunks.map(s => s.trim()).filter(Boolean);
    document.querySelector('.tab[data-mode="custom"]').click();
    document.getElementById('impPanel').style.display = 'none';
    hint.textContent = 'custom text loaded ✓';
};
document.getElementById('uploadBtn').onclick = () => document.getElementById('fileIn').click();
document.getElementById('fileIn').onchange = function(e) {
    const f = this.files[0];
    if (!f) return;
    const r = new FileReader();
    r.onload = ev => { document.getElementById('impTA').value = ev.target.result; hint.textContent = 'loaded: ' + f.name; };
    r.readAsText(f);
};

// Leaderboard (localStorage)
let leaderboard = [];

function loadLeaderboard() {
    const stored = localStorage.getItem('keyforge_leaderboard');
    if (stored) leaderboard = JSON.parse(stored);
    renderLeaderboard();
}
function saveLeaderboard() {
    localStorage.setItem('keyforge_leaderboard', JSON.stringify(leaderboard));
}
function addScoreToLeaderboard(points, wpmVal, accVal) {
    if (points === 0) return;
    leaderboard.push({ points, wpm: wpmVal, acc: accVal, date: Date.now() });
    leaderboard.sort((a,b) => b.points - a.points);
    leaderboard = leaderboard.slice(0, 5);
    saveLeaderboard();
    renderLeaderboard();
}
function renderLeaderboard() {
    const container = document.getElementById('leaderboardList');
    if (!leaderboard.length) { container.innerHTML = '<div class="lb-item">— No scores yet —</div>'; return; }
    let html = '';
    leaderboard.forEach((entry, i) => {
        html += `<div class="lb-item">
            <span class="lb-rank">#${i+1}</span>
            <span class="lb-score">${entry.points} pts</span>
            <span class="lb-details">${entry.wpm} WPM · ${entry.acc}% acc</span>
        </div>`;
    });
    container.innerHTML = html;
}

// start overlay & help
const overlay = document.getElementById('startOverlay');
const startBtn = document.getElementById('startBtn');
const helpBtn = document.getElementById('helpBtn');
function enableGame() {
    overlay.style.display = 'none';
    ti.disabled = false;
    initAudio();
    unlockSound(); // user gesture unlocks audio
    newRound(true);
}
startBtn.addEventListener('click', enableGame);
helpBtn.addEventListener('click', () => {
    overlay.style.display = 'flex';
    ti.disabled = true;
    clearInterval(timerInt);
});
loadLeaderboard();
ti.disabled = true;
</script>
</body>
</html>
