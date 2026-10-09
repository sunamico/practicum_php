<?php
require_once 'db.php';
header('Content-Type: application/json');

try {
    $min_capacity = isset($_GET['capacity']) ? (int)$_GET['capacity'] : 0;
    
    if ($min_capacity > 0) {
        $stmt = $pdo->prepare('SELECT * FROM rooms WHERE capacity >= :capacity ORDER BY id DESC');
        $stmt->execute([':capacity' => $min_capacity]);
        $rooms = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query('SELECT * FROM rooms ORDER BY id DESC');
        $rooms = $stmt->fetchAll();
    }
    
    echo json_encode(['rooms' => $rooms]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
