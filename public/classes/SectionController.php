<?php
/**
 * Страница раздела (например, «Блог»): текст раздела и лента страниц
 * с анонсами — новые первыми, по 15 на страницу, с пагинацией.
 */

declare(strict_types=1);

namespace Site;

use Core\Models\Page;
use Core\Models\Section;

final class SectionController extends Controller
{
    public function show(int $id, string $path): void
    {
        $section = Section::find($id);
        if (!$section || (int) $section['is_active'] !== 1) {
            throw new NotFound();
        }
        $page = $this->pageNumber($path);
        $pager = $this->paginate(Page::feedCount($id), Page::FEED_PER_PAGE, $page);

        Layout::render('section', [
            'seo'     => $this->seoFrom($section, $path, $page),
            'section' => $section,
            'items'   => Page::feed($id, Page::FEED_PER_PAGE, $pager->offset()),
            'pager'   => $pager,
            'basePath' => $path,
            'crumbs'  => [['Главная', '/'], [$section['name'], $path]],
        ], $path);
    }
}
