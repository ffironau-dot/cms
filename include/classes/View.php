<?php
/**
 * Представление: подключение PHP-шаблона с передачей переменных.
 */

declare(strict_types=1);

namespace Core;

final class View
{
    /**
     * Отрисовать шаблон и вернуть HTML.
     *
     * @param string $file Полный путь к файлу шаблона.
     * @param array  $vars Переменные, доступные в шаблоне.
     */
    public static function render(string $file, array $vars = []): string
    {
        ob_start();
        try {
            (static function (string $__file, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                include $__file;
            })($file, $vars);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
