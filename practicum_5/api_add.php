<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не підтримується']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Некоректний формат JSON']);
    exit;
}

// number: непорожній рядок, не довший за 50 символів (VARCHAR(50))
$number = isset($data['number']) && is_string($data['number']) ? trim($data['number']) : '';
if ($number === '' || mb_strlen($number) > 50) {
    http_response_code(400);
    echo json_encode(['error' => 'Назва/номер має бути непорожнім рядком до 50 символів']);
    exit;
}

// capacity: суворо ціле число від 1 до 1000 (рядок "2.7" буде відхилено)
$capacity = filter_var($data['capacity'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 1000]
]);
if ($capacity === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Місткість (capacity) має бути цілим числом від 1 до 1000']);
    exit;
}

// price_per_night: додатне число, до 2 знаків після коми (DECIMAL(10,2))
$priceRaw = isset($data['price_per_night']) ? (string)$data['price_per_night'] : '';
if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $priceRaw) || (float)$priceRaw <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ціна (price_per_night) має бути числом більше 0 (до 2 знаків після коми)']);
    exit;
}
$price = number_format((float)$priceRaw, 2, '.', '');

try {
    $stmt = $pdo->prepare('INSERT INTO rooms (number, capacity, price_per_night) VALUES (:number, :capacity, :price)');
    $stmt->execute([
        ':number'   => $number,
        ':capacity' => $capacity,
        ':price'    => $price
    ]);

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
