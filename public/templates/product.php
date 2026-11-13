<?php
/**
 * Шаблон карточки товара (product.html).
 *
 * Из БД: галерея (основное и дополнительные фото), категория, артикул,
 * название, малый текст, цены, характеристики (свойства категории),
 * большой текст (описание), SEO, хлебные крошки.
 * Вместо корзины — кнопка «Заказать» со всплывающей формой заявки.
 *
 * @var array $product, $category, $gallery, $specs, $crumbs
 * @var string $path
 */
use Site\Tpl;
$scripts = ['product.js'];
include $tpl . '/parts/head.php';
?>
<body id="top">

  <!-- ===== Тёмная верхняя часть ===== -->
  <div class="dark">
    <div class="container">

<?php $ctaHref = ''; $topbarHref = '#address'; include $tpl . '/parts/header.php'; ?>

    </div>
  </div>

  <main>

    <!-- ===== Хлебные крошки ===== -->
    <div class="container">
<?php $crumbsLight = true; include $tpl . '/parts/breadcrumbs.php'; ?>
    </div>

    <!-- ===== Товар: галерея и блок заказа ===== -->
    <section class="section section--product">
      <div class="container">
        <div class="pd">

          <div class="pd__gallery">
<?php if ($gallery): ?>
            <div class="pd__main" id="pd-main">
              <img class="pd__img" src="<?= Tpl::e($gallery[0]['src']) ?>" alt="<?= Tpl::e($gallery[0]['alt']) ?>">
            </div>
<?php if (count($gallery) > 1): ?>
            <div class="pd__thumbs" id="pd-thumbs">
<?php foreach ($gallery as $i => $g): ?>
              <button type="button" class="thumb thumb--img" data-src="<?= Tpl::e($g['src']) ?>" data-alt="<?= Tpl::e($g['alt']) ?>" aria-label="Показать фото <?= $i + 1 ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>"><img src="<?= Tpl::e($g['src']) ?>" alt="" loading="lazy"></button>
<?php endforeach; ?>
            </div>
<?php endif; ?>
<?php else: ?>
            <div class="pd__main" id="pd-main" role="img" aria-label="Фото товара">
              <span id="pd-main-label">[Фото]</span>
            </div>
<?php endif; ?>
          </div>

          <div class="pd__buy">
            <div class="pd__head">
              <p class="pd__eyebrow"><?= Tpl::e($category['name']) ?> · арт. <?= Tpl::e($product['sku']) ?></p>
              <h1 class="pd__title"><?= Tpl::e($product['name']) ?></h1>
<?php if ((string) $product['text_small'] !== ''): ?>
              <p class="pd__lead"><?= $product['text_small'] /* малый текст: отфильтрован при сохранении */ ?></p>
<?php endif; ?>
            </div>

            <div class="pd__price">
<?php if ($product['price'] !== null): ?>
              <p class="pd__price-main"><?= Tpl::price($product['price']) ?></p>
<?php if ($product['price_old'] !== null): ?>
              <p class="pd__price-old"><s><?= Tpl::price($product['price_old']) ?></s></p>
<?php endif; ?>
<?php else: ?>
              <p class="pd__price-main pd__price-main--request">Цена по запросу</p>
<?php endif; ?>
            </div>

            <div class="buy-row">
              <button type="button" class="btn btn--accent btn--xl buy-row__add" id="order-open" aria-haspopup="dialog" aria-controls="order-dialog">Заказать</button>
            </div>

            <ul class="pd__facts">
              <li>Срок изготовления: [СРОК]</li>
              <li>Монтаж и доставка по Москве и области — по запросу</li>
              <li>Гарантия: [СРОК ГАРАНТИИ]</li>
            </ul>
          </div>

        </div>
      </div>
    </section>

<?php if ($specs): ?>
    <!-- ===== Характеристики (свойства категории) ===== -->
    <section class="section" id="specs">
      <div class="container">
        <h2 class="h2 h2--spaced">Характеристики</h2>
        <div class="specs-wrap">
          <table class="specs">
            <caption class="visually-hidden">Технические характеристики товара</caption>
            <tbody>
<?php foreach ($specs as [$name, $value]): ?>
              <tr><th scope="row"><?= Tpl::e($name) ?></th><td><?= Tpl::e($value) ?></td></tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
<?php endif; ?>

<?php if (trim((string) $product['text_big']) !== ''): ?>
    <!-- ===== Описание (большой текст) ===== -->
    <section class="section">
      <div class="container">
        <div class="two-col">
          <div class="text-block">
            <h2 class="h2 h2--bold">Описание</h2>
          </div>
          <div class="text-block cms-text">
            <?= $product['text_big'] /* большой текст: любой HTML (п. 7) */ ?>
          </div>
        </div>
      </div>
    </section>
<?php endif; ?>

<?php $ctaTitle = 'Нужен другой размер или цвет?'; $ctaLead = 'Изготовим по вашему макету и посчитаем индивидуально.'; include $tpl . '/parts/cta.php'; ?>

  </main>

  <!-- ===== Всплывающая форма заказа ===== -->
  <dialog class="modal" id="order-dialog" aria-labelledby="order-title">
    <form class="form modal__form" id="order-form" action="/send/" method="post" novalidate>
      <button type="button" class="modal__close" data-close aria-label="Закрыть">×</button>
      <h2 class="h2 modal__title" id="order-title">Заказать</h2>
      <p class="modal__product"><?= Tpl::e($product['name']) ?> · арт. <?= Tpl::e($product['sku']) ?></p>

      <input type="hidden" name="form" value="order">
      <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
      <input type="hidden" name="back" value="<?= Tpl::e($path) ?>">
      <!-- Поле-ловушка для спам-ботов -->
      <div class="form__hp" aria-hidden="true">
        <label for="o-website">Сайт</label>
        <input id="o-website" name="website" type="text" tabindex="-1" autocomplete="off">
      </div>

      <div class="field">
        <label for="o-name" class="field__label">Имя</label>
        <input id="o-name" name="name" type="text" class="field__input" autocomplete="name" maxlength="100" required>
        <span class="field__error" id="o-name-error" role="alert"></span>
      </div>
      <div class="field">
        <label for="o-phone" class="field__label">Телефон</label>
        <input id="o-phone" name="phone" type="tel" class="field__input" autocomplete="tel" inputmode="tel" placeholder="+7 (___) ___-__-__" maxlength="40" required>
        <span class="field__error" id="o-phone-error" role="alert"></span>
      </div>
      <div class="field">
        <label for="o-comment" class="field__label">Комментарий</label>
        <textarea id="o-comment" name="comment" class="field__input field__input--area" rows="3" maxlength="3000"></textarea>
      </div>

      <button type="submit" class="btn btn--accent btn--xl">Отправить заявку</button>
      <p class="form__legal">Нажимая кнопку, вы соглашаетесь с <a href="/politika/">политикой обработки персональных данных</a>.</p>
    </form>
  </dialog>

<?php $footerAddressId = true; include $tpl . '/parts/footer.php'; ?>
