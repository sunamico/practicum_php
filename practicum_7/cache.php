<?php
// Крок 6: файловий кеш із TTL для «важких» запитів

const CACHE_PREFIX = 'rooms_cache_';

// Виконує SQL або повертає результат із кешу (TTL у секундах)
function cachedQuery(PDO $pdo, string $key, string $sql, array $params = [], int $ttlSeconds = 60, ?bool &$hit = null): array
{
    $file = sys_get_temp_dir() . '/' . CACHE_PREFIX . md5($key) . '.json';
    $hit = false;

    // Є свіжий файл — віддаємо без звернення до БД
    if (is_file($file) && (time() - filemtime($file)) < $ttlSeconds) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached)) {
            $hit = true;
            return $cached;
        }
    }

    $result = runQuery($pdo, $sql, $params)->fetchAll();

    // Запис через тимчасовий файл + rename (атомарно при паралельних запитах)
    $tmp = $file . '.' . getmypid() . '.tmp';
    file_put_contents($tmp, json_encode($result));
    rename($tmp, $file);

    return $result;
}

// Явна інвалідація: видаляємо весь кеш кімнат після зміни даних
function invalidateRoomsCache(): void
{
    foreach (glob(sys_get_temp_dir() . '/' . CACHE_PREFIX . '*.json') ?: [] as $file) {
        @unlink($file);
    }
}

// Вільні кімнати із заданою місткістю (немає бронювання на сьогодні)
function findAvailable(PDO $pdo, int $capacity, bool $useCache = true, ?bool &$hit = null): array
{
    $sql = 'SELECT r.id, r.number, r.capacity, r.price_per_night
            FROM rooms r
            WHERE r.capacity = :capacity
              AND NOT EXISTS (
                  SELECT 1 FROM bookings b
                  WHERE b.room_id = r.id AND b.date_from <= CURDATE() AND b.date_to > CURDATE()
              )
            ORDER BY r.price_per_night, r.id';

    if (!$useCache) {
        $hit = false;
        return runQuery($pdo, $sql, ['capacity' => $capacity])->fetchAll();
    }
    return cachedQuery($pdo, 'available:' . $capacity, $sql, ['capacity' => $capacity], 60, $hit);
}
