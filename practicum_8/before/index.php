<?php
// ВРАЗЛИВА версія застосунку (стан до аудиту) — лише для відтворення атак у практикумі №8
require_once __DIR__ . '/db.php';

$message = '';
$isError = false;
$rooms = $allRooms = $bookings = [];
$q = $_GET['q'] ?? '';

// ❌ Крок 5: видалення за GET-посиланням, без підтвердження і токена (+ SQL-ін’єкція в id)
if (isset($_GET['delete_booking'])) {
    $pdo->exec('DELETE FROM bookings WHERE id = ' . $_GET['delete_booking']);
    header('Location: index.php');
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($_POST['action'] === 'add_room') {
            // ❌ Крок 1: конкатенація; ❌ Крок 4: серверної перевірки полів немає
            $pdo->exec(
                "INSERT INTO rooms (number, capacity, price_per_night) VALUES ('"
                . $_POST['number'] . "', " . $_POST['capacity'] . ', ' . $_POST['price_per_night'] . ')'
            );
            $message = 'Кімнату додано';
        } elseif ($_POST['action'] === 'book') {
            // ❌ Крок 3: форма без CSRF-токена; ❌ Крок 1: конкатенація
            $pdo->exec(
                'INSERT INTO bookings (room_id, guest_name, date_from, date_to) VALUES ('
                . $_POST['room_id'] . ", '" . $_POST['guest_name'] . "', '" . $_POST['date_from'] . "', '" . $_POST['date_to'] . "')"
            );
            $message = 'Кімнату заброньовано';
        }
    }

    if ($q !== '') {
        // ❌ Крок 1: SQL-ін’єкція у пошуку за номером
        $rooms = $pdo->query("SELECT * FROM rooms WHERE number = '$q'")->fetchAll();
    } else {
        $rooms = $pdo->query('SELECT * FROM rooms ORDER BY id')->fetchAll();
    }
    $allRooms = $pdo->query('SELECT * FROM rooms ORDER BY id')->fetchAll();
    $bookings = $pdo->query(
        'SELECT b.id, r.number, b.guest_name, b.date_from, b.date_to
         FROM bookings b JOIN rooms r ON r.id = b.room_id ORDER BY b.id DESC'
    )->fetchAll();
} catch (PDOException $e) {
    // ❌ Крок 6: користувач бачить сирий текст винятку (структура БД)
    $message = $e->getMessage();
    $isError = true;
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Бронювання номерів — до виправлень</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="container">
    <h1>Бронювання номерів</h1>
    <p class="lead">Практична робота №8 · <span class="badge err">вразлива версія</span></p>

    <?php if ($message !== ''): ?>
        <div class="banner <?= $isError ? 'err' : 'ok' ?>"><?= $message ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2>Пошук кімнати за номером</h2>
        <form method="get" class="search-form">
            <div class="field">
                <label for="q">Номер кімнати</label>
                <input id="q" type="text" name="q" value="<?= $q ?>">
            </div>
            <button type="submit" class="btn btn-primary">Шукати</button>
        </form>
        <?php if ($q !== ''): ?>
            <p class="results-title">Результати для: <strong><?= $q ?></strong></p>
        <?php endif; ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Номер</th><th>Місткість</th><th>Ціна за ніч</th></tr></thead>
                <tbody>
                <?php foreach ($rooms as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= $r['number'] ?></td>
                        <td><?= $r['capacity'] ?></td>
                        <td><?= $r['price_per_night'] ?> грн</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Нова кімната</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="add_room">
            <div><label for="number">Номер</label><input id="number" type="text" name="number" required></div>
            <div><label for="capacity">Місткість</label><input id="capacity" type="number" name="capacity" required></div>
            <div><label for="price">Ціна, грн</label><input id="price" type="number" name="price_per_night" required></div>
            <button type="submit" class="btn btn-primary">Додати</button>
        </form>
    </section>

    <section class="panel">
        <h2>Забронювати кімнату</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="book">
            <div>
                <label for="room_id">Кімната</label>
                <select id="room_id" name="room_id">
                    <?php foreach ($allRooms as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= $r['number'] ?> (<?= $r['capacity'] ?> ос.)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label for="guest">Гість</label><input id="guest" type="text" name="guest_name" required></div>
            <div><label for="from">Заїзд</label><input id="from" type="date" name="date_from" required></div>
            <div><label for="to">Виїзд</label><input id="to" type="date" name="date_to" required></div>
            <button type="submit" class="btn btn-primary">Забронювати</button>
        </form>
    </section>

    <section class="panel">
        <h2>Бронювання</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Кімната</th><th>Гість</th><th>Заїзд</th><th>Виїзд</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><?= $b['id'] ?></td>
                        <td><?= $b['number'] ?></td>
                        <td><?= $b['guest_name'] ?></td>
                        <td><?= $b['date_from'] ?></td>
                        <td><?= $b['date_to'] ?></td>
                        <td><a class="btn btn-danger" href="index.php?delete_booking=<?= $b['id'] ?>">Видалити</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
