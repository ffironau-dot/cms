<?php
/**
 * Модель «Товар» (п. 5.2 ТЗ).
 *
 * Адрес товара короткий (/nazvanie-tovara/) и не зависит от категории.
 * Артикул уникален. Цены хранятся с копейками (DECIMAL), на сайте
 * выводятся округлёнными до рубля.
 */

declare(strict_types=1);

namespace Core\Models;

use Core\Db;
use Core\Format;
use Core\Html;
use Core\Upload;
use Core\Urls;

final class Product
{
    public const UPLOAD_DIR = 'products';

    /** Товар по id (с адресом). */
    public static function find(int $id): ?array
    {
        return Db::row(
            "SELECT p.*, r.path FROM products p
             LEFT JOIN routes r ON r.entity_type = 'product' AND r.entity_id = p.id
             WHERE p.id = ?",
            [$id]
        );
    }

    public static function findBySku(string $sku): ?array
    {
        $id = Db::value('SELECT id FROM products WHERE sku = ?', [$sku]);
        return $id === null ? null : self::find((int) $id);
    }

    /** Дополнительные фото товара по порядку. */
    public static function images(int $id): array
    {
        return Db::all('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort, id', [$id]);
    }

    /**
     * Значения свойств товара: [property_id => ['value' => ..., 'option_id' => ...]].
     */
    public static function propertyValues(int $id): array
    {
        $out = [];
        foreach (Db::all('SELECT * FROM product_property_values WHERE product_id = ?', [$id]) as $row) {
            $out[(int) $row['property_id']] = $row;
        }
        return $out;
    }

    /**
     * Характеристики для вывода на сайте: [[название, значение], ...] —
     * только заполненные свойства, в порядке свойств категории.
     */
    public static function specs(array $product): array
    {
        $values = self::propertyValues((int) $product['id']);
        $out = [];
        foreach (Category::properties((int) $product['category_id']) as $prop) {
            $v = $values[(int) $prop['id']] ?? null;
            if (!$v) {
                continue;
            }
            $text = '';
            switch ($prop['type']) {
                case 'list':
                    foreach ($prop['options'] as $o) {
                        if ((int) $o['id'] === (int) $v['option_id']) {
                            $text = $o['value'];
                        }
                    }
                    break;
                case 'bool':
                    $text = $v['value'] === '1' ? 'Да' : ($v['value'] === '0' ? 'Нет' : '');
                    break;
                case 'number':
                    $text = $v['value'] === null ? '' : str_replace('.', ',', (string) $v['value']);
                    break;
                default:
                    $text = (string) $v['value'];
            }
            if ($text !== '') {
                $out[] = [$prop['name'], $text];
            }
        }
        return $out;
    }

    /**
     * Создать или обновить товар.
     *
     * Ключи $in: category_id, sku, slug, name, meta_title, meta_description,
     * og_title, og_description, text_small, text_big, price_old, price,
     * is_active; image — имя нового файла основного фото (если заменено).
     *
     * $partial = true (импорт CSV): пустые значения не перезаписывают
     * существующие поля — обновляются только переданные непустые ключи.
     *
     * @return array{errors:array, id:?int, category_changed:bool}
     */
    public static function save(array $in, ?int $id = null, bool $partial = false): array
    {
        $current = $id ? self::find($id) : null;
        if ($id && !$current) {
            return ['errors' => ['Товар не найден.'], 'id' => null, 'category_changed' => false];
        }
        $has = static fn(string $k): bool => array_key_exists($k, $in) && (!$partial || trim((string) $in[$k]) !== '');
        $val = static fn(string $k, $default = '') => $has($k) ? $in[$k] : ($current[$k] ?? $default);

        $errors = [];
        $data = [];
        $data['sku'] = Html::filterPlain((string) $val('sku'));
        $data['name'] = Html::filterPlain((string) $val('name'));
        $data['slug'] = strtolower(trim((string) $val('slug')));
        foreach (['meta_title', 'meta_description', 'og_title', 'og_description'] as $k) {
            $data[$k] = Html::filterPlain((string) $val($k));
        }
        $data['text_small'] = Html::filterSmall((string) $val('text_small'));
        $data['text_big'] = (string) $val('text_big');

        // Цены: в форме и CSV — «1250,50»; в БД — DECIMAL.
        foreach (['price_old' => 'Старая цена', 'price' => 'Новая цена'] as $k => $label) {
            if ($has($k)) {
                $p = Format::parsePrice((string) $in[$k]);
                if ($p === false) {
                    $errors[] = $label . ': укажите число, например 1250,50.';
                    $p = null;
                }
                $data[$k] = $p;
            } else {
                $data[$k] = $current[$k] ?? null;
            }
        }
        if (!$partial) {
            // Форма: чекбокс не передаётся, если снят.
            $data['is_active'] = !empty($in['is_active']) ? 1 : 0;
        } elseif ($has('is_active')) {
            $data['is_active'] = (string) $in['is_active'] === '1' ? 1 : 0;
        } else {
            // Импорт: пустая ячейка — у нового товара «активен», у существующего без изменений.
            $data['is_active'] = $current ? (int) $current['is_active'] : 1;
        }

        // Категория: по умолчанию — «Без категории».
        $categoryId = $has('category_id') ? (int) $in['category_id'] : ($current ? (int) $current['category_id'] : 0);
        if ($categoryId === 0) {
            $categoryId = (int) Category::system()['id'];
        }
        if (!Category::find($categoryId)) {
            $errors[] = 'Категория не найдена.';
        }
        $data['category_id'] = $categoryId;

        if ($data['sku'] === '') {
            $errors[] = 'Укажите артикул.';
        } elseif (mb_strlen($data['sku']) > 100) {
            $errors[] = 'Артикул — не длиннее 100 символов.';
        } else {
            $other = Db::value('SELECT id FROM products WHERE sku = ? AND id <> ?', [$data['sku'], (int) $id]);
            if ($other !== null) {
                $errors[] = 'Артикул ' . $data['sku'] . ' уже есть у другого товара.';
            }
        }
        if ($data['name'] === '') {
            $errors[] = 'Укажите название товара.';
        }
        if ($data['slug'] === '' && $data['name'] !== '') {
            $data['slug'] = Format::slugify($data['name']);
        }
        if (!Urls::isValidSegment($data['slug'])) {
            $errors[] = 'URL товара: только строчные латинские буквы, цифры и дефис (например, akrilovye-bukvy).';
        }
        if ($errors) {
            return ['errors' => $errors, 'id' => $id, 'category_changed' => false];
        }

        if (array_key_exists('image', $in)) {
            $data['image'] = $in['image'];
        }
        $data['updated_at'] = Db::now();
        $path = '/' . $data['slug'] . '/';
        $categoryChanged = $current && (int) $current['category_id'] !== $categoryId;

        try {
            $id = Db::transaction(static function () use ($data, $id, $path, $categoryChanged): int {
                if ($id === null) {
                    $data['created_at'] = $data['updated_at'];
                    $id = Db::insert('products', $data);
                } else {
                    Db::update('products', $id, $data);
                    if ($categoryChanged) {
                        // При переносе в другую категорию значения прежних свойств удаляются (п. 5.2).
                        Db::query('DELETE FROM product_property_values WHERE product_id = ?', [$id]);
                    }
                }
                Urls::assign([[Urls::PRODUCT, $id, $path]]);
                return $id;
            });
        } catch (\RuntimeException $e) {
            return ['errors' => [$e->getMessage()], 'id' => $id, 'category_changed' => false];
        }
        return ['errors' => [], 'id' => $id, 'category_changed' => $categoryChanged];
    }

    /**
     * Сохранить значения свойств категории из формы.
     * $values: [property_id => строка из формы].
     *
     * @return array Ошибки
     */
    public static function saveProperties(int $id, array $values): array
    {
        $product = self::find($id);
        if (!$product) {
            return ['Товар не найден.'];
        }
        $errors = [];
        $rows = [];
        foreach (Category::properties((int) $product['category_id']) as $prop) {
            $pid = (int) $prop['id'];
            $raw = trim((string) ($values[$pid] ?? ''));
            $value = null;
            $option = null;
            switch ($prop['type']) {
                case 'number':
                    if ($raw !== '') {
                        $n = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $raw);
                        if (!is_numeric($n)) {
                            $errors[] = 'Свойство «' . $prop['name'] . '»: укажите число.';
                            continue 2;
                        }
                        $value = $n;
                    }
                    break;
                case 'bool':
                    $value = $raw === '1' ? '1' : ($raw === '0' ? '0' : null);
                    break;
                case 'list':
                    foreach ($prop['options'] as $o) {
                        if ((string) $o['id'] === $raw) {
                            $option = (int) $o['id'];
                        }
                    }
                    break;
                default:
                    $value = Html::filterPlain($raw);
                    if ($value === '') {
                        $value = null;
                    }
            }
            $rows[$pid] = [$value, $option];
        }
        if ($errors) {
            return $errors;
        }
        Db::transaction(static function () use ($id, $rows): void {
            foreach ($rows as $pid => [$value, $option]) {
                if ($value === null && $option === null) {
                    Db::query('DELETE FROM product_property_values WHERE product_id = ? AND property_id = ?', [$id, $pid]);
                } else {
                    Db::query(
                        'INSERT INTO product_property_values (product_id, property_id, value, option_id) VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE value = VALUES(value), option_id = VALUES(option_id)',
                        [$id, $pid, $value, $option]
                    );
                }
            }
        });
        return [];
    }

    /** Удалить товар вместе с файлами фото (основным и дополнительными). */
    public static function delete(int $id): void
    {
        $product = self::find($id);
        if (!$product) {
            return;
        }
        $images = self::images($id);
        Db::transaction(static function () use ($id): void {
            Urls::remove(Urls::PRODUCT, $id);
            Db::query('DELETE FROM products WHERE id = ?', [$id]);
        });
        Upload::delete(self::UPLOAD_DIR, $product['image']);
        foreach ($images as $img) {
            Upload::delete(self::UPLOAD_DIR, $img['file']);
        }
    }

    /** Добавить дополнительное фото. */
    public static function addImage(int $id, string $file, string $alt, int $sort): void
    {
        Db::insert('product_images', [
            'product_id' => $id,
            'file'       => $file,
            'alt'        => Html::filterPlain($alt),
            'sort'       => $sort,
        ]);
        Db::query('UPDATE products SET updated_at = ? WHERE id = ?', [Db::now(), $id]);
    }

    /** Удалить дополнительное фото (и файл). */
    public static function deleteImage(int $imageId): ?int
    {
        $img = Db::row('SELECT * FROM product_images WHERE id = ?', [$imageId]);
        if (!$img) {
            return null;
        }
        Db::query('DELETE FROM product_images WHERE id = ?', [$imageId]);
        Upload::delete(self::UPLOAD_DIR, $img['file']);
        return (int) $img['product_id'];
    }

    /**
     * Список товаров для админки: поиск по артикулу и названию, фильтр по
     * категории (с подкатегориями), пагинация.
     *
     * @return array{rows:array, total:int}
     */
    public static function adminList(string $q, int $categoryId, int $limit, int $offset): array
    {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(p.sku LIKE ? OR p.name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($categoryId > 0) {
            $ids = Category::withDescendants($categoryId);
            $where[] = 'p.category_id IN (' . Db::placeholders($ids) . ')';
            $params = array_merge($params, $ids);
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Db::value('SELECT COUNT(*) FROM products p ' . $sqlWhere, $params);
        $rows = Db::all(
            "SELECT p.*, r.path, c.name AS category_name FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN routes r ON r.entity_type = 'product' AND r.entity_id = p.id
             $sqlWhere ORDER BY p.created_at DESC, p.id DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Активные товары видимых категорий для страницы категории:
     * новые в начале.
     */
    public static function publicList(array $categoryIds, int $limit, int $offset): array
    {
        if (!$categoryIds) {
            return [];
        }
        return Db::all(
            "SELECT p.*, r.path FROM products p
             JOIN routes r ON r.entity_type = 'product' AND r.entity_id = p.id
             WHERE p.is_active = 1 AND p.category_id IN (" . Db::placeholders($categoryIds) . ")
             ORDER BY p.created_at DESC, p.id DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $categoryIds
        );
    }

    public static function publicCount(array $categoryIds): int
    {
        if (!$categoryIds) {
            return 0;
        }
        return (int) Db::value(
            'SELECT COUNT(*) FROM products WHERE is_active = 1 AND category_id IN (' . Db::placeholders($categoryIds) . ')',
            $categoryIds
        );
    }

    /** Виден ли товар на сайте: активен и его категория видима. */
    public static function isVisible(array $product): bool
    {
        $cat = Category::find((int) $product['category_id']);
        return (int) $product['is_active'] === 1 && $cat !== null && Category::isVisible($cat);
    }
}
