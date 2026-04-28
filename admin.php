<?php
session_start();

/* ===== Р’РєР»СЋС‡РёС‚СЊ РІС‹РІРѕРґ РѕС€РёР±РѕРє (РґР»СЏ Р»РѕРєР°Р»СЊРЅРѕР№ РѕС‚Р»Р°РґРєРё) ===== */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/db.php';

/* =====================[ РђРІС‚РѕСЂРёР·Р°С†РёСЏ ]====================== */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* =====================[ CSRF-С‚РѕРєРµРЅ ]======================= */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf'];

/* =====================[ Logout ]=========================== */
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

/* =====================[ РќР°СЃС‚СЂРѕР№РєРё Р·Р°РіСЂСѓР·РєРё ]=============== */
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_file_size = 10 * 1024 * 1024; // 10 РњР‘

/**
 * Р—Р°РіСЂСѓР¶Р°РµС‚ РёР·РѕР±СЂР°Р¶РµРЅРёРµ РёР· $_FILES[$field] Рё РІРѕР·РІСЂР°С‰Р°РµС‚ Р±РёРЅР°СЂРЅС‹Рµ РґР°РЅРЅС‹Рµ (BLOB) РёР»Рё null, РµСЃР»Рё С„Р°Р№Р» РЅРµ РІС‹Р±СЂР°РЅ.
 * РџСЂРѕРІРѕРґРёС‚ Р±Р°Р·РѕРІС‹Рµ РїСЂРѕРІРµСЂРєРё СЂР°Р·РјРµСЂР°/С‚РёРїР°. Р Р°Р±РѕС‚Р°РµС‚ Рё Р±РµР· СЂР°СЃС€РёСЂРµРЅРёСЏ fileinfo (РёСЃРїРѕР»СЊР·СѓРµС‚ $_FILES['type'] РєР°Рє С„РѕР»Р±СЌРє).
 */
function loadUploadedImage(string $field, array $allowed_types, int $max_file_size, ?string &$err): ?string {
    if (empty($_FILES[$field]['name'])) return null;

    $f = $_FILES[$field];

    if ($f['error'] !== UPLOAD_ERR_OK) { $err = "РћС€РёР±РєР° Р·Р°РіСЂСѓР·РєРё С„Р°Р№Р»Р°: " . (int)$f['error']; return null; }
    if ($f['size'] > $max_file_size)   { $err = "Р Р°Р·РјРµСЂ РёР·РѕР±СЂР°Р¶РµРЅРёСЏ РїСЂРµРІС‹С€Р°РµС‚ 10 РњР‘"; return null; }

    // РџРѕРїСЂРѕР±СѓРµРј РЅР°РґС‘Р¶РЅРѕ СѓР·РЅР°С‚СЊ MIME С‡РµСЂРµР· fileinfo
    $mime = null;
    if (class_exists('finfo')) {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($f['tmp_name']) ?: null;
    }
    // Р¤РѕР»Р±СЌРє РЅР° $_FILES['type']
    if ($mime === null && !empty($f['type'])) {
        $mime = $f['type'];
    }
    if ($mime === null || !in_array($mime, $allowed_types, true)) {
        $err = "РќРµРґРѕРїСѓСЃС‚РёРјС‹Р№ С‚РёРї РёР·РѕР±СЂР°Р¶РµРЅРёСЏ";
        return null;
    }

    return file_get_contents($f['tmp_name']); // BLOB
}

/* =====================[ РџСЂРµР»РѕР°Рґ РґР»СЏ СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ ]======= */
$edit_product = null;
$edit_news    = null;

/* РџРѕРґС‚СЏРЅСѓС‚СЊ С‚РѕРІР°СЂ РґР»СЏ СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ */
if (isset($_GET['edit_product'])) {
    $pid = (int)$_GET['edit_product'];
    if ($pid > 0) {
        $stmt = $conn->prepare("SELECT id, name, price, description, image FROM products WHERE id = ?");
        $stmt->execute([$pid]);
        $edit_product = $stmt->fetch() ?: null;
        if (!$edit_product) {
            $error = "РўРѕРІР°СЂ #$pid РЅРµ РЅР°Р№РґРµРЅ.";
        }
    }
}

/* РџРѕРґС‚СЏРЅСѓС‚СЊ РЅРѕРІРѕСЃС‚СЊ РґР»СЏ СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ */
if (isset($_GET['edit_news'])) {
    $nid = (int)$_GET['edit_news'];
    if ($nid > 0) {
        $stmt = $conn->prepare("SELECT id, title, content, date, image FROM news WHERE id = ?");
        $stmt->execute([$nid]);
        $edit_news = $stmt->fetch() ?: null;
        if (!$edit_news) {
            $error = "РќРѕРІРѕСЃС‚СЊ #$nid РЅРµ РЅР°Р№РґРµРЅР°.";
        }
    }
}

/* =====================[ РћР±СЂР°Р±РѕС‚РєР° С„РѕСЂРј POST ]============== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // РџСЂРѕРІРµСЂРєР° CSRF
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        $error = "РќРµРІРµСЂРЅС‹Р№ CSRF С‚РѕРєРµРЅ";
    } else {

        /* ---------- РўРћР’РђР Р«: РґРѕР±Р°РІРёС‚СЊ / СЂРµРґР°РєС‚РёСЂРѕРІР°С‚СЊ ---------- */
        if (isset($_POST['add_product']) || isset($_POST['edit_product'])) {
            $name        = trim($_POST['name'] ?? '');
            $priceStr    = trim((string)($_POST['price'] ?? ''));
            $description = trim($_POST['description'] ?? '');
            $imgErr      = null;
            $new_image   = loadUploadedImage('image', $allowed_types, $max_file_size, $imgErr);

            if ($name === '' || $description === '') {
                $error = "Р—Р°РїРѕР»РЅРёС‚Рµ РІСЃРµ РѕР±СЏР·Р°С‚РµР»СЊРЅС‹Рµ РїРѕР»СЏ (РќР°Р·РІР°РЅРёРµ Рё РћРїРёСЃР°РЅРёРµ).";
            } elseif ($priceStr === '' || !is_numeric($priceStr) || (float)$priceStr < 0) {
                $error = "Р¦РµРЅР° РґРѕР»Р¶РЅР° Р±С‹С‚СЊ С‡РёСЃР»РѕРј, РЅРµ РјРµРЅСЊС€Рµ 0.";
            } elseif (isset($_POST['add_product']) && $new_image === null) {
                $error = $imgErr ?: "РџРѕР¶Р°Р»СѓР№СЃС‚Р°, Р·Р°РіСЂСѓР·РёС‚Рµ РёР·РѕР±СЂР°Р¶РµРЅРёРµ С‚РѕРІР°СЂР°.";
            }

            if (!isset($error)) {
                try {
                    if (isset($_POST['add_product'])) {
                        $stmt = $conn->prepare("INSERT INTO products (name, price, description, image) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$name, (float)$priceStr, $description, $new_image]);
                        $success = "РџСЂРѕРґСѓРєС‚ СѓСЃРїРµС€РЅРѕ РґРѕР±Р°РІР»РµРЅ!";
                       echo "<script>window.location.href = 'admin.php#products';</script>";
                        exit;
                    } else {
                        // edit_product
                        $id = (int)($_POST['id'] ?? 0);
                        if ($id <= 0) {
                            throw new RuntimeException("РќРµРєРѕСЂСЂРµРєС‚РЅС‹Р№ РёРґРµРЅС‚РёС„РёРєР°С‚РѕСЂ С‚РѕРІР°СЂР°.");
                        }
                        if ($new_image === null) {
                            $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
                            $stmt->execute([$id]);
                            $new_image = $stmt->fetchColumn();
                        }
                        $stmt = $conn->prepare("UPDATE products SET name = ?, price = ?, description = ?, image = ? WHERE id = ?");
                        $stmt->execute([$name, (float)$priceStr, $description, $new_image, $id]);
                        $success = "РџСЂРѕРґСѓРєС‚ СѓСЃРїРµС€РЅРѕ РѕР±РЅРѕРІР»С‘РЅ!";
                        echo "<script>window.location.href = 'admin.php#products';</script>";
                        exit;
                    }
                } catch (Throwable $e) {
                    $error = "РћС€РёР±РєР° СЃРѕС…СЂР°РЅРµРЅРёСЏ С‚РѕРІР°СЂР°: " . $e->getMessage();
                    // Р’РµСЂРЅСѓС‚СЊ РґР°РЅРЅС‹Рµ РІ С„РѕСЂРјСѓ
                    if (isset($_POST['edit_product'])) {
                        $pid = (int)($_POST['id'] ?? 0);
                        if ($pid > 0) {
                            $stmt = $conn->prepare("SELECT id, name, price, description, image FROM products WHERE id = ?");
                            $stmt->execute([$pid]);
                            $edit_product = $stmt->fetch() ?: null;
                        }
                    }
                }
            } else {
                // Р•СЃР»Рё Р±С‹Р»Р° РѕС€РёР±РєР° вЂ” РїРѕРґС‚СЏРЅРµРј Р·Р°РїРёСЃСЊ РґР»СЏ СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ (РµСЃР»Рё СЌС‚Рѕ edit)
                if (isset($_POST['edit_product'])) {
                    $pid = (int)($_POST['id'] ?? 0);
                    if ($pid > 0) {
                        $stmt = $conn->prepare("SELECT id, name, price, description, image FROM products WHERE id = ?");
                        $stmt->execute([$pid]);
                        $edit_product = $stmt->fetch() ?: null;
                    }
                }
            }
        }

        /* ---------- РќРћР’РћРЎРўР: РґРѕР±Р°РІРёС‚СЊ / СЂРµРґР°РєС‚РёСЂРѕРІР°С‚СЊ ---------- */
        if (isset($_POST['add_news']) || isset($_POST['edit_news'])) {
            $title   = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $date    = date('Y-m-d');
            $imgErr  = null;
            $new_image = loadUploadedImage('image', $allowed_types, $max_file_size, $imgErr);

            if ($title === '' || $content === '') {
                $error = "Р—Р°РїРѕР»РЅРёС‚Рµ РїРѕР»СЏ Р—Р°РіРѕР»РѕРІРѕРє Рё РўРµРєСЃС‚.";
            }

            if (!isset($error)) {
                if (isset($_POST['add_news'])) {
                    try {
                        $stmt = $conn->prepare("INSERT INTO news (title, content, date, image) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$title, $content, $date, $new_image]);
                        $success = "РќРѕРІРѕСЃС‚СЊ СѓСЃРїРµС€РЅРѕ РґРѕР±Р°РІР»РµРЅР°!";
                        echo "<script>window.location.href = 'admin.php#news';</script>";
						exit;
                    } catch (Throwable $e) {
                        $error = "РћС€РёР±РєР° РґРѕР±Р°РІР»РµРЅРёСЏ РЅРѕРІРѕСЃС‚Рё: " . $e->getMessage();
                    }
                } else {
                    // edit_news
                    try {
                        $id = (int)($_POST['id'] ?? 0);
                        if ($id <= 0) {
                            throw new RuntimeException("РќРµРєРѕСЂСЂРµРєС‚РЅС‹Р№ РёРґРµРЅС‚РёС„РёРєР°С‚РѕСЂ РЅРѕРІРѕСЃС‚Рё.");
                        }
                        if ($new_image === null) {
                            $stmt = $conn->prepare("SELECT image FROM news WHERE id = ?");
                            $stmt->execute([$id]);
                            $new_image = $stmt->fetchColumn();
                        }
                        $stmt = $conn->prepare("UPDATE news SET title = ?, content = ?, image = ? WHERE id = ?");
                        $stmt->execute([$title, $content, $new_image, $id]);
                        $success = "РќРѕРІРѕСЃС‚СЊ СѓСЃРїРµС€РЅРѕ РѕР±РЅРѕРІР»РµРЅР°!";
                       echo "<script>window.location.href = 'admin.php#news';</script>";
                        exit;
                    } catch (Throwable $e) {
                        $error = "РћС€РёР±РєР° РѕР±РЅРѕРІР»РµРЅРёСЏ РЅРѕРІРѕСЃС‚Рё: " . $e->getMessage();
                        // РџРѕРґС‚СЏРЅРµРј Р·Р°РїРёСЃСЊ РѕР±СЂР°С‚РЅРѕ РІ С„РѕСЂРјСѓ
                        $nid = (int)($_POST['id'] ?? 0);
                        if ($nid > 0) {
                            $stmt = $conn->prepare("SELECT id, title, content, date, image FROM news WHERE id = ?");
                            $stmt->execute([$nid]);
                            $edit_news = $stmt->fetch() ?: null;
                        }
                    }
                }
            } else {
                // Р•СЃР»Рё Р±С‹Р»Р° РѕС€РёР±РєР° вЂ” РїРѕРґС‚СЏРЅРµРј Р·Р°РїРёСЃСЊ РґР»СЏ СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёСЏ (РµСЃР»Рё СЌС‚Рѕ edit)
                if (isset($_POST['edit_news'])) {
                    $nid = (int)($_POST['id'] ?? 0);
                    if ($nid > 0) {
                        $stmt = $conn->prepare("SELECT id, title, content, date, image FROM news WHERE id = ?");
                        $stmt->execute([$nid]);
                        $edit_news = $stmt->fetch() ?: null;
                    }
                }
            }
        }

        /* ---------- РћРўР—Р«Р’Р«: РѕРґРѕР±СЂРёС‚СЊ / СѓРґР°Р»РёС‚СЊ ---------- */
        if (isset($_POST['approve_review']) || isset($_POST['delete_review'])) {
            $review_id = (int)($_POST['review_id'] ?? 0);

            // РіР°СЂР°РЅС‚РёСЂСѓРµРј РЅР°Р»РёС‡РёРµ РїРѕР»СЏ approved (РµСЃР»Рё РєРѕРіРґР°-С‚Рѕ С‚Р°Р±Р»РёС†Р° Р±С‹Р»Р° Р±РµР· РЅРµРіРѕ)
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
                // РµСЃР»Рё reviews РµС‰С‘ РЅРµС‚ вЂ” РЅРёС‡РµРіРѕ РЅРµ РґРµР»Р°РµРј
            }

            try {
                if (isset($_POST['approve_review'])) {
                    $stmt = $conn->prepare("UPDATE reviews SET approved = 1 WHERE id = ?");
                    $stmt->execute([$review_id]);
                    $success = "РћС‚Р·С‹РІ #$review_id РѕРґРѕР±СЂРµРЅ.";
                } else {
                    $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
                    $stmt->execute([$review_id]);
                    $success = "РћС‚Р·С‹РІ #$review_id СѓРґР°Р»С‘РЅ.";
                }
                echo "<script>window.location.href = 'admin.php#reviews';</script>";
                exit;
            } catch (Throwable $e) {
                $error = "РћС€РёР±РєР° РѕР±СЂР°Р±РѕС‚РєРё РѕС‚Р·С‹РІР°: " . $e->getMessage();
            }
        }
    }
}

/* =====================[ РЈРґР°Р»РµРЅРёРµ РїРѕ GET ]================== */
if (isset($_GET['delete_product'])) {
    $product_id = (int)$_GET['delete_product'];
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) {
            $error = "РќРµР»СЊР·СЏ СѓРґР°Р»РёС‚СЊ РїСЂРѕРґСѓРєС‚, С‚Р°Рє РєР°Рє РѕРЅ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ РІ Р·Р°РєР°Р·Р°С…!";
        } else {
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $success = "РџСЂРѕРґСѓРєС‚ СѓСЃРїРµС€РЅРѕ СѓРґР°Р»С‘РЅ!";
        }
    } catch (Throwable $e) {
        $error = "РћС€РёР±РєР° СѓРґР°Р»РµРЅРёСЏ РїСЂРѕРґСѓРєС‚Р°: " . $e->getMessage();
    }
}
if (isset($_GET['delete_news'])) {
    $news_id = (int)$_GET['delete_news'];
    try {
        $stmt = $conn->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$news_id]);
        $success = "РќРѕРІРѕСЃС‚СЊ СѓСЃРїРµС€РЅРѕ СѓРґР°Р»РµРЅР°!";
    } catch (Throwable $e) {
        $error = "РћС€РёР±РєР° СѓРґР°Р»РµРЅРёСЏ РЅРѕРІРѕСЃС‚Рё: " . $e->getMessage();
    }
}

/* =====================[ Р”Р°РЅРЅС‹Рµ РґР»СЏ СЌРєСЂР°РЅР° ]================ */
// РџСЂРѕРґСѓРєС‚С‹
$products = [];
try {
    $stmt = $conn->query("SELECT id, name, price, description, image FROM products ORDER BY id DESC");
    $products = $stmt->fetchAll();
} catch (Throwable $e) { /* РёРіРЅРѕСЂ РґР»СЏ СЃРїРёСЃРєР° */ }

// РќРѕРІРѕСЃС‚Рё
$news = [];
try {
    $stmt = $conn->query("SELECT id, title, content, date, image FROM news ORDER BY date DESC, id DESC");
    $news = $stmt->fetchAll();
} catch (Throwable $e) { /* РёРіРЅРѕСЂ */ }

// Р—Р°РєР°Р·С‹
$orders = [];
try {
    $stmt = $conn->query("
        SELECT o.id, o.order_date, o.first_name, o.last_name, o.phone, o.shipping_address, o.status, u.username
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.order_date DESC, o.id DESC
    ");
    $orders = $stmt->fetchAll();
} catch (Throwable $e) { /* РёРіРЅРѕСЂ */ }

// РџРѕР·РёС†РёРё Р·Р°РєР°Р·РѕРІ
$order_items = [];
if ($orders) {
    $stmt = $conn->prepare("
        SELECT oi.order_id, oi.quantity, oi.price, p.name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    foreach ($orders as $order) {
        try {
            $stmt->execute([$order['id']]);
            $order_items[$order['id']] = $stmt->fetchAll();
        } catch (Throwable $e) {
            $order_items[$order['id']] = [];
        }
    }
}

/* =====================[ РЈС‚РёР»РёС‚Р°: Р·РІС‘Р·РґС‹ ]================== */
function stars($n){ $n = (int)$n; $n = max(0, min(5,$n)); return str_repeat('в…',$n) . str_repeat('в†', 5-$n); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>РђРґРјРёРЅ-РїР°РЅРµР»СЊ</title>
    <link rel="stylesheet" href="css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { display: flex; margin: 0; font-family: 'Roboto', sans-serif; }
        .sidebar { width: 250px; background: #2c3e50; color: white; height: 100vh; padding: 20px; position: fixed; }
        .sidebar h2 { margin-top: 0; }
        .sidebar a { color: white; text-decoration: none; display: block; padding: 10px; margin: 5px 0; }
        .sidebar a:hover { background: #34495e; }
        .main-content { margin-left: 270px; padding: 20px; width: calc(100% - 270px); }
        .admin-header { margin-bottom: 20px; }
        .section { margin-bottom: 30px; }
        .form-container { max-width: 800px; background: white; padding: 20px; border-radius: 5px; }
        .input-group { margin-bottom: 15px; display: flex; align-items: center; }
        .input-group i { margin-right: 10px; }
        .input-group input, .input-group textarea { width: 100%; padding: 10px; }
        .btn { padding: 10px 20px; background: #28a745; color: white; border: none; cursor: pointer; }
        .btn:hover { background: #218838; }
        .logout { background: #dc3545; }
        .logout:hover { background: #c82333; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .products-section, .news-section, .orders-section { margin-top: 20px; }
        .order-card, .product-card, .news-card { border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
        .order-table, .products-table, .news-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .order-table th, .order-table td, .products-table th, .products-table td, .news-table th, .news-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .order-table th, .products-table th, .news-table th { background-color: #f4f4f4; }
        .btn-edit, .btn-delete { display: inline-block; padding: 5px 10px; color: white; text-decoration: none; border-radius: 3px; margin-right: 5px; }
        .btn-edit { background: #007bff; }
        .btn-edit:hover { background: #0056b3; }
        .btn-delete { background: #dc3545; }
        .btn-delete:hover { background: #c82333; }
        .current-image, .product-image, .news-image { max-width: 100px; margin-bottom: 10px; }
        .badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;border:1px solid #ddd;margin-right:8px}
        .badge.ok{background:#e6f8ea;border-color:#bde5c6;}
        .badge.wait{background:#fff7e6;border-color:#ffe0a3;}
        .rv-table{width:100%;border-collapse:collapse;margin-top:10px}
        .rv-table th,.rv-table td{padding:10px;border-bottom:1px solid #eee;vertical-align:top;text-align:left}
        .rv-actions{display:flex;gap:8px}
        .btn.secondary{background:#fff;color:#333;border:1px solid #ddd}
        .btn.primary{background:#2a7a2e;color:#fff;border:0}
        .muted{color:#666}
        .pagination{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:12px;}
        textarea{min-height:120px}
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>РђРґРјРёРЅРєР°</h2>
        <a href="#products"><i class="fas fa-box"></i> РџСЂРѕРґСѓРєС‚С‹</a>
        <a href="#news"><i class="fas fa-newspaper"></i> РќРѕРІРѕСЃС‚Рё</a>
        <a href="#reviews"><i class="fas fa-comments"></i> РћС‚Р·С‹РІС‹</a>
        <a href="#orders"><i class="fas fa-shopping-cart"></i> Р—Р°РєР°Р·С‹</a>
        <a href="admin.php?logout=true" class="btn logout"><i class="fas fa-sign-out-alt"></i> Р’С‹Р№С‚Рё</a>
    </div>

    <!-- РћСЃРЅРѕРІРЅР°СЏ С‡Р°СЃС‚СЊ -->
    <div class="main-content">
        <div class="admin-header">
            <h1>РџР°РЅРµР»СЊ СѓРїСЂР°РІР»РµРЅРёСЏ</h1>
        </div>

        <div class="section">
            <?php if (!empty($success)) echo "<p class='success'><i class='fas fa-check-circle'></i> ".htmlspecialchars($success)."</p>"; ?>
            <?php if (!empty($error))   echo "<p class='error'><i class='fas fa-exclamation-circle'></i> ".htmlspecialchars($error)."</p>"; ?>

            <!-- РџСЂРѕРґСѓРєС‚С‹ -->
            <h2 id="products"><?php echo $edit_product ? 'Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РїСЂРѕРґСѓРєС‚' : 'Р”РѕР±Р°РІРёС‚СЊ РїСЂРѕРґСѓРєС‚'; ?></h2>
            <div class="form-container">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <?php if ($edit_product): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$edit_product['id']; ?>">
                        <?php if (!empty($edit_product['image'])): ?>
                            <p>РўРµРєСѓС‰РµРµ РёР·РѕР±СЂР°Р¶РµРЅРёРµ:</p>
                            <img src="data:image/jpeg;base64,<?php echo base64_encode($edit_product['image']); ?>" class="current-image" alt="РўРµРєСѓС‰РµРµ РёР·РѕР±СЂР°Р¶РµРЅРёРµ">
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="input-group">
                        <i class="fas fa-box"></i>
                        <input type="text" name="name" placeholder="РќР°Р·РІР°РЅРёРµ РїСЂРѕРґСѓРєС‚Р°" value="<?php echo $edit_product ? htmlspecialchars($edit_product['name']) : ''; ?>" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-ruble-sign"></i>
                        <input type="number" name="price" placeholder="Р¦РµРЅР°" step="0.01" min="0" value="<?php echo $edit_product ? htmlspecialchars($edit_product['price']) : ''; ?>" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-file-alt"></i>
                        <textarea name="description" placeholder="РћРїРёСЃР°РЅРёРµ" required><?php echo $edit_product ? htmlspecialchars($edit_product['description']) : ''; ?></textarea>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-image"></i>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/gif" <?php echo $edit_product ? '' : 'required'; ?>>
                        <div class="muted" style="margin-left:8px">JPEG/PNG/GIF, РґРѕ 10 РњР‘</div>
                    </div>

                    <button type="submit" name="<?php echo $edit_product ? 'edit_product' : 'add_product'; ?>" class="btn">
                        <i class="fas fa-<?php echo $edit_product ? 'edit' : 'plus'; ?>"></i>
                        <?php echo $edit_product ? 'РЎРѕС…СЂР°РЅРёС‚СЊ РёР·РјРµРЅРµРЅРёСЏ' : 'Р”РѕР±Р°РІРёС‚СЊ РїСЂРѕРґСѓРєС‚'; ?>
                    </button>
                    <?php if ($edit_product): ?>
                        <a href="admin.php#products" class="btn secondary">РћС‚РјРµРЅРёС‚СЊ</a>
                    <?php endif; ?>
                </form>
            </div>

            <h2>РЎРїРёСЃРѕРє РїСЂРѕРґСѓРєС‚РѕРІ</h2>
            <div class="products-section">
                <?php if (empty($products)): ?>
                    <p>РџСЂРѕРґСѓРєС‚РѕРІ РїРѕРєР° РЅРµС‚</p>
                <?php else: ?>
                    <table class="products-table">
                        <tr>
                            <th>ID</th>
                            <th>РќР°Р·РІР°РЅРёРµ</th>
                            <th>Р¦РµРЅР°</th>
                            <th>РћРїРёСЃР°РЅРёРµ</th>
                            <th>РР·РѕР±СЂР°Р¶РµРЅРёРµ</th>
                            <th>Р”РµР№СЃС‚РІРёСЏ</th>
                        </tr>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo (int)$product['id']; ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo number_format((float)$product['price'], 2, '.', ' '); ?> СЂСѓР±.</td>
                                <td><?php echo nl2br(htmlspecialchars($product['description'])); ?></td>
                                <td>
                                    <?php if (!empty($product['image'])): ?>
                                        <img src="data:image/jpeg;base64,<?php echo base64_encode($product['image']); ?>" class="product-image" alt="РР·РѕР±СЂР°Р¶РµРЅРёРµ">
                                    <?php else: ?>
                                        РќРµС‚ РёР·РѕР±СЂР°Р¶РµРЅРёСЏ
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="admin.php?edit_product=<?php echo (int)$product['id']; ?>#products" class="btn-edit"><i class="fas fa-edit"></i> Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ</a>
                                    <a href="admin.php?delete_product=<?php echo (int)$product['id']; ?>#products" class="btn-delete" onclick="return confirm('РЈРґР°Р»РёС‚СЊ РїСЂРѕРґСѓРєС‚ #<?php echo (int)$product['id']; ?>?');"><i class="fas fa-trash"></i> РЈРґР°Р»РёС‚СЊ</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <!-- РќРѕРІРѕСЃС‚Рё -->
            <h2 id="news"><?php echo $edit_news ? 'Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ РЅРѕРІРѕСЃС‚СЊ/Р°РєС†РёСЋ' : 'Р”РѕР±Р°РІРёС‚СЊ РЅРѕРІРѕСЃС‚СЊ/Р°РєС†РёСЋ'; ?></h2>
            <div class="form-container">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                    <?php if ($edit_news): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$edit_news['id']; ?>">
                        <?php if (!empty($edit_news['image'])): ?>
                            <p>РўРµРєСѓС‰РµРµ РёР·РѕР±СЂР°Р¶РµРЅРёРµ:</p>
                            <img src="data:image/jpeg;base64,<?php echo base64_encode($edit_news['image']); ?>" class="current-image" alt="РўРµРєСѓС‰РµРµ РёР·РѕР±СЂР°Р¶РµРЅРёРµ">
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="input-group">
                        <i class="fas fa-newspaper"></i>
                        <input type="text" name="title" placeholder="Р—Р°РіРѕР»РѕРІРѕРє" value="<?php echo $edit_news ? htmlspecialchars($edit_news['title']) : ''; ?>" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-file-alt"></i>
                        <textarea name="content" placeholder="РўРµРєСЃС‚ РЅРѕРІРѕСЃС‚Рё/Р°РєС†РёРё" required><?php echo $edit_news ? htmlspecialchars($edit_news['content']) : ''; ?></textarea>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-image"></i>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/gif">
                        <div class="muted" style="margin-left:8px">JPEG/PNG/GIF, РґРѕ 10 РњР‘</div>
                    </div>

                    <button type="submit" name="<?php echo $edit_news ? 'edit_news' : 'add_news'; ?>" class="btn">
                        <i class="fas fa-<?php echo $edit_news ? 'edit' : 'plus'; ?>"></i>
                        <?php echo $edit_news ? 'РЎРѕС…СЂР°РЅРёС‚СЊ РёР·РјРµРЅРµРЅРёСЏ' : 'Р”РѕР±Р°РІРёС‚СЊ РЅРѕРІРѕСЃС‚СЊ'; ?>
                    </button>
                    <?php if ($edit_news): ?>
                        <a href="admin.php#news" class="btn secondary">РћС‚РјРµРЅРёС‚СЊ</a>
                    <?php endif; ?>
                </form>
            </div>

            <h2>РЎРїРёСЃРѕРє РЅРѕРІРѕСЃС‚РµР№/Р°РєС†РёР№</h2>
            <div class="news-section">
                <?php if (empty($news)): ?>
                    <p>РќРѕРІРѕСЃС‚РµР№ РїРѕРєР° РЅРµС‚</p>
                <?php else: ?>
                    <table class="news-table">
                        <tr>
                            <th>ID</th>
                            <th>Р—Р°РіРѕР»РѕРІРѕРє</th>
                            <th>РўРµРєСЃС‚</th>
                            <th>Р”Р°С‚Р°</th>
                            <th>РР·РѕР±СЂР°Р¶РµРЅРёРµ</th>
                            <th>Р”РµР№СЃС‚РІРёСЏ</th>
                        </tr>
                        <?php foreach ($news as $item): ?>
                            <tr>
                                <td><?php echo (int)$item['id']; ?></td>
                                <td><?php echo htmlspecialchars($item['title']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($item['content'])); ?></td>
                                <td><?php echo htmlspecialchars($item['date']); ?></td>
                                <td>
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="data:image/jpeg;base64,<?php echo base64_encode($item['image']); ?>" class="news-image" alt="РР·РѕР±СЂР°Р¶РµРЅРёРµ">
                                    <?php else: ?>
                                        РќРµС‚ РёР·РѕР±СЂР°Р¶РµРЅРёСЏ
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="admin.php?edit_news=<?php echo (int)$item['id']; ?>#news" class="btn-edit"><i class="fas fa-edit"></i> Р РµРґР°РєС‚РёСЂРѕРІР°С‚СЊ</a>
                                    <a href="admin.php?delete_news=<?php echo (int)$item['id']; ?>#news" class="btn-delete" onclick="return confirm('РЈРґР°Р»РёС‚СЊ РЅРѕРІРѕСЃС‚СЊ #<?php echo (int)$item['id']; ?>?');"><i class="fas fa-trash"></i> РЈРґР°Р»РёС‚СЊ</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <!-- РћРўР—Р«Р’Р« -->
            <h2 id="reviews">РћС‚Р·С‹РІС‹</h2>

            <div class="form-container" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div>
                    <a class="badge <?php echo ($rv_filter==='pending')?'wait':''; ?>" href="admin.php?rv_filter=pending#reviews">РќР° РјРѕРґРµСЂР°С†РёРё</a>
                    <a class="badge <?php echo ($rv_filter==='approved')?'ok':''; ?>" href="admin.php?rv_filter=approved#reviews">РћРґРѕР±СЂРµРЅРЅС‹Рµ</a>
                    <a class="badge" href="admin.php?rv_filter=all#reviews">Р’СЃРµ</a>
                </div>
                <div class="muted">Р’СЃРµРіРѕ: <?php echo (int)($total_reviews ?? 0); ?></div>
            </div>

            <div class="products-section">
                <?php
                // РџР°РіРёРЅР°С†РёСЏ РѕС‚Р·С‹РІРѕРІ (РїРµСЂРµРјРµРЅРЅС‹Рµ РјРѕРіР»Рё РЅРµ РёРЅРёС†РёР°Р»РёР·РёСЂРѕРІР°С‚СЊСЃСЏ, РїРѕСЌС‚РѕРјСѓ РґСѓР±Р»РёСЂСѓРµРј Р±РµР·РѕРїР°СЃРЅС‹Рµ Р·РЅР°С‡РµРЅРёСЏ)
                $rv_filter = $_GET['rv_filter'] ?? 'pending';
                $rv_page   = max(1, (int)($_GET['rv_page'] ?? 1));
                $rv_limit  = 10;
                $rv_offset = ($rv_page - 1) * $rv_limit;

                // РџРѕР»СѓС‡РёРј РѕС‚Р·С‹РІС‹ РґР»СЏ РІС‹РІРѕРґР° (РµС‰С‘ СЂР°Р·, С‡С‚РѕР±С‹ С‚РѕС‡РЅРѕ Р±С‹Р»Рё РїСЂРё РїСЂСЏРјРѕРј Р·Р°С…РѕРґРµ)
                $rv_where = "WHERE 1";
                if ($rv_filter === 'pending') $rv_where .= " AND (approved = 0 OR approved IS NULL)";
                elseif ($rv_filter === 'approved') $rv_where .= " AND approved = 1";

                try {
                    $total_reviews = (int)$conn->query("SELECT COUNT(*) FROM reviews $rv_where")->fetchColumn();
                    $stmt = $conn->prepare("SELECT id, name, rating, text, approved, created_at FROM reviews $rv_where ORDER BY created_at DESC LIMIT :lim OFFSET :off");
                    $stmt->bindValue(':lim', $rv_limit, PDO::PARAM_INT);
                    $stmt->bindValue(':off', $rv_offset, PDO::PARAM_INT);
                    $stmt->execute();
                    $reviews = $stmt->fetchAll();
                } catch (Throwable $e) {
                    $reviews = [];
                    $total_reviews = 0;
                }
                ?>

                <?php if (empty($reviews)): ?>
                    <p>РћС‚Р·С‹РІРѕРІ РЅРµС‚.</p>
                <?php else: ?>
                    <table class="rv-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>РРјСЏ</th>
                                <th>РћС†РµРЅРєР°</th>
                                <th>РўРµРєСЃС‚</th>
                                <th>РЎС‚Р°С‚СѓСЃ</th>
                                <th>Р”Р°С‚Р°</th>
                                <th>Р”РµР№СЃС‚РІРёСЏ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $r): ?>
                                <tr>
                                    <td><?php echo (int)$r['id']; ?></td>
                                    <td><?php echo htmlspecialchars($r['name']); ?></td>
                                    <td><?php echo stars($r['rating']); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($r['text'])); ?></td>
                                    <td>
                                        <?php
                                            $approved = (int)($r['approved'] ?? 0);
                                            echo $approved ? '<span class="badge ok">РћРґРѕР±СЂРµРЅ</span>' : '<span class="badge wait">РќР° РјРѕРґРµСЂР°С†РёРё</span>';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at']))); ?></td>
                                    <td class="rv-actions">
                                        <?php if (empty($r['approved'])): ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="review_id" value="<?php echo (int)$r['id']; ?>">
                                            <button class="btn primary" type="submit" name="approve_review">РћРґРѕР±СЂРёС‚СЊ</button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="post" style="display:inline" onsubmit="return confirm('РЈРґР°Р»РёС‚СЊ РѕС‚Р·С‹РІ #<?php echo (int)$r['id']; ?>?')">
                                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="review_id" value="<?php echo (int)$r['id']; ?>">
                                            <button class="btn secondary" type="submit" name="delete_review">РЈРґР°Р»РёС‚СЊ</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php
                        $rv_pages = max(1, ceil($total_reviews / $rv_limit));
                    ?>
                    <div class="pagination">
                        <?php if ($rv_page > 1): $p = $rv_page - 1; ?>
                            <a class="btn secondary" href="admin.php?rv_filter=<?php echo urlencode($rv_filter); ?>&rv_page=<?php echo $p; ?>#reviews">РќР°Р·Р°Рґ</a>
                        <?php endif; ?>
                        <span class="muted">РЎС‚СЂ. <?php echo $rv_page; ?> РёР· <?php echo $rv_pages; ?></span>
                        <?php if ($rv_page < $rv_pages): $n = $rv_page + 1; ?>
                            <a class="btn secondary" href="admin.php?rv_filter=<?php echo urlencode($rv_filter); ?>&rv_page=<?php echo $n; ?>#reviews">Р’РїРµСЂС‘Рґ</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Р—Р°РєР°Р·С‹ -->
            <h2 id="orders">Р—Р°РєР°Р·С‹</h2>
            <div class="orders-section">
                <?php if (empty($orders)): ?>
                    <p>Р—Р°РєР°Р·РѕРІ РїРѕРєР° РЅРµС‚</p>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <h3>Р—Р°РєР°Р· #<?php echo (int)$order['id']; ?></h3>
                            <p><strong>РџРѕР»СЊР·РѕРІР°С‚РµР»СЊ:</strong> <?php echo htmlspecialchars($order['username']); ?></p>
                            <p><strong>РРјСЏ:</strong> <?php echo htmlspecialchars($order['first_name']); ?></p>
                            <p><strong>Р¤Р°РјРёР»РёСЏ:</strong> <?php echo htmlspecialchars($order['last_name']); ?></p>
                            <p><strong>РўРµР»РµС„РѕРЅ:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                            <p><strong>РђРґСЂРµСЃ РґРѕСЃС‚Р°РІРєРё:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                            <p><strong>Р”Р°С‚Р° Р·Р°РєР°Р·Р°:</strong> <?php echo htmlspecialchars($order['order_date']); ?></p>
                            <p><strong>РЎС‚Р°С‚СѓСЃ:</strong> <?php echo htmlspecialchars($order['status']); ?></p>
                            <h4>РўРѕРІР°СЂС‹:</h4>
                            <table class="order-table">
                                <tr>
                                    <th>РўРѕРІР°СЂ</th>
                                    <th>Р¦РµРЅР°</th>
                                    <th>РљРѕР»РёС‡РµСЃС‚РІРѕ</th>
                                    <th>РС‚РѕРіРѕ</th>
                                </tr>
                                <?php foreach (($order_items[$order['id']] ?? []) as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo number_format((float)$item['price'], 2, '.', ' '); ?> СЂСѓР±.</td>
                                        <td><?php echo (int)$item['quantity']; ?></td>
                                        <td><?php echo number_format((float)$item['price'] * (int)$item['quantity'], 2, '.', ' '); ?> СЂСѓР±.</td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<script>
// РћР±СЂР°Р±РѕС‚РєР° СЏРєРѕСЂРµР№ РїСЂРё Р·Р°РіСЂСѓР·РєРµ СЃС‚СЂР°РЅРёС†С‹
document.addEventListener('DOMContentLoaded', function() {
    // РџСЂРѕРєСЂСѓС‚РєР° Рє СЏРєРѕСЂСЋ РµСЃР»Рё РѕРЅ РµСЃС‚СЊ РІ URL
    if (window.location.hash) {
        setTimeout(function() {
            const element = document.querySelector(window.location.hash);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 100);
    }
    
    // РћР±РЅРѕРІР»РµРЅРёРµ РІСЃРµС… СЃСЃС‹Р»РѕРє РІ СЃР°Р№РґР±Р°СЂРµ РґР»СЏ СЃРѕС…СЂР°РЅРµРЅРёСЏ СЏРєРѕСЂРµР№
    const sidebarLinks = document.querySelectorAll('.sidebar a');
    sidebarLinks.forEach(link => {
        if (link.getAttribute('href').includes('#')) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const hash = this.getAttribute('href').split('#')[1];
                window.location.hash = hash;
                const element = document.getElementById(hash);
                if (element) {
                    element.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }
    });
    
    // РЎРѕС…СЂР°РЅРµРЅРёРµ СЏРєРѕСЂСЏ РїСЂРё РѕС‚РїСЂР°РІРєРµ С„РѕСЂРј
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const currentHash = window.location.hash;
            if (currentHash) {
                // Р”РѕР±Р°РІР»СЏРµРј СЃРєСЂС‹С‚РѕРµ РїРѕР»Рµ СЃ СЏРєРѕСЂРµРј
                let hashInput = form.querySelector('input[name="current_hash"]');
                if (!hashInput) {
                    hashInput = document.createElement('input');
                    hashInput.type = 'hidden';
                    hashInput.name = 'current_hash';
                    form.appendChild(hashInput);
                }
                hashInput.value = currentHash;
            }
        });
    });
});
</script>
</body>
</html>
