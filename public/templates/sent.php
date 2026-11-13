<?php
/**
 * Страница после отправки заявки (п. 4.7): «Ваше сообщение отправлено»
 * и автоматический возврат через 3 секунды на страницу, с которой
 * отправлена заявка. При ошибке — список ошибок и ссылка назад
 * (без автоматического возврата).
 *
 * @var string $back   Адрес возврата (путь на этом сайте)
 * @var array  $errors
 */
use Site\Tpl;
$seo->canonical = '';
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
<?php if (!$errors): ?>
  <meta http-equiv="refresh" content="3;url=<?= Tpl::e($back) ?>">
<?php endif; ?>
  <title><?= Tpl::e($seo->title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="sent-page">
  <div class="dark sent">
    <div class="container sent__inner">
      <a href="/" class="logo"><?= Tpl::logo($logoUrl, $logoAlt) ?></a>
<?php if (!$errors): ?>
      <h1 class="hero__title">Ваше сообщение отправлено</h1>
      <p class="hero__lead">Спасибо! Мы свяжемся с вами в ближайшее время. Через 3 секунды вы вернётесь на страницу, с которой отправили заявку.</p>
      <p><a href="<?= Tpl::e($back) ?>" class="btn btn--accent btn--lg">Вернуться сейчас</a></p>
      <script>setTimeout(function () { location.replace(<?= json_encode($back, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>); }, 3000);</script>
<?php else: ?>
      <h1 class="hero__title">Заявка не отправлена</h1>
<?php foreach ($errors as $err): ?>
      <p class="hero__lead"><?= Tpl::e($err) ?></p>
<?php endforeach; ?>
<?php if ($phone !== ''): ?>
      <p class="hero__lead">Телефон: <?= Tpl::phone($phone) ?></p>
<?php endif; ?>
      <p><a href="<?= Tpl::e($back) ?>" class="btn btn--accent btn--lg">Вернуться назад</a></p>
<?php endif; ?>
    </div>
  </div>
</body>
</html>
