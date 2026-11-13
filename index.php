<?php
/**
 * Единая точка входа CMS.
 *
 * Все запросы, не относящиеся к существующим файлам, направляются сюда
 * правилами .htaccess. Запросы, адрес которых начинается с /manet,
 * обрабатывает административная панель, остальные — публичная часть.
 */

declare(strict_types=1);

require __DIR__ . '/include/bootstrap.php';

// Пока CMS не установлена, отправляем на установщик.
if (!\Core\Config::isInstalled()) {
    if (is_file(__DIR__ . '/start.php')) {
        header('Location: /start.php', true, 302);
    } else {
        http_response_code(503);
        echo 'CMS не установлена: отсутствует файл include/connect.php.';
    }
    exit;
}

$path = \Core\Request::path();

if ($path === CMS_ADMIN_PREFIX || strncmp($path, CMS_ADMIN_PREFIX . '/', strlen(CMS_ADMIN_PREFIX) + 1) === 0) {
    (new \Admin\App())->run($path);
} else {
    (new \Site\App())->run($path);
}
