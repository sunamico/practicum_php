# Практична робота №6
 
**Тема:** Розробка власного прикладного програмного інтерфейсу (API)  
**Домен:** Система бронювання (готель/зали) (Варіант 8)  
**Студентка:** Савченко Руслана  
**Група:** ІО-44
 
* * *
 
## Мета роботи
 
- Спроєктувати й реалізувати простий API на PHP без фреймворків: єдина точка входу з маршрутизацією за URL-параметрами та HTTP-методом, узгоджений формат JSON-відповіді, коректні коди статусу HTTP для різних сценаріїв (успіх, помилка клієнта, відсутній ресурс).
* * *
 
## Варіант №8: Система бронювання (готель/зали)
 
- **Ресурс:** `resource=rooms` (`id, number, capacity, price_per_night`).
- **GET:** без `id` — список; з `id` — один запис або `404`.
- **POST:** без `action` — створення (обов'язкові поля перевіряються, `400` при відсутності); з `action=book` — забронювати кімнату на вказані дати.
* * *
 
## Структура проєкту
 
```
practicum_6/
├── api.php        # єдина точка входу, маршрутизація, обробники
├── db.php         # підключення до БД через PDO
├── schema.sql     # таблиці rooms, bookings і початкові дані
├── index.html     # сторінка для ручного тестування API 
├── style.css
└── script.js
```
 
* * *
 
## Структура бази даних
 
Для реалізації API було створено базу даних `practicum6` та дві таблиці: `rooms` (кімнати/зали) і `bookings` (бронювання на дати).
 
```sql
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL
);
 
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    guest_name VARCHAR(100) NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);
```
 
<img width="833" height="97" alt="Screenshot 2026-11-10 at 10 35 43" src="https://github.com/user-attachments/assets/03b49c1d-fe81-4ba1-8e55-e9244dedd2fe" />

 Структура БД practicum6  
 
* * *
 
## Хід виконання роботи
 
### Крок 1. Визначити контракт API свого домену
 
Контракт описано коментарем на початку `api.php` та таблицею нижче: метод, ресурс, параметри, очікуваний статус.
 
| Метод | Запит | Тіло | Успіх | Помилки |
|-------|-------|------|-------|---------|
| GET | `api.php?resource=rooms` | — | `200` список | `404` невідомий ресурс |
| GET | `api.php?resource=rooms&id=N` | — | `200` один запис | `404` id немає, `400` id некоректний |
| POST | `api.php?resource=rooms` | `number, capacity, price_per_night` | `201` створена кімната | `400` поле відсутнє або некоректне |
| POST | `api.php?resource=rooms&action=book` | `room_id, guest_name, date_from, date_to` | `201` бронювання | `400`, `404` немає кімнати, `409` дати зайняті |
| інший метод | `api.php?resource=rooms` | — | — | `405` + заголовок `Allow` |
 
Приклад успішної відповіді: `{"success": true, "data": [...  }`; приклад помилки: `{"success": false, "error": "..."}`.
 
**Фрагмент коду (`api.php`):**
 
```php
/**
 * GET  api.php?resource=rooms
 *      -> 200 {"success":true,"data":[{id,number,capacity,price_per_night},...  }
 * GET  api.php?resource=rooms&id=N
 *      -> 200 один запис | 404 якщо id немає | 400 якщо id некоректний
 * POST api.php?resource=rooms                      (створення)
 *      -> 201 створена кімната | 400 якщо поле відсутнє/некоректне
 * POST api.php?resource=rooms&action=book          (бронювання на дати)
 *      -> 201 бронювання | 400 | 404 немає кімнати | 409 дати зайняті
 * Інші методи для rooms -> 405 (+ заголовок Allow); невідомий resource -> 404
 */
```
 
<img width="599" height="347" alt="Screenshot 2026-11-10 at 10 36 55" src="https://github.com/user-attachments/assets/60322d74-3223-4c72-a294-af95ef4b3af0" />
 
 Контракт API у коментарі api.php  
 
* * *
 
### Крок 2. Створити єдину точку входу
 
У файлі `api.php` одразу на початку встановлено заголовок `Content-Type: application/json`, після чого отримано метод запиту (`$_SERVER['REQUEST_METHOD'  `) та параметри `resource`, `id`, `action` з `$_GET`. Усі запити до API надходять в один файл.
 
**Фрагмент коду (`api.php`):**
 
```php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
 
$method   = $_SERVER['REQUEST_METHOD'  ;
$resource = $_GET['resource'   ?? null;
$id       = $_GET['id'   ?? null;
$action   = $_GET['action'   ?? null;
```
 <img width="1466" height="816" alt="Screenshot 2026-11-10 at 10 41 40" src="https://github.com/user-attachments/assets/634707ee-24d6-4ce2-8710-fce745248a15" />
 
 Заголовок Content-Type у DevTools  
 
* * *
 
### Крок 3. Реалізувати маршрутизацію
 
Комбінацію «ресурс + метод + параметри» визначено через `if / elseif`. Для невідомого ресурсу повертається `404` з JSON-описом помилки. Єдиний формат відповіді забезпечують функції `respond()` та `fail()`.
 
**Фрагмент коду (`api.php`):**
 
```php
try {
    if ($resource !== 'rooms') {
        fail(404, 'Невідомий ресурс');
    }
    if (!in_array($method, ['GET', 'POST'  , true)) {
        header('Allow: GET, POST');
        fail(405, 'Метод не підтримується для ресурсу rooms');
    }
 
    if ($method === 'GET' && $id === null) {
        listRooms($pdo);
    } elseif ($method === 'GET') {
        // перевірка id, потім getRoom($pdo, $roomId)
    } elseif ($action === null) {
        createRoom($pdo);
    } elseif ($action === 'book') {
        bookRoom($pdo);
    } else {
        fail(400, 'Невідома дія (action)');
    }
} catch (PDOException $e) {
    error_log('API error: ' . $e->getMessage());
    fail(500, 'Внутрішня помилка сервера');
}
```
 
<img width="1466" height="465" alt="Screenshot 2026-11-10 at 10 43 26" src="https://github.com/user-attachments/assets/13e94d72-5e92-4bec-a726-dd7165377430" />
 
 Невідомий ресурс — 404  
 
* * *
 
### Крок 4. Реалізувати GET — список і одиночний запис
 
`GET api.php?resource=rooms` повертає весь список (`200`). `GET api.php?resource=rooms&id=N` повертає один запис за `id` або `404`, якщо такого `id` немає в таблиці. Значення `id` попередньо перевіряється як додатне ціле (`400` для `id=abc`).
 
**Фрагмент коду (`api.php`):**
 
```php
function listRooms(PDO $pdo): void
{
    $rows = $pdo->query('SELECT id, number, capacity, price_per_night FROM rooms ORDER BY id')->fetchAll();
    respond(200, ['success' => true, 'data' => array_map('formatRoom', $rows)  );
}
 
function getRoom(PDO $pdo, int $id): void
{
    $room = findRoom($pdo, $id);
    if ($room === null) {
        fail(404, 'Кімнату з id=' . $id . ' не знайдено');
    }
    respond(200, ['success' => true, 'data' => $room  );
}
```
 
<img width="472" height="734" alt="Screenshot 2026-11-10 at 10 45 27" src="https://github.com/user-attachments/assets/2660d6a0-24ef-4723-b451-7e9fcbb5ad97" />

 GET список  
 <img width="716" height="776" alt="Screenshot 2026-11-10 at 10 46 12" src="https://github.com/user-attachments/assets/d87cb6ac-ca01-472c-8c8b-59e9be980119" />

 GET один запис  
 <img width="708" height="439" alt="Screenshot 2026-11-10 at 10 46 46" src="https://github.com/user-attachments/assets/1b28858c-72f1-4d77-8078-8dfc92532877" />

 GET неіснуючий id — 404  
 
* * *
 
### Крок 5. Реалізувати POST — створення запису
 
`POST api.php?resource=rooms` приймає дані з `$_POST` або JSON-тіла (`php://input`), перевіряє обов'язкові поля (`400`, якщо чогось бракує), виконує `INSERT` через підготовлений запит і повертає `201` з даними створеного запису.
 
**Фрагмент коду (`api.php`):**
 
```php
function createRoom(PDO $pdo): void
{
    [$number, $capacity, $price   = validateRoom(readInput());
 
    $stmt = $pdo->prepare('INSERT INTO rooms (number, capacity, price_per_night) VALUES (:number, :capacity, :price)');
    $stmt->execute(['number' => $number, 'capacity' => $capacity, 'price' => $price  );
 
    respond(201, ['success' => true, 'data' => findRoom($pdo, (int) $pdo->lastInsertId())  );
}
```
 
Перевірка у терміналі:
 
```bash
curl -i -X POST "http://localhost:8000/api.php?resource=rooms" \ 
-H "Content-Type: application/json" \ 
-d '{"number":"305","capacity":3,"price_per_night":2100}'
```
 
Очікувана відповідь: `HTTP/1.1 201 Created` і тіло `{"success":true,"data":{"id":6,"number":"305","capacity":3,"price_per_night":2100.0}}`.
 

 <img width="822" height="128" alt="Screenshot 2026-11-10 at 10 52 03" src="https://github.com/user-attachments/assets/e660cd39-74e6-4447-af25-cb862f96c3a8" />

 POST — 201 Created  
 <img width="469" height="182" alt="image" src="https://github.com/user-attachments/assets/565024bd-b68c-4585-bc4e-eed68e183db6" />

 Новий запис у таблиці rooms  
 
* * *
 
### Крок 6. Реалізувати доменну дію, окрему від звичайного CRUD
 
Додано POST-дію `action=book` — бронювання кімнати на вказані дати. Перевіряються `room_id`, `guest_name`, формат дат (`YYYY-MM-DD`), що виїзд пізніше за заїзд і що заїзд не в минулому. Далі у транзакції (з блокуванням рядка кімнати `FOR UPDATE`) перевіряється перетин з уже існуючими бронюваннями: якщо кімната зайнята, повертається `409 Conflict`; інакше — `201` з кількістю ночей і сумою до сплати.
 
**Фрагмент коду (`api.php`):**
 
```php
$pdo->beginTransaction();
 
$stmt = $pdo->prepare('SELECT id, price_per_night FROM rooms WHERE id = :id FOR UPDATE');
$stmt->execute(['id' => $roomId  );
$room = $stmt->fetch();
if ($room === false) {
    $pdo->rollBack();
    fail(404, 'Кімнату з id=' . $roomId . ' не знайдено');
}
 
// Перетин періодів: існуюче.from < нове.to ТА існуюче.to > нове.from
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM bookings WHERE room_id = :room AND date_from < :new_to AND date_to > :new_from'
);
$stmt->execute(['room' => $roomId, 'new_to' => $to->format('Y-m-d'), 'new_from' => $from->format('Y-m-d')  );
if ((int) $stmt->fetchColumn() > 0) {
    $pdo->rollBack();
    fail(409, 'Кімната вже заброньована на ці дати');
}
```
 
Перевірка у терміналі (команду виконати двічі):
 
```bash
curl -i -X POST "http://localhost:8000/api.php?resource=rooms&action=book" \
-H "Content-Type: application/json" \
-d '{"room_id":1,"guest_name":"Руслана","date_from":"2027-03-10","date_to":"2027-03-13"}'
```
 
Перший виклик: `201 Created` (`nights: 3`, `total_price: 3600.0`). Другий виклик з тими самими датами: `409 Conflict`.
 
<img width="831" height="143" alt="Screenshot 2026-11-10 at 10 57 18" src="https://github.com/user-attachments/assets/b5d8110b-d83b-46d3-a652-069dbb9af451" />

 Бронювання — 201  
 <img width="826" height="132" alt="Screenshot 2026-11-10 at 10 57 47" src="https://github.com/user-attachments/assets/ed09af2a-17ac-42d3-9e02-c2bf31d16bea" />

 Повторне бронювання — 409  
 <img width="567" height="74" alt="Screenshot 2026-11-10 at 10 58 12" src="https://github.com/user-attachments/assets/d12d47f0-bd30-4f08-b1c3-2bc60522d011" />

 Запис у таблиці bookings  
 
* * *
 
### Крок 7. Обробити некоректні запити
 
Метод, що не підтримується для ресурсу `rooms` (наприклад, `DELETE`), повертає `405 Method Not Allowed` із заголовком `Allow: GET, POST`. Параметри, що не пройшли перевірку, повертають `400 Bad Request` з описом, яке саме поле некоректне.
 
**Фрагмент коду (`api.php`):**
 
```php
function validateRoom(array $in): array
{
    $number = is_scalar($in['number'   ?? null) ? trim((string) $in['number'  ) : '';
    if ($number === '' || mb_strlen($number) > 50) {
        fail(400, 'Поле «number» обов’язкове (1–50 символів)');
    }
 
    $capacity = filter_var($in['capacity'   ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000    );
    if ($capacity === false) {
        fail(400, 'Поле «capacity» має бути цілим числом від 1 до 1000');
    }
    // ... аналогічно price_per_night
}
```
 
Перевірка у терміналі:
 
```bash
# відсутнє поле capacity -> 400
curl -i -X POST "http://localhost:8000/api.php?resource=rooms" \
-H "Content-Type: application/json" \
-d '{"number":"306","price_per_night":1500}'
 
# непідтримуваний метод -> 405
curl -i -X DELETE "http://localhost:8000/api.php?resource=rooms&id=1"
```
 <img width="825" height="130" alt="Screenshot 2026-11-10 at 10 59 28" src="https://github.com/user-attachments/assets/34201364-08cc-4501-813f-46a251962ffe" />

 Некоректні дані — 400  
 <img width="801" height="127" alt="Screenshot 2026-11-10 at 11 00 25" src="https://github.com/user-attachments/assets/571e287f-47ce-4883-b418-6e0a0d90d752" />

 Непідтримуваний метод — 405  
 
* * *
 
### Крок 8. Перевірити API вручну
 
Усі маршрути перевірено браузером (GET), `curl -i` (POST, DELETE) та власною сторінкою `index.html`, яка показує код статусу й тіло відповіді.
 
| № | Запит | Очікуваний статус |
|---|-------|-------------------|
| 1 | `GET ?resource=rooms` | `200` |
| 2 | `GET ?resource=rooms&id=1` | `200` |
| 3 | `GET ?resource=rooms&id=9999` | `404` |
| 4 | `GET ?resource=rooms&id=abc` | `400` |
| 5 | `GET ?resource=cars` | `404` |
| 6 | `POST ?resource=rooms` (коректні дані) | `201` |
| 7 | `POST ?resource=rooms` (без `capacity`) | `400` |
| 8 | `POST ?resource=rooms&action=book` | `201` |
| 9 | `POST ?resource=rooms&action=book` (ті самі дати) | `409` |
| 10 | `DELETE ?resource=rooms&id=1` | `405` |
<img width="711" height="361" alt="Screenshot 2026-11-10 at 11 11 00" src="https://github.com/user-attachments/assets/d7526b4c-b006-4485-8993-79099dc9dff3" />
 <img width="723" height="349" alt="Screenshot 2026-11-10 at 11 11 14" src="https://github.com/user-attachments/assets/8c7a0010-3f8b-4c5b-9b1b-63d581a84016" />
<img width="714" height="400" alt="Screenshot 2026-11-10 at 11 11 36" src="https://github.com/user-attachments/assets/09c952f0-4692-42d9-b230-e080444b3d1a" />
<img width="702" height="423" alt="Screenshot 2026-11-10 at 11 11 50" src="https://github.com/user-attachments/assets/fb377a00-051d-4a4b-9196-926ba88e0a95" />
<img width="706" height="432" alt="Screenshot 2026-11-10 at 11 12 03" src="https://github.com/user-attachments/assets/d5ddf83a-98c8-4edc-9aa6-149024aa5f27" />

<img width="738" height="820" alt="Screenshot 2026-11-10 at 11 01 36" src="https://github.com/user-attachments/assets/c3bd86e4-ab4a-4f4b-8b54-7e74c3f421e9" /> 
<img width="705" height="438" alt="Screenshot 2026-11-10 at 11 12 18" src="https://github.com/user-attachments/assets/2d02a6ec-df1b-47d8-95e5-ce2e7a014f9d" />
<img width="705" height="438" alt="Screenshot 2026-11-10 at 11 12 53" src="https://github.com/user-attachments/assets/5e1887cc-ef72-44bd-b67b-83ac43516398" />
<img width="703" height="421" alt="Screenshot 2026-11-10 at 11 13 08" src="https://github.com/user-attachments/assets/9e42f3a6-8114-4464-a9bd-87d55c16908e" />

 Сторінка тестування API  
 
* * *
 
## Висновки
 
В ході виконання практичної роботи було спроєктовано та реалізовано власний API системи бронювання на чистому PHP. Створено єдину точку входу `api.php` з маршрутизацією за ресурсом, методом і параметрами, єдиний формат JSON-відповіді (`success` + `data` або `error`) та коректні коди статусу: `200`, `201`, `400`, `404`, `405`, а для конфлікту дат бронювання — `409`. Усі запити до БД виконуються через підготовлені вирази, а доменна дія `action=book` додатково захищена транзакцією від подвійного бронювання.
 
