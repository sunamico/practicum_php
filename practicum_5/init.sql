CREATE DATABASE IF NOT EXISTS practicum5;
USE practicum5;

CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL,
    is_booked BOOLEAN DEFAULT FALSE
);

INSERT INTO rooms (number, capacity, price_per_night, is_booked) VALUES 
('101', 2, 1500.00, 0),
('102', 3, 2000.00, 1),
('VIP-Зал', 50, 5000.00, 0),
('Meeting Room', 15, 3000.00, 0);
