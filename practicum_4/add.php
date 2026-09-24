<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $number = $_POST['number'];
    $capacity = (int)$_POST['capacity'];
    $price_per_night = (float)$_POST['price_per_night'];

    $stmt = $pdo->prepare('INSERT INTO rooms (number, capacity, price_per_night) VALUES (:number, :capacity, :price_per_night)');
    $stmt->execute([
        ':number' => $number,
        ':capacity' => $capacity,
        ':price_per_night' => $price_per_night
    ]);

    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Додати номер</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container form-container">
        <h2>Додати новий номер</h2>
        <form method="POST">
            <div class="form-group">
                <label for="number">Номер (або назва залу):</label>
                <input type="text" id="number" name="number" required>
            </div>
            <div class="form-group">
                <label for="capacity">Місткість (осіб):</label>
                <input type="number" id="capacity" name="capacity" min="1" required>
            </div>
            <div class="form-group">
                <label for="price_per_night">Ціна (грн/ніч):</label>
                <input type="number" id="price_per_night" name="price_per_night" step="0.01" min="0" required>
            </div>
            <button type="submit" class="btn btn-success">Додати</button>
            <a href="index.php" class="btn btn-secondary">Скасувати</a>
        </form>
    </div>
</body>
</html>
