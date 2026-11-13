<?php
/**
 * Отправка служебных HTTP-ответов.
 */

declare(strict_types=1);

namespace Core;

final class Response
{
    /** Редирект и завершение работы. */
    public static function redirect(string $url, int $code = 302): never
    {
        header('Location: ' . $url, true, $code);
        exit;
    }
}
