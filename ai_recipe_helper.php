<?php

function hf_api_token(): string
{
    $token = getenv('HF_API_TOKEN')
        ?: getenv('HUGGINGFACE_API_KEY')
        ?: ($_SERVER['HF_API_TOKEN'] ?? '')
        ?: ($_SERVER['HUGGINGFACE_API_KEY'] ?? '');

    return is_string($token) ? trim($token) : '';
}

function text_lower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

function split_keywords(string $value): array
{
    $parts = preg_split('/[^\p{L}\p{N}]+/u', text_lower($value)) ?: [];
    $keywords = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '' && text_length($part) >= 3) {
            $keywords[$part] = true;
        }
    }

    return array_keys($keywords);
}

function build_products_prompt(array $products): string
{
    $lines = [];

    foreach ($products as $product) {
        $lines[] = sprintf(
            '%d | %s | %s',
            (int)($product['id'] ?? 0),
            trim((string)($product['name'] ?? '')),
            trim((string)($product['description'] ?? ''))
        );
    }

    return implode("\n", $lines);
}

function extract_json_object(string $text): ?array
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }

    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('/```json\s*(\{.*?\})\s*```/su', $text, $matches)) {
        $decoded = json_decode($matches[1], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    if (preg_match('/(\{.*\})/su', $text, $matches)) {
        $decoded = json_decode($matches[1], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function normalize_recipe_data(array $data, string $dishName): array
{
    $title = trim((string)($data['title'] ?? $dishName));
    $intro = trim((string)($data['intro'] ?? ''));
    $ingredients = array_values(array_filter(array_map('trim', (array)($data['ingredients'] ?? []))));
    $steps = array_values(array_filter(array_map('trim', (array)($data['steps'] ?? []))));
    $tips = array_values(array_filter(array_map('trim', (array)($data['tips'] ?? []))));
    $matchingIds = array_map('intval', (array)($data['matching_product_ids'] ?? []));

    return [
        'title' => $title !== '' ? $title : $dishName,
        'intro' => $intro,
        'ingredients' => $ingredients,
        'steps' => $steps,
        'tips' => $tips,
        'matching_product_ids' => array_values(array_unique(array_filter($matchingIds))),
    ];
}

function request_hf_recipe(string $dishName, array $products): array
{
    $token = hf_api_token();
    if ($token === '') {
        return [
            'ok' => false,
            'error' => 'Не задан токен Hugging Face. Добавьте переменную окружения HF_API_TOKEN.',
        ];
    }

    $prompt = <<<PROMPT
Ты помощник кулинарного магазина. Ответь строго в JSON без markdown и без пояснений.
Нужно придумать понятный рецепт для блюда "{$dishName}" и выбрать только те товары, которые есть в списке ниже.

Верни объект JSON такого вида:
{
  "title": "название блюда",
  "intro": "1-2 предложения",
  "ingredients": ["ингредиент 1", "ингредиент 2"],
  "steps": ["шаг 1", "шаг 2", "шаг 3"],
  "tips": ["совет 1", "совет 2"],
  "matching_product_ids": [1, 2]
}

Правила:
- Пиши по-русски.
- matching_product_ids может содержать только id из списка товаров.
- Если подходящих товаров нет, верни пустой массив.
- Не добавляй текст вне JSON.

Товары магазина:
%s
PROMPT;

    $payload = [
        'model' => 'deepseek-ai/DeepSeek-R1',
        'messages' => [
            ['role' => 'system', 'content' => 'Ты помощник кулинарного магазина. Ответь строго в JSON без markdown и без пояснений.'],
            ['role' => 'user', 'content' => sprintf($prompt, build_products_prompt($products))],
        ],
        'max_tokens' => 1500,
        'temperature' => 0.3,
    ];

    $ch = curl_init('https://router.huggingface.co/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json; charset=UTF-8',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return [
            'ok' => false,
            'error' => 'Не удалось обратиться к Hugging Face: ' . $curlError,
        ];
    }

    $decoded = json_decode($raw, true);
    if ($statusCode >= 400) {
        $message = is_array($decoded) ? (string)($decoded['error'] ?? $raw) : $raw;
        return [
            'ok' => false,
            'error' => 'Hugging Face вернул ошибку: ' . $message,
        ];
    }

    $generatedText = '';
    if (is_array($decoded) && isset($decoded['choices'][0]['message']['content'])) {
        $generatedText = (string)$decoded['choices'][0]['message']['content'];
        $generatedText = preg_replace('/<think>.*?<\/think>/s', '', $generatedText);
    }

    $recipe = extract_json_object($generatedText);
    if (!is_array($recipe)) {
        return [
            'ok' => false,
            'error' => 'Не удалось разобрать ответ модели. Проверьте формат ответа Hugging Face.',
        ];
    }

    return [
        'ok' => true,
        'data' => normalize_recipe_data($recipe, $dishName),
    ];
}

function find_products_for_recipe(array $products, array $recipeData, string $dishName): array
{
    $indexed = [];
    foreach ($products as $product) {
        $indexed[(int)$product['id']] = $product;
    }

    $matched = [];
    foreach ((array)($recipeData['matching_product_ids'] ?? []) as $productId) {
        $productId = (int)$productId;
        if (isset($indexed[$productId])) {
            $matched[$productId] = $indexed[$productId];
        }
    }

    if (!empty($matched)) {
        return array_values($matched);
    }

    $keywords = split_keywords($dishName . ' ' . implode(' ', (array)($recipeData['ingredients'] ?? [])));
    if (empty($keywords)) {
        return [];
    }

    $scored = [];
    foreach ($products as $product) {
        $haystack = text_lower(trim((string)($product['name'] ?? '') . ' ' . (string)($product['description'] ?? '')));
        $score = 0;

        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                $score++;
            }
        }

        if ($score > 0) {
            $product['match_score'] = $score;
            $scored[] = $product;
        }
    }

    usort($scored, static function (array $a, array $b): int {
        return ($b['match_score'] ?? 0) <=> ($a['match_score'] ?? 0);
    });

    return array_slice($scored, 0, 6);
}
