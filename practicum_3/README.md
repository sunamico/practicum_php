# Практична робота №3

**Тема:** Основи об'єктно-орієнтованого програмування у PHP: класи, повторне використання коду.
**Варіант №8:** Система бронювання (готель/зали)  
**Студентка:** Руслана Савченко  
**Група:** ІО-44
___

**Мета роботи:** Навчитися створювати та використовувати класи й об'єкти в PHP; опрацювати властивості, методи та конструктор класу; освоїти найпростіше успадкування (`extends`, `parent::`); навчитися організовувати код у власні прості «бібліотеки» — окремі файли з класами й функціями, підключені через `include`/`require`.

## Предметна область (Варіант №8)

**Система бронювання (готель/зали):**
*   **Базовий клас:** `Room` із властивостями `number`, `capacity`, `pricePerNight` та методом `getInfo()`.
*   **Похідний клас:** `ConferenceRoom`, який успадковує `Room`, додає масив `equipmentList` та перевизначає метод `getInfo()`.
*   **Менеджер:** `BookingSystem` із методами `addRoom()`, `findAvailable()`, `calculateTotal()`.
*   **Бібліотека функцій:** функції `formatDateRange()`, `nightsBetween()`.

---

## Хід роботи

### Крок 1. Підготовка структури проєкту
Створено папку `practicum03`, у якій розміщено головний файл `index.php` та файл стилів `style.css`. Для організації логіки додано дві підпапки: `classes/` для об'єктно-орієнтованого коду та `lib/` для допоміжних процедурних функцій.

<img width="291" height="211" alt="image" src="https://github.com/user-attachments/assets/7fb5825a-8c62-424f-955a-ea78ae6ce47f" />

*Структура файлів проєкту.*

### Крок 2. Створення базового класу `Room`
У файлі `classes/Room.php` реалізовано базовий клас для сутності готельного номера. Властивості інкапсульовані за допомогою модифікатора `protected`. Метод `getInfo()` повертає відформатований рядок із базовою інформацією про номер та HTML-тегами для стилізації статусів.

```php
<?php
class Room {
    protected string $number;
    protected int $capacity;
    protected float $pricePerNight;
    protected bool $isBooked;

    public function __construct(string $number, int $capacity, float $pricePerNight, bool $isBooked = false) {
        $this->number = $number;
        $this->capacity = $capacity;
        $this->pricePerNight = $pricePerNight;
        $this->isBooked = $isBooked;
    }

    public function getInfo(): string {
        $statusClass = $this->isBooked ? 'status-booked' : 'status-available';
        $statusText = $this->isBooked ? 'Заброньовано' : 'Вільний';
        $statusHtml = "<span class='status-badge {$statusClass}'>{$statusText}</span>";
        $priceHtml = "<span class='price-tag'>{$this->pricePerNight} грн/ніч</span>";

        return "Номер <b>{$this->number}</b> (Місткість: {$this->capacity} ос., Ціна: {$priceHtml}) {$statusHtml}";
    }

    // Додаткові гетери: isAvailable(), getPrice()...
}
```

### Крок 3. Створення похідного класу `ConferenceRoom`
У файлі `classes/ConferenceRoom.php` створено клас конференц-залу, який розширює `Room`. Він викликає батьківський конструктор через `parent::__construct()` та перевизначає метод `getInfo()`, вирізаючи непотрібне слово «Номер» і додаючи перелік обладнання.

```php
<?php
require_once 'Room.php';

class ConferenceRoom extends Room {
    private array $equipmentList;

    public function __construct(string $number, int $capacity, float $pricePerNight, array $equipmentList, bool $isBooked = false) {
        parent::__construct($number, $capacity, $pricePerNight, $isBooked);
        $this->equipmentList = $equipmentList;
    }

    public function getInfo(): string {
        $baseInfo = str_replace('Номер ', '', parent::getInfo());
        $equipment = implode(', ', $this->equipmentList);
        return "<strong>Конференц-зал</strong> {$baseInfo}<em>{$equipment}</em>";
    }
}
```

### Крок 4. Створення класу-менеджера `BookingSystem`
У файлі `classes/BookingSystem.php` реалізовано колекцію, що акумулює об'єкти базового та похідного класів в єдиний масив `$rooms`. Метод `findAvailable()` здійснює фільтрацію масиву, а `calculateTotal()` виконує агрегацію (розрахунок очікуваного прибутку).

```php
<?php
require_once 'Room.php';

class BookingSystem {
    private array $rooms = [];

    public function addRoom(Room $room): void {
        $this->rooms[] = $room;
    }

    public function findAvailable(): array {
        return array_filter($this->rooms, fn($room) => $room->isAvailable());
    }

    public function calculateTotal(int $nights): float {
        $total = 0;
        foreach ($this->rooms as $room) {
            if (!$room->isAvailable()) $total += $room->getPrice() * $nights;
        }
        return $total;
    }
    
    public function getAllRooms(): array { 
        return $this->rooms; 
    }
}
```

### Крок 5. Створення бібліотеки функцій
У файлі `lib/functions.php` розміщено хелпери для роботи з датами, відокремлені від ООП-логіки, оскільки вони виконують загальні утилітарні задачі.

```php
<?php
function formatDateRange(string $checkIn, string $checkOut): string {
    return date('d.m.Y', strtotime($checkIn)) . " — " . date('d.m.Y', strtotime($checkOut));
}

function nightsBetween(string $checkIn, string $checkOut): int {
    $interval = (new DateTime($checkIn))->diff(new DateTime($checkOut));
    return $interval->days > 0 ? $interval->days : 1;
}
```

### Крок 6. Формування головного файлу `index.php`
У точці входу підключено всі створені файли за допомогою `require_once`. Ініціалізовано об'єкт менеджера `$system`, до якого додано два звичайні номери та два конференц-зали (з різними статусами бронювання). За допомогою методів об'єктів згенеровано HTML-розмітку.

### Крок 7. Перевірка результату в браузері
Після запуску локального сервера сторінка коректно відображає дані. Завдяки поліморфізму базові номери та конференц-зали обробляються в єдиному циклі, але виводять різну інформацію (у залів наявний бейдж «Конференц-зал» та блок обладнання). Додано візуальне розділення статусів за кольорами (зелений для вільних, червоний для заброньованих) та абсолютне вирівнювання статусів по правому краю карток.

<img width="1149" height="866" alt="image" src="https://github.com/user-attachments/assets/ae14eb8d-49d3-420b-beaf-5a9d2c4648cd" />

*Фінальний вигляд вебсторінки із застосованими стилями та даними з об'єктів.*

---

## Висновок
У результаті виконання практичної роботи було успішно закріплено навички роботи з об'єктно-орієнтованим програмуванням у PHP. Створено повноцінну структуру системи бронювання з використанням ключових принципів ООП: інкапсуляції, успадкування та поліморфізму. Код логічно розділено на окремі файли класів та функцій, що забезпечує його модульність, зручність підтримки та можливість повторного використання. Інтерфейс успішно адаптовано для коректного візуального відображення даних з об'єктів.
