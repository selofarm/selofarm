<?php
session_start();
require_once __DIR__ . '/db.php';

// Обработка добавления в корзину
if (isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if ($product) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (isset($_SESSION['cart'][$product_id])) {
            if ($_SESSION['cart'][$product_id]['quantity'] >= 99) {
                $_SESSION['cart_limit_error'] = true;
            } else {
                $_SESSION['cart'][$product_id]['quantity']++;
            }
        } else {
            $_SESSION['cart'][$product_id] = [
                'id' => (int)$product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'image' => $product['image'],
                'quantity' => 1
            ];
        }
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?></title>
    <link rel="stylesheet" href="css/product.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="section">
        <?php if (!empty($_SESSION['cart_limit_error'])): unset($_SESSION['cart_limit_error']); ?>
            <p style="color:#c00;font-weight:bold;margin-bottom:12px">
                <i class="fas fa-exclamation-circle"></i>
                Максимальное количество одного товара — 99 шт. Для заказа большего количества свяжитесь с менеджером.
            </p>
        <?php endif; ?>
        <div class="product-details">
    <?php if (!empty($product['image'])): ?>
        <?php if (strlen($product['image']) > 200): ?> 
            <!-- Скорее всего это BLOB -->
            <?php $d=$product['image']; if(substr($d,0,3)==="\xFF\xD8\xFF") $m='image/jpeg'; elseif(substr($d,0,4)==="\x89PNG") $m='image/png'; elseif(substr($d,0,3)==='GIF') $m='image/gif'; else $m='image/jpeg'; ?>
            <img src="data:<?= $m ?>;base64,<?= base64_encode($d) ?>" alt="product" class="product-img">
        <?php else: ?> 
            <!-- Скорее всего это путь -->
            <img src="<?= htmlspecialchars($product['image']) ?>" alt="product" class="product-img">
        <?php endif; ?>
    <?php else: ?>
        <!-- Заглушка если картинки нет -->
        <img src="img/no-image.jpg" alt="Нет изображения" class="product-img">
    <?php endif; ?>

    <div class="product-info">
        <h1><?= htmlspecialchars($product['name']) ?></h1>
        <p class="price">Цена: <?= htmlspecialchars(number_format((float)$product['price'], 2, '.', ' ')) ?> руб./<?= htmlspecialchars($product['price_unit'] ?? 'шт.') ?></p>
        <p><?= htmlspecialchars($product['description']) ?></p>
        <form method="POST">
            <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
            <button type="submit" name="add_to_cart" class="btn add-to-cart">
                <i class="fas fa-cart-plus"></i> Добавить в корзину
            </button>
        </form>
        <a href="catalog.php" class="btn back-btn">
            <i class="fas fa-arrow-left"></i> Назад к каталогу
        </a>
    </div>
</div>

    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
