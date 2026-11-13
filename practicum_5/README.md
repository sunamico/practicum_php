# Практична робота №5

**Тема:** Взаємодія фронтенду та бекенду: AJAX, обмін даними у форматі JSON
**Домен:** Система бронювання (готель/зали) — варіант №8
**Студентка:** Савченко Руслана
**Група:** ІО-44

---

## Мета роботи

Навчитися виконувати AJAX-запити з JavaScript (`fetch`) до власних PHP-ендпоінтів, що повертають дані у форматі JSON; опрацьовувати відповідь на клієнті та оновлювати вміст сторінки (DOM) без її повного перезавантаження; чітко розрізняти класичний цикл «форма → перезавантаження сторінки» з практикумів №1–2 і асинхронний обмін даними, на якому будується сучасний JavaScript-фронтенд.

---

## Варіант №8: Система бронювання (готель/зали)

- **Ендпоінт списку:** `api_list.php` → JSON `{"rooms": [...]}`, де кожен елемент має поля `id`, `number`, `capacity`, `price_per_night`, `is_booked`.
- **Пошук:** жива фільтрація за мінімальною місткістю (параметр `capacity`) через подію `input`, без перезавантаження.
- **Додавання:** `api_add.php` (POST) додає номер у БД.
- **Бронювання:** `api_book.php` (POST) позначає номер зайнятим; картка на сторінці оновлюється без перезавантаження.

### Структура проєкту

| Файл | Призначення |
|---|---|
| `init.sql` | створення БД `practicum5`, таблиці `rooms` і тестових даних |
| `db.php` | підключення до MySQL через PDO |
| `api_list.php` | список номерів (з необов'язковим фільтром за місткістю) |
| `api_add.php` | додавання номера |
| `api_book.php` | бронювання номера |
| `index.html`, `style.css` | розмітка та оформлення |
| `script.js` | уся клієнтська логіка (`fetch`, рендер, обробка помилок) |



---

## Структура бази даних

Створено базу даних `practicum5` (кодування `utf8mb4`) та таблицю `rooms`:

```sql
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL,
    is_booked BOOLEAN NOT NULL DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Поле `is_booked` зберігає стан бронювання (`0` — вільно, `1` — зайнято).
<img width="480" height="175" alt="Screenshot 2026-11-13 at 18 07 56" src="https://github.com/user-attachments/assets/ef956808-611f-40cb-a4a0-1ec659c8939c" />

Таблиця rooms у phpMyAdmin

---

## Хід виконання роботи

### Крок 1. Підготувати JSON-ендпоінт списку (`api_list.php`)

Скрипт підключається до БД через PDO (`db.php`), читає з URL необов'язковий параметр `capacity`, виконує підготовлений запит `SELECT` і повертає результат у форматі JSON із заголовком `Content-Type: application/json`. Значення параметра передається через плейсхолдер, що захищає від SQL-ін'єкцій.

```php
<?php
require_once 'db.php';
header('Content-Type: application/json');

try {
    $min_capacity = isset($_GET['capacity']) && is_numeric($_GET['capacity']) ? (int)$_GET['capacity'] : 0;

    if ($min_capacity > 0) {
        $stmt = $pdo->prepare('SELECT * FROM rooms WHERE capacity >= :capacity ORDER BY id DESC');
        $stmt->execute([':capacity' => $min_capacity]);
        $rooms = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query('SELECT * FROM rooms ORDER BY id DESC');
        $rooms = $stmt->fetchAll();
    }

    echo json_encode(['rooms' => $rooms]);
} catch (Exception $e) {
    error_log('api_list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Внутрішня помилка сервера']);
}
```
<img width="2566" height="234" alt="image" src="https://github.com/user-attachments/assets/7a99a051-532b-47a9-8c97-f2a4de242b70" />


---

### Крок 2. Підготувати сторінку з контейнером для результатів (`index.html`)

Розмітка містить:

- поле «живого» пошуку `#search-capacity`;
- форму додавання `#add-room-form`;
- блок для повідомлень про помилки `#error-message`;
- порожній контейнер `#results`, який заповнюється карточками через JavaScript.

```html
<div class="top-controls">
    <div class="search-box">
        <label for="search-capacity">Пошук від місткості (ос.):</label>
        <input type="number" id="search-capacity" placeholder="Наприклад: 2" min="1">
    </div>

    <div class="form-box">
        <h3>Додати новий номер</h3>
        <form id="add-room-form">
            <input type="text" id="add-number" placeholder="Назва/Номер" maxlength="50" required>
            <input type="number" id="add-capacity" placeholder="Місткість" min="1" max="1000" step="1" required>
            <input type="number" id="add-price" placeholder="Ціна (грн)" step="0.01" min="0.01" required>
            <button type="submit" class="btn btn-success">Додати</button>
        </form>
    </div>
</div>

<div id="error-message" aria-live="polite"></div>
<div id="results" class="rooms-grid"></div>
```

Оформлення (пастельно-рожева гама) винесено у `style.css`.
<img width="1470" height="708" alt="Screenshot 2026-11-13 at 18 08 24" src="https://github.com/user-attachments/assets/b4a8ac8c-b1f2-4538-a761-ed9f6905dbe4" />

Головна сторінка

---

### Крок 3. Завантажити список через AJAX при відкритті сторінки (`script.js`)

Функція `loadRooms()` за допомогою `fetch` робить асинхронний запит до `api_list.php`, перевіряє `response.ok`, розбирає JSON і передає масив `rooms` у `renderRooms()`. Функція `renderRooms()` створює карточку для кожного номера: заброньовані отримують клас `booked` і мітку «Зайнято», вільні — кнопку «Забронювати». Усі дані з БД проходять через `escapeHTML()` для захисту від XSS.

```js
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
```

```js
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
```

Лічильник `requestCounter` гарантує, що повільна застаріла відповідь не перезапише новішу.
<img width="1419" height="369" alt="Screenshot 2026-11-13 at 18 09 04" src="https://github.com/user-attachments/assets/bad7a75a-7120-410c-855b-2be1a3fcfe02" />

Список номерів, завантажений через AJAX

---

### Крок 4. Реалізувати живий пошук без перезавантаження

На подію `input` поля `#search-capacity` додано обробник із короткою затримкою (debounce, 250 мс), щоб не надсилати запит на кожне натискання клавіші. Сервер фільтрує номери за умовою `capacity >= значення`, а `renderRooms()` перемальовує вміст контейнера.

```js
searchInput.addEventListener('input', (e) => {
    const value = e.target.value.trim();
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadRooms(value), 250);
});
```
<img width="722" height="472" alt="Screenshot 2026-11-13 at 18 09 39" src="https://github.com/user-attachments/assets/53ca5b19-1ae8-49b0-acf1-e620967cff04" />

Живий пошук за місткістю

---

### Крок 5. Підготувати ендпоінт додавання запису (`api_add.php`)

Скрипт приймає лише `POST` (інакше `405`), читає JSON з `php://input` і валідує поля на сервері:

- `number` — непорожній рядок до 50 символів;
- `capacity` — ціле число від 1 до 1000 (`FILTER_VALIDATE_INT`, тож `2.7` відхиляється);
- `price_per_night` — число більше 0, не більше двох знаків після коми.

Далі виконується підготовлений запит `INSERT`; у відповідь повертається доданий об'єкт зі статусом `201 Created`.

```php
$capacity = filter_var($data['capacity'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 1000]
]);
if ($capacity === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Місткість (capacity) має бути цілим числом від 1 до 1000']);
    exit;
}

$priceRaw = isset($data['price_per_night']) ? (string)$data['price_per_night'] : '';
if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $priceRaw) || (float)$priceRaw <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ціна (price_per_night) має бути числом більше 0 (до 2 знаків після коми)']);
    exit;
}
$price = number_format((float)$priceRaw, 2, '.', '');

try {
    $stmt = $pdo->prepare('INSERT INTO rooms (number, capacity, price_per_night) VALUES (:number, :capacity, :price)');
    $stmt->execute([':number' => $number, ':capacity' => $capacity, ':price' => $price]);

    http_response_code(201);
    echo json_encode([
        'id'              => (int)$pdo->lastInsertId(),
        'number'          => $number,
        'capacity'        => $capacity,
        'price_per_night' => $price,
        'is_booked'       => 0
    ]);
} catch (Exception $e) {
    error_log('api_add error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Не вдалося додати запис через помилку сервера']);
}
```

Деталі помилок БД записуються лише в лог сервера (`error_log`); клієнт отримує загальне повідомлення.
<img width="680" height="214" alt="Screenshot 2026-11-13 at 18 18 44" src="https://github.com/user-attachments/assets/2c9d271f-2ae9-4fcd-aa0e-f20499468857" />

<img width="675" height="210" alt="Screenshot 2026-11-13 at 18 18 59" src="https://github.com/user-attachments/assets/7c215bf6-a350-4c29-96f2-188000ecf07d" />

---

### Крок 6. Додати запис через AJAX без перезавантаження сторінки

На подію `submit` форми викликається `e.preventDefault()`, що скасовує стандартне перезавантаження. Дані формуються в JSON і надсилаються методом `POST` через `fetch`. Поки запит виконується, кнопка вимкнена (захист від подвійного надсилання). Після успіху форма очищується, а список оновлюється викликом `loadRooms()` з урахуванням поточного фільтра.

```js
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
```
<img width="689" height="236" alt="Screenshot 2026-11-13 at 18 11 00" src="https://github.com/user-attachments/assets/1047f47f-7186-4d37-b6fc-04dbb3c14fad" /> 
Додавання нового номера
<img width="226" height="173" alt="Screenshot 2026-11-13 at 18 11 17" src="https://github.com/user-attachments/assets/c10f7ab7-2511-4f7d-85b2-7271cd467ec0" />

Новий номер у списку без перезавантаження

---

### Крок 7. Бронювання номера (`api_book.php`)

Ендпоінт `api_book.php` приймає `{ "id": N }` і виконує атомарне оновлення `UPDATE rooms SET is_booked = 1 WHERE id = :id AND is_booked = 0`. Перевірка `rowCount()` дозволяє розрізнити результати:

- `200` — номер успішно заброньовано;
- `404` — номера з таким `id` немає;
- `409` — номер уже заброньований (захист від подвійного бронювання).

```php
$stmt = $pdo->prepare('UPDATE rooms SET is_booked = 1 WHERE id = :id AND is_booked = 0');
$stmt->execute([':id' => $id]);

if ($stmt->rowCount() === 1) {
    echo json_encode(['success' => true]);
    exit;
}
// інакше: 404 (номера немає) або 409 (уже заброньовано)
```

На клієнті кліки по кнопках «Забронювати» обробляються через делегування подій на контейнері `#results`. Після успішної відповіді картка отримує клас `booked`, а кнопка замінюється міткою «Зайнято» — без перезавантаження та без повторного запиту списку. Якщо сервер повернув помилку (наприклад, номер уже забронював хтось інший), список синхронізується з БД і показується повідомлення.

```js
resultsContainer.addEventListener('click', (e) => {
    const button = e.target.closest('button[data-book-id]');
    if (button) bookRoom(button);
});
```

<img width="233" height="175" alt="Screenshot 2026-11-13 at 18 11 58" src="https://github.com/user-attachments/assets/f66eef8e-e176-4653-8a20-7f6a27f7b8f4" /> <img width="236" height="185" alt="Screenshot 2026-11-13 at 18 12 21" src="https://github.com/user-attachments/assets/f9d8b07b-7f66-47e3-9662-3834bffd088a" />

Номер до та після бронювання

---

### Крок 8. Обробити помилки мережі та сервера

Усі `fetch`-запити обгорнуто в `try...catch` з перевіркою `response.ok`. Відповідь розбирається функцією `readJson()`, яка не «падає», якщо сервер повернув не JSON. Помилка виводиться у вигляді банера в `#error-message`, а не лише в консоль. Текст вставляється через `textContent`, тому повідомлення з сервера не може виконати HTML/JS-код.

```js
function showError(message) {
    const alert = document.createElement('div');
    alert.className = 'error-alert';
    alert.textContent = message;
    errorMsg.replaceChildren(alert);
}
```

Для перевірки зупинено MySQL у MAMP. Сервер повернув статус `500` із загальним повідомленням, а детальна причина (`Connection refused`) потрапила лише в лог сервера.
<img width="1391" height="390" alt="Screenshot 2026-11-13 at 18 13 07" src="https://github.com/user-attachments/assets/51bae9f1-3251-4bb7-8e84-e3c8cc203c5d" />


Банер помилки при вимкненій БД

---

### Крок 9. Перевірити результат в інструментах розробника (DevTools)

У браузері відкрито Developer Tools:

1. На вкладці **Network** видно, що запити до `api_list.php`, `api_add.php` та `api_book.php` мають тип **fetch** і повертають очікувані статуси (`200 OK`, `201 Created`, а при помилках — `400`, `404`, `409`, `500`).
2. На вкладці **Elements** видно, що змінюється лише внутрішнє дерево контейнера `#results`, а сторінка не перезавантажується.
<img width="692" height="182" alt="Screenshot 2026-11-13 at 18 15 38" src="https://github.com/user-attachments/assets/5378bef5-22b4-4704-9438-e72d4cd95733" />

Network у DevTools
<img width="804" height="585" alt="Screenshot 2026-11-13 at 18 17 03" src="https://github.com/user-attachments/assets/44a9a35d-b564-4312-a781-37e981cea359" />

Elements у DevTools
---

## Висновки

У ході практичної роботи реалізовано взаємодію клієнтської частини з сервером за допомогою AJAX (`fetch`) і формату JSON: три PHP-ендпоінти (`api_list.php`, `api_add.php`, `api_book.php`) та JavaScript-клієнт, який оновлює DOM без перезавантаження сторінки. Реалізовано жива фільтрація за місткістю, додавання номерів і бронювання.

Окрему увагу приділено надійності й безпеці: SQL-запити виконуються через підготовлені вирази PDO; вхідні дані валідуються на сервері; дані з БД екрануються перед вставкою в HTML (захист від XSS); бронювання виконується атомарним запитом, який не допускає подвійного бронювання; деталі внутрішніх помилок не показуються користувачеві, а записуються в лог сервера. Помилки мережі та сервера відображаються користувачу у зрозумілому вигляді.
