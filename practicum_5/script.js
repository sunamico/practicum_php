document.addEventListener('DOMContentLoaded', () => {
    const resultsContainer = document.getElementById('results');
    const searchInput = document.getElementById('search-capacity');
    const addForm = document.getElementById('add-room-form');
    const errorMsg = document.getElementById('error-message');

    let requestCounter = 0; // щоб ігнорувати застарілі відповіді
    let searchTimer = null; // debounce для живого пошуку

    // Захист від XSS при вставці даних через innerHTML
    function escapeHTML(value) {
        return String(value ?? '').replace(/[&<>'"]/g, tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag]));
    }

    // Текст помилки вставляється через textContent, а не innerHTML
    function showError(message) {
        const alert = document.createElement('div');
        alert.className = 'error-alert';
        alert.textContent = message;
        errorMsg.replaceChildren(alert);
    }

    function clearError() {
        errorMsg.replaceChildren();
    }

    // Сервер може повернути не-JSON (наприклад, при збої) — не падаємо
    async function readJson(response) {
        try {
            return await response.json();
        } catch {
            return {};
        }
    }

    // Завантаження списку через AJAX (з необов'язковим фільтром)
    async function loadRooms(minCapacity = '') {
        const requestId = ++requestCounter;
        try {
            const url = minCapacity
                ? `api_list.php?capacity=${encodeURIComponent(minCapacity)}`
                : 'api_list.php';

            const response = await fetch(url);
            const data = await readJson(response);

            if (requestId !== requestCounter) return; // прийшла застаріла відповідь

            if (!response.ok || data.error) {
                throw new Error(data.error || `Помилка сервера: ${response.status}`);
            }

            clearError();
            renderRooms(data.rooms);
        } catch (err) {
            if (requestId !== requestCounter) return;
            showError(`Помилка завантаження: ${err.message}`);
        }
    }

    function renderRooms(rooms) {
        resultsContainer.innerHTML = '';

        if (!rooms || rooms.length === 0) {
            resultsContainer.innerHTML = '<p class="no-data">Відповідних номерів не знайдено.</p>';
            return;
        }

        rooms.forEach(room => {
            const booked = Number(room.is_booked) === 1;
            const card = document.createElement('div');
            card.className = `room-card ${booked ? 'booked' : ''}`;
            card.id = `room-${Number(room.id)}`;

            card.innerHTML = `
                <div class="card-header">
                    <h3>${escapeHTML(room.number)}</h3>
                </div>
                <div class="card-body">
                    <p><strong>Місткість:</strong> ${escapeHTML(room.capacity)} ос.</p>
                    <p><strong>Ціна:</strong> ${escapeHTML(room.price_per_night)} грн/ніч</p>
                </div>
                <div class="card-footer">
                    ${booked
                        ? '<span class="badge danger">Зайнято</span>'
                        : `<button type="button" class="btn btn-primary" data-book-id="${Number(room.id)}">Забронювати</button>`}
                </div>
            `;
            resultsContainer.appendChild(card);
        });
    }

    // Живий пошук без перезавантаження (з невеликою затримкою)
    searchInput.addEventListener('input', (e) => {
        const value = e.target.value.trim();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadRooms(value), 250);
    });

    // Додавання запису через AJAX
    addForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError();

        const submitBtn = addForm.querySelector('button[type="submit"]');
        const roomData = {
            number: document.getElementById('add-number').value.trim(),
            capacity: document.getElementById('add-capacity').value.trim(),
            price_per_night: document.getElementById('add-price').value.trim()
        };

        submitBtn.disabled = true;
        try {
            const response = await fetch('api_add.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(roomData)
            });

            const resData = await readJson(response);

            if (!response.ok || resData.error) {
                throw new Error(resData.error || `Помилка сервера: ${response.status}`);
            }

            addForm.reset();
            loadRooms(searchInput.value.trim());
        } catch (err) {
            showError(`Помилка додавання: ${err.message}`);
        } finally {
            submitBtn.disabled = false;
        }
    });

    // Бронювання: делегування подій на контейнері результатів
    resultsContainer.addEventListener('click', (e) => {
        const button = e.target.closest('button[data-book-id]');
        if (button) bookRoom(button);
    });

    async function bookRoom(button) {
        const id = Number(button.dataset.bookId);
        button.disabled = true;
        clearError();

        try {
            const response = await fetch('api_book.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });

            const result = await readJson(response);

            if (!response.ok || result.error) {
                throw new Error(result.error || `Помилка сервера: ${response.status}`);
            }

            // Оновлюємо картку в DOM без нового запиту до БД
            const card = button.closest('.room-card');
            card.classList.add('booked');
            button.outerHTML = '<span class="badge danger">Зайнято</span>';
        } catch (err) {
            // Синхронізуємо список зі станом БД (напр., номер уже забронював хтось інший)
            await loadRooms(searchInput.value.trim());
            showError(`Помилка бронювання: ${err.message}`);
        }
    }

    loadRooms();
});
