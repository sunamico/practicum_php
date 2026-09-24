
CREATE DATABASE IF NOT EXISTS practicum4;
USE practicum4;

CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL
);

INSERT INTO rooms (number, capacity, price_per_night) VALUES 
('101', 2, 1500.00),
('102', 3, 2000.00),
('VIP-Зал', 50, 5000.00),
('Meeting Room', 15, 3000.00);
