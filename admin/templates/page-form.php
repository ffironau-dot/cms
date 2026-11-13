<?php
/** @var array $values, $errors, $sections, $sectionPaths @var ?int $id @var string $imageUrl */
use Admin\Form;
?>
<?= Form::errors($errors) ?>
<?php if ($id): ?>
<p><a href="<?= Form::e($values['path']) ?>" target="_blank" rel="noopener">Открыть на сайте: <?= Form::e($values['path']) ?></a></p>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="a-form">
  <?= Form::token() ?>
  <?= Form::select('section_id', 'Раздел', (string) $values['section_id'], $sections, ['hint' => 'Страница без раздела существует отдельно и может иметь произвольный адрес.']) ?>
  <div data-show-if="section_id=">
    <?= Form::text('slug_path', 'Адрес страницы (целиком)', $values['section_id'] ? '' : $values['slug'], ['maxlength' => 480, 'placeholder' => '/o-kompanii/komanda/', 'hint' => 'Произвольный путь: сегменты из строчных латинских букв, цифр и дефиса через «/».']) ?>
  </div>
  <div data-show-if="section_id!=">
    <?= Form::text('slug_segment', 'URL (собственный сегмент)', $values['section_id'] ? $values['slug'] : '', ['maxlength' => 200, 'placeholder' => 'nazvanie-stati', 'prefix' => '/раздел/', 'hint' => 'Полный адрес: адрес раздела + сегмент. Смена адреса создаёт редирект 301.']) ?>
    <?= Form::text('published_at', 'Дата публикации', (string) $values['published_at'], ['type' => 'date', 'hint' => 'Необязательно. Если не заполнена — на сайте не выводится, а в ленте страница сортируется по дате создания.']) ?>
  </div>
  <?= Form::text('name', 'Название', $values['name'], ['required' => true, 'maxlength' => 255]) ?>
  <?= Form::text('meta_title', 'Title', $values['meta_title'], ['maxlength' => 255, 'hint' => 'Если пусто — используется название.']) ?>
  <?= Form::textarea('meta_description', 'Description', $values['meta_description'], ['rows' => 2]) ?>
  <?= Form::text('og_title', 'OG:Title', $values['og_title'], ['maxlength' => 255]) ?>
  <?= Form::textarea('og_description', 'OG:Description', $values['og_description'], ['rows' => 2]) ?>
  <?= Form::image('image', 'Основная картинка', $imageUrl, ['removable' => true]) ?>
  <?= Form::textarea('text_small', 'Короткий текст (анонс)', $values['text_small'], ['rows' => 3, 'hint' => 'Допустимые теги: &lt;u&gt;, &lt;i&gt;, &lt;b&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;. Выводится под заголовком и в ленте раздела.']) ?>
  <?= Form::textarea('text_big', 'Длинный текст (HTML)', $values['text_big'], ['rows' => 18, 'code' => true, 'hint' => 'Любой HTML-код. Заголовки &lt;h2&gt; автоматически попадают в блок «Содержание». Картинки — через раздел «Файлы».']) ?>
  <?= Form::checkbox('is_active', 'Активна', (bool) $values['is_active']) ?>
  <div class="a-actions">
    <button type="submit" class="a-btn">Сохранить</button>
    <a href="<?= CMS_ADMIN_PREFIX ?>/pages" class="a-btn a-btn--ghost">Отмена</a>
  </div>
</form>
