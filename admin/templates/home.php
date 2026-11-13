<?php
/** @var array $errors, $s @var string $ogUrl */
use Admin\Form;
?>
<?= Form::errors($errors) ?>
<form method="post" enctype="multipart/form-data" class="a-form">
  <?= Form::token() ?>
  <?= Form::text('home_title', 'Title', $s['home_title'], ['maxlength' => 255, 'hint' => 'Выводится в &lt;title&gt; главной страницы.']) ?>
  <?= Form::textarea('home_description', 'Description', $s['home_description'], ['rows' => 3, 'hint' => 'Выводится в &lt;meta name="description"&gt;.']) ?>
  <?= Form::text('home_og_title', 'OG:Title', $s['home_og_title'], ['maxlength' => 255, 'hint' => 'Если пусто — используется Title.']) ?>
  <?= Form::textarea('home_og_description', 'OG:Description', $s['home_og_description'], ['rows' => 3, 'hint' => 'Если пусто — используется Description.']) ?>
  <?= Form::image('home_og_image', 'OG:Image', $ogUrl, ['removable' => true, 'hint' => 'Картинка для превью ссылки в соцсетях и мессенджерах (рекомендуется 1200×630).']) ?>
  <div class="a-actions"><button type="submit" class="a-btn">Сохранить</button></div>
</form>
