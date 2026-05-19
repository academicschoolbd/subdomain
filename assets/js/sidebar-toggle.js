(function(){
  var toggle = document.querySelector('[data-sidebar-toggle]');
  var backdrop = document.querySelector('[data-sidebar-backdrop]');
  var root = document.querySelector('[data-admin-root]') || document.querySelector('[data-dash-root]');
  if (!toggle || !root) return;
  function open() { root.classList.add('mobile-sidebar-open'); }
  function close() { root.classList.remove('mobile-sidebar-open'); }
  toggle.addEventListener('click', function() {
    root.classList.contains('mobile-sidebar-open') ? close() : open();
  });
  if (backdrop) backdrop.addEventListener('click', close);
  root.querySelectorAll('.dash-nav-item').forEach(function(btn) {
    btn.addEventListener('click', close);
  });
})();
