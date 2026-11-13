<?php
/** @var array $slides @var bool $autoplay @var string $interval */
use Admin\Form;
use Core\Upload;
use Core\Models\Slide;
?>
<form method="post" action="<?= CMS_ADMIN_PREFIX ?>/slides/settings" class="a-form a-form--inline a-panel">
  <?= Form::token() ?>
  <strong>Настройки слайдера</strong>
  <?= Form::checkbox('slider_autoplay', 'Автопрокрутка', $autoplay) ?>
  <?= Form::text('slider_interval', 'Интервал, секунд', $interval, ['type' => 'number', 'min' => 1, 'max' => 600]) ?>
  <button type="submit" class="a-btn">Сохранить настройки</button>
</form>

<p><a class="a-btn" href="<?= CMS_ADMIN_PREFIX ?>/slides/edit">+ Добавить слайд</a></p>
<p class="a-hint">Слайды выводятся по возрастанию поля «порядок». Заголовок первого активного слайда выводится тегом h1, остальных — h2. Хотя бы один слайд всегда должен быть активным. Если активный слайд один, стрелки, точки и счётчик не выводятся.</p>
<table class="a-table">
  <thead><tr><th>Фото</th><th>Заголовок</th><th>Порядок</th><th>Активность</th><th></th></tr></thead>
  <tbody>
<?php foreach ($slides as $s): ?>
    <tr>
      <td class="a-thumb"><?php if ($s['image']): ?><img src="<?= Form::e(Upload::url(Slide::UPLOAD_DIR, $s['image'])) ?>" alt=""><?php endif; ?></td>
      <td><?= Form::e($s['title']) ?></td>
      <td><?= (int) $s['sort'] ?></td>
      <td><?= Form::yesNo($s['is_active']) ?></td>
      <td class="a-row-actions"><a href="<?= CMS_ADMIN_PREFIX ?>/slides/edit?id=<?= (int) $s['id'] ?>">изменить</a> <?= Form::deleteLink('/slides/delete', (int) $s['id']) ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
