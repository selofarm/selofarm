<?php
/**
 * Миграция базы данных.
 * Запустите однократно: php migrate.php  или откройте в браузере.
 */
require_once __DIR__ . '/db.php';

$results = [];

/* ── 1. Добавить price_unit в products ─────────────────────────── */
try {
    $cols = $conn->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_ASSOC);
    $has  = false;
    foreach ($cols as $c) {
        if (strcasecmp($c['Field'], 'price_unit') === 0) { $has = true; break; }
    }
    if ($has) {
        $results[] = ['ok', 'products.price_unit — уже существует, пропущено.'];
    } else {
        $conn->exec("ALTER TABLE products ADD COLUMN price_unit VARCHAR(20) NOT NULL DEFAULT 'шт.' AFTER price");
        $results[] = ['ok', 'products.price_unit — колонка добавлена (DEFAULT «шт.»).'];
    }
} catch (Throwable $e) {
    $results[] = ['err', 'products.price_unit — ошибка: ' . $e->getMessage()];
}

/* ── 2. Удалить user_id из orders (связь с пользователем больше не нужна) */
try {
    $cols = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_ASSOC);
    $hasUserId = false;
    foreach ($cols as $c) {
        if (strcasecmp($c['Field'], 'user_id') === 0) { $hasUserId = true; break; }
    }
    if ($hasUserId) {
        $conn->exec("ALTER TABLE orders DROP COLUMN user_id");
        $results[] = ['ok', 'orders.user_id — удалена связь с пользователем.'];
    } else {
        $results[] = ['ok', 'orders.user_id — уже удален, пропущено.'];
    }
} catch (Throwable $e) {
    $results[] = ['err', 'orders.user_id — ошибка: ' . $e->getMessage()];
}

/* ── 3. Гарантировать approved в reviews (как в существующем коде) */
try {
    $cols = $conn->query("SHOW COLUMNS FROM reviews")->fetchAll(PDO::FETCH_ASSOC);
    $has  = false;
    foreach ($cols as $c) {
        if (strcasecmp($c['Field'], 'approved') === 0) { $has = true; break; }
    }
    if ($has) {
        $results[] = ['ok', 'reviews.approved — уже существует, пропущено.'];
    } else {
        $conn->exec("ALTER TABLE reviews ADD COLUMN approved TINYINT(1) NOT NULL DEFAULT 0");
        $results[] = ['ok', 'reviews.approved — колонка добавлена.'];
    }
} catch (Throwable $e) {
    $results[] = ['warn', 'reviews.approved — пропущено (таблица может не существовать): ' . $e->getMessage()];
}

/* ── Вывод ─────────────────────────────────────────────────────── */
$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    echo "<!DOCTYPE html><html lang='ru'><head><meta charset='UTF-8'><title>Миграция</title>";
    echo "<style>body{font-family:monospace;padding:24px}";
    echo ".ok{color:#1a7a1a}.err{color:#c00}.warn{color:#a06000}</style></head><body>";
    echo "<h2>Результат миграции</h2><ul>";
}
foreach ($results as [$type, $msg]) {
    if ($isCli) {
        echo ($type === 'ok' ? '✓' : ($type === 'err' ? '✗' : '!')) . " $msg\n";
    } else {
        echo "<li class='$type'>" . htmlspecialchars($msg) . "</li>";
    }
}
$hasError = in_array('err', array_column($results, 0));
if ($isCli) {
    echo $hasError ? "\nМиграция завершена с ошибками.\n" : "\nМиграция успешно завершена.\n";
} else {
    echo "</ul><p>" . ($hasError ? "<span class='err'>Миграция завершена с ошибками.</span>" : "<span class='ok'>Миграция успешно завершена.</span>") . "</p>";
    echo "</body></html>";
}
exit($hasError ? 1 : 0);
