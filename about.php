<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рћ РЅР°СЃ вЂ” Р¤РµСЂРјРµСЂСЃРєРёР№ СЂС‹РЅРѕРє</title>
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
            <h1>Рћ РЅР°СЃ</h1>
            <p class="muted">РњС‹ вЂ” С„РµСЂРјРµСЂСЃРєРѕРµ С…РѕР·СЏР№СЃС‚РІРѕ, РєРѕС‚РѕСЂРѕРµ РєСЂСѓРіР»С‹Р№ РіРѕРґ РїРѕСЃС‚Р°РІР»СЏРµС‚ СЃРІРµР¶РёРµ РїСЂРѕРґСѓРєС‚С‹ РЅР°РїСЂСЏРјСѓСЋ СЃ РїРѕР»РµР№ Рё С„РµСЂРј. Р Р°Р±РѕС‚Р°РµРј СЃ РїСЂРѕРІРµСЂРµРЅРЅС‹РјРё РїРѕСЃС‚Р°РІС‰РёРєР°РјРё, СЃРѕР±Р»СЋРґР°РµРј СЃС‚Р°РЅРґР°СЂС‚С‹ РєР°С‡РµСЃС‚РІР° Рё Р·Р°Р±РѕС‚РёРјСЃСЏ Рѕ С‡РµСЃС‚РЅРѕР№ С†РµРЅРµ РґР»СЏ РїРѕРєСѓРїР°С‚РµР»РµР№.</p>
        </section>

        <section class="grid">
            <div class="card">
                <h2>РџРѕС‡РµРјСѓ РЅР°СЃ РІС‹Р±РёСЂР°СЋС‚</h2>
                <ul class="list">
                    <li>РЎРІРµР¶РёРµ Рё РЅР°С‚СѓСЂР°Р»СЊРЅС‹Рµ РїСЂРѕРґСѓРєС‚С‹ Р±РµР· Р»РёС€РЅРёС… РїРѕСЃСЂРµРґРЅРёРєРѕРІ.</li>
                    <li>РџСЂРѕР·СЂР°С‡РЅРѕРµ РїСЂРѕРёСЃС…РѕР¶РґРµРЅРёРµ Рё С‡РµСЃС‚РЅР°СЏ С†РµРЅР°.</li>
                    <li>Р‘РµСЂРµР¶РЅР°СЏ Р»РѕРіРёСЃС‚РёРєР° Рё Р±С‹СЃС‚СЂР°СЏ РґРѕСЃС‚Р°РІРєР°.</li>
                    <li>Р›РѕРєР°Р»СЊРЅС‹Рµ С„РµСЂРјРµСЂС‹ Рё СѓСЃС‚РѕР№С‡РёРІРѕРµ СЃРµР»СЊСЃРєРѕРµ С…РѕР·СЏР№СЃС‚РІРѕ.</li>
                </ul>
            </div>

            <div class="card">
                <h2>Р“РґРµ РјС‹ РЅР°С…РѕРґРёРјСЃСЏ</h2>
                <div id="map"></div>
                <div class="address">РќР°С€ Р°РґСЂРµСЃ: Рі. РњРѕСЃРєРІР°, СѓР». РџСЂРёРјРµСЂРЅР°СЏ, 1</div>
                <p class="muted">РќРёР¶Рµ вЂ” РёРЅС‚РµСЂР°РєС‚РёРІРЅР°СЏ РєР°СЂС‚Р° РЇРЅРґРµРєСЃ. Р’С‹ РјРѕР¶РµС‚Рµ РїСЂРѕР»РѕР¶РёС‚СЊ РјР°СЂС€СЂСѓС‚ РґРѕ РјР°РіР°Р·РёРЅР°.</p>
            </div>
        </section>
    </main>

    <script type="text/javascript">
        ymaps.ready(function () {
            // РЈРєР°Р¶РёС‚Рµ РєРѕРѕСЂРґРёРЅР°С‚С‹ РІР°С€РµРіРѕ РјР°РіР°Р·РёРЅР°
            var coords = [55.751244, 37.618423]; // РњРѕСЃРєРІР°, РґР»СЏ РїСЂРёРјРµСЂР°
            var map = new ymaps.Map('map', {
                center: coords,
                zoom: 14,
                controls: ['zoomControl', 'geolocationControl']
            });
            var placemark = new ymaps.Placemark(coords, {
                balloonContent: 'Р¤РµСЂРјРµСЂСЃРєРёР№ СЂС‹РЅРѕРє вЂ” СѓР». РџСЂРёРјРµСЂРЅР°СЏ, 1',
                hintContent: 'РќР°С€ РјР°РіР°Р·РёРЅ'
            }, {
                preset: 'islands#redShoppingIcon'
            });
            map.geoObjects.add(placemark);
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>
