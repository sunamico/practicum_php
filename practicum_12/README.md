# Практична робота №12

**Тема:** Анімація, Canvas та інтерактивні/ігрові елементи  
**Домен:** Вікторина/квіз зі збереженням результатів (Варіант 8)  
**Студентка:** Савченко Руслана  
**Група:** ІО-44

* * *

## Мета роботи

- Додати до застосунку елемент інтерактивності через Canvas API — анімований таймер зворотного відліку, пов'язаний із реальними даними вікторини (питання, відповіді, збережені результати), а не декоративний елемент «сам по собі». Навчитися будувати цикл анімації через `requestAnimationFrame` і коректно перемальовувати кадр.

* * *

## Варіант №8: Вікторина/квіз зі збереженням результатів

- **Технологія:** Canvas.
- **Елемент:** анімований таймер зворотного відліку на відповідь — дуга, що зменшується щокадру через `ctx.arc()` у циклі `requestAnimationFrame`.

* * *

## Структура проєкту

```
practicum_12/
├── index.html   # розмітка: <canvas id="timer">, питання, історія результатів
├── style.css    # інтерфейс
└── script.js    # дані, цикл анімації, керування, localStorage
```


* * *

## Хід виконання роботи

### Крок 1. Обрати технологію відповідно до свого варіанта

Для варіанта №8 обрано **Canvas**: таймер зворотного відліку малюється дугою, довжина якої щокадру зменшується пропорційно часу, що лишився на відповідь. Кожне питання вікторини має ліміт 15 секунд (`TIME_PER_QUESTION`).

<img width="946" height="754" alt="Screenshot 2026-11-10 at 13 56 36" src="https://github.com/user-attachments/assets/fa253d95-e0e6-4bd1-8b75-b5efc488a5b0" />

 Початковий стан вікторини  

* * *

### Крок 2 (CSS-варіант). Не застосовується

Крок призначений для CSS-варіанта (`transition` / `@keyframes`). За варіантом №8 використовується Canvas, тому крок пропущено; CSS застосовано лише для оформлення інтерфейсу та підсвічування правильної/неправильної відповіді (`.option.correct`, `.option.wrong`).

* * *

### Крок 3 (Canvas-варіант). Додати `<canvas>` і отримати 2D-контекст

У розмітку додано елемент `<canvas>` із фіксованими `width`/`height` (140×140). У скрипті отримано 2D-контекст і додатково враховано щільність пікселів екрана (`devicePixelRatio`), щоб дуга була чіткою на Retina-екранах.

**Фрагмент коду (`index.html`):**

```html
<canvas id="timer" width="140" height="140" role="img" aria-label="Таймер зупинено"></canvas>
```

**Фрагмент коду (`script.js`):**

```js
const SIZE = 140;
const canvas = document.getElementById('timer');
const ctx = canvas.getContext('2d');
const dpr = window.devicePixelRatio || 1;
canvas.width = SIZE * dpr;
canvas.height = SIZE * dpr;
canvas.style.width = SIZE + 'px';
canvas.style.height = SIZE + 'px';
ctx.scale(dpr, dpr);
```

<img width="698" height="60" alt="Screenshot 2026-11-10 at 13 52 41" src="https://github.com/user-attachments/assets/1300eb01-d988-4fa6-b5d2-a936601a71cf" />

 Canvas і 2D-контекст  

* * *

### Крок 4. Реалізувати функцію відображення поточного стану

Функція `drawTimer()` читає стан із об'єкта `state` (скільки мілісекунд залишилось) та масиву об'єктів `QUESTIONS`, а не використовує захардкоджені числа. Вона малює «трек» (повне світло-рожеве коло), дугу частки часу, що залишилась (від верхньої точки за годинниковою стрілкою; колір плавно змінюється з рожевого на червоний, коли часу майже не лишилось), і число секунд у центрі.

Дані застосунку — масив об'єктів `QUESTIONS` (текст питання, варіанти, індекс правильної відповіді) та історія результатів у `localStorage` (ключ `quizResults`).

**Фрагмент коду (`script.js`):**

```js
const QUESTIONS = [
    {
        text: 'Який HTTP-код статусу означає, що новий запис успішно створено?',
        options: ['200 OK', '201 Created', '204 No Content', '302 Found'  ,
        correct: 1,
    },
    // ... ще 7 питань
  ;

function drawTimer() {
    const total = TIME_PER_QUESTION * 1000;
    const fraction = Math.max(0, Math.min(1, state.remainingMs / total));
    const seconds = Math.ceil(state.remainingMs / 1000);
    const center = SIZE / 2;
    const radius = 56;

    ctx.clearRect(0, 0, SIZE, SIZE);
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
    // ... число секунд у центрі
}
```

<img width="321" height="196" alt="Screenshot 2026-11-10 at 13 57 07" src="https://github.com/user-attachments/assets/731409b2-c2e8-4d7f-b59e-16615d103630" />

 Таймер посеред відліку  
<img width="290" height="192" alt="Screenshot 2026-11-10 at 13 57 22" src="https://github.com/user-attachments/assets/a720e651-04c3-4e31-8e49-fba8507c9b0d" />

 Таймер наприкінці відліку  

* * *

### Крок 5 (Canvas-варіант). Реалізувати цикл анімації через `requestAnimationFrame`

У кожному кадрі функція `tick(ts)` спочатку оновлює стан (віднімає від `remainingMs` реальний час між кадрами, тому швидкість не залежить від частоти екрана), потім `drawTimer()` викликає `clearRect` і перемальовує кадр, після чого планує наступний кадр. Коли час вичерпано, цикл зупиняється й викликається `onTimeout()`.

**Фрагмент коду (`script.js`):**

```js
function tick(ts) {
    if (state.lastTs !== null) {
        state.remainingMs -= ts - state.lastTs;   // оновити стан
    }
    state.lastTs = ts;

    if (state.remainingMs <= 0) {
        state.remainingMs = 0;
        state.rafId = null;
        drawTimer();
        onTimeout();
        return;
    }

    drawTimer();                                   // clearRect + перемалювати кадр
    state.rafId = requestAnimationFrame(tick);     // запланувати наступний кадр
}

function startTimer() {
    stopTimer();
    state.lastTs = null;   // після паузи час не «стрибає»
    state.rafId = requestAnimationFrame(tick);
}
```

<img width="433" height="379" alt="Screenshot 2026-11-10 at 13 54 37" src="https://github.com/user-attachments/assets/5100b95b-d85b-4e24-834d-256c00edd863" />

 Цикл requestAnimationFrame  

* * *

### Крок 6. Прив'язати анімацію до реальної дії користувача

Таймер не існує окремо від застосунку: він стартує при показі кожного питання (`showQuestion()`), зупиняється, коли користувач обирає відповідь (`onAnswer()` → `stopTimer()`), а якщо час вийшов — застосунок сам зараховує питання як пропущене (`onTimeout()`), показує правильну відповідь і через 1,5 с переходить до наступного. Після останнього питання результат (кількість правильних, загальна кількість, тривалість) зберігається в `localStorage` і відображається в таблиці «Історія результатів».

**Фрагмент коду (`script.js`):**

```js
function onAnswer(chosen) {
    if (state.phase !== 'question' || state.paused) {
        return;
    }
    stopTimer();   // відповіли — таймер зупиняється
    const correct = chosen === QUESTIONS[state.index  .correct;
    if (correct) {
        state.score++;
    }
    revealAnswer(chosen, correct ? 'Правильно!' : 'Неправильно.');
}

function saveResult(result) {
    const results = loadResults();
    results.push(result);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(results.slice(-MAX_SAVED)));
}
```

<img width="951" height="681" alt="image" src="https://github.com/user-attachments/assets/c0e5bc1e-45d0-40b6-86ab-bcbf6f2a564b" />

 Відповідь користувача  
<img width="967" height="744" alt="Screenshot 2026-11-10 at 14 01 16" src="https://github.com/user-attachments/assets/6d8830ab-a087-4640-baf1-92cfc7c5577e" />

 Тайм-аут  
<img width="950" height="766" alt="Screenshot 2026-11-10 at 13 55 22" src="https://github.com/user-attachments/assets/9c0f33d0-37b2-476b-a12a-fc6ee3732fdd" />


 Результат та історія  
<img width="1470" height="146" alt="Screenshot 2026-11-10 at 13 58 50" src="https://github.com/user-attachments/assets/bf170a5c-96d3-44fe-9ccc-fa1ecb83c2d8" />

 localStorage  

* * *

### Крок 7. Додати керування за потребою варіанта

Додано кнопку «Почати / Почати знову», кнопку «Пауза / Продовжити» (під час паузи питання розмивається, щоб не можна було «думати безкоштовно», а таймер не рухається), а також керування клавіатурою: клавіші `1`–`4` обирають варіант відповіді, `P` ставить гру на паузу. Кнопка «Очистити» видаляє збережену історію.

**Фрагмент коду (`script.js`):**

```js
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
        return;
    }
    if (e.key >= '1' && e.key <= '9') {
        onAnswer(Number(e.key) - 1);
    } else if (e.key.toLowerCase() === 'p') {
        togglePause();
    }
});
```

<img width="891" height="630" alt="image" src="https://github.com/user-attachments/assets/dc13a725-27e3-4ff1-9b4a-0d16df682e48" />

 Пауза  

* * *

### Крок 8. Перевірити продуктивність і коректне завершення

Перевірено, що анімація не «гальмує» інтерфейс і зупиняється, коли це доречно:

- після відповіді, тайм-ауту, паузи та завершення гри викликається `cancelAnimationFrame` (функція `stopTimer()`), а `state.rafId` стає `null`;
- при переході на іншу вкладку (`visibilitychange`) гра автоматично ставиться на паузу, а при закритті сторінки (`pagehide`) цикл зупиняється;
- під час відліку у вкладці **Performance** DevTools кадри йдуть стабільно (~60 FPS), після відповіді нові `Animation Frame Fired` не з'являються.

**Фрагмент коду (`script.js`):**

```js
function stopTimer() {
    if (state.rafId !== null) {
        cancelAnimationFrame(state.rafId);
        state.rafId = null;
    }
}

document.addEventListener('visibilitychange', () => {
    if (document.hidden && state.phase === 'question' && !state.paused) {
        togglePause();
    }
});
window.addEventListener('pagehide', stopTimer);
```

**Перевірка в консолі браузера:** під час відліку `state.rafId` — число; після вибору відповіді `state.rafId === null`.

<img width="1023" height="410" alt="image" src="https://github.com/user-attachments/assets/3b52716d-4887-40ad-8956-ca6d1b67e551" />

 Performance  
<img width="1024" height="703" alt="image" src="https://github.com/user-attachments/assets/e49c854f-1d28-4159-a2a2-c45035d843f6" />
<img width="504" height="296" alt="image" src="https://github.com/user-attachments/assets/7974403f-185a-4a80-8594-73f8b6bca1f7" />

 state.rafId у консолі  

* * *

### Крок 9. Прокоментувати код, зробити коміт із описовим повідомленням

У коді кожен блок позначено коротким коментарем із номером кроку (`// Крок 5: цикл анімації через requestAnimationFrame` тощо). Зміни зафіксовано комітом із описовим повідомленням.

* * *

## Висновки

В ході виконання практичної роботи до застосунку-вікторини додано інтерактивний Canvas-елемент — анімований таймер зворотного відліку, дуга якого скорочується щокадру в циклі `requestAnimationFrame`. Анімація пов'язана з реальними даними та діями: таймер стартує для кожного питання з масиву `QUESTIONS`, зупиняється при відповіді, обробляє тайм-аут і ставиться на паузу, а підсумок гри зберігається в `localStorage`. Час відліку рахується за реальними інтервалами між кадрами, кадр перемальовується через `clearRect`, а цикл коректно завершується через `cancelAnimationFrame`.
