<?php
/** @var array $values, $errors @var ?int $id */
use Admin\Form;
use Core\Upload;
use Core\Models\Slide;
?>
<?= Form::errors($errors) ?>
<form method="post" enctype="multipart/form-data" class="a-form">
  <?= Form::token() ?>
  <?= Form::text('eyebrow', 'Надзаголовок', $values['eyebrow'], ['maxlength' => 255]) ?>
  <?= Form::text('title', 'Заголовок', $values['title'], ['required' => true, 'maxlength' => 255]) ?>
  <?= Form::textarea('text', 'Текст', $values['text'], ['rows' => 3, 'hint' => 'HTML-теги не допускаются.']) ?>
  <fieldset class="a-fieldset"><legend>Кнопка 1</legend>
    <?= Form::text('btn1_text', 'Текст кнопки', $values['btn1_text'], ['maxlength' => 255]) ?>
    <?= Form::text('btn1_url', 'Ссылка', $values['btn1_url'], ['maxlength' => 500, 'placeholder' => '#quote', 'hint' => 'Если текст или ссылка не заполнены, кнопка не выводится.']) ?>
  </fieldset>
  <fieldset class="a-fieldset"><legend>Кнопка 2</legend>
    <?= Form::text('btn2_text', 'Текст кнопки', $values['btn2_text'], ['maxlength' => 255]) ?>
    <?= Form::text('btn2_url', 'Ссылка', $values['btn2_url'], ['maxlength' => 500]) ?>
  </fieldset>
  <?= Form::image('image', 'Фото', Upload::url(Slide::UPLOAD_DIR, $id ? (string) Slide::find($id)['image'] : ''), ['accept' => 'image/jpeg,image/png,image/webp', 'hint' => 'JPG, PNG или WebP. Автоматического ресайза нет — подготовьте изображение нужного размера. При замене старый файл удаляется.']) ?>
  <?= Form::text('image_alt', 'Alt фото', $values['image_alt'], ['maxlength' => 255]) ?>
  <?= Form::text('caption', 'Подпись на фото', $values['caption'], ['maxlength' => 255]) ?>
  <?= Form::text('sort', 'Порядок', $values['sort'], ['type' => 'number']) ?>
  <?= Form::checkbox('is_active', 'Активен', (bool) $values['is_active']) ?>
  <div class="a-actions">
    <button type="submit" class="a-btn">Сохранить</button>
    <a href="<?= CMS_ADMIN_PREFIX ?>/slides" class="a-btn a-btn--ghost">Отмена</a>
  </div>
</form>
