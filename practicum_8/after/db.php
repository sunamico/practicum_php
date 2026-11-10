<?php
// БЕЗПЕЧНА версія: помилки PHP не показуються користувачу, а пишуться в лог (Крок 6)
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Kiev');

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=8889;dbname=practicum8;charset=utf8mb4',
        'root',
        'root',   
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connect error: ' . $e->getMessage());
    http_response_code(500);
    exit('Сервіс тимчасово недоступний. Спробуйте пізніше.');
}
