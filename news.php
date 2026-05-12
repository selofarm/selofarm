<?php
session_start();
require_once __DIR__ . '/db.php';
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новости и акции</title>
    <link rel="stylesheet" href="css/news.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <main class="section">
        <h1>Новости и акции</h1>
        <div class="news-grid">
        <?php
        try {
            $stmt = $conn->query("SELECT * FROM news ORDER BY date DESC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        ?>
            <div class="news-card">
                <?php
                if (!empty($row['image'])) {
                    if (strlen($row['image']) > 200) {
                        $d=$row['image']; if(substr($d,0,3)==="\xFF\xD8\xFF") $m='image/jpeg'; elseif(substr($d,0,4)==="\x89PNG") $m='image/png'; elseif(substr($d,0,3)==='GIF') $m='image/gif'; else $m='image/jpeg';
                        $imgSrc = "data:{$m};base64," . base64_encode($d);
                    } else {
                        $imgSrc = htmlspecialchars($row['image']);
                    }
                    echo "<img src='{$imgSrc}' alt='Новость' class='news-img'>";
                }
                ?>
                <div class="news-body">
                    <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                    <p><?php echo nl2br(htmlspecialchars($row['content'])); ?></p>
                    <span class="date">
                        <i class="far fa-calendar-alt"></i>
                        <?php echo htmlspecialchars($row['date']); ?>
                    </span>
                </div>
            </div>
        <?php
            }
        } catch (Throwable $e) {
            echo "<p style='color:#b00'>Не удалось загрузить новости.</p>";
        }
        ?>
        </div>
    </main>
    <?php include 'footer.php'; ?>
</body>
</html>
