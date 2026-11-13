<?php
/**
 * Каркас админки: навигационное меню слева, рабочая область справа (п. 3.4).
 *
 * @var string $content  HTML рабочей области
 * @var string $title    Заголовок страницы
 * @var string $section  Активный пункт меню
 * @var array  $flash    Сообщения [[тип, текст], ...]
 * @var array  $warnings Предупреждения о служебных файлах
 */
use Admin\Form;
$nav = [
    'Сайт' => [
        'dashboard' => ['', 'Панель управления'],
        'address'   => ['/address', 'Адрес в шапке'],
        'phone'     => ['/phone', 'Телефон в шапке'],
        'logo'      => ['/logo', 'Смена логотипа'],
        'menu'      => ['/menu', 'Верхнее меню'],
        'slides'    => ['/slides', 'Слайдер на главной'],
        'home'      => ['/home', 'Главная: Title и Description'],
    ],
    'Каталог' => [
        'categories' => ['/categories', 'Категории товаров'],
        'products'   => ['/products', 'Товары'],
        'import'     => ['/products/import', 'Импорт из CSV'],
    ],
    'Страницы' => [
        'pages'    => ['/pages', 'Текстовые страницы'],
        'sections' => ['/sections', 'Разделы'],
        'files'    => ['/files', 'Файлы'],
    ],
];
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= Form::e($title) ?> — управление сайтом</title>
  <link rel="stylesheet" href="/assets/admin.css">
  <script src="/assets/admin.js" defer></script>
</head>
<body class="a-body">
  <aside class="a-side">
    <a class="a-brand" href="<?= CMS_ADMIN_PREFIX ?>">Управление сайтом</a>
    <nav class="a-nav" aria-label="Разделы админки">
<?php foreach ($nav as $group => $items): ?>
      <p class="a-nav__group"><?= Form::e($group) ?></p>
<?php foreach ($items as $key => [$sub, $label]): ?>
      <a class="a-nav__link<?= $section === $key ? ' is-active' : '' ?>" href="<?= CMS_ADMIN_PREFIX . $sub ?>"><?= Form::e($label) ?></a>
<?php endforeach; ?>
<?php endforeach; ?>
    </nav>
    <div class="a-side__foot">
      <a href="/" target="_blank" rel="noopener">Открыть сайт ↗</a>
      <form method="post" action="<?= CMS_ADMIN_PREFIX ?>/logout">
        <?= Form::token() ?>
        <button type="submit" class="a-btn a-btn--ghost">Выйти</button>
      </form>
    </div>
  </aside>

  <main class="a-main">
<?php foreach ($warnings as $w): ?>
    <div class="a-alert a-alert--warning"><?= Form::e($w) ?></div>
<?php endforeach; ?>
    <h1 class="a-title"><?= Form::e($title) ?></h1>
<?php foreach ($flash as [$type, $msg]): ?>
    <div class="a-alert a-alert--<?= Form::e($type) ?>"><?= Form::e($msg) ?></div>
<?php endforeach; ?>
    <?= $content ?>
  </main>
</body>
</html>
