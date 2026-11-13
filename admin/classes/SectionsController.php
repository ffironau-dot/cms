<?php
/**
 * Разделы текстовых страниц (п. 6): список, создание, редактирование, удаление.
 */

declare(strict_types=1);

namespace Admin;

use Core\Models\Section;
use Core\Request;

final class SectionsController extends Controller
{
    public function index(): void
    {
        $this->render('sections-list', ['rows' => Section::all()], 'Разделы текстовых страниц', 'sections');
    }

    public function edit(): void
    {
        $id = Request::getInt('id') ?: null;
        $section = $id ? Section::find($id) : null;
        if ($id && !$section) {
            $this->flash('error', 'Раздел не найден.');
            $this->redirect('/sections');
        }
        $errors = [];
        $values = $section ?? [
            'slug' => '', 'name' => '', 'meta_title' => '', 'meta_description' => '',
            'og_title' => '', 'og_description' => '', 'text_big' => '', 'is_active' => 1, 'path' => '',
        ];
        if ($this->isValidPost()) {
            $res = Section::save($_POST, $id);
            if (!$res['errors']) {
                $this->flash('success', 'Раздел сохранён.');
                $this->redirect('/sections');
            }
            $errors = $res['errors'];
            $values = array_merge($values, $_POST);
            $values['is_active'] = !empty($_POST['is_active']);
        }
        $this->render('section-form', ['values' => $values, 'errors' => $errors, 'id' => $id],
            $id ? 'Редактирование раздела' : 'Новый раздел', 'sections');
    }

    public function delete(): void
    {
        $id = Request::getInt('id');
        $section = Section::find($id);
        if (!$section) {
            $this->flash('error', 'Раздел не найден.');
            $this->redirect('/sections');
        }
        if ($this->isValidPost()) {
            $err = Section::delete($id);
            $this->flash($err ? 'error' : 'success', $err ?? 'Раздел «' . $section['name'] . '» удалён.');
            $this->redirect('/sections');
        }
        $count = (int) \Core\Db::value('SELECT COUNT(*) FROM pages WHERE section_id = ?', [$id]);
        if ($count > 0) {
            $this->flash('error', 'Нельзя удалить раздел «' . $section['name'] . '», пока в нём есть страницы (' . $count . ').');
            $this->redirect('/sections');
        }
        $this->confirm('Удаление раздела', 'Удалить раздел «' . $section['name'] . '» (' . $section['path'] . ')?', '/sections', 'sections');
    }
}
