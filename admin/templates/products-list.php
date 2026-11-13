<?php
/** @var array $rows, $categories @var \Core\Paginator $pager @var string $q @var int $categoryId */
use Admin\Form;
use Core\Format;
use Core\Upload;
use Core\Models\Product;
?>
<p>
  <a class="a-btn" href="<?= CMS_ADMIN_PREFIX ?>/products/edit">+ Добавить товар</a>
  <a class="a-btn a-btn--ghost" href="<?= CMS_ADMIN_PREFIX ?>/products/import">Импорт из CSV</a>
</p>
<form method="get" class="a-form a-form--inline a-panel">
  <?= Form::text('q', 'Поиск по артикулу или названию', $q) ?>
  <div class="a-field"><label for="f-category_id">Категория</label>
    <select id="f-category_id" name="category_id">
      <option value="">— все —</option>
<?php foreach ($categories as [$c, $level]): ?>
      <option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === $categoryId ? ' selected' : '' ?>><?= $level === 2 ? '— ' : '' ?><?= Form::e($c['name']) ?></option>
<?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="a-btn">Найти</button>
<?php if ($q !== '' || $categoryId): ?>
  <a href="<?= CMS_ADMIN_PREFIX ?>/products">сбросить</a>
<?php endif; ?>
</form>
<p class="a-muted">Найдено: <?= (int) $pager->total ?>. Фильтр по родительской категории показывает и товары её подкатегорий.</p>
<?php if ($rows): ?>
<table class="a-table">
  <thead><tr><th>Фото</th><th>Артикул</th><th>Название</th><th>Категория</th><th>Цена, ₽</th><th>Активность</th><th></th></tr></thead>
  <tbody>
<?php foreach ($rows as $p): ?>
    <tr>
      <td class="a-thumb"><?php if ($p['image']): ?><img src="<?= Form::e(Upload::url(Product::UPLOAD_DIR, $p['image'])) ?>" alt="" loading="lazy"><?php endif; ?></td>
      <td><?= Form::e($p['sku']) ?></td>
      <td><a href="<?= CMS_ADMIN_PREFIX ?>/products/edit?id=<?= (int) $p['id'] ?>"><?= Form::e($p['name']) ?></a></td>
      <td><?= Form::e($p['category_name']) ?></td>
      <td><?= Form::e(Format::priceInput($p['price'])) ?></td>
      <td><?= Form::yesNo($p['is_active']) ?></td>
      <td class="a-row-actions">
        <a href="<?= CMS_ADMIN_PREFIX ?>/products/edit?id=<?= (int) $p['id'] ?>">изменить</a>
        <a href="<?= Form::e($p['path']) ?>" target="_blank" rel="noopener">на сайте</a>
        <?= Form::deleteLink('/products/delete', (int) $p['id']) ?>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
<?php $base = CMS_ADMIN_PREFIX . '/products'; $query = array_filter(['q' => $q, 'category_id' => $categoryId ?: null]); include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
