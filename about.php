<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>О нас — Фермерский рынок</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/about.css">
    <style>
        body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 0; }
        .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .hero { padding: 32px 0 8px; }
        h1 { font-size: 32px; margin: 0 0 8px; }
        .muted { color: #555; line-height: 1.6; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px; }
        .card { background: #fff; border: 1px solid #eee; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 18px; }
        .list li { margin: 6px 0; }
        #map { width: 100%; height: 380px; border-radius: 14px; }
        .address { margin-top: 12px; font-weight: 600; }
        @media (max-width: 800px){ .grid { grid-template-columns: 1fr; } }
    </style>
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU" type="text/javascript"></script>
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container">
        <section class="hero">
            <h1>О нас</h1>
            <p class="muted">Мы — фермерское хозяйство, которое круглый год поставляет свежие продукты напрямую с полей и ферм. Работаем с проверенными поставщиками, соблюдаем стандарты качества и заботимся о честной цене для покупателей.</p>
        </section>

        <section class="grid">
            <div class="card">
                <h2>Почему нас выбирают</h2>
                <ul class="list">
                    <li>Свежие и натуральные продукты без лишних посредников.</li>
                    <li>Прозрачное происхождение и честная цена.</li>
                    <li>Бережная логистика и быстрая доставка.</li>
                    <li>Локальные фермеры и устойчивое сельское хозяйство.</li>
                </ul>
            </div>

            <div class="card">
                <h2>Где мы находимся</h2>
                <div id="map"></div>
                <div class="address">Наш адрес: г. Курск, пр. Победы, 19</div>
                <p class="muted">Интерактивная карта Яндекс. Вы можете проложить маршрут до нашего магазина.</p>
            </div>
        </section>
    </main>

    <script type="text/javascript">
        ymaps.ready(function () {
            var coords = [51.7304, 36.1927]; // Курск, центр города
            var map = new ymaps.Map('map', {
                center: coords,
                zoom: 15,
                controls: ['zoomControl', 'geolocationControl']
            });
            var placemark = new ymaps.Placemark(coords, {
                balloonContent: 'Фермерский рынок — пр. Победы, 19, Курск',
                hintContent: 'Наш магазин'
            }, {
                preset: 'islands#redShoppingIcon'
            });
            map.geoObjects.add(placemark);
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>
