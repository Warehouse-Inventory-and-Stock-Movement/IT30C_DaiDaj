
(function () {
  var btn = document.getElementById('menuToggle');
  var sidebar = document.getElementById('sidebar');
  if (btn && sidebar) {
    btn.addEventListener('click', function () { sidebar.classList.toggle('open'); });
    document.addEventListener('click', function (ev) {
      if (sidebar.classList.contains('open') && !sidebar.contains(ev.target) && !btn.contains(ev.target)) {
        sidebar.classList.remove('open');
      }
    });
  }
})();

document.addEventListener('submit', function (ev) {
  var msg = ev.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) ev.preventDefault();
});
