<?php
/**
 * Безопасная загрузка изображений (п. 4.2, 4.5, 6.1, 8.3 ТЗ).
 *
 *  - тип файла определяется по содержимому (finfo + getimagesize),
 *    а не по расширению или заголовку от браузера;
 *  - расширение сохранённого файла всегда соответствует реальному типу;
 *  - файлы сохраняются в /uploads/<папка>/ под безопасным именем;
 *    выполнение скриптов в /uploads запрещено через .htaccess.
 *
 * Размер файла CMS не ограничивает (по ТЗ); ограничения могут задавать
 * настройки PHP на сервере (upload_max_filesize, post_max_size).
 */

declare(strict_types=1);

namespace Core;

final class Upload
{
    /** Допустимые типы изображений: MIME → расширение. */
    public const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    /** Наборы допустимых расширений для разных мест загрузки. */
    public const ALL_IMAGES = ['jpg', 'png', 'gif', 'webp'];
    public const SLIDE_IMAGES = ['jpg', 'png', 'webp'];

    /**
     * Файл из $_FILES[$field] или null, если файл не выбран.
     *
     * @throws \RuntimeException при ошибке загрузки
     */
    public static function fromRequest(string $field): ?array
    {
        $f = $_FILES[$field] ?? null;
        if (!is_array($f) || !isset($f['error']) || is_array($f['error'])) {
            return null;
        }
        if ($f['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE   => 'файл больше, чем разрешено настройками PHP на сервере (upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE  => 'файл слишком большой',
                UPLOAD_ERR_PARTIAL    => 'файл загружен не полностью',
                UPLOAD_ERR_NO_TMP_DIR => 'на сервере нет временной папки',
                UPLOAD_ERR_CANT_WRITE => 'не удалось записать файл на диск',
                UPLOAD_ERR_EXTENSION  => 'загрузка остановлена расширением PHP',
            ];
            throw new \RuntimeException('Ошибка загрузки: ' . ($messages[$f['error']] ?? 'код ' . $f['error']) . '.');
        }
        if (!is_uploaded_file($f['tmp_name'])) {
            throw new \RuntimeException('Ошибка загрузки: файл получен не через форму.');
        }
        return $f;
    }

    /**
     * Определить реальный тип изображения по содержимому.
     * Возвращает расширение (jpg/png/gif/webp) или null.
     */
    public static function detectImage(string $path): ?string
    {
        if (!is_file($path) || filesize($path) === 0) {
            return null;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($path);
        if (!isset(self::TYPES[$mime])) {
            return null;
        }
        $info = @getimagesize($path);
        if ($info === false || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            return null;
        }
        // Тип по сигнатуре и по разбору изображения должен совпадать.
        if (($info['mime'] ?? '') !== $mime) {
            return null;
        }
        return self::TYPES[$mime];
    }

    /** Текст «JPG, PNG, WebP» для сообщений. */
    public static function describe(array $allowed): string
    {
        return implode(', ', array_map(static fn($e) => $e === 'webp' ? 'WebP' : strtoupper($e), $allowed));
    }

    /**
     * Сохранить загруженное изображение под случайным именем.
     *
     * @param array  $file    Элемент $_FILES
     * @param string $dir     Подпапка в /uploads (logo, slides, products, ...)
     * @param array  $allowed Допустимые расширения
     * @return string Имя сохранённого файла
     */
    public static function storeImage(array $file, string $dir, array $allowed): string
    {
        return self::storeImageFromPath($file['tmp_name'], $dir, $allowed, true);
    }

    /**
     * Сохранить изображение из файла на диске (загрузка через форму или
     * скачанное при импорте CSV).
     */
    public static function storeImageFromPath(string $tmp, string $dir, array $allowed, bool $uploaded = false): string
    {
        $ext = self::detectImage($tmp);
        if ($ext === null || !in_array($ext, $allowed, true)) {
            throw new \RuntimeException('Файл не является изображением допустимого формата (' . self::describe($allowed) . ').');
        }
        $target = self::dirPath($dir);
        do {
            $name = bin2hex(random_bytes(10)) . '.' . $ext;
        } while (is_file($target . '/' . $name));
        self::move($tmp, $target . '/' . $name, $uploaded);
        return $name;
    }

    /**
     * Сохранить файл в раздел «Файлы» с сохранением исходного имени (п. 6.1).
     * Из имени удаляются только небезопасные символы, расширение приводится
     * к реальному типу; при совпадении имени добавляется номер (foto-2.jpg).
     *
     * @return array{name:string, mime:string, size:int}
     */
    public static function storeNamed(array $file, string $dir, callable $isTaken): array
    {
        $tmp = $file['tmp_name'];
        $ext = self::detectImage($tmp);
        if ($ext === null) {
            throw new \RuntimeException('«' . Html::filterPlain((string) $file['name']) . '»: файл не является изображением (JPG, PNG, WebP, GIF).');
        }
        [$base, $origExt] = self::splitName((string) $file['name']);
        // Расширение исходного имени сохраняется, если оно соответствует типу (jpeg/jpg, регистр).
        $lower = strtolower($origExt);
        $finalExt = ($lower === $ext || ($ext === 'jpg' && in_array($lower, ['jpeg', 'jpe'], true))) ? $origExt : $ext;

        $target = self::dirPath($dir);
        $name = $base . '.' . $finalExt;
        $n = 2;
        while ($isTaken($name) || is_file($target . '/' . $name)) {
            $name = $base . '-' . $n . '.' . $finalExt;
            $n++;
        }
        self::move($tmp, $target . '/' . $name, true);
        $mime = array_search($ext, self::TYPES, true);
        return ['name' => $name, 'mime' => (string) $mime, 'size' => (int) filesize($target . '/' . $name)];
    }

    /**
     * Безопасная очистка имени файла: удаляются разделители пути,
     * управляющие символы и символы, ломающие адрес (?, #, %), а также
     * точки в начале (скрытые файлы). Возвращает [основа, расширение].
     */
    public static function splitName(string $original): array
    {
        $name = str_replace(['/', '\\', "\0"], '', $original);
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = str_replace(['?', '#', '%', ':', '*', '"', '<', '>', '|'], '', $name);
        $name = trim($name, " .\t");
        $dot = strrpos($name, '.');
        if ($dot !== false) {
            $base = substr($name, 0, $dot);
            $ext = substr($name, $dot + 1);
        } else {
            $base = $name;
            $ext = '';
        }
        $base = trim($base, " .");
        if ($base === '') {
            $base = 'file';
        }
        // Ограничение длины имени (байты), без разрыва символа UTF-8.
        if (strlen($base) > 180) {
            $base = mb_strcut($base, 0, 180, 'UTF-8');
        }
        return [$base, $ext];
    }

    /** Удалить файл из папки загрузок (без ошибки, если файла нет). */
    public static function delete(string $dir, ?string $name): void
    {
        if ($name === null || $name === '' || str_contains($name, '/') || str_contains($name, '\\') || $name[0] === '.') {
            return;
        }
        $path = self::dirPath($dir) . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Адрес файла на сайте: /uploads/products/abc.jpg (имя кодируется для URL). */
    public static function url(string $dir, ?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }
        return CMS_UPLOADS_URL . '/' . $dir . '/' . rawurlencode($name);
    }

    /** Полный путь к подпапке загрузок (создаётся при необходимости). */
    public static function dirPath(string $dir): string
    {
        $path = CMS_UPLOADS . '/' . $dir;
        if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException('Не удалось создать папку ' . $path . '.');
        }
        return $path;
    }

    private static function move(string $from, string $to, bool $uploaded): void
    {
        $ok = $uploaded ? move_uploaded_file($from, $to) : rename($from, $to);
        if (!$ok && !$uploaded) {
            $ok = copy($from, $to);
            @unlink($from);
        }
        if (!$ok) {
            throw new \RuntimeException('Не удалось сохранить файл на сервере (проверьте права на папку uploads).');
        }
        @chmod($to, 0644);
    }
}
