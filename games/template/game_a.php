<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outbreak Origins: Tile Match</title>
    <style>
        :root {
            --bg-color: #1a1a1a;
            --panel-bg: #2d3436;
            --primary: #00b894; /* Bio/Medical Green */
            --primary-dark: #00896f;
            --danger: #d63031;
            --warning: #fdcb6e;
            --text-light: #dfe6e9;
            --card-back: #353b48;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-light);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            overflow: hidden;
            background-image: radial-gradient(circle at center, #2d3436 0%, #1a1a1a 100%);
        }

        .screen {
            width: 100%;
            max-width: 900px;
            height: 650px;
            background-color: rgba(45, 52, 54, 0.8);
            border: 4px solid var(--primary);
            border-radius: 12px;
            position: relative;
            box-shadow: 0 10px 40px rgba(0,0,0,0.8), inset 0 0 20px rgba(0, 184, 148, 0.2);
            display: flex;
            flex-direction: column;
        }

        /* Views Configuration */
        .view {
            display: none;
            width: 100%;
            height: 100%;
            padding: 30px;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .view.active {
            display: flex;
        }

        h1 {
            font-size: 3rem;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
            text-shadow: 0 0 10px rgba(0, 184, 148, 0.5);
        }

        button.main-btn {
            padding: 12px 28px;
            font-size: 1.1rem;
            font-weight: bold;
            background-color: var(--primary);
            color: #1a1a1a;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
            margin: 10px;
            text-transform: uppercase;
            box-shadow: 0 4px 6px rgba(0,0,0,0.4);
        }

        button.main-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.5);
        }

        /* Video Tutorial */
        .video-container {
            width: 100%;
            max-width: 480px;
            aspect-ratio: 16/9;
            background: #000;
            margin: 20px 0;
            border: 2px solid var(--warning);
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .tut-text {
            color: var(--warning);
            margin-bottom: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .tut-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            width: 220px;
            height: 150px;
            perspective: 1000px;
        }

        .tut-card {
            position: relative;
            transform-style: preserve-3d;
            width: 100%;
            height: 100%;
        }

        .tut-front, .tut-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: bold;
            flex-direction: column;
            text-align: center;
            border: 2px solid var(--primary);
        }

        .tut-front {
            background: linear-gradient(135deg, var(--card-back), #2f3640);
            color: var(--primary);
            font-size: 1.5rem;
        }

        .tut-back {
            background: var(--text-light);
            color: var(--bg-color);
            transform: rotateY(180deg);
        }
        
        .tut-tag {
            font-size: 0.6rem;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 3px;
        }

        .tut-cursor {
            position: absolute;
            font-size: 2rem;
            z-index: 10;
            animation: tutorialLoop 5s infinite;
            pointer-events: none;
            filter: drop-shadow(0 4px 4px rgba(0,0,0,0.5));
        }

        @keyframes tutorialLoop {
            0%, 15% { transform: translate(80px, 60px); opacity: 0; }
            20% { transform: translate(80px, 60px); opacity: 1; }
            30% { transform: translate(-30px, -20px); opacity: 1; } 
            35% { transform: translate(-30px, -20px) scale(0.8); } 
            40% { transform: translate(-30px, -20px) scale(1); }
            50% { transform: translate(-30px, 50px); opacity: 1; } 
            55% { transform: translate(-30px, 50px) scale(0.8); } 
            60% { transform: translate(-30px, 50px) scale(1); }
            75%, 100% { opacity: 0; }
        }

        @keyframes flipCard1 {
            0%, 35% { transform: rotateY(0deg); }
            40%, 100% { transform: rotateY(180deg); }
        }

        @keyframes flipCard3 {
            0%, 55% { transform: rotateY(0deg); }
            60%, 100% { transform: rotateY(180deg); }
        }

        @keyframes matchedGlow {
            0%, 65% { border-color: var(--primary); background: var(--text-light); color: var(--bg-color); }
            70%, 100% { border-color: #fff; background: var(--primary); color: #fff;}
        }
        
        @keyframes matchedGlowTag {
            0%, 65% { color: #555; }
            70%, 100% { color: rgba(255,255,255,0.7); }
        }

        #tc1 { animation: flipCard1 5s infinite; }
        #tc3 { animation: flipCard3 5s infinite; }
        #tc1 .tut-back, #tc3 .tut-back { animation: matchedGlow 5s infinite; }
        #tc1 .tut-tag, #tc3 .tut-tag { animation: matchedGlowTag 5s infinite; }

        /* Game Header */
        #game-header {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 25px;
            background: rgba(0,0,0,0.6);
            border-bottom: 2px solid var(--primary);
        }

        .stats-box {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--warning);
        }

        #lives-display {
            color: var(--danger);
            font-size: 1.5rem;
            letter-spacing: 5px;
        }

        /* Game Board */
        #board-container {
            flex-grow: 1;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 12px 20px 20px;
        }

        .grid {
            display: grid;
            gap: 10px;
            perspective: 1000px; /* Required for 3D flip */
            width: 100%;
            height: 100%;
            max-width: 760px;
            max-height: 470px;
            align-content: center;
            justify-content: center;
            grid-auto-rows: minmax(0, 1fr);
        }

        /* Card Styles */
        .card {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.6s cubic-bezier(0.4, 0.2, 0.2, 1);
            cursor: pointer;
        }

        .card.flipped, .card.matched {
            transform: rotateY(180deg);
        }

        .card-face {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            font-size: clamp(0.8rem, 1.25vw, 1.05rem);
            font-weight: bold;
            padding: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            user-select: none;
        }

        .card-front {
            background: linear-gradient(135deg, var(--card-back), #2f3640);
            border: 2px solid #718093;
            color: var(--primary);
            font-size: 2.5rem;
        }

        .card-front:hover {
            border-color: var(--primary);
            box-shadow: 0 0 15px rgba(0, 184, 148, 0.4);
        }

        .card-back {
            background-color: var(--text-light);
            color: var(--bg-color);
            border: 3px solid var(--primary);
            transform: rotateY(180deg);
            flex-direction: column;
        }

        .card.matched .card-back {
            background-color: var(--primary);
            color: #fff;
            border-color: #fff;
        }

        .card.wrong .card-back {
            background-color: var(--danger);
            color: #fff;
            border-color: #fff;
            animation: shake 0.5s;
        }

        .tag {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }
        
        .card.matched .tag, .card.wrong .tag {
            color: rgba(255,255,255,0.7);
        }

        @keyframes shake {
            0%, 100% { transform: rotateY(180deg) translateX(0); }
            20% { transform: rotateY(180deg) translateX(-5px); }
            40% { transform: rotateY(180deg) translateX(5px); }
            60% { transform: rotateY(180deg) translateX(-5px); }
            80% { transform: rotateY(180deg) translateX(5px); }
        }

        /* Overlay Messages */
        #message-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3rem;
            font-weight: bold;
            color: var(--warning);
            text-shadow: 0 5px 15px rgba(0,0,0,0.8);
            z-index: 5;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
        }

        /* Modals */
        .overlay {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 10;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .overlay.active {
            display: flex;
        }

        .overlay-content {
            background: var(--panel-bg);
            padding: 40px;
            border-radius: 12px;
            border: 2px solid var(--primary);
            text-align: center;
            min-width: 350px;
        }
    </style>
</head>
<body>

    <div class="screen">
        
        <!-- 1. START PAGE / MAIN MENU -->
        <div id="start-page" class="view active">
            <h1>Outbreak Origins</h1>
            
            <p style="font-size: 1.1rem; max-width: 80%; margin-top: 10px; line-height: 1.5;">
                Match each <strong>Term</strong> to its <strong>Definition</strong>.<br>
                <span style="color: var(--warning);">Each level has 5 unique rounds with 3 hearts total.</span>
                Beat all 5 levels before the timer and hearts run out!
            </p>
            
            <!-- Video Tutorial -->
            <div class="video-container">
                <div class="tut-text">How to Play:</div>
                <div class="tut-grid">
                    <div class="tut-card" id="tc1"><div class="tut-front">☣️</div><div class="tut-back"><div class="tut-tag">Disease</div>Ebola</div></div>
                    <div class="tut-card" id="tc2"><div class="tut-front">☣️</div><div class="tut-back"><div class="tut-tag">Disease</div>Zika</div></div>
                    <div class="tut-card" id="tc3"><div class="tut-front">☣️</div><div class="tut-back"><div class="tut-tag">Origin</div>DR Congo</div></div>
                    <div class="tut-card" id="tc4"><div class="tut-front">☣️</div><div class="tut-back"><div class="tut-tag">Origin</div>Uganda</div></div>
                </div>
                <div class="tut-cursor">👆</div>
            </div>

            <button class="main-btn" onclick="startGame()">Start Game 🦠</button>
        </div>

        <!-- 2. GAME PLAY PAGE -->
        <div id="game-page" class="view" style="padding: 0;">
            <div id="game-header">
                <div class="stats-box">LEVEL: <span id="level-txt">1</span></div>
                <div class="stats-box" style="color: #dfe6e9;">TIME: <span id="time-txt">20</span>s</div>
                <div id="lives-display">❤️ ❤️ ❤️</div>
                <button class="main-btn" onclick="pauseGame()" style="padding: 8px 15px; font-size: 0.9rem; margin: 0;">PAUSE ⏸️</button>
            </div>

            <div id="message-overlay">MEMORIZE!</div>

            <div id="board-container">
                <div class="grid" id="grid">
                    <!-- Cards injected here -->
                </div>
            </div>
        </div>

        <!-- 3. PAUSE MODAL -->
        <div id="pause-modal" class="overlay">
            <div class="overlay-content">
                <h2 style="color: var(--warning); margin-bottom: 20px; font-size: 2rem;">Game Paused</h2>
                <button class="main-btn" onclick="toggleSound()" id="sound-btn" style="width: 100%;">Audio: ON 🔊</button>
                <br>
                <button class="main-btn" onclick="resumeGame()" style="width: 100%;">Resume ▶️</button>
                <br>
                <button class="main-btn" onclick="returnToMainMenu()" style="background-color: #636e72; width: 100%;">Main Menu 🏠</button>
            </div>
        </div>

        <!-- 4. END GAME MODAL -->
        <div id="game-over-modal" class="overlay">
            <div class="overlay-content">
                <h2 style="color: var(--danger); font-size: 3rem; margin-bottom: 10px;">OUTBREAK!</h2>
                <p style="font-size: 1.3rem; margin-bottom: 10px;">You ran out of lives.</p>
                <p style="margin: 15px 0; font-size: 1.2rem;">You survived until <strong>Level <span id="final-level">1</span></strong></p>
                
                <button class="main-btn" onclick="startGame()" style="margin-top: 20px; width: 100%;">Try Again 🔄</button>
                <button class="main-btn" onclick="returnToMainMenu()" style="background-color: #636e72; width: 100%;">Main Menu 🏠</button>
            </div>
        </div>

    </div>

    <script>
        // Terms and definitions generated from the study notes (injected by play.php)
        const diseaseData = <?= json_encode($diseaseData, JSON_HEX_TAG) ?>;

        // State Variables
        let lives = 3;
        let level = 1;
        let isPaused = false;
        let gameActive = false;
        let soundEnabled = true;
        
        let boardLocked = true; // Prevents clicking during animations/memorize phase
        let cardsFlipped = [];
        let matchedPairs = 0;
        let totalPairs = 0;
        
        let timeLeft = 10;
        let timerInterval = null;
        let lastHeartLossAt = null;
        const levelConfigs = [
            { cards: 4, time: 10 },
            { cards: 6, time: 15 },
            { cards: 8, time: 20 },
            { cards: 10, time: 25 },
            { cards: 12, time: 30 }
        ];

        // Web Audio API Synth setup
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        let bgmNode = null;

        function playSFX(type) {
            if (!soundEnabled) return;
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);

            if (type === 'flip') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(400, audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(600, audioCtx.currentTime + 0.1);
                gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
                gain.gain.linearRampToValueAtTime(0, audioCtx.currentTime + 0.1);
                osc.start(); osc.stop(audioCtx.currentTime + 0.1);
            } else if (type === 'match') {
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(440, audioCtx.currentTime); // A4
                osc.frequency.setValueAtTime(554, audioCtx.currentTime + 0.1); // C#5
                osc.frequency.setValueAtTime(659, audioCtx.currentTime + 0.2); // E5
                gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
                gain.gain.linearRampToValueAtTime(0, audioCtx.currentTime + 0.4);
                osc.start(); osc.stop(audioCtx.currentTime + 0.4);
            } else if (type === 'error') {
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(150, audioCtx.currentTime);
                osc.frequency.linearRampToValueAtTime(80, audioCtx.currentTime + 0.3);
                gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
                gain.gain.linearRampToValueAtTime(0, audioCtx.currentTime + 0.3);
                osc.start(); osc.stop(audioCtx.currentTime + 0.3);
            } else if (type === 'gameover') {
                osc.type = 'square';
                osc.frequency.setValueAtTime(200, audioCtx.currentTime);
                osc.frequency.linearRampToValueAtTime(50, audioCtx.currentTime + 1);
                gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
                gain.gain.linearRampToValueAtTime(0, audioCtx.currentTime + 1);
                osc.start(); osc.stop(audioCtx.currentTime + 1);
            }
        }

        function simulateBGM() {
            if (!soundEnabled || !gameActive || isPaused) {
                if (bgmNode) { try { bgmNode.stop(); } catch(e){} bgmNode = null; }
                return;
            }
            if (bgmNode) return; 
            
            // Creepy/Tense ambient drone
            bgmNode = audioCtx.createOscillator();
            let gainNode = audioCtx.createGain();
            bgmNode.type = 'sine';
            bgmNode.frequency.setValueAtTime(110, audioCtx.currentTime); // Low A
            
            // Add subtle oscillation for tension
            let lfo = audioCtx.createOscillator();
            lfo.frequency.value = 0.5;
            let lfoGain = audioCtx.createGain();
            lfoGain.gain.value = 10;
            lfo.connect(lfoGain);
            lfoGain.connect(bgmNode.frequency);
            lfo.start();

            gainNode.gain.setValueAtTime(0.03, audioCtx.currentTime);
            bgmNode.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            bgmNode.start();
        }

        // Navigation
        function showView(viewId) {
            document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
            document.getElementById(viewId).classList.add('active');
        }

        function updateUI() {
            document.getElementById('level-txt').innerText = level;
            document.getElementById('time-txt').innerText = timeLeft;
            document.getElementById('lives-display').innerText = "❤️ ".repeat(lives) + "🖤 ".repeat(3 - lives);
        }

        function showMessage(text, duration) {
            const msg = document.getElementById('message-overlay');
            msg.innerText = text;
            msg.style.opacity = 1;
            setTimeout(() => {
                msg.style.opacity = 0;
            }, duration);
        }

        // Game Loop
        function startGame() {
            // Audio context needs to be resumed after user gesture in some browsers
            if (audioCtx.state === 'suspended') audioCtx.resume();
            
            document.getElementById('game-over-modal').classList.remove('active');
            lives = 3;
            level = 1;
            gameActive = true;
            isPaused = false;
            lastHeartLossAt = null;
            
            showView('game-page');
            simulateBGM();
            startLevel();
        }

        function startTimer() {
            clearInterval(timerInterval);
            const currentConfig = levelConfigs[Math.min(level - 1, levelConfigs.length - 1)];
            const halfTime = Math.floor(currentConfig.time / 2);
            const isLevelOne = level === 1;
            const isLevelTwo = level === 2;
            
            timerInterval = setInterval(() => {
                if(!isPaused && gameActive) {
                    timeLeft--;
                    updateUI();

                    if (timeLeft <= 0) {
                        loseLife();
                        return;
                    }

                    if (isLevelOne) {
                        const triggerTimes = [8, 5];
                        if (triggerTimes.includes(timeLeft) && lastHeartLossAt !== timeLeft) {
                            loseLife();
                            lastHeartLossAt = timeLeft;
                        }
                        else if (isLevelTwo) {
                            const triggerTimes = [12, 8, 4];
                            if (triggerTimes.includes(timeLeft) && lastHeartLossAt !== timeLeft) {
                                loseLife();
                                lastHeartLossAt = timeLeft;
                            }
                        }
                    } else {
                        const secondsPastHalf = Math.max(0, halfTime - timeLeft);
                        if (timeLeft <= halfTime && secondsPastHalf % 5 === 0 && lastHeartLossAt !== timeLeft) {
                            loseLife();
                            lastHeartLossAt = timeLeft;
                        }
                    }
                }
            }, 1000);
        }

        function shuffleArray(array) {
            const arr = [...array];
            for (let i = arr.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [arr[i], arr[j]] = [arr[j], arr[i]];
            }
            return arr;
        }

        function startLevel() {
            lives = 3;
            updateUI();
            matchedPairs = 0;
            cardsFlipped = [];
            boardLocked = false;
            lastHeartLossAt = null;

            if (level > levelConfigs.length) {
                endGame(true);
                return;
            }

            const currentConfig = levelConfigs[level - 1];
            totalPairs = currentConfig.cards / 2;
            const totalCards = currentConfig.cards;
            timeLeft = currentConfig.time;
            document.getElementById('time-txt').innerText = timeLeft;
            
            generateBoard(totalPairs, totalCards);

            showMessage("GO!", 1000);
            startTimer();
        }

        function generateBoard(pairCount, totalCards) {
            const grid = document.getElementById('grid');
            grid.innerHTML = '';
            
            let cols = 2;
            let rows = 2;
            
            if (totalCards <= 4) { cols = 2; rows = 2; }
            else if (totalCards <= 6) { cols = 3; rows = 2; }
            else if (totalCards <= 8) { cols = 4; rows = 2; }
            else if (totalCards <= 10) { cols = 5; rows = 2; }
            else { cols = 3; rows = 4; }
            
            grid.style.gridTemplateColumns = `repeat(${cols}, minmax(0, 1fr))`;
            grid.style.gridTemplateRows = `repeat(${rows}, minmax(0, 1fr))`;
            
            // Select random data
            let shuffledData = shuffleArray(diseaseData);
            let selectedData = shuffledData.slice(0, pairCount);
            
            let cardArray = [];
            
            // Create two cards per data item (One Term, One Definition)
            selectedData.forEach(item => {
                // Term Card
                cardArray.push({
                    id: item.id,
                    type: 'Term',
                    text: item.disease
                });
                // Definition Card
                cardArray.push({
                    id: item.id,
                    type: 'Definition',
                    text: item.country
                });
            });
            
            // Shuffle the final deck
            cardArray = shuffleArray(cardArray);
            
            // Render HTML
            cardArray.forEach(cardObj => {
                const cardEl = document.createElement('div');
                cardEl.classList.add('card');
                cardEl.dataset.id = cardObj.id;
                
                cardEl.innerHTML = `
                    <div class="card-face card-front">☣️</div>
                    <div class="card-face card-back">
                        <div class="tag">${cardObj.type}</div>
                        <div>${cardObj.text}</div>
                    </div>
                `;
                
                cardEl.addEventListener('click', () => flipCard(cardEl));
                grid.appendChild(cardEl);
            });
        }

        function flipCard(card) {
            if (boardLocked || isPaused) return;
            if (card.classList.contains('flipped') || card.classList.contains('matched')) return;

            playSFX('flip');
            card.classList.add('flipped');
            cardsFlipped.push(card);

            if (cardsFlipped.length === 2) {
                checkMatch();
            }
        }

        function checkMatch() {
            boardLocked = true;
            const [card1, card2] = cardsFlipped;
            const isMatch = card1.dataset.id === card2.dataset.id;

            if (isMatch) {
                // Correct
                setTimeout(() => {
                    playSFX('match');
                    card1.classList.add('matched');
                    card2.classList.add('matched');
                    matchedPairs++;
                    cardsFlipped = [];
                    
                    if (matchedPairs === totalPairs) {
                        clearInterval(timerInterval);
                        if(lives === 3) {
                            showMessage("PERFECT CLEAR!", 1500);
                        } else {
                            showMessage("LEVEL CLEARED!", 1500);
                        }
                        
                        setTimeout(() => {
                            level++;
                            if (level > levelConfigs.length) {
                                endGame(true);
                            } else {
                                startLevel();
                            }
                        }, 1500);
                    } else {
                        boardLocked = false;
                    }
                }, 400);

            } else {
                // Wrong Match -> No life lost, just delay
                setTimeout(() => {
                    playSFX('error');
                    card1.classList.add('wrong');
                    card2.classList.add('wrong');
                    
                    setTimeout(() => {
                        card1.classList.remove('flipped', 'wrong');
                        card2.classList.remove('flipped', 'wrong');
                        cardsFlipped = [];
                        
                        if (lives > 0) boardLocked = false;
                    }, 800);
                }, 500);
            }
        }

        function loseLife() {
            lives--;
            updateUI();
            
            // Visual feedback for damage
            const screen = document.querySelector('.screen');
            screen.style.borderColor = "var(--danger)";
            setTimeout(() => screen.style.borderColor = "var(--primary)", 300);

            if (lives <= 0) {
                endGame();
            }
        }

        // Modals and Controls
        function pauseGame() {
            if(!gameActive) return;
            isPaused = true;
            document.getElementById('pause-modal').classList.add('active');
            simulateBGM(); 
        }

        function resumeGame() {
            isPaused = false;
            document.getElementById('pause-modal').classList.remove('active');
            simulateBGM();
        }

        function toggleSound() {
            soundEnabled = !soundEnabled;
            document.getElementById('sound-btn').innerText = soundEnabled ? "Audio: ON 🔊" : "Audio: OFF 🔇";
            simulateBGM();
        }

        function returnToMainMenu() {
            gameActive = false;
            isPaused = false;
            clearInterval(timerInterval);
            if (bgmNode) { try { bgmNode.stop(); } catch(e){} bgmNode = null; }
            
            document.getElementById('pause-modal').classList.remove('active');
            document.getElementById('game-over-modal').classList.remove('active');
            showView('start-page');
        }

        function endGame(victory = false) {
            gameActive = false;
            boardLocked = true;
            clearInterval(timerInterval);
            if (bgmNode) { try { bgmNode.stop(); } catch(e){} bgmNode = null; }
            
            playSFX('gameover');
            document.getElementById('final-level').innerText = victory ? levelConfigs.length : level;
            document.getElementById('game-over-modal').classList.add('active');
        }
    </script>
</body>
</html>
