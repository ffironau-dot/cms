<?php
/**
 * Форма входа в админку.
 * @var ?string $error
 * @var array $flash
 * @var string $login
 */
use Admin\Form;
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Вход — управление сайтом</title>
  <link rel="stylesheet" href="/assets/admin.css">
</head>
<body class="a-login">
  <form class="a-login__box" method="post" action="<?= CMS_ADMIN_PREFIX ?>/login">
    <h1 class="a-title">Вход</h1>
<?php foreach ($flash as [$type, $msg]): ?>
    <div class="a-alert a-alert--<?= Form::e($type) ?>"><?= Form::e($msg) ?></div>
<?php endforeach; ?>
<?php if ($error): ?>
    <div class="a-alert a-alert--error"><?= Form::e($error) ?></div>
<?php endif; ?>
    <?= Form::token() ?>
    <?= Form::text('login', 'Логин', $login, ['required' => true]) ?>
    <?= Form::text('password', 'Пароль', '', ['type' => 'password', 'required' => true]) ?>
    <button type="submit" class="a-btn">Войти</button>
  </form>
</body>
</html>
