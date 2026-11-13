<?php
/** @var array $tree */
use Admin\Form;
?>
<p><a class="a-btn" href="<?= CMS_ADMIN_PREFIX ?>/menu/edit">+ Добавить пункт</a></p>
<p class="a-hint">Пункты выводятся по возрастанию поля «порядок» внутри каждого уровня. Неактивные пункты (и подпункты неактивного родителя) на сайте не выводятся.</p>
<?php if (!$tree): ?>
<p>Пунктов меню пока нет.</p>
<?php else: ?>
<table class="a-table">
  <thead><tr><th>Анкор</th><th>Ссылка</th><th>Порядок</th><th>Активность</th><th></th></tr></thead>
  <tbody>
<?php foreach ($tree as $item): ?>
    <tr>
      <td><strong><?= Form::e($item['anchor']) ?></strong></td>
      <td><?= $item['url'] !== '' && $item['url'] !== null ? Form::e($item['url']) : '<span class="a-muted">без ссылки</span>' ?></td>
      <td><?= (int) $item['sort'] ?></td>
      <td><?= Form::yesNo($item['is_active']) ?></td>
      <td class="a-row-actions"><a href="<?= CMS_ADMIN_PREFIX ?>/menu/edit?id=<?= (int) $item['id'] ?>">изменить</a> <?= Form::deleteLink('/menu/delete', (int) $item['id']) ?></td>
    </tr>
<?php foreach ($item['children'] as $child): ?>
    <tr class="a-sub">
      <td>— <?= Form::e($child['anchor']) ?></td>
      <td><?= Form::e($child['url']) ?></td>
      <td><?= (int) $child['sort'] ?></td>
      <td><?= Form::yesNo($child['is_active']) ?></td>
      <td class="a-row-actions"><a href="<?= CMS_ADMIN_PREFIX ?>/menu/edit?id=<?= (int) $child['id'] ?>">изменить</a> <?= Form::deleteLink('/menu/delete', (int) $child['id']) ?></td>
    </tr>
<?php endforeach; ?>
<?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
