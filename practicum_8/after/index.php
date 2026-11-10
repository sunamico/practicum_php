<?php
// БЕЗПЕЧНА версія застосунку (після аудиту) — практична №8
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

startSecureSession();

$errors   = [];
$rooms    = [];
$allRooms = [];
$bookings = [];
$q        = (isset($_GET['q']) && is_string($_GET['q'])) ? trim($_GET['q']) : '';

// ---------- Крок 4: серверна валідація ----------

function parseDate(mixed $value): ?DateTimeImmutable
{
    if (!is_string($value)) {
        return null;
    }
    $date   = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }
    return $date;
}

function validateRoom(array $in): array
{
    $errors = [];
    $number = is_scalar($in['number'] ?? null) ? trim((string) $in['number']) : '';
    if ($number === '' || mb_strlen($number) > 50) {
        $errors[] = 'Номер кімнати обов’язковий (до 50 символів).';
    }
    $capacity = filter_var($in['capacity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
    if ($capacity === false) {
        $errors[] = 'Місткість має бути цілим числом від 1 до 1000.';
    }
    $price = filter_var($in['price_per_night'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($price === false || $price <= 0 || $price > 1000000) {
        $errors[] = 'Ціна має бути числом більше 0.';
    }
    return [$errors, ['number' => $number, 'capacity' => $capacity, 'price' => $price]];
}

function validateBooking(PDO $pdo, array $in): array
{
    $errors = [];
    $roomId = filter_var($in['room_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($roomId === false) {
        $errors[] = 'Оберіть кімнату зі списку.';
    }
    $guest = is_scalar($in['guest_name'] ?? null) ? trim((string) $in['guest_name']) : '';
    if ($guest === '' || mb_strlen($guest) > 100) {
        $errors[] = 'Ім’я гостя обов’язкове (до 100 символів).';
    }
    $from = parseDate($in['date_from'] ?? null);
    $to   = parseDate($in['date_to'] ?? null);
    if ($from === null || $to === null) {
        $errors[] = 'Вкажіть коректні дати заїзду та виїзду.';
    } elseif ($to <= $from) {
        $errors[] = 'Дата виїзду має бути пізніше за дату заїзду.';
    } elseif ($from < new DateTimeImmutable('today')) {
        $errors[] = 'Дата заїзду не може бути в минулому.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM rooms WHERE id = :id');
        $stmt->execute(['id' => $roomId]);
        if ((int) $stmt->fetchColumn() === 0) {
            $errors[] = 'Кімнату не знайдено.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM bookings WHERE room_id = :room AND date_from < :new_to AND date_to > :new_from'
            );
            $stmt->execute(['room' => $roomId, 'new_to' => $to->format('Y-m-d'), 'new_from' => $from->format('Y-m-d')]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = 'Кімната вже заброньована на ці дати.';
            }
        }
    }

    return [$errors, [
        'room'   => $roomId,
        'guest'  => $guest,
        'from_d' => $from ? $from->format('Y-m-d') : null,
        'to_d'   => $to ? $to->format('Y-m-d') : null,
    ]];
}

// ---------- Обробка запитів ----------
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireCsrf();   // Крок 3: без токена — 403 до будь-якої дії
        $action = $_POST['action'] ?? '';

        if ($action === 'add_room') {
            [$errors, $clean] = validateRoom($_POST);
            if (!$errors) {
                $pdo->prepare('INSERT INTO rooms (number, capacity, price_per_night) VALUES (:number, :capacity, :price)')
                    ->execute($clean);
                redirectWithFlash('Кімнату додано.');
            }
        } elseif ($action === 'book') {
            [$errors, $clean] = validateBooking($pdo, $_POST);
            if (!$errors) {
                $pdo->prepare('INSERT INTO bookings (room_id, guest_name, date_from, date_to) VALUES (:room, :guest, :from_d, :to_d)')
                    ->execute($clean);
                redirectWithFlash('Кімнату заброньовано.');
            }
        } elseif ($action === 'delete_booking') {
            // Крок 5: видалення лише через POST + CSRF-токен
            $bookingId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($bookingId === false) {
                $errors[] = 'Некоректний id бронювання.';
            } else {
                $pdo->prepare('DELETE FROM bookings WHERE id = :id')->execute(['id' => $bookingId]);
                redirectWithFlash('Бронювання видалено.');
            }
        } else {
            $errors[] = 'Невідома дія.';
        }
    }

    // Крок 1: пошук — підготовлений запит замість конкатенації
    if ($q !== '') {
        $stmt = $pdo->prepare('SELECT id, number, capacity, price_per_night FROM rooms WHERE number = :q ORDER BY id');
        $stmt->execute(['q' => $q]);
    } else {
        $stmt = $pdo->query('SELECT id, number, capacity, price_per_night FROM rooms ORDER BY id');
    }
    $rooms    = $stmt->fetchAll();
    $allRooms = $pdo->query('SELECT id, number, capacity FROM rooms ORDER BY id')->fetchAll();
    $bookings = $pdo->query(
        'SELECT b.id, r.number, b.guest_name, b.date_from, b.date_to
         FROM bookings b JOIN rooms r ON r.id = b.room_id ORDER BY b.id DESC'
    )->fetchAll();
} catch (PDOException $e) {
    // Крок 6: деталі — в лог, користувачу — загальне повідомлення
    error_log('practicum8: ' . $e->getMessage());
    $errors[] = 'Сталася помилка під час роботи з базою даних. Спробуйте пізніше.';
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Бронювання номерів — після виправлень</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="container">
    <h1>Бронювання номерів</h1>
    <p class="lead">Практична робота №8 · <span class="badge ok">захищена версія</span></p>

    <?php if ($flash): ?>
        <div class="banner ok"><?= e($flash) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="banner err">
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <section class="panel">
        <h2>Пошук кімнати за номером</h2>
        <form method="get" class="search-form">
            <div class="field">
                <label for="q">Номер кімнати</label>
                <input id="q" type="text" name="q" value="<?= e($q) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Шукати</button>
        </form>
        <?php if ($q !== ''): ?>
            <p class="results-title">Результати для: <strong><?= e($q) ?></strong></p>
        <?php endif; ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Номер</th><th>Місткість</th><th>Ціна за ніч</th></tr></thead>
                <tbody>
                <?php foreach ($rooms as $r): ?>
                    <tr>
                        <td><?= e($r['id']) ?></td>
                        <td><?= e($r['number']) ?></td>
                        <td><?= e($r['capacity']) ?></td>
                        <td><?= e($r['price_per_night']) ?> грн</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rooms): ?>
                    <tr><td colspan="4" class="muted">Кімнат не знайдено.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Нова кімната</h2>
        <form method="post" class="form-grid">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_room">
            <div><label for="number">Номер</label><input id="number" type="text" name="number" maxlength="50" required></div>
            <div><label for="capacity">Місткість</label><input id="capacity" type="number" name="capacity" min="1" required></div>
            <div><label for="price">Ціна, грн</label><input id="price" type="number" name="price_per_night" min="1" step="0.01" required></div>
            <button type="submit" class="btn btn-primary">Додати</button>
        </form>
    </section>

    <section class="panel">
        <h2>Забронювати кімнату</h2>
        <form method="post" class="form-grid">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="book">
            <div>
                <label for="room_id">Кімната</label>
                <select id="room_id" name="room_id">
                    <?php foreach ($allRooms as $r): ?>
                        <option value="<?= e($r['id']) ?>"><?= e($r['number']) ?> (<?= e($r['capacity']) ?> ос.)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label for="guest">Гість</label><input id="guest" type="text" name="guest_name" maxlength="100" required></div>
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
                        <td><?= e($b['id']) ?></td>
                        <td><?= e($b['number']) ?></td>
                        <td><?= e($b['guest_name']) ?></td>
                        <td><?= e($b['date_from']) ?></td>
                        <td><?= e($b['date_to']) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Видалити бронювання?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_booking">
                                <input type="hidden" name="id" value="<?= e($b['id']) ?>">
                                <button type="submit" class="btn btn-danger">Видалити</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$bookings): ?>
                    <tr><td colspan="6" class="muted">Бронювань поки немає.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
