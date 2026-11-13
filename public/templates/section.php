<?php
/**
 * Шаблон страницы раздела (например, «Блог»): заголовок, текст раздела
 * и лента страниц с анонсом и картинкой (15 на страницу, пагинация).
 * Вёрстка построена на основе page.html и карточек из catalog.html.
 *
 * @var array $section
 * @var array $items  Страницы ленты
 * @var \Core\Paginator $pager
 * @var string $basePath
 * @var array $crumbs
 */
use Core\Format;
use Core\Upload;
use Core\Models\Page;
use Site\Tpl;
include $tpl . '/parts/head.php';
?>
<body id="top">

  <div class="dark">
    <div class="container">

<?php $ctaHref = '#quote'; $topbarHref = '#address'; include $tpl . '/parts/header.php'; ?>

<?php include $tpl . '/parts/breadcrumbs.php'; ?>

      <div class="page-head">
        <h1 class="hero__title"><?= Tpl::e($section['name']) ?><?= $pager->page > 1 ? ' — страница ' . $pager->page : '' ?></h1>
      </div>

    </div>
  </div>

  <main>

<?php if (trim((string) $section['text_big']) !== '' && $pager->page === 1): ?>
    <section class="section section--article">
      <div class="container">
        <div class="prose">
          <?= $section['text_big'] /* текст раздела: любой HTML (п. 7) */ ?>
        </div>
      </div>
    </section>
<?php endif; ?>

<?php if ($items): ?>
    <!-- ===== Лента раздела ===== -->
    <section class="section" id="feed">
      <div class="container">
        <div class="grid grid--products grid--feed">
<?php foreach ($items as $item):
    $img = Upload::url(Page::UPLOAD_DIR, $item['image']);
    $date = $item['published_at'] ?: null;
?>
          <article class="product feed-item">
            <a href="<?= Tpl::e($item['path']) ?>" class="product__img-link" tabindex="-1" aria-hidden="true">
<?php if ($img !== ''): ?>
              <img class="product__img" src="<?= Tpl::e($img) ?>" alt="" loading="lazy">
<?php else: ?>
              <div class="product__img product__img--placeholder">[Фото]</div>
<?php endif; ?>
            </a>
            <div class="product__body">
<?php if ($date): ?>
              <p class="feed-item__date"><time datetime="<?= Tpl::e($date) ?>"><?= Tpl::e(Format::dateRu($date)) ?></time></p>
<?php endif; ?>
              <h2 class="product__title"><a href="<?= Tpl::e($item['path']) ?>"><?= Tpl::e($item['name']) ?></a></h2>
<?php if ((string) $item['text_small'] !== ''): ?>
              <p class="product__text"><?= $item['text_small'] ?></p>
<?php endif; ?>
              <div class="product__foot">
                <a href="<?= Tpl::e($item['path']) ?>" class="link-underline">Читать →</a>
              </div>
            </div>
          </article>
<?php endforeach; ?>
        </div>
<?php include $tpl . '/parts/pagination.php'; ?>
      </div>
    </section>
<?php endif; ?>

<?php $ctaTitle = 'Остались вопросы?'; $ctaLead = 'Позвоните или оставьте заявку — ответим и посчитаем стоимость.'; include $tpl . '/parts/cta.php'; ?>

  </main>

<?php $footerAddressId = true; include $tpl . '/parts/footer.php'; ?>
