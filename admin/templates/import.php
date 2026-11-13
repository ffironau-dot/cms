<?php
/** @var ?array $report @var array $errors */
use Admin\Form;
?>
<?= Form::errors($errors) ?>
<?php if ($report): ?>
<div class="a-panel">
  <h2 class="a-h2">Отчёт об импорте</h2>
  <p>Добавлено товаров: <strong><?= (int) $report['added'] ?></strong>. Обновлено: <strong><?= (int) $report['updated'] ?></strong>. Пропущено строк: <strong><?= count($report['skipped']) ?></strong>.</p>
<?php if ($report['skipped']): ?>
  <table class="a-table">
    <thead><tr><th>Строка</th><th>Артикул</th><th>Причина пропуска</th></tr></thead>
    <tbody>
<?php foreach ($report['skipped'] as $s): ?>
      <tr><td><?= (int) $s['line'] ?></td><td><?= Form::e($s['sku']) ?></td><td><?= Form::e($s['reason']) ?></td></tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php if ($report['warnings']): ?>
  <h3>Предупреждения (строки импортированы)</h3>
  <ul>
<?php foreach ($report['warnings'] as $w): ?>
    <li>Строка <?= (int) $w['line'] ?> (<?= Form::e($w['sku']) ?>): <?= Form::e($w['reason']) ?></li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="a-form">
  <?= Form::token() ?>
  <div class="a-field"><label for="f-csv">CSV-файл</label><input id="f-csv" type="file" name="csv" accept=".csv,text/csv" required></div>
  <div class="a-actions"><button type="submit" class="a-btn">Импортировать</button></div>
</form>

<div class="a-hint a-help">
  <p><strong>Формат файла:</strong> разделитель — «;», кодировка — UTF-8, первая строка — названия колонок:</p>
  <p><code>артикул; URL; название; категория; title; description; og:title; og:description; малый текст; большой текст; старая цена; новая цена; активность; ссылка на основное фото</code></p>
  <ul>
    <li>Товар ищется по артикулу: если найден — обновляется, иначе создаётся. При обновлении пустая ячейка не стирает значение.</li>
    <li>Категория указывается её адресом, например <code>/neonovye-vyveski/</code>. Пусто — «Без категории» для нового товара.</li>
    <li>URL — сегмент адреса товара (<code>akrilovye-bukvy</code>); пусто у нового товара — создаётся из названия.</li>
    <li>Активность: <code>1</code> или <code>0</code>. Цены: число с запятой, без пробелов и символа валюты (<code>1250,50</code>).</li>
    <li>Фото по ссылке скачивается на сервер. Дополнительные фото и свойства категорий через CSV не импортируются.</li>
    <li>Строки с ошибками пропускаются, остальные импортируются.</li>
  </ul>
  <p><a href="<?= CMS_ADMIN_PREFIX ?>/products/import-sample">Скачать пример файла</a></p>
</div>
