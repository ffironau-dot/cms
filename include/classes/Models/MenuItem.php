<?php
/**
 * Модель «Пункт верхнего меню» (п. 4.4 ТЗ). Два уровня вложенности.
 */

declare(strict_types=1);

namespace Core\Models;

use Core\Db;
use Core\Html;

final class MenuItem
{
    public static function find(int $id): ?array
    {
        return Db::row('SELECT * FROM menu_items WHERE id = ?', [$id]);
    }

    /**
     * Дерево меню: пункты первого уровня по порядку, у каждого — children.
     *
     * @param bool $onlyActive Только активные (для сайта): неактивный
     *                         родитель скрывается вместе с подпунктами.
     */
    public static function tree(bool $onlyActive = false): array
    {
        $rows = Db::all('SELECT * FROM menu_items ' . ($onlyActive ? 'WHERE is_active = 1 ' : '') . 'ORDER BY sort, id');
        $roots = [];
        $children = [];
        foreach ($rows as $r) {
            $r['children'] = [];
            if ($r['parent_id'] === null) {
                $roots[(int) $r['id']] = $r;
            } else {
                $children[] = $r;
            }
        }
        foreach ($children as $c) {
            if (isset($roots[(int) $c['parent_id']])) {
                $roots[(int) $c['parent_id']]['children'][] = $c;
            }
        }
        return array_values($roots);
    }

    /** Пункты первого уровня (для выбора родителя). */
    public static function roots(): array
    {
        return Db::all('SELECT * FROM menu_items WHERE parent_id IS NULL ORDER BY sort, id');
    }

    public static function hasChildren(int $id): bool
    {
        return (int) Db::value('SELECT COUNT(*) FROM menu_items WHERE parent_id = ?', [$id]) > 0;
    }

    /** @return array{errors:array, id:?int} */
    public static function save(array $in, ?int $id = null): array
    {
        if ($id && !self::find($id)) {
            return ['errors' => ['Пункт меню не найден.'], 'id' => null];
        }
        $level = (int) ($in['level'] ?? 1) === 2 ? 2 : 1;
        $parentId = $level === 2 && !empty($in['parent_id']) ? (int) $in['parent_id'] : null;
        $data = [
            'url'        => Html::filterPlain($in['url'] ?? ''),
            'anchor'     => Html::filterPlain($in['anchor'] ?? ''),
            'link_title' => Html::filterPlain($in['link_title'] ?? ''),
            'sort'       => (int) ($in['sort'] ?? 0),
            'is_active'  => !empty($in['is_active']) ? 1 : 0,
            'parent_id'  => $parentId,
        ];
        $errors = [];
        if ($data['anchor'] === '') {
            $errors[] = 'Укажите анкор (текст пункта меню).';
        }
        if ($data['url'] !== '' && preg_match('/^\s*(javascript|data|vbscript):/i', $data['url'])) {
            $errors[] = 'Недопустимая ссылка.';
        }
        if ($level === 2) {
            $parent = $parentId ? self::find($parentId) : null;
            if (!$parent) {
                $errors[] = 'Для пункта второго уровня выберите родительский пункт.';
            } elseif ($parent['parent_id'] !== null) {
                $errors[] = 'Родителем может быть только пункт первого уровня (вложенность — не более двух уровней).';
            } elseif ($parentId === $id) {
                $errors[] = 'Пункт не может быть родителем самого себя.';
            }
            if ($id && self::hasChildren($id)) {
                $errors[] = 'У пункта есть подпункты, поэтому он должен оставаться пунктом первого уровня.';
            }
            if ($data['url'] === '') {
                $errors[] = 'Укажите ссылку: без ссылки может быть только пункт первого уровня, раскрывающий подпункты.';
            }
        }
        if ($errors) {
            return ['errors' => $errors, 'id' => $id];
        }
        if ($id === null) {
            $id = Db::insert('menu_items', $data);
        } else {
            Db::update('menu_items', $id, $data);
        }
        return ['errors' => [], 'id' => $id];
    }

    /** Удалить пункт; запрещено, пока есть подпункты. Возвращает ошибку или null. */
    public static function delete(int $id): ?string
    {
        if (!self::find($id)) {
            return 'Пункт меню не найден.';
        }
        if (self::hasChildren($id)) {
            return 'Нельзя удалить пункт, у которого есть подпункты: сначала удалите все его подпункты.';
        }
        Db::query('DELETE FROM menu_items WHERE id = ?', [$id]);
        return null;
    }
}
