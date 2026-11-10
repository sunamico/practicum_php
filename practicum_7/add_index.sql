-- Крок 4: індекс на стовпець фільтрації (capacity)
USE practicum7;

-- 1) ДО індексу
EXPLAIN SELECT * FROM rooms WHERE capacity = 8;      -- очікується type=ALL, rows≈500

-- 2) Створення індексу
CREATE INDEX idx_rooms_capacity ON rooms (capacity);

-- 3) ПІСЛЯ індексу
EXPLAIN SELECT * FROM rooms WHERE capacity = 8;      -- очікується type=ref, rows≈25


