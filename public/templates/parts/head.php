<?php
/**
 * Общий <head> страниц сайта.
 * @var \Site\Seo $seo
 * @var array $scripts Дополнительные скрипты страницы (имена файлов в /assets)
 */
use Site\Seo;
use Site\Tpl;
$scripts = $scripts ?? [];
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= Tpl::e($seo->title) ?></title>
<?php if ($seo->description !== ''): ?>
  <meta name="description" content="<?= Tpl::e($seo->description) ?>">
<?php endif; ?>
<?php if ($seo->canonical !== ''): ?>
  <link rel="canonical" href="<?= Tpl::e(Seo::absolute($seo->canonical)) ?>">
  <meta property="og:url" content="<?= Tpl::e(Seo::absolute($seo->canonical)) ?>">
  <meta property="og:type" content="<?= Tpl::e($seo->ogType) ?>">
<?php endif; ?>
<?php if ($seo->ogTitle !== ''): ?>
  <meta property="og:title" content="<?= Tpl::e($seo->ogTitle) ?>">
<?php endif; ?>
<?php if ($seo->ogDescription !== ''): ?>
  <meta property="og:description" content="<?= Tpl::e($seo->ogDescription) ?>">
<?php endif; ?>
<?php if ($seo->ogImage !== ''): ?>
  <meta property="og:image" content="<?= Tpl::e(Seo::absolute($seo->ogImage)) ?>">
<?php endif; ?>
  <meta name="theme-color" content="#1A1A1A">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/styles.css">
  <script src="/assets/script.js" defer></script>
<?php foreach ($scripts as $s): ?>
  <script src="/assets/<?= Tpl::e($s) ?>" defer></script>
<?php endforeach; ?>
</head>
