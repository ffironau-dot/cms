<?php
/**
 * Верхняя строка с адресом и шапка сайта (логотип, меню, телефон, кнопка).
 *
 * @var string $address  Адрес компании (п. 4.1) — с микроразметкой Schema.org
 * @var string $phone    Телефон (п. 4.3)
 * @var string $logoUrl, $logoAlt
 * @var array  $menu     Дерево меню (Site\Menu::build)
 * @var bool   $isHome   Главная страница (логотип ведёт на #top)
 * @var string $ctaHref  Ссылка кнопки «Рассчитать стоимость»; пусто — без кнопки
 * @var string $topbarHref Ссылка адреса в верхней строке
 */
use Site\Tpl;
$isHome = $isHome ?? false;
$ctaHref = $ctaHref ?? '#quote';
$topbarHref = $topbarHref ?? '#address';
?>
      <div class="topbar"<?php if ($address !== ''): ?> itemscope itemtype="https://schema.org/LocalBusiness"<?php endif; ?>>
<?php if ($address !== ''): ?>
        <a href="<?= Tpl::e($topbarHref) ?>" class="topbar__link" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress"><span itemprop="streetAddress"><?= Tpl::e($address) ?></span></a>
<?php endif; ?>
      </div>

      <header class="header">
        <a href="<?= $isHome ? '#top' : '/' ?>" class="logo"><?= Tpl::logo($logoUrl, $logoAlt) ?></a>

        <button class="burger" type="button" aria-label="Открыть меню" aria-expanded="false" aria-controls="main-nav">
          <span class="burger__line"></span>
          <span class="burger__line"></span>
          <span class="burger__line"></span>
        </button>

        <?php include $tpl . '/parts/nav.php'; ?>

        <div class="header__cta<?= $ctaHref === '' ? ' header__cta--show' : '' ?>">
          <?= Tpl::phone($phone, 'header__phone') ?>
<?php if ($ctaHref !== ''): ?>
          <a href="<?= Tpl::e($ctaHref) ?>" class="btn btn--accent btn--sm">Рассчитать стоимость</a>
<?php endif; ?>
        </div>
      </header>
