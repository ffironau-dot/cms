# Схема БД (черновик на утверждение)

MySQL 5.7, движок InnoDB, кодировка `utf8mb4` / `utf8mb4_unicode_ci`. Возможности MySQL 8 не используются.
Префикс таблиц не используется (при необходимости добавим в установщике).

Обозначения: **PK** — первичный ключ, **UQ** — уникальный индекс, **FK** — внешний ключ, `NULL` — поле может быть пустым.

---

## 1. Общие настройки сайта

### `settings` — пары «ключ — значение»

| Поле | Тип | Описание |
|---|---|---|
| `name` | VARCHAR(64) PK | ключ настройки |
| `value` | TEXT NULL | значение |

Ключи:

| Ключ | Что хранит | ТЗ |
|---|---|---|
| `address` | адрес компании одной строкой | 4.1 |
| `phone` | телефон в том виде, как введён | 4.3 |
| `logo_file` | имя файла логотипа (пусто — выводится «Лого») | 4.2 |
| `logo_alt` | alt логотипа | 4.2 |
| `slider_autoplay` | автопрокрутка слайдера: `1` / `0` | 4.5 |
| `slider_interval` | интервал автопрокрутки, секунд | 4.5 |
| `home_title`, `home_description` | Title и Description главной | 4.6 |
| `home_og_title`, `home_og_description`, `home_og_image` | OG-теги главной (`home_og_image` — имя файла) | 4.6 |

Отдельная таблица на одну строку не нужна: настроек мало, набор ключей фиксирован, установщик создаёт их со значениями по умолчанию.

---

## 2. Верхнее меню (4.4)

### `menu_items`

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `parent_id` | INT UNSIGNED NULL, FK → `menu_items.id` | родитель; `NULL` — пункт 1-го уровня |
| `url` | VARCHAR(500) NULL | ссылка; может быть пустой только у родительского пункта |
| `anchor` | VARCHAR(255) | текст ссылки |
| `link_title` | VARCHAR(255) NULL | атрибут title |
| `sort` | INT | порядок (по возрастанию) |
| `is_active` | TINYINT(1) | активность |

Индекс: (`parent_id`, `sort`).
Уровень не хранится отдельно, он вычисляется: `parent_id IS NULL` → 1, иначе → 2. Родителем может быть только пункт 1-го уровня (проверка в коде), поэтому третьего уровня не будет.
FK с `ON DELETE RESTRICT`: удалить пункт с подпунктами нельзя и на уровне БД.

---

## 3. Слайдер главной (4.5)

### `slides`

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `eyebrow` | VARCHAR(255) NULL | надзаголовок |
| `title` | VARCHAR(255) | заголовок (h1 у первого активного, иначе h2) |
| `text` | TEXT NULL | текст |
| `btn1_text`, `btn1_url` | VARCHAR(255) / VARCHAR(500) NULL | кнопка 1 |
| `btn2_text`, `btn2_url` | VARCHAR(255) / VARCHAR(500) NULL | кнопка 2 |
| `image` | VARCHAR(255) NULL | имя файла фото |
| `image_alt` | VARCHAR(255) NULL | alt фото |
| `caption` | VARCHAR(255) NULL | подпись на фото |
| `sort` | INT | порядок |
| `is_active` | TINYINT(1) | активность |
| `created_at`, `updated_at` | DATETIME | |

Индекс: (`is_active`, `sort`). Правило «хотя бы один активный слайд» проверяется в коде.

---

## 4. Единое пространство адресов

Категории, товары, разделы и текстовые страницы живут в одном пространстве URL (5.1, 6). Уникальность обеспечивает одна таблица, и гарантирует её сама БД через уникальный индекс, а не только проверка в коде.

### `routes` — текущие адреса сущностей

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `path` | VARCHAR(500) UQ | полный адрес, например `/neonovye-vyveski/dlya-bara/`, `/blog/statya/` |
| `entity_type` | ENUM('category','product','section','page') | тип сущности |
| `entity_id` | INT UNSIGNED | id сущности |

UQ (`entity_type`, `entity_id`): у каждой сущности ровно один адрес.
Роутер ищет запрошенный путь сначала в `routes`, затем в `redirects`; если не нашёл, отдаёт 404.
Зарезервированные адреса (`/manet…`, `/sitemap.xml`, `/robots.txt`, служебные и загружаемые папки) проверяются в коде.

> Индекс на VARCHAR(500) в utf8mb4 — 2000 байт, это укладывается в лимит 3072 байта (в MySQL 5.7.7+ `innodb_large_prefix` и формат строк DYNAMIC включены по умолчанию). Установщик проверит это при создании таблиц.

### `redirects` — 301-редиректы

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `old_path` | VARCHAR(500) UQ | старый адрес |
| `new_path` | VARCHAR(500) | куда вести |
| `created_at` | DATETIME | |

Индекс: `new_path`.
Логика при смене адреса X → Y (в транзакции):
1. Все редиректы `… → X` перенаправляются на `Y` (цепочки схлопываются: A→B→C становится A→C).
2. Добавляется редирект `X → Y`.
3. Если есть редирект с адреса `Y`, он удаляется (адрес снова занят живой страницей).

При удалении сущности её строка в `routes` удаляется, а редиректы, которые вели на её адрес, тоже удаляются. Так удалённые адреса отдают 404, а не редирект на 404.

---

## 5. Каталог

### `categories` (5.1)

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `parent_id` | INT UNSIGNED NULL, FK → `categories.id` | родительская категория (`NULL` — 1-й уровень) |
| `slug` | VARCHAR(255) | собственный сегмент URL |
| `name` | VARCHAR(255) | название |
| `meta_title`, `meta_description` | VARCHAR(255) / TEXT NULL | Title, Description |
| `og_title`, `og_description` | VARCHAR(255) / TEXT NULL | OG-теги |
| `image` | VARCHAR(255) NULL | основное изображение (оно же OG:Image) |
| `text_small` | TEXT NULL | малый текст |
| `text_big` | MEDIUMTEXT NULL | большой текст (любой HTML) |
| `is_active` | TINYINT(1) DEFAULT 1 | активность |
| `is_system` | TINYINT(1) DEFAULT 0 | `1` — «Без категории» (нельзя удалить, нельзя переименовать) |
| `created_at`, `updated_at` | DATETIME | `updated_at` → `lastmod` в sitemap |

Полный адрес хранится в `routes`. Количество товаров не хранится: оно считается запросом с учётом подкатегорий.
FK `parent_id` с `ON DELETE RESTRICT`.

### `category_properties` — свойства товаров категории (5.1.4)

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `category_id` | INT UNSIGNED FK → `categories.id` | категория-владелец |
| `name` | VARCHAR(255) | название свойства |
| `type` | ENUM('string','number','list','bool') | строка / число / выбор из списка / да-нет |
| `sort` | INT | порядок вывода |

Подкатегория наследует свойства родителя: набор свойств товара = свойства его категории + свойства её родителя (собирается запросом).

### `category_property_options` — варианты для типа «выбор из списка»

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `property_id` | INT UNSIGNED FK → `category_properties.id` ON DELETE CASCADE | |
| `value` | VARCHAR(255) | вариант |
| `sort` | INT | порядок |

### `products` (5.2)

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `category_id` | INT UNSIGNED FK → `categories.id` | по умолчанию «Без категории» |
| `sku` | VARCHAR(100) UQ | артикул |
| `slug` | VARCHAR(255) | сегмент URL (полный адрес `/slug/` — в `routes`) |
| `name` | VARCHAR(255) | название |
| `meta_title`, `meta_description` | VARCHAR(255) / TEXT NULL | |
| `og_title`, `og_description` | VARCHAR(255) / TEXT NULL | |
| `image` | VARCHAR(255) NULL | основное фото (оно же OG:Image) |
| `text_small` | TEXT NULL | малый текст |
| `text_big` | MEDIUMTEXT NULL | большой текст |
| `price_old` | DECIMAL(12,2) NULL | старая цена, ₽ (необязательна) |
| `price` | DECIMAL(12,2) NULL | новая цена, ₽ |
| `is_active` | TINYINT(1) DEFAULT 1 | |
| `created_at`, `updated_at` | DATETIME | сортировка «новые первыми»; `lastmod` |

Индексы: (`category_id`, `created_at`), `name` (поиск в админке).
FK `category_id` с `ON DELETE RESTRICT`: категорию с товарами удалить нельзя.

### `product_images` — дополнительные фото

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `product_id` | INT UNSIGNED FK → `products.id` ON DELETE CASCADE | |
| `file` | VARCHAR(255) | имя файла |
| `sort` | INT | порядок (по возрастанию) |

### `product_property_values` — значения свойств категории у товара

| Поле | Тип | Описание |
|---|---|---|
| `product_id` | INT UNSIGNED FK → `products.id` ON DELETE CASCADE | |
| `property_id` | INT UNSIGNED FK → `category_properties.id` ON DELETE CASCADE | |
| `value` | TEXT NULL | строка / число / `1`-`0` для да-нет |
| `option_id` | INT UNSIGNED NULL FK → `category_property_options.id` ON DELETE SET NULL | для типа «список» |

PK (`product_id`, `property_id`).
`ON DELETE CASCADE` по свойству реализует п. 5.1: при удалении свойства значения у товаров удаляются. При смене категории товара значения прежних свойств удаляет код.

---

## 6. Текстовые страницы (6)

### `sections` — разделы (например, «Блог»)

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `slug` | VARCHAR(255) | сегмент URL (раздел — один уровень, адрес `/slug/`) |
| `name` | VARCHAR(255) | название |
| `meta_title`, `meta_description` | VARCHAR(255) / TEXT NULL | |
| `og_title`, `og_description` | VARCHAR(255) / TEXT NULL | OG-теги (в ТЗ у раздела не перечислены — добавлены для единообразия, см. вопрос ниже) |
| `text_big` | MEDIUMTEXT NULL | текст раздела |
| `is_active` | TINYINT(1) DEFAULT 1 | |
| `created_at`, `updated_at` | DATETIME | |

### `pages` — текстовые страницы

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `section_id` | INT UNSIGNED NULL FK → `sections.id` ON DELETE RESTRICT | раздел (`NULL` — отдельная страница) |
| `slug` | VARCHAR(500) | у отдельной страницы — путь целиком (`o-kompanii/komanda`), у страницы в разделе — собственный сегмент |
| `name` | VARCHAR(255) | |
| `meta_title`, `meta_description` | VARCHAR(255) / TEXT NULL | |
| `og_title`, `og_description` | VARCHAR(255) / TEXT NULL | |
| `image` | VARCHAR(255) NULL | основная картинка |
| `text_small` | TEXT NULL | анонс |
| `text_big` | MEDIUMTEXT NULL | длинный текст |
| `published_at` | DATE NULL | дата публикации (только для страниц в разделе) |
| `is_active` | TINYINT(1) DEFAULT 1 | |
| `created_at`, `updated_at` | DATETIME | |

Сортировка ленты раздела: `COALESCE(published_at, DATE(created_at)) DESC, id DESC`.

---

## 7. Раздел «Файлы» (6.1)

### `files`

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `filename` | VARCHAR(255) UQ | имя файла на диске (`foto.jpg`, `foto-2.jpg`) |
| `mime` | VARCHAR(50) | реальный тип по содержимому |
| `size` | INT UNSIGNED | размер, байт |
| `created_at` | DATETIME | |

Таблица нужна для быстрой пагинации (50 на страницу) и поиска по имени без обхода папки.

---

## 8. Служебные таблицы безопасности (4.7, 8.3)

### `login_attempts` — защита входа в админку

| Поле | Тип | Описание |
|---|---|---|
| `ip` | VARBINARY(16) PK | IP (IPv4/IPv6 в бинарном виде) |
| `fails` | SMALLINT UNSIGNED | неверных попыток подряд |
| `last_fail_at` | DATETIME | |
| `locked_until` | DATETIME NULL | блокировка на 15 минут после 5 неудач |

### `form_submissions_log` — ограничение частоты заявок с одного IP

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `ip` | VARBINARY(16) | |
| `created_at` | DATETIME | |

Индекс (`ip`, `created_at`). Записи старше суток удаляются при очередной отправке. Сами заявки не хранятся, они уходят только на email.

---

## Что хранится вне БД

- `include/connect.php`: параметры БД, домен, логин и хеш пароля администратора, email получателя заявок, параметры SMTP (хост, порт, шифрование, логин, пароль, адрес отправителя). Все имена переменных нейтральные.
- Загруженные файлы: `/uploads/logo/`, `/uploads/slides/`, `/uploads/og/`, `/uploads/categories/`, `/uploads/products/`, `/uploads/pages/`, `/uploads/files/`. В `/uploads/` выполнение PHP запрещено через `.htaccess`.

## Вопросы по схеме

1. **OG-теги у раздела текстовых страниц.** В ТЗ у раздела перечислены только адрес, название, Title, Description и текст. В схему OG:Title и OG:Description добавлены для единообразия. Оставить?
2. **Основная картинка у раздела.** В ТЗ её нет, в схеме тоже. Нужна ли (например, для OG:Image)?
3. **alt у дополнительных фото товара.** В ТЗ не упомянут. Сейчас предполагается, что alt = название товара. Нужно ли отдельное поле?
4. **Блокировка входа — по IP.** В ТЗ: «после 5 неверных попыток вход блокируется на 15 минут». Схема блокирует IP, с которого шли неверные попытки, а не вход для всех (иначе злоумышленник мог бы заблокировать администратора). Подходит?
