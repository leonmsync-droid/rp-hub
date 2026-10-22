// RankPortfolio hub: previous/next buttons for each category row of cards, and soft open/close for the questions.
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.category').forEach(function (row) {
      var track = row.querySelector('.track');
      var arrows = row.querySelector('.arrows');
      var prev = row.querySelector('.prev');
      var next = row.querySelector('.next');
      if (!track || !prev || !next) return;

      var step = function () {
        var card = track.querySelector('.card');
        return card ? card.getBoundingClientRect().width + 16 : track.clientWidth * 0.8;
      };
      var update = function () {
        prev.disabled = track.scrollLeft <= 4;
        next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
        // a row whose cards all fit has nothing to scroll, so its arrows step aside
        arrows.classList.toggle('idle', prev.disabled && next.disabled);
      };

      prev.addEventListener('click', function () { track.scrollBy({ left: -step(), behavior: 'smooth' }); });
      next.addEventListener('click', function () { track.scrollBy({ left: step(), behavior: 'smooth' }); });
      track.addEventListener('scroll', function () { window.requestAnimationFrame(update); }, { passive: true });
      window.addEventListener('resize', update);
      update();
    });
  });
})();

// Questions: animate each answer's height and fade instead of snapping open.
(function () {
  'use strict';
  var EASE = 'cubic-bezier(.32, .72, 0, 1)';
  document.addEventListener('DOMContentLoaded', function () {
    if (!('animate' in Element.prototype) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    document.querySelectorAll('.faq details').forEach(function (item) {
      var summary = item.querySelector('summary');
      var answer = item.querySelector('p');
      var running = null;

      summary.addEventListener('click', function (event) {
        event.preventDefault();
        var start = item.getBoundingClientRect().height;
        var opening = !item.open || item.classList.contains('closing');
        if (running) running.cancel();

        item.open = true;
        item.classList.toggle('closing', !opening);
        var end = opening ? item.scrollHeight : summary.getBoundingClientRect().height + 1; // + the 1px bottom border
        item.style.overflow = 'hidden';

        running = item.animate({ height: [start + 'px', end + 'px'] }, { duration: opening ? 520 : 420, easing: EASE });
        answer.animate({ opacity: opening ? [0, 1] : [1, 0], transform: opening ? ['translateY(-6px)', 'none'] : ['none', 'translateY(-6px)'] },
          { duration: opening ? 520 : 280, easing: EASE, fill: 'both' });

        var self = running;
        running.finished.then(function () {
          if (running !== self) return;
          running = null;
          item.style.overflow = '';
          if (!opening) {
            item.open = false;
            item.classList.remove('closing');
          }
        }, function () { /* cancelled by a newer click */ });
      });
    });
  });
})();

// Countdowns: the tab on a row's first card slides the countdown out to the right; times tick down each minute.
(function () {
  'use strict';
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };
  var tick = function () {
    var now = Date.now();
    document.querySelectorAll('.count').forEach(function (el) {
      var left = Math.floor((Number(el.dataset.starts) - now) / 60000);
      if (left <= 0) {
        el.textContent = now < Number(el.dataset.ends) ? 'Happening now' : 'Ended';
        el.classList.add('now');
        return;
      }
      el.textContent = Math.floor(left / 1440) + 'd ' + pad(Math.floor(left / 60) % 24) + 'h ' + pad(left % 60) + 'm';
    });
  };
  document.addEventListener('DOMContentLoaded', function () {
    tick();
    window.setInterval(tick, 15000);

    document.querySelectorAll('.drawer-tab').forEach(function (tab) {
      var drawer = tab.parentElement;
      var track = drawer.closest('.track');
      tab.addEventListener('click', function () {
        var open = drawer.classList.toggle('open');
        tab.setAttribute('aria-expanded', String(open));
      });
      // once open, bring the whole countdown into view if the row is narrower than the card plus drawer
      drawer.addEventListener('transitionend', function (event) {
        if (event.target !== drawer || event.propertyName !== 'width') return;
        track.dispatchEvent(new Event('scroll'));
        if (!drawer.classList.contains('open')) return;
        var over = drawer.getBoundingClientRect().right - track.getBoundingClientRect().right;
        if (over > 0) track.scrollBy({ left: over + 16, behavior: 'smooth' });
      });
    });
  });
})();
