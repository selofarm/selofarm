<?php
session_start();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            header("Location: admin.php");
            exit;
        } else {
            $error = "РќРµРІРµСЂРЅС‹Р№ РїР°СЂРѕР»СЊ РґР»СЏ РїРѕР»СЊР·РѕРІР°С‚РµР»СЏ '$username'";
        }
    } else {
        $error = "РџРѕР»СЊР·РѕРІР°С‚РµР»СЊ СЃ Р»РѕРіРёРЅРѕРј '$username' РЅРµ РЅР°Р№РґРµРЅ";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Р’С…РѕРґ РІ Р°РґРјРёРЅ-РїР°РЅРµР»СЊ</title>
    <link rel="stylesheet" href="css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="form-container">
        <h2>Р’С…РѕРґ РІ Р°РґРјРёРЅ-РїР°РЅРµР»СЊ</h2>
        <?php if (isset($error)) echo "<p class='error'><i class='fas fa-exclamation-circle'></i> $error</p>"; ?>
        <form method="POST">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="Р›РѕРіРёРЅ" required>
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="РџР°СЂРѕР»СЊ" required>
            </div>
            <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Р’РѕР№С‚Рё</button>
        </form>
    </div>
</body>
</html>
