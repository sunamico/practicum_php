<?php
/**
 * Практична робота №6 — власний API (варіант 8: система бронювання).
 *
 * Крок 1. Контракт API
 * ---------------------------------------------------------------
 * GET  api.php?resource=rooms
 *      -> 200 {"success":true,"data":[{id,number,capacity,price_per_night},...]}
 * GET  api.php?resource=rooms&id=N
 *      -> 200 один запис | 404 якщо id немає | 400 якщо id некоректний
 * POST api.php?resource=rooms                      (створення)
 *      тіло JSON або form-data: number, capacity, price_per_night
 *      -> 201 створена кімната | 400 якщо поле відсутнє/некоректне
 * POST api.php?resource=rooms&action=book          (бронювання на дати)
 *      тіло: room_id, guest_name, date_from, date_to (YYYY-MM-DD)
 *      -> 201 бронювання | 400 | 404 немає кімнати | 409 дати зайняті
 * Інші методи для rooms -> 405 (+ заголовок Allow); невідомий resource -> 404
 * Помилка завжди: {"success":false,"error":"опис"}
 */

// Крок 2: єдина точка входу — JSON-заголовок одразу на початку
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$method   = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? null;
$id       = $_GET['id'] ?? null;
$action   = $_GET['action'] ?? null;

// ---------- Допоміжні функції ----------

// Єдиний формат відповіді
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

// Тіло запиту: JSON або $_POST
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
    return [
        'id'              => (int) $row['id'],
        'number'          => $row['number'],
        'capacity'        => (int) $row['capacity'],
        'price_per_night' => (float) $row['price_per_night'],
    ];
}

function findRoom(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, number, capacity, price_per_night FROM rooms WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ? formatRoom($row) : null;
}

// Крок 7: перевірка полів кімнати (400 з назвою поля)
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

// ---------- Обробники ----------

// Крок 4: GET — список
function listRooms(PDO $pdo): void
{
    $rows = $pdo->query('SELECT id, number, capacity, price_per_night FROM rooms ORDER BY id')->fetchAll();
    respond(200, ['success' => true, 'data' => array_map('formatRoom', $rows)]);
}

// Крок 4: GET — одна кімната або 404
function getRoom(PDO $pdo, int $id): void
{
    $room = findRoom($pdo, $id);
    if ($room === null) {
        fail(404, 'Кімнату з id=' . $id . ' не знайдено');
    }
    respond(200, ['success' => true, 'data' => $room]);
}

// Крок 5: POST — створення запису (prepared statement)
function createRoom(PDO $pdo): void
{
    [$number, $capacity, $price] = validateRoom(readInput());

    $stmt = $pdo->prepare('INSERT INTO rooms (number, capacity, price_per_night) VALUES (:number, :capacity, :price)');
    $stmt->execute(['number' => $number, 'capacity' => $capacity, 'price' => $price]);

    respond(201, ['success' => true, 'data' => findRoom($pdo, (int) $pdo->lastInsertId())]);
}

// Крок 6: доменна дія action=book — бронювання на дати
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

    // Транзакція + блокування рядка кімнати: без подвійних бронювань
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, price_per_night FROM rooms WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $roomId]);
    $room = $stmt->fetch();
    if ($room === false) {
        $pdo->rollBack();
        fail(404, 'Кімнату з id=' . $roomId . ' не знайдено');
    }

    // Перетин періодів: існуюче.from < нове.to ТА існуюче.to > нове.from
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM bookings WHERE room_id = :room AND date_from < :new_to AND date_to > :new_from'
    );
    $stmt->execute(['room' => $roomId, 'new_to' => $to->format('Y-m-d'), 'new_from' => $from->format('Y-m-d')]);
    if ((int) $stmt->fetchColumn() > 0) {
        $pdo->rollBack();
        fail(409, 'Кімната вже заброньована на ці дати');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO bookings (room_id, guest_name, date_from, date_to) VALUES (:room, :guest, :from_d, :to_d)'
    );
    $stmt->execute([
        'room'   => $roomId,
        'guest'  => $guest,
        'from_d' => $from->format('Y-m-d'),
        'to_d'   => $to->format('Y-m-d'),
    ]);
    $bookingId = (int) $pdo->lastInsertId();
    $pdo->commit();

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

// ---------- Крок 3: маршрутизація ----------
try {
    if ($resource !== 'rooms') {
        fail(404, 'Невідомий ресурс');
    }

    // Крок 7: непідтримуваний метод -> 405
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        fail(405, 'Метод не підтримується для ресурсу rooms');
    }

    if ($method === 'GET' && $id === null) {
        listRooms($pdo);
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
