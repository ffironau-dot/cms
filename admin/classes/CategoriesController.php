<?php
/**
 * Категории товаров (п. 5.1): список, создание, редактирование, удаление.
 */

declare(strict_types=1);

namespace Admin;

use Core\Models\Category;
use Core\Request;
use Core\Upload;

final class CategoriesController extends Controller
{
    public function index(): void
    {
        $rows = [];
        foreach (Category::flatTree() as [$cat, $level]) {
            $cat['level'] = $level;
            $cat['count'] = Category::productCount((int) $cat['id']);
            $rows[] = $cat;
        }
        $this->render('categories-list', ['rows' => $rows], 'Категории товаров', 'categories');
    }

    public function edit(): void
    {
        $id = Request::getInt('id') ?: null;
        $cat = $id ? Category::find($id) : null;
        if ($id && !$cat) {
            $this->flash('error', 'Категория не найдена.');
            $this->redirect('/categories');
        }
        $errors = [];
        $values = $cat ?? [
            'parent_id' => null, 'slug' => '', 'name' => '', 'meta_title' => '', 'meta_description' => '',
            'og_title' => '', 'og_description' => '', 'image' => '', 'text_small' => '', 'text_big' => '',
            'is_active' => 1, 'is_system' => 0, 'path' => '',
        ];
        if ($this->isValidPost()) {
            $in = $_POST;
            $newFile = null;
            try {
                $newFile = $this->processImage('image', Category::UPLOAD_DIR, Upload::ALL_IMAGES, $in);
                $res = Category::save($in, $id);
                if (!$res['errors']) {
                    $this->cleanupOldImage(Category::UPLOAD_DIR, $cat, $in);
                    $this->flash('success', 'Категория сохранена.');
                    $this->redirect('/categories');
                }
                $errors = $res['errors'];
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
            if ($newFile) {
                Upload::delete(Category::UPLOAD_DIR, $newFile);
            }
            $values = array_merge($values, $_POST);
            $values['is_active'] = !empty($_POST['is_active']);
        }
        // Родителем может быть только категория первого уровня (кроме «Без категории» и самой себя).
        $parents = ['' => '— нет (категория первого уровня) —'];
        foreach (Category::roots() as $r) {
            if ((int) $r['is_system'] !== 1 && (int) $r['id'] !== (int) $id) {
                $parents[$r['id']] = $r['name'];
            }
        }
        $this->render('category-form', [
            'values'      => $values,
            'errors'      => $errors,
            'parents'     => $parents,
            'id'          => $id,
            'imageUrl'    => Upload::url(Category::UPLOAD_DIR, $cat['image'] ?? ''),
            'hasChildren' => $id ? (bool) Category::children($id) : false,
        ], $id ? 'Редактирование категории' : 'Новая категория', 'categories');
    }

    public function delete(): void
    {
        $id = Request::getInt('id');
        $cat = Category::find($id);
        if (!$cat) {
            $this->flash('error', 'Категория не найдена.');
            $this->redirect('/categories');
        }
        if ($this->isValidPost()) {
            $err = Category::delete($id);
            $this->flash($err ? 'error' : 'success', $err ?? 'Категория «' . $cat['name'] . '» удалена.');
            $this->redirect('/categories');
        }
        // Проверяем запреты заранее, чтобы не спрашивать подтверждение зря.
        if ((int) $cat['is_system'] === 1 || Category::children($id) || Category::productCount($id) > 0) {
            $this->flash('error', (int) $cat['is_system'] === 1
                ? 'Категорию «Без категории» удалить нельзя.'
                : 'Нельзя удалить категорию «' . $cat['name'] . '», пока в ней есть подкатегории или товары: сначала удалите их или перенесите (например, в «Без категории»).');
            $this->redirect('/categories');
        }
        $this->confirm('Удаление категории', 'Удалить категорию «' . $cat['name'] . '» (' . $cat['path'] . ') вместе с её свойствами? Адрес категории будет отдавать 404.', '/categories', 'categories');
    }
}
