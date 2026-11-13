<?php
/** @var array $values, $errors, $parents @var ?int $id */
use Admin\Form;
?>
<?= Form::errors($errors) ?>
<form method="post" class="a-form">
  <?= Form::token() ?>
  <?= Form::text('anchor', 'Анкор (текст пункта)', $values['anchor'], ['required' => true, 'maxlength' => 255]) ?>
  <?= Form::text('url', 'Ссылка', $values['url'], ['maxlength' => 500, 'placeholder' => '/neonovye-vyveski/', 'hint' => 'Абсолютная (https://...) или относительная (/o-kompanii/). Пункт первого уровня, раскрывающий подпункты, может быть без ссылки.']) ?>
  <?= Form::text('link_title', 'Title ссылки', $values['link_title'], ['maxlength' => 255]) ?>
  <?= Form::select('level', 'Уровень вложенности', (string) $values['level'], ['1' => '1 — пункт верхнего уровня', '2' => '2 — подпункт'], ['hint' => 'Максимум два уровня.']) ?>
  <div data-show-if="level=2">
    <?= Form::select('parent_id', 'Родительский пункт', (string) $values['parent_id'], ['' => '— выберите —'] + $parents) ?>
  </div>
  <?= Form::text('sort', 'Порядок', $values['sort'], ['type' => 'number', 'hint' => 'Пункты выводятся по возрастанию.']) ?>
  <?= Form::checkbox('is_active', 'Активен', (bool) $values['is_active']) ?>
  <div class="a-actions">
    <button type="submit" class="a-btn">Сохранить</button>
    <a href="<?= CMS_ADMIN_PREFIX ?>/menu" class="a-btn a-btn--ghost">Отмена</a>
  </div>
</form>
