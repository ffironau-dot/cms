<?php
/**
 * Начальная загрузка CMS.
 *
 * Подключается единой точкой входа index.php (а также служебными
 * скриптами и тестами). Задаёт базовые настройки PHP, регистрирует
 * автозагрузчик классов и объявляет константы с путями.
 *
 * Архитектура (MVC):
 *   include/classes  — общее ядро (namespace Core): БД, конфигурация,
 *                      модели (Core\Models), фильтрация HTML, загрузка
 *                      файлов, почта, реестр адресов и т. д.
 *   public/classes   — контроллеры и вспомогательные классы публичной
 *                      части (namespace Site).
 *   public/templates — шаблоны (представления) публичной части.
 *   admin/classes    — контроллеры административной панели (namespace Admin).
 *   admin/templates  — шаблоны административной панели.
 */

declare(strict_types=1);

// Корневой каталог сайта (на уровень выше папки include).
define('CMS_ROOT', dirname(__DIR__));
// Каталог загружаемых файлов и его адрес на сайте.
define('CMS_UPLOADS', CMS_ROOT . '/uploads');
define('CMS_UPLOADS_URL', '/uploads');
// Адрес административной панели (по ТЗ: domain.com/manet).
define('CMS_ADMIN_PREFIX', '/manet');

// Все даты и время в CMS — московские.
date_default_timezone_set('Europe/Moscow');
mb_internal_encoding('UTF-8');

/**
 * Автозагрузчик классов по пространствам имён:
 *   Core\X\Y  → include/classes/X/Y.php
 *   Site\X    → public/classes/X.php
 *   Admin\X   → admin/classes/X.php
 */
spl_autoload_register(static function (string $class): void {
    $map = [
        'Core\\'  => CMS_ROOT . '/include/classes/',
        'Site\\'  => CMS_ROOT . '/public/classes/',
        'Admin\\' => CMS_ROOT . '/admin/classes/',
    ];
    foreach ($map as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});
