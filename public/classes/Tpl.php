<?php
/**
 * Помощники для шаблонов публичной части (короткие функции вывода).
 */

declare(strict_types=1);

namespace Site;

use Core\Format;
use Core\Html;

final class Tpl
{
    /** Экранирование. */
    public static function e($v): string
    {
        return Html::e($v);
    }

    /**
     * Кликабельный телефон: <a href="tel:+7...">текст как введён</a>.
     * Пустая строка, если телефон не задан.
     */
    public static function phone(string $phone, string $class = ''): string
    {
        if (trim($phone) === '') {
            return '';
        }
        return '<a href="' . Html::e(Format::telHref($phone)) . '"' . ($class !== '' ? ' class="' . Html::e($class) . '"' : '')
            . '>' . Html::e($phone) . '</a>';
    }

    /** Логотип: картинка или текст-заглушка «Лого» (п. 4.2). */
    public static function logo(string $url, string $alt): string
    {
        if ($url === '') {
            return 'Лого';
        }
        return '<img src="' . Html::e($url) . '" alt="' . Html::e($alt) . '">';
    }

    /** Цена для сайта: «1 251 ₽». */
    public static function price($value): string
    {
        $p = Format::price($value);
        return $p === '' ? '' : $p . "\u{00A0}₽";
    }

    /** Атрибут class для пункта меню. */
    public static function navClass(string $base, bool $active): string
    {
        return $base . ($active ? ' nav__link--active' : '');
    }
}
