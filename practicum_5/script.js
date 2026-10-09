document.addEventListener('DOMContentLoaded', () => {
    const resultsContainer = document.getElementById('results');
    const searchInput = document.getElementById('search-capacity');
    const addForm = document.getElementById('add-room-form');
    const errorMsg = document.getElementById('error-message');

    // Крок 3: Завантажити список через AJAX при відкритті сторінки
    async function loadRooms(minCapacity = '') {
        try {
            errorMsg.innerHTML = '';
            
            // Формуємо URL для GET-запиту з параметром або без нього
            const url = minCapacity ? `api_list.php?capacity=${encodeURIComponent(minCapacity)}` : 'api_list.php';
            
            const response = await fetch(url);
            
            // Крок 7: Обробити помилки мережі та сервера
            if (!response.ok) throw new Error(`Помилка сервера: ${response.status}`);
            
            const data = await response.json();
            
            if (data.error) throw new Error(data.error);
            
            renderRooms(data.rooms);
        } catch (err) {
            showError(`Помилка завантаження: ${err.message}`);
        }
    }

    // Функція рендерингу отриманого JSON у DOM
    function renderRooms(rooms) {
        resultsContainer.innerHTML = '';
        
        if (!rooms || rooms.length === 0) {
            resultsContainer.innerHTML = '<p class="no-data">Відповідних номерів не знайдено.</p>';
            return;
        }

        rooms.forEach(room => {
            const card = document.createElement('div');
            card.className = `room-card ${room.is_booked == 1 ? 'booked' : ''}`;
            card.id = `room-${room.id}`;
            
            // Екрануємо дані для захисту від XSS
            const safeNumber = escapeHTML(room.number);
            
            card.innerHTML = `
                <div class="card-header">
                    <h3>${safeNumber}</h3>
                </div>
                <div class="card-body">
                    <p><strong>Місткість:</strong> ${room.capacity} ос.</p>
                    <p><strong>Ціна:</strong> ${room.price_per_night} грн/ніч</p>
                </div>
                <div class="card-footer">
                    ${room.is_booked == 1 
                        ? '<span class="badge danger">Зайнято</span>' 
                        : `<button class="btn btn-primary" onclick="bookRoom(${room.id})">Забронювати</button>`}
                </div>
            `;
            resultsContainer.appendChild(card);
        });
    }

    // Захист від XSS
    function escapeHTML(str) {
        if (!str) return '';
        return str.toString().replace(/[&<>'"]/g, 
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );
    }

    function showError(message) {
        errorMsg.innerHTML = `<div class="error-alert">${message}</div>`;
    }

    // Крок 4: Живий пошук без перезавантаження
    searchInput.addEventListener('input', (e) => {
        const val = e.target.value.trim();
        loadRooms(val);
    });

// Крок 6: Додати запис через AJAX без перезавантаження
    addForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorMsg.innerHTML = '';
        
        const roomData = {
            number: document.getElementById('add-number').value,
            capacity: document.getElementById('add-capacity').value,
            price_per_night: document.getElementById('add-price').value
        };

        try {
            const response = await fetch('api_add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(roomData)
            });
            
            const resData = await response.json();
            
            if (!response.ok || resData.error) {
                throw new Error(resData.error || `Помилка сервера: ${response.status}`);
            }
            
            // Якщо все успішно — оновлюємо список і очищаємо форму
            loadRooms(searchInput.value.trim());
            addForm.reset();
            
        } catch (err) {
            showError(`Помилка додавання: ${err.message}`);
        }
    });

    // Додатково: Бронювання номера (Зміна стану на "Зайнято" без перезавантаження)
    window.bookRoom = async function(id) {
        try {
            const response = await fetch('api_book.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            
            if (!response.ok) throw new Error('Помилка сервера при бронюванні');
            
            const result = await response.json();
            if (result.error) throw new Error(result.error);
            
            // Оновлюємо DOM без повторного запиту до БД
            const card = document.getElementById(`room-${id}`);
            if (card) {
                card.classList.add('booked');
                const btn = card.querySelector('.card-footer button');
                if (btn) {
                    btn.outerHTML = '<span class="badge danger">Зайнято</span>';
                }
            }
        } catch (err) {
            showError(`Помилка бронювання: ${err.message}`);
        }
    };

    // Ініціалізація - завантажуємо список при відкритті сторінки
    loadRooms();
});
