/* Скрипты административной панели. */
(function () {
  'use strict';

  /* Условный показ блоков формы: data-show-if="поле=значение" или "поле!=значение".
     Скрытые поля отключаются (disabled), чтобы не отправлялись. */
  var blocks = document.querySelectorAll('[data-show-if]');
  function update() {
    blocks.forEach(function (block) {
      var rule = block.getAttribute('data-show-if');
      var neg = rule.indexOf('!=') !== -1;
      var parts = rule.split(neg ? '!=' : '=');
      var field = block.closest('form').elements[parts[0]];
      if (!field) return;
      var value = field.type === 'checkbox' ? (field.checked ? '1' : '') : field.value;
      var show = neg ? value !== parts[1] : value === parts[1];
      block.hidden = !show;
      block.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = !show; });
    });
  }
  if (blocks.length) {
    document.addEventListener('change', update);
    update();
  }

  /* Кнопка «копировать адрес» в разделе «Файлы». */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-copy]');
    if (!btn) return;
    var text = btn.getAttribute('data-copy');
    var done = function () {
      var old = btn.textContent;
      btn.textContent = 'скопировано ✓';
      setTimeout(function () { btn.textContent = old; }, 1500);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done);
    } else {
      var input = btn.closest('tr').querySelector('.a-copy-src');
      input.select();
      document.execCommand('copy');
      done();
    }
  });
})();
