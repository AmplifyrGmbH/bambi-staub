(function () {
  'use strict';

  // ── Config (injected per inline-script in buchen/index.html) ──
  const cfg            = Object.assign({ pricing: [], minNights: 3 }, window.BOOKING_CONFIG);
  const MIN_NIGHTS     = cfg.minNights || 3;
  // Hochsaison (Mitte Januar bis Mitte März, jedes Jahr): längerer Mindestaufenthalt
  const HS             = cfg.highSeason || { from: '01-15', to: '03-15', minNights: 7 };
  // Preise kommen vom Server (config/pricing.php über /api/pricing.php)
  let CLEANING_PRICE   = 0;
  let LINEN_PER_BED    = 0;
  let ENERGY_PER_NIGHT = 0;
  let TAX              = null;
  // CHF-Betrag: ganze Franken ohne, sonst mit Rappen (19.50)
  const chf = n => Number.isInteger(n) ? String(n) : n.toFixed(2);
  const energyCost = nights => isFlat() ? 0 : Math.round(nights * ENERGY_PER_NIGHT * 100) / 100;
  const MONTHS     = 14; // Monate voraus
  const MOBILE_STEP = 3;  // Handy: so viele Monate pro Schritt

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
  function toStr(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  }

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

  // Fällt eine der Nächte von arrival bis vor departure in die Hochsaison?
  function inHighSeason(dateStr) {
    const md = dateStr.slice(5);
    return md >= HS.from && md <= HS.to;
  }
  function minNightsFor(arrival, departure) {
    for (let d = arrival; d < departure; d = addDays(d, 1)) {
      if (inHighSeason(d)) return HS.minNights;
    }
    return MIN_NIGHTS;
  }

  function fmtDE(s) {
    const [y, m, d] = s.split('-');
    return `${d}.${m}.${y}`;
  }

  const MONTH_NAMES = [
    'Januar','Februar','März','April','Mai','Juni',
    'Juli','August','September','Oktober','November','Dezember',
  ];
  const LOADED_AT = Date.now();
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
  // Rabattcode mit festem Preis pro Nacht (z. B. STAUBRABATT): keine Zusatzkosten
  const isFlat = () => !!S.discountCode && S.discountType === 'night_rate';

  function nightPrice(dateStr) {
    if (isFlat()) return S.discountValue;
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
  let $cal, $pricePreview, $formWrap, $form, $success, $stickyBar;

  function init() {
    $cal          = document.getElementById('booking-calendar');
    $pricePreview = document.getElementById('price-preview');
    $formWrap     = document.getElementById('booking-form');
    $form         = document.getElementById('inquiry-form');
    $success      = document.getElementById('booking-success');
    $stickyBar    = document.getElementById('booking-sticky-bar');

    if (!$cal) return;

    renderCalendar();
    fetchAvailability();
    bindDiscountBtn();
    bindForm();
    bindStickyBar();
    bindExtras();
  }

  // ── Fetch availability ─────────────────────────────────────────
  function fetchAvailability() {
    const getJSON = url => fetch(url).then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
    Promise.all([getJSON('/api/availability.php'), getJSON('/api/pricing.php')])
      .then(([data, prices]) => {
        cfg.pricing    = prices.pricing || [];
        CLEANING_PRICE = prices.cleaning;
        LINEN_PER_BED  = prices.linenPerBed;
        ENERGY_PER_NIGHT = prices.energyPerNight || 0;
        TAX            = prices.touristTax || null;
        document.querySelectorAll('[data-price="cleaning-amount"]').forEach(el => { el.textContent = `(CHF ${CLEANING_PRICE})`; });
        document.querySelectorAll('[data-price="linen"]').forEach(el => { el.textContent = `+CHF ${LINEN_PER_BED}/${el.dataset.unit}`; });
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

    // Handy: erst wenige Monate zeigen, damit das Formular nicht 14 Monate
    // weiter unten liegt. Gewählte Daten bleiben immer sichtbar.
    const mobile = window.matchMedia('(max-width: 579px)').matches;
    let count = MONTHS;
    if (mobile) {
      const monthIdx = s => { const d = fromStr(s); return (d.getFullYear() - start.getFullYear()) * 12 + d.getMonth() - start.getMonth(); };
      let need = MOBILE_STEP;
      if (S.arrival)   need = Math.max(need, monthIdx(S.arrival) + 1);
      if (S.departure) need = Math.max(need, monthIdx(S.departure) + 1);
      S.shownMonths = Math.max(S.shownMonths || MOBILE_STEP, need);
      count = Math.min(MONTHS, S.shownMonths);
    }

    for (let i = 0; i < count; i++) {
      const d = new Date(start.getFullYear(), start.getMonth() + i, 1);
      grid.appendChild(buildMonth(d.getFullYear(), d.getMonth(), today));
    }

    $cal.innerHTML = '';
    $cal.appendChild(grid);

    if (count < MONTHS) {
      const more = document.createElement('button');
      more.type = 'button';
      more.className = 'cal-more';
      more.textContent = 'Weitere Monate anzeigen';
      more.addEventListener('click', () => {
        S.shownMonths = Math.min(MONTHS, count + MOBILE_STEP);
        renderCalendar();
      });
      $cal.appendChild(more);
    }

    // Hover: nur CSS-Klassen aktualisieren, kein Re-Render
    grid.addEventListener('mouseover', e => {
      const btn = e.target.closest('.cal-day--available');
      if (!btn || !S.arrival || S.departure) return;
      S.hover = btn.dataset.date;
      applyHoverClasses();
    });
    grid.addEventListener('mouseleave', () => {
      if (!S.arrival || S.departure) return;
      S.hover = null;
      applyHoverClasses();
    });
  }

  function applyHoverClasses() {
    const all = $cal.querySelectorAll('.cal-day--available');
    all.forEach(btn => {
      const d = btn.dataset.date;
      const inHover = S.arrival && !S.departure && S.hover && d > S.arrival && d <= S.hover;
      btn.classList.toggle('cal-day--hover-range', !!inHover);
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
    const minN = minNightsFor(S.arrival, dateStr);
    if (nights < minN) {
      showError(minN > MIN_NIGHTS
        ? `Zwischen Mitte Januar und Mitte März beträgt der Mindestaufenthalt ${minN} Nächte.`
        : `Mindestaufenthalt: ${minN} Nächte.`);
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
      $hint.textContent = `Anreise: ${fmtDE(S.arrival)} – jetzt Abreisedatum wählen (mind. ${inHighSeason(S.arrival) ? HS.minNights : MIN_NIGHTS} Nächte).`;
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
    const extras   = getExtrasCost();
    const energy   = energyCost(nights);
    const final    = chf(total - S.discountAmt + extras + energy);

    let linesHTML = '';
    breakdown.forEach(b => {
      const n = b.nights;
      linesHTML += `<div class="price-line"><span>${n} ${n > 1 ? 'Nächte' : 'Nacht'} × CHF ${b.price}</span><span>CHF ${b.subtotal}</span></div>`;
    });

    const cleaning = getCleaningCost();
    const linen    = getLinenCost();
    let extrasHTML = energy > 0 ? `<div class="price-line"><span>Energiekosten (${nights} × CHF ${chf(ENERGY_PER_NIGHT)})</span><span>CHF ${chf(energy)}</span></div>` : '';
    if (cleaning > 0) extrasHTML += `<div class="price-line"><span>Endreinigung</span><span>CHF ${cleaning}</span></div>`;
    if (linen > 0)    extrasHTML += `<div class="price-line"><span>Bettwäsche</span><span>CHF ${linen}</span></div>`;

    let discountHTML = '';
    if (S.discountAmt > 0) {
      discountHTML = `<div class="price-line price-line--discount"><span>${S.discountLabel}</span><span>–CHF ${S.discountAmt}</span></div>`;
    }

    $pricePreview.innerHTML = `
      <div class="price-breakdown">
        <div class="price-dates">${fmtDE(S.arrival)} &rarr; ${fmtDE(S.departure)} &middot; <strong>${nights} Nächte</strong></div>
        <div class="price-lines">${linesHTML}${extrasHTML}</div>
        ${discountHTML}
        <div class="price-total"><span>Total (Anfrage)</span><span>CHF ${final}</span></div>
        <p class="price-note">Verbindlich nach Bestätigung per E-Mail.</p>
        ${isFlat() ? `<p class="price-note">Mit diesem Code reinigen Sie das Chalet selbst und bringen Ihre Bettwäsche mit (und nehmen sie wieder mit).</p>` : ''}
        ${TAX ? `<p class="price-note price-note--tax">${`Nicht im Preis enthalten: Kurtaxe CHF ${TAX.adult}.– pro Person ab ${TAX.from_age} Jahren und Nacht (Zwischensaison die Hälfte), jünger gratis. Sie bezahlen sie vor Ort beim Tourismus-Büro Sörenberg.`}</p>` : ''}
      </div>`;
    $pricePreview.style.display = 'block';
  }

  function hidePricePreview() {
    if ($pricePreview) $pricePreview.style.display = 'none';
  }

  function applyDiscount(total) {
    if (!S.discountCode || S.discountValue === 0 || isFlat()) return 0;
    if (S.discountType === 'percent') return Math.round(total * S.discountValue / 100);
    return Math.min(S.discountValue, total);
  }

  // Endreinigung: ohne Familiencode immer im Preis, mit Code reinigen die Gäste selbst
  function getCleaningCost() {
    return isFlat() ? 0 : CLEANING_PRICE;
  }

  function getLinenCost() {
    if (isFlat()) return 0;
    const el = document.querySelector('[name="linen"]:checked');
    if (!el || el.value !== 'rent') return 0;
    // Bettwäsche pro Person: Anzahl aus dem Feld «Anzahl Personen»
    const persons = Math.max(0, Math.min(4, parseInt(document.getElementById('field-persons')?.value) || 0));
    return persons * LINEN_PER_BED;
  }

  function getExtrasCost() {
    return getCleaningCost() + getLinenCost();
  }

  // ── Form show/hide ─────────────────────────────────────────────
  function showForm() {
    if (!$form) return;
    $form.querySelector('[name="arrival"]').value   = S.arrival;
    $form.querySelector('[name="departure"]').value = S.departure;
    updateStickyBar();
  }

  function hideForm() {
    updateStickyBar();
  }

  // ── Sticky Bar ─────────────────────────────────────────────────
  function updateStickyBar() {
    if (!$stickyBar) return;
    if (S.arrival && S.departure) {
      const nights = diffDays(S.arrival, S.departure);
      const total  = calcTotal(S.arrival, S.departure);
      const short = s => { const [y,m,d] = s.split('-'); return `${d}.${m}.`; };
      document.getElementById('sticky-bar-dates-text').textContent =
        `${short(S.arrival)} → ${short(S.departure)} · ${nights} Nächte`;
      const priceEl = document.getElementById('sticky-bar-price');
      if (total !== null) {
        const final = chf(total - applyDiscount(total) + getExtrasCost() + energyCost(nights));
        priceEl.innerHTML = `CHF ${final}<small>Total</small>`;
      } else {
        priceEl.textContent = '';
      }
      $stickyBar.classList.add('visible');
      document.body.classList.add('has-sticky-bar');
    } else {
      $stickyBar.classList.remove('visible');
      document.body.classList.remove('has-sticky-bar');
    }
  }

  function bindExtras() {
    // Bettwäsche (pro Person): Preis neu rechnen bei Wahl oder geänderter Personenzahl
    const refresh = () => { if (S.arrival && S.departure) { updatePricePreview(); updateStickyBar(); } };
    document.querySelectorAll('[name="linen"]').forEach(r => r.addEventListener('change', refresh));
    const personsInput = document.getElementById('field-persons');
    if (personsInput) personsInput.addEventListener('input', refresh);
  }

  function bindStickyBar() {
    const ctaBtn   = document.getElementById('sticky-bar-cta');
    const resetBtn = document.getElementById('sticky-bar-reset');
    if (ctaBtn) {
      ctaBtn.addEventListener('click', () => {
        if ($form) $form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    }
    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        S.arrival = null; S.departure = null; S.hover = null;
        hidePricePreview();
        updateStickyBar();
        renderCalendar();
        updateHint();
      });
    }
  }

  // Fester Nachtpreis: Endreinigung und Bettwäsche-Miete sind nicht wählbar, Hinweis anzeigen
  function setFlatMode(on) {
    const set = (name, val) => { const el = document.querySelector(`[name="${name}"][value="${val}"]`); if (el) el.checked = true; };
    if (on) set('linen', 'own');
    document.querySelectorAll('[name="linen"][value="rent"]').forEach(el => {
      el.disabled = on;
      const label = el.closest('label'); if (label) label.style.opacity = on ? '.45' : '';
    });
    const ci = document.getElementById('cleaning-info');
    if (ci) ci.textContent = on ? 'Die Endreinigung machen Sie selbst – mit dem Familiencode entfallen die Reinigungskosten.' : `Die Endreinigung (CHF ${CLEANING_PRICE}) ist im Preis enthalten.`;
    let n = document.getElementById('flat-note');
    if (on && !n) {
      n = document.createElement('p'); n.id = 'flat-note'; n.className = 'form-note';
      n.textContent = 'Mit diesem Code reinigen Sie das Chalet selbst und bringen Ihre Bettwäsche mit (und nehmen sie wieder mit).';
      const res = document.getElementById('discount-result'); if (res) res.after(n);
    }
    if (!on && n) n.remove();
    if (S.arrival && S.departure) { updatePricePreview(); updateStickyBar(); }
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
            S.discountLabel = data.type === 'night_rate'
              ? 'CHF ' + data.value + ' pro Nacht, ohne Zusatzkosten'
              : data.type === 'percent'
              ? data.value + '% Rabatt'
              : 'CHF ' + data.value + ' Rabatt';
            result.textContent = '\u2713 ' + S.discountLabel + ' angewendet';
            result.className   = 'discount-result discount-result--ok';
            setFlatMode(isFlat());
            if (S.arrival && S.departure) updatePricePreview();
          } else {
            S.discountCode  = null;
            S.discountValue = 0;
            S.discountAmt   = 0;
            S.discountType  = null;
            setFlatMode(false);
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
        showFormError('Bitte wählen Sie zuerst Anreise- und Abreisedatum im Kalender.');
        return;
      }

      // Pflichtfelder (Name, E-Mail, Personen, Adresse) direkt im Browser markieren
      if (!$form.reportValidity()) return;

      S.submitting        = true;
      submitBtn.disabled  = true;
      submitBtn.textContent = 'Wird gesendet…';

      const fd = new FormData($form);
      // Ausfülldauer in Sekunden, der Server lehnt zu schnelle (Bot-)Einsendungen ab
      fd.append('dauer', Math.round((Date.now() - LOADED_AT) / 1000));

      fetch('/api/submit-inquiry.php', { method: 'POST', body: fd })
        .then(r => r.text().then(t => {
          try { return JSON.parse(t); } catch (err) { throw new Error('keine JSON-Antwort'); }
        }))
        .then(data => {
          S.submitting         = false;
          submitBtn.disabled   = false;
          submitBtn.textContent = 'Anfrage absenden';

          if (data.ok) {
            onSuccess();
          } else {
            showFormError(data.message || 'Ein Fehler ist aufgetreten.');
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
