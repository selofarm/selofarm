<?php
session_start();
require_once __DIR__ . '/db.php';

/* =====================[ BASE_URL Рё Р°СЃСЃРµС‚С‹ ]================ */
$BASE_URL = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); // '' РёР»Рё '/selo'
function asset_url(string $path): string {
    global $BASE_URL;
    return $BASE_URL . '/' . ltrim($path, '/');
}

/* =====================[ РҐРµР»РїРµСЂ: РєРѕСЂСЂРµРєС‚РЅС‹Р№ src РґР»СЏ img ]=== */
function image_src($image): string {
    if (is_string($image) && (str_starts_with($image, 'data:') || str_starts_with($image, 'http'))) {
        return $image;
    }
    $looksLikePath = is_string($image) && preg_match('~\.(jpe?g|png|gif|webp)$~i', $image);
    $hasBinary     = is_string($image) && preg_match('~[^\x09\x0A\x0D\x20-\x7E]~', $image);

    if ($image && !$looksLikePath && $hasBinary) {
        return 'data:image/jpeg;base64,' . base64_encode($image);
    }
    if ($looksLikePath) {
        global $BASE_URL;
        $path = '/' . ltrim((string)$image, '/');
        if (!str_starts_with($path, $BASE_URL . '/')) {
            if (!str_starts_with($path, '/images/')) {
                $path = asset_url('images/' . ltrim($path, '/'));
            } else {
                $path = $BASE_URL . $path;
            }
        }
        $abs = $_SERVER['DOCUMENT_ROOT'] . $path;
        if (!is_file($abs)) {
            return asset_url('images/no-image.jpg');
        }
        return $path;
    }
    return asset_url('images/no-image.jpg');
}

/* =====================[ РРЅРёС†РёР°Р»РёР·Р°С†РёСЏ РєРѕСЂР·РёРЅС‹ ]============ */
if (!isset($_SESSION['cart'])) {
    // cart[product_id] = ['id','name','price','image','quantity']
    $_SESSION['cart'] = [];
}

/* =====================[ РЈРґР°Р»РµРЅРёРµ РїРѕР·РёС†РёРё ]================= */
if (isset($_POST['remove_item'])) {
    $pid = (int)($_POST['product_id'] ?? 0);
    if (isset($_SESSION['cart'][$pid])) {
        unset($_SESSION['cart'][$pid]);
        $success = "РџРѕР·РёС†РёСЏ СѓРґР°Р»РµРЅР° РёР· РєРѕСЂР·РёРЅС‹.";
    }
}

/* =====================[ РћР±РЅРѕРІР»РµРЅРёРµ РєРѕР»РёС‡РµСЃС‚РІР° ]============ */
if (isset($_POST['update_qty'])) {
    foreach ((array)($_POST['qty'] ?? []) as $pid => $qty) {
        $pid = (int)$pid;
        $q   = max(1, (int)$qty);
        if (isset($_SESSION['cart'][$pid])) {
            $_SESSION['cart'][$pid]['quantity'] = $q;
        }
    }
    $success = "РљРѕР»РёС‡РµСЃС‚РІРѕ РѕР±РЅРѕРІР»РµРЅРѕ.";
}

/* =====================[ РћС„РѕСЂРјР»РµРЅРёРµ Р·Р°РєР°Р·Р° ]================
   РЈ РІР°СЃ РІ orders:
     - РµСЃС‚СЊ РѕР±СЏР·Р°С‚РµР»СЊРЅРѕРµ РїРѕР»Рµ user_id (Р±РµР· default),
     - СЃС‚Р°С‚СѓСЃ ENUM('Open','Processed','Completed','Canceled').

   Р РµС€РµРЅРёРµ:
     - Р±РµСЂС‘Рј user_id РёР· $_SESSION['user_id'] РёР»Рё СЃС‚Р°РІРёРј 0 (РіРѕСЃС‚СЊ),
     - СЃС‚Р°С‚СѓСЃ РїРёС€РµРј 'Open'.
*/
if (isset($_POST['checkout'])) {
    $first_name       = trim($_POST['first_name'] ?? '');
    $last_name        = trim($_POST['last_name'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $shipping_address = trim($_POST['shipping_address'] ?? '');

    if ($first_name === '' || $last_name === '' || $phone === '' || $shipping_address === '') {
        $error = "РџРѕР¶Р°Р»СѓР№СЃС‚Р°, Р·Р°РїРѕР»РЅРёС‚Рµ РІСЃРµ РїРѕР»СЏ Р·Р°РєР°Р·Р°.";
    } elseif (empty($_SESSION['cart'])) {
        $error = "РљРѕСЂР·РёРЅР° РїСѓСЃС‚Р°.";
    } else {
        // Р•СЃР»Рё Сѓ РІР°СЃ РµСЃС‚СЊ Р°РІС‚РѕСЂРёР·Р°С†РёСЏ вЂ” РїРѕР»РѕР¶РёС‚Рµ С‚СѓРґР° СЂРµР°Р»СЊРЅС‹Р№ id РїРѕР»СЊР·РѕРІР°С‚РµР»СЏ
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; // 0 = РіРѕСЃС‚СЊ

        try {
            $conn->beginTransaction();

            // 1) РЎРѕР·РґР°С‘Рј Р·Р°РєР°Р· (РІСЃС‚Р°РІР»СЏРµРј user_id Рё status='Open')
            $stmt = $conn->prepare("
                INSERT INTO orders (user_id, first_name, last_name, phone, shipping_address, order_date, status)
                VALUES (:uid, :fn, :ln, :ph, :addr, NOW(), :st)
            ");
            $stmt->execute([
                ':uid'  => $userId,
                ':fn'   => $first_name,
                ':ln'   => $last_name,
                ':ph'   => $phone,
                ':addr' => $shipping_address,
                ':st'   => 'Open', // СЃРѕРѕС‚РІРµС‚СЃС‚РІСѓРµС‚ РІР°С€РµРјСѓ ENUM
            ]);
            $order_id = (int)$conn->lastInsertId();

            // 2) РџРёС€РµРј РїРѕР·РёС†РёРё
            $itemStmt = $conn->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price)
                VALUES (:oid, :pid, :qty, :price)
            ");
            foreach ($_SESSION['cart'] as $it) {
                $itemStmt->execute([
                    ':oid'   => $order_id,
                    ':pid'   => (int)$it['id'],
                    ':qty'   => (int)$it['quantity'],
                    ':price' => (float)$it['price'],
                ]);
            }

            $conn->commit();
            $_SESSION['cart'] = []; // РѕС‡РёСЃС‚РёС‚СЊ РєРѕСЂР·РёРЅСѓ
            $success = "Р—Р°РєР°Р· РѕС„РѕСЂРјР»РµРЅ! РќРѕРјРµСЂ Р·Р°РєР°Р·Р°: #{$order_id}";
        } catch (Exception $ex) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = "РћС€РёР±РєР° РїСЂРё РѕС„РѕСЂРјР»РµРЅРёРё Р·Р°РєР°Р·Р°: " . $ex->getMessage();
        }
    }
}

/* =====================[ Р”Р°РЅРЅС‹Рµ РєРѕСЂР·РёРЅС‹ РґР»СЏ РІС‹РІРѕРґР° ]======== */
$cart_items = [];
$total = 0.0;
foreach ($_SESSION['cart'] as $id => $it) {
    $subtotal = (float)$it['price'] * (int)$it['quantity'];
    $cart_items[] = [
        'id'       => (int)$id,
        'name'     => (string)$it['name'],
        'price'    => (float)$it['price'],
        'quantity' => (int)$it['quantity'],
        'subtotal' => $subtotal,
        'image'    => $it['image'],
    ];
    $total += $subtotal;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>РљРѕСЂР·РёРЅР°</title>
    <link rel="stylesheet" href="<?= asset_url('css/cart.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/catalog.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="section">
    <h1 class="page-title"><i class="fas fa-shopping-cart"></i> Р’Р°С€Р° РєРѕСЂР·РёРЅР°</h1>

    <?php if (!empty($error)): ?>
        <p class="message error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <p class="message success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-basket"></i>
            <p>Р’Р°С€Р° РєРѕСЂР·РёРЅР° РїСѓСЃС‚Р°</p>
            <a href="catalog.php" class="btn"><i class="fas fa-arrow-left"></i> Р’РµСЂРЅСѓС‚СЊСЃСЏ Рє РєР°С‚Р°Р»РѕРіСѓ</a>
        </div>
    <?php else: ?>
        <form method="POST">
            <table class="cart-table">
                <tr>
                    <th>РўРѕРІР°СЂ</th>
                    <th>Р¦РµРЅР°</th>
                    <th>РљРѕР»РёС‡РµСЃС‚РІРѕ</th>
                    <th>РС‚РѕРіРѕ</th>
                    <th>Р”РµР№СЃС‚РІРёРµ</th>
                </tr>
                <?php foreach ($cart_items as $item): ?>
                    <tr>
                        <td class="cart-item-title">
                            <img src="<?= htmlspecialchars(image_src($item['image'])) ?>" class="cart-img" alt="РўРѕРІР°СЂ">
                            <?= htmlspecialchars($item['name']) ?>
                        </td>
                        <td><?= number_format($item['price'], 2, '.', ' ') ?> СЂСѓР±.</td>
                        <td>
                            <input type="number" name="qty[<?= (int)$item['id'] ?>]" min="1" value="<?= (int)$item['quantity'] ?>" style="width:80px">
                        </td>
                        <td><?= number_format($item['subtotal'], 2, '.', ' ') ?> СЂСѓР±.</td>
                        <td>
                            <button type="submit" name="remove_item" value="1" class="btn remove-from-cart"
                                    onclick="this.form.product_id.value='<?= (int)$item['id'] ?>'">
                                <i class="fas fa-trash"></i> РЈРґР°Р»РёС‚СЊ
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3" style="text-align:right"><strong>РћР±С‰Р°СЏ СЃСѓРјРјР°:</strong></td>
                    <td><strong><?= number_format($total, 2, '.', ' ') ?> СЂСѓР±.</strong></td>
                    <td style="text-align:right">
                        <button type="submit" name="update_qty" value="1" class="btn"><i class="fas fa-sync"></i> РћР±РЅРѕРІРёС‚СЊ</button>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="product_id" value="">
        </form>

        <form method="POST" class="checkout-form" style="margin-top:24px">
            <h3><i class="fas fa-truck"></i> РћС„РѕСЂРјР»РµРЅРёРµ Р·Р°РєР°Р·Р°</h3>
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="first_name" placeholder="РРјСЏ" required>
            </div>
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="last_name" placeholder="Р¤Р°РјРёР»РёСЏ" required>
            </div>
            <div class="input-group">
                <i class="fas fa-phone"></i>
                <input type="text" name="phone" placeholder="РўРµР»РµС„РѕРЅ" required>
            </div>
            <div class="input-group">
                <i class="fas fa-map-marker-alt"></i>
                <input type="text" name="shipping_address" placeholder="РђРґСЂРµСЃ РґРѕСЃС‚Р°РІРєРё" required>
            </div>
            <button type="submit" name="checkout" class="btn"><i class="fas fa-credit-card"></i> РћС„РѕСЂРјРёС‚СЊ Р·Р°РєР°Р·</button>
        </form>
    <?php endif; ?>
</div>

<?php include "footer.php"; ?>

</body>
</html>
