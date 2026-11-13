<?php
/**
 * Общие настройки сайта (таблица settings «ключ — значение»):
 * адрес, телефон, логотип, параметры слайдера, SEO главной страницы.
 */

declare(strict_types=1);

namespace Core;

final class Settings
{
    /** Значения по умолчанию (создаются установщиком). */
    public const DEFAULTS = [
        'address'             => '',
        'phone'               => '',
        'logo_file'           => '',
        'logo_alt'            => '',
        'slider_autoplay'     => '1',
        'slider_interval'     => '7',
        'home_title'          => 'Изготовление вывесок в Москве',
        'home_description'    => 'Изготовление и монтаж вывесок в Москве и области: объёмные буквы, световые короба, неон.',
        'home_og_title'       => '',
        'home_og_description' => '',
        'home_og_image'       => '',
    ];

    /** @var array<string,string>|null Кеш значений на время запроса. */
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            foreach (Db::all('SELECT name, value FROM settings') as $row) {
                self::$cache[$row['name']] = (string) $row['value'];
            }
        }
        return self::$cache;
    }

    public static function get(string $name): string
    {
        return self::all()[$name] ?? '';
    }

    public static function set(string $name, string $value): void
    {
        Db::query(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$name, $value]
        );
        if (self::$cache !== null) {
            self::$cache[$name] = $value;
        }
    }

    /** Сбросить кеш (тесты). */
    public static function reset(): void
    {
        self::$cache = null;
    }
}
