// Крок 8: панель для вимірювань «до / після»

const errorBox = document.getElementById('error-message');

function showError(message) {
    errorBox.replaceChildren();
    const banner = document.createElement('div');
    banner.className = 'banner err';
    banner.textContent = message;
    errorBox.appendChild(banner);
}

// Запит до API з debug=1: повертає { data, meta }
async function callApi(query) {
    errorBox.replaceChildren();
    const response = await fetch(`api.php?resource=rooms&${query}&debug=1`);
    const json = await response.json();
    if (!response.ok || !json.success) {
        throw new Error(json.error || `Помилка сервера: ${response.status}`);
    }
    return json;
}

// Картка з одним показником
function metric(name, value) {
    const card = document.createElement('div');
    card.className = 'metric';
    const v = document.createElement('div');
    v.className = 'value';
    v.textContent = value;
    const n = document.createElement('div');
    n.className = 'name';
    n.textContent = name;
    card.append(v, n);
    return card;
}

function renderMetrics(container, items) {
    container.replaceChildren(...items.map(([name, value]) => metric(name, value)));
}

// Крок 1–3, 5: список кімнат у режимі slow/fast
async function loadList(mode) {
    try {
        const { data, meta } = await callApi(`mode=${mode}`);
        renderMetrics(document.getElementById('list-metrics'), [
            ['Час, мс', meta.elapsed_ms],
            ['Пік пам’яті, МБ', meta.peak_memory_mb],
            ['SQL-запитів', meta.sql_queries],
            ['Кімнат у відповіді', data.length],
        ]);
        document.getElementById('list-note').textContent =
            mode === 'slow' ? 'Режим N+1: окремий запит COUNT(*) для кожної кімнати.' : 'Режим JOIN: усі дані одним запитом.';
    } catch (err) {
        showError(`Помилка завантаження: ${err.message}`);
    }
}

// Крок 6: findAvailable() з кешем або без
async function loadAvailable(capacity, useCache) {
    try {
        const { data, meta } = await callApi(`available=1&capacity=${encodeURIComponent(capacity)}&cache=${useCache ? 1 : 0}`);
        renderMetrics(document.getElementById('avail-metrics'), [
            ['Час, мс', meta.elapsed_ms],
            ['SQL-запитів', meta.sql_queries],
            ['Кеш', meta.cache === 'off' ? 'вимкнено' : meta.cache_hit ? 'HIT (з кешу)' : 'MISS (з БД)'],
            ['Вільних кімнат', data.length],
        ]);
        const numbers = data.slice(0, 8).map((room) => room.number).join(', ');
        document.getElementById('avail-note').textContent = data.length ? `Перші кімнати: ${numbers}` : 'Вільних кімнат такої місткості немає.';
    } catch (err) {
        showError(`Помилка завантаження: ${err.message}`);
    }
}

document.getElementById('btn-slow').addEventListener('click', () => loadList('slow'));
document.getElementById('btn-fast').addEventListener('click', () => loadList('fast'));
document.getElementById('available-form').addEventListener('submit', (e) => {
    e.preventDefault();
    loadAvailable(document.getElementById('capacity').value, document.getElementById('use-cache').checked);
});
