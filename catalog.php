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
        $aiError = 'Введите название блюда.';
    } elseif (empty($products)) {
        $aiError = 'Список товаров недоступен, поэтому подбор пока невозможен.';
    } else {
        $aiResponse = request_hf_recipe($aiDish, $products);
        if (!($aiResponse['ok'] ?? false)) {
            $aiError = (string)($aiResponse['error'] ?? 'Не удалось получить ответ от ИИ.');
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
    <title>Каталог продукции</title>
    <link rel="stylesheet" href="<?= asset_url('css/catalog.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="section">
    <h1>Каталог продукции</h1>

    <section class="ai-recipe-box">
        <p class="ai-kicker">ИИ-помощник</p>
        <h2>Подобрать рецепт по блюду</h2>
        <p class="ai-subtitle">Введите название блюда, и сайт сгенерирует рецепт через Hugging Face и покажет товары из базы, если они подходят.</p>

        <form method="POST" class="ai-recipe-form" accept-charset="UTF-8">
            <label for="dish_name">Название блюда</label>
            <div class="ai-form-row">
                <input
                    type="text"
                    id="dish_name"
                    name="dish_name"
                    value="<?= htmlspecialchars($aiDish, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    placeholder="Например, борщ, сырники, шакшука"
                    maxlength="120"
                >
                <button type="submit" name="generate_recipe" class="btn ai-submit">
                    <i class="fas fa-robot"></i> Получить рецепт
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
                        <h4>Ингредиенты</h4>
                        <ul class="ai-list">
                            <?php foreach ($aiRecipe['ingredients'] as $ingredient): ?>
                                <li><?= htmlspecialchars((string)$ingredient, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($aiRecipe['steps'])): ?>
                        <h4>Как готовить</h4>
                        <ol class="ai-steps">
                            <?php foreach ($aiRecipe['steps'] as $step): ?>
                                <li><?= htmlspecialchars((string)$step, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>

                    <?php if (!empty($aiRecipe['tips'])): ?>
                        <h4>Советы</h4>
                        <ul class="ai-list">
                            <?php foreach ($aiRecipe['tips'] as $tip): ?>
                                <li><?= htmlspecialchars((string)$tip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="ai-products-card">
                    <h3>Товары из базы</h3>

                    <?php if (!empty($aiProducts)): ?>
                        <div class="ai-products-grid">
                            <?php foreach ($aiProducts as $row): ?>
                                <?php $src = image_src($row['image']); ?>
                                <article class="ai-product-item">
                                    <img src="<?= htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)$row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                    <div>
                                        <h4><?= htmlspecialchars((string)$row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h4>
                                        <p class="ai-price">Цена: <?= htmlspecialchars(number_format((float)$row['price'], 2, '.', ' '), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> руб.</p>
                                        <p><?= htmlspecialchars((string)($row['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                                        <div class="ai-product-actions">
                                            <a href="product.php?id=<?= (int)$row['id'] ?>" class="btn">Подробнее</a>
                                            <form method="POST">
                                                <input type="hidden" name="product_id" value="<?= (int)$row['id'] ?>">
                                                <button type="submit" name="add_to_cart" class="btn add-to-cart">
                                                    <i class="fas fa-cart-plus"></i> В корзину
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="ai-message">Под это блюдо в базе пока не нашлось подходящих товаров.</div>
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
                <p>Цена: <?= htmlspecialchars(number_format((float)$row['price'], 2, '.', ' '), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> руб.</p>
                <p><?= htmlspecialchars((string)($row['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <a href="product.php?id=<?= (int)$row['id'] ?>" class="btn">Подробнее</a>

                <form method="POST">
                    <input type="hidden" name="product_id" value="<?= (int)$row['id'] ?>">
                    <button type="submit" name="add_to_cart" class="btn add-to-cart">
                        <i class="fas fa-cart-plus"></i> В корзину
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
