/* Public site behaviour: live search, QR code, lightbox, share. Progressive enhancement only. */
(function () {
  'use strict';
  const S = window.SITE || {};

  // ----- live search suggestions (the form still works without JS) -----
  const input = document.querySelector('[data-live-search]');
  const box = document.getElementById('searchResults');
  if (input && box) {
    let timer, controller;
    const hide = () => { box.hidden = true; box.innerHTML = ''; };
    const group = (title, items, href) => {
      if (!items.length) return;
      const h = document.createElement('h3'); h.textContent = title; box.appendChild(h);
      items.forEach((it) => {
        const a = document.createElement('a'); a.href = href(it); a.setAttribute('role', 'option');
        a.textContent = it.name;
        if (it.destination_name) { const s = document.createElement('small'); s.textContent = ' · ' + it.destination_name; a.appendChild(s); }
        box.appendChild(a);
      });
    };
    input.addEventListener('input', () => {
      clearTimeout(timer);
      const q = input.value.trim();
      if (q.length < 2) return hide();
      timer = setTimeout(async () => {
        controller?.abort(); controller = new AbortController();
        try {
          const res = await fetch(S.searchUrl + '?q=' + encodeURIComponent(q), { signal: controller.signal });
          const data = await res.json();
          box.innerHTML = '';
          group('Destinations', data.destinations || [], (d) => S.destinationUrl + encodeURIComponent(d.slug));
          group('Attractions', data.attractions || [], (a) => S.attractionUrl + encodeURIComponent(a.slug));
          group('Categories', data.categories || [], (c) => S.attractionsUrl + encodeURIComponent(c.name));
          box.hidden = box.children.length === 0;
        } catch (e) { if (e.name !== 'AbortError') hide(); }
      }, 220);
    });
    document.addEventListener('click', (e) => { if (!box.contains(e.target) && e.target !== input) hide(); });
    input.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(); });
  }

  // ----- QR code (URL is generated server-side from the current host) -----
  const qr = document.getElementById('qrcode');
  if (qr) {
    const draw = () => {
      if (!window.QRCode) return false;
      qr.innerHTML = '';
      new window.QRCode(qr, { text: qr.dataset.url, width: 180, height: 180, correctLevel: window.QRCode.CorrectLevel.M });
      return true;
    };
    if (!draw()) { let n = 0; const t = setInterval(() => { if (draw() || ++n > 40) clearInterval(t); }, 150); }
  }

  // ----- lightbox -----
  const modalEl = document.getElementById('lightboxModal');
  if (modalEl && window.bootstrap) {
    const modal = new window.bootstrap.Modal(modalEl);
    document.querySelectorAll('[data-lightbox]').forEach((a) => {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        const img = document.getElementById('lightboxImg');
        img.src = a.getAttribute('href'); img.alt = a.dataset.caption || '';
        document.getElementById('lightboxCaption').textContent = a.dataset.caption || '';
        modal.show();
      });
    });
  }

  // ----- share -----
  document.querySelectorAll('[data-share]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const data = { title: btn.dataset.title, url: btn.dataset.url };
      try {
        if (navigator.share) await navigator.share(data);
        else { await navigator.clipboard.writeText(data.url); btn.textContent = 'Link copied'; }
      } catch (e) { /* user cancelled */ }
    });
  });
})();
