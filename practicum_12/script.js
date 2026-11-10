'use strict';

// Крок 4: дані застосунку — масив об’єктів (питання вікторини)
const QUESTIONS = [
    {
        text: 'Який HTTP-код статусу означає, що новий запис успішно створено?',
        options: ['200 OK', '201 Created', '204 No Content', '302 Found'],
        correct: 1,
    },
    {
        text: 'Яка функція PHP встановлює код статусу HTTP-відповіді?',
        options: ['set_status()', 'header_status()', 'http_response_code()', 'response_code()'],
        correct: 2,
    },
    {
        text: 'Що захищає від SQL-ін’єкцій при роботі з PDO?',
        options: ['Конкатенація значень у запит', 'Підготовлені запити prepare()/execute()', 'Функція md5()', 'Метод exec() без параметрів'],
        correct: 1,
    },
    {
        text: 'Яка функція екранує спецсимволи HTML і захищає від XSS?',
        options: ['addslashes()', 'trim()', 'htmlspecialchars()', 'strtoupper()'],
        correct: 2,
    },
    {
        text: 'Який метод Canvas очищає прямокутну область перед перемальовуванням кадру?',
        options: ['clearRect()', 'eraseRect()', 'removeRect()', 'resetRect()'],
        correct: 0,
    },
    {
        text: 'Чим скасовують запланований кадр анімації?',
        options: ['clearTimeout()', 'stopAnimation()', 'cancelAnimationFrame()', 'removeFrame()'],
        correct: 2,
    },
    {
        text: 'Який код статусу HTTP повертають, якщо метод не підтримується для ресурсу?',
        options: ['400 Bad Request', '404 Not Found', '500 Internal Server Error', '405 Method Not Allowed'],
        correct: 3,
    },
    {
        text: 'Для чого потрібен індекс на стовпці бази даних?',
        options: ['Шифрує значення у стовпці', 'Прискорює пошук і фільтрацію за цим стовпцем', 'Прискорює вставку нових рядків', 'Створює резервну копію таблиці'],
        correct: 1,
    },
];

const TIME_PER_QUESTION = 15;      // секунд на питання
const FEEDBACK_DELAY = 1500;       // мс показу правильної відповіді
const STORAGE_KEY = 'quizResults'; // ключ localStorage
const MAX_SAVED = 20;              // скільки результатів зберігати

// Крок 3: canvas із фіксованим розміром і 2D-контекст (+ різкість на Retina)
const SIZE = 140;
const canvas = document.getElementById('timer');
const ctx = canvas.getContext('2d');
const dpr = window.devicePixelRatio || 1;
canvas.width = SIZE * dpr;
canvas.height = SIZE * dpr;
canvas.style.width = SIZE + 'px';
canvas.style.height = SIZE + 'px';
ctx.scale(dpr, dpr);

const panelEl = document.getElementById('quiz-panel');
const questionEl = document.getElementById('question-text');
const optionsEl = document.getElementById('options');
const feedbackEl = document.getElementById('feedback');
const progressEl = document.getElementById('progress');
const scoreEl = document.getElementById('score');
const btnStart = document.getElementById('btn-start');
const btnPause = document.getElementById('btn-pause');
const btnClear = document.getElementById('btn-clear');

// Стан гри
const state = {
    index: 0,
    score: 0,
    phase: 'idle',          // idle | question | feedback | finished
    remainingMs: TIME_PER_QUESTION * 1000,
    paused: false,
    rafId: null,            // id запланованого кадру (null — анімація зупинена)
    lastTs: null,           // мітка часу попереднього кадру
    startedAt: 0,
    feedbackTimeout: null,
    lastSeconds: null,
};

// ---------- Крок 4: відображення поточного стану таймера ----------

const COLOR_FULL = [231, 90, 141];   // рожевий — часу багато
const COLOR_EMPTY = [179, 38, 30];   // червоний — час закінчується

function lerpColor(from, to, t) {
    const rgb = from.map((v, i) => Math.round(v + (to[i] - v) * t));
    return `rgb(${rgb.join(',')})`;
}

// Малює кадр: трек, дуга частки часу, що лишилась, і число секунд
function drawTimer() {
    const total = TIME_PER_QUESTION * 1000;
    const fraction = Math.max(0, Math.min(1, state.remainingMs / total));
    const seconds = Math.ceil(state.remainingMs / 1000);
    const center = SIZE / 2;
    const radius = 56;

    ctx.clearRect(0, 0, SIZE, SIZE);   // Крок 5: очистити кадр

    ctx.lineWidth = 12;
    ctx.lineCap = 'round';

    // Трек (повне коло)
    ctx.beginPath();
    ctx.arc(center, center, radius, 0, Math.PI * 2);
    ctx.strokeStyle = '#ffe1ea';
    ctx.stroke();

    // Дуга: починається зверху і скорочується разом із часом
    if (fraction > 0) {
        ctx.beginPath();
        ctx.arc(center, center, radius, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * fraction);
        ctx.strokeStyle = lerpColor(COLOR_EMPTY, COLOR_FULL, fraction);
        ctx.stroke();
    }

    // Число секунд у центрі
    ctx.fillStyle = '#4a2635';
    ctx.font = '700 34px system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(String(seconds), center, center + 2);

    // Для скрінрідерів оновлюємо підпис лише при зміні секунди
    if (seconds !== state.lastSeconds) {
        state.lastSeconds = seconds;
        canvas.setAttribute('aria-label', `Залишилось ${seconds} с`);
    }
}

// ---------- Крок 5: цикл анімації через requestAnimationFrame ----------

function tick(ts) {
    // Оновити стан: віднімаємо реальний час між кадрами
    if (state.lastTs !== null) {
        state.remainingMs -= ts - state.lastTs;
    }
    state.lastTs = ts;

    if (state.remainingMs <= 0) {
        state.remainingMs = 0;
        state.rafId = null;
        drawTimer();
        onTimeout();
        return;
    }

    drawTimer();
    state.rafId = requestAnimationFrame(tick);
}

function startTimer() {
    stopTimer();
    state.lastTs = null;   // щоб після паузи час не «стрибнув»
    state.rafId = requestAnimationFrame(tick);
}

// Крок 8: коректна зупинка анімації
function stopTimer() {
    if (state.rafId !== null) {
        cancelAnimationFrame(state.rafId);
        state.rafId = null;
    }
}

// ---------- Крок 6: анімація прив’язана до дій користувача ----------

function startQuiz() {
    stopTimer();
    clearTimeout(state.feedbackTimeout);
    state.index = 0;
    state.score = 0;
    state.paused = false;
    state.startedAt = Date.now();
    panelEl.classList.remove('is-paused');
    btnStart.textContent = 'Почати знову';
    scoreEl.textContent = 'Правильних відповідей: 0';
    showQuestion();
}

function showQuestion() {
    const q = QUESTIONS[state.index];
    state.phase = 'question';
    state.remainingMs = TIME_PER_QUESTION * 1000;   // таймер стартує заново для кожного питання

    questionEl.textContent = q.text;
    feedbackEl.textContent = '';
    progressEl.textContent = `Питання ${state.index + 1} з ${QUESTIONS.length}`;
    btnPause.disabled = false;
    btnPause.textContent = 'Пауза';

    optionsEl.replaceChildren();
    q.options.forEach((text, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'option';
        btn.textContent = `${i + 1}. ${text}`;
        btn.addEventListener('click', () => onAnswer(i));
        optionsEl.appendChild(btn);
    });

    drawTimer();
    startTimer();
}

function onAnswer(chosen) {
    if (state.phase !== 'question' || state.paused) {
        return;
    }
    if (chosen < 0 || chosen >= QUESTIONS[state.index].options.length) {
        return;   // натиснули цифру, якої немає серед варіантів
    }
    stopTimer();   // відповіли — таймер зупиняється
    const correct = chosen === QUESTIONS[state.index].correct;
    if (correct) {
        state.score++;
    }
    revealAnswer(chosen, correct ? 'Правильно!' : 'Неправильно.');
}

function onTimeout() {
    revealAnswer(null, 'Час вийшов.');
}

// Показати правильну відповідь і через паузу перейти далі
function revealAnswer(chosen, message) {
    const q = QUESTIONS[state.index];
    state.phase = 'feedback';
    btnPause.disabled = true;

    optionsEl.querySelectorAll('.option').forEach((btn, i) => {
        btn.disabled = true;
        if (i === q.correct) {
            btn.classList.add('correct');
        } else if (i === chosen) {
            btn.classList.add('wrong');
        }
    });

    feedbackEl.textContent = chosen === q.correct ? message : `${message} Правильна відповідь: ${q.options[q.correct]}.`;
    scoreEl.textContent = `Правильних відповідей: ${state.score}`;
    state.feedbackTimeout = setTimeout(nextQuestion, FEEDBACK_DELAY);
}

function nextQuestion() {
    state.index++;
    if (state.index >= QUESTIONS.length) {
        finishQuiz();
    } else {
        showQuestion();
    }
}

// ---------- Крок 7: керування — старт/пауза, миша й клавіатура ----------

function togglePause() {
    if (state.phase !== 'question') {
        return;
    }
    state.paused = !state.paused;
    panelEl.classList.toggle('is-paused', state.paused);
    btnPause.textContent = state.paused ? 'Продовжити' : 'Пауза';
    if (state.paused) {
        stopTimer();
    } else {
        startTimer();
    }
}

document.addEventListener('keydown', (e) => {
    if (e.ctrlKey || e.metaKey || e.altKey) {
        return;   // не перехоплюємо системні комбінації
    }
    if (e.key >= '1' && e.key <= '9') {
        onAnswer(Number(e.key) - 1);
    } else if (e.key.toLowerCase() === 'p') {
        togglePause();
    }
});

// Крок 8: анімація не працює у фоновій вкладці — ставимо на паузу
document.addEventListener('visibilitychange', () => {
    if (document.hidden && state.phase === 'question' && !state.paused) {
        togglePause();
    }
});
window.addEventListener('pagehide', stopTimer);

// ---------- Збереження результатів (localStorage) ----------

function loadResults() {
    try {
        const data = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        return Array.isArray(data) ? data : [];
    } catch {
        return [];
    }
}

function saveResult(result) {
    try {
        const results = loadResults();
        results.push(result);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(results.slice(-MAX_SAVED)));
    } catch {
        // сховище недоступне (приватний режим) — гра працює далі
    }
}

function renderHistory() {
    const results = loadResults().slice().reverse();
    const tbody = document.getElementById('history');
    tbody.replaceChildren();

    results.forEach((r) => {
        const tr = document.createElement('tr');
        const cells = [
            new Date(r.date).toLocaleString('uk-UA'),
            `${r.score} з ${r.total}`,
            `${r.durationSec} с`,
        ];
        cells.forEach((text) => {
            const td = document.createElement('td');
            td.textContent = text;
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });

    document.getElementById('history-empty').hidden = results.length > 0;
    btnClear.disabled = results.length === 0;

    const bestEl = document.getElementById('best');
    if (results.length) {
        const best = results.reduce((a, b) => (b.score > a.score ? b : a));
        bestEl.textContent = `Найкращий результат: ${best.score} з ${best.total}`;
    } else {
        bestEl.textContent = '';
    }
}

function finishQuiz() {
    stopTimer();
    state.phase = 'finished';
    saveResult({
        date: new Date().toISOString(),
        score: state.score,
        total: QUESTIONS.length,
        durationSec: Math.round((Date.now() - state.startedAt) / 1000),
    });

    questionEl.textContent = `Готово! Ваш результат: ${state.score} з ${QUESTIONS.length}`;
    optionsEl.replaceChildren();
    feedbackEl.textContent = '';
    progressEl.textContent = 'Вікторину завершено';
    btnPause.disabled = true;
    ctx.clearRect(0, 0, SIZE, SIZE);
    canvas.setAttribute('aria-label', 'Таймер зупинено');
    renderHistory();
}

btnStart.addEventListener('click', startQuiz);
btnPause.addEventListener('click', togglePause);
btnClear.addEventListener('click', () => {
    if (confirm('Видалити всі збережені результати?')) {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch {
            // ігноруємо
        }
        renderHistory();
    }
});

// Початковий стан
progressEl.textContent = `Питання 0 з ${QUESTIONS.length}`;
drawTimer();
renderHistory();
