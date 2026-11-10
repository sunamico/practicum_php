-- Практична №6, варіант 8: система бронювання (готель/зали)
CREATE DATABASE IF NOT EXISTS practicum6 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE practicum6;

CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL
);

-- Бронювання на дати (date_to — день виїзду, не включається)
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    guest_name VARCHAR(100) NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Початкові дані (виконати один раз)
INSERT INTO rooms (number, capacity, price_per_night) VALUES
    ('101', 2, 1200.00),
    ('102', 2, 1300.00),
    ('201', 3, 1800.00),
    ('202', 4, 2400.00),
    ('Зала «Лотос»', 20, 9000.00);
