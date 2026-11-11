# Практична робота №7

**Тема:** Експерименти з підвищеним навантаженням: оптимізація серверних скриптів  
**Домен:** Система бронювання (готель/зали) (Варіант 8)  
**Студентка:** Савченко Руслана  
**Група:** ІО-44

* * *

## Мета роботи

- Навчитися вимірювати продуктивність власного PHP-застосунку (час виконання, використання пам'яті), проводити просте навантажувальне тестування засобом Apache Bench (`ab`) і застосовувати базові прийоми оптимізації — кешування результатів запитів, індекси бази даних, зменшення кількості SQL-запитів на один HTTP-запит.

* * *

## Варіант №8: Система бронювання (готель/зали)

- **Навантаження:** списковий ендпоінт `resource=rooms`.
- **Індекс:** стовпець `capacity`.
- **N+1:** для кожної кімнати окремий запит кількості кімнат із такою самою місткістю замість одного агрегатного запиту.
- **Кеш:** `findAvailable($capacity)` — список вільних кімнат за місткістю.

* * *

## Структура проєкту

```
practicum_7/
├── api.php        # API з практикуму №6 + профілювання, режими slow/fast, кеш
├── db.php         # PDO + лічильник SQL-запитів (runQuery)
├── cache.php      # файловий кеш із TTL, findAvailable(), інвалідація
├── schema.sql     # таблиці rooms, bookings (без індексу)
├── add_index.sql  # Крок 4: EXPLAIN → CREATE INDEX → EXPLAIN
├── seed.php       # 500 кімнат + бронювання для навантаження
├── index.html     # панель вимірювань 
├── style.css
└── script.js
```

* * *

## Структура бази даних

Створено базу `practicum7` з таблицями `rooms` та `bookings` (структура така сама, як у практикумі №6). Для навантаження скриптом `seed.php` додано 500 кімнат із місткістю від 1 до 20 (по 25 кімнат кожної місткості); кожна третя кімната зайнята на сьогодні. Індекс на `capacity` на цьому етапі свідомо відсутній.

```sql
SELECT COUNT(*) FROM rooms;                 -- 500
SELECT capacity, COUNT(*) FROM rooms GROUP BY capacity LIMIT 3;
```

<img width="953" height="265" alt="Screenshot 2026-11-10 at 11 33 17" src="https://github.com/user-attachments/assets/a139bae5-00fe-474f-aeb7-0132187bb1a8" />
<img width="774" height="561" alt="Screenshot 2026-11-10 at 11 34 10" src="https://github.com/user-attachments/assets/108d3ba9-f473-44a1-98af-a47b68107da2" />

Структура таблиці rooms і дані для навантаження

* * *

## Хід виконання роботи

### Крок 1. Виміряти базовий час виконання «як є»

Виклик спискового ендпоінта обгорнуто функцією `profile()`, яка до й після виконання викликає `microtime(true)` і `memory_get_peak_usage(true)`. Параметр `debug=1` додає результат до відповіді (блок `meta`) та пише його в `error_log`. Режим `mode=slow` — це початковий варіант з N+1 запитами («до» оптимізацій).

**Фрагмент коду (`api.php`):**

```php
function profile(callable $fn): array
{
    $start  = microtime(true);
    $result = $fn();
    $meta   = [
        'elapsed_ms'     => round((microtime(true) - $start) * 1000, 2),
        'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        'sql_queries'    => QueryStats::$count,
    ];
    return [$result, $meta];
}
```

Виклик: `http://localhost:8000/api.php?resource=rooms&mode=slow&debug=1`. Отримані значення — базова лінія («до»). 

<img width="1568" height="693" alt="image" src="https://github.com/user-attachments/assets/0cef973d-0a3e-41f8-9ec3-a0cc2ca3b697" />

 Базові вимірювання 

* * *

### Крок 2. Провести навантажувальне тестування «як є»

Запущено Apache Bench на цей самий ендпоінт у режимі `slow`: 200 запитів, 20 паралельних клієнтів. Зафіксовано `Requests per second`, `Time per request (mean)` та `Failed requests`.

```bash
ab -n 200 -c 20 "http://localhost:8000/api.php?resource=rooms&mode=slow"
```

<img width="1308" height="924" alt="image" src="https://github.com/user-attachments/assets/05332499-b3d0-4daa-bad2-0bbd9f62f298" />

 ab до оптимізацій

* * *

### Крок 3. Знайти вузьке місце — порахувати кількість SQL-запитів

Усі запити виконуються через обгортку `runQuery()`, яка збільшує статичний лічильник `QueryStats::$count`. Для сценарію варіанта 8 це показує проблему N+1: один запит на список кімнат + по одному запиту `COUNT(*)` на кожну з 500 кімнат = **501 запит** на один HTTP-виклик.

**Фрагмент коду (`db.php`, `api.php`):**

```php
final class QueryStats
{
    public static int $count = 0;
}

function runQuery(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    QueryStats::$count++;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// ПОВІЛЬНО: N+1 — окремий запит для кожної кімнати
foreach ($rooms as &$room) {
    $room['same_capacity_count'] = runQuery(
        $pdo,
        'SELECT COUNT(*) FROM rooms WHERE capacity = :capacity',
        ['capacity' => $room['capacity']]
    )->fetchColumn();
}
```

<img width="1362" height="896" alt="image" src="https://github.com/user-attachments/assets/b40b712c-423d-4abc-bf72-c214aef5c51b" />

 Лічильник SQL-запитів: 501

* * *

### Крок 4. Додати індекс на стовпець фільтрації

Виконано `EXPLAIN` для запиту з фільтром за місткістю до створення індексу, створено індекс `idx_rooms_capacity` на стовпці `capacity` і повторено `EXPLAIN`. Порівнюються стовпці `type` та `rows`.

```sql
EXPLAIN SELECT * FROM rooms WHERE capacity = 8;     -- до: type=ALL, rows=500
CREATE INDEX idx_rooms_capacity ON rooms (capacity);
EXPLAIN SELECT * FROM rooms WHERE capacity = 8;     -- після: type=ref, rows=25
```

До індексу СУБД перебирала всі рядки таблиці (повне сканування `ALL`), після — використовує індекс (`ref`) і переглядає лише рядки з потрібною місткістю.

<img width="1186" height="340" alt="image" src="https://github.com/user-attachments/assets/a39862f0-0bb4-4c84-a9d6-2fe99e8a17d9" />

 EXPLAIN до індексу 
<img width="1272" height="348" alt="image" src="https://github.com/user-attachments/assets/9c73cf8d-050d-42a5-80d9-6834aa05e2ed" />

 EXPLAIN після індексу 
<img width="1568" height="652" alt="image" src="https://github.com/user-attachments/assets/e090ed0e-cc98-44fa-98d7-1d2ca04fed63" />

* * *

### Крок 5. Усунути N+1-запит

Сценарій переписано так, щоб замість запиту в циклі виконувався один запит: кількість кімнат кожної місткості обчислюється підзапитом `GROUP BY`, який з'єднується з основною таблицею через `JOIN`. Лічильник із кроку 3 підтверджує, що кількість запитів зменшилась з 501 до 1. Стару версію залишено в режимі `mode=slow` для порівняння.

**Фрагмент коду (`api.php`):**

```php
function listRoomsFast(PDO $pdo): array
{
    $sql = 'SELECT r.id, r.number, r.capacity, r.price_per_night, c.cnt AS same_capacity_count
            FROM rooms r
            JOIN (SELECT capacity, COUNT(*) AS cnt FROM rooms GROUP BY capacity) c ON c.capacity = r.capacity
            ORDER BY r.id';
    return array_map('formatRoom', runQuery($pdo, $sql)->fetchAll());
}
```

<img width="1375" height="868" alt="image" src="https://github.com/user-attachments/assets/d04501b0-288e-404a-b3c9-9aaec5522d89" />

 Один SQL-запит замість 501

* * *

### Крок 6. Додати кешування «важкого» запиту

Для `findAvailable($capacity)` (вільні кімнати за місткістю) реалізовано файловий кеш із TTL 60 секунд: результат зберігається у JSON-файл у тимчасовій папці й повертається без звернення до БД, поки файл «свіжий». Запис виконується через тимчасовий файл + `rename()`, щоб паралельні запити не читали недописаний файл. Після зміни даних (створення кімнати, бронювання) кеш явно інвалідується функцією `invalidateRoomsCache()`; якщо б цього не було — запис застарів би за TTL, а не залишився назавжди.

**Фрагмент коду (`cache.php`):**

```php
function cachedQuery(PDO $pdo, string $key, string $sql, array $params = [], int $ttlSeconds = 60, ?bool &$hit = null): array
{
    $file = sys_get_temp_dir() . '/' . CACHE_PREFIX . md5($key) . '.json';
    $hit = false;

    if (is_file($file) && (time() - filemtime($file)) < $ttlSeconds) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached)) {
            $hit = true;
            return $cached;
        }
    }

    $result = runQuery($pdo, $sql, $params)->fetchAll();
    $tmp = $file . '.' . getmypid() . '.tmp';
    file_put_contents($tmp, json_encode($result));
    rename($tmp, $file);
    return $result;
}
```

Перевірка: на сторінці вимірювань (блок «Вільні кімнати за місткістю») перший пошук дає `MISS` і 1 SQL-запит, повторний — `HIT` і 0 SQL-запитів. Після створення кімнати (`POST`) наступний пошук знову `MISS`.

```bash
ab -n 200 -c 20 "http://localhost:8000/api.php?resource=rooms&available=1&capacity=4&cache=0"
ab -n 200 -c 20 "http://localhost:8000/api.php?resource=rooms&available=1&capacity=4&cache=1"
```

<img width="1442" height="442" alt="image" src="https://github.com/user-attachments/assets/ce81b1ee-7e5b-43ec-bd4b-b6a3324af542" />

 Кеш: MISS 
<img width="1424" height="462" alt="image" src="https://github.com/user-attachments/assets/2d51bf32-d67f-4904-86e7-e5dcf9f01e32" />

 Кеш: HIT 
<img width="1400" height="855" alt="image" src="https://github.com/user-attachments/assets/36ab74e3-f65b-4461-9974-1c3ffa515028" /> 
<img width="1400" height="841" alt="image" src="https://github.com/user-attachments/assets/b6db431e-c9ac-4355-8be7-f6445441bc38" />

* * *

### Крок 7. Повторно виміряти показники після оптимізацій

Виконано той самий профайлінг і той самий `ab`-тест на оптимізованому коді (індекс створено, N+1 усунуто — режим `fast` за замовчуванням).

```bash
ab -n 200 -c 20 "http://localhost:8000/api.php?resource=rooms"
```

Нові значення часу, пам'яті та `Requests per second` внесено до таблиці у кроці 8.

<img width="1299" height="924" alt="image" src="https://github.com/user-attachments/assets/f556edad-f20d-48e1-87e7-453aaf73bccc" />

 ab після оптимізацій

* * *

### Крок 8. Оформити короткий звіт «до / після»

**Список кімнат (`resource=rooms`):**

| Показник | До (N+1, без індексу) | Після (JOIN + індекс) |
|----------|-----------------------|-----------------------|
| Час виконання, мс | 71.84 | 2.68 |
| Пікова пам'ять, МБ | 2 | 2 |
| SQL-запитів на виклик | 501 | 1 |
| Requests per second | 23.35 | 381.81 |
| Time per request (mean), мс | 856.523 | 52.381 |
| Failed requests | 0 | 0 |

**Вільні кімнати за місткістю (`findAvailable`, 200 запитів / 20 клієнтів):**

| Показник | Без кешу | З кешем (TTL 60 с) |
|----------|----------|--------------------|
| SQL-запитів на виклик | 1 | 0 (при HIT) |
| Requests per second | 630.95 | 1345.84 |
| Time per request (mean), мс | 31.698 | 14.861 |

**Пояснення причини різниці:** замість одного запиту на список і 500 окремих запитів `COUNT(*)` застосунок виконує один запит з `GROUP BY` і `JOIN`, тому кількість SQL-запитів зменшилась з 501 до 1, а пропускна здатність зросла приблизно в 16 разів (23.35 → 381.81 запитів/с); індекс на `capacity` прибрав повне сканування таблиці (`type: ALL → ref`, `rows: 500 → 25`), а кеш `findAvailable` усунув звернення до БД при повторних запитах (приблизно вдвічі більше запитів/с).

<img width="732" height="540" alt="Screenshot 2026-11-11 at 10 48 06" src="https://github.com/user-attachments/assets/0256f533-246f-4d21-8786-622348ef5190" />

 Панель вимірювань

* * *

## Висновки

В ході виконання практичної роботи було виміряно продуктивність власного API (час виконання, пікова пам'ять, кількість SQL-запитів), проведено навантажувальне тестування за допомогою Apache Bench та застосовано три прийоми оптимізації: усунення проблеми N+1 (501 запит замінено одним запитом із `JOIN`), індекс на стовпець `capacity` (`EXPLAIN`: `ALL` → `ref`) та файловий кеш із TTL з явною інвалідацією для `findAvailable`. Порівняння «до / після» підтвердило, що найбільший виграш дає зменшення кількості SQL-запитів на один HTTP-запит.
