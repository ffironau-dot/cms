<?php
/**
 * Карточка товара: галерея, цены, характеристики из свойств категории,
 * описание, кнопка «Заказать» со всплывающей формой.
 */

declare(strict_types=1);

namespace Site;

use Core\Models\Category;
use Core\Models\Product;
use Core\Upload;

final class ProductController extends Controller
{
    public function show(int $id, string $path): void
    {
        $product = Product::find($id);
        if (!$product || !Product::isVisible($product)) {
            throw new NotFound();
        }
        $category = Category::find((int) $product['category_id']);

        // Галерея: основное фото, затем дополнительные по порядку.
        $gallery = [];
        if ($product['image']) {
            $gallery[] = ['src' => Upload::url(Product::UPLOAD_DIR, $product['image']), 'alt' => $product['name']];
        }
        foreach (Product::images($id) as $img) {
            $gallery[] = ['src' => Upload::url(Product::UPLOAD_DIR, $img['file']), 'alt' => $img['alt'] ?: $product['name']];
        }

        $crumbs = [['Главная', '/']];
        foreach (Category::chain($category) as $c) {
            $crumbs[] = [$c['name'], $c['path']];
        }
        $crumbs[] = [$product['name'], $path];

        $seo = $this->seoFrom($product, $path, 1, Upload::url(Product::UPLOAD_DIR, $product['image']));
        $seo->ogType = 'product';

        // Для подсветки меню используется адрес категории товара (п. 4.4).
        Layout::render('product', [
            'seo'      => $seo,
            'product'  => $product,
            'category' => $category,
            'gallery'  => $gallery,
            'specs'    => Product::specs($product),
            'crumbs'   => $crumbs,
            'path'     => $path,
        ], (string) $category['path']);
    }
}
