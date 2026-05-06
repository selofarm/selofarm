-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Хост: sql306.infinityfree.com
-- Время создания: Май 06 2026 г., 11:32
-- Версия сервера: 11.4.10-MariaDB
-- Версия PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `if0_40873119_selofarm`
--

-- --------------------------------------------------------

--
-- Структура таблицы `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `cart_items`
--

INSERT INTO `cart_items` (`id`, `user_id`, `product_id`, `quantity`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 2, '2026-01-25 10:00:00', '2026-01-25 10:00:00'),
(2, 2, 3, 1, '2026-01-25 10:00:00', '2026-01-25 10:00:00'),
(3, 2, 6, 1, '2026-01-25 10:00:00', '2026-01-25 10:00:00'),
(4, 3, 2, 1, '2026-01-25 11:30:00', '2026-01-25 11:30:00'),
(5, 3, 7, 1, '2026-01-25 11:30:00', '2026-01-25 11:30:00'),
(6, 4, 10, 2, '2026-01-25 12:45:00', '2026-01-25 12:45:00'),
(7, 4, 15, 1, '2026-01-25 12:45:00', '2026-01-25 12:45:00'),
(8, 5, 11, 1, '2026-01-25 14:20:00', '2026-01-25 14:20:00'),
(9, 5, 18, 3, '2026-01-25 14:20:00', '2026-01-25 14:20:00'),
(10, 5, 20, 1, '2026-01-25 14:20:00', '2026-01-25 14:20:00');

-- --------------------------------------------------------

--
-- Структура таблицы `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `date` date DEFAULT curdate(),
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `news`
--

INSERT INTO `news` (`id`, `title`, `content`, `date`, `image`, `created_at`) VALUES
(1, 'Открытие нового сезона!', 'Дорогие друзья! Рады сообщить о начале нового сезона свежих овощей и фруктов. В этом году мы расширили ассортимент и добавили новые экопродукты.', '2026-01-10', 'img/news_season.jpg', '2026-01-10 15:33:39'),
(2, 'Специальная акция на мед', 'Только в этом месяце скидка 15% на весь ассортимент меда! Успейте приобрести натуральный мед с нашей пасеки по выгодной цене.', '2026-01-10', 'img/news_honey.jpg', '2026-01-10 15:33:39'),
(3, 'Новые поставки молочной продукции', 'С этой недели в продаже появились свежие молочные продукты от местных фермеров: молоко, творог, сметана и сыры.', '2026-01-15', 'img/news_dairy.jpg', '2026-01-15 10:20:00'),
(4, 'Фермерский фестиваль 2026', 'Приглашаем всех на ежегодный фермерский фестиваль, который состоится 25 февраля. Дегустации, мастер-классы и специальные предложения!', '2026-01-20', 'img/news_festival.jpg', '2026-01-20 14:45:00');

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `shipping_address` text NOT NULL,
  `order_date` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('Open','Processed','Completed','Canceled') DEFAULT 'Open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `first_name`, `last_name`, `phone`, `shipping_address`, `order_date`, `status`) VALUES
(1, 2, 'Мария', 'Иванова', '+7 (912) 345-67-89', 'ул. Ленина, д. 10, кв. 25, г. Москва', '2026-01-12 09:15:00', 'Completed'),
(2, 3, 'Петр', 'Сидоров', '+7 (923) 456-78-90', 'пр. Мира, д. 45, г. Санкт-Петербург', '2026-01-14 11:30:00', 'Processed'),
(3, 2, 'Мария', 'Иванова', '+7 (912) 345-67-89', 'ул. Ленина, д. 10, кв. 25, г. Москва', '2026-01-18 14:20:00', 'Open'),
(4, 4, 'Алексей', 'Кузнецов', '+7 (934) 567-89-01', 'ул. Садовая, д. 3, г. Екатеринбург', '2026-01-19 16:45:00', 'Completed'),
(5, 5, 'Ольга', 'Петрова', '+7 (945) 678-90-12', 'ул. Центральная, д. 7, г. Казань', '2026-01-22 10:10:00', 'Processed');

-- --------------------------------------------------------

--
-- Структура таблицы `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 1, 1, 2, '150.00'),
(2, 1, 3, 1, '350.00'),
(3, 1, 5, 1, '280.00'),
(4, 2, 2, 3, '120.00'),
(5, 2, 7, 2, '420.00'),
(6, 3, 10, 1, '190.00'),
(7, 3, 12, 2, '320.00'),
(8, 4, 4, 1, '180.00'),
(9, 4, 8, 1, '250.00'),
(10, 4, 15, 1, '310.00'),
(11, 5, 6, 2, '210.00'),
(12, 5, 11, 1, '290.00'),
(13, 5, 18, 3, '95.00');

-- --------------------------------------------------------

--
-- Структура таблицы `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `price_unit` varchar(20) NOT NULL DEFAULT 'шт.',
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `price_unit`, `description`, `image`, `created_at`, `updated_at`) VALUES
(1, 'Свежие яблоки', '150.00', 'кг', 'Сочные яблоки с местного сада, богаты витаминами', 'img/apples.jpg', '2026-01-10 15:33:39', '2026-01-30 07:36:02'),
(2, 'Домашние яйца', '120.00', 'шт.', 'Натуральные яйца от свободно пасущихся кур', 'img/eggs.jpg', '2026-01-10 15:33:39', '2026-01-30 07:36:00'),
(3, 'Органический мед', '350.00', 'кг', 'Натуральный мед с пасеки, без добавок', 'img/honey.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:57'),
(4, 'Свежая морковь', '80.00', 'кг', 'Молодая морковь с грядки, богата каротином', 'img/carrots.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:54'),
(5, 'Картофель фермерский', '90.00', 'кг', 'Отборный картофель, выращенный без химикатов', 'img/potatoes.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:51'),
(6, 'Помидоры черри', '280.00', 'кг', 'Сладкие помидорки черри, идеальны для салатов', 'img/cherry_tomatoes.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:47'),
(7, 'Сыр домашний', '420.00', 'кг', 'Натуральный сыр из коровьего молока', 'img/cheese.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:43'),
(8, 'Сметана деревенская', '250.00', 'уп.', 'Густая сметана 25% жирности', 'img/sour_cream.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:40'),
(9, 'Огурцы грунтовые', '120.00', 'кг', 'Свежие огурцы с открытого грунта', 'img/cucumbers.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:36'),
(10, 'Лук репчатый', '65.00', 'кг', 'Красный и желтый лук', 'img/onions.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:33'),
(11, 'Молоко парное', '290.00', 'л', 'Свежее молоко от коров на свободном выпасе', 'img/milk.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:30'),
(12, 'Творог домашний', '320.00', 'кг', 'Нежный творог 9% жирности', 'img/cottage_cheese.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:28'),
(13, 'Капуста белокочанная', '75.00', 'кг', 'Свежая капуста поздних сортов', 'img/cabbage.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:24'),
(14, 'Свекла столовая', '85.00', 'кг', 'Сладкая свекла для борщей и салатов', 'img/beetroot.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:21'),
(15, 'Грецкие орехи', '310.00', 'кг', 'Очищенные грецкие орехи', 'img/walnuts.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:19'),
(16, 'Чеснок домашний', '110.00', 'кг', 'Ароматный чеснок с собственного огорода', 'img/garlic.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:16'),
(17, 'Зелень свежая', '140.00', 'уп.', 'Укроп, петрушка, зеленый лук', 'img/greens.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:13'),
(18, 'Перец болгарский', '95.00', 'кг', 'Красный, желтый и зеленый перец', 'img/bell_pepper.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:11'),
(19, 'Тыква мускатная', '180.00', 'кг', 'Сладкая тыква для пирогов и каш', 'img/pumpkin.jpg', '2026-01-10 15:33:39', '2026-01-30 07:35:08'),
(20, 'Куриное филе', '380.00', 'кг', 'Филе куриной грудки', 'img/chicken.jpg', '2026-01-10 15:33:39', '2026-01-30 07:34:50');

-- --------------------------------------------------------

--
-- Структура таблицы `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `text` text DEFAULT NULL,
  `approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `reviews`
--

INSERT INTO `reviews` (`id`, `name`, `rating`, `text`, `approved`, `created_at`) VALUES
(1, 'Анна', 5, 'Отличные продукты, всегда свежие и вкусные! Заказываю регулярно, доставка всегда вовремя.', 1, '2026-01-10 15:33:39'),
(2, 'Иван', 4, 'Хорошее качество, доставка быстрая. Особенно нравятся молочные продукты и овощи. Рекомендую!', 1, '2026-01-10 15:33:39'),
(3, 'Сергей', 5, 'Всё отлично! Продукты действительно фермерские, чувствуется разница с магазинными. Цены адекватные.', 1, '2026-01-10 15:51:21'),
(4, 'Елена', 5, 'Прекрасный сервис! Яйца всегда свежайшие, мед просто волшебный. Спасибо за качество!', 1, '2026-01-15 12:30:00'),
(5, 'Дмитрий', 4, 'Нравится ассортимент и качество. Были небольшие задержки с доставкой, но в целом всё хорошо.', 1, '2026-01-18 09:45:00');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `first_name`, `last_name`, `phone`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@selofarm.ru', 'Александр', 'Смирнов', '+7 (999) 123-45-67', '2026-01-10 15:33:39'),
(2, 'maria_ivanova', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'maria@mail.ru', 'Мария', 'Иванова', '+7 (912) 345-67-89', '2026-01-11 10:20:00'),
(3, 'petr_sidorov', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'petr@yandex.ru', 'Петр', 'Сидоров', '+7 (923) 456-78-90', '2026-01-12 14:15:00'),
(4, 'alex_kuznetsov', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alex@mail.ru', 'Алексей', 'Кузнецов', '+7 (934) 567-89-01', '2026-01-13 11:30:00'),
(5, 'olga_petrova', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'olga@gmail.com', 'Ольга', 'Петрова', '+7 (945) 678-90-12', '2026-01-14 16:45:00');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_order_date` (`order_date`),
  ADD KEY `idx_status` (`status`);

--
-- Индексы таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Индексы таблицы `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT для таблицы `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT для таблицы `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
