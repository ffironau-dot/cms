<?php
/**
 * Вспомогательные функции для работы с HTTP-запросом.
 */

declare(strict_types=1);

namespace Core;

final class Request
{
    /** Путь запроса без строки параметров, декодированный (UTF-8). */
    public static function path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        $path = rawurldecode($path);
        // Повторяющиеся слеши схлопываем.
        $path = preg_replace('#/{2,}#', '/', $path) ?? '/';
        return $path;
    }

    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /** Строковый параметр из GET. */
    public static function get(string $key, string $default = ''): string
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? $v : $default;
    }

    /** Строковый параметр из POST. */
    public static function post(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $default;
        return is_string($v) ? $v : $default;
    }

    /** Целое число из GET (0, если параметр отсутствует или некорректен). */
    public static function getInt(string $key): int
    {
        $v = self::get($key);
        return ctype_digit($v) ? (int) $v : 0;
    }

    /** IP-адрес клиента. */
    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /** IP-адрес в бинарном виде для хранения в VARBINARY(16). */
    public static function ipBinary(): string
    {
        $bin = @inet_pton(self::ip());
        return $bin === false ? "\0\0\0\0" : $bin;
    }

    /** Работает ли сайт по HTTPS. */
    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /**
     * Номер страницы пагинации из ?page=N.
     * Возвращает 1, если параметра нет; null — если значение некорректно
     * (не число, 0, ведущие нули) — такие адреса отдают 404.
     */
    public static function pageNumber(): ?int
    {
        if (!array_key_exists('page', $_GET)) {
            return 1;
        }
        $v = $_GET['page'];
        if (!is_string($v) || !preg_match('/^[1-9][0-9]{0,6}$/', $v)) {
            return null;
        }
        return (int) $v;
    }
}
