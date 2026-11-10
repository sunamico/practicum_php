<?php
// Допоміжні функції безпеки: сесія, CSRF-токен, екранування

// Крок 3: сесія і токен, унікальний для сесії
function startSecureSession(): void
{
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Крок 2: екранування HTML-спецсимволів (захист від XSS)
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Приховане поле з токеном для кожної форми, що змінює дані
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

// Перевірка токена при обробці POST: без токена або зі старим — 403
function requireCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Недійсний CSRF-токен');
    }
}

// Паттерн «POST → redirect → GET» з одноразовим повідомленням
function redirectWithFlash(string $text): void
{
    $_SESSION['flash'] = $text;
    header('Location: index.php');
    exit;
}
