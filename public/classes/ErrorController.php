<?php
/**
 * Страницы ошибок: 404 и 500.
 */

declare(strict_types=1);

namespace Site;

final class ErrorController
{
    public function notFound(): void
    {
        $seo = new Seo();
        $seo->title = 'Страница не найдена';
        try {
            Layout::render('error', ['seo' => $seo, 'code' => 404], '', 404);
        } catch (\Throwable $e) {
            http_response_code(404);
            echo 'Страница не найдена';
        }
    }

    public function serverError(): void
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="ru"><meta charset="utf-8"><title>Ошибка сервера</title>'
            . '<p style="font-family:sans-serif;padding:40px">Произошла ошибка на сервере. Попробуйте обновить страницу позже.</p></html>';
    }
}
