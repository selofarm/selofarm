<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ai_recipe_helper.php';

$BASE_URL = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

function asset_url(string $path): string
{
    global $BASE_URL;
    return $BASE_URL . '/' . ltrim($path, '/');
}

function image_src($image): string
{
    if (is_string($image) && (str_starts_with($image, 'data:') || str_starts_with($image, 'http'))) {
        return $image;
    }

    $looksLikePath = is_string($image) && preg_match('~\.(jpe?g|png|gif|webp)$~i', $image);
    $hasBinary = is_string($image) && preg_match('~[^\x09\x0A\x0D\x20-\x7E]~', $image);

    if ($image && !$looksLikePath && $hasBinary) {
        return 'data:image/jpeg;base64,' . base64_encode($image);
    }

    if ($looksLikePath) {
        $path = '/' . ltrim($image, '/');
        global $BASE_URL;

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

if (isset($_POST['add_to_cart'])) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $stmt = $conn->prepare("SELECT id, name, price, image, description FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity']++;
        } else {
            $_SESSION['cart'][$product_id] = [
                'id' => (int)$product['id'],
                'name' => $product['name'],
                'price' => (float)$product['price'],
                'image' => $product['image'],
                'quantity' => 1,
            ];
        }
    }
}

$aiDish = '';
$aiRecipe = null;
$aiProducts = [];
$aiError = '';

try {
    $stmt = $conn->query("SELECT id, name, price, description, image FROM products ORDER BY id DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $products = [];
}

if (isset($_POST['generate_recipe'])) {
    $aiDish = trim((string)($_POST['dish_name'] ?? ''));

    if ($aiDish === '') {
        $aiError = 'Р’РІРµРґРёС‚Рµ РЅР°Р·РІР°РЅРёРµ Р±Р»СЋРґР°.';
    } elseif (empty($products)) {
        $aiError = 'РЎРїРёСЃРѕРє С‚РѕРІР°СЂРѕРІ РЅРµРґРѕСЃС‚СѓРїРµРЅ, РїРѕСЌС‚РѕРјСѓ РїРѕРґР±РѕСЂ РїРѕРєР° РЅРµРІРѕР·РјРѕР¶РµРЅ.';
    } else {
        $aiResponse = request_hf_recipe($aiDish, $products);
        if (!($aiResponse['ok'] ?? false)) {
            $aiError = (string)($aiResponse['error'] ?? 'РќРµ СѓРґР°Р»РѕСЃСЊ РїРѕР»СѓС‡РёС‚СЊ РѕС‚РІРµС‚ РѕС‚ РР.');
        } else {
            $aiRecipe = $aiResponse['data'];
            $aiProducts = find_products_for_recipe($products, $aiRecipe, $aiDish);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>РљР°С‚Р°Р»РѕРі РїСЂРѕРґСѓРєС†РёРё</title>
    <link rel="stylesheet" href="<?= asset_url('css/catalog.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="section">
    <h1>РљР°С‚Р°Р»РѕРі РїСЂРѕРґСѓРєС†РёРё</h1>

    <section class="ai-recipe-box">
        <p class="ai-kicker">РР-РїРѕРјРѕС‰РЅРёРє</p>
        <h2>РџРѕРґРѕР±СЂР°С‚СЊ СЂРµС†РµРїС‚ РїРѕ Р±Р»СЋРґСѓ</h2>
        <p class="ai-subtitle">Р’РІРµРґРёС‚Рµ РЅР°Р·РІР°РЅРёРµ Р±Р»СЋРґР°, Рё СЃР°Р№С‚ СЃРіРµРЅРµСЂРёСЂСѓРµС‚ СЂРµС†РµРїС‚ С‡РµСЂРµР· Hugging Face Рё РїРѕРєР°Р¶РµС‚ С‚РѕРІР°СЂС‹ РёР· Р±Р°Р·С‹, РµСЃР»Рё РѕРЅРё РїРѕРґС…РѕРґСЏС‚.</p>

        <form method="POST" class="ai-recipe-form" accept-charset="UTF-8">
            <label for="dish_name">РќР°Р·РІР°РЅРёРµ Р±Р»СЋРґР°</label>
            <div class="ai-form-row">
                <input
                    type="text"
                    id="dish_name"
                    name="dish_name"
                    value="<?= htmlspecialchars($aiDish, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    placeholder="РќР°РїСЂРёРјРµСЂ, Р±РѕСЂС‰, СЃС‹СЂРЅРёРєРё, С€Р°РєС€СѓРєР°"
                    maxlength="120"
                >
                <button type="submit" name="generate_recipe" class="btn ai-submit">
                    <i class="fas fa-robot"></i> РџРѕР»СѓС‡РёС‚СЊ СЂРµС†РµРїС‚
                </button>
            </div>
        </form>

        <?php if ($aiError !== ''): ?>
            <div class="ai-message ai-error"><?= htmlspecialchars($aiError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (is_array($aiRecipe)): ?>
            <div class="ai-result">
                <div class="ai-recipe-card">
                    <h3><?= htmlspecialchars((string)$aiRecipe['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>

                    <?php if (!empty($aiRecipe['intro'])): ?>
                        <p class="ai-intro"><?= htmlspecialchars((string)$aiRecipe['intro'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <?php endif; ?>

                    <?php if (!empty($aiRecipe['ingredients'])): ?>
                        <h4>РРЅРіСЂРµРґРёРµРЅС‚С‹</h4>
                        <ul class="ai-list">
                            <?php foreach ($aiRecipe['ingredients'] as $ingredient): ?>
                                <li><?= htmlspecialchars((string)$ingredient, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($aiRecipe['steps'])): ?>
                        <h4>РљР°Рє РіРѕС‚РѕРІРёС‚СЊ</h4>
                        <ol class="ai-steps">
                            <?php foreach ($aiRecipe['steps'] as $step): ?>
                                <li><?= htmlspecialchars((string)$step, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>

                    <?php if (!empty($aiRecipe['tips'])): ?>
                        <h4>РЎРѕРІРµС‚С‹</h4>
                        <ul class="ai-list">
                            <?php foreach ($aiRecipe['tips'] as $tip): ?>
                                <li><?= htmlspecialchars((string)$tip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="ai-products-card">
                    <h3>РўРѕРІР°СЂС‹ РёР· Р±Р°Р·С‹</h3>

                    <?php if (!empty($aiProducts)): ?>
                        <div class="ai-products-grid">
                            <?php foreach ($aiProducts as $row): ?>
                                <?php $src = image_src($row['image']); ?>
                                <article class="ai-product-item">
                                    <img src="<?= htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)$row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                    <div>
                                        <h4><?= htmlspecialchars((string)$row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h4>
                                        <p class="ai-price">Р¦РµРЅР°: <?= htmlspecialchars(number_format((float)$row['price'], 2, '.', ' '), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> СЂСѓР±.</p>
                                        <p><?= htmlspecialchars((string)($row['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                                        <div class="ai-product-actions">
                                            <a href="product.php?id=<?= (int)$row['id'] ?>" class="btn">РџРѕРґСЂРѕР±РЅРµРµ</a>
                                            <form method="POST">
                                                <input type="hidden" name="product_id" value="<?= (int)$row['id'] ?>">
                                                <button type="submit" name="add_to_cart" class="btn add-to-cart">
                                                    <i class="fas fa-cart-plus"></i> Р’ РєРѕСЂР·РёРЅСѓ
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="ai-message">РџРѕРґ СЌС‚Рѕ Р±Р»СЋРґРѕ РІ Р±Р°Р·Рµ РїРѕРєР° РЅРµ РЅР°С€Р»РѕСЃСЊ РїРѕРґС…РѕРґСЏС‰РёС… С‚РѕРІР°СЂРѕРІ.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <div class="products">
        <?php foreach ($products as $row): ?>
            <?php $src = image_src($row['image']); ?>
            <div class="product">
                <img src="<?= htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="product">
                <h3><?= htmlspecialchars((string)$row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                <p>Р¦РµРЅР°: <?= htmlspecialchars(number_format((float)$row['price'], 2, '.', ' '), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> СЂСѓР±.</p>
                <p><?= htmlspecialchars((string)($row['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <a href="product.php?id=<?= (int)$row['id'] ?>" class="btn">РџРѕРґСЂРѕР±РЅРµРµ</a>

                <form method="POST">
                    <input type="hidden" name="product_id" value="<?= (int)$row['id'] ?>">
                    <button type="submit" name="add_to_cart" class="btn add-to-cart">
                        <i class="fas fa-cart-plus"></i> Р’ РєРѕСЂР·РёРЅСѓ
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
