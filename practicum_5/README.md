# Практична робота №5
**Тема:** Взаємодія фронтенду та бекенду: AJAX, обмін даними JSON  
**Домен:** Система бронювання (готель/зали) (Варіант 8)  
**Студентка:** Савченко Руслана  
**Група:** ІО-44

---

## Мета роботи
Навчитися виконувати AJAX-запити з JavaScript (fetch) до власного PHP-ендпоінта, що повертає дані у форматі JSON; опрацьовувати відповідь на клієнті та оновлювати вміст сторінки (DOM) без її повного перезавантаження; чітко розрізняти класичний цикл «форма → перезавантаження сторінки» з практикумів No1–2 і асинхронний обмін даними, на якому будується сучасний JavaScript-фронтенд.
---
## Варіант №8: Система бронювання (готель/зали)
- Ендпоінт: `api_list.php` → JSON-масив `rooms` (`id, number, capacity, price_per_night`).
- Пошук: жива фільтрація за мінімальною місткістю (`capacity`) через подію `input`, без перезавантаження.
- Додавання: `api_add.php` (POST) додає запис; після бронювання картка кімнати позначається зайнятою без перезавантаження сторінки.
___
## Структура бази даних
Для реалізації системи було створено базу даних `practicum5` та таблицю `rooms` з такою структурою:

```sql
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL,
    is_booked BOOLEAN DEFAULT FALSE
);
```

---

## Хід виконання роботи

### Крок 1. Підготувати JSON-ендпоінт списку (`api_list.php`)
Створено серверний скрипт, який підключається до бази даних через PDO (`db.php`), отримує з параметрів URL фільтр місткості (`$_GET['q']`), виконує SQL-запит `SELECT` та повертає результат у форматі JSON з відповідним HTTP-заголовком `Content-Type: application/json`.

**Фрагмент коду (`api_list.php`):**
```php
<?php
require_once 'db.php';
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';

try {
    if ($q !== '') {
        $stmt = $pdo->prepare("SELECT * FROM rooms WHERE capacity >= :q ORDER BY id DESC");
        $stmt->execute(['q' => $q]);
    } else {
        $stmt = $pdo->query("SELECT * FROM rooms ORDER BY id DESC");
    }
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Помилка сервера: ' . $e->getMessage()]);
}
?>
```
<img width="1283" height="117" alt="image" src="https://github.com/user-attachments/assets/8b29f300-7801-4b6a-a415-c7674c66fa66" />

---

### Крок 2. Підготувати сторінку з контейнером для результатів (`index.html`)
Створено розмітку HTML-сторінки з використанням пастельно-рожевого стилю (`style.css`). На сторінці розміщено:
- Поле для «живого» пошуку номерів за місткістю (`<input id="search-capacity">`).
- Форму додавання нового номера (`<form id="add-room-form">`).
- Порожній контейнер для динамічного виводу карток номерів (`<div id="rooms-list"></div>`).
- Блок для відображення помилок (`<div id="error-message"></div>`).

**Фрагмент коду (`index.html`):**
```html
<div class="container">
    <h1>Бронювання номерів (AJAX & JSON)</h1>
    
    <div class="top-controls">
        <div class="search-box">
            <label for="search-capacity">Пошук від місткості (ос.):</label>
            <input type="number" id="search-capacity" placeholder="Наприклад: 2">
        </div>

        <div class="form-box">
            <h3>Додати новий номер</h3>
            <form id="add-room-form">
                <input type="text" id="add-number" placeholder="Назва/Номер" required>
                <input type="number" id="add-capacity" placeholder="Місткість" required>
                <input type="number" id="add-price" placeholder="Ціна (грн)" required>
                <button type="submit" class="btn btn-success">Додати</button>
            </form>
        </div>
    </div>

    <div id="error-message"></div>
    <div id="rooms-list" class="rooms-grid"></div>
</div>
```
<img width="1447" height="535" alt="image" src="https://github.com/user-attachments/assets/9ebb975e-4d53-4994-bdc6-e64dd1f06106" />
---

### Крок 3. Завантажити список через AJAX при відкритті сторінки (`script.js`)
Написано функцію `loadRooms()`, яка за допомогою `fetch('api_list.php')` виконує асинхронний запит до сервера, отримує JSON-дані, генерує HTML-картки для кожного номера та вставляє їх у контейнер `#rooms-list` без використання PHP-вставок всередині HTML.

**Фрагмент коду (`script.js`):**
```javascript
async function loadRooms(query = '') {
    try {
        const url = query ? `api_list.php?q=${encodeURIComponent(query)}` : 'api_list.php';
        const response = await fetch(url);
        
        if (!response.ok) throw new Error(`Помилка сервера: ${response.status}`);
        
        const rooms = await response.json();
        renderRooms(rooms);
    } catch (err) {
        showError(`Помилка завантаження: ${err.message}`);
    }
}

function renderRooms(rooms) {
    roomsList.innerHTML = '';
    if (rooms.length === 0) {
        roomsList.innerHTML = '<p>Номерів не знайдено.</p>';
        return;
    }

    rooms.forEach(room => {
        const card = document.createElement('div');
        card.className = 'room-card';
        card.innerHTML = `
            <div class="card-header">
                <h3>Номер: ${room.number}</h3>
            </div>
            <div class="card-body">
                <p><strong>Місткість:</strong> ${room.capacity} ос.</p>
                <p><strong>Ціна:</strong> ${room.price_per_night} грн/ніч</p>
            </div>
        `;
        roomsList.appendChild(card);
    });
}

document.addEventListener('DOMContentLoaded', () => loadRooms());
```
<img width="943" height="188" alt="image" src="https://github.com/user-attachments/assets/0a0e57d2-2c2d-48a3-9a10-f9d3f5244611" />

---

### Крок 4. Реалізувати живий пошук без перезавантаження
На подію `input` для поля пошуку `#search-capacity` додано обробник. При введенні числа викликається `fetch('api_list.php?q=' + значення)`, сервер фільструє кімнати за місткістю, а клієнтський скрипт миттєво перемальовує вміст контейнера.

**Фрагмент коду (`script.js`):**
```javascript
searchInput.addEventListener('input', (e) => {
    const query = e.target.value.trim();
    loadRooms(query);
});
```
<img width="700" height="414" alt="image" src="https://github.com/user-attachments/assets/f6eb6953-31f1-4500-925c-576432e44b66" />

---

### Крок 5. Підготувати ендпоінт додавання запису (`api_add.php`)
Створено скрипт `api_add.php`, який приймає JSON-потік через `php://input`, валідує отримані дані, додає новий номер у базу даних через підготовлений PDO-запит `INSERT` та повертає JSON із доданим об'єктом і статусом 201 Created.

**Фрагмент коду (`api_add.php`):**
```php
<?php
require_once 'db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$number = trim($input['number'] ?? '');
$capacity = intval($input['capacity'] ?? 0);
$price = floatval($input['price_per_night'] ?? 0);

if (empty($number) || $capacity <= 0 || $price <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Некоректні дані']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO rooms (number, capacity, price_per_night) VALUES (:number, :capacity, :price)");
    $stmt->execute([
        'number' => $number,
        'capacity' => $capacity,
        'price' => $price
    ]);

    http_response_code(201);
    echo json_encode([
        'id' => $pdo->lastInsertId(),
        'number' => $number,
        'capacity' => $capacity,
        'price_per_night' => $price
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Не вдалося додати запис: ' . $e->getMessage()]);
}
?>
```
---

### Крок 6. Додати запис через AJAX без перезавантаження сторінки
Оброблено подію `submit` для форми додавання. Викликом `e.preventDefault()` скасовано стандартне перезавантаження сторінки. Дані форми зчитуються, формуються у JSON та надсилаються методом `POST` за допомогою `fetch('api_add.php')`. Після успішної відповіді викликається `loadRooms()`, і новий номер одразу з'являється у списку.

**Фрагмент коду (`script.js`):**
```javascript
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
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(roomData)
        });
        
        const resData = await response.json();
        if (!response.ok || resData.error) {
            throw new Error(resData.error || `Помилка сервера: ${response.status}`);
        }
        
        loadRooms(searchInput.value.trim());
        addForm.reset();
    } catch (err) {
        showError(`Помилка додавання: ${err.message}`);
    }
});
```
<img width="687" height="229" alt="image" src="https://github.com/user-attachments/assets/ab437ce0-12aa-49c8-9646-78629a71e45d" />
<img width="237" height="191" alt="image" src="https://github.com/user-attachments/assets/a0e2f20d-3ad8-4357-bd5b-95d57de4ffa0" />

---

### Крок 7. Обробити помилки мережі та сервера
Всі `fetch`-запити обгорнуті в блоки `try...catch`. Реалізовано перевірку `response.ok`. У разі збою (наприклад, помилка з'єднання з БД або відсутність таблиці) помилка відображається у вигляді графічного  банера у блоці `#error-message`, а не лише виводиться у консоль.

**Фрагмент коду (`script.js`):**
```javascript
function showError(message) {
    errorMsg.innerHTML = `<div class="error-banner">${message}</div>`;
}
```
<img width="674" height="111" alt="image" src="https://github.com/user-attachments/assets/f7663dd8-35c4-4357-9787-7ebcf0ac5e62" />

---

### Крок 8. Перевірити результат в інструментах розробника (DevTools)
Відкрито вкладку **Developer Tools** у браузорі Safari/Chrome:
1. У вкладці **Network** перевірено, що всі запити до `api_list.php` та `api_add.php` відправляються асинхронно та мають тип **fetch** (або xhr) і повертають правильні HTTP-статуси (`200 OK`, `201 Created`).
2. У вкладці **Elements** підтверджено, що оновлюється лише внутрішнє DOM-дерево блоку `#rooms-list`, а вся сторінка не перезавантажується.

<img width="1005" height="164" alt="image" src="https://github.com/user-attachments/assets/1b223257-676d-4662-a11e-81953362c583" />
<img width="855" height="577" alt="image" src="https://github.com/user-attachments/assets/8213b776-5450-4064-98e4-e9a35fb93aeb" />

---

## Висновки
В ході виконання лабораторної роботи було успішно засвоєно принципи створення сучасних Single Page Application (SPA) елементів. Було реалізовано повноцінну взаємодію клієнтської частини з сервером за допомогою AJAX (`fetch`) та формату JSON, що дозволило створити зручний «живий» пошук та додавання даних у БД без перезавантаження сторінки.
