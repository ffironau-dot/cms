<?php
/**
 * Страница настройки из одного поля (адрес, телефон).
 * @var string $name, $label, $value, $hint, $action
 */
use Admin\Form;
?>
<form method="post" action="<?= CMS_ADMIN_PREFIX . Form::e($action) ?>" class="a-form">
  <?= Form::token() ?>
  <?= Form::text($name, $label, $value, ['hint' => Form::e($hint), 'maxlength' => 255]) ?>
  <div class="a-actions"><button type="submit" class="a-btn">Сохранить</button></div>
</form>
