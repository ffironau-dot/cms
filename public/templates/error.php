<?php
/**
 * Страница «Не найдено» (404). Построена на вёрстке page.html.
 */
use Site\Tpl;
include $tpl . '/parts/head.php';
?>
<body id="top">

  <div class="dark">
    <div class="container">

<?php $ctaHref = '#quote'; $topbarHref = '#address'; include $tpl . '/parts/header.php'; ?>

      <div class="page-head">
        <h1 class="hero__title">Страница не найдена</h1>
        <p class="page-head__lead">Возможно, страница была удалена или вы ошиблись в адресе. Перейдите на <a href="/" class="link-underline">главную страницу</a>.</p>
      </div>

    </div>
  </div>

  <main>
<?php $ctaTitle = 'Остались вопросы?'; $ctaLead = 'Позвоните или оставьте заявку — ответим и посчитаем стоимость.'; include $tpl . '/parts/cta.php'; ?>
  </main>

<?php $footerAddressId = true; include $tpl . '/parts/footer.php'; ?>
