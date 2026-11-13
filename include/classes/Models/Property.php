<?php
/**
 * Модель «Свойство товаров категории» (п. 5.1.4 ТЗ).
 *
 * Типы: строка, число, выбор из списка, да/нет. Варианты списка хранятся
 * в category_property_options. При удалении свойства его значения у всех
 * товаров удаляются (внешний ключ ON DELETE CASCADE).
 */

declare(strict_types=1);

namespace Core\Models;

use Core\Db;
use Core\Html;

final class Property
{
    public const TYPES = [
        'string' => 'Строка',
        'number' => 'Число',
        'list'   => 'Выбор из списка',
        'bool'   => 'Да/нет',
    ];

    public static function find(int $id): ?array
    {
        $row = Db::row('SELECT * FROM category_properties WHERE id = ?', [$id]);
        if ($row) {
            $row['options'] = Db::all('SELECT id, value FROM category_property_options WHERE property_id = ? ORDER BY sort, id', [$id]);
        }
        return $row;
    }

    /** Собственные свойства категории (без унаследованных). */
    public static function ofCategory(int $categoryId): array
    {
        $rows = Db::all('SELECT * FROM category_properties WHERE category_id = ? ORDER BY sort, id', [$categoryId]);
        foreach ($rows as &$r) {
            $r['options'] = Db::all('SELECT id, value FROM category_property_options WHERE property_id = ? ORDER BY sort, id', [(int) $r['id']]);
        }
        return $rows;
    }

    /** Количество товаров, у которых заполнено значение свойства. */
    public static function valuesCount(int $id): int
    {
        return (int) Db::value(
            "SELECT COUNT(*) FROM product_property_values WHERE property_id = ? AND (option_id IS NOT NULL OR (value IS NOT NULL AND value <> ''))",
            [$id]
        );
    }

    /**
     * Создать или изменить свойство. Тип после создания не меняется
     * (иначе существующие значения потеряли бы смысл).
     *
     * $in['options'] — варианты списка, по одному на строку. Существующие
     * варианты сопоставляются по тексту: совпавшие сохраняются (и значения
     * товаров вместе с ними), исчезнувшие — удаляются.
     *
     * @return array{errors:array, id:?int}
     */
    public static function save(int $categoryId, array $in, ?int $id = null): array
    {
        $name = Html::filterPlain($in['name'] ?? '');
        $type = (string) ($in['type'] ?? '');
        $sort = (int) ($in['sort'] ?? 0);
        $current = $id ? self::find($id) : null;
        $errors = [];
        if ($id && (!$current || (int) $current['category_id'] !== $categoryId)) {
            return ['errors' => ['Свойство не найдено.'], 'id' => null];
        }
        if ($current) {
            $type = $current['type'];
        }
        if ($name === '') {
            $errors[] = 'Укажите название свойства.';
        }
        if (!isset(self::TYPES[$type])) {
            $errors[] = 'Выберите тип свойства.';
        }
        $options = [];
        if ($type === 'list') {
            foreach (preg_split('/\R/u', (string) ($in['options'] ?? '')) as $line) {
                $line = Html::filterPlain($line);
                if ($line !== '' && !in_array($line, $options, true)) {
                    $options[] = $line;
                }
            }
            if (!$options) {
                $errors[] = 'Для свойства «Выбор из списка» укажите хотя бы один вариант.';
            }
        }
        if ($errors) {
            return ['errors' => $errors, 'id' => $id];
        }
        $id = Db::transaction(static function () use ($categoryId, $id, $name, $type, $sort, $options, $current): int {
            if ($id === null) {
                $id = Db::insert('category_properties', ['category_id' => $categoryId, 'name' => $name, 'type' => $type, 'sort' => $sort]);
            } else {
                Db::update('category_properties', $id, ['name' => $name, 'sort' => $sort]);
            }
            if ($type === 'list') {
                $existing = [];
                foreach (($current['options'] ?? []) as $o) {
                    $existing[$o['value']] = (int) $o['id'];
                }
                foreach ($options as $i => $value) {
                    if (isset($existing[$value])) {
                        Db::query('UPDATE category_property_options SET sort = ? WHERE id = ?', [$i, $existing[$value]]);
                        unset($existing[$value]);
                    } else {
                        Db::insert('category_property_options', ['property_id' => $id, 'value' => $value, 'sort' => $i]);
                    }
                }
                foreach ($existing as $optionId) {
                    // Значения товаров с удалённым вариантом удаляются.
                    Db::query('DELETE FROM product_property_values WHERE option_id = ?', [$optionId]);
                    Db::query('DELETE FROM category_property_options WHERE id = ?', [$optionId]);
                }
            }
            return $id;
        });
        return ['errors' => [], 'id' => $id];
    }

    /** Удалить свойство вместе со значениями у всех товаров. */
    public static function delete(int $id): void
    {
        Db::query('DELETE FROM category_properties WHERE id = ?', [$id]);
    }
}
