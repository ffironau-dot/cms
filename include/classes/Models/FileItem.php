<?php
/**
 * Модель «Файл» раздела админки «Файлы» (п. 6.1 ТЗ): картинки для
 * вставки в HTML-код больших текстов.
 */

declare(strict_types=1);

namespace Core\Models;

use Core\Db;
use Core\Upload;

final class FileItem
{
    public const UPLOAD_DIR = 'files';
    public const PER_PAGE = 50;

    /** Занято ли имя файла (без учёта регистра). */
    public static function isTaken(string $name): bool
    {
        return Db::value('SELECT id FROM files WHERE filename = ?', [$name]) !== null;
    }

    /** Загрузить файл из формы; возвращает итоговое имя. */
    public static function upload(array $file): string
    {
        $stored = Upload::storeNamed($file, self::UPLOAD_DIR, [self::class, 'isTaken']);
        Db::insert('files', [
            'filename'   => $stored['name'],
            'mime'       => $stored['mime'],
            'size'       => $stored['size'],
            'created_at' => Db::now(),
        ]);
        return $stored['name'];
    }

    /** @return array{rows:array, total:int} */
    public static function search(string $q, int $limit, int $offset): array
    {
        $where = '';
        $params = [];
        if ($q !== '') {
            $where = 'WHERE filename LIKE ?';
            $params[] = '%' . addcslashes($q, '%_\\') . '%';
        }
        return [
            'total' => (int) Db::value('SELECT COUNT(*) FROM files ' . $where, $params),
            'rows'  => Db::all('SELECT * FROM files ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset, $params),
        ];
    }

    public static function delete(int $id): void
    {
        $row = Db::row('SELECT * FROM files WHERE id = ?', [$id]);
        if (!$row) {
            return;
        }
        Db::query('DELETE FROM files WHERE id = ?', [$id]);
        Upload::delete(self::UPLOAD_DIR, $row['filename']);
    }
}
