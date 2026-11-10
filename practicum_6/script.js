// Крок 8: ручна перевірка маршрутів API з браузера

// Дата через N днів у форматі YYYY-MM-DD
function dayOffset(days) {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

// Готові запити (body може бути функцією — дати рахуються від сьогодні)
const presets = [
    { label: 'GET: список', method: 'GET', url: 'api.php?resource=rooms' },
    { label: 'GET: id=1', method: 'GET', url: 'api.php?resource=rooms&id=1' },
    { label: 'GET: id=9999 (404)', method: 'GET', url: 'api.php?resource=rooms&id=9999' },
    { label: 'GET: id=abc (400)', method: 'GET', url: 'api.php?resource=rooms&id=abc' },
    { label: 'GET: resource=cars (404)', method: 'GET', url: 'api.php?resource=cars' },
    {
        label: 'POST: створити',
        method: 'POST',
        url: 'api.php?resource=rooms',
        body: { number: '305', capacity: 3, price_per_night: 2100 },
    },
    {
        label: 'POST: без capacity (400)',
        method: 'POST',
        url: 'api.php?resource=rooms',
        body: { number: '306', price_per_night: 1500 },
    },
    {
        label: 'POST: action=book',
        method: 'POST',
        url: 'api.php?resource=rooms&action=book',
        body: () => ({ room_id: 1, guest_name: 'Руслана', date_from: dayOffset(30), date_to: dayOffset(33) }),
    },
    { label: 'DELETE (405)', method: 'DELETE', url: 'api.php?resource=rooms&id=1' },
];

const form = document.getElementById('request-form');
const methodEl = document.getElementById('method');
const urlEl = document.getElementById('url');
const bodyEl = document.getElementById('body');
const statusEl = document.getElementById('status');
const outputEl = document.getElementById('output');

// Підставити готовий запит у форму
function applyPreset(p) {
    methodEl.value = p.method;
    urlEl.value = p.url;
    const body = typeof p.body === 'function' ? p.body() : p.body;
    bodyEl.value = body ? JSON.stringify(body, null, 2) : '';
}

// Показати код статусу кольоровим бейджем
function showStatus(code, text, ms, allow) {
    statusEl.replaceChildren();
    const badge = document.createElement('span');
    badge.className = 'badge ' + (code < 300 ? 'ok' : code < 500 ? 'warn' : 'err');
    badge.textContent = `${code} ${text}`;
    statusEl.append(badge, ` ${ms} мс`);
    if (allow) {
        statusEl.append(` · Allow: ${allow}`);
    }
}

// Надіслати запит і вивести статус + тіло відповіді
async function sendRequest() {
    const method = methodEl.value;
    const options = { method, headers: {} };
    if (method === 'POST') {
        options.headers['Content-Type'] = 'application/json';
        options.body = bodyEl.value;
    }

    const started = performance.now();
    try {
        const response = await fetch(urlEl.value.trim(), options);
        const text = await response.text();
        const ms = Math.round(performance.now() - started);
        showStatus(response.status, response.statusText, ms, response.headers.get('Allow'));
        try {
            outputEl.textContent = JSON.stringify(JSON.parse(text), null, 2);
        } catch {
            outputEl.textContent = text;
        }
    } catch (err) {
        statusEl.textContent = 'Помилка мережі';
        outputEl.textContent = err.message;
    }
}

presets.forEach((p) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-ghost';
    btn.textContent = p.label;
    btn.addEventListener('click', () => {
        applyPreset(p);
        sendRequest();
    });
    document.getElementById('presets').appendChild(btn);
});

form.addEventListener('submit', (e) => {
    e.preventDefault();
    sendRequest();
});

applyPreset(presets[0]);
