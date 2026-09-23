# Практична робота №4

**Тема:** Взаємодія вебзастосунку з базою даних (PDO/MySQL) 
**Домен:** Система бронювання (готель/зали) 
**Студентка:** Савченко Руслана 
**Група:** ІО-44 

## Мета роботи

Навчитися підключати PHP-застосунок до бази даних MySQL/MariaDB через розширення PDO; створювати таблицю бази даних для свого домену; виконувати базові CRUD-операції (SELECT, INSERT, UPDATE, DELETE) засобами PDO; використовувати підготовлені запити (prepared statements) для захисту від SQL-ін'єкцій замість підстановки значень безпосередньо в текст SQL-запиту.

---
## Варіант №8: Система бронювання(готель/зали)
- Таблиця: `rooms` (id, number, capacity, price_per_night).
- Вибірка: findAvailable($capacity) - SELECT * FROM rooms WHERE capacity >= :capacity; findByNumber($number) - SELECT * FROM rooms WHERE number = :number.
- Зміна даних: addRoom() - INSERT; updateRoom($id, ...) - UPDATE ... SET price_per_night = :price WHERE id = :id; deleteRoom($id) - DELETE FROM rooms WHERE id = :id.
___

## Хід роботи

### 1. Створення бази даних та таблиці

За допомогою SQL-скрипта створено базу даних `practicum4` та таблицю `rooms` із полями `id`, `number`, `capacity` та `price_per_night`. Таблицю наповнено початковими тестовими даними.
<img width="1019" height="371" alt="image" src="https://github.com/user-attachments/assets/6300f875-6cf8-4162-bdf2-d0bef58a7e8c" />

<img width="711" height="72" alt="image" src="https://github.com/user-attachments/assets/3beea880-35cd-4388-b8a8-7c10b1fb5fa9" />

<img width="693" height="104" alt="image" src="https://github.com/user-attachments/assets/8375afa4-151e-485c-81ef-ba5a9db4e18d" />

### 2. Підключення до бази даних (db.php)

Налаштовано з'єднання з MySQL через об'єкт PDO. Вирішено проблему специфіки роботи MAMP та вбудованого сервера PHP за допомогою використання шляху до `unix_socket`. Налаштовано обробку винятків `try...catch`.

```
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
```

### 3. Відображення списку номерів (Read)

У файлі `index.php` реалізовано вибірку всіх записів з таблиці за допомогою підготовленого запиту `SELECT`. Дані виведено у вигляді HTML-таблиці.

<img width="1380" height="497" alt="image" src="https://github.com/user-attachments/assets/c1fa9f2a-59e1-498e-babb-ba2a7a1c823f" />


### 4. Додавання нового номера (Create)

Створено сторінку `add.php` з формою. Дані обробляються POST-запитом та зберігаються у БД безпечним методом (через плейсхолдери `INSERT INTO rooms ...`).

<img width="451" height="330" alt="image" src="https://github.com/user-attachments/assets/269e0dfa-c520-464d-8f7d-f832946abeea" />


### 5. Редагування номера (Update)

Реалізовано скрипт `edit.php`. При переході на сторінку система отримує `id` запису, виконує `SELECT`, підставляє поточні дані у форму, а після натискання кнопки оновлює їх через `UPDATE`.

<img width="448" height="321" alt="image" src="https://github.com/user-attachments/assets/d23bfd2a-88aa-46b0-a1f8-10a24d8afb74" />


### 6. Видалення та фільтрація (Delete & Search)

На головній сторінці реалізовано обробку GET-параметра для видалення записів (`DELETE`) з перевіркою підтвердження. Також додано форму для пошуку номерів за мінімальною місткістю.

<img width="1360" height="391" alt="image" src="https://github.com/user-attachments/assets/0b25d187-e3a9-4c40-bd2e-c724c013e0b5" />


---

## Висновки

У ході виконання роботи було успішно налаштовано взаємодію локального PHP-сервера та СУБД MySQL. Вивчено принципи роботи з PDO, налаштовано безпечні SQL-запити та розроблено повноцінний веб-інтерфейс для керування довідником номерів системи бронювання. Додатково було адаптовано CSS-стилі.
