<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'lib/functions.php';
require_once 'classes/BookingSystem.php';
require_once 'classes/ConferenceRoom.php';

$system = new BookingSystem();

$system->addRoom(new Room("101", 2, 1500, true));
$system->addRoom(new Room("102", 3, 2000, false));
$system->addRoom(new ConferenceRoom("VIP-Зал", 50, 5000, ['Проєктор', 'Мікрофони', 'Фліпчарт'], false));
$system->addRoom(new ConferenceRoom("Meeting Room", 15, 3000, ['Плазма 65"', 'Дошка'], true));

$dateIn = '2026-10-01';
$dateOut = '2026-10-05';
$nights = nightsBetween($dateIn, $dateOut);
$dateRange = formatDateRange($dateIn, $dateOut);
$expectedRevenue = $system->calculateTotal($nights);
$availableRooms = $system->findAvailable();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>ООП: Система бронювання</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Система бронювання готелю</h1>
        
        <div class="highlight">
            <p><strong>📅 Дати періоду:</strong> <?= $dateRange ?> (Кількість ночей: <?= $nights ?>)</p>
            <p><strong>💰 Очікуваний дохід від зайнятих номерів:</strong> <?= $expectedRevenue ?> грн.</p>
        </div>

        <h2>Усі номери та зали:</h2>
        <ul class="room-list">
            <?php foreach ($system->getAllRooms() as $room): ?>
                <li><?= $room->getInfo() ?></li>
            <?php endforeach; ?>
        </ul>

        <h2>Вільні номери для бронювання:</h2>
        <ul class="room-list">
            <?php foreach ($availableRooms as $room): ?>
                <li><?= $room->getInfo() ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</body>
</html>