<?php
/** @var array $rows */
use Admin\Form;
?>
<p><a class="a-btn" href="<?= CMS_ADMIN_PREFIX ?>/sections/edit">+ Создать раздел</a></p>
<?php if (!$rows): ?>
<p>Разделов пока нет.</p>
<?php else: ?>
<table class="a-table">
  <thead><tr><th>Название</th><th>URL</th><th>Страниц</th><th>Активность</th><th></th></tr></thead>
  <tbody>
<?php foreach ($rows as $s): ?>
    <tr>
      <td><?= Form::e($s['name']) ?></td>
      <td><a href="<?= Form::e($s['path']) ?>" target="_blank" rel="noopener"><?= Form::e($s['path']) ?></a></td>
      <td><a href="<?= CMS_ADMIN_PREFIX ?>/pages?section=<?= (int) $s['id'] ?>"><?= (int) $s['pages_count'] ?></a></td>
      <td><?= Form::yesNo($s['is_active']) ?></td>
      <td class="a-row-actions">
        <a href="<?= CMS_ADMIN_PREFIX ?>/sections/edit?id=<?= (int) $s['id'] ?>">изменить</a>
        <a href="<?= CMS_ADMIN_PREFIX ?>/pages/edit?section=<?= (int) $s['id'] ?>">+ страница</a>
        <?= Form::deleteLink('/sections/delete', (int) $s['id']) ?>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
