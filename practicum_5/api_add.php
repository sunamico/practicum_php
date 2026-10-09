<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $stmt = $pdo->prepare('INSERT INTO rooms (number, capacity, price_per_night) VALUES (:number, :capacity, :price)');
        $stmt->execute([
            ':number' => $data['number'],
            ':capacity' => $data['capacity'],
            ':price' => $data['price_per_night']
        ]);
        
        $id = $pdo->lastInsertId();
        
        // Повертаємо код 201 (Created)
        http_response_code(201);
        echo json_encode([
            'id' => $id,
            'number' => $data['number'],
            'capacity' => $data['capacity'],
            'price_per_night' => $data['price_per_night'],
            'is_booked' => 0
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
