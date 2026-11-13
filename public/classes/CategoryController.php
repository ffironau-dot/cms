<?php
/**
 * Страница категории: тексты, изображение, подкатегории, товары
 * (включая товары подкатегорий) с пагинацией по 36 штук.
 */

declare(strict_types=1);

namespace Site;

use Core\Models\Category;
use Core\Models\Product;
use Core\Upload;

final class CategoryController extends Controller
{
    public const PER_PAGE = 36;

    public function show(int $id, string $path): void
    {
        $cat = Category::find($id);
        if (!$cat || !Category::isVisible($cat)) {
            throw new NotFound();
        }
        $page = $this->pageNumber($path);

        // Товары категории и её видимых подкатегорий.
        $ids = [$id];
        $subcategories = [];
        foreach (Category::children($id) as $child) {
            if (Category::isVisible($child)) {
                $ids[] = (int) $child['id'];
                $subcategories[] = $child;
            }
        }
        $pager = $this->paginate(Product::publicCount($ids), self::PER_PAGE, $page);
        $products = Product::publicList($ids, self::PER_PAGE, $pager->offset());

        $image = Upload::url(Category::UPLOAD_DIR, $cat['image']);
        $crumbs = [['Главная', '/']];
        foreach (Category::chain($cat) as $c) {
            $crumbs[] = [$c['name'], $c['path']];
        }

        Layout::render('category', [
            'seo'           => $this->seoFrom($cat, $path, $page, $image),
            'category'      => $cat,
            'image'         => $image,
            'subcategories' => $subcategories,
            'products'      => $products,
            'pager'         => $pager,
            'basePath'      => $path,
            'crumbs'        => $crumbs,
        ], $path);
    }
}
