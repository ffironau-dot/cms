<?php
/**
 * Данные для <head> страницы: title, description, canonical, OG-теги,
 * а также хлебные крошки с микроразметкой BreadcrumbList.
 */

declare(strict_types=1);

namespace Site;

use Core\Config;

final class Seo
{
    public string $title = '';
    public string $description = '';
    /** Путь canonical (с параметром page, если нужно) — без домена. */
    public string $canonical = '';
    public string $ogTitle = '';
    public string $ogDescription = '';
    /** Путь к OG-изображению (/uploads/...) или пусто. */
    public string $ogImage = '';
    public string $ogType = 'website';

    /** Абсолютный адрес: домен из конфигурации + путь. */
    public static function absolute(string $path): string
    {
        if ($path === '' || preg_match('#^https?://#i', $path)) {
            return $path;
        }
        // Кириллица и пробелы в пути кодируются, слеши и строка параметров — нет.
        $query = '';
        if (($q = strpos($path, '?')) !== false) {
            $query = substr($path, $q);
            $path = substr($path, 0, $q);
        }
        $encoded = implode('/', array_map('rawurlencode', array_map('rawurldecode', explode('/', $path))));
        return Config::siteUrl() . $encoded . $query;
    }
}
