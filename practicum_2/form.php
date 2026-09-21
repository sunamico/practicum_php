<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$guestName = '';
$roomNumber = '';
$checkIn = '';
$checkOut = '';
$errors = [];
$successMessage = '';

// Step 3: Server-side processing and validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guestName = trim($_POST['guestName'] ?? '');
    $roomNumber = trim($_POST['roomNumber'] ?? '');
    $checkIn = trim($_POST['checkIn'] ?? '');
    $checkOut = trim($_POST['checkOut'] ?? '');

    // Validate guestName: only letters and spaces (Unicode support for Cyrillic)
    if (empty($guestName)) {
        $errors[] = 'Ім\'я гостя є обов\'язковим.';
    } elseif (!preg_match('/^[\p{L}\s]+$/u', $guestName)) {
        $errors[] = 'Ім\'я повинно містити лише літери та пробіли.';
    }

    // Validate roomNumber
    if (empty($roomNumber) || !is_numeric($roomNumber) || $roomNumber < 1) {
        $errors[] = 'Введіть коректний номер кімнати.';
    }

    // Validate dates: checkOut must be later than checkIn
    if (empty($checkIn) || empty($checkOut)) {
        $errors[] = 'Обидві дати (заїзд та виїзд) є обов\'язковими.';
    } else {
        $timeIn = strtotime($checkIn);
        $timeOut = strtotime($checkOut);
        
        if ($timeOut <= $timeIn) {
            $errors[] = 'Дата виїзду повинна бути пізнішою за дату заїзду.';
        }
    }

    // Step 4: Show result
    if (empty($errors)) {
        $successMessage = "Бронювання успішно підтверджено! Гість: " . htmlspecialchars($guestName) . ", Номер: " . htmlspecialchars($roomNumber);
        // Clear fields after success
        $guestName = $roomNumber = $checkIn = $checkOut = '';
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Практична робота 2 - Форма бронювання</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-container">
        <header class="top-header">
            <h1>Бронювання номеру</h1>
            <p class="subtitle">Система оформлення гостей</p>
        </header>

        <main class="main-content">
            <div class="card form-card">
                
                <!-- Display Server Errors -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Display Success Message -->
                <?php if ($successMessage): ?>
                    <div class="alert alert-success">
                        <?= $successMessage ?>
                    </div>
                <?php endif; ?>

                <!-- Step 2: HTML Form with HTML5 Validation -->
                <form id="bookingForm" method="post" action="form.php" novalidate>
                    <div class="form-group">
                        <label for="guestName">Ім'я та прізвище гостя</label>
                        <!-- HTML5 validation: required, pattern -->
                        <input type="text" id="guestName" name="guestName" 
                               value="<?= htmlspecialchars($guestName) ?>" 
                               pattern="^[\p{L}\s]+$" 
                               required 
                               placeholder="Наприклад: Іванов Іван">
                        <span class="js-error" id="nameError"></span>
                    </div>

                    <div class="form-group">
                        <label for="roomNumber">Номер кімнати</label>
                        <!-- HTML5 validation: required, min -->
                        <input type="number" id="roomNumber" name="roomNumber" 
                               value="<?= htmlspecialchars($roomNumber) ?>" 
                               min="1" 
                               required 
                               placeholder="Наприклад: 101">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="checkIn">Дата заїзду</label>
                            <input type="date" id="checkIn" name="checkIn" 
                                   value="<?= htmlspecialchars($checkIn) ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="checkOut">Дата виїзду</label>
                            <input type="date" id="checkOut" name="checkOut" 
                                   value="<?= htmlspecialchars($checkOut) ?>" 
                                   required>
                        </div>
                    </div>
                    <span class="js-error" id="dateError"></span>

                    <button type="submit" class="submit-btn">Забронювати</button>
                </form>
            </div>
        </main>
    </div>

    <!-- Client-side JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('bookingForm');
            const checkInInput = document.getElementById('checkIn');
            const checkOutInput = document.getElementById('checkOut');
            const guestNameInput = document.getElementById('guestName');
            
            const dateError = document.getElementById('dateError');
            const nameError = document.getElementById('nameError');

            // Step 6: localStorage implementation
            // Restore dates if they exist in local storage and inputs are currently empty
            if (!checkInInput.value && localStorage.getItem('savedCheckIn')) {
                checkInInput.value = localStorage.getItem('savedCheckIn');
            }
            if (!checkOutInput.value && localStorage.getItem('savedCheckOut')) {
                checkOutInput.value = localStorage.getItem('savedCheckOut');
            }

            // Save dates to localStorage immediately when user changes them
            checkInInput.addEventListener('change', () => {
                localStorage.setItem('savedCheckIn', checkInInput.value);
            });
            checkOutInput.addEventListener('change', () => {
                localStorage.setItem('savedCheckOut', checkOutInput.value);
            });

            // Step 5: Client-side JS validation
            form.addEventListener('submit', (event) => {
                let isValid = true;
                dateError.textContent = '';
                nameError.textContent = '';

                // Date validation logic
                if (checkInInput.value && checkOutInput.value) {
                    const dateIn = new Date(checkInInput.value);
                    const dateOut = new Date(checkOutInput.value);
                    
                    if (dateOut <= dateIn) {
                        dateError.textContent = 'Помилка: Дата виїзду повинна бути пізнішою за дату заїзду!';
                        isValid = false;
                    }
                }

                // Name format validation logic
                const nameRegex = /^[A-Za-zА-Яа-яІіЇїЄєҐґ\s]+$/;
                if (guestNameInput.value && !nameRegex.test(guestNameInput.value)) {
                    nameError.textContent = 'Помилка: Ім\'я може містити лише літери та пробіли!';
                    isValid = false;
                }

                // Prevent submission if JS validation fails
                if (!isValid) {
                    event.preventDefault();
                }
            });
        });
    </script>
</body>
</html>