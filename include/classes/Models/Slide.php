<?php
/**
 * Модель «Слайд» слайдера на главной (п. 4.5 ТЗ).
 *
 * В системе всегда должен оставаться хотя бы один активный слайд:
 * деактивировать или удалить последний активный слайд нельзя.
 */

declare(strict_types=1);

namespace Core\Models;

use Core\Db;
use Core\Html;
use Core\Upload;

final class Slide
{
    public const UPLOAD_DIR = 'slides';

    public static function find(int $id): ?array
    {
        return Db::row('SELECT * FROM slides WHERE id = ?', [$id]);
    }

    public static function all(): array
    {
        return Db::all('SELECT * FROM slides ORDER BY sort, id');
    }

    /** Активные слайды по порядку (для сайта). */
    public static function active(): array
    {
        return Db::all('SELECT * FROM slides WHERE is_active = 1 ORDER BY sort, id');
    }

    public static function activeCount(): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM slides WHERE is_active = 1');
    }

    /** @return array{errors:array, id:?int} */
    public static function save(array $in, ?int $id = null): array
    {
        $current = $id ? self::find($id) : null;
        if ($id && !$current) {
            return ['errors' => ['Слайд не найден.'], 'id' => null];
        }
        $data = [
            'eyebrow'   => Html::filterPlain($in['eyebrow'] ?? ''),
            'title'     => Html::filterPlain($in['title'] ?? ''),
            'text'      => Html::filterPlain($in['text'] ?? ''),
            'btn1_text' => Html::filterPlain($in['btn1_text'] ?? ''),
            'btn1_url'  => Html::filterPlain($in['btn1_url'] ?? ''),
            'btn2_text' => Html::filterPlain($in['btn2_text'] ?? ''),
            'btn2_url'  => Html::filterPlain($in['btn2_url'] ?? ''),
            'image_alt' => Html::filterPlain($in['image_alt'] ?? ''),
            'caption'   => Html::filterPlain($in['caption'] ?? ''),
            'sort'      => (int) ($in['sort'] ?? 0),
            'is_active' => !empty($in['is_active']) ? 1 : 0,
            'updated_at' => Db::now(),
        ];
        $errors = [];
        if ($data['title'] === '') {
            $errors[] = 'Укажите заголовок слайда.';
        }
        foreach (['btn1_url' => 'Кнопка 1', 'btn2_url' => 'Кнопка 2'] as $k => $label) {
            if ($data[$k] !== '' && preg_match('/^\s*(javascript|data|vbscript):/i', $data[$k])) {
                $errors[] = $label . ': недопустимая ссылка.';
            }
        }
        if ($current && (int) $current['is_active'] === 1 && $data['is_active'] === 0 && self::activeCount() <= 1) {
            $errors[] = 'Нельзя деактивировать последний активный слайд: на сайте должен оставаться хотя бы один.';
        }
        if (!$current && $data['is_active'] === 0 && self::activeCount() === 0) {
            $errors[] = 'Первый слайд должен быть активным.';
        }
        if ($errors) {
            return ['errors' => $errors, 'id' => $id];
        }
        if (array_key_exists('image', $in)) {
            $data['image'] = $in['image'];
        }
        if ($id === null) {
            $data['created_at'] = $data['updated_at'];
            $id = Db::insert('slides', $data);
        } else {
            Db::update('slides', $id, $data);
        }
        return ['errors' => [], 'id' => $id];
    }

    /** Удалить слайд и файл фото. Возвращает текст ошибки или null. */
    public static function delete(int $id): ?string
    {
        $slide = self::find($id);
        if (!$slide) {
            return 'Слайд не найден.';
        }
        if ((int) $slide['is_active'] === 1 && self::activeCount() <= 1) {
            return 'Нельзя удалить последний активный слайд: на сайте должен оставаться хотя бы один.';
        }
        Db::query('DELETE FROM slides WHERE id = ?', [$id]);
        Upload::delete(self::UPLOAD_DIR, $slide['image']);
        return null;
    }
}
