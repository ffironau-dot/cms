<?php
/** @var array $stats */
use Admin\Form;
?>
<div class="a-cards">
<?php foreach ($stats as $label => $n): ?>
  <div class="a-card"><span class="a-card__n"><?= (int) $n ?></span><span><?= Form::e($label) ?></span></div>
<?php endforeach; ?>
</div>
<p class="a-hint">Слева — разделы управления. Блоки шаблонов (тексты, «Как работаем», отзывы, FAQ, калькулятор и т. п.) статичны и правятся в файлах шаблонов <code>public/templates</code>.</p>
