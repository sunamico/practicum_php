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

// Перевірка, що number є рядком (відсіює масиви та інші типи) і не порожній
if (!isset($data['number']) || !is_string($data['number']) || trim($data['number']) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Поле number має бути непорожнім рядком']);
    exit;
}

// Перевірка, що capacity та price_per_night є числами та більші за 0
if (!isset($data['capacity']) || !is_numeric($data['capacity']) || (int)$data['capacity'] <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Місткість (capacity) має бути цілим числом більше 0']);
    exit;
}

if (!isset($data['price_per_night']) || !is_numeric($data['price_per_night']) || (float)$data['price_per_night'] <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ціна (price_per_night) має бути числом більше 0']);
    exit;
}

$number = trim($data['number']);
$capacity = (int)$data['capacity'];
$price = (float)$data['price_per_night'];

try {
    $stmt = $pdo->prepare('INSERT INTO rooms (number, capacity, price_per_night) VALUES (:number, :capacity, :price)');
    $stmt->execute([
        ':number' => $number,
        ':capacity' => $capacity,
        ':price' => $price
    ]);

    $id = $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'id' => $id,
        'number' => $number,
        'capacity' => $capacity,
        'price_per_night' => $price,
        'is_booked' => 0
    ]);
} catch (Exception $e) {
    error_log('api_add error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Не вдалося додати запис через помилку сервера']);
}
