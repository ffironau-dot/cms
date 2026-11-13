<?php
/**
 * Текстовые страницы (п. 6): список, создание, редактирование, удаление.
 */

declare(strict_types=1);

namespace Admin;

use Core\Models\Page;
use Core\Models\Section;
use Core\Request;
use Core\Upload;

final class PagesController extends Controller
{
    public function index(): void
    {
        $filter = array_key_exists('section', $_GET) && $_GET['section'] !== '' ? Request::getInt('section') : -1;
        $this->render('pages-list', [
            'rows'     => Page::adminList($filter),
            'sections' => Section::all(),
            'filter'   => $filter,
        ], 'Текстовые страницы', 'pages');
    }

    public function edit(): void
    {
        $id = Request::getInt('id') ?: null;
        $page = $id ? Page::find($id) : null;
        if ($id && !$page) {
            $this->flash('error', 'Страница не найдена.');
            $this->redirect('/pages');
        }
        $errors = [];
        $values = $page ?? [
            'section_id' => Request::getInt('section') ?: null, 'slug' => '', 'name' => '', 'meta_title' => '', 'meta_description' => '',
            'og_title' => '', 'og_description' => '', 'image' => '', 'text_small' => '', 'text_big' => '',
            'published_at' => null, 'is_active' => 1, 'path' => '',
        ];
        if ($page && $page['section_id'] === null) {
            $values['slug'] = '/' . $page['slug'] . '/';
        }
        if ($this->isValidPost()) {
            $in = $_POST;
            // В форме два поля адреса: путь целиком (без раздела) и сегмент (в разделе).
            $in['slug'] = !empty($in['section_id']) ? (string) ($in['slug_segment'] ?? '') : (string) ($in['slug_path'] ?? '');
            $newFile = null;
            try {
                $newFile = $this->processImage('image', Page::UPLOAD_DIR, Upload::ALL_IMAGES, $in);
                $res = Page::save($in, $id);
                if (!$res['errors']) {
                    $this->cleanupOldImage(Page::UPLOAD_DIR, $page, $in);
                    $this->flash('success', 'Страница сохранена.');
                    $this->redirect('/pages');
                }
                $errors = $res['errors'];
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
            if ($newFile) {
                Upload::delete(Page::UPLOAD_DIR, $newFile);
            }
            $values = array_merge($values, $_POST);
            $values['slug'] = $in['slug'];
            $values['is_active'] = !empty($_POST['is_active']);
        }
        $sections = ['' => '— без раздела (отдельная страница) —'];
        foreach (Section::all() as $s) {
            $sections[$s['id']] = $s['name'] . ' (' . $s['path'] . ')';
        }
        $this->render('page-form', [
            'values'   => $values,
            'errors'   => $errors,
            'id'       => $id,
            'sections' => $sections,
            'sectionPaths' => array_column(Section::all(), 'path', 'id'),
            'imageUrl' => Upload::url(Page::UPLOAD_DIR, $page['image'] ?? ''),
        ], $id ? 'Редактирование страницы' : 'Новая страница', 'pages');
    }

    public function delete(): void
    {
        $id = Request::getInt('id');
        $page = Page::find($id);
        if (!$page) {
            $this->flash('error', 'Страница не найдена.');
            $this->redirect('/pages');
        }
        if ($this->isValidPost()) {
            Page::delete($id);
            $this->flash('success', 'Страница «' . $page['name'] . '» удалена.');
            $this->redirect('/pages');
        }
        $this->confirm('Удаление страницы', 'Удалить страницу «' . $page['name'] . '» (' . $page['path'] . ')? Адрес будет отдавать 404.', '/pages', 'pages');
    }
}
