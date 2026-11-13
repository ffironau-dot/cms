-- =====================================================================
-- Структура базы данных CMS (см. docs/db-schema.md).
-- MySQL 5.7+, InnoDB, utf8mb4. Возможности MySQL 8 не используются.
-- Файл выполняется установщиком start.php; запросы разделены строкой
-- из точки с запятой в конце, комментарии «--» игнорируются.
-- =====================================================================

-- Общие настройки сайта (ключ — значение)
CREATE TABLE settings (
  name  VARCHAR(64) NOT NULL,
  value TEXT NULL,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Верхнее меню (два уровня)
CREATE TABLE menu_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id  INT UNSIGNED NULL,
  url        VARCHAR(500) NULL,
  anchor     VARCHAR(255) NOT NULL,
  link_title VARCHAR(255) NULL,
  sort       INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_menu_parent_sort (parent_id, sort),
  CONSTRAINT fk_menu_parent FOREIGN KEY (parent_id) REFERENCES menu_items (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Слайды слайдера на главной
CREATE TABLE slides (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  eyebrow    VARCHAR(255) NULL,
  title      VARCHAR(255) NOT NULL,
  text       TEXT NULL,
  btn1_text  VARCHAR(255) NULL,
  btn1_url   VARCHAR(500) NULL,
  btn2_text  VARCHAR(255) NULL,
  btn2_url   VARCHAR(500) NULL,
  image      VARCHAR(255) NULL,
  image_alt  VARCHAR(255) NULL,
  caption    VARCHAR(255) NULL,
  sort       INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_slides_active_sort (is_active, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Единое пространство адресов: категории, товары, разделы, текстовые страницы
CREATE TABLE routes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  path        VARCHAR(500) NOT NULL,
  entity_type ENUM('category','product','section','page') NOT NULL,
  entity_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_routes_path (path),
  UNIQUE KEY uq_routes_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Постоянные редиректы 301 со старых адресов
CREATE TABLE redirects (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  old_path   VARCHAR(500) NOT NULL,
  new_path   VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_redirects_old (old_path),
  KEY idx_redirects_new (new_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Категории товаров (два уровня)
CREATE TABLE categories (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id        INT UNSIGNED NULL,
  slug             VARCHAR(255) NOT NULL,
  name             VARCHAR(255) NOT NULL,
  meta_title       VARCHAR(255) NULL,
  meta_description TEXT NULL,
  og_title         VARCHAR(255) NULL,
  og_description   TEXT NULL,
  image            VARCHAR(255) NULL,
  text_small       TEXT NULL,
  text_big         MEDIUMTEXT NULL,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  is_system        TINYINT(1) NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL,
  updated_at       DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_categories_parent (parent_id),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Свойства товаров, заданные для категории
CREATE TABLE category_properties (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  name        VARCHAR(255) NOT NULL,
  type        ENUM('string','number','list','bool') NOT NULL,
  sort        INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_props_category (category_id, sort),
  CONSTRAINT fk_props_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Варианты значений для свойств типа «выбор из списка»
CREATE TABLE category_property_options (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  property_id INT UNSIGNED NOT NULL,
  value       VARCHAR(255) NOT NULL,
  sort        INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_options_property (property_id, sort),
  CONSTRAINT fk_options_property FOREIGN KEY (property_id) REFERENCES category_properties (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Товары
CREATE TABLE products (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id      INT UNSIGNED NOT NULL,
  sku              VARCHAR(100) NOT NULL,
  slug             VARCHAR(255) NOT NULL,
  name             VARCHAR(255) NOT NULL,
  meta_title       VARCHAR(255) NULL,
  meta_description TEXT NULL,
  og_title         VARCHAR(255) NULL,
  og_description   TEXT NULL,
  image            VARCHAR(255) NULL,
  text_small       TEXT NULL,
  text_big         MEDIUMTEXT NULL,
  price_old        DECIMAL(12,2) NULL,
  price            DECIMAL(12,2) NULL,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at       DATETIME NOT NULL,
  updated_at       DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_sku (sku),
  KEY idx_products_category_created (category_id, created_at),
  KEY idx_products_name (name(191)),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Дополнительные фото товара
CREATE TABLE product_images (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  file       VARCHAR(255) NOT NULL,
  alt        VARCHAR(255) NULL,
  sort       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_pimages_product (product_id, sort),
  CONSTRAINT fk_pimages_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Значения свойств категории у товара
CREATE TABLE product_property_values (
  product_id  INT UNSIGNED NOT NULL,
  property_id INT UNSIGNED NOT NULL,
  value       TEXT NULL,
  option_id   INT UNSIGNED NULL,
  PRIMARY KEY (product_id, property_id),
  KEY idx_ppv_property (property_id),
  KEY idx_ppv_option (option_id),
  CONSTRAINT fk_ppv_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_ppv_property FOREIGN KEY (property_id) REFERENCES category_properties (id) ON DELETE CASCADE,
  CONSTRAINT fk_ppv_option FOREIGN KEY (option_id) REFERENCES category_property_options (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Разделы текстовых страниц (например, «Блог»)
CREATE TABLE sections (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug             VARCHAR(255) NOT NULL,
  name             VARCHAR(255) NOT NULL,
  meta_title       VARCHAR(255) NULL,
  meta_description TEXT NULL,
  og_title         VARCHAR(255) NULL,
  og_description   TEXT NULL,
  text_big         MEDIUMTEXT NULL,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at       DATETIME NOT NULL,
  updated_at       DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Текстовые страницы
CREATE TABLE pages (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id       INT UNSIGNED NULL,
  slug             VARCHAR(500) NOT NULL,
  name             VARCHAR(255) NOT NULL,
  meta_title       VARCHAR(255) NULL,
  meta_description TEXT NULL,
  og_title         VARCHAR(255) NULL,
  og_description   TEXT NULL,
  image            VARCHAR(255) NULL,
  text_small       TEXT NULL,
  text_big         MEDIUMTEXT NULL,
  published_at     DATE NULL,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at       DATETIME NOT NULL,
  updated_at       DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_pages_section (section_id),
  CONSTRAINT fk_pages_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Загруженные файлы (раздел «Файлы»)
CREATE TABLE files (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  filename   VARCHAR(255) NOT NULL,
  mime       VARCHAR(50) NOT NULL,
  size       INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_files_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Неудачные попытки входа в админку (блокировка по IP)
CREATE TABLE login_attempts (
  ip           VARBINARY(16) NOT NULL,
  fails        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_fail_at DATETIME NOT NULL,
  locked_until DATETIME NULL,
  PRIMARY KEY (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Журнал отправок форм заявок (ограничение частоты с одного IP)
CREATE TABLE form_submissions_log (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip         VARBINARY(16) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_fsl_ip_created (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
