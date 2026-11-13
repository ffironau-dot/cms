(function () {
  'use strict';

  /* ---------- Галерея ----------
     Миниатюры (.thumb) содержат data-src и data-alt; по нажатию
     основное фото подменяется. Фото выводит CMS из админки. */
  var thumbsBox = document.getElementById('pd-thumbs');
  var mainBox = document.getElementById('pd-main');
  if (thumbsBox && mainBox) {
    var thumbs = Array.prototype.slice.call(thumbsBox.querySelectorAll('.thumb'));

    var showPhoto = function (btn) {
      thumbs.forEach(function (t) { t.setAttribute('aria-pressed', String(t === btn)); });
      var img = mainBox.querySelector('.pd__img');
      if (!img) {
        img = document.createElement('img');
        img.className = 'pd__img';
        mainBox.appendChild(img);
      }
      img.src = btn.dataset.src;
      img.alt = btn.dataset.alt || '';
    };

    thumbsBox.addEventListener('click', function (e) {
      var btn = e.target.closest('.thumb');
      if (btn && btn.dataset.src) showPhoto(btn);
    });
  }

  /* ---------- Всплывающая форма «Заказать» ----------
     Используется <dialog>: Esc и кнопка «×» закрывают окно, щелчок по
     затемнённому фону — тоже. Отправка — обычный POST на /send/. */
  var openBtn = document.getElementById('order-open');
  var dialog = document.getElementById('order-dialog');
  if (openBtn && dialog) {
    openBtn.addEventListener('click', function () {
      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
      } else {
        dialog.setAttribute('open', '');
      }
      var first = dialog.querySelector('input[name="name"]');
      if (first) first.focus();
    });

    var close = function () {
      if (typeof dialog.close === 'function') dialog.close(); else dialog.removeAttribute('open');
      openBtn.focus();
    };

    dialog.addEventListener('click', function (e) {
      if (e.target.closest('[data-close]') || e.target === dialog) close();
    });
  }
})();
