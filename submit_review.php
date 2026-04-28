<?php
// Обработка отправки отзыва с главной страницы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$name   = trim($_POST['name'] ?? '');
$rating = (int)($_POST['rating'] ?? 0);
$text   = trim($_POST['text'] ?? '');

// honeypot: скрытое поле "company" в форме должно быть пустым
$hp = trim($_POST['company'] ?? '');
if ($hp !== '') {
    header('Location: index.php'); // бот — тихо уходим
    exit;
}

// быстрая валидация
if ($name === '' || $rating < 1 || $rating > 5 || $text === '') {
    header('Location: index.php?review_error=1#reviews');
    exit;
}

require_once __DIR__ . '/db.php';
// гарантируем наличие колонки approved (на случай, если миграция не выполнена)
try {
    $cols = $conn->query("SHOW COLUMNS FROM reviews")->fetchAll();
    $hasApproved = false;
    foreach ($cols as $c) {
        if (strcasecmp($c['Field'], 'approved') === 0) { $hasApproved = true; break; }
    }
    if (!$hasApproved) {
        $conn->exec("ALTER TABLE reviews ADD COLUMN approved TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (Throwable $e) {
    // если таблицы нет вообще — можно создать (опционально)
    // $conn->exec("CREATE TABLE IF NOT EXISTS reviews (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, rating TINYINT NOT NULL, text TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, approved TINYINT(1) NOT NULL DEFAULT 0) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

// сохраняем СТРОГО с approved=0 (на модерацию)
$stmt = $conn->prepare("INSERT INTO reviews (name, rating, text, approved) VALUES (:name, :rating, :text, 0)");
$stmt->execute([
    ':name'   => $name,
    ':rating' => $rating,
    ':text'   => $text,
]);

header('Location: index.php?review_ok=1#reviews');
exit;
