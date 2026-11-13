(function () {
  'use strict';

  /* ---------- Мобильное меню ---------- */
  var burger = document.querySelector('.burger');
  var nav = document.getElementById('main-nav');

  function setMenu(open) {
    if (!burger || !nav) return;
    burger.setAttribute('aria-expanded', String(open));
    burger.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
    nav.classList.toggle('is-open', open);
  }

  if (burger && nav) {
    burger.addEventListener('click', function () {
      setMenu(burger.getAttribute('aria-expanded') !== 'true');
    });

    // закрываем меню после перехода по ссылке
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) setMenu(false);
    });

    // закрываем по Esc
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') setMenu(false);
    });

    // при переходе на десктоп сбрасываем состояние
    window.matchMedia('(min-width: 900px)').addEventListener('change', function (mq) {
      if (mq.matches) setMenu(false);
    });
  }

  /* ---------- Выпадающие меню (по одному на каждый пункт с подпунктами) ---------- */
  var desktop = window.matchMedia('(min-width: 900px)');
  var canHover = window.matchMedia('(hover: hover)');

  document.querySelectorAll('.nav__trigger').forEach(function (trigger) {
    var submenu = document.getElementById(trigger.getAttribute('aria-controls'));
    if (!submenu) return;
    var closeTimer = null;

    var setSub = function (open) {
      trigger.setAttribute('aria-expanded', String(open));
      submenu.hidden = !open;
    };

    trigger.addEventListener('click', function () {
      var isOpen = trigger.getAttribute('aria-expanded') === 'true';
      // на десктопе с мышью меню уже открыто по наведению — клик его не закрывает
      setSub(desktop.matches && canHover.matches ? true : !isOpen);
    });

    var item = trigger.parentElement;
    item.addEventListener('mouseenter', function () {
      if (desktop.matches && canHover.matches) { clearTimeout(closeTimer); setSub(true); }
    });
    item.addEventListener('mouseleave', function () {
      if (desktop.matches && canHover.matches) closeTimer = setTimeout(function () { setSub(false); }, 150);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
        setSub(false);
        if (desktop.matches) trigger.focus();
      }
    });
    document.addEventListener('click', function (e) {
      if (desktop.matches && !item.contains(e.target)) setSub(false);
    });
    item.addEventListener('focusout', function (e) {
      if (desktop.matches && !item.contains(e.relatedTarget)) setSub(false);
    });
    desktop.addEventListener('change', function () { setSub(false); });
  });

  /* ---------- Слайдер на главной ---------- */
  var slider = document.getElementById('hero-slider');
  // Один слайд — стрелок, точек и счётчика нет, автопрокрутка не запускается.
  if (slider && slider.querySelectorAll('.slide').length > 1) {
    var slides = slider.querySelectorAll('.slide');
    var dots = slider.querySelectorAll('.slider__dot');
    var counter = slider.querySelector('.slider__counter');
    var current = 0;
    var timer = null;
    // Интервал автопрокрутки задаётся в админке (data-autoplay, мс; 0 — выключена)
    var AUTOPLAY_MS = Number(slider.dataset.autoplay) || 0;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function show(i) {
      current = (i + slides.length) % slides.length;
      slides.forEach(function (s, k) { s.hidden = k !== current; });
      dots.forEach(function (d, k) {
        d.classList.toggle('is-active', k === current);
        if (k === current) d.setAttribute('aria-current', 'true');
        else d.removeAttribute('aria-current');
      });
      if (counter) counter.textContent = pad(current + 1) + ' / ' + pad(slides.length);
    }

    function pad(n) { return (n < 10 ? '0' : '') + n; }

    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    function play() {
      stop();
      if (AUTOPLAY_MS && !reduce) timer = setInterval(function () { show(current + 1); }, AUTOPLAY_MS);
    }

    dots.forEach(function (d, k) {
      d.addEventListener('click', function () { show(k); play(); });
    });
    slider.querySelectorAll('.slider__arrow').forEach(function (b) {
      b.addEventListener('click', function () { show(current + Number(b.dataset.dir)); play(); });
    });

    slider.addEventListener('mouseenter', stop);
    slider.addEventListener('mouseleave', play);
    slider.addEventListener('focusin', stop);
    slider.addEventListener('focusout', play);

    slider.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') show(current - 1);
      if (e.key === 'ArrowRight') show(current + 1);
    });

    // свайп на touch-экранах
    var startX = null;
    slider.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; stop(); }, { passive: true });
    slider.addEventListener('touchend', function (e) {
      if (startX !== null) {
        var dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 50) show(current + (dx < 0 ? 1 : -1));
        startX = null;
      }
      play();
    });

    document.addEventListener('visibilitychange', function () { document.hidden ? stop() : play(); });
    play();
  }

  /* ---------- Год в подвале ---------- */
  var year = document.getElementById('year');
  if (year) year.textContent = new Date().getFullYear();

  /* ---------- Формы заявок ----------
     Формы отправляются обычным POST на /send/ (п. 4.7 ТЗ): сервер
     показывает страницу «Ваше сообщение отправлено» и через 3 секунды
     возвращает на исходную страницу. Здесь — только маска телефона и
     проверка полей перед отправкой (окончательная проверка — на сервере). */

  // Маска телефона: +7 (999) 123-45-67
  function formatPhone(value) {
    var digits = value.replace(/\D/g, '');
    if (digits[0] === '8') digits = '7' + digits.slice(1);
    if (digits[0] !== '7') digits = '7' + digits;
    digits = digits.slice(0, 11);

    var out = '+7';
    if (digits.length > 1) out += ' (' + digits.slice(1, 4);
    if (digits.length >= 4) out += ')';
    if (digits.length > 4) out += ' ' + digits.slice(4, 7);
    if (digits.length > 7) out += '-' + digits.slice(7, 9);
    if (digits.length > 9) out += '-' + digits.slice(9, 11);
    return out;
  }

  function setError(input, message) {
    var box = document.getElementById(input.id + '-error');
    if (message) {
      input.setAttribute('aria-invalid', 'true');
      if (box) input.setAttribute('aria-describedby', box.id);
    } else {
      input.removeAttribute('aria-invalid');
      input.removeAttribute('aria-describedby');
    }
    if (box) box.textContent = message || '';
  }

  document.querySelectorAll('form[action="/send/"]').forEach(function (form) {
    var nameInput = form.elements.name;
    var phoneInput = form.elements.phone;
    var submitBtn = form.querySelector('button[type="submit"]');
    if (!nameInput || !phoneInput) return;

    phoneInput.addEventListener('input', function () {
      var raw = phoneInput.value;
      phoneInput.value = raw.replace(/\D/g, '').length ? formatPhone(raw) : '';
    });

    function validate() {
      var ok = true;
      if (nameInput.value.trim().length < 2) {
        setError(nameInput, 'Введите имя');
        ok = false;
      } else {
        setError(nameInput, '');
      }
      if (phoneInput.value.replace(/\D/g, '').length !== 11) {
        setError(phoneInput, 'Введите телефон полностью');
        ok = false;
      } else {
        setError(phoneInput, '');
      }
      return ok;
    }

    form.addEventListener('submit', function (e) {
      if (!validate()) {
        e.preventDefault();
        var firstInvalid = form.querySelector('[aria-invalid="true"]');
        if (firstInvalid) firstInvalid.focus();
        return;
      }
      // защита от повторной отправки двойным щелчком
      if (submitBtn) submitBtn.disabled = true;
    });

    // при возврате на страницу кнопкой «Назад» снова разрешаем отправку
    window.addEventListener('pageshow', function () { if (submitBtn) submitBtn.disabled = false; });

    // снимаем ошибку при вводе
    nameInput.addEventListener('input', function () { setError(nameInput, ''); });
    phoneInput.addEventListener('input', function () { setError(phoneInput, ''); });
  });
})();
