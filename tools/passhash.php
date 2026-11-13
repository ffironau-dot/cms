<?php
/**
 * Служебный скрипт получения хеша пароля администратора (п. 3.3, 8.2 ТЗ).
 *
 * Пароль меняется только правкой include/connect.php. Этот скрипт выдаёт
 * хеш введённого пароля (password_hash), ничего не сохраняя. Полученный
 * хеш вставьте в include/connect.php вместо прежнего значения переменной
 * $r08 и УДАЛИТЕ этот файл с сервера: пока он есть, админка выводит
 * предупреждение.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$hash = '';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $p = (string) ($_POST['p'] ?? '');
    if (strlen($p) < 8) {
        $error = 'Пароль — не короче 8 символов.';
    } elseif ($p !== (string) ($_POST['p2'] ?? '')) {
        $error = 'Пароли не совпадают.';
    } else {
        $hash = password_hash($p, PASSWORD_DEFAULT);
    }
}
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Хеш пароля</title>
  <link rel="stylesheet" href="/assets/admin.css">
</head>
<body class="a-login">
  <form method="post" class="a-login__box" style="width:min(560px,calc(100% - 32px))">
    <h1 class="a-title">Хеш нового пароля</h1>
<?php if ($error): ?>
    <div class="a-alert a-alert--error"><?= $e($error) ?></div>
<?php endif; ?>
<?php if ($hash): ?>
    <div class="a-alert a-alert--success">Хеш получен. Вставьте его в <code>include/connect.php</code> как значение переменной <code>$r08</code> (в одинарных кавычках) и удалите файл passhash.php с сервера.</div>
    <input type="text" readonly value="<?= $e($hash) ?>" onclick="this.select()">
<?php endif; ?>
    <div class="a-field"><label for="p">Новый пароль</label><input id="p" type="password" name="p" required minlength="8" autocomplete="new-password"></div>
    <div class="a-field"><label for="p2">Ещё раз</label><input id="p2" type="password" name="p2" required minlength="8" autocomplete="new-password"></div>
    <button type="submit" class="a-btn">Получить хеш</button>
    <p class="a-hint">Пароль и хеш нигде не сохраняются.</p>
  </form>
</body>
</html>
