/* ── NAV ──────────────────────────────────────────────────────── */
(function () {
  const header = document.querySelector('.site-header');
  const hamburger = document.querySelector('.hamburger');
  const mobileMenu = document.querySelector('.mobile-menu');
  const mobileSubToggles = document.querySelectorAll('.mobile-sub-toggle');

  // Scroll → glass header
  function onScroll() {
    if (window.scrollY > 20) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // Hamburger toggle
  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', function () {
      const isOpen = mobileMenu.classList.toggle('open');
      hamburger.classList.toggle('open', isOpen);
      hamburger.setAttribute('aria-expanded', isOpen);
    });
  }

  // Mobile sub-menu toggles
  mobileSubToggles.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const sub = this.closest('.mobile-nav-item').querySelector('.mobile-sub');
      if (!sub) return;
      const isOpen = sub.classList.toggle('open');
      this.classList.toggle('open', isOpen);
    });
  });

  // Close mobile menu on nav link click
  document.querySelectorAll('.mobile-nav-link').forEach(function (link) {
    link.addEventListener('click', function () {
      if (mobileMenu) mobileMenu.classList.remove('open');
      if (hamburger) hamburger.classList.remove('open');
    });
  });

  // Dropdown sub-links: scroll to section after navigation
  document.querySelectorAll('.dropdown-item[data-hash]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const hash = this.dataset.hash;
      const href = this.dataset.href;
      if (href) {
        window.location.href = href + hash;
      } else if (hash) {
        const el = document.querySelector(hash);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  document.querySelectorAll('.mobile-sub-link[data-href]').forEach(function (a) {
    // already plain <a> tags, href set directly
  });

  // Active nav link
  const currentPath = window.location.pathname.replace(/\/$/, '');
  document.querySelectorAll('.nav-link[data-page], .mobile-nav-link[data-page]').forEach(function (link) {
    const page = '/' + (link.dataset.page || '');
    const normalized = page === '/index' ? '/' : page;
    if (currentPath === normalized || currentPath === normalized.replace('.html', '')) {
      link.classList.add('active');
    }
  });
})();

/* ── SLIDERS ──────────────────────────────────────────────────── */
(function () {
  document.querySelectorAll('.slider').forEach(function (slider) {
    const slides = slider.querySelectorAll('.slide');
    const dots   = slider.querySelectorAll('.dot');
    const prev   = slider.querySelector('.slider-prev');
    const next   = slider.querySelector('.slider-next');
    let current  = 0;

    if (slides.length <= 1) {
      if (prev) prev.style.display = 'none';
      if (next) next.style.display = 'none';
      return;
    }

    function goTo(n) {
      slides[current].classList.remove('active');
      if (dots[current]) dots[current].classList.remove('active');
      current = ((n % slides.length) + slides.length) % slides.length;
      slides[current].classList.add('active');
      if (dots[current]) dots[current].classList.add('active');
    }

    if (prev) prev.addEventListener('click', function () { goTo(current - 1); });
    if (next) next.addEventListener('click', function () { goTo(current + 1); });

    dots.forEach(function (dot, i) {
      dot.addEventListener('click', function () { goTo(i); });
    });
  });
})();

/* ── FAMILIE SLIDER ───────────────────────────────────────────── */
(function () {
  const slider = document.querySelector('.familie-slider');
  if (!slider) return;

  const slides   = slider.querySelectorAll('.familie-slide');
  const dotsWrap = document.getElementById('familie-dots');
  const prev     = slider.querySelector('.familie-prev');
  const next     = slider.querySelector('.familie-next');
  let current    = 0;

  // Build dots
  slides.forEach(function (_, i) {
    const dot = document.createElement('button');
    dot.className = 'dot' + (i === 0 ? ' active' : '');
    dot.setAttribute('aria-label', 'Bild ' + (i + 1));
    dot.addEventListener('click', function () { goTo(i); });
    dotsWrap.appendChild(dot);
  });

  function goTo(n) {
    const dots = dotsWrap.querySelectorAll('.dot');
    slides[current].classList.remove('active');
    dots[current].classList.remove('active');
    current = ((n % slides.length) + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current].classList.add('active');
  }

  prev.addEventListener('click', function () { goTo(current - 1); });
  next.addEventListener('click', function () { goTo(current + 1); });
})();

/* ── LIGHTBOX ─────────────────────────────────────────────────── */
(function () {
  const lb = document.createElement('div');
  lb.className = 'lightbox';
  lb.innerHTML =
    '<button class="lightbox-close" aria-label="Schliessen">&times;</button>' +
    '<button class="lightbox-nav lightbox-prev" aria-label="Zurück"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" width="24" height="24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 15l-5-5 5-5"/></svg></button>' +
    '<img class="lightbox-img" src="" alt="">' +
    '<button class="lightbox-nav lightbox-next" aria-label="Weiter"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" width="24" height="24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 5l5 5-5 5"/></svg></button>' +
    '<p class="lightbox-counter"></p>';
  document.body.appendChild(lb);

  const lbImg     = lb.querySelector('.lightbox-img');
  const lbClose   = lb.querySelector('.lightbox-close');
  const lbPrev    = lb.querySelector('.lightbox-prev');
  const lbNext    = lb.querySelector('.lightbox-next');
  const lbCounter = lb.querySelector('.lightbox-counter');

  let images = [];
  let current = 0;

  function show(index) {
    current = ((index % images.length) + images.length) % images.length;
    lbImg.src = images[current].src;
    lbImg.alt = images[current].alt || '';
    lbCounter.textContent = (current + 1) + ' / ' + images.length;
    lbPrev.style.display = images.length > 1 ? '' : 'none';
    lbNext.style.display = images.length > 1 ? '' : 'none';
  }

  function open(sliderImgs, startIndex) {
    images = sliderImgs;
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
    show(startIndex);
  }

  function close() {
    lb.classList.remove('open');
    document.body.style.overflow = '';
    lbImg.src = '';
    images = [];
  }

  document.querySelectorAll('.room-slider-wrap').forEach(function (wrap) {
    const imgs = Array.from(wrap.querySelectorAll('img'));
    imgs.forEach(function (img, i) {
      img.addEventListener('click', function () { open(imgs, i); });
    });
  });

  document.querySelectorAll('.slider').forEach(function (slider) {
    const imgs = Array.from(slider.querySelectorAll('.slide img'));
    imgs.forEach(function (img, i) {
      img.style.cursor = 'zoom-in';
      img.addEventListener('click', function () { open(imgs, i); });
    });
  });

  lbPrev.addEventListener('click', function (e) { e.stopPropagation(); show(current - 1); });
  lbNext.addEventListener('click', function (e) { e.stopPropagation(); show(current + 1); });
  lbClose.addEventListener('click', close);
  lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
  document.addEventListener('keydown', function (e) {
    if (!lb.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') show(current - 1);
    if (e.key === 'ArrowRight') show(current + 1);
  });
})();

/* ── STAR RATING ──────────────────────────────────────────────── */
(function () {
  const container = document.querySelector('.star-rating');
  if (!container) return;

  const input   = document.getElementById('sterne-value');
  const buttons = container.querySelectorAll('.star-btn');

  function render(hover, selected) {
    buttons.forEach(function (btn, i) {
      const svg = btn.querySelector('svg');
      const val = parseInt(btn.dataset.value, 10);
      if (val <= (hover || selected)) {
        svg.classList.add('filled');
        svg.classList.remove('empty');
      } else {
        svg.classList.remove('filled');
        svg.classList.add('empty');
      }
    });
  }

  buttons.forEach(function (btn) {
    btn.addEventListener('mouseenter', function () {
      render(parseInt(this.dataset.value, 10), parseInt(input.value || 0, 10));
    });
    btn.addEventListener('mouseleave', function () {
      render(0, parseInt(input.value || 0, 10));
    });
    btn.addEventListener('click', function () {
      input.value = this.dataset.value;
      render(0, parseInt(this.dataset.value, 10));
    });
  });

  render(0, 0);
})();

/* ── REVIEW FORM ──────────────────────────────────────────────── */
(function () {
  const form = document.getElementById('review-form');
  if (!form) return;

  const reviewsList = document.getElementById('reviews-list');
  const successMsg  = document.getElementById('form-success');

  function getField(id) { return document.getElementById(id); }

  function showError(fieldId, msg) {
    const field = getField(fieldId);
    if (!field) return;
    field.classList.add('error');
    let err = field.parentElement.querySelector('.form-error');
    if (!err) {
      err = document.createElement('p');
      err.className = 'form-error';
      field.parentElement.appendChild(err);
    }
    err.textContent = msg;
  }

  function clearErrors() {
    form.querySelectorAll('.form-error').forEach(function (el) { el.remove(); });
    form.querySelectorAll('.error').forEach(function (el) { el.classList.remove('error'); });
  }

  function starSVG(filled) {
    return '<svg viewBox="0 0 16 16" class="star star--' + (filled ? 'filled' : 'empty') + '"><path d="M8 1l1.85 3.75 4.15.6-3 2.93.7 4.1L8 10.4l-3.7 1.98.7-4.1-3-2.93 4.15-.6L8 1z"/></svg>';
  }

  function createReviewCard(data) {
    const div = document.createElement('article');
    div.className = 'review-card';

    let stars = '';
    for (let i = 1; i <= 5; i++) stars += starSVG(i <= data.sterne);

    const date = new Date().toLocaleDateString('de-CH', { month: 'long', year: 'numeric' });

    div.innerHTML =
      '<div class="review-header">' +
        '<div class="stars">' + stars + '</div>' +
        '<span class="review-date">' + date + '</span>' +
      '</div>' +
      '<blockquote class="review-quote">„' + escapeHtml(data.text) + '"</blockquote>' +
      '<div class="review-footer">' +
        '<p class="review-name">' + escapeHtml(data.name) + '</p>' +
        '<p class="review-stay">' + escapeHtml(data.aufenthalt || 'Aufenthalt in Sörenberg') + '</p>' +
      '</div>';
    return div;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearErrors();

    const name      = getField('review-name').value.trim();
    const aufenthalt = getField('review-aufenthalt').value.trim();
    const sterne    = parseInt(getField('sterne-value').value || '0', 10);
    const text      = getField('review-text').value.trim();

    let valid = true;

    if (!name) { showError('review-name', 'Bitte geben Sie Ihren Namen an.'); valid = false; }
    if (!sterne) {
      const ratingGroup = form.querySelector('.form-group--stars');
      let err = ratingGroup.querySelector('.form-error');
      if (!err) {
        err = document.createElement('p');
        err.className = 'form-error';
        ratingGroup.appendChild(err);
      }
      err.textContent = 'Bitte wählen Sie eine Bewertung.';
      valid = false;
    }
    if (text.length < 20) { showError('review-text', 'Bitte schreiben Sie mindestens 20 Zeichen.'); valid = false; }

    if (!valid) return;

    const card = createReviewCard({ name: name, aufenthalt: aufenthalt, sterne: sterne, text: text });
    reviewsList.insertBefore(card, reviewsList.firstChild);

    form.reset();
    getField('sterne-value').value = '';
    // Reset stars visual
    document.querySelectorAll('.star-btn svg').forEach(function (svg) {
      svg.classList.remove('filled');
      svg.classList.add('empty');
    });

    if (successMsg) {
      successMsg.classList.add('visible');
      setTimeout(function () { successMsg.classList.remove('visible'); }, 5000);
    }

    reviewsList.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
})();
