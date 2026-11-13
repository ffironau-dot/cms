<?php
/**
 * Конфигурация установки.
 *
 * Параметры хранятся в include/connect.php (создаётся установщиком).
 * По ТЗ (п. 3.3) имена переменных в этом файле нейтральные и не
 * раскрывают назначения. Соответствие имён и смысла задано только
 * здесь, в константе MAP:
 *
 *   $r01 — хост MySQL            $r09 — email получателя заявок
 *   $r02 — порт MySQL            $r10 — SMTP: хост (пусто — функция mail())
 *   $r03 — имя базы данных       $r11 — SMTP: порт
 *   $r04 — пользователь БД       $r12 — SMTP: шифрование ('', 'ssl', 'tls')
 *   $r05 — пароль БД             $r13 — SMTP: логин
 *   $r06 — адрес сайта           $r14 — SMTP: пароль
 *          (https://domain.com)  $r15 — адрес отправителя писем
 *   $r07 — логин администратора
 *   $r08 — хеш пароля администратора (password_hash)
 */

declare(strict_types=1);

namespace Core;

final class Config
{
    /** Соответствие «смысловой ключ → нейтральное имя переменной». */
    public const MAP = [
        'db_host'     => 'r01',
        'db_port'     => 'r02',
        'db_name'     => 'r03',
        'db_user'     => 'r04',
        'db_pass'     => 'r05',
        'site_url'    => 'r06',
        'admin_login' => 'r07',
        'admin_hash'  => 'r08',
        'mail_to'     => 'r09',
        'smtp_host'   => 'r10',
        'smtp_port'   => 'r11',
        'smtp_secure' => 'r12',
        'smtp_user'   => 'r13',
        'smtp_pass'   => 'r14',
        'mail_from'   => 'r15',
    ];

    /** @var array<string,mixed>|null Загруженные значения (по смысловым ключам). */
    private static ?array $values = null;

    /** Путь к файлу конфигурации. */
    public static function file(): string
    {
        return CMS_ROOT . '/include/connect.php';
    }

    /** Установлена ли CMS (есть ли файл конфигурации). */
    public static function isInstalled(): bool
    {
        return is_file(self::file());
    }

    /** Получить параметр по смысловому ключу (см. MAP). */
    public static function get(string $key, $default = null)
    {
        if (self::$values === null) {
            self::load();
        }
        return self::$values[$key] ?? $default;
    }

    /** Подменить значения (используется в тестах). */
    public static function override(array $values): void
    {
        if (self::$values === null) {
            self::$values = [];
        }
        self::$values = array_merge(self::$values, $values);
    }

    /** Адрес сайта без завершающего слеша, например https://domain.com */
    public static function siteUrl(): string
    {
        return rtrim((string) self::get('site_url', ''), '/');
    }

    /** Читает include/connect.php в изолированной области видимости. */
    private static function load(): void
    {
        self::$values = [];
        if (!self::isInstalled()) {
            return;
        }
        $vars = (static function (string $file): array {
            include $file;
            return get_defined_vars();
        })(self::file());
        foreach (self::MAP as $key => $var) {
            if (array_key_exists($var, $vars)) {
                self::$values[$key] = $vars[$var];
            }
        }
    }

    /**
     * Сформировать текст файла connect.php из смысловых значений.
     * Используется установщиком.
     */
    public static function render(array $values): string
    {
        $out = "<?php\n";
        foreach (self::MAP as $key => $var) {
            $out .= '$' . $var . ' = ' . var_export((string) ($values[$key] ?? ''), true) . ";\n";
        }
        return $out;
    }
}
