<?php
/** @var array $cat, $values, $errors @var ?int $id */
use Admin\Form;
use Core\Models\Property;
?>
<?= Form::errors($errors) ?>
<form method="post" class="a-form">
  <?= Form::token() ?>
  <?= Form::text('name', 'Название свойства', $values['name'], ['required' => true, 'maxlength' => 255]) ?>
<?php if ($id): ?>
  <p class="a-field"><strong>Тип:</strong> <?= Form::e(Property::TYPES[$values['type']]) ?> <span class="a-hint">(тип после создания не меняется)</span></p>
  <input type="hidden" name="type" value="<?= Form::e($values['type']) ?>">
<?php else: ?>
  <?= Form::select('type', 'Тип', $values['type'], Property::TYPES) ?>
<?php endif; ?>
  <div data-show-if="type=list">
    <?= Form::textarea('options', 'Варианты списка (по одному на строку)', $values['options_text'], ['rows' => 5, 'hint' => 'Если удалить вариант, значения товаров с этим вариантом будут удалены.']) ?>
  </div>
  <?= Form::text('sort', 'Порядок', $values['sort'], ['type' => 'number']) ?>
  <div class="a-actions">
    <button type="submit" class="a-btn">Сохранить</button>
    <a href="<?= CMS_ADMIN_PREFIX ?>/categories/props?category_id=<?= (int) $cat['id'] ?>" class="a-btn a-btn--ghost">Отмена</a>
  </div>
</form>
