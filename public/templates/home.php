<?php
/**
 * Шаблон главной страницы (index.html).
 *
 * Динамические части: <head> (Title, Description, OG — из админки, п. 4.6),
 * адрес, телефон, логотип, меню, слайдер (п. 4.5), форма заявки (п. 4.7).
 * Остальные блоки статичны и правятся в этом файле (п. 4.8 ТЗ).
 *
 * @var array $slides            Активные слайды по порядку
 * @var int   $sliderAutoplayMs  Интервал автопрокрутки, мс (0 — выключена)
 */
use Core\Upload;
use Core\Models\Slide;
use Site\Tpl;
include $tpl . '/parts/head.php';
$count = count($slides);
?>
<body id="top">

  <!-- ===== Тёмная верхняя часть: адрес, шапка, первый экран ===== -->
  <div class="dark">
    <div class="container">

<?php $isHome = true; $ctaHref = '#quote'; $topbarHref = '#address'; include $tpl . '/parts/header.php'; ?>

      <section class="slider" id="hero-slider" aria-roledescription="carousel" aria-label="Слайдер на главной" data-autoplay="<?= (int) $sliderAutoplayMs ?>">
        <div class="slider__track" aria-live="off">
<?php foreach ($slides as $i => $slide):
    // У первого активного слайда заголовок — h1, у остальных — h2 (на странице ровно один h1).
    $h = $i === 0 ? 'h1' : 'h2';
    $btn1 = $slide['btn1_text'] !== '' && $slide['btn1_url'] !== '';
    $btn2 = $slide['btn2_text'] !== '' && $slide['btn2_url'] !== '';
?>
          <div class="slide" role="group" aria-roledescription="slide" aria-label="Слайд <?= $i + 1 ?> из <?= $count ?>"<?= $i > 0 ? ' hidden' : '' ?>>
            <div class="slide__text">
<?php if ($slide['eyebrow'] !== ''): ?>
              <p class="eyebrow"><?= Tpl::e($slide['eyebrow']) ?></p>
<?php endif; ?>
              <<?= $h ?> class="hero__title"><?= Tpl::e($slide['title']) ?></<?= $h ?>>
<?php if ((string) $slide['text'] !== ''): ?>
              <p class="hero__lead"><?= nl2br(Tpl::e($slide['text']), false) ?></p>
<?php endif; ?>
<?php if ($btn1 || $btn2): ?>
              <div class="hero__actions">
<?php if ($btn1): ?>
                <a href="<?= Tpl::e($slide['btn1_url']) ?>" class="btn btn--accent btn--lg"><?= Tpl::e($slide['btn1_text']) ?></a>
<?php endif; ?>
<?php if ($btn2): ?>
                <a href="<?= Tpl::e($slide['btn2_url']) ?>" class="btn btn--outline btn--lg"><?= Tpl::e($slide['btn2_text']) ?></a>
<?php endif; ?>
              </div>
<?php endif; ?>
            </div>
            <div class="slide__media">
<?php if ((string) $slide['image'] !== ''): ?>
              <img class="slide__img" src="<?= Tpl::e(Upload::url(Slide::UPLOAD_DIR, $slide['image'])) ?>" alt="<?= Tpl::e($slide['image_alt']) ?>"<?= $i > 0 ? ' loading="lazy"' : '' ?>>
<?php endif; ?>
<?php if ((string) $slide['caption'] !== ''): ?>
              <span class="slide__caption"><?= Tpl::e($slide['caption']) ?></span>
<?php endif; ?>
            </div>
          </div>
<?php endforeach; ?>
        </div>

<?php if ($count > 1): // Один слайд — без стрелок, точек и счётчика ?>
        <div class="slider__controls">
          <div class="slider__dots">
<?php for ($i = 0; $i < $count; $i++): ?>
            <button type="button" class="slider__dot<?= $i === 0 ? ' is-active' : '' ?>" aria-label="Слайд <?= $i + 1 ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>></button>
<?php endfor; ?>
            <span class="slider__counter" aria-live="polite"><?= sprintf('%02d / %02d', 1, $count) ?></span>
          </div>
          <div class="slider__arrows">
            <button type="button" class="slider__arrow" data-dir="-1" aria-label="Предыдущий слайд"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg></button>
            <button type="button" class="slider__arrow" data-dir="1" aria-label="Следующий слайд"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg></button>
          </div>
        </div>
<?php endif; ?>
      </section>

    </div>
  </div>

  <main>

    <!-- ===== Текстовый блок 1 ===== -->
    <section class="section section--text">
      <div class="container">
        <div class="text-block">
          <h2 class="h2 h2--bold">[Заголовок первого текстового блока]</h2>
          <h3 class="text-block__sub">[Подзаголовок — одна-две строки, уточняющие заголовок]</h3>
          <p class="text-block__body">[Основной текст блока: абзац о компании, подходе или условиях работы.]</p>
        </div>
      </div>
    </section>

    <!-- ===== Виды вывесок ===== -->
    <section class="section" id="types">
      <div class="container">
        <div class="section__head">
          <h2 class="h2">Что мы изготавливаем</h2>
          <p class="section__note">Подберём тип вывески под фасад, бюджет и требования площадки.</p>
        </div>

        <div class="grid grid--cards">
          <article class="card">
            <span class="card__num">01</span>
            <h3 class="card__title"><a href="#types">Объёмные буквы</a></h3>
            <p class="card__text">С контражурной или лицевой подсветкой, из акрила, металла, пластика.</p>
            <p class="card__price">от [ЦЕНА] ₽</p>
          </article>

          <article class="card">
            <span class="card__num">02</span>
            <h3 class="card__title">Световые короба</h3>
            <p class="card__text">Плоские и двусторонние, на кронштейне или в плоскости фасада.</p>
            <p class="card__price">от [ЦЕНА] ₽</p>
          </article>

          <article class="card">
            <span class="card__num">03</span>
            <h3 class="card__title">Неоновые вывески</h3>
            <p class="card__text">Гибкий неон для витрин, баров и интерьеров — по вашему макету или эскизу.</p>
            <p class="card__price">от [ЦЕНА] ₽</p>
          </article>

          <article class="card">
            <span class="card__num">04</span>
            <h3 class="card__title">Фасадные панели и крышные установки</h3>
            <p class="card__text">Крупные конструкции с расчётом на ветровую нагрузку и креплением на объекте.</p>
            <p class="card__price">от [ЦЕНА] ₽</p>
          </article>

          <article class="card">
            <span class="card__num">05</span>
            <h3 class="card__title">Таблички и навигация</h3>
            <p class="card__text">Режим работы, указатели, информационные стенды для офисов и ТЦ.</p>
            <p class="card__price">от [ЦЕНА] ₽</p>
          </article>

          <article class="card card--dark">
            <span class="card__num">06</span>
            <h3 class="card__title">Нужно другое?</h3>
            <p class="card__text">Расскажите задачу — предложим решение и посчитаем.</p>
            <a href="#quote" class="card__link">Оставить заявку →</a>
          </article>
        </div>
      </div>
    </section>

    <!-- ===== Как работаем ===== -->
    <section class="section" id="process">
      <div class="container">
        <h2 class="h2 h2--spaced">От заявки до монтажа — четыре шага</h2>

        <ol class="steps">
          <li class="step">
            <span class="step__num">1</span>
            <h3 class="step__title">Замер и макет</h3>
            <p class="step__text">Выезжаем на объект, фиксируем размеры фасада и готовим визуализацию.</p>
          </li>
          <li class="step">
            <span class="step__num">2</span>
            <h3 class="step__title">Согласование</h3>
            <p class="step__text">Помогаем подготовить документы для размещения вывески в соответствии с правилами города.</p>
          </li>
          <li class="step">
            <span class="step__num">3</span>
            <h3 class="step__title">Производство</h3>
            <p class="step__text">Изготавливаем на собственном производстве, контролируем качество на каждом этапе. [СРОК]</p>
          </li>
          <li class="step">
            <span class="step__num">4</span>
            <h3 class="step__title">Монтаж и гарантия</h3>
            <p class="step__text">Монтируем, подключаем, сдаём объект. Гарантия [СРОК ГАРАНТИИ].</p>
          </li>
        </ol>
      </div>
    </section>

    <!-- ===== Наши работы ===== -->
    <section class="section" id="works">
      <div class="container">
        <div class="section__head">
          <h2 class="h2">Наши работы</h2>
          <a href="#works" class="link-underline">Все проекты →</a>
        </div>

        <div class="grid grid--works">
          <!-- Вместо .work__img вставьте: <img class="work__img" src="images/work-1.webp" alt="..." loading="lazy" width="600" height="640"> -->
          <figure class="work">
            <div class="work__img work__img--placeholder">[Фото объекта]</div>
            <figcaption class="work__caption"><strong>[Название объекта]</strong><span>Объёмные буквы · [район]</span></figcaption>
          </figure>
          <figure class="work">
            <div class="work__img work__img--placeholder">[Фото объекта]</div>
            <figcaption class="work__caption"><strong>[Название объекта]</strong><span>Световой короб · [район]</span></figcaption>
          </figure>
          <figure class="work">
            <div class="work__img work__img--placeholder">[Фото объекта]</div>
            <figcaption class="work__caption"><strong>[Название объекта]</strong><span>Неон · [район]</span></figcaption>
          </figure>
          <figure class="work">
            <div class="work__img work__img--placeholder">[Фото объекта]</div>
            <figcaption class="work__caption"><strong>[Название объекта]</strong><span>Фасадная панель · [район]</span></figcaption>
          </figure>
        </div>
      </div>
    </section>

    <!-- ===== Текстовый блок 2 ===== -->
    <section class="section section--text">
      <div class="container">
        <div class="text-block">
          <h2 class="h2">[Заголовок второго текстового блока]</h2>
          <h3 class="text-block__sub">[Подзаголовок — одна-две строки, уточняющие заголовок]</h3>
          <p class="text-block__body">[Основной текст блока: абзац о материалах, гарантиях, согласовании или других важных для клиента вещах.]</p>
        </div>
      </div>
    </section>

    <!-- ===== Отзывы ===== -->
    <section class="section">
      <div class="container">
        <h2 class="h2 h2--spaced">Что говорят клиенты</h2>
        <div class="grid grid--reviews">
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

    <!-- ===== Форма заявки ===== -->
    <section class="section" id="quote">
      <div class="container">
        <div class="quote">
          <div class="quote__info">
            <h2 class="h2">Рассчитаем вывеску за один рабочий день</h2>
            <p class="quote__lead">Оставьте контакты и коротко опишите задачу. Менеджер свяжется с вами и уточнит детали.</p>
            <p class="quote__contacts">
<?php if ($phone !== ''): ?>
              <?= Tpl::phone($phone) ?><br>
<?php endif; ?>
              [email@domain.ru]<br>
<?php if ($address !== ''): ?>
              <?= Tpl::e($address) ?><br>
<?php endif; ?>
              [График работы]
            </p>
          </div>

          <!-- Заявка отправляется обычным POST на /send/ (п. 4.7 ТЗ) -->
          <form class="form" id="quote-form" action="/send/" method="post" novalidate>
            <input type="hidden" name="form" value="quote">
            <input type="hidden" name="back" value="/">
            <!-- Поле-ловушка для спам-ботов: скрыто от людей, должно остаться пустым -->
            <div class="form__hp" aria-hidden="true">
              <label for="f-website">Сайт</label>
              <input id="f-website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <div class="field">
              <label for="f-name" class="field__label">Имя</label>
              <input id="f-name" name="name" type="text" class="field__input" autocomplete="name" maxlength="100" required>
              <span class="field__error" id="f-name-error" role="alert"></span>
            </div>

            <div class="field">
              <label for="f-phone" class="field__label">Телефон</label>
              <input id="f-phone" name="phone" type="tel" class="field__input" autocomplete="tel" inputmode="tel" placeholder="+7 (___) ___-__-__" maxlength="40" required>
              <span class="field__error" id="f-phone-error" role="alert"></span>
            </div>

            <div class="field">
              <label for="f-type" class="field__label">Что нужно изготовить</label>
              <select id="f-type" name="type" class="field__input">
                <option>Объёмные буквы</option>
                <option>Световой короб</option>
                <option>Неоновая вывеска</option>
                <option>Фасадная панель</option>
                <option>Другое</option>
              </select>
            </div>

            <button type="submit" class="btn btn--accent btn--xl">Получить расчёт</button>
            <p class="form__status" id="form-status" role="status" aria-live="polite"></p>
            <p class="form__legal">Нажимая кнопку, вы соглашаетесь с <a href="/politika/">политикой обработки персональных данных</a>.</p>
          </form>
        </div>
      </div>
    </section>

    <!-- ===== Адрес и карта ===== -->
    <section class="section section--last" id="address">
      <div class="container">
        <div class="address">
          <div class="address__info">
            <h2 class="h2 h2--bold">Как нас найти</h2>
            <dl class="address__list">
              <div class="address__item">
                <dt>Адрес</dt>
                <dd class="address__main"><?= Tpl::e($address) ?></dd>
              </div>
              <div class="address__item">
                <dt>Как добраться</dt>
                <dd>[Станция метро, ориентиры, парковка]</dd>
              </div>
              <div class="address__item">
                <dt>График работы</dt>
                <dd>[Пн–Пт, 10:00–19:00]</dd>
              </div>
              <div class="address__item">
                <dt>Связь</dt>
                <dd><?php if ($phone !== ''): ?><?= Tpl::phone($phone, 'address__phone') ?><br><?php endif; ?>[email@domain.ru]</dd>
              </div>
            </dl>
          </div>

          <!-- Карта: замените содержимое .map на iframe конструктора Яндекс.Карт:
               <iframe src="https://yandex.ru/map-widget/v1/?..." title="Карта: как нас найти" loading="lazy"></iframe> -->
          <div class="map" role="img" aria-label="Место для карты с меткой адреса">
            <svg class="map__pin" aria-hidden="true" width="56" height="72" viewBox="0 0 56 72">
              <path d="M28 2C14 2 3 13 3 27c0 18 25 43 25 43s25-25 25-43C53 13 42 2 28 2z" fill="currentColor" stroke="#1A1A1A" stroke-width="3"/>
              <circle cx="28" cy="27" r="9" fill="#1A1A1A"/>
            </svg>
            <span class="map__label">[Вставьте карту]</span>
          </div>
        </div>
      </div>
    </section>

  </main>

<?php $footerAddressId = false; $footerAboutLink = true; include $tpl . '/parts/footer.php'; ?>
