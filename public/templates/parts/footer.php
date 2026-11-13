<?php
/**
 * Подвал сайта: логотип (п. 4.2), копирайт, реквизиты (статичные).
 *
 * @var bool $footerAddressId Ставить id="address" (на внутренних страницах
 *                            верхняя ссылка адреса ведёт к подвалу)
 */
use Site\Tpl;
?>
  <footer class="footer"<?= !empty($footerAddressId) ? ' id="address"' : '' ?>>
    <div class="container footer__inner">
      <span class="footer__logo"><?= Tpl::logo($logoUrl, $logoAlt) ?></span>
      <span>© <span id="year"><?= date('Y') ?></span> [Название компании]. Изготовление вывесок в Москве.</span>
<?php if (!empty($footerAboutLink)): ?>
      <a href="/o-kompanii/">О компании</a>
<?php endif; ?>
      <span>[ИНН / ОГРН]</span>
    </div>
  </footer>

</body>
</html>
