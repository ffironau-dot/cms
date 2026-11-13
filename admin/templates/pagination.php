<?php
/** @var \Core\Paginator $pager @var string $base @var array $query */
use Admin\Form;
use Core\Paginator;
if ($pager->pages <= 1) {
    return;
}
?>
<nav class="a-pager">
<?php foreach ($pager->numbers() as $n): ?>
<?php if ($n === 0): ?>
  <span>…</span>
<?php elseif ($n === $pager->page): ?>
  <strong><?= $n ?></strong>
<?php else: ?>
  <a href="<?= Form::e(Paginator::url($base, $n, $query)) ?>"><?= $n ?></a>
<?php endif; ?>
<?php endforeach; ?>
</nav>
