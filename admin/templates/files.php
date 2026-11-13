<?php
/** @var array $rows @var \Core\Paginator $pager @var string $q */
use Admin\Form;
use Core\Upload;
use Core\Models\FileItem;
?>
<form method="post" enctype="multipart/form-data" class="a-form a-form--inline a-panel">
  <?= Form::token() ?>
  <div class="a-field"><label for="f-files">Загрузить картинки</label><input id="f-files" type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" required></div>
  <button type="submit" class="a-btn">Загрузить</button>
  <p class="a-hint">JPG, PNG, WebP, GIF. Имя файла сохраняется; если такое уже есть — добавляется номер (foto-2.jpg).</p>
</form>
<form method="get" class="a-form a-form--inline">
  <?= Form::text('q', 'Поиск по имени', $q) ?>
  <button type="submit" class="a-btn a-btn--ghost">Найти</button>
</form>
<p class="a-muted">Файлов: <?= (int) $pager->total ?>. Скопируйте адрес и вставьте в HTML-код: <code>&lt;img src="адрес" alt="..."&gt;</code></p>
<?php if ($rows): ?>
<table class="a-table">
  <thead><tr><th>Миниатюра</th><th>Имя</th><th>Адрес</th><th></th></tr></thead>
  <tbody>
<?php foreach ($rows as $f): $url = Upload::url(FileItem::UPLOAD_DIR, $f['filename']); ?>
    <tr>
      <td class="a-thumb"><img src="<?= Form::e($url) ?>" alt="" loading="lazy"></td>
      <td><?= Form::e($f['filename']) ?></td>
      <td><input type="text" readonly value="<?= Form::e($url) ?>" class="a-copy-src" aria-label="Адрес файла"></td>
      <td class="a-row-actions">
        <button type="button" class="a-btn a-btn--ghost a-btn--sm" data-copy="<?= Form::e($url) ?>">копировать адрес</button>
        <?= Form::deleteLink('/files/delete', (int) $f['id']) ?>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
<?php $base = CMS_ADMIN_PREFIX . '/files'; $query = array_filter(['q' => $q]); include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
