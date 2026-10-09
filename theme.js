// Runs in <head> so the saved theme is applied before the page paints (no white flash in dark mode).
(function () {
  var t = null;
  try { t = localStorage.getItem('es-theme'); } catch (e) {}
  if (!t) t = (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', t);
  window.toggleTheme = function () {
    var n = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', n);
    try { localStorage.setItem('es-theme', n); } catch (e) {}
  };
})();
