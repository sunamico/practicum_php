<?php
// ВРАЗЛИВА версія (до аудиту): помилки підключення не перехоплюються і виводяться «як є»
$pdo = new PDO(
    'mysql:host=127.0.0.1;port=8889;dbname=practicum8;charset=utf8mb4',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);