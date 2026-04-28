<?php
// РћР±СЂР°Р±РѕС‚РєР° РѕС‚РїСЂР°РІРєРё РѕС‚Р·С‹РІР° СЃ РіР»Р°РІРЅРѕР№ СЃС‚СЂР°РЅРёС†С‹
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$name   = trim($_POST['name'] ?? '');
$rating = (int)($_POST['rating'] ?? 0);
$text   = trim($_POST['text'] ?? '');

// honeypot: СЃРєСЂС‹С‚РѕРµ РїРѕР»Рµ "company" РІ С„РѕСЂРјРµ РґРѕР»Р¶РЅРѕ Р±С‹С‚СЊ РїСѓСЃС‚С‹Рј
$hp = trim($_POST['company'] ?? '');
if ($hp !== '') {
    header('Location: index.php'); // Р±РѕС‚ вЂ” С‚РёС…Рѕ СѓС…РѕРґРёРј
    exit;
}

// Р±С‹СЃС‚СЂР°СЏ РІР°Р»РёРґР°С†РёСЏ
if ($name === '' || $rating < 1 || $rating > 5 || $text === '') {
    header('Location: index.php?review_error=1#reviews');
    exit;
}

require_once __DIR__ . '/db.php';
// РіР°СЂР°РЅС‚РёСЂСѓРµРј РЅР°Р»РёС‡РёРµ РєРѕР»РѕРЅРєРё approved (РЅР° СЃР»СѓС‡Р°Р№, РµСЃР»Рё РјРёРіСЂР°С†РёСЏ РЅРµ РІС‹РїРѕР»РЅРµРЅР°)
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
    // РµСЃР»Рё С‚Р°Р±Р»РёС†С‹ РЅРµС‚ РІРѕРѕР±С‰Рµ вЂ” РјРѕР¶РЅРѕ СЃРѕР·РґР°С‚СЊ (РѕРїС†РёРѕРЅР°Р»СЊРЅРѕ)
    // $conn->exec("CREATE TABLE IF NOT EXISTS reviews (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, rating TINYINT NOT NULL, text TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, approved TINYINT(1) NOT NULL DEFAULT 0) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

// СЃРѕС…СЂР°РЅСЏРµРј РЎРўР РћР“Рћ СЃ approved=0 (РЅР° РјРѕРґРµСЂР°С†РёСЋ)
$stmt = $conn->prepare("INSERT INTO reviews (name, rating, text, approved) VALUES (:name, :rating, :text, 0)");
$stmt->execute([
    ':name'   => $name,
    ':rating' => $rating,
    ':text'   => $text,
]);

header('Location: index.php?review_ok=1#reviews');
exit;
