-- Практична №7, варіант 8: система бронювання (готель/зали)
-- Індекс на capacity свідомо НЕ створюється тут — див. add_index.sql (Крок 4)
CREATE DATABASE IF NOT EXISTS practicum7 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE practicum7;

CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    guest_name VARCHAR(100) NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);
-- Дані для навантаження створює seed.php (500 кімнат)
