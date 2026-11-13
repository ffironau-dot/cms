<?php
/**
 * Автоматический sitemap.xml (п. 8.4 ТЗ): главная, активные категории,
 * товары, разделы и текстовые страницы (с учётом скрытия неактивными
 * родителями), с датами изменения lastmod.
 */

declare(strict_types=1);

namespace Site;

use Core\Db;
use Core\Html;
use Core\Models\Category;

final class SitemapController
{
    public function show(): void
    {
        $urls = [['/', null]];
        foreach (Category::all() as $c) {
            if (Category::isVisible($c) && $c['path']) {
                $urls[] = [$c['path'], $c['updated_at']];
            }
        }
        $visible = Category::visibleIds();
        if ($visible) {
            $rows = Db::all(
                "SELECT r.path, p.updated_at FROM products p
                 JOIN routes r ON r.entity_type = 'product' AND r.entity_id = p.id
                 WHERE p.is_active = 1 AND p.category_id IN (" . Db::placeholders($visible) . ') ORDER BY r.path',
                $visible
            );
            foreach ($rows as $r) {
                $urls[] = [$r['path'], $r['updated_at']];
            }
        }
        $rows = Db::all(
            "SELECT r.path, s.updated_at FROM sections s
             JOIN routes r ON r.entity_type = 'section' AND r.entity_id = s.id
             WHERE s.is_active = 1 ORDER BY r.path"
        );
        foreach ($rows as $r) {
            $urls[] = [$r['path'], $r['updated_at']];
        }
        $rows = Db::all(
            "SELECT r.path, p.updated_at FROM pages p
             JOIN routes r ON r.entity_type = 'page' AND r.entity_id = p.id
             LEFT JOIN sections s ON s.id = p.section_id
             WHERE p.is_active = 1 AND (p.section_id IS NULL OR s.is_active = 1) ORDER BY r.path"
        );
        foreach ($rows as $r) {
            $urls[] = [$r['path'], $r['updated_at']];
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$path, $updated]) {
            echo '  <url><loc>' . Html::e(Seo::absolute($path)) . '</loc>';
            if ($updated) {
                echo '<lastmod>' . date('c', (int) strtotime($updated)) . '</lastmod>';
            }
            echo "</url>\n";
        }
        echo "</urlset>\n";
    }
}
