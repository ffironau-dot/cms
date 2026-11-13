<?php
/**
 * Маршрутизатор публичной части сайта.
 *
 * Порядок разбора адреса:
 *  1. Служебные адреса: «/» (главная), /sitemap.xml, /send/ (приём заявок).
 *  2. Адрес без завершающего слеша, для которого есть страница со слешем,
 *     — редирект 301 на адрес со слешем.
 *  3. Поиск в таблице routes (категории, товары, разделы, страницы).
 *  4. Поиск в таблице redirects — редирект 301 на новый адрес.
 *  5. Иначе — 404.
 */

declare(strict_types=1);

namespace Site;

use Core\Response;
use Core\Urls;

final class App
{
    public function run(string $path): void
    {
        try {
            $this->dispatch($path);
        } catch (NotFound $e) {
            (new ErrorController())->notFound();
        } catch (\Throwable $e) {
            error_log('CMS: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            (new ErrorController())->serverError();
        }
    }

    private function dispatch(string $path): void
    {
        if ($path === '/' || $path === '/index.php') {
            if ($path === '/index.php') {
                Response::redirect('/', 301);
            }
            (new HomeController())->show();
            return;
        }
        if ($path === '/sitemap.xml') {
            (new SitemapController())->show();
            return;
        }
        if ($path === '/send/' || $path === '/send') {
            (new FormController())->submit();
            return;
        }

        // Нормализация: адрес без завершающего слеша.
        if (!str_ends_with($path, '/')) {
            $withSlash = $path . '/';
            if (Urls::find($withSlash) !== null || Urls::findRedirect($withSlash) !== null) {
                Response::redirect(self::withQuery(Urls::findRedirect($withSlash) ?? $withSlash), 301);
            }
            throw new NotFound();
        }

        $route = Urls::find($path);
        if ($route !== null) {
            $id = (int) $route['entity_id'];
            switch ($route['entity_type']) {
                case Urls::CATEGORY:
                    (new CategoryController())->show($id, $path);
                    return;
                case Urls::PRODUCT:
                    (new ProductController())->show($id, $path);
                    return;
                case Urls::SECTION:
                    (new SectionController())->show($id, $path);
                    return;
                case Urls::PAGE:
                    (new PageController())->show($id, $path);
                    return;
            }
        }

        $target = Urls::findRedirect($path);
        if ($target !== null) {
            Response::redirect(self::withQuery($target), 301);
        }
        throw new NotFound();
    }

    /** Добавить к адресу исходную строку параметров (например, ?page=2). */
    private static function withQuery(string $path): string
    {
        $q = (string) ($_SERVER['QUERY_STRING'] ?? '');
        return Seo::absolute($path) . ($q !== '' ? '?' . $q : '');
    }
}
