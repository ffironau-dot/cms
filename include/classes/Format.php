<?php
/**
 * Форматирование значений для вывода: цены, телефон, даты.
 */

declare(strict_types=1);

namespace Core;

final class Format
{
    /**
     * Цена для сайта: округление до целого рубля по правилам
     * арифметического округления (п. 5.2), разделение разрядов.
     * Например: 1250.50 → «1 251».
     */
    public static function price($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $rounded = (int) round((float) $value, 0, PHP_ROUND_HALF_UP);
        return number_format($rounded, 0, ',', "\u{00A0}");
    }

    /**
     * Разбор цены из формы или CSV: «1250,50», «1250.50», «1 250,50».
     * Возвращает строку вида «1250.50», null для пустого значения
     * и false, если значение некорректно.
     *
     * @return string|null|false
     */
    public static function parsePrice(string $input)
    {
        $s = str_replace(["\u{00A0}", ' '], '', trim($input));
        if ($s === '') {
            return null;
        }
        $s = str_replace(',', '.', $s);
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $s)) {
            return false;
        }
        return number_format((float) $s, 2, '.', '');
    }

    /** Цена для поля формы в админке: «1250,50» (или пусто). */
    public static function priceInput($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return str_replace('.', ',', number_format((float) $value, 2, '.', ''));
    }

    /**
     * Значение для href="tel:…": из введённого телефона удаляются все
     * символы, кроме цифр и знака «+» (п. 4.3).
     */
    public static function telHref(string $phone): string
    {
        return 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
    }

    /** Дата «13 ноября 2026» для вывода на сайте. */
    public static function dateRu(?string $date): string
    {
        if (!$date) {
            return '';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return '';
        }
        $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
        return (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    }

    /** Транслитерация строки в сегмент URL (для автогенерации адреса). */
    public static function slugify(string $text): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh',
            'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
            'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];
        $text = mb_strtolower($text);
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}
