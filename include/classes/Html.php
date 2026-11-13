<?php
/**
 * Экранирование вывода и фильтрация HTML в полях (раздел 7 ТЗ).
 *
 *   Большой (длинный) текст — любые теги, не фильтруется.
 *   Малый (короткий) текст  — только u, i, b, strong, em, br, без атрибутов.
 *   Все остальные поля      — теги вырезаются перед сохранением.
 *
 * Фильтрация выполняется разбором HTML через DOMDocument, а не регулярными
 * выражениями: так атрибуты-обработчики (onclick и т. п.), комментарии и
 * некорректная разметка гарантированно не попадают в результат.
 */

declare(strict_types=1);

namespace Core;

use DOMDocument;
use DOMNode;

final class Html
{
    /** Теги, разрешённые в малом тексте. */
    public const SMALL_TEXT_TAGS = ['u', 'i', 'b', 'strong', 'em', 'br'];

    /** Элементы, содержимое которых удаляется целиком (а не только теги). */
    private const DROP_WITH_CONTENT = ['script', 'style', 'template', 'noscript', 'iframe', 'object', 'embed', 'svg', 'math', 'head', 'title'];

    /** Экранирование для вывода в HTML (текст и значения атрибутов). */
    public static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /** Фильтр для малого текста: оставляет только разрешённые теги без атрибутов. */
    public static function filterSmall(?string $html): string
    {
        return trim(self::filter((string) $html, self::SMALL_TEXT_TAGS));
    }

    /**
     * Фильтр для простых полей: вырезает все теги.
     * Возвращает обычный текст (без HTML-сущностей), который при выводе экранируется.
     */
    public static function filterPlain(?string $value): string
    {
        $value = (string) $value;
        if ($value === '' || (!str_contains($value, '<') && !str_contains($value, '&'))) {
            return trim(self::stripControl($value));
        }
        $html = self::filter($value, []);
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Пробелы, оставшиеся на месте вырезанных тегов, схлопываются.
        $text = (string) preg_replace('/[ \t]{2,}/u', ' ', $text);
        return trim(self::stripControl($text));
    }

    /** Удаляет управляющие символы (кроме перевода строки и табуляции). */
    public static function stripControl(string $s): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    }

    /**
     * Общий фильтр: разбирает фрагмент HTML и собирает его заново,
     * оставляя только теги из $allowed (без атрибутов). Текст экранируется.
     */
    private static function filter(string $html, array $allowed): string
    {
        $html = self::stripControl($html);
        if (trim($html) === '') {
            return '';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="__root">' . $html . '</div></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('__root');
        if ($root === null) {
            return self::e(strip_tags($html));
        }
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= self::walk($child, $allowed);
        }
        return $out;
    }

    private static function walk(DOMNode $node, array $allowed): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return self::e($node->nodeValue);
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            // Комментарии и прочие узлы отбрасываются.
            return '';
        }
        $tag = strtolower($node->nodeName);
        if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
            return '';
        }
        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= self::walk($child, $allowed);
        }
        if (in_array($tag, $allowed, true)) {
            return $tag === 'br' ? '<br>' : '<' . $tag . '>' . $inner . '</' . $tag . '>';
        }
        // Блочные элементы при вырезании разделяем пробелом, чтобы слова не слипались.
        if (in_array($tag, ['p', 'div', 'li', 'tr', 'td', 'th', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'br', 'section', 'article'], true)) {
            return ' ' . $inner . ' ';
        }
        return $inner;
    }
}
