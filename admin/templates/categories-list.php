<?php
/** @var array $rows */
use Admin\Form;
?>
<p><a class="a-btn" href="<?= CMS_ADMIN_PREFIX ?>/categories/edit">+ Создать категорию</a></p>
<table class="a-table">
  <thead><tr><th>Название</th><th>URL</th><th>Товаров</th><th>Активность</th><th></th></tr></thead>
  <tbody>
<?php foreach ($rows as $c): ?>
    <tr class="<?= $c['level'] === 2 ? 'a-sub' : '' ?>">
      <td><?= $c['level'] === 2 ? '— ' : '' ?><?= Form::e($c['name']) ?><?= (int) $c['is_system'] === 1 ? ' <span class="a-muted">(системная)</span>' : '' ?></td>
      <td><a href="<?= Form::e($c['path']) ?>" target="_blank" rel="noopener"><?= Form::e($c['path']) ?></a></td>
      <td title="С учётом подкатегорий"><?= (int) $c['count'] ?></td>
      <td><?= Form::yesNo($c['is_active']) ?></td>
      <td class="a-row-actions">
        <a href="<?= CMS_ADMIN_PREFIX ?>/categories/edit?id=<?= (int) $c['id'] ?>">изменить</a>
        <a href="<?= CMS_ADMIN_PREFIX ?>/categories/props?category_id=<?= (int) $c['id'] ?>">свойства товаров</a>
        <a href="<?= CMS_ADMIN_PREFIX ?>/products?category_id=<?= (int) $c['id'] ?>">товары</a>
<?php if ((int) $c['is_system'] !== 1): ?>
        <?= Form::deleteLink('/categories/delete', (int) $c['id']) ?>
<?php endif; ?>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
