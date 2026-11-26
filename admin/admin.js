// Admin-Center: Rückfrage vor heiklen Aktionen (data-bestaetigen), Suche bei Enter.
document.addEventListener('click', function (e) {
  var el = e.target.closest('[data-bestaetigen]');
  if (el && !window.confirm(el.getAttribute('data-bestaetigen'))) e.preventDefault();
});
