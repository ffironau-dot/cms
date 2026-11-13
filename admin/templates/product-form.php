<?php
/**
 * @var array $values, $errors, $categories, $props, $propValues, $images
 * @var ?int $id @var ?array $product @var string $imageUrl
 */
use Admin\Form;
use Core\Format;
use Core\Upload;
use Core\Models\Product;
?>
<?= Form::errors($errors) ?>
<?php if ($product): ?>
<p><a href="<?= Form::e($product['path']) ?>" target="_blank" rel="noopener">Открыть на сайте: <?= Form::e($product['path']) ?></a></p>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="a-form">
  <?= Form::token() ?>
  <?= Form::select('category_id', 'Категория', (string) $values['category_id'], $categories, ['hint' => 'При переносе в другую категорию значения свойств прежней категории удаляются.']) ?>
  <?= Form::text('sku', 'Артикул', $values['sku'], ['required' => true, 'maxlength' => 100, 'hint' => 'Уникален среди товаров.']) ?>
  <?= Form::text('name', 'Название', $values['name'], ['required' => true, 'maxlength' => 255]) ?>
  <?= Form::text('slug', 'URL', $values['slug'], ['maxlength' => 200, 'placeholder' => 'akrilovye-bukvy', 'prefix' => '/', 'hint' => 'Адрес товара: /сегмент/ — не зависит от категории. Если пусто — создаётся из названия. Смена адреса создаёт редирект 301.']) ?>
  <?= Form::text('meta_title', 'Title', $values['meta_title'], ['maxlength' => 255, 'hint' => 'Если пусто — используется название.']) ?>
  <?= Form::textarea('meta_description', 'Description', $values['meta_description'], ['rows' => 2]) ?>
  <?= Form::text('og_title', 'OG:Title', $values['og_title'], ['maxlength' => 255]) ?>
  <?= Form::textarea('og_description', 'OG:Description', $values['og_description'], ['rows' => 2]) ?>
  <?= Form::image('image', 'Основное изображение', $imageUrl, ['removable' => true, 'hint' => 'JPG, PNG, GIF или WebP. При замене старый файл удаляется.']) ?>
  <?= Form::textarea('text_small', 'Малый текст', $values['text_small'], ['rows' => 3, 'hint' => 'Допустимые теги: &lt;u&gt;, &lt;i&gt;, &lt;b&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;.']) ?>
  <?= Form::textarea('text_big', 'Большой текст (HTML)', $values['text_big'], ['rows' => 12, 'code' => true]) ?>
  <div class="a-grid2">
    <?= Form::text('price_old', 'Старая цена, ₽', is_string($values['price_old'] ?? null) && str_contains((string) $values['price_old'], ',') ? $values['price_old'] : Format::priceInput($values['price_old']), ['placeholder' => '1500,00', 'hint' => 'Необязательна.']) ?>
    <?= Form::text('price', 'Новая цена, ₽', is_string($values['price'] ?? null) && str_contains((string) $values['price'], ',') ? $values['price'] : Format::priceInput($values['price']), ['placeholder' => '1250,50', 'hint' => 'С копейками через запятую; на сайте округляется до рубля.']) ?>
  </div>
  <?= Form::checkbox('is_active', 'Активен', (bool) $values['is_active']) ?>

<?php if ($id): ?>
  <fieldset class="a-fieldset"><legend>Свойства категории</legend>
<?php if (!$props): ?>
    <p class="a-muted">У категории товара нет свойств.</p>
<?php endif; ?>
<?php foreach ($props as $p): $pid = (int) $p['id']; $v = (string) ($propValues[$pid] ?? ''); $fname = 'props[' . $pid . ']'; ?>
<?php if ($p['type'] === 'list'): ?>
    <?= Form::select($fname, $p['name'], $v, ['' => '— не указано —'] + array_column($p['options'], 'value', 'id')) ?>
<?php elseif ($p['type'] === 'bool'): ?>
    <?= Form::select($fname, $p['name'], $v, ['' => '— не указано —', '1' => 'Да', '0' => 'Нет']) ?>
<?php elseif ($p['type'] === 'number'): ?>
    <?= Form::text($fname, $p['name'], str_replace('.', ',', $v), ['inputmode' => 'decimal']) ?>
<?php else: ?>
    <?= Form::text($fname, $p['name'], $v, ['maxlength' => 1000]) ?>
<?php endif; ?>
<?php endforeach; ?>
  </fieldset>
<?php else: ?>
  <p class="a-hint">Свойства категории и дополнительные фото можно заполнить после первого сохранения товара.</p>
<?php endif; ?>
  <div class="a-actions">
    <button type="submit" class="a-btn">Сохранить</button>
    <a href="<?= CMS_ADMIN_PREFIX ?>/products" class="a-btn a-btn--ghost">К списку</a>
  </div>
</form>

<?php if ($id): ?>
<h2 class="a-h2" id="photos">Дополнительные фото</h2>
<?php if ($images): ?>
<form method="post" action="<?= CMS_ADMIN_PREFIX ?>/products/images-save?id=<?= (int) $id ?>" class="a-form">
  <?= Form::token() ?>
  <table class="a-table">
    <thead><tr><th>Фото</th><th>Alt</th><th>Порядок</th><th></th></tr></thead>
    <tbody>
<?php foreach ($images as $img): $iid = (int) $img['id']; ?>
      <tr>
        <td class="a-thumb"><img src="<?= Form::e(Upload::url(Product::UPLOAD_DIR, $img['file'])) ?>" alt=""></td>
        <td><input type="text" name="images[<?= $iid ?>][alt]" value="<?= Form::e($img['alt']) ?>" maxlength="255" aria-label="Alt"></td>
        <td><input type="number" name="images[<?= $iid ?>][sort]" value="<?= (int) $img['sort'] ?>" aria-label="Порядок" class="a-short"></td>
        <td><a class="a-link-danger" href="<?= CMS_ADMIN_PREFIX ?>/products/image-delete?id=<?= $iid ?>">удалить</a></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
  <div class="a-actions"><button type="submit" class="a-btn a-btn--ghost">Сохранить alt и порядок</button></div>
</form>
<?php else: ?>
<p class="a-muted">Дополнительных фото нет.</p>
<?php endif; ?>
<form method="post" action="<?= CMS_ADMIN_PREFIX ?>/products/image-add?id=<?= (int) $id ?>" enctype="multipart/form-data" class="a-form a-form--inline a-panel">
  <?= Form::token() ?>
  <div class="a-field"><label for="f-photo">Добавить фото</label><input id="f-photo" type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp" required></div>
  <?= Form::text('alt', 'Alt', '', ['maxlength' => 255]) ?>
  <?= Form::text('sort', 'Порядок', (string) (count($images) + 1), ['type' => 'number']) ?>
  <button type="submit" class="a-btn">Загрузить</button>
</form>
<?php endif; ?>
