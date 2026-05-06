<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once __DIR__ . '/db.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Продажа сельхозпродукции</title>
    <link rel="stylesheet" href="css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="banner">
        <div class="banner-content">
            <h1>Добро пожаловать в наше фермерское хозяйство!</h1>
            <p>Свежие и натуральные продукты прямо с полей!</p>
        </div>
    </div>

    <div class="section">
        <h2>О нас</h2>
        <p>Мы - фермерское хозяйство, поставляющее свежую сельхозпродукцию прямо с полей. Наша миссия - обеспечить вас качественными продуктами по доступным ценам.</p>
    </div>

    <div class="section">
        <h2>Хиты продаж</h2>
        <div class="products">
        <?php
        try {
            $stmt = $conn->query("SELECT * FROM products ORDER BY id DESC LIMIT 3");
            while ($row = $stmt->fetch()) {
                echo "<div class='product'>";
                echo "<img src='" . htmlspecialchars($row['image']) . "' alt='product'>";
                echo "<h3>" . htmlspecialchars($row['name']) . "</h3>";
                echo "<p>Цена: " . htmlspecialchars(number_format((float)$row['price'], 2, '.', ' ')) . " руб./" . htmlspecialchars($row['price_unit'] ?? 'шт.') . "</p>";
                echo "<a href='product.php?id=" . (int)$row['id'] . "' class='btn'>Подробнее</a>";
                echo "</div>";
            }
        } catch (Throwable $e) {
            echo "<p style='color:#b00'>Не удалось загрузить товары.</p>";
        }
        ?>
        </div>
    </div>

    <div class="section">
        <h2>Новости</h2>
        <?php
        try {
            $stmt = $conn->query("SELECT * FROM news ORDER BY date DESC LIMIT 2");
            while ($row = $stmt->fetch()) {
                echo "<div class='news'>";
                echo "<h3>" . htmlspecialchars($row['title']) . "</h3>";
                echo "<p>" . htmlspecialchars($row['content']) . "</p>";
                echo "<p><small>" . htmlspecialchars($row['date']) . "</small></p>";
                echo "</div>";
            }
        } catch (Throwable $e) {
            echo "<p style='color:#b00'>Не удалось загрузить новости.</p>";
        }
        ?>
    </div>

    <!-- ==== ОТЗЫВЫ ПОКУПАТЕЛЕЙ ==== -->
    <div class="section" id="reviews" style="scroll-margin-top: 80px;">
        <h2>Отзывы покупателей</h2>

        <?php
        // Грузим последние 5 ТОЛЬКО ОДОБРЕННЫХ отзывов
        try {
            $stmt = $conn->query("
                SELECT name, rating, text, created_at
                FROM reviews
                WHERE COALESCE(approved, 0) = 1
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $reviews = $stmt->fetchAll();
        } catch (Throwable $e) {
            $reviews = [];
            echo "<p style='color:#b00'>Не удалось загрузить отзывы. Убедитесь, что создана таблица <code>reviews</code>.</p>";
        }
        ?>

        <div class="reviews-list" style="display:grid; gap:12px; margin: 16px 0;">
            <?php if (!empty($reviews)): ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="review" style="border:1px solid #eee; border-radius:12px; padding:14px;">
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                            <span aria-label="Рейтинг" title="Оценка">
                                <?php
                                    $stars = max(1, min(5, (int)$r['rating']));
                                    echo str_repeat('★', $stars) . str_repeat('☆', 5 - $stars);
                                ?>
                            </span>
                        </div>
                        <p style="margin:8px 0 6px;"><?php echo nl2br(htmlspecialchars($r['text'])); ?></p>
                        <small style="color:#666;"><?php echo date('d.m.Y H:i', strtotime($r['created_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Пока нет отзывов. Будьте первым!</p>
            <?php endif; ?>
        </div>

        <h3>Оставить отзыв</h3>
        <?php if (!empty($_GET['review_error'])): ?>
            <p style="color:#b00;">Проверьте поля формы: имя, оценка (1–5), текст.</p>
        <?php elseif (!empty($_GET['review_ok'])): ?>
            <p style="color:#0a0;">Спасибо! Ваш отзыв отправлен и появится после модерации.</p>
        <?php endif; ?>

        <form action="submit_review.php" method="post" style="display:grid; gap:10px; max-width:520px;">
            <input type="text" name="name" placeholder="Ваше имя" required style="padding:10px; border-radius:10px; border:1px solid #ddd;">
            <select name="rating" required style="padding:10px; border-radius:10px; border:1px solid #ddd;">
                <option value="">Оценка</option>
                <option value="5">5 — Отлично</option>
                <option value="4">4 — Хорошо</option>
                <option value="3">3 — Нормально</option>
                <option value="2">2 — Плохо</option>
                <option value="1">1 — Очень плохо</option>
            </select>
            <textarea name="text" rows="4" placeholder="Ваш отзыв" required style="padding:10px; border-radius:10px; border:1px solid #ddd;"></textarea>

            <!-- honeypot: должно оставаться пустым -->
            <input type="text" name="company" autocomplete="off"
                   style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden"
                   tabindex="-1">

            <button type="submit" class="btn" style="padding:12px 14px; border-radius:12px; border:0; background:#2a7a2e; color:#fff; font-weight:600; cursor:pointer;">Отправить отзыв</button>
        </form>
    </div>
    <!-- ==== /ОТЗЫВЫ ==== -->

    <?php include 'footer.php'; ?>
</body>
</html>
