<?php
/**
 * Маршрутизатор административной панели (адрес /manet).
 *
 * Все страницы, кроме входа, доступны только авторизованному
 * администратору. Все ответы помечаются заголовком X-Robots-Tag: noindex
 * (адрес админки не публикуется в robots.txt, п. 8.4).
 * Все изменяющие действия выполняются методом POST с CSRF-токеном.
 */

declare(strict_types=1);

namespace Admin;

final class App
{
    /** Маршруты: путь после /manet → [класс контроллера, метод]. */
    private const ROUTES = [
        ''                       => [DashboardController::class, 'index'],
        '/login'                 => [LoginController::class, 'login'],
        '/logout'                => [LoginController::class, 'logout'],
        '/address'               => [SettingsController::class, 'address'],
        '/phone'                 => [SettingsController::class, 'phone'],
        '/logo'                  => [SettingsController::class, 'logo'],
        '/home'                  => [SettingsController::class, 'home'],
        '/menu'                  => [MenuController::class, 'index'],
        '/menu/edit'             => [MenuController::class, 'edit'],
        '/menu/delete'           => [MenuController::class, 'delete'],
        '/slides'                => [SlidesController::class, 'index'],
        '/slides/edit'           => [SlidesController::class, 'edit'],
        '/slides/delete'         => [SlidesController::class, 'delete'],
        '/slides/settings'       => [SlidesController::class, 'settings'],
        '/categories'            => [CategoriesController::class, 'index'],
        '/categories/edit'       => [CategoriesController::class, 'edit'],
        '/categories/delete'     => [CategoriesController::class, 'delete'],
        '/categories/props'      => [PropertiesController::class, 'index'],
        '/categories/prop-edit'  => [PropertiesController::class, 'edit'],
        '/categories/prop-delete' => [PropertiesController::class, 'delete'],
        '/products'              => [ProductsController::class, 'index'],
        '/products/edit'         => [ProductsController::class, 'edit'],
        '/products/delete'       => [ProductsController::class, 'delete'],
        '/products/image-add'    => [ProductsController::class, 'imageAdd'],
        '/products/images-save'  => [ProductsController::class, 'imagesSave'],
        '/products/image-delete' => [ProductsController::class, 'imageDelete'],
        '/products/import'       => [ImportController::class, 'import'],
        '/products/import-sample' => [ImportController::class, 'sample'],
        '/pages'                 => [PagesController::class, 'index'],
        '/pages/edit'            => [PagesController::class, 'edit'],
        '/pages/delete'          => [PagesController::class, 'delete'],
        '/sections'              => [SectionsController::class, 'index'],
        '/sections/edit'         => [SectionsController::class, 'edit'],
        '/sections/delete'       => [SectionsController::class, 'delete'],
        '/files'                 => [FilesController::class, 'index'],
        '/files/delete'          => [FilesController::class, 'delete'],
    ];

    public function run(string $path): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store');

        Auth::startSession();

        $sub = rtrim(substr($path, strlen(CMS_ADMIN_PREFIX)), '/');
        $route = self::ROUTES[$sub] ?? null;

        // Без авторизации доступна только страница входа.
        if ($sub !== '/login' && !Auth::check()) {
            header('Location: ' . CMS_ADMIN_PREFIX . '/login', true, 302);
            return;
        }
        if ($route === null) {
            http_response_code(404);
            (new DashboardController())->notFound();
            return;
        }
        [$class, $method] = $route;
        try {
            (new $class())->$method();
        } catch (\Throwable $e) {
            error_log('CMS admin: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            (new DashboardController())->error($e);
        }
    }
}
