<?php
// Наповнення БД для навантаження: 500 кімнат + бронювання на сьогодні
// Запуск: php seed.php  (або відкрити у браузері)
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

const TARGET_ROOMS = 500;

$count = (int) $pdo->query('SELECT COUNT(*) FROM rooms')->fetchColumn();
if ($count >= TARGET_ROOMS) {
    echo "Уже є $count кімнат — seed не потрібен\n";
    exit;
}

$pdo->beginTransaction();

// Кімнати: місткість 1..20 (по 25 кімнат кожної місткості)
$stmt = $pdo->prepare('INSERT INTO rooms (number, capacity, price_per_night) VALUES (:number, :capacity, :price)');
for ($i = $count + 1; $i <= TARGET_ROOMS; $i++) {
    $capacity = 1 + ($i % 20);
    $stmt->execute([
        'number'   => 'R-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        'capacity' => $capacity,
        'price'    => 800 + $capacity * 150 + ($i % 7) * 50,
    ]);
}

// Кожна третя кімната зайнята сьогодні
$hasBookings = (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn() > 0;
if (!$hasBookings) {
    $ids  = $pdo->query('SELECT id FROM rooms ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $book = $pdo->prepare(
        'INSERT INTO bookings (room_id, guest_name, date_from, date_to)
         VALUES (:room, :guest, CURDATE() - INTERVAL 1 DAY, CURDATE() + INTERVAL 3 DAY)'
    );
    foreach ($ids as $k => $roomId) {
        if ($k % 3 === 0) {
            $book->execute(['room' => $roomId, 'guest' => 'Тестовий гість']);
        }
    }
}

$pdo->commit();
echo 'Готово: кімнат ' . TARGET_ROOMS . ', бронювань створено для кожної третьої кімнати' . "\n";
