<?php
/**
 * Хлебные крошки с микроразметкой Schema.org BreadcrumbList (п. 8.4).
 * Последний элемент — текущая страница (без ссылки).
 *
 * @var array $crumbs [[название, путь], ...]
 * @var bool  $crumbsLight Светлый вариант (карточка товара)
 */
use Site\Seo;
use Site\Tpl;
$last = count($crumbs) - 1;
?>
      <nav class="breadcrumbs<?= !empty($crumbsLight) ? ' breadcrumbs--light' : '' ?>" aria-label="Хлебные крошки">
        <ol class="breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">
<?php foreach ($crumbs as $i => [$name, $path]): ?>
          <li class="breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
<?php if ($i < $last): ?>
            <a href="<?= Tpl::e($path) ?>" itemprop="item"><span itemprop="name"><?= Tpl::e($name) ?></span></a>
            <span aria-hidden="true">/</span>
<?php else: ?>
            <span aria-current="page" itemprop="name"><?= Tpl::e($name) ?></span>
            <link itemprop="item" href="<?= Tpl::e(Seo::absolute($path)) ?>">
<?php endif; ?>
            <meta itemprop="position" content="<?= $i + 1 ?>">
          </li>
<?php endforeach; ?>
        </ol>
      </nav>
