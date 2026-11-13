(function () {
  'use strict';

  var app = document.getElementById('calc-app');
  if (!app) return;

  /* ---------- Настройки расчёта (демо-значения — замените на свои) ---------- */
  var RATE_PER_CM = Number(app.dataset.rate) || 40; // ₽ за 1 см высоты одной буквы

  var TYPES = {
    acryl: { label: 'акрил', mult: 1 },
    steel: { label: 'нержавеющая сталь', mult: 1.8 },
    pvc:   { label: 'ПВХ', mult: 0.7 }
  };

  var LIGHTS = {
    none: { label: 'без подсветки', add: 0 },
    face: { label: 'лицевая', add: 0.35 },
    back: { label: 'контражурная', add: 0.5 }
  };

  var MONTAGE_ADD = 0.2; // монтаж: + 20% к стоимости изготовления с подсветкой
  var URGENT_ADD = 0.3;  // срочность: + 30% ко всей сумме

  var MIN_COUNT = 1;
  var MAX_COUNT = 99;

  /* ---------- Состояние ---------- */
  var state = { type: 'acryl', light: 'face', height: 40, count: 8, montage: true, urgent: false };

  /* ---------- Элементы ---------- */
  var heightInput = document.getElementById('calc-height');
  var heightOut = document.getElementById('calc-height-out');
  var countOut = document.getElementById('calc-count-out');
  var totalOut = document.getElementById('calc-total');
  var rowsOut = document.getElementById('calc-rows');
  var montageInput = document.getElementById('calc-montage');
  var urgentInput = document.getElementById('calc-urgent');

  function money(n) {
    return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽';
  }

  function addRow(label, value) {
    var wrap = document.createElement('div');
    wrap.className = 'calc__row-item';
    var dt = document.createElement('dt');
    dt.textContent = label;
    var dd = document.createElement('dd');
    dd.textContent = value;
    wrap.appendChild(dt);
    wrap.appendChild(dd);
    rowsOut.appendChild(wrap);
  }

  function render() {
    var type = TYPES[state.type];
    var light = LIGHTS[state.light];

    var base = state.count * state.height * RATE_PER_CM * type.mult;
    var lightCost = base * light.add;
    var montageCost = state.montage ? (base + lightCost) * MONTAGE_ADD : 0;
    var urgentCost = state.urgent ? (base + lightCost + montageCost) * URGENT_ADD : 0;
    var total = base + lightCost + montageCost + urgentCost;

    heightOut.textContent = state.height + ' см';
    countOut.textContent = state.count;
    totalOut.textContent = money(total);

    rowsOut.textContent = '';
    addRow('Изготовление букв (' + type.label + ')', money(base));
    if (light.add) addRow('Подсветка: ' + light.label, '+ ' + money(lightCost));
    if (state.montage) addRow('Монтаж', '+ ' + money(montageCost));
    if (state.urgent) addRow('Срочное изготовление', '+ ' + money(urgentCost));
  }

  /* ---------- Кнопки-«пилюли» (материал, подсветка) ---------- */
  app.querySelectorAll('.calc__pills').forEach(function (group) {
    var key = group.dataset.group;
    group.addEventListener('click', function (e) {
      var btn = e.target.closest('.pill');
      if (!btn) return;
      state[key] = btn.dataset.value;
      group.querySelectorAll('.pill').forEach(function (p) {
        p.setAttribute('aria-pressed', String(p === btn));
      });
      render();
    });
  });

  /* ---------- Высота ---------- */
  heightInput.addEventListener('input', function () {
    state.height = Number(heightInput.value);
    render();
  });

  /* ---------- Количество знаков ---------- */
  document.getElementById('calc-dec').addEventListener('click', function () {
    state.count = Math.max(MIN_COUNT, state.count - 1);
    render();
  });
  document.getElementById('calc-inc').addEventListener('click', function () {
    state.count = Math.min(MAX_COUNT, state.count + 1);
    render();
  });

  /* ---------- Чекбоксы ---------- */
  montageInput.addEventListener('change', function () {
    state.montage = montageInput.checked;
    render();
  });
  urgentInput.addEventListener('change', function () {
    state.urgent = urgentInput.checked;
    render();
  });

  // начальные значения берём из разметки
  state.montage = montageInput.checked;
  state.urgent = urgentInput.checked;
  state.height = Number(heightInput.value);

  render();
})();
