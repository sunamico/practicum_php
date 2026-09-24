<?php
$dbname = 'practicum4';
$user = 'root'; 
$password = 'root'; 

$dsn = "mysql:unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    die("Помилка підключення до БД: " . $e->getMessage());
}
?>
