<?php
/**
 * Верхнее меню сайта и подсветка текущего пункта (п. 4.4 ТЗ).
 *
 * Правила подсветки:
 *  - пункт подсвечивается (класс nav__link--active), если адрес текущей
 *    страницы начинается со ссылки пункта (сравнение по сегментам:
 *    /neon/ подходит для /neon/ и /neon/bar/, но не для /neonovye/);
 *  - если подходит несколько пунктов — подсвечивается пункт с самой
 *    длинной совпавшей ссылкой;
 *  - ссылка «/» (главная) сравнивается только по полному совпадению;
 *  - для подпункта подсвечивается также его родительский пункт;
 *  - для карточки товара сравнение идёт с адресом категории товара
 *    (адрес товара короткий и не содержит адрес категории); так
 *    подсвечивается пункт, ведущий на категорию или её родителя.
 */

declare(strict_types=1);

namespace Site;

use Core\Config;
use Core\Models\MenuItem;

final class Menu
{
    /**
     * Дерево активных пунктов с отметками active.
     *
     * @param string $path Адрес текущей страницы (для товара — адрес его категории)
     */
    public static function build(string $path): array
    {
        $tree = MenuItem::tree(true);
        [$activeId, $activeParent] = self::findActive($tree, $path);
        foreach ($tree as &$item) {
            $item['active'] = (int) $item['id'] === $activeId || (int) $item['id'] === $activeParent;
            foreach ($item['children'] as &$child) {
                $child['active'] = (int) $child['id'] === $activeId;
            }
            unset($child);
        }
        return $tree;
    }

    /**
     * Найти подсвечиваемый пункт. Возвращает [id пункта, id его родителя]
     * (0, если нет).
     */
    public static function findActive(array $tree, string $path): array
    {
        $best = 0;
        $bestParent = 0;
        $bestLen = -1;
        foreach ($tree as $item) {
            $candidates = array_merge([$item], $item['children']);
            foreach ($candidates as $c) {
                $link = self::linkPath((string) $c['url']);
                if ($link === null || !self::matches($link, $path)) {
                    continue;
                }
                if (strlen($link) > $bestLen) {
                    $bestLen = strlen($link);
                    $best = (int) $c['id'];
                    $bestParent = $c['parent_id'] !== null ? (int) $c['parent_id'] : 0;
                }
            }
        }
        return [$best, $bestParent];
    }

    /** Подходит ли ссылка пункта для текущего адреса. */
    public static function matches(string $link, string $path): bool
    {
        if ($link === '/') {
            return $path === '/';
        }
        if ($path === $link) {
            return true;
        }
        // Префикс по целым сегментам.
        return str_starts_with($path, rtrim($link, '/') . '/');
    }

    /**
     * Путь из ссылки пункта меню: относительная ссылка «/neon/» → «/neon/»,
     * абсолютная ссылка на свой домен → её путь. Ссылки на другие сайты,
     * якоря (#quote), tel:, mailto: в подсветке не участвуют (null).
     */
    public static function linkPath(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || $url[0] === '#') {
            return null;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) || str_starts_with($url, '//')) {
            $host = parse_url($url, PHP_URL_HOST);
            $site = parse_url(Config::siteUrl(), PHP_URL_HOST);
            if (!$host || !$site || strcasecmp((string) $host, (string) $site) !== 0) {
                return null;
            }
            $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        } else {
            $path = (string) parse_url($url, PHP_URL_PATH);
            if ($path === '') {
                return null;
            }
            if ($path[0] !== '/') {
                $path = '/' . $path;
            }
        }
        return rawurldecode($path);
    }
}
