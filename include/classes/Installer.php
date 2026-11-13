<?php
/**
 * Создание структуры БД и начальных данных. Используется установщиком
 * start.php и автоматическими тестами.
 */

declare(strict_types=1);

namespace Core;

use PDO;

final class Installer
{
    /** Таблицы CMS в порядке, допускающем удаление (с учётом внешних ключей). */
    public const TABLES = [
        'product_property_values', 'category_property_options', 'category_properties', 'product_images', 'products',
        'categories', 'pages', 'sections', 'routes', 'redirects', 'menu_items', 'slides', 'settings', 'files',
        'login_attempts', 'form_submissions_log',
    ];

    /** Создать таблицы и начальные данные. */
    public static function install(PDO $pdo): void
    {
        Db::setPdo($pdo);
        $sql = (string) file_get_contents(CMS_ROOT . '/include/schema.sql');
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
        $now = Db::now();
        foreach (Settings::DEFAULTS as $name => $value) {
            Db::insert('settings', ['name' => $name, 'value' => $value]);
        }
        // Системная категория «Без категории».
        $catId = Db::insert('categories', [
            'parent_id' => null, 'slug' => 'bez-kategorii', 'name' => 'Без категории',
            'is_active' => 1, 'is_system' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        Db::insert('routes', ['path' => '/bez-kategorii/', 'entity_type' => 'category', 'entity_id' => $catId]);
        // Первый слайд: в системе всегда должен быть хотя бы один активный слайд.
        Db::insert('slides', [
            'eyebrow' => 'Изготовление и монтаж · Москва и область',
            'title' => 'Вывески, которые видно с другой стороны улицы',
            'text' => 'Делаем объёмные буквы, световые короба и неон: замер, дизайн, производство, согласование и монтаж — одним подрядчиком.',
            'btn1_text' => 'Получить расчёт за 1 день', 'btn1_url' => '#quote',
            'btn2_text' => 'Смотреть работы', 'btn2_url' => '#works',
            'sort' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        Settings::reset();
    }

    /** Удалить все таблицы CMS (только для тестов). */
    public static function dropAll(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $t) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $t . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
