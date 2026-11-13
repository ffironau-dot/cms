<?php
/** @var array $errors @var string $url, $alt */
use Admin\Form;
?>
<?= Form::errors($errors) ?>
<form method="post" enctype="multipart/form-data" class="a-form">
  <?= Form::token() ?>
  <?= Form::image('logo', 'Файл логотипа', $url, ['removable' => true, 'hint' => 'PNG, JPG, GIF или WebP. Размер не ограничивается — подготовьте изображение заранее. Старый файл при замене удаляется с сервера. Пока логотип не загружен, на сайте выводится текст «Лого».']) ?>
  <?= Form::text('logo_alt', 'Alt логотипа', $alt, ['maxlength' => 255, 'hint' => 'Альтернативный текст картинки, например название компании.']) ?>
  <div class="a-actions"><button type="submit" class="a-btn">Сохранить</button></div>
</form>
