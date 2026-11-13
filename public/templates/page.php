<?php
/**
 * Шаблон текстовой страницы (page.html).
 *
 * Из БД: название (h1), короткий текст (вводный абзац), основная картинка,
 * длинный текст (HTML), дата публикации (для страниц раздела), SEO.
 * Блок «Содержание» строится автоматически по заголовкам h2 длинного
 * текста и не выводится, если заголовков нет.
 *
 * @var array  $page
 * @var ?array $section
 * @var string $image  Адрес основной картинки или ''
 * @var string $html   Длинный текст с id у заголовков h2
 * @var array  $toc    [[id, текст], ...]
 * @var array  $crumbs
 */
use Core\Format;
use Site\Tpl;
include $tpl . '/parts/head.php';
?>
<body id="top">

  <!-- ===== Тёмная верхняя часть ===== -->
  <div class="dark">
    <div class="container">

<?php $ctaHref = '#quote'; $topbarHref = '#address'; include $tpl . '/parts/header.php'; ?>

<?php include $tpl . '/parts/breadcrumbs.php'; ?>

      <div class="page-head">
<?php if ($section && $page['published_at']): ?>
        <p class="eyebrow"><time datetime="<?= Tpl::e($page['published_at']) ?>"><?= Tpl::e(Format::dateRu($page['published_at'])) ?></time></p>
<?php endif; ?>
        <h1 class="hero__title"><?= Tpl::e($page['name']) ?></h1>
<?php if ((string) $page['text_small'] !== ''): ?>
        <p class="page-head__lead"><?= $page['text_small'] /* короткий текст: отфильтрован при сохранении */ ?></p>
<?php endif; ?>
      </div>

    </div>
  </div>

  <main>

    <!-- ===== Содержание и статья ===== -->
    <section class="section section--article" aria-label="Содержание страницы">
      <div class="container">
        <div class="doc">

<?php if ($toc): ?>
          <nav class="toc" aria-label="Содержание">
            <p class="toc__title">Содержание</p>
            <div class="toc__list">
<?php foreach ($toc as [$id, $text]): ?>
              <a href="#<?= Tpl::e($id) ?>"><?= Tpl::e($text) ?></a>
<?php endforeach; ?>
            </div>
          </nav>
<?php endif; ?>

          <article class="prose">
<?php if ($image !== ''): ?>
            <figure class="prose__figure">
              <img class="prose__img" src="<?= Tpl::e($image) ?>" alt="<?= Tpl::e($page['name']) ?>">
            </figure>
<?php endif; ?>
            <?= $html /* длинный текст: любой HTML (п. 7) */ ?>
          </article>
        </div>
      </div>
    </section>

<?php $ctaTitle = 'Остались вопросы?'; $ctaLead = 'Позвоните или оставьте заявку — ответим и посчитаем стоимость.'; include $tpl . '/parts/cta.php'; ?>

  </main>

<?php $footerAddressId = true; include $tpl . '/parts/footer.php'; ?>
