<?php
/** @var array $values, $errors, $parents @var ?int $id @var string $imageUrl @var bool $hasChildren */
use Admin\Form;
$isSystem = (int) ($values['is_system'] ?? 0) === 1;
?>
<?= Form::errors($errors) ?>
<?php if ($id): ?>
<p><a href="<?= Form::e($values['path']) ?>" target="_blank" rel="noopener">Открыть на сайте: <?= Form::e($values['path']) ?></a> ·
   <a href="<?= CMS_ADMIN_PREFIX ?>/categories/props?category_id=<?= (int) $id ?>">Свойства товаров категории</a></p>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="a-form">
  <?= Form::token() ?>
<?php if ($isSystem): ?>
  <p class="a-hint">Системная категория: название изменить нельзя, адрес и активность — можно. Удалить её нельзя.</p>
<?php elseif ($hasChildren): ?>
  <p class="a-hint">У категории есть подкатегории, поэтому она остаётся категорией первого уровня.</p>
<?php else: ?>
  <?= Form::select('parent_id', 'Родительская категория', (string) $values['parent_id'], $parents, ['hint' => 'Вложенность — не более двух уровней.']) ?>
<?php endif; ?>
  <?= Form::text('name', 'Название', $values['name'], ['required' => true, 'maxlength' => 255, 'readonly' => $isSystem]) ?>
  <?= Form::text('slug', 'URL (собственный сегмент)', $values['slug'], ['required' => true, 'maxlength' => 200, 'placeholder' => 'neonovye-vyveski', 'hint' => 'Только строчные латинские буквы, цифры и дефис. Полный адрес собирается автоматически: /сегмент-родителя/сегмент/. При смене адреса со старого создаётся редирект 301.', 'prefix' => '/…/']) ?>
  <?= Form::text('meta_title', 'Title', $values['meta_title'], ['maxlength' => 255, 'hint' => 'Если пусто — используется название.']) ?>
  <?= Form::textarea('meta_description', 'Description', $values['meta_description'], ['rows' => 2]) ?>
  <?= Form::text('og_title', 'OG:Title', $values['og_title'], ['maxlength' => 255]) ?>
  <?= Form::textarea('og_description', 'OG:Description', $values['og_description'], ['rows' => 2]) ?>
  <?= Form::image('image', 'Основное изображение (оно же OG:Image)', $imageUrl, ['removable' => true, 'hint' => 'JPG, PNG, GIF или WebP.']) ?>
  <?= Form::textarea('text_small', 'Малый текст', $values['text_small'], ['rows' => 3, 'hint' => 'Допустимые теги: &lt;u&gt;, &lt;i&gt;, &lt;b&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;. Выводится под заголовком.']) ?>
  <?= Form::textarea('text_big', 'Большой текст (HTML)', $values['text_big'], ['rows' => 14, 'code' => true, 'hint' => 'Любой HTML-код. Картинки загружайте в разделе «Файлы» и вставляйте их адрес.']) ?>
  <?= Form::checkbox('is_active', 'Активна', (bool) $values['is_active'], ['hint' => 'Неактивная категория скрывает с сайта свои подкатегории и товары.']) ?>
  <div class="a-actions">
    <button type="submit" class="a-btn">Сохранить</button>
    <a href="<?= CMS_ADMIN_PREFIX ?>/categories" class="a-btn a-btn--ghost">Отмена</a>
  </div>
</form>
