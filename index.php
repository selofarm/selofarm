<?php
session_start();
require_once __DIR__ . '/db.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>РџСЂРѕРґР°Р¶Р° СЃРµР»СЊС…РѕР·РїСЂРѕРґСѓРєС†РёРё</title>
    <link rel="stylesheet" href="css/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="banner">
        <div class="banner-content">
            <h1>Р”РѕР±СЂРѕ РїРѕР¶Р°Р»РѕРІР°С‚СЊ РІ РЅР°С€Рµ С„РµСЂРјРµСЂСЃРєРѕРµ С…РѕР·СЏР№СЃС‚РІРѕ!</h1>
            <p>РЎРІРµР¶РёРµ Рё РЅР°С‚СѓСЂР°Р»СЊРЅС‹Рµ РїСЂРѕРґСѓРєС‚С‹ РїСЂСЏРјРѕ СЃ РїРѕР»РµР№!</p>
        </div>
    </div>

    <div class="section">
        <h2>Рћ РЅР°СЃ</h2>
        <p>РњС‹ - С„РµСЂРјРµСЂСЃРєРѕРµ С…РѕР·СЏР№СЃС‚РІРѕ, РїРѕСЃС‚Р°РІР»СЏСЋС‰РµРµ СЃРІРµР¶СѓСЋ СЃРµР»СЊС…РѕР·РїСЂРѕРґСѓРєС†РёСЋ РїСЂСЏРјРѕ СЃ РїРѕР»РµР№. РќР°С€Р° РјРёСЃСЃРёСЏ - РѕР±РµСЃРїРµС‡РёС‚СЊ РІР°СЃ РєР°С‡РµСЃС‚РІРµРЅРЅС‹РјРё РїСЂРѕРґСѓРєС‚Р°РјРё РїРѕ РґРѕСЃС‚СѓРїРЅС‹Рј С†РµРЅР°Рј.</p>
    </div>

    <div class="section">
        <h2>РҐРёС‚С‹ РїСЂРѕРґР°Р¶</h2>
        <div class="products">
        <?php
        try {
            $stmt = $conn->query("SELECT * FROM products ORDER BY id DESC LIMIT 3");
            while ($row = $stmt->fetch()) {
                echo "<div class='product'>";
                echo "<img src='" . htmlspecialchars($row['image']) . "' alt='product'>";
                echo "<h3>" . htmlspecialchars($row['name']) . "</h3>";
                echo "<p>Р¦РµРЅР°: " . htmlspecialchars($row['price']) . " СЂСѓР±.</p>";
                echo "<a href='product.php?id=" . (int)$row['id'] . "' class='btn'>РџРѕРґСЂРѕР±РЅРµРµ</a>";
                echo "</div>";
            }
        } catch (Throwable $e) {
            echo "<p style='color:#b00'>РќРµ СѓРґР°Р»РѕСЃСЊ Р·Р°РіСЂСѓР·РёС‚СЊ С‚РѕРІР°СЂС‹.</p>";
        }
        ?>
        </div>
    </div>

    <div class="section">
        <h2>РќРѕРІРѕСЃС‚Рё</h2>
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
            echo "<p style='color:#b00'>РќРµ СѓРґР°Р»РѕСЃСЊ Р·Р°РіСЂСѓР·РёС‚СЊ РЅРѕРІРѕСЃС‚Рё.</p>";
        }
        ?>
    </div>

    <!-- ==== РћРўР—Р«Р’Р« РџРћРљРЈРџРђРўР•Р›Р•Р™ ==== -->
    <div class="section" id="reviews" style="scroll-margin-top: 80px;">
        <h2>РћС‚Р·С‹РІС‹ РїРѕРєСѓРїР°С‚РµР»РµР№</h2>

        <?php
        // Р“СЂСѓР·РёРј РїРѕСЃР»РµРґРЅРёРµ 5 РўРћР›Р¬РљРћ РћР”РћР‘Р Р•РќРќР«РҐ РѕС‚Р·С‹РІРѕРІ
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
            echo "<p style='color:#b00'>РќРµ СѓРґР°Р»РѕСЃСЊ Р·Р°РіСЂСѓР·РёС‚СЊ РѕС‚Р·С‹РІС‹. РЈР±РµРґРёС‚РµСЃСЊ, С‡С‚Рѕ СЃРѕР·РґР°РЅР° С‚Р°Р±Р»РёС†Р° <code>reviews</code>.</p>";
        }
        ?>

        <div class="reviews-list" style="display:grid; gap:12px; margin: 16px 0;">
            <?php if (!empty($reviews)): ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="review" style="border:1px solid #eee; border-radius:12px; padding:14px;">
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                            <span aria-label="Р РµР№С‚РёРЅРі" title="РћС†РµРЅРєР°">
                                <?php
                                    $stars = max(1, min(5, (int)$r['rating']));
                                    echo str_repeat('в…', $stars) . str_repeat('в†', 5 - $stars);
                                ?>
                            </span>
                        </div>
                        <p style="margin:8px 0 6px;"><?php echo nl2br(htmlspecialchars($r['text'])); ?></p>
                        <small style="color:#666;"><?php echo date('d.m.Y H:i', strtotime($r['created_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>РџРѕРєР° РЅРµС‚ РѕС‚Р·С‹РІРѕРІ. Р‘СѓРґСЊС‚Рµ РїРµСЂРІС‹Рј!</p>
            <?php endif; ?>
        </div>

        <h3>РћСЃС‚Р°РІРёС‚СЊ РѕС‚Р·С‹РІ</h3>
        <?php if (!empty($_GET['review_error'])): ?>
            <p style="color:#b00;">РџСЂРѕРІРµСЂСЊС‚Рµ РїРѕР»СЏ С„РѕСЂРјС‹: РёРјСЏ, РѕС†РµРЅРєР° (1вЂ“5), С‚РµРєСЃС‚.</p>
        <?php elseif (!empty($_GET['review_ok'])): ?>
            <p style="color:#0a0;">РЎРїР°СЃРёР±Рѕ! Р’Р°С€ РѕС‚Р·С‹РІ РѕС‚РїСЂР°РІР»РµРЅ Рё РїРѕСЏРІРёС‚СЃСЏ РїРѕСЃР»Рµ РјРѕРґРµСЂР°С†РёРё.</p>
        <?php endif; ?>

        <form action="submit_review.php" method="post" style="display:grid; gap:10px; max-width:520px;">
            <input type="text" name="name" placeholder="Р’Р°С€Рµ РёРјСЏ" required style="padding:10px; border-radius:10px; border:1px solid #ddd;">
            <select name="rating" required style="padding:10px; border-radius:10px; border:1px solid #ddd;">
                <option value="">РћС†РµРЅРєР°</option>
                <option value="5">5 вЂ” РћС‚Р»РёС‡РЅРѕ</option>
                <option value="4">4 вЂ” РҐРѕСЂРѕС€Рѕ</option>
                <option value="3">3 вЂ” РќРѕСЂРјР°Р»СЊРЅРѕ</option>
                <option value="2">2 вЂ” РџР»РѕС…Рѕ</option>
                <option value="1">1 вЂ” РћС‡РµРЅСЊ РїР»РѕС…Рѕ</option>
            </select>
            <textarea name="text" rows="4" placeholder="Р’Р°С€ РѕС‚Р·С‹РІ" required style="padding:10px; border-radius:10px; border:1px solid #ddd;"></textarea>

            <!-- honeypot: РґРѕР»Р¶РЅРѕ РѕСЃС‚Р°РІР°С‚СЊСЃСЏ РїСѓСЃС‚С‹Рј -->
            <input type="text" name="company" autocomplete="off"
                   style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden"
                   tabindex="-1">

            <button type="submit" class="btn" style="padding:12px 14px; border-radius:12px; border:0; background:#2a7a2e; color:#fff; font-weight:600; cursor:pointer;">РћС‚РїСЂР°РІРёС‚СЊ РѕС‚Р·С‹РІ</button>
        </form>
    </div>
    <!-- ==== /РћРўР—Р«Р’Р« ==== -->

    <?php include 'footer.php'; ?>
</body>
</html>
