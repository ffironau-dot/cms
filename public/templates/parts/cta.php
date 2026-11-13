<?php
/**
 * Блок «Призыв к действию» внизу внутренних страниц (статичный текст,
 * телефон — из админки). Кнопка ведёт к форме заявки на главной.
 *
 * @var string $ctaTitle, $ctaLead
 */
use Site\Tpl;
?>
    <section class="section section--last" id="quote">
      <div class="container">
        <div class="cta">
          <div class="cta__text">
            <h2 class="h2"><?= Tpl::e($ctaTitle) ?></h2>
            <p class="cta__lead"><?= Tpl::e($ctaLead) ?></p>
          </div>
          <div class="cta__actions">
            <?= Tpl::phone($phone, 'cta__phone') ?>
            <a href="/#quote" class="btn btn--accent btn--xl">Оставить заявку</a>
          </div>
        </div>
      </div>
    </section>
