<?php
/**
 * Практична робота №7 — оптимізація API (варіант 8: система бронювання).
 * Основа — api.php з практикуму №6 (контракт той самий). Додано:
 *  - профілювання: microtime() + memory_get_peak_usage() + лічильник SQL (Кроки 1, 3);
 *  - GET rooms&mode=slow  — N+1 («до»);  mode=fast (за замовч.) — один JOIN («після») (Крок 5);
 *  - GET rooms&available=1&capacity=N[&cache=0] — findAvailable() з файловим кешем (Крок 6);
 *  - інвалідація кешу після створення кімнати та бронювання;
 *  - debug=1 додає до відповіді блок meta (час, пам’ять, SQL, cache_hit) і пише його в error_log.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cache.php';

$method   = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? null;
$id       = $_GET['id'] ?? null;
$action   = $_GET['action'] ?? null;
$debug    = ($_GET['debug'] ?? '') === '1';

// ---------- Допоміжні функції (як у практикумі №6) ----------

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(int $status, string $message): void
{
    respond($status, ['success' => false, 'error' => $message]);
}

function readInput(): array
{
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($type, 'application/json') === false && !empty($_POST)) {
        return $_POST;
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        fail(400, 'Тіло запиту має бути коректним JSON-об’єктом');
    }
    return $data;
}

function formatRoom(array $row): array
{
    $room = [
        'id'              => (int) $row['id'],
        'number'          => $row['number'],
        'capacity'        => (int) $row['capacity'],
        'price_per_night' => (float) $row['price_per_night'],
    ];
    if (isset($row['same_capacity_count'])) {
        $room['same_capacity_count'] = (int) $row['same_capacity_count'];
    }
    return $room;
}

function findRoom(PDO $pdo, int $id): ?array
{
    $row = runQuery($pdo, 'SELECT id, number, capacity, price_per_night FROM rooms WHERE id = :id', ['id' => $id])->fetch();
    return $row ? formatRoom($row) : null;
}

function validateRoom(array $in): array
{
    $number = is_scalar($in['number'] ?? null) ? trim((string) $in['number']) : '';
    if ($number === '' || mb_strlen($number) > 50) {
        fail(400, 'Поле «number» обов’язкове (1–50 символів)');
    }
    $capacity = filter_var($in['capacity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
    if ($capacity === false) {
        fail(400, 'Поле «capacity» має бути цілим числом від 1 до 1000');
    }
    $price = filter_var($in['price_per_night'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($price === false || $price <= 0 || $price > 1000000) {
        fail(400, 'Поле «price_per_night» має бути числом більше 0');
    }
    return [$number, $capacity, (float) $price];
}

function parseDate(mixed $value, string $field): DateTimeImmutable
{
    $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
    if ($date === false) {
        fail(400, 'Поле «' . $field . '» має бути датою у форматі YYYY-MM-DD');
    }
    $errors = DateTimeImmutable::getLastErrors();
    if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        fail(400, 'Поле «' . $field . '» містить неіснуючу дату');
    }
    return $date;
}

// ---------- Крок 1: профілювання ----------

// Виконує $fn і повертає [результат, meta]: час, пікова пам’ять, кількість SQL
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

// Відповідь зі списком; meta показується лише при debug=1
function respondData(array $data, array $meta, bool $debug): void
{
    $payload = ['success' => true, 'data' => $data];
    if ($debug) {
        $payload['meta'] = $meta;
        error_log('[api] ' . json_encode($meta, JSON_UNESCAPED_UNICODE));
    }
    respond(200, $payload);
}

// ---------- Обробники списку ----------

// ПОВІЛЬНО (Крок 3): N+1 — окремий запит COUNT(*) для кожної кімнати
function listRoomsSlow(PDO $pdo): array
{
    $rooms = runQuery($pdo, 'SELECT id, number, capacity, price_per_night FROM rooms ORDER BY id')->fetchAll();
    foreach ($rooms as &$room) {
        $room['same_capacity_count'] = runQuery(
            $pdo,
            'SELECT COUNT(*) FROM rooms WHERE capacity = :capacity',
            ['capacity' => $room['capacity']]
        )->fetchColumn();
    }
    unset($room);
    return array_map('formatRoom', $rooms);
}

// ШВИДКО (Крок 5): один запит — агрегат у підзапиті + JOIN
function listRoomsFast(PDO $pdo): array
{
    $sql = 'SELECT r.id, r.number, r.capacity, r.price_per_night, c.cnt AS same_capacity_count
            FROM rooms r
            JOIN (SELECT capacity, COUNT(*) AS cnt FROM rooms GROUP BY capacity) c ON c.capacity = r.capacity
            ORDER BY r.id';
    return array_map('formatRoom', runQuery($pdo, $sql)->fetchAll());
}

// ---------- Обробники з практикуму №6 (+ інвалідація кешу) ----------

function getRoom(PDO $pdo, int $id): void
{
    $room = findRoom($pdo, $id);
    if ($room === null) {
        fail(404, 'Кімнату з id=' . $id . ' не знайдено');
    }
    respond(200, ['success' => true, 'data' => $room]);
}

function createRoom(PDO $pdo): void
{
    [$number, $capacity, $price] = validateRoom(readInput());
    runQuery(
        $pdo,
        'INSERT INTO rooms (number, capacity, price_per_night) VALUES (:number, :capacity, :price)',
        ['number' => $number, 'capacity' => $capacity, 'price' => $price]
    );
    $roomId = (int) $pdo->lastInsertId();

    invalidateRoomsCache();   // Крок 6: дані змінилися — кеш скидаємо
    respond(201, ['success' => true, 'data' => findRoom($pdo, $roomId)]);
}

function bookRoom(PDO $pdo): void
{
    $in = readInput();

    $roomId = filter_var($in['room_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($roomId === false) {
        fail(400, 'Поле «room_id» має бути додатним цілим числом');
    }
    $guest = is_scalar($in['guest_name'] ?? null) ? trim((string) $in['guest_name']) : '';
    if ($guest === '' || mb_strlen($guest) > 100) {
        fail(400, 'Поле «guest_name» обов’язкове (1–100 символів)');
    }
    $from = parseDate($in['date_from'] ?? null, 'date_from');
    $to   = parseDate($in['date_to'] ?? null, 'date_to');
    if ($to <= $from) {
        fail(400, 'Поле «date_to» має бути пізніше за «date_from»');
    }
    if ($from < new DateTimeImmutable('today')) {
        fail(400, 'Поле «date_from» не може бути в минулому');
    }

    $pdo->beginTransaction();

    $room = runQuery($pdo, 'SELECT id, price_per_night FROM rooms WHERE id = :id FOR UPDATE', ['id' => $roomId])->fetch();
    if ($room === false) {
        $pdo->rollBack();
        fail(404, 'Кімнату з id=' . $roomId . ' не знайдено');
    }

    $busy = runQuery(
        $pdo,
        'SELECT COUNT(*) FROM bookings WHERE room_id = :room AND date_from < :new_to AND date_to > :new_from',
        ['room' => $roomId, 'new_to' => $to->format('Y-m-d'), 'new_from' => $from->format('Y-m-d')]
    )->fetchColumn();
    if ((int) $busy > 0) {
        $pdo->rollBack();
        fail(409, 'Кімната вже заброньована на ці дати');
    }

    runQuery(
        $pdo,
        'INSERT INTO bookings (room_id, guest_name, date_from, date_to) VALUES (:room, :guest, :from_d, :to_d)',
        ['room' => $roomId, 'guest' => $guest, 'from_d' => $from->format('Y-m-d'), 'to_d' => $to->format('Y-m-d')]
    );
    $bookingId = (int) $pdo->lastInsertId();
    $pdo->commit();

    invalidateRoomsCache();   // Крок 6: зайнятість змінилась — кеш скидаємо

    $nights = (int) $from->diff($to)->days;
    respond(201, [
        'success' => true,
        'data'    => [
            'id'          => $bookingId,
            'room_id'     => $roomId,
            'guest_name'  => $guest,
            'date_from'   => $from->format('Y-m-d'),
            'date_to'     => $to->format('Y-m-d'),
            'nights'      => $nights,
            'total_price' => round($nights * (float) $room['price_per_night'], 2),
        ],
    ]);
}

// ---------- Маршрутизація ----------
try {
    if ($resource !== 'rooms') {
        fail(404, 'Невідомий ресурс');
    }
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        fail(405, 'Метод не підтримується для ресурсу rooms');
    }

    if ($method === 'GET' && isset($_GET['available'])) {
        // Крок 6: вільні кімнати за місткістю (з кешем або без)
        $capacity = filter_var($_GET['capacity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($capacity === false) {
            fail(400, 'Параметр «capacity» має бути додатним цілим числом');
        }
        $useCache = ($_GET['cache'] ?? '1') !== '0';
        $hit = false;
        [$rooms, $meta] = profile(function () use ($pdo, $capacity, $useCache, &$hit) {
            return array_map('formatRoom', findAvailable($pdo, $capacity, $useCache, $hit));
        });
        $meta['cache'] = $useCache ? 'on' : 'off';
        $meta['cache_hit'] = $hit;
        respondData($rooms, $meta, $debug);
    } elseif ($method === 'GET' && $id === null) {
        // Кроки 1–5: список; mode=slow — «до», mode=fast — «після»
        $mode = ($_GET['mode'] ?? 'fast') === 'slow' ? 'slow' : 'fast';
        [$rooms, $meta] = profile(fn () => $mode === 'slow' ? listRoomsSlow($pdo) : listRoomsFast($pdo));
        $meta['mode'] = $mode;
        respondData($rooms, $meta, $debug);
    } elseif ($method === 'GET') {
        $roomId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($roomId === false) {
            fail(400, 'Параметр «id» має бути додатним цілим числом');
        }
        getRoom($pdo, $roomId);
    } elseif ($action === null) {
        createRoom($pdo);
    } elseif ($action === 'book') {
        bookRoom($pdo);
    } else {
        fail(400, 'Невідома дія (action)');
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('API error: ' . $e->getMessage());
    fail(500, 'Внутрішня помилка сервера');
}
