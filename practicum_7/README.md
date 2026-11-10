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

![Структура таблиці rooms і дані для навантаження]

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

Виклик: `http://localhost/practicum_7/api.php?resource=rooms&mode=slow&debug=1`. Отримані значення — базова лінія («до»).

<img width="800" height="470" alt="Screenshot 2026-11-10 at 11 36 09" src="https://github.com/user-attachments/assets/bdecaee4-f2f6-46c9-9db8-66d49ca84c27" />

![Базові вимірювання (meta)]

* * *

### Крок 2. Провести навантажувальне тестування «як є»

Запущено Apache Bench на цей самий ендпоінт у режимі `slow`: 200 запитів, 20 паралельних клієнтів. Зафіксовано `Requests per second`, `Time per request (mean)` та `Failed requests`.

```bash
ab -n 200 -c 20 "http://localhost:8000/practicum_7/api.php?resource=rooms&mode=slow"
```

<img width="817" height="591" alt="Screenshot 2026-11-10 at 11 39 31" src="https://github.com/user-attachments/assets/6b2b348d-60da-4387-b02f-058ff61acad5" />

![ab до оптимізацій]

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

<img width="760" height="470" alt="image" src="https://github.com/user-attachments/assets/58ebd06d-648c-4725-81ba-ce5047ffa5ab" />

![Лічильник SQL-запитів: 501]

* * *

### Крок 4. Додати індекс на стовпець фільтрації

Виконано `EXPLAIN` для запиту з фільтром за місткістю до створення індексу, створено індекс `idx_rooms_capacity` на стовпці `capacity` і повторено `EXPLAIN`. Порівнюються стовпці `type` та `rows`.

```sql
EXPLAIN SELECT * FROM rooms WHERE capacity = 8;     -- до: type=ALL, rows≈500
CREATE INDEX idx_rooms_capacity ON rooms (capacity);
EXPLAIN SELECT * FROM rooms WHERE capacity = 8;     -- після: type=ref, rows≈25
```

До індексу СУБД перебирала всі рядки таблиці (повне сканування `ALL`), після — використовує індекс (`ref`) і переглядає лише рядки з потрібною місткістю.

<img width="598" height="160" alt="Screenshot 2026-11-10 at 11 43 40" src="https://github.com/user-attachments/assets/347c0a61-6b69-43ea-a876-fcd4b6088422" />

![EXPLAIN до індексу]
<img width="645" height="281" alt="Screenshot 2026-11-10 at 11 44 30" src="https://github.com/user-attachments/assets/94c2a52a-ae33-42e6-8206-b41d27482ed0" />

![EXPLAIN після індексу]

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

<img width="719" height="464" alt="Screenshot 2026-11-10 at 11 45 42" src="https://github.com/user-attachments/assets/a8807dfe-f496-45a1-bb5a-4d033709269f" />

![Один SQL-запит замість 501]

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
ab -n 200 -c 20 "http://localhost:8000/practicum_7/api.php?resource=rooms&available=1&capacity=4&cache=0"
ab -n 200 -c 20 "http://localhost:8000/practicum_7/api.php?resource=rooms&available=1&capacity=4&cache=1"
```

<img width="717" height="227" alt="Screenshot 2026-11-10 at 11 47 27" src="https://github.com/user-attachments/assets/37eb1fc8-714c-4545-b7b9-301ece87907f" />

![Кеш: MISS]
<img width="708" height="224" alt="Screenshot 2026-11-10 at 11 47 39" src="https://github.com/user-attachments/assets/288f5ecf-3fb8-43d9-9eeb-b2a8626be1d0" />

![Кеш: HIT]

* * *

### Крок 7. Повторно виміряти показники після оптимізацій

Виконано той самий профайлінг і той самий `ab`-тест на оптимізованому коді (індекс створено, N+1 усунуто — режим `fast` за замовчуванням).

```bash
ab -n 200 -c 20 "http://localhost:8000/practicum_7/api.php?resource=rooms"
```

Нові значення часу, пам'яті та `Requests per second` внесено до таблиці у кроці 8.

<img width="678" height="575" alt="Screenshot 2026-11-10 at 11 52 28" src="https://github.com/user-attachments/assets/5ca40a5b-451f-4a75-a3b9-b992e9a185f6" />

![ab після оптимізацій]

* * *

### Крок 8. Оформити короткий звіт «до / після»

**Список кімнат (`resource=rooms`):**

| Показник | До (N+1, без індексу) | Після (JOIN + індекс) |
|----------|-----------------------|-----------------------|
| Час виконання, мс | 70.04 | 2.46 |
| Пікова пам'ять, МБ | 2 | 2 |
| SQL-запитів на виклик | 501 | 1 |
| Requests per second | 3431.71 | 6430.66 |
| Time per request (mean), мс | 5.828 | 3.110 |
| Failed requests | 0 | 0 |

**Вільні кімнати за місткістю (`findAvailable`, 200 запитів / 20 клієнтів):**

| Показник | Без кешу | З кешем (TTL 60 с) |
|----------|----------|--------------------|
| SQL-запитів на виклик | 1 | 0 (при HIT) |
| Requests per second | 563.01 | 1057.93 |
| Time per request (mean), мс | 35.524 | 18.905 |

**Пояснення причини різниці:** замість одного запиту на список і 500 окремих запитів `COUNT(*)` (по одному на кожну кімнату) застосунок виконує єдиний запит з агрегацією `GROUP BY` і `JOIN`, а індекс на `capacity` дозволяє СУБД не сканувати всю таблицю; кеш для `findAvailable` прибирає звернення до БД взагалі, поки запис не застаріє.

<img width="716" height="537" alt="Screenshot 2026-11-10 at 11 54 09" src="https://github.com/user-attachments/assets/42f66276-e2b3-47e5-a2b7-6de85b92379a" />

![Панель вимірювань]

* * *

## Висновки

В ході виконання практичної роботи було виміряно продуктивність власного API (час виконання, пікова пам'ять, кількість SQL-запитів), проведено навантажувальне тестування за допомогою Apache Bench та застосовано три прийоми оптимізації: усунення проблеми N+1 (501 запит замінено одним запитом із `JOIN`), індекс на стовпець `capacity` (`EXPLAIN`: `ALL` → `ref`) та файловий кеш із TTL з явною інвалідацією для `findAvailable`. Порівняння «до / після» підтвердило, що найбільший виграш дає зменшення кількості SQL-запитів на один HTTP-запит.
