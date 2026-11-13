<?php
/**
 * Единое пространство адресов сайта (п. 5.1 и 6 ТЗ).
 *
 * Категории, товары, разделы и текстовые страницы имеют адреса в одном
 * пространстве. Текущие адреса хранятся в таблице routes (уникальный
 * индекс по path гарантирует, что один адрес не займут две сущности),
 * старые адреса — в таблице redirects (постоянные редиректы 301).
 *
 * Формат адреса: начинается и заканчивается слешем, сегменты состоят из
 * строчных латинских букв, цифр и дефиса: /neonovye-vyveski/dlya-bara/
 */

declare(strict_types=1);

namespace Core;

final class Urls
{
    public const CATEGORY = 'category';
    public const PRODUCT  = 'product';
    public const SECTION  = 'section';
    public const PAGE     = 'page';

    /**
     * Зарезервированные первые сегменты адреса: служебные и загружаемые
     * папки, точка входа формы заявки. Адреса, начинающиеся с /manet,
     * проверяются отдельно (запрещено всё, что начинается с «/manet»).
     */
    public const RESERVED_SEGMENTS = [
        'assets', 'uploads', 'include', 'public', 'admin', 'tests', 'docs', 'docker',
        'send', 'tools', 'index.php', 'start.php', 'passhash.php', 'sitemap.xml', 'robots.txt',
    ];

    /** Корректен ли один сегмент адреса. */
    public static function isValidSegment(string $segment): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $segment) && strlen($segment) <= 200;
    }

    /**
     * Нормализация пути, введённого целиком (адрес отдельной текстовой
     * страницы): «o-kompanii/komanda» → «/o-kompanii/komanda/».
     * Возвращает null, если путь некорректен.
     */
    public static function normalizePath(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        $segments = array_values(array_filter(explode('/', $input), static fn($s) => $s !== ''));
        if (!$segments) {
            return null;
        }
        foreach ($segments as $s) {
            if (!self::isValidSegment($s)) {
                return null;
            }
        }
        $path = '/' . implode('/', $segments) . '/';
        return strlen($path) <= 480 ? $path : null;
    }

    /** Зарезервирован ли адрес (п. 5.1: /manet, sitemap.xml, robots.txt, служебные папки). */
    public static function isReserved(string $path): bool
    {
        if (str_starts_with($path, CMS_ADMIN_PREFIX)) {
            return true;
        }
        $first = explode('/', trim($path, '/'))[0] ?? '';
        return $path === '/' || in_array($first, self::RESERVED_SEGMENTS, true);
    }

    /** Текущий адрес сущности или null. */
    public static function pathOf(string $type, int $id): ?string
    {
        $v = Db::value('SELECT path FROM routes WHERE entity_type = ? AND entity_id = ?', [$type, $id]);
        return $v === null ? null : (string) $v;
    }

    /** Найти сущность по адресу. */
    public static function find(string $path): ?array
    {
        return Db::row('SELECT entity_type, entity_id FROM routes WHERE path = ?', [$path]);
    }

    /** Найти редирект по старому адресу. */
    public static function findRedirect(string $path): ?string
    {
        $v = Db::value('SELECT new_path FROM redirects WHERE old_path = ?', [$path]);
        return $v === null ? null : (string) $v;
    }

    /**
     * Проверка, можно ли назначить адрес. Возвращает текст ошибки или null.
     *
     * @param array $ignore Сущности, чьи текущие адреса не считаются занятыми
     *                      (сами перемещаемые сущности): [[type, id], ...]
     */
    public static function checkAvailable(string $path, array $ignore = []): ?string
    {
        if (self::isReserved($path)) {
            return 'Адрес ' . $path . ' зарезервирован системой, выберите другой.';
        }
        $owner = self::find($path);
        if ($owner !== null) {
            foreach ($ignore as [$t, $i]) {
                if ($owner['entity_type'] === $t && (int) $owner['entity_id'] === (int) $i) {
                    return null;
                }
            }
            $names = [
                self::CATEGORY => 'категорией',
                self::PRODUCT  => 'товаром',
                self::SECTION  => 'разделом',
                self::PAGE     => 'текстовой страницей',
            ];
            return 'Адрес ' . $path . ' уже занят ' . ($names[$owner['entity_type']] ?? 'другой страницей') . '.';
        }
        return null;
    }

    /**
     * Назначить адреса сущностям (создание или смена адреса, в том числе
     * каскадная). Выполняется в транзакции.
     *
     * Для каждой сущности, чей адрес изменился, создаётся редирект 301
     * со старого адреса на новый; цепочки схлопываются (A→B→C даёт A→C),
     * редирект с адреса, который снова стал живым, удаляется.
     *
     * @param array $moves [[type, id, newPath], ...]
     * @throws \RuntimeException если какой-либо адрес занят или зарезервирован
     */
    public static function assign(array $moves): void
    {
        if (!$moves) {
            return;
        }
        Db::transaction(static function () use ($moves): void {
            $ignore = array_map(static fn($m) => [$m[0], $m[1]], $moves);
            $seen = [];
            foreach ($moves as [$type, $id, $path]) {
                if (isset($seen[$path])) {
                    throw new \RuntimeException('Адрес ' . $path . ' получается у нескольких страниц одновременно.');
                }
                $seen[$path] = true;
                $error = self::checkAvailable($path, $ignore);
                if ($error !== null) {
                    throw new \RuntimeException($error);
                }
            }

            // Старые адреса перемещаемых сущностей.
            $old = [];
            foreach ($moves as [$type, $id, $path]) {
                $old[$type . ':' . $id] = self::pathOf($type, (int) $id);
            }

            // Первый проход: временные адреса, чтобы при обмене адресами
            // между перемещаемыми сущностями не сработал уникальный индекс.
            foreach ($moves as [$type, $id, $path]) {
                if ($old[$type . ':' . $id] !== null) {
                    Db::query(
                        'UPDATE routes SET path = ? WHERE entity_type = ? AND entity_id = ?',
                        ['#tmp#' . $type . '#' . $id, $type, (int) $id]
                    );
                }
            }

            // Второй проход: окончательные адреса и редиректы.
            foreach ($moves as [$type, $id, $path]) {
                $was = $old[$type . ':' . $id];
                if ($was === null) {
                    Db::query('INSERT INTO routes (path, entity_type, entity_id) VALUES (?, ?, ?)', [$path, $type, (int) $id]);
                } else {
                    Db::query('UPDATE routes SET path = ? WHERE entity_type = ? AND entity_id = ?', [$path, $type, (int) $id]);
                }
                // Адрес снова живой — редирект с него больше не нужен.
                Db::query('DELETE FROM redirects WHERE old_path = ?', [$path]);
                if ($was !== null && $was !== $path) {
                    self::addRedirect($was, $path);
                }
            }
        });
    }

    /** Добавить редирект old → new со схлопыванием цепочек. */
    private static function addRedirect(string $old, string $new): void
    {
        // Все редиректы, которые вели на старый адрес, теперь ведут сразу на новый.
        Db::query('UPDATE redirects SET new_path = ? WHERE new_path = ?', [$new, $old]);
        // Редирект «сам на себя» невозможен.
        Db::query('DELETE FROM redirects WHERE old_path = new_path');
        Db::query(
            'INSERT INTO redirects (old_path, new_path, created_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE new_path = VALUES(new_path), created_at = VALUES(created_at)',
            [$old, $new, Db::now()]
        );
    }

    /**
     * Удалить адрес сущности (при удалении самой сущности).
     * Редиректы, которые вели на этот адрес, тоже удаляются: адреса
     * удалённых сущностей отдают 404 (п. 5.1).
     */
    public static function remove(string $type, int $id): void
    {
        $path = self::pathOf($type, $id);
        if ($path === null) {
            return;
        }
        Db::query('DELETE FROM routes WHERE entity_type = ? AND entity_id = ?', [$type, $id]);
        Db::query('DELETE FROM redirects WHERE new_path = ?', [$path]);
    }
}
