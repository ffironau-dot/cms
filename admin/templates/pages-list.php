<?php
/** @var array $rows, $sections @var int $filter */
use Admin\Form;
?>
<p><a class="a-btn" href="<?= CMS_ADMIN_PREFIX ?>/pages/edit">+ Создать страницу</a> <a class="a-btn a-btn--ghost" href="<?= CMS_ADMIN_PREFIX ?>/sections">Разделы</a></p>
<form method="get" class="a-form a-form--inline a-panel">
  <div class="a-field"><label for="f-section">Раздел</label>
    <select id="f-section" name="section" onchange="this.form.submit()">
      <option value="">— все страницы —</option>
      <option value="0"<?= $filter === 0 ? ' selected' : '' ?>>без раздела</option>
<?php foreach ($sections as $s): ?>
      <option value="<?= (int) $s['id'] ?>"<?= $filter === (int) $s['id'] ? ' selected' : '' ?>><?= Form::e($s['name']) ?></option>
<?php endforeach; ?>
    </select>
  </div>
</form>
<?php if (!$rows): ?>
<p>Страниц пока нет.</p>
<?php else: ?>
<table class="a-table">
  <thead><tr><th>Название</th><th>Раздел</th><th>URL</th><th>Активность</th><th></th></tr></thead>
  <tbody>
<?php foreach ($rows as $p): ?>
    <tr>
      <td><a href="<?= CMS_ADMIN_PREFIX ?>/pages/edit?id=<?= (int) $p['id'] ?>"><?= Form::e($p['name']) ?></a></td>
      <td><?= $p['section_name'] !== null ? Form::e($p['section_name']) : '<span class="a-muted">без раздела</span>' ?></td>
      <td><a href="<?= Form::e($p['path']) ?>" target="_blank" rel="noopener"><?= Form::e($p['path']) ?></a></td>
      <td><?= Form::yesNo($p['is_active']) ?></td>
      <td class="a-row-actions"><a href="<?= CMS_ADMIN_PREFIX ?>/pages/edit?id=<?= (int) $p['id'] ?>">изменить</a> <?= Form::deleteLink('/pages/delete', (int) $p['id']) ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
