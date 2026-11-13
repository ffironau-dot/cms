<?php
/**
 * Верхнее меню (п. 4.4): список, создание/редактирование, удаление.
 */

declare(strict_types=1);

namespace Admin;

use Core\Models\MenuItem;
use Core\Request;

final class MenuController extends Controller
{
    public function index(): void
    {
        $this->render('menu-list', ['tree' => MenuItem::tree()], 'Верхнее меню', 'menu');
    }

    public function edit(): void
    {
        $id = Request::getInt('id') ?: null;
        $item = $id ? MenuItem::find($id) : null;
        if ($id && !$item) {
            $this->flash('error', 'Пункт меню не найден.');
            $this->redirect('/menu');
        }
        $errors = [];
        $values = $item ?? ['url' => '', 'anchor' => '', 'link_title' => '', 'sort' => 0, 'is_active' => 1, 'parent_id' => null];
        $values['level'] = $values['parent_id'] !== null ? 2 : 1;
        if ($this->isValidPost()) {
            $res = MenuItem::save($_POST, $id);
            if (!$res['errors']) {
                $this->flash('success', 'Пункт меню сохранён.');
                $this->redirect('/menu');
            }
            $errors = $res['errors'];
            $values = array_merge($values, $_POST);
            $values['is_active'] = !empty($_POST['is_active']);
        }
        $parents = [];
        foreach (MenuItem::roots() as $r) {
            if ((int) $r['id'] !== (int) $id) {
                $parents[$r['id']] = $r['anchor'];
            }
        }
        $this->render('menu-form', ['values' => $values, 'errors' => $errors, 'parents' => $parents, 'id' => $id],
            $id ? 'Редактирование пункта меню' : 'Новый пункт меню', 'menu');
    }

    public function delete(): void
    {
        $id = Request::getInt('id');
        $item = MenuItem::find($id);
        if (!$item) {
            $this->flash('error', 'Пункт меню не найден.');
            $this->redirect('/menu');
        }
        if ($this->isValidPost()) {
            $err = MenuItem::delete($id);
            $this->flash($err ? 'error' : 'success', $err ?? 'Пункт меню «' . $item['anchor'] . '» удалён.');
            $this->redirect('/menu');
        }
        if (MenuItem::hasChildren($id)) {
            $this->flash('error', 'Нельзя удалить пункт «' . $item['anchor'] . '», у которого есть подпункты: сначала удалите все его подпункты.');
            $this->redirect('/menu');
        }
        $this->confirm('Удаление пункта меню', 'Удалить пункт меню «' . $item['anchor'] . '»?', '/menu', 'menu');
    }
}
