<?php
/**
 * Установщик CMS (п. 8.2 ТЗ).
 *
 * Запрашивает параметры БД, адрес сайта, логин и пароль администратора,
 * email для заявок и параметры SMTP; создаёт все таблицы (включая
 * системную категорию «Без категории» и первый слайд), записывает
 * параметры в include/connect.php (пароль — в виде хеша) и robots.txt.
 *
 * После успешной установки повторный запуск блокируется (файл
 * include/connect.php уже существует), а в админке выводится
 * предупреждение с просьбой удалить start.php.
 */

declare(strict_types=1);

require __DIR__ . '/include/bootstrap.php';

use Core\Config;
use Core\Db;
use Core\Html;

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

/** Вывод страницы установщика. */
function installer_page(string $body): void
{
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>Установка CMS</title><link rel="stylesheet" href="/assets/admin.css"></head>'
        . '<body class="a-login"><div class="a-login__box" style="width:min(640px,calc(100% - 32px))">' . $body . '</div></body></html>';
}

if (Config::isInstalled()) {
    http_response_code(403);
    installer_page('<h1 class="a-title">CMS уже установлена</h1><p>Повторный запуск установщика заблокирован. Удалите файл <code>start.php</code> с сервера.</p><p><a href="/manet">Перейти в админку</a></p>');
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$v = [
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'site_url' => $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
    'admin_login' => 'admin', 'mail_to' => '',
    'smtp_host' => '', 'smtp_port' => '465', 'smtp_secure' => 'ssl', 'smtp_user' => '', 'smtp_pass' => '', 'mail_from' => '',
];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach ($v as $k => $_) {
        $v[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $password = (string) ($_POST['admin_pass'] ?? '');
    $password2 = (string) ($_POST['admin_pass2'] ?? '');

    // --- Проверка введённых данных ---
    if ($v['db_name'] === '' || $v['db_user'] === '') {
        $errors[] = 'Укажите имя базы данных и пользователя.';
    }
    $v['site_url'] = rtrim($v['site_url'], '/');
    if (!preg_match('#^https?://[^/\s]+$#i', $v['site_url'])) {
        $errors[] = 'Адрес сайта — в виде https://domain.com (без пути и слеша в конце).';
    }
    if (!preg_match('/^[A-Za-z0-9._@-]{3,64}$/', $v['admin_login'])) {
        $errors[] = 'Логин администратора: от 3 до 64 символов (латиница, цифры, . _ @ -).';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Пароль администратора — не короче 8 символов.';
    } elseif ($password !== $password2) {
        $errors[] = 'Пароли не совпадают.';
    }
    if ($v['mail_to'] !== '' && !filter_var($v['mail_to'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email для заявок указан неверно.';
    }
    if ($v['mail_from'] !== '' && !filter_var($v['mail_from'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Адрес отправителя писем указан неверно.';
    }
    if (!in_array($v['smtp_secure'], ['', 'ssl', 'tls'], true)) {
        $errors[] = 'Шифрование SMTP: ssl, tls или без шифрования.';
    }
    foreach (['include', 'uploads'] as $dir) {
        if (!is_writable(__DIR__ . '/' . $dir)) {
            $errors[] = 'Нет прав на запись в папку ' . $dir . '/.';
        }
    }

    // --- Подключение к БД ---
    $pdo = null;
    if (!$errors) {
        try {
            $pdo = Db::connect($v['db_host'], $v['db_port'], $v['db_name'], $v['db_user'], $v['db_pass']);
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            if (version_compare(preg_replace('/[^0-9.].*$/', '', $version), '5.7.0', '<')) {
                $errors[] = 'Требуется MySQL 5.7 или новее, на сервере: ' . $version . '.';
            }
            if ($pdo->query("SHOW TABLES LIKE 'settings'")->fetchColumn() !== false) {
                $errors[] = 'В базе данных уже есть таблицы CMS. Используйте пустую базу данных.';
            }
        } catch (\PDOException $e) {
            $errors[] = 'Не удалось подключиться к базе данных: ' . $e->getMessage();
        }
    }

    // --- Создание таблиц и начальных данных ---
    if (!$errors) {
        try {
            \Core\Installer::install($pdo);

            // Файл конфигурации (пароль — только в виде хеша).
            $config = Config::render([
                'db_host' => $v['db_host'], 'db_port' => $v['db_port'], 'db_name' => $v['db_name'],
                'db_user' => $v['db_user'], 'db_pass' => $v['db_pass'], 'site_url' => $v['site_url'],
                'admin_login' => $v['admin_login'], 'admin_hash' => password_hash($password, PASSWORD_DEFAULT),
                'mail_to' => $v['mail_to'], 'smtp_host' => $v['smtp_host'], 'smtp_port' => $v['smtp_port'],
                'smtp_secure' => $v['smtp_secure'], 'smtp_user' => $v['smtp_user'], 'smtp_pass' => $v['smtp_pass'],
                'mail_from' => $v['mail_from'],
            ]);
            if (file_put_contents(Config::file(), $config, LOCK_EX) === false) {
                throw new \RuntimeException('Не удалось записать include/connect.php.');
            }
            @chmod(Config::file(), 0640);

            // Статический robots.txt с указанием на sitemap.xml (адрес админки не указывается).
            $robots = "User-agent: *\nDisallow:\n\nSitemap: " . $v['site_url'] . "/sitemap.xml\n";
            $robotsNote = '';
            if (@file_put_contents(__DIR__ . '/robots.txt', $robots) === false) {
                $robotsNote = '<div class="a-alert a-alert--error">Не удалось записать robots.txt (нет прав на запись в корень сайта). Создайте файл robots.txt вручную с содержимым:<pre>' . Html::e($robots) . '</pre></div>';
            }

            installer_page('<h1 class="a-title">Установка завершена</h1>'
                . '<div class="a-alert a-alert--warning">Удалите файл start.php с сервера.</div>'
                . '<p>Таблицы созданы, параметры записаны в <code>include/connect.php</code>.</p>' . $robotsNote
                . '<p><a class="a-btn" href="/manet">Перейти в админку</a> <a class="a-btn a-btn--ghost" href="/">Открыть сайт</a></p>');
            exit;
        } catch (\Throwable $e) {
            $errors[] = 'Ошибка установки: ' . $e->getMessage();
        }
    }
}

$f = static function (string $name, string $label, string $type = 'text', string $hint = '') use ($v): string {
    return '<div class="a-field"><label for="i-' . $name . '">' . Html::e($label) . '</label>'
        . '<input id="i-' . $name . '" type="' . $type . '" name="' . $name . '" value="' . ($type === 'password' ? '' : Html::e($v[$name] ?? '')) . '">'
        . ($hint !== '' ? '<p class="a-hint">' . Html::e($hint) . '</p>' : '') . '</div>';
};
$errHtml = '';
if ($errors) {
    $errHtml = '<div class="a-alert a-alert--error"><ul><li>' . implode('</li><li>', array_map([Html::class, 'e'], $errors)) . '</li></ul></div>';
}
installer_page('<h1 class="a-title">Установка CMS</h1>' . $errHtml
    . '<form method="post" class="a-form">'
    . '<fieldset class="a-fieldset"><legend>База данных MySQL 5.7+</legend>'
    . $f('db_host', 'Хост') . $f('db_port', 'Порт') . $f('db_name', 'Имя базы данных', 'text', 'База должна быть создана заранее и быть пустой, кодировка utf8mb4.')
    . $f('db_user', 'Пользователь') . $f('db_pass', 'Пароль', 'password') . '</fieldset>'
    . '<fieldset class="a-fieldset"><legend>Сайт и администратор</legend>'
    . $f('site_url', 'Адрес сайта', 'text', 'Например https://domain.com — используется в canonical, sitemap.xml и OG-тегах.')
    . $f('admin_login', 'Логин администратора') . $f('admin_pass', 'Пароль администратора', 'password', 'Не короче 8 символов. Хранится только в виде хеша.')
    . $f('admin_pass2', 'Пароль ещё раз', 'password') . '</fieldset>'
    . '<fieldset class="a-fieldset"><legend>Заявки с сайта</legend>'
    . $f('mail_to', 'Email получателя заявок')
    . $f('smtp_host', 'SMTP-сервер', 'text', 'Например smtp.yandex.ru. Если оставить пустым — письма отправляются функцией mail().')
    . $f('smtp_port', 'SMTP-порт', 'text', '465 (SSL) или 587 (TLS).')
    . '<div class="a-field"><label for="i-smtp_secure">Шифрование</label><select id="i-smtp_secure" name="smtp_secure">'
    . '<option value="ssl"' . ($v['smtp_secure'] === 'ssl' ? ' selected' : '') . '>SSL</option>'
    . '<option value="tls"' . ($v['smtp_secure'] === 'tls' ? ' selected' : '') . '>STARTTLS</option>'
    . '<option value=""' . ($v['smtp_secure'] === '' ? ' selected' : '') . '>без шифрования</option></select></div>'
    . $f('smtp_user', 'SMTP-логин') . $f('smtp_pass', 'SMTP-пароль', 'password')
    . $f('mail_from', 'Адрес отправителя', 'text', 'Обычно совпадает с SMTP-логином.') . '</fieldset>'
    . '<div class="a-actions"><button type="submit" class="a-btn">Установить</button></div></form>');
