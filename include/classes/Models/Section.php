<?php
/**
 * Модель «Раздел текстовых страниц» (п. 6 ТЗ), например «Блог».
 *
 * Раздел — отдельная страница со своим адресом /segment/ и лентой
 * входящих в него страниц. Вложенных разделов нет. При смене адреса
 * раздела адреса его страниц меняются каскадно (с редиректами 301).
 */

declare(strict_types=1);

namespace Core\Models;

use Core\Db;
use Core\Html;
use Core\Urls;

final class Section
{
    public static function find(int $id): ?array
    {
        return Db::row(
            "SELECT s.*, r.path FROM sections s
             LEFT JOIN routes r ON r.entity_type = 'section' AND r.entity_id = s.id
             WHERE s.id = ?",
            [$id]
        );
    }

    /** Все разделы (для списков и выпадающих меню). */
    public static function all(): array
    {
        return Db::all(
            "SELECT s.*, r.path, (SELECT COUNT(*) FROM pages p WHERE p.section_id = s.id) AS pages_count
             FROM sections s LEFT JOIN routes r ON r.entity_type = 'section' AND r.entity_id = s.id
             ORDER BY s.name"
        );
    }

    /** @return array{errors:array, id:?int} */
    public static function save(array $in, ?int $id = null): array
    {
        $current = $id ? self::find($id) : null;
        if ($id && !$current) {
            return ['errors' => ['Раздел не найден.'], 'id' => null];
        }
        $data = [
            'slug'             => strtolower(trim((string) ($in['slug'] ?? ''))),
            'name'             => Html::filterPlain($in['name'] ?? ''),
            'meta_title'       => Html::filterPlain($in['meta_title'] ?? ''),
            'meta_description' => Html::filterPlain($in['meta_description'] ?? ''),
            'og_title'         => Html::filterPlain($in['og_title'] ?? ''),
            'og_description'   => Html::filterPlain($in['og_description'] ?? ''),
            'text_big'         => (string) ($in['text_big'] ?? ''),
            'is_active'        => !empty($in['is_active']) ? 1 : 0,
            'updated_at'       => Db::now(),
        ];
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'Укажите название раздела.';
        }
        if (!Urls::isValidSegment($data['slug'])) {
            $errors[] = 'URL раздела: только строчные латинские буквы, цифры и дефис (например, blog).';
        }
        if ($errors) {
            return ['errors' => $errors, 'id' => $id];
        }
        $path = '/' . $data['slug'] . '/';
        try {
            $id = Db::transaction(static function () use ($data, $id, $current, $path): int {
                if ($id === null) {
                    $data['created_at'] = $data['updated_at'];
                    $id = Db::insert('sections', $data);
                    Urls::assign([[Urls::SECTION, $id, $path]]);
                    return $id;
                }
                Db::update('sections', $id, $data);
                if ($current['path'] !== $path) {
                    $moves = [[Urls::SECTION, $id, $path]];
                    foreach (Db::all('SELECT id, slug FROM pages WHERE section_id = ?', [$id]) as $p) {
                        $moves[] = [Urls::PAGE, (int) $p['id'], $path . $p['slug'] . '/'];
                    }
                    Urls::assign($moves);
                }
                return $id;
            });
        } catch (\RuntimeException $e) {
            return ['errors' => [$e->getMessage()], 'id' => $id];
        }
        return ['errors' => [], 'id' => $id];
    }

    /** Удаление запрещено, пока в разделе есть страницы. Возвращает ошибку или null. */
    public static function delete(int $id): ?string
    {
        if (!self::find($id)) {
            return 'Раздел не найден.';
        }
        $count = (int) Db::value('SELECT COUNT(*) FROM pages WHERE section_id = ?', [$id]);
        if ($count > 0) {
            return 'Нельзя удалить раздел, пока в нём есть страницы (' . $count . '): сначала удалите их или уберите из раздела.';
        }
        Db::transaction(static function () use ($id): void {
            Urls::remove(Urls::SECTION, $id);
            Db::query('DELETE FROM sections WHERE id = ?', [$id]);
        });
        return null;
    }
}
