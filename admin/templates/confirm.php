<?php
/**
 * Запрос подтверждения удаления.
 * @var string $message
 * @var string $cancel
 * @var array  $extra  ['image' => адрес превью]
 */
use Admin\Form;
?>
<form method="post" class="a-confirm">
  <?= Form::token() ?>
  <p class="a-confirm__text"><?= Form::e($message) ?></p>
<?php if (!empty($extra['image'])): ?>
  <div class="a-preview"><img src="<?= Form::e($extra['image']) ?>" alt=""></div>
<?php endif; ?>
  <div class="a-actions">
    <button type="submit" class="a-btn a-btn--danger">Да, удалить</button>
    <a href="<?= Form::e($cancel) ?>" class="a-btn a-btn--ghost">Отмена</a>
  </div>
</form>
