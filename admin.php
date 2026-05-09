<?php
ob_start();
session_start();

/* ===== Включить вывод ошибок (для локальной отладки) ===== */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/db.php';

/* ── Авто-миграция ───────────────────────────────────────────── */
try {
    $cols = $conn->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_ASSOC);
    $hasPriceUnit = false; $imageType = '';
    foreach ($cols as $c) {
        if (strcasecmp($c['Field'], 'price_unit') === 0) $hasPriceUnit = true;
        if (strcasecmp($c['Field'], 'image') === 0)      $imageType    = strtolower($c['Type']);
    }
    if (!$hasPriceUnit) {
        $conn->exec("ALTER TABLE products ADD COLUMN price_unit VARCHAR(20) NOT NULL DEFAULT 'шт.' AFTER price");
    }
    // Если image — не BLOB-тип, конвертируем в LONGBLOB
    if ($imageType !== '' && strpos($imageType, 'blob') === false) {
        $conn->exec("ALTER TABLE products MODIFY COLUMN image LONGBLOB");
    }
} catch (Throwable $e) { /* таблица ещё не создана — пропустить */ }

try {
    $cols = $conn->query("SHOW COLUMNS FROM news")->fetchAll(PDO::FETCH_ASSOC);
    $imageType = '';
    foreach ($cols as $c) {
        if (strcasecmp($c['Field'], 'image') === 0) $imageType = strtolower($c['Type']);
    }
    if ($imageType !== '' && strpos($imageType, 'blob') === false) {
        $conn->exec("ALTER TABLE news MODIFY COLUMN image LONGBLOB");
    }
} catch (Throwable $e) { /* игнор */ }

try {
    $cols = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (strcasecmp($c['Field'], 'status') === 0) {
            // Если ENUM или VARCHAR короче 50 — расширяем до VARCHAR(50)
            if (strpos(strtolower($c['Type']), 'enum') !== false
                || strtolower($c['Type']) === 'varchar(20)'
                || strtolower($c['Type']) === 'varchar(30)') {
                $conn->exec("ALTER TABLE orders MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Новый'");
            }
            break;
        }
    }
} catch (Throwable $e) { /* игнор */ }

/* =====================[ Авторизация ]====================== */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* =====================[ CSRF-токен ]======================= */
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

/* =====================[ Настройки загрузки ]=============== */
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_file_size = 10 * 1024 * 1024; // 10 МБ

/**
 * Загружает изображение из $_FILES[$field] и возвращает бинарные данные (BLOB) или null, если файл не выбран.
 * Проводит базовые проверки размера/типа. Работает и без расширения fileinfo (использует $_FILES['type'] как фолбэк).
 */
function loadUploadedImage(string $field, array $allowed_types, int $max_file_size, ?string &$err): ?string {
    if (empty($_FILES[$field]['name'])) return null;

    $f = $_FILES[$field];

    if ($f['error'] !== UPLOAD_ERR_OK) { $err = "Ошибка загрузки файла: " . (int)$f['error']; return null; }
    if ($f['size'] > $max_file_size)   { $err = "Размер изображения превышает 10 МБ"; return null; }

    // Попробуем надёжно узнать MIME через fileinfo
    $mime = null;
    if (class_exists('finfo')) {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($f['tmp_name']) ?: null;
    }
    // Фолбэк на $_FILES['type']
    if ($mime === null && !empty($f['type'])) {
        $mime = $f['type'];
    }
    if ($mime === null || !in_array($mime, $allowed_types, true)) {
        $err = "Недопустимый тип изображения";
        return null;
    }

    return file_get_contents($f['tmp_name']); // BLOB
}

/* =====================[ Прелоад для редактирования ]======= */
$edit_product = null;
$edit_news    = null;

/* Подтянуть товар для редактирования */
if (isset($_GET['edit_product'])) {
    $pid = (int)$_GET['edit_product'];
    if ($pid > 0) {
        $stmt = $conn->prepare("SELECT id, name, price, price_unit, description, image FROM products WHERE id = ?");
        $stmt->execute([$pid]);
        $edit_product = $stmt->fetch() ?: null;
        if (!$edit_product) {
            $error = "Товар #$pid не найден.";
        }
    }
}

/* Подтянуть новость для редактирования */
if (isset($_GET['edit_news'])) {
    $nid = (int)$_GET['edit_news'];
    if ($nid > 0) {
        $stmt = $conn->prepare("SELECT id, title, content, date, image FROM news WHERE id = ?");
        $stmt->execute([$nid]);
        $edit_news = $stmt->fetch() ?: null;
        if (!$edit_news) {
            $error = "Новость #$nid не найдена.";
        }
    }
}

/* =====================[ Обработка форм POST ]============== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        $error = "Неверный CSRF токен";
    } else {

        /* ---------- ТОВАРЫ: добавить / редактировать ---------- */
        if (isset($_POST['add_product']) || isset($_POST['edit_product'])) {
            $name        = trim($_POST['name'] ?? '');
            $priceStr    = trim((string)($_POST['price'] ?? ''));
            $allowed_units = ['кг', 'шт.', 'л', 'г', 'уп.', '100 г'];
            $price_unit  = trim($_POST['price_unit'] ?? 'шт.');
            if (!in_array($price_unit, $allowed_units, true)) { $price_unit = 'шт.'; }
            $description = trim($_POST['description'] ?? '');
            $imgErr      = null;
            $new_image   = loadUploadedImage('image', $allowed_types, $max_file_size, $imgErr);

            if ($name === '' || $description === '') {
                $error = "Заполните все обязательные поля (Название и Описание).";
            } elseif ($priceStr === '' || !is_numeric($priceStr) || (float)$priceStr < 0) {
                $error = "Цена должна быть числом, не меньше 0.";
            } elseif (isset($_POST['add_product']) && $new_image === null) {
                $error = $imgErr ?: "Пожалуйста, загрузите изображение товара.";
            }

            if (!isset($error)) {
                try {
                    if (isset($_POST['add_product'])) {
                        $stmt = $conn->prepare("INSERT INTO products (name, price, price_unit, description, image) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$name, (float)$priceStr, $price_unit, $description, $new_image]);
                        $success = "Продукт успешно добавлен!";
                        header("Location: admin.php#products");
                        exit;
                    } else {
                        // edit_product
                        $id = (int)($_POST['id'] ?? 0);
                        if ($id <= 0) {
                            throw new RuntimeException("Некорректный идентификатор товара.");
                        }
                        if ($new_image === null) {
                            $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
                            $stmt->execute([$id]);
                            $new_image = $stmt->fetchColumn();
                        }
                        $stmt = $conn->prepare("UPDATE products SET name = ?, price = ?, price_unit = ?, description = ?, image = ? WHERE id = ?");
                        $stmt->execute([$name, (float)$priceStr, $price_unit, $description, $new_image, $id]);
                        $success = "Продукт успешно обновлён!";
                        header("Location: admin.php#products");
                        exit;
                    }
                } catch (Throwable $e) {
                    $error = "Ошибка сохранения товара: " . $e->getMessage();
                    // Вернуть данные в форму
                    if (isset($_POST['edit_product'])) {
                        $pid = (int)($_POST['id'] ?? 0);
                        if ($pid > 0) {
                            $stmt = $conn->prepare("SELECT id, name, price, price_unit, description, image FROM products WHERE id = ?");
                            $stmt->execute([$pid]);
                            $edit_product = $stmt->fetch() ?: null;
                        }
                    }
                }
            } else {
                // Если была ошибка — подтянем запись для редактирования (если это edit)
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

        /* ---------- НОВОСТИ: добавить / редактировать ---------- */
        if (isset($_POST['add_news']) || isset($_POST['edit_news'])) {
            $title   = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $date    = date('Y-m-d');
            $imgErr  = null;
            $new_image = loadUploadedImage('image', $allowed_types, $max_file_size, $imgErr);

            if ($title === '' || $content === '') {
                $error = "Заполните поля Заголовок и Текст.";
            }

            if (!isset($error)) {
                if (isset($_POST['add_news'])) {
                    try {
                        $stmt = $conn->prepare("INSERT INTO news (title, content, date, image) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$title, $content, $date, $new_image]);
                        $success = "Новость успешно добавлена!";
                        header("Location: admin.php#news");
                        exit;
                    } catch (Throwable $e) {
                        $error = "Ошибка добавления новости: " . $e->getMessage();
                    }
                } else {
                    // edit_news
                    try {
                        $id = (int)($_POST['id'] ?? 0);
                        if ($id <= 0) {
                            throw new RuntimeException("Некорректный идентификатор новости.");
                        }
                        if ($new_image === null) {
                            $stmt = $conn->prepare("SELECT image FROM news WHERE id = ?");
                            $stmt->execute([$id]);
                            $new_image = $stmt->fetchColumn();
                        }
                        $stmt = $conn->prepare("UPDATE news SET title = ?, content = ?, image = ? WHERE id = ?");
                        $stmt->execute([$title, $content, $new_image, $id]);
                        $success = "Новость успешно обновлена!";
                        header("Location: admin.php#news");
                        exit;
                    } catch (Throwable $e) {
                        $error = "Ошибка обновления новости: " . $e->getMessage();
                        // Подтянем запись обратно в форму
                        $nid = (int)($_POST['id'] ?? 0);
                        if ($nid > 0) {
                            $stmt = $conn->prepare("SELECT id, title, content, date, image FROM news WHERE id = ?");
                            $stmt->execute([$nid]);
                            $edit_news = $stmt->fetch() ?: null;
                        }
                    }
                }
            } else {
                // Если была ошибка — подтянем запись для редактирования (если это edit)
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

        /* ---------- ЗАКАЗЫ: изменить статус ---------- */
        if (isset($_POST['update_order_status'])) {
            $order_id  = (int)($_POST['order_id'] ?? 0);
            $allowed_statuses = ['Новый', 'В обработке', 'Доставляется', 'Выполнен', 'Отменён'];
            $new_status = trim($_POST['order_status'] ?? '');
            if ($order_id <= 0 || !in_array($new_status, $allowed_statuses, true)) {
                $error = "Некорректные данные заказа.";
            } else {
                try {
                    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $order_id]);
                    // Redirect только при успехе
                    header("Location: admin.php#orders");
                    exit;
                } catch (Throwable $e) {
                    $error = "Ошибка обновления статуса: " . $e->getMessage();
                }
            }
        }

        /* ---------- ОТЗЫВЫ: одобрить / удалить ---------- */
        if (isset($_POST['approve_review']) || isset($_POST['delete_review'])) {
            $review_id = (int)($_POST['review_id'] ?? 0);

            // гарантируем наличие поля approved (если когда-то таблица была без него)
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
                // если reviews ещё нет — ничего не делаем
            }

            try {
                if (isset($_POST['approve_review'])) {
                    $stmt = $conn->prepare("UPDATE reviews SET approved = 1 WHERE id = ?");
                    $stmt->execute([$review_id]);
                    $success = "Отзыв #$review_id одобрен.";
                } else {
                    $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
                    $stmt->execute([$review_id]);
                    $success = "Отзыв #$review_id удалён.";
                }
                header("Location: admin.php#reviews");
                exit;
            } catch (Throwable $e) {
                $error = "Ошибка обработки отзыва: " . $e->getMessage();
            }
        }
    }
}

/* =====================[ Удаление по GET ]================== */
if (isset($_GET['delete_product'])) {
    $product_id = (int)$_GET['delete_product'];
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) {
            $error = "Нельзя удалить продукт, так как он используется в заказах!";
        } else {
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $success = "Продукт успешно удалён!";
        }
    } catch (Throwable $e) {
        $error = "Ошибка удаления продукта: " . $e->getMessage();
    }
}
if (isset($_GET['delete_news'])) {
    $news_id = (int)$_GET['delete_news'];
    try {
        $stmt = $conn->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$news_id]);
        $success = "Новость успешно удалена!";
    } catch (Throwable $e) {
        $error = "Ошибка удаления новости: " . $e->getMessage();
    }
}
if (isset($_GET['delete_order'])) {
    $order_id = (int)$_GET['delete_order'];
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $conn->commit();
        $success = "Заказ #$order_id успешно удалён!";
    } catch (Throwable $e) {
        $conn->rollBack();
        $error = "Ошибка удаления заказа: " . $e->getMessage();
    }
}

/* =====================[ Данные для экрана ]================ */
// Продукты
$products = [];
try {
    $stmt = $conn->query("SELECT id, name, price, price_unit, description, image FROM products ORDER BY id DESC");
    $products = $stmt->fetchAll();
} catch (Throwable $e) { /* игнор для списка */ }

// Новости
$news = [];
try {
    $stmt = $conn->query("SELECT id, title, content, date, image FROM news ORDER BY date DESC, id DESC");
    $news = $stmt->fetchAll();
} catch (Throwable $e) { /* игнор */ }

// Заказы
$orders = [];
try {
    $stmt = $conn->query("
        SELECT o.id, o.order_date, o.first_name, o.last_name, o.phone, o.shipping_address, o.status
        FROM orders o
        ORDER BY o.order_date DESC, o.id DESC
    ");
    $orders = $stmt->fetchAll();
} catch (Throwable $e) { /* игнор */ }

// Позиции заказов
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

/* =====================[ Утилита: звёзды ]================== */
function stars($n){ $n = (int)$n; $n = max(0, min(5,$n)); return str_repeat('★',$n) . str_repeat('☆', 5-$n); }

function getImageSrc($blob) {
    if (empty($blob)) return '';
    if (is_resource($blob)) return '';
    return 'data:image/jpeg;base64,' . base64_encode((string)$blob);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
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
        .success { color: green; font-weight: bold; position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1000; background: #e8f5e9; border: 2px solid #4caf50; padding: 15px 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-size: 16px; }
        .error { color: red; font-weight: bold; position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1000; background: #ffebee; border: 2px solid #f44336; padding: 15px 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-size: 16px; }
        .products-section, .news-section, .orders-section { margin-top: 20px; }
        .order-card, .product-card, .news-card { border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
        .order-table, .products-table, .news-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .order-table th, .order-table td, .products-table th, .products-table td, .news-table th, .news-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .order-table th, .products-table th, .news-table th { background-color: #f4f4f4; }
        .btn-edit, .btn-delete { display: inline-block; padding: 5px 10px; color: white; text-decoration: none; border-radius: 3px; margin-right: 5px; border: none; cursor: pointer; font-size: 14px; }
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
        .status-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;}
        .status-new{background:#e8f4ff;color:#1565c0;border:1px solid #90caf9}
        .status-process{background:#fff8e1;color:#e65100;border:1px solid #ffcc02}
        .status-delivery{background:#e3f2fd;color:#0277bd;border:1px solid #81d4fa}
        .status-done{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7}
        .status-cancel{background:#fce4ec;color:#b71c1c;border:1px solid #f48fb1}
        .order-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px;}
        .order-actions select{padding:6px 10px;border-radius:4px;border:1px solid #ccc;font-size:14px;}
        .order-actions .btn-status{padding:6px 14px;background:#007bff;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;}
        .order-actions .btn-status:hover{background:#0056b3}
        .order-actions .btn-del-order{padding:6px 14px;background:#dc3545;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;}
        .order-actions .btn-del-order:hover{background:#c82333}
        .modal{display:none;position:fixed;z-index:999;left:0;top:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5)}
        .modal-content{background-color:#fefefe;margin:5% auto;padding:20px;border-radius:8px;width:90%;max-width:500px;box-shadow:0 4px 20px rgba(0,0,0,0.3)}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .modal-header h3{margin:0}
        .close{color:#aaa;font-size:28px;font-weight:bold;cursor:pointer}
        .close:hover{color:#000}
        .modal-image{max-width:100%;max-height:200px;border-radius:4px;margin-bottom:15px}
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Админка</h2>
        <a href="#products"><i class="fas fa-box"></i> Продукты</a>
        <a href="#news"><i class="fas fa-newspaper"></i> Новости</a>
        <a href="#reviews"><i class="fas fa-comments"></i> Отзывы</a>
        <a href="#orders"><i class="fas fa-shopping-cart"></i> Заказы</a>
        <a href="admin.php?logout=true" class="btn logout"><i class="fas fa-sign-out-alt"></i> Выйти</a>
    </div>

    <!-- Основная часть -->
    <div class="main-content">
        <div class="admin-header">
            <h1>Панель управления</h1>
        </div>

        <div class="section">
            <?php if (!empty($success)) echo "<p class='success'><i class='fas fa-check-circle'></i> ".htmlspecialchars($success)."</p>"; ?>
            <?php if (!empty($error))   echo "<p class='error'><i class='fas fa-exclamation-circle'></i> ".htmlspecialchars($error)."</p>"; ?>

            <!-- Продукты -->
            <h2 id="products">Продукты</h2>
            <button class="btn" onclick="openProductModal()"><i class="fas fa-plus"></i> Добавить продукт</button>
            
            <div id="productModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="productModalTitle">Добавить продукт</h3>
                        <span class="close" onclick="closeProductModal()">&times;</span>
                    </div>
                    <form method="POST" enctype="multipart/form-data" id="productForm">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="id" id="productId" value="">
                        <input type="hidden" name="current_hash" value="#products">

                        <div id="currentProductImage" style="display:none;">
                            <p>Текущее изображение:</p>
                            <img id="productImagePreview" class="modal-image" src="" alt="Текущее изображение">
                        </div>

                        <div class="input-group">
                            <i class="fas fa-box"></i>
                            <input type="text" name="name" id="productName" placeholder="Название продукта" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-ruble-sign"></i>
                            <input type="number" name="price" id="productPrice" placeholder="Цена" step="0.01" min="0" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-tag"></i>
                            <select name="price_unit" id="productPriceUnit" style="padding:10px;width:100%">
                                <?php
                                $units = ['кг', 'шт.', 'л', 'г', 'уп.', '100 г'];
                                foreach ($units as $u):
                                ?>
                                    <option value="<?php echo htmlspecialchars($u); ?>">Цена за <?php echo htmlspecialchars($u); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-file-alt"></i>
                            <textarea name="description" id="productDescription" placeholder="Описание" required></textarea>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-image"></i>
                            <input type="file" name="image" id="productImage" accept="image/jpeg,image/png,image/gif">
                            <div class="muted" style="margin-left:8px">JPEG/PNG/GIF, до 10 МБ</div>
                        </div>

                        <button type="submit" name="add_product" id="productSubmitBtn" class="btn">
                            <i class="fas fa-plus"></i> Добавить продукт
                        </button>
                    </form>
                </div>
            </div>

            <h2>Список продуктов</h2>
            <div class="products-section">
                <?php if (empty($products)): ?>
                    <p>Продуктов пока нет</p>
                <?php else: ?>
                    <table class="products-table">
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Цена</th>
                            <th>Описание</th>
                            <th>Изображение</th>
                            <th>Действия</th>
                        </tr>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo (int)$product['id']; ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo number_format((float)$product['price'], 2, '.', ' '); ?> руб./<?php echo htmlspecialchars($product['price_unit'] ?? 'шт.'); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($product['description'])); ?></td>
                                <td>
                                    <?php $imgSrc = getImageSrc($product['image']); if ($imgSrc): ?>
                                        <img src="<?php echo $imgSrc; ?>" class="product-image" alt="Изображение">
                                    <?php else: ?>
                                        Нет изображения
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn-edit" onclick="openProductModal(<?php echo (int)$product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>', '<?php echo addslashes($product['price']); ?>', '<?php echo addslashes($product['price_unit'] ?? 'шт.'); ?>', '<?php echo addslashes(htmlspecialchars($product['description'])); ?>', '<?php echo !empty($product['image']) ? addslashes('data:image/jpeg;base64,' . base64_encode($product['image'])) : ''; ?>')"><i class="fas fa-edit"></i> Редактировать</button>
                                    <a href="admin.php?delete_product=<?php echo (int)$product['id']; ?>#products" class="btn-delete" onclick="return confirm('Удалить продукт #<?php echo (int)$product['id']; ?>?');"><i class="fas fa-trash"></i> Удалить</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Новости -->
            <h2 id="news">Новости и акции</h2>
            <button class="btn" onclick="openNewsModal()"><i class="fas fa-plus"></i> Добавить новость</button>
            
            <div id="newsModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="newsModalTitle">Добавить новость</h3>
                        <span class="close" onclick="closeNewsModal()">&times;</span>
                    </div>
                    <form method="POST" enctype="multipart/form-data" id="newsForm">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="id" id="newsId" value="">
                        <input type="hidden" name="current_hash" value="#news">

                        <div id="currentNewsImage" style="display:none;">
                            <p>Текущее изображение:</p>
                            <img id="newsImagePreview" class="modal-image" src="" alt="Текущее изображение">
                        </div>

                        <div class="input-group">
                            <i class="fas fa-newspaper"></i>
                            <input type="text" name="title" id="newsTitle" placeholder="Заголовок" required>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-file-alt"></i>
                            <textarea name="content" id="newsContent" placeholder="Текст новости/акции" required></textarea>
                        </div>
                        <div class="input-group">
                            <i class="fas fa-image"></i>
                            <input type="file" name="image" id="newsImage" accept="image/jpeg,image/png,image/gif">
                            <div class="muted" style="margin-left:8px">JPEG/PNG/GIF, до 10 МБ</div>
                        </div>

                        <button type="submit" name="add_news" id="newsSubmitBtn" class="btn">
                            <i class="fas fa-plus"></i> Добавить новость
                        </button>
                    </form>
                </div>
            </div>

            <h2>Список новостей/акций</h2>
            <div class="news-section">
                <?php if (empty($news)): ?>
                    <p>Новостей пока нет</p>
                <?php else: ?>
                    <table class="news-table">
                        <tr>
                            <th>ID</th>
                            <th>Заголовок</th>
                            <th>Текст</th>
                            <th>Дата</th>
                            <th>Изображение</th>
                            <th>Действия</th>
                        </tr>
                        <?php foreach ($news as $item): ?>
                            <tr>
                                <td><?php echo (int)$item['id']; ?></td>
                                <td><?php echo htmlspecialchars($item['title']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($item['content'])); ?></td>
                                <td><?php echo htmlspecialchars($item['date']); ?></td>
                                <td>
                                    <?php $imgSrc = getImageSrc($item['image']); if ($imgSrc): ?>
                                        <img src="<?php echo $imgSrc; ?>" class="news-image" alt="Изображение">
                                    <?php else: ?>
                                        Нет изображения
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn-edit" onclick="openNewsModal(<?php echo (int)$item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['title'])); ?>', '<?php echo addslashes(htmlspecialchars($item['content'])); ?>', '<?php echo !empty($item['image']) ? addslashes('data:image/jpeg;base64,' . base64_encode($item['image'])) : ''; ?>')"><i class="fas fa-edit"></i> Редактировать</button>
                                    <a href="admin.php?delete_news=<?php echo (int)$item['id']; ?>#news" class="btn-delete" onclick="return confirm('Удалить новость #<?php echo (int)$item['id']; ?>?');"><i class="fas fa-trash"></i> Удалить</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <!-- ОТЗЫВЫ -->
            <h2 id="reviews">Отзывы</h2>

            <div class="form-container" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div>
                    <a class="badge <?php echo ($rv_filter==='pending')?'wait':''; ?>" href="admin.php?rv_filter=pending#reviews">На модерации</a>
                    <a class="badge <?php echo ($rv_filter==='approved')?'ok':''; ?>" href="admin.php?rv_filter=approved#reviews">Одобренные</a>
                    <a class="badge" href="admin.php?rv_filter=all#reviews">Все</a>
                </div>
                <div class="muted">Всего: <?php echo (int)($total_reviews ?? 0); ?></div>
            </div>

            <div class="products-section">
                <?php
                // Пагинация отзывов (переменные могли не инициализироваться, поэтому дублируем безопасные значения)
                $rv_filter = $_GET['rv_filter'] ?? 'pending';
                $rv_page   = max(1, (int)($_GET['rv_page'] ?? 1));
                $rv_limit  = 10;
                $rv_offset = ($rv_page - 1) * $rv_limit;

                // Получим отзывы для вывода (ещё раз, чтобы точно были при прямом заходе)
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
                    <p>Отзывов нет.</p>
                <?php else: ?>
                    <table class="rv-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Имя</th>
                                <th>Оценка</th>
                                <th>Текст</th>
                                <th>Статус</th>
                                <th>Дата</th>
                                <th>Действия</th>
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
                                            echo $approved ? '<span class="badge ok">Одобрен</span>' : '<span class="badge wait">На модерации</span>';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at']))); ?></td>
                                    <td class="rv-actions">
                                        <?php if (empty($r['approved'])): ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="review_id" value="<?php echo (int)$r['id']; ?>">
                                            <button class="btn primary" type="submit" name="approve_review">Одобрить</button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="post" style="display:inline" onsubmit="return confirm('Удалить отзыв #<?php echo (int)$r['id']; ?>?')">
                                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="review_id" value="<?php echo (int)$r['id']; ?>">
                                            <button class="btn secondary" type="submit" name="delete_review">Удалить</button>
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
                            <a class="btn secondary" href="admin.php?rv_filter=<?php echo urlencode($rv_filter); ?>&rv_page=<?php echo $p; ?>#reviews">Назад</a>
                        <?php endif; ?>
                        <span class="muted">Стр. <?php echo $rv_page; ?> из <?php echo $rv_pages; ?></span>
                        <?php if ($rv_page < $rv_pages): $n = $rv_page + 1; ?>
                            <a class="btn secondary" href="admin.php?rv_filter=<?php echo urlencode($rv_filter); ?>&rv_page=<?php echo $n; ?>#reviews">Вперёд</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Заказы -->
            <h2 id="orders">Заказы</h2>
            <div class="orders-section">
                <?php if (empty($orders)): ?>
                    <p>Заказов пока нет</p>
                <?php else: ?>
                    <?php
                    $status_class_map = [
                        'Новый'        => 'status-new',
                        'В обработке'  => 'status-process',
                        'Доставляется' => 'status-delivery',
                        'Выполнен'     => 'status-done',
                        'Отменён'      => 'status-cancel',
                    ];
                    $all_statuses = ['Новый', 'В обработке', 'Доставляется', 'Выполнен', 'Отменён'];
                    ?>
                    <?php foreach ($orders as $order):
                        $cur_status = $order['status'] ?? 'Новый';
                        $badge_cls  = $status_class_map[$cur_status] ?? 'status-new';
                    ?>
                        <div class="order-card">
                            <h3>
                                Заказ #<?php echo (int)$order['id']; ?>
                                <span class="status-badge <?php echo $badge_cls; ?>"><?php echo htmlspecialchars($cur_status); ?></span>
                            </h3>
                            <p><strong>Имя:</strong> <?php echo htmlspecialchars($order['first_name']); ?></p>
                            <p><strong>Фамилия:</strong> <?php echo htmlspecialchars($order['last_name']); ?></p>
                            <p><strong>Телефон:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                            <p><strong>Адрес доставки:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                            <p><strong>Дата заказа:</strong> <?php echo htmlspecialchars($order['order_date']); ?></p>
                            <h4>Товары:</h4>
                            <table class="order-table">
                                <tr>
                                    <th>Товар</th>
                                    <th>Цена</th>
                                    <th>Количество</th>
                                    <th>Итого</th>
                                </tr>
                                <?php foreach (($order_items[$order['id']] ?? []) as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo number_format((float)$item['price'], 2, '.', ' '); ?> руб.</td>
                                        <td><?php echo (int)$item['quantity']; ?></td>
                                        <td><?php echo number_format((float)$item['price'] * (int)$item['quantity'], 2, '.', ' '); ?> руб.</td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>

                            <!-- Действия с заказом -->
                            <div class="order-actions">
                                <form method="POST" style="display:flex;align-items:center;gap:8px;">
                                    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                    <select name="order_status">
                                        <?php foreach ($all_statuses as $st): ?>
                                            <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($cur_status === $st) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($st); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="update_order_status" class="btn-status">
                                        <i class="fas fa-sync-alt"></i> Изменить статус
                                    </button>
                                </form>
                                <a href="admin.php?delete_order=<?php echo (int)$order['id']; ?>#orders"
                                   class="btn-del-order"
                                   onclick="return confirm('Удалить заказ #<?php echo (int)$order['id']; ?> и все его позиции?');">
                                    <i class="fas fa-trash"></i> Удалить заказ
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<script>
// Обработка уведомлений - автоматическое скрытие
document.addEventListener('DOMContentLoaded', function() {
    const successMsg = document.querySelector('.success');
    const errorMsg = document.querySelector('.error');
    
    if (successMsg) {
        setTimeout(() => {
            successMsg.style.transition = 'opacity 0.5s';
            successMsg.style.opacity = '0';
            setTimeout(() => successMsg.remove(), 500);
        }, 4000);
    }
    
    if (errorMsg) {
        setTimeout(() => {
            errorMsg.style.transition = 'opacity 0.5s';
            errorMsg.style.opacity = '0';
            setTimeout(() => errorMsg.remove(), 500);
        }, 6000);
    }

    // Продукты - открыть модалку при редактировании
    <?php if ($edit_product): ?>
    openProductModal(<?php echo (int)$edit_product['id']; ?>, <?php echo json_encode(htmlspecialchars($edit_product['name'], ENT_QUOTES, 'UTF-8')); ?>, <?php echo json_encode($edit_product['price']); ?>, <?php echo json_encode(htmlspecialchars($edit_product['price_unit'] ?? 'шт.', ENT_QUOTES, 'UTF-8')); ?>, <?php echo json_encode(htmlspecialchars($edit_product['description'], ENT_QUOTES, 'UTF-8')); ?>, "<?php echo !empty($edit_product['image']) ? 'data:image/jpeg;base64,' . base64_encode($edit_product['image']) : ''; ?>");
    <?php endif; ?>

    // Новости - открыть модалку при редактировании
    <?php if ($edit_news): ?>
    openNewsModal(<?php echo (int)$edit_news['id']; ?>, <?php echo json_encode(htmlspecialchars($edit_news['title'], ENT_QUOTES, 'UTF-8')); ?>, <?php echo json_encode(htmlspecialchars($edit_news['content'], ENT_QUOTES, 'UTF-8')); ?>, "<?php echo !empty($edit_news['image']) ? 'data:image/jpeg;base64,' . base64_encode($edit_news['image']) : ''; ?>");
    <?php endif; ?>
});

// Модальное окно товара
function openProductModal(id, name, price, priceUnit, description, image) {
    const modal = document.getElementById('productModal');
    const title = document.getElementById('productModalTitle');
    const form = document.getElementById('productForm');
    const submitBtn = document.getElementById('productSubmitBtn');
    const imageDiv = document.getElementById('currentProductImage');
    const imagePreview = document.getElementById('productImagePreview');
    const imageInput = document.getElementById('productImage');

    if (id) {
        title.textContent = 'Редактировать продукт';
        document.getElementById('productId').value = id;
        document.getElementById('productName').value = name;
        document.getElementById('productPrice').value = price;
        document.getElementById('productPriceUnit').value = priceUnit;
        document.getElementById('productDescription').value = description;
        submitBtn.name = 'edit_product';
        submitBtn.innerHTML = '<i class="fas fa-edit"></i> Сохранить изменения';
        imageInput.required = false;
        if (image) {
            imagePreview.src = image;
            imageDiv.style.display = 'block';
        } else {
            imageDiv.style.display = 'none';
        }
    } else {
        title.textContent = 'Добавить продукт';
        document.getElementById('productId').value = '';
        document.getElementById('productName').value = '';
        document.getElementById('productPrice').value = '';
        document.getElementById('productPriceUnit').value = 'шт.';
        document.getElementById('productDescription').value = '';
        submitBtn.name = 'add_product';
        submitBtn.innerHTML = '<i class="fas fa-plus"></i> Добавить продукт';
        imageInput.required = true;
        imageDiv.style.display = 'none';
    }
    modal.style.display = 'block';
}

function closeProductModal() {
    document.getElementById('productModal').style.display = 'none';
    window.location.hash = 'products';
}

// Модальное окно новости
function openNewsModal(id, title, content, image) {
    const modal = document.getElementById('newsModal');
    const modalTitle = document.getElementById('newsModalTitle');
    const form = document.getElementById('newsForm');
    const submitBtn = document.getElementById('newsSubmitBtn');
    const imageDiv = document.getElementById('currentNewsImage');
    const imagePreview = document.getElementById('newsImagePreview');
    const imageInput = document.getElementById('newsImage');

    if (id) {
        modalTitle.textContent = 'Редактировать новость';
        document.getElementById('newsId').value = id;
        document.getElementById('newsTitle').value = title;
        document.getElementById('newsContent').value = content;
        submitBtn.name = 'edit_news';
        submitBtn.innerHTML = '<i class="fas fa-edit"></i> Сохранить изменения';
        imageInput.required = false;
        if (image) {
            imagePreview.src = image;
            imageDiv.style.display = 'block';
        } else {
            imageDiv.style.display = 'none';
        }
    } else {
        modalTitle.textContent = 'Добавить новость';
        document.getElementById('newsId').value = '';
        document.getElementById('newsTitle').value = '';
        document.getElementById('newsContent').value = '';
        submitBtn.name = 'add_news';
        submitBtn.innerHTML = '<i class="fas fa-plus"></i> Добавить новость';
        imageInput.required = false;
        imageDiv.style.display = 'none';
    }
    modal.style.display = 'block';
}

function closeNewsModal() {
    document.getElementById('newsModal').style.display = 'none';
    window.location.hash = 'news';
}

// Закрытие модальных окон при клике вне
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Обработка якорей при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Прокрутка к якорю если он есть в URL
    if (window.location.hash) {
        setTimeout(function() {
            const element = document.querySelector(window.location.hash);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 100);
    }
    
    // Обновление всех ссылок в сайдбаре для сохранения якорей
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
    
    // Сохранение якоря при отправке форм
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const currentHash = window.location.hash;
            if (currentHash) {
                // Добавляем скрытое поле с якорем
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
