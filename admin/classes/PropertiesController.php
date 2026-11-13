<?php
/**
 * Свойства товаров категории (п. 5.1.4): добавление, изменение, удаление.
 */

declare(strict_types=1);

namespace Admin;

use Core\Models\Category;
use Core\Models\Property;
use Core\Request;

final class PropertiesController extends Controller
{
    private function category(): array
    {
        $cat = Category::find(Request::getInt('category_id') ?: Request::getInt('cid'));
        if (!$cat) {
            $this->flash('error', 'Категория не найдена.');
            $this->redirect('/categories');
        }
        return $cat;
    }

    public function index(): void
    {
        $cat = $this->category();
        $inherited = $cat['parent_id'] !== null ? Property::ofCategory((int) $cat['parent_id']) : [];
        $own = Property::ofCategory((int) $cat['id']);
        foreach ($own as &$p) {
            $p['count'] = Property::valuesCount((int) $p['id']);
        }
        $this->render('props-list', [
            'cat'       => $cat,
            'parent'    => $cat['parent_id'] !== null ? Category::find((int) $cat['parent_id']) : null,
            'inherited' => $inherited,
            'own'       => $own,
        ], 'Свойства товаров: ' . $cat['name'], 'categories');
    }

    public function edit(): void
    {
        $cat = $this->category();
        $id = Request::getInt('id') ?: null;
        $prop = $id ? Property::find($id) : null;
        if ($id && (!$prop || (int) $prop['category_id'] !== (int) $cat['id'])) {
            $this->flash('error', 'Свойство не найдено.');
            $this->redirect('/categories/props?category_id=' . $cat['id']);
        }
        $errors = [];
        $values = $prop ?? ['name' => '', 'type' => 'string', 'sort' => 0, 'options' => []];
        $values['options_text'] = implode("\n", array_column($values['options'], 'value'));
        if ($this->isValidPost()) {
            $res = Property::save((int) $cat['id'], $_POST, $id);
            if (!$res['errors']) {
                $this->flash('success', 'Свойство сохранено.');
                $this->redirect('/categories/props?category_id=' . $cat['id']);
            }
            $errors = $res['errors'];
            $values = array_merge($values, $_POST);
            $values['options_text'] = (string) ($_POST['options'] ?? '');
        }
        $this->render('prop-form', ['cat' => $cat, 'values' => $values, 'errors' => $errors, 'id' => $id],
            ($id ? 'Редактирование свойства' : 'Новое свойство') . ': ' . $cat['name'], 'categories');
    }

    public function delete(): void
    {
        $cat = $this->category();
        $prop = Property::find(Request::getInt('id'));
        $back = '/categories/props?category_id=' . $cat['id'];
        if (!$prop || (int) $prop['category_id'] !== (int) $cat['id']) {
            $this->flash('error', 'Свойство не найдено.');
            $this->redirect($back);
        }
        if ($this->isValidPost()) {
            Property::delete((int) $prop['id']);
            $this->flash('success', 'Свойство «' . $prop['name'] . '» удалено вместе со значениями у товаров.');
            $this->redirect($back);
        }
        // Предупреждение с количеством товаров, у которых будут удалены значения (п. 5.1).
        $count = Property::valuesCount((int) $prop['id']);
        $this->confirm(
            'Удаление свойства',
            'Удалить свойство «' . $prop['name'] . '»? Значения этого свойства будут удалены у товаров: ' . $count . ' шт.',
            $back,
            'categories'
        );
    }
}
