(function () {
  'use strict';

  // ── Config (injected per inline-script in buchen/index.html) ──
  const cfg        = window.BOOKING_CONFIG || { pricing: [], minNights: 3 };
  const MIN_NIGHTS = cfg.minNights || 3;
  const MONTHS     = 14; // Monate voraus

  // ── State ──────────────────────────────────────────────────────
  const S = {
    booked:         [],   // [{start:'YYYY-MM-DD', end:'YYYY-MM-DD'}, …]
    arrival:        null, // 'YYYY-MM-DD'
    departure:      null,
    hover:          null,
    loading:        true,
    discountCode:   null,
    discountType:   null,
    discountValue:  0,
    discountLabel:  '',
    discountAmt:    0,
    priceTotal:     0,
    submitting:     false,
  };

  // ── Date helpers ───────────────────────────────────────────────
  function toStr(d) { return d.toISOString().slice(0, 10); }

  function fromStr(s) {
    const [y, m, d] = s.split('-').map(Number);
    return new Date(y, m - 1, d);
  }

  function addDays(s, n) {
    const d = fromStr(s);
    d.setDate(d.getDate() + n);
    return toStr(d);
  }

  function diffDays(a, b) {
    return Math.round((fromStr(b) - fromStr(a)) / 86400000);
  }

  function fmtDE(s) {
    const [y, m, d] = s.split('-');
    return `${d}.${m}.${y}`;
  }

  const MONTH_NAMES = [
    'Januar','Februar','März','April','Mai','Juni',
    'Juli','August','September','Oktober','November','Dezember',
  ];
  const DAY_NAMES = ['Mo','Di','Mi','Do','Fr','Sa','So'];

  // ── Availability helpers ───────────────────────────────────────
  function isBooked(dateStr) {
    return S.booked.some(b => dateStr >= b.start && dateStr < b.end);
  }

  function firstBookedOnOrAfter(from) {
    let cur = from;
    for (let i = 0; i < 500; i++) {
      if (isBooked(cur)) return cur;
      cur = addDays(cur, 1);
    }
    return null;
  }

  function rangeHasBooking(arrival, departure) {
    let cur = arrival;
    while (cur < departure) {
      if (isBooked(cur)) return true;
      cur = addDays(cur, 1);
    }
    return false;
  }

  // ── Pricing helpers ────────────────────────────────────────────
  function nightPrice(dateStr) {
    const p = cfg.pricing.find(p => dateStr >= p.from && dateStr <= p.to);
    return p ? p.price : null;
  }

  function calcTotal(arrival, departure) {
    let total = 0;
    let cur   = arrival;
    while (cur < departure) {
      const p = nightPrice(cur);
      if (p === null) return null;
      total += p;
      cur = addDays(cur, 1);
    }
    return total;
  }

  function calcBreakdown(arrival, departure) {
    const lines = [];
    let cur     = arrival;
    while (cur < departure) {
      const p = nightPrice(cur);
      if (p === null) return null;
      const last = lines[lines.length - 1];
      if (last && last.price === p) {
        last.nights++;
        last.subtotal += p;
      } else {
        lines.push({ price: p, nights: 1, subtotal: p });
      }
      cur = addDays(cur, 1);
    }
    return lines;
  }

  // ── DOM refs ───────────────────────────────────────────────────
  let $cal, $pricePreview, $formWrap, $form, $success;

  function init() {
    $cal          = document.getElementById('booking-calendar');
    $pricePreview = document.getElementById('price-preview');
    $formWrap     = document.getElementById('booking-form');   // äusserer div (show/hide)
    $form         = document.getElementById('inquiry-form');   // das eigentliche <form>
    $success      = document.getElementById('booking-success');

    if (!$cal) return;

    renderCalendar(); // shows loading state
    fetchAvailability();
    bindDiscountBtn();
    bindForm();
  }

  // ── Fetch availability ─────────────────────────────────────────
  function fetchAvailability() {
    fetch('/api/availability.php')
      .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(data => {
        S.booked  = data.bookings || [];
        S.loading = false;
        renderCalendar();
        updateHint();
      })
      .catch(() => {
        S.loading = false;
        $cal.innerHTML = '<p class="cal-error">Verfügbarkeit konnte nicht geladen werden – bitte Seite neu laden.</p>';
      });
  }

  // ── Calendar rendering ─────────────────────────────────────────
  function renderCalendar() {
    if (S.loading) {
      $cal.innerHTML = '<div class="cal-loading">Verfügbarkeit wird geladen…</div>';
      return;
    }

    const today = toStr(new Date());
    const start = new Date();
    start.setDate(1);

    const grid = document.createElement('div');
    grid.className = 'cal-months-grid';

    for (let i = 0; i < MONTHS; i++) {
      const d = new Date(start.getFullYear(), start.getMonth() + i, 1);
      grid.appendChild(buildMonth(d.getFullYear(), d.getMonth(), today));
    }

    $cal.innerHTML = '';
    $cal.appendChild(grid);

    // Re-attach hover listener on the whole grid (event delegation)
    grid.addEventListener('mouseover', e => {
      const btn = e.target.closest('.cal-day--available');
      if (!btn) return;
      if (S.arrival && !S.departure) {
        S.hover = btn.dataset.date;
        renderCalendar();
      }
    });
    grid.addEventListener('mouseleave', () => {
      if (S.arrival && !S.departure) {
        S.hover = null;
        renderCalendar();
      }
    });
  }

  function buildMonth(year, month, today) {
    const wrap = document.createElement('div');
    wrap.className = 'cal-month';

    const title = document.createElement('div');
    title.className = 'cal-month-title';
    title.textContent = MONTH_NAMES[month] + ' ' + year;
    wrap.appendChild(title);

    // Weekday headers
    const header = document.createElement('div');
    header.className = 'cal-grid';
    DAY_NAMES.forEach(n => {
      const el = document.createElement('div');
      el.className = 'cal-day-name';
      el.textContent = n;
      header.appendChild(el);
    });
    wrap.appendChild(header);

    // Days
    const daysGrid = document.createElement('div');
    daysGrid.className = 'cal-grid';

    const firstDow = new Date(year, month, 1).getDay(); // 0=Sun
    const offset   = firstDow === 0 ? 6 : firstDow - 1; // Mo=0

    for (let i = 0; i < offset; i++) {
      const empty = document.createElement('div');
      empty.className = 'cal-day cal-day--empty';
      daysGrid.appendChild(empty);
    }

    const daysInMonth = new Date(year, month + 1, 0).getDate();
    for (let day = 1; day <= daysInMonth; day++) {
      const dateStr = `${year}-${String(month + 1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
      daysGrid.appendChild(buildDay(dateStr, today));
    }

    wrap.appendChild(daysGrid);
    return wrap;
  }

  function buildDay(dateStr, today) {
    const btn = document.createElement('button');
    btn.className    = 'cal-day';
    btn.textContent  = String(parseInt(dateStr.slice(8)));
    btn.dataset.date = dateStr;
    btn.type         = 'button';

    const isPast    = dateStr < today;
    const isBookedD = isBooked(dateStr);

    if (isPast) {
      btn.classList.add('cal-day--past');
      btn.disabled = true;
    } else if (isBookedD) {
      btn.classList.add('cal-day--booked');
      btn.disabled = true;
      btn.setAttribute('aria-label', 'Belegt');
    } else {
      btn.classList.add('cal-day--available');

      // Range highlighting
      const isArrival   = dateStr === S.arrival;
      const isDeparture = dateStr === S.departure;
      const inRange     = S.arrival && S.departure && dateStr > S.arrival && dateStr < S.departure;
      const inHover     = S.arrival && !S.departure && S.hover && dateStr > S.arrival && dateStr <= S.hover;

      if (isArrival)   btn.classList.add('cal-day--arrival');
      if (isDeparture) btn.classList.add('cal-day--departure');
      if (inRange)     btn.classList.add('cal-day--in-range');
      if (inHover)     btn.classList.add('cal-day--hover-range');

      btn.addEventListener('click', () => handleClick(dateStr));
    }

    return btn;
  }

  // ── Click handler ──────────────────────────────────────────────
  function handleClick(dateStr) {
    clearError();

    // No selection or complete selection → start fresh
    if (!S.arrival || (S.arrival && S.departure)) {
      S.arrival   = dateStr;
      S.departure = null;
      S.hover     = null;
      hideForm();
      hidePricePreview();
      renderCalendar();
      updateHint();
      return;
    }

    // Departure selection
    if (dateStr <= S.arrival) {
      S.arrival   = dateStr;
      S.departure = null;
      renderCalendar();
      updateHint();
      return;
    }

    const nights = diffDays(S.arrival, dateStr);
    if (nights < MIN_NIGHTS) {
      showError(`Mindestaufenthalt: ${MIN_NIGHTS} Nächte.`);
      return;
    }

    if (rangeHasBooking(S.arrival, dateStr)) {
      showError('Der gewählte Zeitraum enthält belegte Tage. Bitte Abreise vor dem nächsten belegten Tag wählen.');
      return;
    }

    S.departure = dateStr;
    renderCalendar();
    updateHint();
    updatePricePreview();
    showForm();
  }

  // ── Hint text ──────────────────────────────────────────────────
  function updateHint() {
    const $hint = document.getElementById('cal-hint');
    if (!$hint) return;
    if (!S.arrival) {
      $hint.textContent = 'Anreisedatum auswählen.';
    } else if (!S.departure) {
      $hint.textContent = `Anreise: ${fmtDE(S.arrival)} – jetzt Abreisedatum wählen (mind. ${MIN_NIGHTS} Nächte).`;
    } else {
      $hint.textContent = `${fmtDE(S.arrival)} → ${fmtDE(S.departure)} · ${diffDays(S.arrival, S.departure)} Nächte`;
    }
  }

  // ── Error display ──────────────────────────────────────────────
  function showError(msg) {
    let el = document.getElementById('cal-error-msg');
    if (!el) {
      el    = document.createElement('p');
      el.id = 'cal-error-msg';
      el.className = 'cal-error';
      $cal.after(el);
    }
    el.textContent = msg;
  }

  function clearError() {
    const el = document.getElementById('cal-error-msg');
    if (el) el.remove();
  }

  // ── Price preview ──────────────────────────────────────────────
  function updatePricePreview() {
    if (!$pricePreview || !S.arrival || !S.departure) return;

    const nights    = diffDays(S.arrival, S.departure);
    const total     = calcTotal(S.arrival, S.departure);
    const breakdown = calcBreakdown(S.arrival, S.departure);

    if (total === null || breakdown === null) {
      $pricePreview.innerHTML = '<div class="price-breakdown"><p class="price-note">Für diesen Zeitraum liegt kein Standardpreis vor – bitte direkt via info@bambi-staub.ch anfragen.</p></div>';
      $pricePreview.style.display = 'block';
      return;
    }

    S.priceTotal   = total;
    S.discountAmt  = applyDiscount(total);
    const final    = total - S.discountAmt;

    let linesHTML = '';
    breakdown.forEach(b => {
      const n = b.nights;
      linesHTML += `<div class="price-line"><span>${n} Nacht${n > 1 ? 'e' : ''} × CHF ${b.price}</span><span>CHF ${b.subtotal}</span></div>`;
    });

    let discountHTML = '';
    if (S.discountAmt > 0) {
      discountHTML = `<div class="price-line price-line--discount"><span>${S.discountLabel}</span><span>–CHF ${S.discountAmt}</span></div>`;
    }

    $pricePreview.innerHTML = `
      <div class="price-breakdown">
        <div class="price-dates">${fmtDE(S.arrival)} &rarr; ${fmtDE(S.departure)} &middot; <strong>${nights} Nächte</strong></div>
        <div class="price-lines">${linesHTML}</div>
        ${discountHTML}
        <div class="price-total"><span>Total (Anfrage)</span><span>CHF ${final}</span></div>
        <p class="price-note">Preis inkl. aller Nebenkosten. Verbindlich nach deiner Bestätigung per E-Mail.</p>
      </div>`;
    $pricePreview.style.display = 'block';
  }

  function hidePricePreview() {
    if ($pricePreview) $pricePreview.style.display = 'none';
  }

  function applyDiscount(total) {
    if (!S.discountCode || S.discountValue === 0) return 0;
    if (S.discountType === 'percent') return Math.round(total * S.discountValue / 100);
    return Math.min(S.discountValue, total); // fixed
  }

  // ── Form show/hide ─────────────────────────────────────────────
  function showForm() {
    if (!$formWrap || !$form) return;
    $form.querySelector('[name="arrival"]').value   = S.arrival;
    $form.querySelector('[name="departure"]').value = S.departure;
    $formWrap.style.display = 'block';
    setTimeout(() => $formWrap.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
  }

  function hideForm() {
    if ($formWrap) $formWrap.style.display = 'none';
  }

  // ── Discount ───────────────────────────────────────────────────
  function bindDiscountBtn() {
    const btn    = document.getElementById('discount-check-btn');
    const input  = document.getElementById('discount-input');
    const result = document.getElementById('discount-result');
    if (!btn || !input) return;

    btn.addEventListener('click', () => {
      const code = input.value.trim().toUpperCase();
      if (!code) return;

      btn.disabled    = true;
      btn.textContent = '…';
      result.textContent = '';

      const fd = new FormData();
      fd.append('code', code);

      fetch('/api/validate-discount.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          btn.disabled    = false;
          btn.textContent = 'Prüfen';
          if (data.valid) {
            S.discountCode  = code;
            S.discountType  = data.type;
            S.discountValue = data.value;
            S.discountLabel = data.type === 'percent'
              ? data.value + '% Rabatt'
              : 'CHF ' + data.value + ' Rabatt';
            result.textContent = '\u2713 ' + S.discountLabel + ' angewendet';
            result.className   = 'discount-result discount-result--ok';
            if (S.arrival && S.departure) updatePricePreview();
          } else {
            S.discountCode  = null;
            S.discountValue = 0;
            S.discountAmt   = 0;
            result.textContent = data.message || 'Ungültiger Code.';
            result.className   = 'discount-result discount-result--err';
            if (S.arrival && S.departure) updatePricePreview();
          }
        })
        .catch(() => {
          btn.disabled    = false;
          btn.textContent = 'Prüfen';
          result.textContent = 'Fehler beim Prüfen.';
          result.className   = 'discount-result discount-result--err';
        });
    });
  }

  // ── Form submission ────────────────────────────────────────────
  function bindForm() {
    if (!$form) return;

    $form.addEventListener('submit', e => {  // bindet auf <form id="inquiry-form">
      e.preventDefault();
      if (S.submitting) return;

      const submitBtn = $form.querySelector('[type="submit"]');
      clearFormError();

      // Basic client-side check
      if (!S.arrival || !S.departure) {
        showFormError('Bitte wähle zuerst Anreise- und Abreisedatum im Kalender.');
        return;
      }

      S.submitting        = true;
      submitBtn.disabled  = true;
      submitBtn.textContent = 'Wird gesendet…';

      fetch('/api/submit-inquiry.php', { method: 'POST', body: new FormData($form) })
        .then(r => r.json())
        .then(data => {
          S.submitting         = false;
          submitBtn.disabled   = false;
          submitBtn.textContent = 'Anfrage absenden';

          if (data.ok) {
            onSuccess();
          } else {
            showFormError(data.error || 'Ein Fehler ist aufgetreten.');
          }
        })
        .catch(() => {
          S.submitting         = false;
          submitBtn.disabled   = false;
          submitBtn.textContent = 'Anfrage absenden';
          showFormError('Verbindungsfehler – bitte erneut versuchen oder direkt an info@bambi-staub.ch schreiben.');
        });
    });
  }

  function showFormError(msg) {
    let el = $form.querySelector('.form-submit-error');
    if (!el) {
      el = document.createElement('p');
      el.className = 'form-submit-error cal-error';
      $form.querySelector('.form-submit-wrap').prepend(el);
    }
    el.textContent = msg;
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function clearFormError() {
    if (!$form) return;
    const el = $form.querySelector('.form-submit-error');
    if (el) el.remove();
  }

  function onSuccess() {
    hideForm();
    hidePricePreview();
    if ($success) {
      $success.style.display = 'block';
      $success.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  // ── Boot ───────────────────────────────────────────────────────
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
