<?php
/**
 * Модель «Текстовая страница» (п. 6 ТЗ).
 *
 * Отдельная страница (без раздела) имеет произвольный адрес, который
 * администратор вводит целиком (/o-kompanii/komanda/). Страница в разделе
 * получает адрес «сегмент раздела + собственный сегмент» (/blog/statya/).
 */

declare(strict_types=1);

namespace Core\Models;

use Core\Db;
use Core\Html;
use Core\Upload;
use Core\Urls;

final class Page
{
    public const UPLOAD_DIR = 'pages';
    /** Страниц на одной странице ленты раздела. */
    public const FEED_PER_PAGE = 15;

    public static function find(int $id): ?array
    {
        return Db::row(
            "SELECT p.*, r.path FROM pages p
             LEFT JOIN routes r ON r.entity_type = 'page' AND r.entity_id = p.id
             WHERE p.id = ?",
            [$id]
        );
    }

    /** Все страницы для списка в админке (с разделом, адресом и названием). */
    public static function adminList(int $sectionFilter = -1): array
    {
        $where = '';
        $params = [];
        if ($sectionFilter === 0) {
            $where = 'WHERE p.section_id IS NULL';
        } elseif ($sectionFilter > 0) {
            $where = 'WHERE p.section_id = ?';
            $params[] = $sectionFilter;
        }
        return Db::all(
            "SELECT p.*, r.path, s.name AS section_name FROM pages p
             LEFT JOIN sections s ON s.id = p.section_id
             LEFT JOIN routes r ON r.entity_type = 'page' AND r.entity_id = p.id
             $where ORDER BY s.name IS NOT NULL, s.name, r.path",
            $params
        );
    }

    /** @return array{errors:array, id:?int} */
    public static function save(array $in, ?int $id = null): array
    {
        $current = $id ? self::find($id) : null;
        if ($id && !$current) {
            return ['errors' => ['Страница не найдена.'], 'id' => null];
        }
        $errors = [];
        $sectionId = !empty($in['section_id']) ? (int) $in['section_id'] : null;
        $section = $sectionId ? Section::find($sectionId) : null;
        if ($sectionId && !$section) {
            $errors[] = 'Раздел не найден.';
        }
        $slugInput = trim((string) ($in['slug'] ?? ''));
        if ($sectionId) {
            $slug = strtolower(trim($slugInput, '/'));
            if (!Urls::isValidSegment($slug)) {
                $errors[] = 'URL страницы в разделе — один сегмент: строчные латинские буквы, цифры и дефис (например, nazvanie-stati).';
            }
        } else {
            $normalized = Urls::normalizePath(strtolower($slugInput));
            if ($normalized === null) {
                $errors[] = 'Адрес страницы: путь из сегментов через «/», в сегментах — строчные латинские буквы, цифры и дефис (например, /o-kompanii/komanda/).';
                $slug = '';
            } else {
                $slug = trim($normalized, '/');
            }
        }
        $published = null;
        $date = trim((string) ($in['published_at'] ?? ''));
        if ($sectionId && $date !== '') {
            $d = \DateTime::createFromFormat('!Y-m-d', $date);
            if (!$d || $d->format('Y-m-d') !== $date) {
                $errors[] = 'Дата публикации указана неверно.';
            } else {
                $published = $date;
            }
        }
        $data = [
            'section_id'       => $sectionId,
            'slug'             => $slug,
            'name'             => Html::filterPlain($in['name'] ?? ''),
            'meta_title'       => Html::filterPlain($in['meta_title'] ?? ''),
            'meta_description' => Html::filterPlain($in['meta_description'] ?? ''),
            'og_title'         => Html::filterPlain($in['og_title'] ?? ''),
            'og_description'   => Html::filterPlain($in['og_description'] ?? ''),
            'text_small'       => Html::filterSmall($in['text_small'] ?? ''),
            'text_big'         => (string) ($in['text_big'] ?? ''),
            'published_at'     => $published,
            'is_active'        => !empty($in['is_active']) ? 1 : 0,
            'updated_at'       => Db::now(),
        ];
        if ($data['name'] === '') {
            $errors[] = 'Укажите название страницы.';
        }
        if ($errors) {
            return ['errors' => $errors, 'id' => $id];
        }
        if (array_key_exists('image', $in)) {
            $data['image'] = $in['image'];
        }
        $path = ($section ? $section['path'] : '/') . $slug . '/';
        try {
            $id = Db::transaction(static function () use ($data, $id, $path): int {
                if ($id === null) {
                    $data['created_at'] = $data['updated_at'];
                    $id = Db::insert('pages', $data);
                } else {
                    Db::update('pages', $id, $data);
                }
                Urls::assign([[Urls::PAGE, $id, $path]]);
                return $id;
            });
        } catch (\RuntimeException $e) {
            return ['errors' => [$e->getMessage()], 'id' => $id];
        }
        return ['errors' => [], 'id' => $id];
    }

    public static function delete(int $id): void
    {
        $page = self::find($id);
        if (!$page) {
            return;
        }
        Db::transaction(static function () use ($id): void {
            Urls::remove(Urls::PAGE, $id);
            Db::query('DELETE FROM pages WHERE id = ?', [$id]);
        });
        Upload::delete(self::UPLOAD_DIR, $page['image']);
    }

    /** Видна ли страница на сайте: активна и её раздел (если есть) активен. */
    public static function isVisible(array $page): bool
    {
        if ((int) $page['is_active'] !== 1) {
            return false;
        }
        if ($page['section_id'] !== null) {
            $s = Section::find((int) $page['section_id']);
            return $s !== null && (int) $s['is_active'] === 1;
        }
        return true;
    }

    /**
     * Лента раздела: активные страницы, новые первыми. Если дата
     * публикации не заполнена, используется дата создания.
     */
    public static function feed(int $sectionId, int $limit, int $offset): array
    {
        return Db::all(
            "SELECT p.*, r.path FROM pages p
             JOIN routes r ON r.entity_type = 'page' AND r.entity_id = p.id
             WHERE p.section_id = ? AND p.is_active = 1
             ORDER BY COALESCE(p.published_at, DATE(p.created_at)) DESC, p.created_at DESC, p.id DESC
             LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            [$sectionId]
        );
    }

    public static function feedCount(int $sectionId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM pages WHERE section_id = ? AND is_active = 1', [$sectionId]);
    }
}
