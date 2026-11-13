<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не підтримується']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data) || !isset($data['id']) || !is_numeric($data['id']) || (int)$data['id'] <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Некоректний ідентифікатор кімнати']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE rooms SET is_booked = 1 WHERE id = :id');
    $stmt->execute([':id' => (int)$data['id']]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('api_book error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Не вдалося оновити статус бронювання']);
}
