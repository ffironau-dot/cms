<?php
/**
 * Текстовая страница. Содержание (оглавление) строится автоматически
 * по заголовкам h2 длинного текста.
 */

declare(strict_types=1);

namespace Site;

use Core\Models\Page;
use Core\Models\Section;
use Core\Upload;
use Core\Urls;

final class PageController extends Controller
{
    public function show(int $id, string $path): void
    {
        $page = Page::find($id);
        if (!$page || !Page::isVisible($page)) {
            throw new NotFound();
        }
        $section = $page['section_id'] !== null ? Section::find((int) $page['section_id']) : null;

        $crumbs = [['Главная', '/']];
        if ($section) {
            $crumbs[] = [$section['name'], $section['path']];
        } else {
            // Для вложенного адреса (/o-kompanii/komanda/) в крошки попадают
            // существующие видимые страницы по адресам-предкам.
            $segments = array_filter(explode('/', trim($path, '/')));
            $prefix = '/';
            foreach (array_slice($segments, 0, -1) as $seg) {
                $prefix .= $seg . '/';
                $r = Urls::find($prefix);
                if ($r && $r['entity_type'] === Urls::PAGE && ($p = Page::find((int) $r['entity_id'])) && Page::isVisible($p)) {
                    $crumbs[] = [$p['name'], $prefix];
                }
            }
        }
        $crumbs[] = [$page['name'], $path];

        [$html, $toc] = self::buildToc((string) $page['text_big']);
        $image = Upload::url(Page::UPLOAD_DIR, $page['image']);
        $seo = $this->seoFrom($page, $path, 1, $image);
        $seo->ogType = 'article';

        Layout::render('page', [
            'seo'     => $seo,
            'page'    => $page,
            'section' => $section,
            'image'   => $image,
            'html'    => $html,
            'toc'     => $toc,
            'crumbs'  => $crumbs,
        ], $path);
    }

    /**
     * Оглавление по заголовкам h2: заголовкам без id добавляется id="sN".
     *
     * @return array{0:string, 1:array} [HTML с id у заголовков, [[id, текст], ...]]
     */
    public static function buildToc(string $html): array
    {
        $toc = [];
        $n = 0;
        $html = (string) preg_replace_callback('#<h2(\s[^>]*)?>(.*?)</h2>#is', static function ($m) use (&$toc, &$n) {
            $n++;
            $attrs = $m[1] ?? '';
            if (preg_match('/\sid\s*=\s*["\']([^"\']+)["\']/i', $attrs, $idm)) {
                $id = $idm[1];
            } else {
                $id = 's' . $n;
                $attrs .= ' id="' . $id . '"';
            }
            $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text !== '') {
                $toc[] = [$id, $text];
            }
            return '<h2' . $attrs . '>' . $m[2] . '</h2>';
        }, $html);
        return [$html, $toc];
    }
}
