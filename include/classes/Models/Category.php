<?php
/**
 * Модель «Категория товаров» (п. 5.1 ТЗ).
 *
 * Категории вложенные, максимум два уровня. Адрес подкатегории —
 * полный путь: сегмент родителя + собственный сегмент. Системная
 * категория «Без категории» (is_system = 1) не удаляется, её название
 * не меняется, но адрес и активность менять можно.
 */

declare(strict_types=1);

namespace Core\Models;

use Core\Db;
use Core\Html;
use Core\Upload;
use Core\Urls;

final class Category
{
    /** Папка загрузок для изображений категорий. */
    public const UPLOAD_DIR = 'categories';
    /** Название системной категории. */
    public const SYSTEM_NAME = 'Без категории';

    /** @var array<int,array>|null Кеш всех категорий на время запроса. */
    private static ?array $all = null;

    /** Все категории [id => row] (с полем path). Категорий немного, кешируем. */
    public static function all(bool $fresh = false): array
    {
        if (self::$all === null || $fresh) {
            self::$all = [];
            $rows = Db::all(
                "SELECT c.*, r.path FROM categories c
                 LEFT JOIN routes r ON r.entity_type = 'category' AND r.entity_id = c.id
                 ORDER BY c.is_system DESC, c.name"
            );
            foreach ($rows as $row) {
                self::$all[(int) $row['id']] = $row;
            }
        }
        return self::$all;
    }

    public static function find(int $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    /** Системная категория «Без категории». */
    public static function system(): array
    {
        foreach (self::all() as $c) {
            if ((int) $c['is_system'] === 1) {
                return $c;
            }
        }
        throw new \RuntimeException('Системная категория «Без категории» не найдена.');
    }

    /** Дочерние категории. */
    public static function children(int $id): array
    {
        return array_values(array_filter(self::all(), static fn($c) => (int) $c['parent_id'] === $id));
    }

    /** Категории первого уровня. */
    public static function roots(): array
    {
        return array_values(array_filter(self::all(), static fn($c) => $c['parent_id'] === null));
    }

    /**
     * Дерево для выпадающих списков: [[row, level], ...] —
     * родитель, затем его подкатегории.
     */
    public static function flatTree(): array
    {
        $out = [];
        foreach (self::roots() as $root) {
            $out[] = [$root, 1];
            foreach (self::children((int) $root['id']) as $child) {
                $out[] = [$child, 2];
            }
        }
        return $out;
    }

    /** id категории и всех её подкатегорий. */
    public static function withDescendants(int $id): array
    {
        $ids = [$id];
        foreach (self::children($id) as $c) {
            $ids[] = (int) $c['id'];
        }
        return $ids;
    }

    /**
     * Видна ли категория на сайте: активна сама и активен её родитель
     * (неактивная категория скрывает всё, что внутри неё).
     */
    public static function isVisible(array $cat): bool
    {
        if ((int) $cat['is_active'] !== 1) {
            return false;
        }
        if ($cat['parent_id'] !== null) {
            $parent = self::find((int) $cat['parent_id']);
            return $parent !== null && (int) $parent['is_active'] === 1;
        }
        return true;
    }

    /** id всех видимых на сайте категорий. */
    public static function visibleIds(): array
    {
        $ids = [];
        foreach (self::all() as $c) {
            if (self::isVisible($c)) {
                $ids[] = (int) $c['id'];
            }
        }
        return $ids;
    }

    /** Цепочка «родитель → категория» для хлебных крошек. */
    public static function chain(array $cat): array
    {
        $chain = [$cat];
        if ($cat['parent_id'] !== null && ($p = self::find((int) $cat['parent_id']))) {
            array_unshift($chain, $p);
        }
        return $chain;
    }

    /**
     * Количество товаров в категории с учётом подкатегорий (вычисляемое свойство).
     *
     * @param bool $onlyActive Считать только активные товары (для сайта)
     */
    public static function productCount(int $id, bool $onlyActive = false): int
    {
        $ids = self::withDescendants($id);
        $sql = 'SELECT COUNT(*) FROM products WHERE category_id IN (' . Db::placeholders($ids) . ')';
        if ($onlyActive) {
            $sql .= ' AND is_active = 1';
        }
        return (int) Db::value($sql, $ids);
    }

    /**
     * Свойства товаров категории с учётом наследования: сначала свойства
     * родительской категории, затем собственные.
     */
    public static function properties(int $id): array
    {
        $cat = self::find($id);
        if (!$cat) {
            return [];
        }
        $ids = $cat['parent_id'] !== null ? [(int) $cat['parent_id'], $id] : [$id];
        $rows = Db::all(
            'SELECT p.*, c.name AS category_name FROM category_properties p
             JOIN categories c ON c.id = p.category_id
             WHERE p.category_id IN (' . Db::placeholders($ids) . ')
             ORDER BY (p.category_id = ?) ASC, p.sort, p.id',
            array_merge($ids, [$id])
        );
        foreach ($rows as &$r) {
            $r['options'] = $r['type'] === 'list'
                ? Db::all('SELECT id, value FROM category_property_options WHERE property_id = ? ORDER BY sort, id', [(int) $r['id']])
                : [];
        }
        return $rows;
    }

    /**
     * Создать или обновить категорию.
     *
     * @param array    $in Данные формы (уже без файлов); ключ image — имя
     *                     нового файла, если изображение заменено.
     * @param int|null $id id существующей категории или null для новой
     * @return array{errors:array, id:?int}
     */
    public static function save(array $in, ?int $id = null): array
    {
        $errors = [];
        $current = $id ? self::find($id) : null;
        if ($id && !$current) {
            return ['errors' => ['Категория не найдена.'], 'id' => null];
        }
        $isSystem = $current && (int) $current['is_system'] === 1;

        $data = [
            'name'             => $isSystem ? self::SYSTEM_NAME : Html::filterPlain($in['name'] ?? ''),
            'slug'             => strtolower(trim((string) ($in['slug'] ?? ''))),
            'meta_title'       => Html::filterPlain($in['meta_title'] ?? ''),
            'meta_description' => Html::filterPlain($in['meta_description'] ?? ''),
            'og_title'         => Html::filterPlain($in['og_title'] ?? ''),
            'og_description'   => Html::filterPlain($in['og_description'] ?? ''),
            'text_small'       => Html::filterSmall($in['text_small'] ?? ''),
            'text_big'         => (string) ($in['text_big'] ?? ''),
            'is_active'        => !empty($in['is_active']) ? 1 : 0,
        ];
        $parentId = !empty($in['parent_id']) ? (int) $in['parent_id'] : null;

        if ($data['name'] === '') {
            $errors[] = 'Укажите название категории.';
        }
        if (!Urls::isValidSegment($data['slug'])) {
            $errors[] = 'URL категории: только строчные латинские буквы, цифры и дефис (например, neonovye-vyveski).';
        }
        if ($parentId !== null) {
            $parent = self::find($parentId);
            if ($isSystem) {
                $errors[] = 'Категория «Без категории» может быть только категорией первого уровня.';
            } elseif (!$parent) {
                $errors[] = 'Родительская категория не найдена.';
            } elseif ($parentId === $id) {
                $errors[] = 'Категория не может быть родителем самой себя.';
            } elseif ($parent['parent_id'] !== null) {
                $errors[] = 'Вложенность категорий — не более двух уровней: выберите родителем категорию первого уровня.';
            } elseif ((int) $parent['is_system'] === 1) {
                $errors[] = 'Категория «Без категории» не может быть родительской.';
            } elseif ($id && self::children($id)) {
                $errors[] = 'У категории есть подкатегории, поэтому она должна оставаться категорией первого уровня.';
            }
        }
        if ($errors) {
            return ['errors' => $errors, 'id' => $id];
        }

        $parentPath = $parentId !== null ? (string) self::find($parentId)['path'] : '/';
        $newPath = $parentPath . $data['slug'] . '/';
        if (array_key_exists('image', $in)) {
            $data['image'] = $in['image'];
        }
        $data['parent_id'] = $parentId;
        $data['updated_at'] = Db::now();

        try {
            $id = Db::transaction(static function () use ($data, $id, $current, $newPath): int {
                if ($id === null) {
                    $data['created_at'] = $data['updated_at'];
                    $data['is_system'] = 0;
                    $id = Db::insert('categories', $data);
                    Urls::assign([[Urls::CATEGORY, $id, $newPath]]);
                    return $id;
                }
                Db::update('categories', $id, $data);
                $moves = [];
                if ($current['path'] !== $newPath) {
                    // Каскадная смена адреса: подкатегории получают новый префикс.
                    $moves[] = [Urls::CATEGORY, $id, $newPath];
                    foreach (self::children($id) as $child) {
                        $moves[] = [Urls::CATEGORY, (int) $child['id'], $newPath . $child['slug'] . '/'];
                        Db::query('UPDATE categories SET updated_at = ? WHERE id = ?', [Db::now(), (int) $child['id']]);
                    }
                }
                Urls::assign($moves);
                return $id;
            });
        } catch (\RuntimeException $e) {
            return ['errors' => [$e->getMessage()], 'id' => $id];
        } finally {
            self::$all = null;
        }
        return ['errors' => [], 'id' => $id];
    }

    /**
     * Удалить категорию. Запрещено для «Без категории» и пока в категории
     * есть подкатегории или товары (п. 5.1). Возвращает текст ошибки или null.
     */
    public static function delete(int $id): ?string
    {
        $cat = self::find($id);
        if (!$cat) {
            return 'Категория не найдена.';
        }
        if ((int) $cat['is_system'] === 1) {
            return 'Категорию «Без категории» удалить нельзя.';
        }
        if (self::children($id)) {
            return 'Нельзя удалить категорию, пока в ней есть подкатегории: сначала удалите или перенесите их.';
        }
        $count = (int) Db::value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id]);
        if ($count > 0) {
            return 'Нельзя удалить категорию, пока в ней есть товары (' . $count . '): сначала удалите их или перенесите в другую категорию, например «Без категории».';
        }
        Db::transaction(static function () use ($id): void {
            Urls::remove(Urls::CATEGORY, $id);
            Db::query('DELETE FROM categories WHERE id = ?', [$id]);
        });
        Upload::delete(self::UPLOAD_DIR, $cat['image']);
        self::$all = null;
        return null;
    }

    /** Сбросить кеш (после изменений из других моделей и в тестах). */
    public static function reset(): void
    {
        self::$all = null;
    }
}
