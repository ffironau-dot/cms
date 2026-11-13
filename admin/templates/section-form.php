<?php
/** @var array $values, $errors @var ?int $id */
use Admin\Form;
?>
<?= Form::errors($errors) ?>
<form method="post" class="a-form">
  <?= Form::token() ?>
  <?= Form::text('name', 'Название', $values['name'], ['required' => true, 'maxlength' => 255]) ?>
  <?= Form::text('slug', 'URL', $values['slug'], ['required' => true, 'maxlength' => 200, 'placeholder' => 'blog', 'prefix' => '/', 'hint' => 'Один сегмент. При смене адреса меняются и адреса страниц раздела (с редиректами 301).']) ?>
  <?= Form::text('meta_title', 'Title', $values['meta_title'], ['maxlength' => 255]) ?>
  <?= Form::textarea('meta_description', 'Description', $values['meta_description'], ['rows' => 2]) ?>
  <?= Form::text('og_title', 'OG:Title', $values['og_title'], ['maxlength' => 255]) ?>
  <?= Form::textarea('og_description', 'OG:Description', $values['og_description'], ['rows' => 2]) ?>
  <?= Form::textarea('text_big', 'Текст (HTML)', $values['text_big'], ['rows' => 12, 'code' => true]) ?>
  <?= Form::checkbox('is_active', 'Активен', (bool) $values['is_active'], ['hint' => 'Неактивный раздел скрывает все свои страницы.']) ?>
  <div class="a-actions">
    <button type="submit" class="a-btn">Сохранить</button>
    <a href="<?= CMS_ADMIN_PREFIX ?>/sections" class="a-btn a-btn--ghost">Отмена</a>
  </div>
</form>
