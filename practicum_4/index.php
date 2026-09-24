<?php
require_once 'db.php';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM rooms WHERE id = :id');
    $stmt->execute([':id' => $id]);
    header('Location: index.php');
    exit;
}

$capacityFilter = $_GET['capacity'] ?? null;

if ($capacityFilter) {
    $stmt = $pdo->prepare('SELECT * FROM rooms WHERE capacity >= :capacity');
    $stmt->execute([':capacity' => $capacityFilter]);
    $rooms = $stmt->fetchAll();
} else {
    $stmt = $pdo->query('SELECT * FROM rooms');
    $rooms = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Система бронювання (готель/зали)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Керування номерами</h1>
        
        <form method="GET" action="index.php" class="filter-form">
            <label for="capacity">Пошук номерів від місткості:</label>
            <input type="number" name="capacity" id="capacity" min="1" value="<?= htmlspecialchars((string)$capacityFilter) ?>">
            <button type="submit" class="btn btn-primary">Знайти</button>
            <a href="index.php" class="btn btn-secondary">Скинути</a>
        </form>

        <a href="add.php" class="btn btn-success mb-3">+ Додати новий номер</a>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Номер (Назва)</th>
                    <th>Місткість (ос.)</th>
                    <th>Ціна (грн/ніч)</th>
                    <th>Дії</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rooms) > 0): ?>
                    <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td><?= $room['id'] ?></td>
                            <td><?= htmlspecialchars($room['number']) ?></td>
                            <td><?= $room['capacity'] ?></td>
                            <td><?= $room['price_per_night'] ?></td>
                            <td class="actions">
                                <a href="edit.php?id=<?= $room['id'] ?>" class="btn btn-sm btn-warning">Редагувати</a>
                                <a href="index.php?delete=<?= $room['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Ви впевнені, що хочете видалити цей номер?');">Видалити</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">Номери не знайдено.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
