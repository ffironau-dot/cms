<?php
/**
 * Общие данные всех страниц сайта (шапка, подвал): адрес, телефон,
 * логотип, верхнее меню. Отрисовка страницы в общий каркас.
 */

declare(strict_types=1);

namespace Site;

use Core\Settings;
use Core\Upload;
use Core\View;

final class Layout
{
    /** Каталог шаблонов публичной части. */
    public static function dir(): string
    {
        return CMS_ROOT . '/public/templates';
    }

    /**
     * Отрисовать страницу.
     *
     * @param string $template Имя шаблона без .php (home, category, ...)
     * @param array  $vars     Переменные шаблона; обязательно seo (Seo)
     * @param string $menuPath Адрес, по которому подсвечивается пункт меню
     * @param int    $status   HTTP-код ответа
     */
    public static function render(string $template, array $vars, string $menuPath, int $status = 200): void
    {
        $settings = Settings::all();
        $common = [
            'address'   => $settings['address'],
            'phone'     => $settings['phone'],
            'logoUrl'   => Upload::url('logo', $settings['logo_file']),
            'logoAlt'   => $settings['logo_alt'],
            'menu'      => Menu::build($menuPath),
            'tpl'       => self::dir(),
        ];
        $html = View::render(self::dir() . '/' . $template . '.php', array_merge($common, $vars));
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }
}
