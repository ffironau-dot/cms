<?php
/** @var array $cat, $inherited, $own @var ?array $parent */
use Admin\Form;
use Core\Models\Property;
$cid = (int) $cat['id'];
?>
<p><a href="<?= CMS_ADMIN_PREFIX ?>/categories">← К списку категорий</a></p>
<p><a class="a-btn" href="<?= CMS_ADMIN_PREFIX ?>/categories/prop-edit?category_id=<?= $cid ?>">+ Добавить свойство</a></p>
<?php if ($parent): ?>
<h2 class="a-h2">Унаследованы от категории «<?= Form::e($parent['name']) ?>»</h2>
<?php if ($inherited): ?>
<ul>
<?php foreach ($inherited as $p): ?>
  <li><?= Form::e($p['name']) ?> <span class="a-muted">(<?= Form::e(Property::TYPES[$p['type']]) ?>)</span></li>
<?php endforeach; ?>
</ul>
<p class="a-hint">Эти свойства редактируются в родительской категории: <a href="<?= CMS_ADMIN_PREFIX ?>/categories/props?category_id=<?= (int) $parent['id'] ?>">перейти</a>.</p>
<?php else: ?>
<p class="a-muted">Нет.</p>
<?php endif; ?>
<?php endif; ?>
<h2 class="a-h2">Свойства категории</h2>
<?php if (!$own): ?>
<p>Свойств пока нет.</p>
<?php else: ?>
<table class="a-table">
  <thead><tr><th>Название</th><th>Тип</th><th>Варианты</th><th>Порядок</th><th>Заполнено у товаров</th><th></th></tr></thead>
  <tbody>
<?php foreach ($own as $p): ?>
    <tr>
      <td><?= Form::e($p['name']) ?></td>
      <td><?= Form::e(Property::TYPES[$p['type']]) ?></td>
      <td><?= Form::e(implode(', ', array_column($p['options'], 'value'))) ?></td>
      <td><?= (int) $p['sort'] ?></td>
      <td><?= (int) $p['count'] ?></td>
      <td class="a-row-actions">
        <a href="<?= CMS_ADMIN_PREFIX ?>/categories/prop-edit?category_id=<?= $cid ?>&amp;id=<?= (int) $p['id'] ?>">изменить</a>
        <a class="a-link-danger" href="<?= CMS_ADMIN_PREFIX ?>/categories/prop-delete?category_id=<?= $cid ?>&amp;id=<?= (int) $p['id'] ?>">удалить</a>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
