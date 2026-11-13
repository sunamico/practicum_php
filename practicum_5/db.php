<?php
$host = '127.0.0.1';
$port = '8889';
$dbname = 'practicum5';
$user = 'root';
$password = 'root';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    // Деталі — лише в лог сервера, клієнту — загальне повідомлення
    error_log('DB connection error: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Не вдалося підключитися до бази даних']);
    exit;
}
