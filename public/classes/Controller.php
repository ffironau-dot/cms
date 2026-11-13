<?php
/**
 * Базовый контроллер публичной части: общие помощники.
 */

declare(strict_types=1);

namespace Site;

use Core\Paginator;
use Core\Request;
use Core\Response;

abstract class Controller
{
    /**
     * Номер страницы пагинации с проверками (п. 5.1): некорректный номер
     * — 404, ?page=1 — редирект 301 на основной адрес без параметра.
     */
    protected function pageNumber(string $basePath): int
    {
        $n = Request::pageNumber();
        if ($n === null) {
            throw new NotFound();
        }
        if ($n === 1 && isset($_GET['page'])) {
            Response::redirect(Seo::absolute($basePath), 301);
        }
        return $n;
    }

    /** Пагинатор; номер за пределами диапазона — 404. */
    protected function paginate(int $total, int $perPage, int $page): Paginator
    {
        $p = new Paginator($total, $perPage, $page);
        if (!$p->isValid()) {
            throw new NotFound();
        }
        return $p;
    }

    /** Заполнить SEO из полей сущности (Title, Description, OG). */
    protected function seoFrom(array $entity, string $canonical, int $page = 1, string $image = ''): Seo
    {
        $seo = new Seo();
        $seo->title = (string) ($entity['meta_title'] ?: $entity['name']);
        if ($page > 1) {
            $seo->title .= ' — страница ' . $page;
        }
        $seo->description = (string) $entity['meta_description'];
        $seo->canonical = $page > 1 ? Paginator::url($canonical, $page) : $canonical;
        $seo->ogTitle = (string) ($entity['og_title'] ?: $seo->title);
        $seo->ogDescription = (string) ($entity['og_description'] ?: $seo->description);
        $seo->ogImage = $image;
        return $seo;
    }
}
