<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не підтримується']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$id = is_array($data)
    ? filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;

if ($id === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Некоректний ідентифікатор кімнати']);
    exit;
}

try {
    // Атомарна операція: бронюємо лише вільний номер (захист від подвійного бронювання)
    $stmt = $pdo->prepare('UPDATE rooms SET is_booked = 1 WHERE id = :id AND is_booked = 0');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 1) {
        echo json_encode(['success' => true]);
        exit;
    }

    // Рядок не змінено: або номера немає, або він уже заброньований
    $check = $pdo->prepare('SELECT is_booked FROM rooms WHERE id = :id');
    $check->execute([':id' => $id]);

    if ($check->fetch() === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Номер не знайдено']);
    } else {
        http_response_code(409);
        echo json_encode(['error' => 'Цей номер уже заброньовано']);
    }
} catch (Exception $e) {
    error_log('api_book error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Не вдалося оновити статус бронювання']);
}
