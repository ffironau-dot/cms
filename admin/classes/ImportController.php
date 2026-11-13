<?php
/**
 * Массовый импорт товаров из CSV (п. 5.2 ТЗ).
 *
 * Формат: разделитель «;», кодировка UTF-8, первая строка — названия колонок:
 *   артикул; URL; название; категория; title; description; og:title;
 *   og:description; малый текст; большой текст; старая цена; новая цена;
 *   активность; ссылка на основное фото
 *
 * Правила:
 *  - товар ищется по артикулу: найден — обновляется, нет — создаётся;
 *  - при обновлении пустая ячейка не стирает значение поля;
 *  - смена URL — по общим правилам (уникальность, редирект 301);
 *  - категория указывается её адресом (/neonovye-vyveski/), пусто — «Без категории»
 *    (для нового товара) или без изменений (для существующего);
 *  - фото по ссылке скачивается на сервер;
 *  - строки с ошибками пропускаются, по итогам выводится отчёт.
 */

declare(strict_types=1);

namespace Admin;

use Core\Models\Category;
use Core\Models\Product;
use Core\Upload;
use Core\Urls;

final class ImportController extends Controller
{
    /** Колонки файла в порядке ТЗ: ключ поля → варианты названия в заголовке. */
    public const COLUMNS = [
        'sku'              => ['артикул', 'sku'],
        'slug'             => ['url', 'адрес'],
        'name'             => ['название', 'name'],
        'category'         => ['категория', 'category'],
        'meta_title'       => ['title'],
        'meta_description' => ['description'],
        'og_title'         => ['og:title', 'og title'],
        'og_description'   => ['og:description', 'og description'],
        'text_small'       => ['малый текст'],
        'text_big'         => ['большой текст'],
        'price_old'        => ['старая цена'],
        'price'            => ['новая цена', 'цена'],
        'is_active'        => ['активность'],
        'photo'            => ['ссылка на основное фото', 'основное фото', 'фото'],
    ];

    /** Таймаут скачивания одного фото, секунд. */
    private const DOWNLOAD_TIMEOUT = 30;

    public function import(): void
    {
        $report = null;
        $errors = [];
        if ($this->isValidPost()) {
            try {
                $file = Upload::fromRequest('csv');
                if (!$file) {
                    throw new \RuntimeException('Выберите CSV-файл.');
                }
                @set_time_limit(0);
                $report = $this->process((string) file_get_contents($file['tmp_name']));
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        $this->render('import', ['report' => $report, 'errors' => $errors], 'Импорт товаров из CSV', 'import');
    }

    /** Пример файла для скачивания. */
    public function sample(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="import-sample.csv"');
        $head = array_map(static fn($v) => $v[0], self::COLUMNS);
        $head[1] = 'URL';
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $head, ';', '"', '');
        fputcsv($out, ['A-001', 'akrilovye-bukvy', 'Акриловые буквы', '/obemnye-bukvy/', '', '', '', '', 'Яркие <b>буквы</b>', '<p>Описание</p>', '1500,00', '1250,50', '1', 'https://example.com/photo.jpg'], ';', '"', '');
        fclose($out);
    }

    /**
     * Обработка содержимого CSV.
     *
     * @return array{added:int, updated:int, skipped:array, warnings:array}
     */
    public function process(string $content): array
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new \RuntimeException('Файл должен быть в кодировке UTF-8.');
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream, 0, ';', '"', '');
        if (!$header || count($header) < 2) {
            throw new \RuntimeException('Не удалось прочитать первую строку с названиями колонок (разделитель — «;»).');
        }
        $map = self::mapHeader($header);
        if (!isset($map['sku'])) {
            throw new \RuntimeException('В первой строке нет колонки «артикул».');
        }

        $report = ['added' => 0, 'updated' => 0, 'skipped' => [], 'warnings' => []];
        $line = 1;
        while (($row = fgetcsv($stream, 0, ';', '"', '')) !== false) {
            $line++;
            if ($row === [null] || trim(implode('', array_map('strval', $row))) === '') {
                continue;
            }
            $cells = [];
            foreach ($map as $key => $index) {
                $cells[$key] = trim((string) ($row[$index] ?? ''));
            }
            $this->importRow($line, $cells, $report);
        }
        fclose($stream);
        return $report;
    }

    /** Сопоставление колонок по названиям; если названия не распознаны — по порядку из ТЗ. */
    public static function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $i => $title) {
            $t = mb_strtolower(trim((string) $title));
            foreach (self::COLUMNS as $key => $names) {
                if (in_array($t, $names, true) && !isset($map[$key])) {
                    $map[$key] = $i;
                }
            }
        }
        if (count($map) < 3) {
            $map = [];
            foreach (array_keys(self::COLUMNS) as $i => $key) {
                $map[$key] = $i;
            }
        }
        return $map;
    }

    private function importRow(int $line, array $c, array &$report): void
    {
        $skip = static function (string $reason) use ($line, &$report, $c): void {
            $report['skipped'][] = ['line' => $line, 'sku' => $c['sku'] ?? '', 'reason' => $reason];
        };
        $sku = $c['sku'] ?? '';
        if ($sku === '') {
            $skip('не указан артикул');
            return;
        }
        $existing = Product::findBySku($sku);

        $in = ['sku' => $sku];
        foreach (['name', 'meta_title', 'meta_description', 'og_title', 'og_description', 'text_small', 'text_big', 'price_old', 'price'] as $k) {
            if (($c[$k] ?? '') !== '') {
                $in[$k] = $c[$k];
            }
        }
        // URL: адрес товара — один сегмент (допускается «/slug/» или полный адрес).
        if (($c['slug'] ?? '') !== '') {
            $path = (string) (parse_url($c['slug'], PHP_URL_PATH) ?? $c['slug']);
            $slug = strtolower(trim($path, '/'));
            if (!Urls::isValidSegment($slug)) {
                $skip('некорректный URL «' . $c['slug'] . '» (только латиница, цифры и дефис, один сегмент)');
                return;
            }
            $in['slug'] = $slug;
        }
        // Категория — по её адресу.
        if (($c['category'] ?? '') !== '') {
            $path = (string) (parse_url($c['category'], PHP_URL_PATH) ?? '');
            $path = '/' . trim($path, '/') . '/';
            $route = Urls::find($path);
            if (!$route || $route['entity_type'] !== Urls::CATEGORY) {
                $skip('неизвестная категория «' . $c['category'] . '»');
                return;
            }
            $in['category_id'] = (int) $route['entity_id'];
        }
        if (($c['is_active'] ?? '') !== '') {
            if (!in_array($c['is_active'], ['0', '1'], true)) {
                $skip('активность должна быть 1 или 0');
                return;
            }
            $in['is_active'] = $c['is_active'];
        }
        if (!$existing && ($c['name'] ?? '') === '') {
            $skip('пустое название');
            return;
        }

        // Фото: скачивается до сохранения; ошибка скачивания — предупреждение.
        $newPhoto = null;
        if (($c['photo'] ?? '') !== '') {
            try {
                $newPhoto = self::downloadImage($c['photo']);
                $in['image'] = $newPhoto;
            } catch (\RuntimeException $e) {
                $report['warnings'][] = ['line' => $line, 'sku' => $sku, 'reason' => 'фото не загружено: ' . $e->getMessage()];
            }
        }

        $res = Product::save($in, $existing ? (int) $existing['id'] : null, true);
        if ($res['errors']) {
            if ($newPhoto) {
                Upload::delete(Product::UPLOAD_DIR, $newPhoto);
            }
            $skip(implode(' ', $res['errors']));
            return;
        }
        if ($newPhoto && $existing && $existing['image']) {
            Upload::delete(Product::UPLOAD_DIR, $existing['image']);
        }
        $existing ? $report['updated']++ : $report['added']++;
    }

    /**
     * Скачать изображение по ссылке и сохранить в папку товаров.
     * Допускаются только ссылки http(s); тип проверяется по содержимому.
     */
    public static function downloadImage(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException('ссылка должна начинаться с http:// или https://');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'cmsimg');
        $fh = fopen($tmp, 'wb');
        $ok = false;
        $error = '';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE           => $fh,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => self::DOWNLOAD_TIMEOUT,
                CURLOPT_USERAGENT      => 'CMS image import',
                CURLOPT_FAILONERROR    => true,
            ]);
            $ok = curl_exec($ch) !== false;
            $error = curl_error($ch);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => self::DOWNLOAD_TIMEOUT, 'follow_location' => 1, 'max_redirects' => 3]]);
            $src = @fopen($url, 'rb', false, $ctx);
            if ($src) {
                $ok = stream_copy_to_stream($src, $fh) !== false;
                fclose($src);
            } else {
                $error = 'не удалось открыть ссылку';
            }
        }
        fclose($fh);
        if (!$ok) {
            @unlink($tmp);
            throw new \RuntimeException($error !== '' ? $error : 'ошибка скачивания');
        }
        try {
            return Upload::storeImageFromPath($tmp, Product::UPLOAD_DIR, Upload::ALL_IMAGES);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }
}
