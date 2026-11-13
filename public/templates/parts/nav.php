<?php
/**
 * Верхнее меню (п. 4.4): два уровня. Пункт с подпунктами выводится
 * кнопкой, раскрывающей выпадающий блок; если у него есть ссылка, она
 * выводится в выпадающем блоке первой строкой. Подсветка текущей
 * страницы — класс nav__link--active.
 *
 * @var array $menu
 */
use Site\Tpl;
$chevron = '<svg class="nav__chevron" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>';
$titleAttr = static fn(array $i): string => $i['link_title'] !== null && $i['link_title'] !== '' ? ' title="' . Tpl::e($i['link_title']) . '"' : '';
?>
        <nav class="nav" id="main-nav" aria-label="Основное меню">
<?php foreach ($menu as $item): ?>
<?php if ($item['children']): $subId = 'submenu-' . (int) $item['id']; ?>
          <div class="nav__item">
            <button type="button" class="<?= Tpl::navClass('nav__link nav__trigger', $item['active']) ?>" aria-expanded="false" aria-controls="<?= $subId ?>"<?= $titleAttr($item) ?>><?= Tpl::e($item['anchor']) ?> <?= $chevron ?></button>
            <div class="submenu" id="<?= $subId ?>" hidden>
              <div class="submenu__grid submenu__grid--flat">
                <div class="submenu__col submenu__col--wide">
<?php if ((string) $item['url'] !== ''): ?>
                  <a href="<?= Tpl::e($item['url']) ?>" class="submenu__all"<?= $titleAttr($item) ?>><?= Tpl::e($item['anchor']) ?> →</a>
<?php endif; ?>
                  <div class="submenu__links">
<?php foreach ($item['children'] as $child): ?>
                    <a href="<?= Tpl::e($child['url']) ?>" class="<?= Tpl::navClass('submenu__link', $child['active']) ?>"<?= $titleAttr($child) ?><?= $child['active'] ? ' aria-current="page"' : '' ?>><?= Tpl::e($child['anchor']) ?></a>
<?php endforeach; ?>
                  </div>
                </div>
                <div class="submenu__promo">
                  <p class="submenu__promo-label">Не знаете, что выбрать?</p>
                  <p class="submenu__promo-text">Подберём тип вывески под фасад и бюджет.</p>
                  <a href="/#quote" class="submenu__promo-link">Получить консультацию →</a>
                </div>
              </div>
            </div>
          </div>
<?php elseif ((string) $item['url'] !== ''): ?>
          <a href="<?= Tpl::e($item['url']) ?>" class="<?= Tpl::navClass('nav__link', $item['active']) ?>"<?= $titleAttr($item) ?>><?= Tpl::e($item['anchor']) ?></a>
<?php else: ?>
          <span class="<?= Tpl::navClass('nav__link', $item['active']) ?>"<?= $titleAttr($item) ?>><?= Tpl::e($item['anchor']) ?></span>
<?php endif; ?>
<?php endforeach; ?>
        </nav>
