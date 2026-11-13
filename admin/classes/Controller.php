<?php
/**
 * Базовый контроллер административной панели.
 */

declare(strict_types=1);

namespace Admin;

use Core\Request;
use Core\View;

abstract class Controller
{
    /** Каталог шаблонов админки. */
    protected function tplDir(): string
    {
        return CMS_ROOT . '/admin/templates';
    }

    /**
     * Отрисовать страницу админки: шаблон $template внутри общего каркаса
     * (меню слева, рабочая область справа).
     */
    protected function render(string $template, array $vars, string $title, string $section = ''): void
    {
        $content = View::render($this->tplDir() . '/' . $template . '.php', $vars);
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        header('Content-Type: text/html; charset=utf-8');
        echo View::render($this->tplDir() . '/layout.php', [
            'content'  => $content,
            'title'    => $title,
            'section'  => $section,
            'flash'    => $flash,
            'warnings' => self::serviceFileWarnings(),
        ]);
    }

    /** Сообщение для следующей страницы (success | error | info). */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = [$type, $message];
    }

    /** Редирект внутри админки. */
    protected function redirect(string $sub): never
    {
        header('Location: ' . CMS_ADMIN_PREFIX . $sub, true, 303);
        exit;
    }

    /**
     * Проверка POST-запроса и CSRF-токена. При ошибке токена — сообщение
     * и возврат на исходную страницу.
     */
    protected function isValidPost(): bool
    {
        if (!Request::isPost()) {
            return false;
        }
        if (!Auth::checkCsrf()) {
            $this->flash('error', 'Форма устарела или отправлена с другого сайта (ошибка проверки CSRF). Повторите действие.');
            return false;
        }
        return true;
    }

    /**
     * Страница подтверждения удаления (запрос подтверждения перед
     * удалением, п. 4.4, 4.5, 5.1, 5.2, 6, 6.1 ТЗ).
     */
    protected function confirm(string $title, string $message, string $cancelSub, string $section, array $extra = []): void
    {
        $this->render('confirm', ['message' => $message, 'cancel' => CMS_ADMIN_PREFIX . $cancelSub, 'extra' => $extra], $title, $section);
    }

    /**
     * Обработка поля загрузки основного изображения сущности.
     * Новый файл сохраняется и записывается в $in['image']; отмеченный
     * чекбокс «удалить изображение» даёт $in['image'] = null.
     * Возвращает имя нового файла (чтобы удалить его, если сохранение
     * сущности не удалось) или null.
     *
     * @throws \RuntimeException при недопустимом файле
     */
    protected function processImage(string $field, string $dir, array $allowed, array &$in): ?string
    {
        if ($file = \Core\Upload::fromRequest($field)) {
            $name = \Core\Upload::storeImage($file, $dir, $allowed);
            $in['image'] = $name;
            return $name;
        }
        if (Request::post($field . '_remove') === '1') {
            $in['image'] = null;
        }
        return null;
    }

    /**
     * После успешного сохранения: удалить старый файл, если изображение
     * заменено или удалено (п. 4.2, 4.5, 5.2 ТЗ).
     */
    protected function cleanupOldImage(string $dir, ?array $current, array $in): void
    {
        if ($current && array_key_exists('image', $in) && $current['image'] && $current['image'] !== $in['image']) {
            \Core\Upload::delete($dir, $current['image']);
        }
    }

    /**
     * Предупреждения о служебных файлах, которые нужно удалить с сервера
     * (установщик start.php и скрипт получения хеша пароля passhash.php, п. 8.2).
     */
    public static function serviceFileWarnings(): array
    {
        $w = [];
        if (is_file(CMS_ROOT . '/start.php')) {
            $w[] = 'На сервере остался установщик start.php. Удалите этот файл.';
        }
        if (is_file(CMS_ROOT . '/passhash.php')) {
            $w[] = 'На сервере есть служебный скрипт passhash.php (получение хеша пароля). Удалите его после использования.';
        }
        return $w;
    }
}
