/* Side menu: opens from the right on small screens and closes again. */
(function () {
  var nav = document.querySelector('.nav');
  var toggle = document.querySelector('.menu-toggle');
  var backdrop = document.querySelector('.nav-backdrop');
  var closeButton = document.querySelector('.nav-close');
  if (!nav || !toggle) { return; }

  function setOpen(open) {
    nav.classList.toggle('open', open);
    document.body.classList.toggle('nav-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  toggle.addEventListener('click', function () {
    setOpen(!nav.classList.contains('open'));
  });
  if (backdrop) { backdrop.addEventListener('click', function () { setOpen(false); }); }
  if (closeButton) { closeButton.addEventListener('click', function () { setOpen(false); }); }
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') { setOpen(false); }
  });
  // If the window becomes wide again, the normal bar returns, so reset.
  window.addEventListener('resize', function () {
    if (window.innerWidth > 1320) { setOpen(false); }
  });
})();
