<?php
session_start();
require_once __DIR__ . '/db.php';

// РћР±СЂР°Р±РѕС‚РєР° РґРѕР±Р°РІР»РµРЅРёСЏ РІ РєРѕСЂР·РёРЅСѓ
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
            $_SESSION['cart'][$product_id]['quantity']++;
        } else {
            $_SESSION['cart'][$product_id] = [
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
        <div class="product-details">
    <?php if (!empty($product['image'])): ?>
        <?php if (strlen($product['image']) > 200): ?> 
            <!-- РЎРєРѕСЂРµРµ РІСЃРµРіРѕ СЌС‚Рѕ BLOB -->
            <img src="data:image/jpeg;base64,<?= base64_encode($product['image']) ?>" alt="product" class="product-img">
        <?php else: ?> 
            <!-- РЎРєРѕСЂРµРµ РІСЃРµРіРѕ СЌС‚Рѕ РїСѓС‚СЊ -->
            <img src="<?= htmlspecialchars($product['image']) ?>" alt="product" class="product-img">
        <?php endif; ?>
    <?php else: ?>
        <!-- Р—Р°РіР»СѓС€РєР° РµСЃР»Рё РєР°СЂС‚РёРЅРєРё РЅРµС‚ -->
        <img src="img/no-image.jpg" alt="РќРµС‚ РёР·РѕР±СЂР°Р¶РµРЅРёСЏ" class="product-img">
    <?php endif; ?>

    <div class="product-info">
        <h1><?= htmlspecialchars($product['name']) ?></h1>
        <p class="price">Р¦РµРЅР°: <?= htmlspecialchars($product['price']) ?> СЂСѓР±.</p>
        <p><?= htmlspecialchars($product['description']) ?></p>
        <form method="POST">
            <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
            <button type="submit" name="add_to_cart" class="btn add-to-cart">
                <i class="fas fa-cart-plus"></i> Р”РѕР±Р°РІРёС‚СЊ РІ РєРѕСЂР·РёРЅСѓ
            </button>
        </form>
        <a href="catalog.php" class="btn back-btn">
            <i class="fas fa-arrow-left"></i> РќР°Р·Р°Рґ Рє РєР°С‚Р°Р»РѕРіСѓ
        </a>
    </div>
</div>

    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
