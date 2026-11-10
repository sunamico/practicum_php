# Практична робота №8

**Тема:** Безпека PHP-застосунків: пошук і усунення типових вразливостей  
**Домен:** Система бронювання (готель/зали) (Варіант 8)  
**Студентка:** Савченко Руслана  
**Група:** ІО-44

* * *

## Мета роботи

- Провести аудит власного застосунку (усіх практикумів №1–7 варіанта) на три типові класи вразливостей вебзастосунків — SQL-ін'єкції, XSS (Cross-Site Scripting) і CSRF (Cross-Site Request Forgery) — а також на незахищені форми; знайдені вразливості усунути й перевірити результат. Це фінальний практикум курсу.

* * *

## Варіант №8: Система бронювання (готель/зали)

Поля й дії для перевірки:

- **SQL-ін'єкція:** параметр фільтра за номером кімнати (пошук `q`).
- **XSS:** поле `number` кімнати.
- **CSRF:** форма бронювання кімнати.
- **Незахищена дія:** видалення бронювання через GET-посилання без підтвердження.

* * *

## Структура проєкту

```
practicum_8/
├── schema.sql          # БД practicum8: rooms, bookings
├── style.css           
├── before/             # стан ДО аудиту (вразлива версія)
│   ├── db.php
│   └── index.php
├── after/              # стан ПІСЛЯ виправлень
│   ├── db.php          # display_errors=0, помилки в лог
│   ├── security.php    # сесія, CSRF-токен, екранування e()
│   └── index.php
└── csrf_attack/
    └── evil.html       # сторінка-«зловмисник» для демонстрації CSRF
```


* * *

## Структура бази даних

Для аудиту використано базу `practicum8` з таблицями `rooms` (кімнати) та `bookings` (бронювання) — структура така сама, як у попередніх практикумах варіанта.

```sql
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL
);
```

<img width="735" height="770" alt="Screenshot 2026-11-10 at 12 24 33" src="https://github.com/user-attachments/assets/4ccfe66e-9329-4e78-9598-d1ecf930e78e" />

![Застосунок до виправлень]

* * *

## Хід виконання роботи

### Крок 1. Провести аудит SQL-запитів

Переглянуто всі файли застосунку й знайдено місця, де SQL-запит формується конкатенацією значень з `$_GET`/`$_POST`: пошук за номером кімнати, додавання кімнати, бронювання та видалення бронювання. Усі вони переписані через підготовлені запити (`prepare()` / `execute()`).

**До (вразливо):**

```php
$rooms = $pdo->query("SELECT * FROM rooms WHERE number = '$q'")->fetchAll();
```

**Після (безпечно):**

```php
$stmt = $pdo->prepare('SELECT id, number, capacity, price_per_night FROM rooms WHERE number = :q ORDER BY id');
$stmt->execute(['q' => $q]);
```

**Перевірка payload** `' OR '1'='1` у полі пошуку «Номер кімнати»:

- до виправлення запит перетворюється на `WHERE number = '' OR '1'='1'` і повертає **всі** кімнати;
- після виправлення значення передається як дані, тому нічого не знайдено;
- додатково payload `'` (одна лапка) у вразливій версії викликає SQL-помилку з текстом винятку.

<img width="723" height="393" alt="Screenshot 2026-11-10 at 12 26 29" src="https://github.com/user-attachments/assets/1ce26500-6056-4d1e-ab8f-cbd51bc46fc8" />

![SQL-ін'єкція до виправлення]
<img width="709" height="300" alt="Screenshot 2026-11-10 at 12 27 50" src="https://github.com/user-attachments/assets/840161ec-cd8c-49f5-b5a9-da59a217a9b8" />

![SQL-ін'єкція після виправлення]

* * *

### Крок 2. Перевірити захист від XSS

Знайдено місця виводу даних користувача в HTML: таблиця кімнат, список бронювань (гість, номер), випадаючий список, значення поля пошуку та рядок «Результати для: …». У вразливій версії значення виводилось «як є»; у захищеній усі місця обгорнуто функцією `e()`, яка викликає `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.

**До (вразливо):**

```php
<td><?= $r['number'] ?></td>
```

**Після (безпечно):**

```php
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
// ...
<td><?= e($r['number']) ?></td>
```

**Перевірка payload:**

1. У формі «Нова кімната» в поле «Номер» введено `<img src=x onerror=alert(1)>` (збережена XSS) — після додавання й перезавантаження сторінки у вразливій версії спливає вікно `alert`; у захищеній тег показується як звичайний текст.
2. У полі пошуку введено `<script>alert(1)</script>` (відображена XSS) — у вразливій версії скрипт виконується через рядок «Результати для: …».


<img width="615" height="271" alt="Screenshot 2026-11-10 at 12 30 34" src="https://github.com/user-attachments/assets/6e12cdb7-ed8d-4f16-bdff-a5f9e2e9f906" />

![XSS до виправлення]
<img width="732" height="437" alt="Screenshot 2026-11-10 at 12 32 50" src="https://github.com/user-attachments/assets/400b2de4-ec49-4120-8076-34f2412b9bfa" />

![XSS після виправлення]

* * *

### Крок 3. Реалізувати CSRF-захист

Для форм, що змінюють дані (додавання кімнати, бронювання, видалення бронювання), додано прихований токен. Токен генерується один раз на сесію (`bin2hex(random_bytes(32))`), виводиться в кожній формі функцією `csrfField()` і перевіряється при обробці POST через `hash_equals()`. Запит без токена або з неправильним токеном відхиляється кодом `403`, дія не виконується.

**Фрагмент коду (`after/security.php`):**

```php
function startSecureSession(): void
{
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

function requireCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Недійсний CSRF-токен');
    }
}
```

**Перевірка:**

1. Сторінка `csrf_attack/evil.html` (відкрита з іншого origin: `php -S localhost:9000` у папці `csrf_attack`) надсилає форму бронювання на застосунок. У вразливій версії бронювання «CSRF-атака» створюється; у захищеній — відповідь `403 Недійсний CSRF-токен`.
2. Запит без токена через `curl`:

```bash
curl -i -X POST "http://localhost:8000/practicum_8/after/index.php" \
  -d "action=book&room_id=1&guest_name=Test&date_from=2027-03-01&date_to=2027-03-03"
```

Відповідь: `HTTP/1.1 403 Forbidden`, тіло `Недійсний CSRF-токен`. Запит із неправильним значенням `csrf_token=WRONG` дає такий самий результат.

<img width="707" height="195" alt="Screenshot 2026-11-10 at 12 40 06" src="https://github.com/user-attachments/assets/464b49f2-f390-489d-854c-f73a7a1495d5" />

![CSRF до виправлення]
<img width="757" height="270" alt="Screenshot 2026-11-10 at 12 40 33" src="https://github.com/user-attachments/assets/8c91df6d-b31b-4e42-b2b2-be44271995ed" />

![CSRF після виправлення — 403]
<img width="1047" height="179" alt="Screenshot 2026-11-10 at 12 42 46" src="https://github.com/user-attachments/assets/7b6811e8-da86-4718-b79c-0a4908c3dcb5" />

![curl без токена — 403]

* * *

### Крок 4. Перевірити базову валідацію форм

Обов'язкові поля перевіряються на сервері (атрибути `required`/`min` у HTML лише допомагають користувачу й легко обходяться). Додано функції `validateRoom()` і `validateBooking()`: порожній номер, нецілу чи від'ємну місткість, нечислову ціну, некоректні або минулі дати, неіснуючу кімнату та перетин дат бронювання відхилено з поясненням, жодних записів у БД не створюється.

**Фрагмент коду (`after/index.php`):**

```php
function validateRoom(array $in): array
{
    $errors = [];
    $number = is_scalar($in['number'] ?? null) ? trim((string) $in['number']) : '';
    if ($number === '' || mb_strlen($number) > 50) {
        $errors[] = 'Номер кімнати обов’язковий (до 50 символів).';
    }
    $capacity = filter_var($in['capacity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
    if ($capacity === false) {
        $errors[] = 'Місткість має бути цілим числом від 1 до 1000.';
    }
    // ... аналогічно ціна
    return [$errors, ['number' => $number, 'capacity' => $capacity, 'price' => $price]];
}
```

**Перевірка в обхід HTML-форми:** у DevTools → Elements видалено атрибут `required` з полів і відправлено порожню форму (або від'ємну місткість). У вразливій версії — сирий текст SQL-помилки, у захищеній — червоний банер зі списком зрозумілих помилок.

<img width="718" height="135" alt="Screenshot 2026-11-10 at 12 46 17" src="https://github.com/user-attachments/assets/bdd1f6d4-8237-4fb4-8ccb-23bcafeb45c5" />

![Валідація до виправлення]
<img width="711" height="129" alt="Screenshot 2026-11-10 at 12 47 29" src="https://github.com/user-attachments/assets/e900772a-d36b-436e-a7bd-93aafe33aa10" />

![Валідація після виправлення]

* * *

### Крок 5. Захистити «важливі» дії від виконання за GET-запитом

У вразливій версії видалення бронювання виконувалось за посиланням `index.php?delete_booking=ID` — достатньо було відкрити URL (навіть випадково, наприклад, ботом-краулером). У захищеній версії видалення виконується лише POST-запитом із CSRF-токеном, а перед відправленням форми показується підтвердження `confirm()`. Це не повний захист саме по собі (підтвердження на JavaScript обходиться), тому головний захист — POST + CSRF-токен.

**До (вразливо):**

```php
<a class="btn btn-danger" href="index.php?delete_booking=<?= $b['id'] ?>">Видалити</a>
```

**Після (безпечно):**

```php
<form method="post" onsubmit="return confirm('Видалити бронювання?');">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete_booking">
    <input type="hidden" name="id" value="<?= e($b['id']) ?>">
    <button type="submit" class="btn btn-danger">Видалити</button>
</form>
```

**Перевірка:** відкриття `before/index.php?delete_booking=1` в адресному рядку видаляє бронювання; відкриття `after/index.php?delete_booking=1` нічого не видаляє.

<img width="705" height="186" alt="Screenshot 2026-11-10 at 12 52 06" src="https://github.com/user-attachments/assets/289d1d6b-0410-4ae7-b255-833521b13363" />
<img width="709" height="170" alt="Screenshot 2026-11-10 at 12 52 38" src="https://github.com/user-attachments/assets/6f7df435-a6be-4b16-a9f4-f942a72a8f3e" />

![Видалення через GET до виправлення]
<img width="834" height="793" alt="Screenshot 2026-11-10 at 12 57 04" src="https://github.com/user-attachments/assets/5ce9c004-7eb0-49e0-83b0-74b437f36445" />

![Видалення через POST з підтвердженням]

* * *

### Крок 6. Перевірити обробку помилок

У вразливій версії виняток `PDOException` виводився користувачу напряму (текст містить назви таблиць і структуру БД). У захищеній версії помилки PHP не показуються (`display_errors = 0`), винятки перехоплюються, деталі пишуться в лог через `error_log()`, а користувач бачить загальне повідомлення.

**До (вразливо):**

```php
} catch (PDOException $e) {
    $message = $e->getMessage();   // сирий текст помилки на сторінці
}
```

**Після (безпечно):**

```php
} catch (PDOException $e) {
    error_log('practicum8: ' . $e->getMessage());
    $errors[] = 'Сталася помилка під час роботи з базою даних. Спробуйте пізніше.';
}
```

**Перевірка:** у phpMyAdmin тимчасово виконано `RENAME TABLE bookings TO bookings_tmp;` і відкрито обидві сторінки; після перевірки таблицю повернуто назад (`RENAME TABLE bookings_tmp TO bookings;`).

<img width="725" height="150" alt="Screenshot 2026-11-10 at 12 58 59" src="https://github.com/user-attachments/assets/a0dfac36-c9b3-4267-a92e-04a4dec3031b" />

![Сира помилка до виправлення]
<img width="701" height="122" alt="Screenshot 2026-11-10 at 12 59 22" src="https://github.com/user-attachments/assets/ddd3a5d1-ff73-4b3f-88ab-031b3f02053f" />

![Загальне повідомлення та запис у логу]

* * *

### Крок 7. Скласти звіт «до / після»

| Вразливість | Payload / спосіб перевірки | Результат до | Виправлення | Результат після |
|-------------|----------------------------|--------------|-------------|-----------------|
| SQL-ін'єкція (пошук за номером) | `' OR '1'='1` | показано всі кімнати | `prepare()` + `execute(['q' => $q])` | «Кімнат не знайдено» |
| XSS (поле `number`, пошук) | `<img src=x onerror=alert(1)>`, `<script>alert(1)</script>` | спливає `alert` | `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` через `e()` | payload показано як текст |
| CSRF (форма бронювання) | форма на `evil.html`, `curl` без токена | бронювання створено | токен у сесії + `hash_equals()` у `requireCsrf()` | `403 Недійсний CSRF-токен` |
| Незахищена дія (видалення) | `?delete_booking=1` у адресному рядку | бронювання видалено | POST + CSRF-токен + `confirm()` | GET-запит нічого не видаляє |
| Валідація форм | порожній/від'ємний `capacity` без `required` | сира SQL-помилка | `validateRoom()`, `validateBooking()` | банер зі списком помилок |
| Обробка помилок | `RENAME TABLE bookings ...` | сирий текст винятку | `error_log()` + загальне повідомлення | користувач бачить загальний текст |

**Фрагменти виправлень** наведено в кроках 1–6 (файли `after/index.php` і `after/security.php`).

<img width="741" height="823" alt="Screenshot 2026-11-10 at 13 01 10" src="https://github.com/user-attachments/assets/279e59b6-c39b-4021-87c6-b6326e81b5a7" />
<img width="696" height="135" alt="Screenshot 2026-11-10 at 13 02 12" src="https://github.com/user-attachments/assets/048b974d-93f9-49a3-9633-394af16def7e" />

![Застосунок після виправлень]

* * *

### Крок 8. Підготуватися до захисту курсу

**Чекліст перед захистом:**

- усі 8 практикумів відкриваються й працюють разом як єдиний застосунок (форми, API, оптимізований список, захищена версія);
- БД імпортовано з `schema.sql`, у `after/` працює пошук, додавання, бронювання та видалення;
- підготовлено демонстрацію трьох атак на `before/` і того, що `after/` їх відбиває.

* * *

## Висновки

В ході виконання фінального практикуму проведено аудит застосунку системи бронювання на SQL-ін'єкції, XSS, CSRF, незахищені форми, незахищені дії та обробку помилок. Усі знайдені вразливості усунуто: запити виконуються через підготовлені вирази, вивід екранується, форми захищені CSRF-токеном, видалення виконується лише через POST, дані перевіряються на сервері, а помилки пишуться в лог і не показуються користувачу. Результат кожного виправлення перевірено конкретними payload'ами на версіях «до» та «після».
