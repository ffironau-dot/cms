<?php
/**
 * Постраничная навигация (?page=N; первая страница — без параметра).
 *
 * @var \Core\Paginator $pager
 * @var string $basePath
 */
use Core\Paginator;
use Site\Tpl;
if ($pager->pages <= 1) {
    return;
}
?>
        <nav class="pagination" aria-label="Страницы">
<?php if ($pager->page > 1): ?>
          <a class="pagination__link pagination__link--arrow" href="<?= Tpl::e(Paginator::url($basePath, $pager->page - 1)) ?>" rel="prev" aria-label="Предыдущая страница">←</a>
<?php endif; ?>
<?php foreach ($pager->numbers() as $n): ?>
<?php if ($n === 0): ?>
          <span class="pagination__gap">…</span>
<?php elseif ($n === $pager->page): ?>
          <span class="pagination__link is-current" aria-current="page"><?= $n ?></span>
<?php else: ?>
          <a class="pagination__link" href="<?= Tpl::e(Paginator::url($basePath, $n)) ?>"><?= $n ?></a>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($pager->page < $pager->pages): ?>
          <a class="pagination__link pagination__link--arrow" href="<?= Tpl::e(Paginator::url($basePath, $pager->page + 1)) ?>" rel="next" aria-label="Следующая страница">→</a>
<?php endif; ?>
        </nav>
