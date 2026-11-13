<?php
/**
 * Главная страница админки: сводка и быстрые ссылки.
 */

declare(strict_types=1);

namespace Admin;

use Core\Db;
use Core\Html;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $stats = [
            'Категории'          => (int) Db::value('SELECT COUNT(*) FROM categories'),
            'Товары'             => (int) Db::value('SELECT COUNT(*) FROM products'),
            'Текстовые страницы' => (int) Db::value('SELECT COUNT(*) FROM pages'),
            'Разделы'            => (int) Db::value('SELECT COUNT(*) FROM sections'),
            'Слайды'             => (int) Db::value('SELECT COUNT(*) FROM slides'),
            'Файлы'              => (int) Db::value('SELECT COUNT(*) FROM files'),
            'Редиректы 301'      => (int) Db::value('SELECT COUNT(*) FROM redirects'),
        ];
        $this->render('dashboard', ['stats' => $stats], 'Панель управления', 'dashboard');
    }

    public function notFound(): void
    {
        $this->render('message', ['text' => 'Страница админки не найдена.'], 'Не найдено');
    }

    public function error(\Throwable $e): void
    {
        $this->render('message', ['text' => 'Произошла ошибка: ' . Html::e($e->getMessage())], 'Ошибка');
    }
}
