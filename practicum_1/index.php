<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$rooms = [
    ['number' => '101', 'capacity' => 2, 'pricePerNight' => 1500, 'isBooked' => true],
    ['number' => '102', 'capacity' => 4, 'pricePerNight' => 2500, 'isBooked' => false],
    ['number' => '201', 'capacity' => 2, 'pricePerNight' => 1200, 'isBooked' => true],
    ['number' => '202', 'capacity' => 1, 'pricePerNight' => 800,  'isBooked' => false],
    ['number' => '301', 'capacity' => 6, 'pricePerNight' => 4500, 'isBooked' => true],
];

function formatRoom(array $room): string {
    return "{$room['capacity']} місць • {$room['pricePerNight']} грн / ніч";
}

$totalIncome = 0;
foreach ($rooms as $room) {
    if ($room['isBooked']) {
        $totalIncome += $room['pricePerNight'];
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Практична робота 1 - Бронювання номерів</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-container">
        <header class="top-header">
            <div>
                <h1>Система бронювання</h1>
                <p class="subtitle">Панель управління номерним фондом</p>
            </div>
        </header>

        <div class="main-content">
            <section class="rooms-section">
                <div class="section-header">
                    <h2>Список номерів</h2>
                </div>
                <div class="rooms-grid">
                    <?php foreach ($rooms as $room): ?>
                        <?php
                            $statusText = $room['isBooked'] ? 'Заброньовано' : 'Вільний';
                            $statusClass = $room['isBooked'] ? 'booked' : 'free';
                        ?>
                        <div class="card">
                            <div class="card-header">
                                <h3>Номер <?= $room['number'] ?></h3>
                                <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                            </div>
                            <div class="card-body">
                                <p class="room-details"><?= formatRoom($room) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <aside class="sidebar">
                <div class="stats-card">
                    <h3>Фінансова статистика</h3>
                    <p class="stats-label">Очікуваний дохід від заброньованих номерів</p>
                    <div class="stats-value"><?= number_format($totalIncome, 0, '', ' ') ?> грн</div>
                </div>
            </aside>
        </div>
    </div>
</body>
</html>
