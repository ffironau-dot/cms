<?php
/**
 * Шаблон страницы категории (catalog.html).
 *
 * Из БД: название (h1), малый текст (подзаголовок), основное изображение,
 * большой текст, список подкатегорий, товары категории и подкатегорий
 * (36 на страницу, с пагинацией), хлебные крошки, SEO.
 * Блоки «Как выбрать», галерея, калькулятор, отзывы, FAQ — статичные (п. 4.8).
 *
 * @var array  $category
 * @var string $image          Адрес основного изображения или ''
 * @var array  $subcategories  Видимые подкатегории
 * @var array  $products       Товары текущей страницы
 * @var \Core\Paginator $pager
 * @var string $basePath
 * @var array  $crumbs
 */
use Core\Upload;
use Core\Models\Category;
use Core\Models\Product;
use Site\Tpl;
$scripts = ['calculator.js'];
include $tpl . '/parts/head.php';
?>
<body id="top">

  <!-- ===== Тёмная верхняя часть ===== -->
  <div class="dark">
    <div class="container">

<?php $ctaHref = '#calc'; $topbarHref = '#address'; include $tpl . '/parts/header.php'; ?>

<?php include $tpl . '/parts/breadcrumbs.php'; ?>

      <section class="hero hero--page">
        <div class="hero__text">
          <h1 class="hero__title"><?= Tpl::e($category['name']) ?></h1>
<?php if ((string) $category['text_small'] !== ''): ?>
          <p class="hero__lead"><?= $category['text_small'] /* малый текст: отфильтрован при сохранении (u, i, b, strong, em, br) */ ?></p>
<?php endif; ?>
          <div class="hero__actions">
            <a href="#calc" class="btn btn--accent btn--lg">Рассчитать стоимость</a>
<?php if ($products): ?>
            <a href="#products" class="btn btn--outline btn--lg">Смотреть варианты</a>
<?php endif; ?>
          </div>
        </div>

<?php if ($image !== ''): ?>
        <div class="page-art page-art--image">
          <img class="page-art__img" src="<?= Tpl::e($image) ?>" alt="<?= Tpl::e($category['name']) ?>">
        </div>
<?php else: ?>
        <div class="page-art" role="img" aria-label="Иллюстрация: светящиеся объёмные буквы на тёмном фасаде">
          <span class="page-art__text">БУКВЫ</span>
        </div>
<?php endif; ?>
      </section>

    </div>
  </div>

  <main>

<?php if (trim((string) $category['text_big']) !== '' && $pager->page === 1): ?>
    <!-- ===== Большой текст категории (HTML из админки) ===== -->
    <section class="section section--text">
      <div class="container">
        <div class="text-block cms-text">
          <?= $category['text_big'] /* большой текст: любой HTML (решение заказчика, п. 7) */ ?>
        </div>
      </div>
    </section>
<?php endif; ?>

<?php if ($subcategories): ?>
    <!-- ===== Подкатегории ===== -->
    <section class="section" id="subcategories">
      <div class="container">
        <div class="section__head">
          <h2 class="h2">Разделы</h2>
        </div>
        <div class="grid grid--products grid--subcats">
<?php foreach ($subcategories as $sub): $subImg = Upload::url(Category::UPLOAD_DIR, $sub['image']); ?>
          <article class="product product--subcat">
            <a href="<?= Tpl::e($sub['path']) ?>" class="product__img-link" tabindex="-1" aria-hidden="true">
<?php if ($subImg !== ''): ?>
              <img class="product__img" src="<?= Tpl::e($subImg) ?>" alt="" loading="lazy">
<?php else: ?>
              <div class="product__img product__img--placeholder">[Фото]</div>
<?php endif; ?>
            </a>
            <div class="product__body">
              <h3 class="product__title"><a href="<?= Tpl::e($sub['path']) ?>"><?= Tpl::e($sub['name']) ?></a></h3>
            </div>
          </article>
<?php endforeach; ?>
        </div>
      </div>
    </section>
<?php endif; ?>

<?php if ($products): ?>
    <!-- ===== Товары категории (и её подкатегорий) ===== -->
    <section class="section" id="products">
      <div class="container">
        <div class="section__head">
          <h2 class="h2">Варианты<?= $pager->page > 1 ? ' — страница ' . $pager->page : '' ?></h2>
        </div>

        <div class="grid grid--products">
<?php foreach ($products as $p): $pImg = Upload::url(Product::UPLOAD_DIR, $p['image']); ?>
          <article class="product">
            <a href="<?= Tpl::e($p['path']) ?>" class="product__img-link" tabindex="-1" aria-hidden="true">
<?php if ($pImg !== ''): ?>
              <img class="product__img" src="<?= Tpl::e($pImg) ?>" alt="" loading="lazy">
<?php else: ?>
              <div class="product__img product__img--placeholder">[Фото]</div>
<?php endif; ?>
            </a>
            <div class="product__body">
              <h3 class="product__title"><a href="<?= Tpl::e($p['path']) ?>"><?= Tpl::e($p['name']) ?></a></h3>
<?php if ((string) $p['text_small'] !== ''): ?>
              <p class="product__text"><?= $p['text_small'] ?></p>
<?php endif; ?>
              <div class="product__foot">
                <p class="product__price">
<?php if ($p['price'] !== null): ?>
                  <?= Tpl::price($p['price']) ?>
<?php if ($p['price_old'] !== null): ?>
                  <s class="product__price-old"><?= Tpl::price($p['price_old']) ?></s>
<?php endif; ?>
<?php else: ?>
                  Цена по запросу
<?php endif; ?>
                </p>
                <a href="<?= Tpl::e($p['path']) ?>" class="btn btn--dark btn--sm">Подробнее</a>
              </div>
            </div>
          </article>
<?php endforeach; ?>
        </div>

<?php include $tpl . '/parts/pagination.php'; ?>
      </div>
    </section>
<?php endif; ?>

    <!-- ===== Как выбрать ===== -->
    <section class="section section--text">
      <div class="container">
        <div class="two-col">
          <div class="text-block">
            <h2 class="h2 h2--bold">Как выбрать объёмные буквы</h2>
            <h3 class="text-block__sub">Материал, подсветка и размер определяют и внешний вид, и срок службы.</h3>
          </div>
          <div class="text-block">
            <p class="text-block__body">[Основной текст раздела: чем отличаются акрил, металл и ПВХ, где какой материал уместен, как работает лицевая и контражурная подсветка.]</p>
            <p class="text-block__body">[Второй абзац: что учитывать при выборе размера и места установки, как мы помогаем с согласованием.]</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== Галерея ===== -->
    <section class="section" id="gallery">
      <div class="container">
        <div class="section__head">
          <h2 class="h2">Галерея работ</h2>
          <a href="#gallery" class="link-underline">Все проекты →</a>
        </div>

        <!-- Вместо .tile__img – placeholder вставьте <img class="tile__img" src="images/gallery-1.webp" alt="..." loading="lazy"> -->
        <div class="gallery">
          <figure class="tile"><div class="tile__img tile__img--placeholder">[Фото 1]</div><figcaption class="tile__caption">[Объект · район]</figcaption></figure>
          <figure class="tile"><div class="tile__img tile__img--placeholder">[Фото 2]</div><figcaption class="tile__caption">[Объект · район]</figcaption></figure>
          <figure class="tile"><div class="tile__img tile__img--placeholder">[Фото 3]</div><figcaption class="tile__caption">[Объект · район]</figcaption></figure>
          <figure class="tile"><div class="tile__img tile__img--placeholder">[Фото 4]</div><figcaption class="tile__caption">[Объект · район]</figcaption></figure>
          <figure class="tile"><div class="tile__img tile__img--placeholder">[Фото 5]</div><figcaption class="tile__caption">[Объект · район]</figcaption></figure>
          <figure class="tile"><div class="tile__img tile__img--placeholder">[Фото 6]</div><figcaption class="tile__caption">[Объект · район]</figcaption></figure>
        </div>
      </div>
    </section>

    <!-- ===== Калькулятор ===== -->
    <section class="section" id="calc">
      <div class="container">
        <div class="section__intro">
          <h2 class="h2">Калькулятор стоимости</h2>
          <p class="section__note">Задайте параметры — получите ориентировочную цену. Точную стоимость назовём после замера.</p>
        </div>

        <!-- data-rate — ставка, ₽ за 1 см высоты одной буквы (демо-значение, замените на свою).
             Множители материалов и подсветки — вверху файла calculator.js -->
        <div class="calc" id="calc-app" data-rate="40">

          <div class="calc__form" role="group" aria-label="Параметры расчёта">
            <fieldset class="calc__group">
              <legend class="calc__legend">Материал букв</legend>
              <div class="calc__pills" data-group="type">
                <button type="button" class="pill" data-value="acryl" aria-pressed="true">Акрил</button>
                <button type="button" class="pill" data-value="steel" aria-pressed="false">Нержавеющая сталь</button>
                <button type="button" class="pill" data-value="pvc" aria-pressed="false">ПВХ</button>
              </div>
            </fieldset>

            <fieldset class="calc__group">
              <legend class="calc__legend">Подсветка</legend>
              <div class="calc__pills" data-group="light">
                <button type="button" class="pill" data-value="none" aria-pressed="false">Без подсветки</button>
                <button type="button" class="pill" data-value="face" aria-pressed="true">Лицевая</button>
                <button type="button" class="pill" data-value="back" aria-pressed="false">Контражурная</button>
              </div>
            </fieldset>

            <div class="calc__group">
              <div class="calc__row">
                <label for="calc-height" class="calc__legend">Высота букв</label>
                <output for="calc-height" id="calc-height-out" class="calc__value">40 см</output>
              </div>
              <input id="calc-height" class="range" type="range" min="10" max="150" step="5" value="40">
              <div class="calc__scale"><span>10 см</span><span>150 см</span></div>
            </div>

            <div class="calc__group">
              <p class="calc__legend" id="calc-count-label">Количество знаков</p>
              <div class="stepper" role="group" aria-labelledby="calc-count-label">
                <button type="button" class="stepper__btn" id="calc-dec" aria-label="Уменьшить количество знаков">−</button>
                <output class="stepper__value" id="calc-count-out">8</output>
                <button type="button" class="stepper__btn" id="calc-inc" aria-label="Увеличить количество знаков">+</button>
              </div>
            </div>

            <div class="calc__group calc__checks">
              <label class="check"><input type="checkbox" id="calc-montage" checked> Монтаж на объекте</label>
              <label class="check"><input type="checkbox" id="calc-urgent"> Срочное изготовление</label>
            </div>
          </div>

          <div class="calc__result">
            <p class="calc__label">Ориентировочная стоимость</p>
            <p class="calc__total" id="calc-total" aria-live="polite">—</p>
            <dl class="calc__rows" id="calc-rows"></dl>
            <a href="/#quote" class="btn btn--accent btn--xl">Заказать точный расчёт</a>
            <p class="calc__note">Расчёт ориентировочный и не является офертой. Итоговая цена зависит от макета, сложности и условий монтажа.</p>
          </div>

        </div>
      </div>
    </section>

    <!-- ===== Текстовый блок B: под калькулятором ===== -->
    <section class="section section--text">
      <div class="container">
        <div class="text-block">
          <h2 class="h2">[Заголовок текстового блока под калькулятором]</h2>
          <h3 class="text-block__sub">[Подзаголовок — одна-две строки, уточняющие заголовок]</h3>
          <p class="text-block__body">[Основной текст: как рассчитывается точная стоимость, что влияет на цену, как дальше будет проходить заказ.]</p>
        </div>
      </div>
    </section>

    <!-- ===== Отзывы ===== -->
    <section class="section">
      <div class="container">
        <h2 class="h2 h2--spaced">Отзывы клиентов об объёмных буквах</h2>
        <div class="grid grid--reviews">
          <blockquote class="review">
            <p class="review__text">[Текст реального отзыва клиента]</p>
            <footer class="review__author">[Имя, компания]</footer>
          </blockquote>
          <blockquote class="review">
            <p class="review__text">[Текст реального отзыва клиента]</p>
            <footer class="review__author">[Имя, компания]</footer>
          </blockquote>
          <blockquote class="review">
            <p class="review__text">[Текст реального отзыва клиента]</p>
            <footer class="review__author">[Имя, компания]</footer>
          </blockquote>
        </div>
      </div>
    </section>

    <!-- ===== Вопросы и ответы ===== -->
    <section class="section" id="faq">
      <div class="container">
        <div class="two-col">
          <h2 class="h2">Вопросы и ответы</h2>

          <div class="faq">
            <details class="faq__item" open>
              <summary class="faq__q">От чего зависит цена объёмных букв?</summary>
              <p class="faq__a">Стоимость определяют материал, высота и количество знаков, тип подсветки, сложность шрифта и условия монтажа. Ориентир можно получить в калькуляторе выше, точную цену назовём после замера.</p>
            </details>
            <details class="faq__item">
              <summary class="faq__q">Нужно ли согласовывать вывеску?</summary>
              <p class="faq__a">Для размещения вывески на фасаде в Москве обычно требуется согласование. Поможем подготовить документы и эскиз в соответствии с требованиями города. [Уточнить состав услуги]</p>
            </details>
            <details class="faq__item">
              <summary class="faq__q">Какие сроки изготовления?</summary>
              <p class="faq__a">[СРОК] в зависимости от материала, подсветки и сложности макета. Срочное изготовление возможно по согласованию.</p>
            </details>
            <details class="faq__item">
              <summary class="faq__q">Какая гарантия на вывеску?</summary>
              <p class="faq__a">Гарантия [СРОК ГАРАНТИИ] на конструкцию и светодиодные элементы. Условия обслуживания — по договору.</p>
            </details>
            <details class="faq__item">
              <summary class="faq__q">Можно ли заказать по своему макету?</summary>
              <p class="faq__a">Да, принимаем макеты в векторном формате. Если макета нет, наш дизайнер подготовит визуализацию на вашем фасаде. [Условия по стоимости дизайна]</p>
            </details>
          </div>
        </div>
      </div>
    </section>

<?php $ctaTitle = 'Не нашли нужный вариант?'; $ctaLead = 'Расскажите задачу — предложим решение и посчитаем индивидуально.'; include $tpl . '/parts/cta.php'; ?>

  </main>

<?php $footerAddressId = true; include $tpl . '/parts/footer.php'; ?>
