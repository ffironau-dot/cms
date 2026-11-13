<?php
/**
 * Товары (п. 5.2): список (50 на страницу, поиск по артикулу и названию,
 * фильтр по категории), создание, редактирование, удаление,
 * дополнительные фото.
 */

declare(strict_types=1);

namespace Admin;

use Core\Models\Category;
use Core\Models\Product;
use Core\Paginator;
use Core\Request;
use Core\Upload;

final class ProductsController extends Controller
{
    public const PER_PAGE = 50;

    public function index(): void
    {
        $q = trim(Request::get('q'));
        $categoryId = Request::getInt('category_id');
        $page = max(1, Request::getInt('page'));
        $total = Product::adminList($q, $categoryId, 1, 0)['total'];
        $pager = new Paginator($total, self::PER_PAGE, $page);
        if (!$pager->isValid()) {
            $pager = new Paginator($total, self::PER_PAGE, 1);
        }
        $list = Product::adminList($q, $categoryId, self::PER_PAGE, $pager->offset());
        $this->render('products-list', [
            'rows'       => $list['rows'],
            'pager'      => $pager,
            'q'          => $q,
            'categoryId' => $categoryId,
            'categories' => Category::flatTree(),
        ], 'Товары', 'products');
    }

    public function edit(): void
    {
        $id = Request::getInt('id') ?: null;
        $product = $id ? Product::find($id) : null;
        if ($id && !$product) {
            $this->flash('error', 'Товар не найден.');
            $this->redirect('/products');
        }
        $errors = [];
        $values = $product ?? [
            'category_id' => (int) Category::system()['id'], 'sku' => '', 'slug' => '', 'name' => '',
            'meta_title' => '', 'meta_description' => '', 'og_title' => '', 'og_description' => '',
            'image' => '', 'text_small' => '', 'text_big' => '', 'price_old' => null, 'price' => null, 'is_active' => 1,
        ];
        $propValues = [];
        if ($product) {
            foreach (Product::propertyValues($id) as $pid => $v) {
                $propValues[$pid] = $v['option_id'] !== null ? (string) $v['option_id'] : (string) $v['value'];
            }
        }
        if ($this->isValidPost()) {
            $in = $_POST;
            $newFile = null;
            try {
                $newFile = $this->processImage('image', Product::UPLOAD_DIR, Upload::ALL_IMAGES, $in);
                $res = Product::save($in, $id);
                if (!$res['errors']) {
                    $this->cleanupOldImage(Product::UPLOAD_DIR, $product, $in);
                    $newId = (int) $res['id'];
                    $propErrors = [];
                    if (!$res['category_changed'] && $id !== null) {
                        $propErrors = Product::saveProperties($newId, (array) ($_POST['props'] ?? []));
                    }
                    if ($propErrors) {
                        foreach ($propErrors as $e) {
                            $this->flash('error', $e);
                        }
                        $this->flash('info', 'Основные поля товара сохранены, значения свойств — нет.');
                    } elseif ($res['category_changed']) {
                        $this->flash('success', 'Товар сохранён. Категория изменена: значения свойств прежней категории удалены, заполните свойства новой категории.');
                    } else {
                        $this->flash('success', 'Товар сохранён.');
                    }
                    // Новый товар — сразу на редактирование (свойства и доп. фото).
                    $this->redirect($id === null || $res['category_changed'] || $propErrors ? '/products/edit?id=' . $newId : '/products');
                }
                $errors = $res['errors'];
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
            if ($newFile) {
                Upload::delete(Product::UPLOAD_DIR, $newFile);
            }
            $values = array_merge($values, $_POST);
            $values['is_active'] = !empty($_POST['is_active']);
            $propValues = (array) ($_POST['props'] ?? []);
        }
        $categories = [];
        foreach (Category::flatTree() as [$c, $level]) {
            $categories[$c['id']] = ($level === 2 ? '— ' : '') . $c['name'];
        }
        $this->render('product-form', [
            'values'     => $values,
            'errors'     => $errors,
            'id'         => $id,
            'product'    => $product,
            'categories' => $categories,
            'props'      => $product ? Category::properties((int) $product['category_id']) : [],
            'propValues' => $propValues,
            'images'     => $id ? Product::images($id) : [],
            'imageUrl'   => Upload::url(Product::UPLOAD_DIR, $product['image'] ?? ''),
        ], $id ? 'Редактирование товара' : 'Новый товар', 'products');
    }

    public function delete(): void
    {
        $id = Request::getInt('id');
        $product = Product::find($id);
        if (!$product) {
            $this->flash('error', 'Товар не найден.');
            $this->redirect('/products');
        }
        if ($this->isValidPost()) {
            Product::delete($id);
            $this->flash('success', 'Товар «' . $product['name'] . '» удалён.');
            $this->redirect('/products');
        }
        $this->confirm('Удаление товара', 'Удалить товар «' . $product['name'] . '» (арт. ' . $product['sku'] . ')? Основное и дополнительные фото будут удалены с сервера.', '/products', 'products');
    }

    /** Добавить дополнительное фото (по одному). */
    public function imageAdd(): void
    {
        $id = Request::getInt('id');
        if (!Product::find($id)) {
            $this->redirect('/products');
        }
        if ($this->isValidPost()) {
            try {
                $file = Upload::fromRequest('photo');
                if (!$file) {
                    throw new \RuntimeException('Выберите файл фото.');
                }
                $name = Upload::storeImage($file, Product::UPLOAD_DIR, Upload::ALL_IMAGES);
                Product::addImage($id, $name, Request::post('alt'), (int) Request::post('sort'));
                $this->flash('success', 'Фото добавлено.');
            } catch (\RuntimeException $e) {
                $this->flash('error', $e->getMessage());
            }
        }
        $this->redirect('/products/edit?id=' . $id . '#photos');
    }

    /** Сохранить alt и порядок дополнительных фото. */
    public function imagesSave(): void
    {
        $id = Request::getInt('id');
        if ($this->isValidPost()) {
            foreach ((array) ($_POST['images'] ?? []) as $imgId => $data) {
                \Core\Db::query(
                    'UPDATE product_images SET alt = ?, sort = ? WHERE id = ? AND product_id = ?',
                    [\Core\Html::filterPlain((string) ($data['alt'] ?? '')), (int) ($data['sort'] ?? 0), (int) $imgId, $id]
                );
            }
            $this->flash('success', 'Дополнительные фото сохранены.');
        }
        $this->redirect('/products/edit?id=' . $id . '#photos');
    }

    /** Удалить дополнительное фото (с подтверждением). */
    public function imageDelete(): void
    {
        $imgId = Request::getInt('id');
        $img = \Core\Db::row('SELECT * FROM product_images WHERE id = ?', [$imgId]);
        if (!$img) {
            $this->flash('error', 'Фото не найдено.');
            $this->redirect('/products');
        }
        $back = '/products/edit?id=' . $img['product_id'] . '#photos';
        if ($this->isValidPost()) {
            Product::deleteImage($imgId);
            $this->flash('success', 'Фото удалено.');
            $this->redirect($back);
        }
        $this->confirm('Удаление фото', 'Удалить это дополнительное фото? Файл будет удалён с сервера.', $back, 'products',
            ['image' => Upload::url(Product::UPLOAD_DIR, $img['file'])]);
    }
}
