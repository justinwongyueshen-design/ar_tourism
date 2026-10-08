/**
 * Visual AR poster editor.
 * Hotspots are stored NORMALISED (x, y, w, h in 0..1 of the poster image, origin top-left),
 * so the same layout works on any screen size and maps directly onto the MindAR target.
 */
(function () {
  'use strict';
  const data = JSON.parse(document.getElementById('editorData').textContent);
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const $ = (id) => document.getElementById(id);
  const stage = $('stage');
  const MIN = 0.04;

  let keySeq = 0;
  let hotspots = data.hotspots.map((h) => ({ ...h, key: ++keySeq }));
  let selected = null;
  let dirty = false;

  const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
  const r6 = (v) => Math.round(v * 1e6) / 1e6;
  const byKey = (k) => hotspots.find((h) => h.key === k);

  // attraction <select>
  const pAttr = $('pAttr');
  pAttr.innerHTML = '<option value="">— none (custom text only) —</option>';
  data.attractions.forEach((a) => { const o = document.createElement('option'); o.value = a.id; o.textContent = a.label; pAttr.appendChild(o); });

  function markDirty() { dirty = true; }
  function toast(type, text) {
    const m = $('editorMsg'); m.className = 'alert alert-' + type; m.textContent = text;
    if (type === 'success') setTimeout(() => { m.className = 'alert d-none'; }, 3500);
  }

  function add(cx, cy) {
    const w = 0.22, h = 0.14;
    const hs = { key: ++keySeq, id: null, label: '', attraction_id: null, x: clamp(cx - w / 2, 0, 1 - w), y: clamp(cy - h / 2, 0, 1 - h), w, h,
      z: hotspots.length, content_type: 'both', video_url: '', custom_content: '' };
    hs.label = 'Hotspot ' + (hotspots.length + 1);
    hotspots.push(hs);
    select(hs.key);
    markDirty();
  }

  function remove(key) {
    hotspots = hotspots.filter((h) => h.key !== key);
    if (selected === key) selected = null;
    markDirty();
    render();
  }

  function select(key) { selected = key; render(); }

  // ----- rendering -----
  function render() {
    stage.querySelectorAll('.hs').forEach((n) => n.remove());
    hotspots.forEach((h, i) => {
      const el = document.createElement('div');
      el.className = 'hs' + (h.key === selected ? ' selected' : '');
      el.dataset.key = h.key;
      el.tabIndex = 0;
      el.setAttribute('role', 'button');
      el.setAttribute('aria-label', 'Hotspot ' + (i + 1) + ': ' + (h.label || 'unnamed'));
      el.style.cssText = `left:${h.x * 100}%;top:${h.y * 100}%;width:${h.w * 100}%;height:${h.h * 100}%;z-index:${1 + h.z}`;
      el.innerHTML = '<span class="hs-n"></span><span class="hs-l"></span>' + ['nw', 'ne', 'sw', 'se'].map((d) => `<span class="h ${d}" data-dir="${d}"></span>`).join('');
      el.querySelector('.hs-n').textContent = String(i + 1);
      el.querySelector('.hs-l').textContent = h.label;
      stage.appendChild(el);
    });
    renderList();
    renderProps();
  }

  function renderList() {
    const list = $('hsList');
    list.innerHTML = '';
    hotspots.forEach((h, i) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'list-group-item list-group-item-action' + (h.key === selected ? ' active' : '');
      b.textContent = (i + 1) + '. ' + (h.label || 'Unnamed');
      b.addEventListener('click', () => select(h.key));
      list.appendChild(b);
    });
    $('hsCount').textContent = String(hotspots.length);
  }

  function renderProps() {
    const h = selected ? byKey(selected) : null;
    $('noSel').hidden = !!h;
    $('propsForm').hidden = !h;
    if (!h) return;
    if (document.activeElement !== $('pLabel')) $('pLabel').value = h.label;
    pAttr.value = h.attraction_id === null ? '' : String(h.attraction_id);
    $('pType').value = h.content_type;
    if (document.activeElement !== $('pVideo')) $('pVideo').value = h.video_url || '';
    if (document.activeElement !== $('pContent')) $('pContent').value = h.custom_content || '';
    $('pZ').value = h.z;
    $('pPos').textContent = `x ${(h.x * 100).toFixed(1)}%  y ${(h.y * 100).toFixed(1)}%  w ${(h.w * 100).toFixed(1)}%  h ${(h.h * 100).toFixed(1)}%`;
  }

  // light update during drag (avoid rebuilding DOM)
  function applyGeometry(h) {
    const el = stage.querySelector(`.hs[data-key="${h.key}"]`);
    if (!el) return;
    el.style.left = h.x * 100 + '%'; el.style.top = h.y * 100 + '%';
    el.style.width = h.w * 100 + '%'; el.style.height = h.h * 100 + '%';
    $('pPos').textContent = `x ${(h.x * 100).toFixed(1)}%  y ${(h.y * 100).toFixed(1)}%  w ${(h.w * 100).toFixed(1)}%  h ${(h.h * 100).toFixed(1)}%`;
  }

  // ----- pointer interaction -----
  let drag = null;
  stage.addEventListener('pointerdown', (e) => {
    const el = e.target.closest('.hs');
    if (!el) return;
    e.preventDefault();
    const h = byKey(Number(el.dataset.key));
    const dir = e.target.dataset.dir || 'move';
    if (selected !== h.key) { selected = h.key; render(); }
    const target = stage.querySelector(`.hs[data-key="${h.key}"]`);
    target.setPointerCapture(e.pointerId);
    drag = { key: h.key, dir, sx: e.clientX, sy: e.clientY, orig: { x: h.x, y: h.y, w: h.w, h: h.h }, rect: stage.getBoundingClientRect(), moved: false, el: target };
  });
  stage.addEventListener('pointermove', (e) => {
    if (!drag) return;
    const h = byKey(drag.key);
    const dx = (e.clientX - drag.sx) / drag.rect.width;
    const dy = (e.clientY - drag.sy) / drag.rect.height;
    if (Math.abs(dx) + Math.abs(dy) > 0.002) drag.moved = true;
    const o = drag.orig;
    if (drag.dir === 'move') {
      h.x = r6(clamp(o.x + dx, 0, 1 - o.w));
      h.y = r6(clamp(o.y + dy, 0, 1 - o.h));
    } else {
      let x1 = o.x, y1 = o.y, x2 = o.x + o.w, y2 = o.y + o.h;
      if (drag.dir.includes('w')) x1 = clamp(o.x + dx, 0, x2 - MIN);
      if (drag.dir.includes('e')) x2 = clamp(o.x + o.w + dx, x1 + MIN, 1);
      if (drag.dir.includes('n')) y1 = clamp(o.y + dy, 0, y2 - MIN);
      if (drag.dir.includes('s')) y2 = clamp(o.y + o.h + dy, y1 + MIN, 1);
      h.x = r6(x1); h.y = r6(y1); h.w = r6(x2 - x1); h.h = r6(y2 - y1);
    }
    applyGeometry(h);
  });
  const endDrag = () => { if (drag && drag.moved) markDirty(); drag = null; };
  stage.addEventListener('pointerup', endDrag);
  stage.addEventListener('pointercancel', endDrag);

  // click on empty poster area = add hotspot
  stage.addEventListener('click', (e) => {
    if (e.target.closest('.hs')) return;
    if (!$('clickAdd').checked) { selected = null; render(); return; }
    const rect = stage.getBoundingClientRect();
    add((e.clientX - rect.left) / rect.width, (e.clientY - rect.top) / rect.height);
  });

  // keyboard: nudge / delete
  stage.addEventListener('keydown', (e) => {
    const el = e.target.closest('.hs');
    if (!el) return;
    const h = byKey(Number(el.dataset.key));
    const step = e.shiftKey ? 0.02 : 0.005;
    let handled = true;
    if (e.key === 'ArrowLeft') h.x = r6(clamp(h.x - step, 0, 1 - h.w));
    else if (e.key === 'ArrowRight') h.x = r6(clamp(h.x + step, 0, 1 - h.w));
    else if (e.key === 'ArrowUp') h.y = r6(clamp(h.y - step, 0, 1 - h.h));
    else if (e.key === 'ArrowDown') h.y = r6(clamp(h.y + step, 0, 1 - h.h));
    else if (e.key === 'Delete' || e.key === 'Backspace') { remove(h.key); return; }
    else handled = false;
    if (handled) { e.preventDefault(); markDirty(); applyGeometry(h); }
  });

  // ----- property inputs -----
  function bindProp(id, apply, evt = 'input') {
    $(id).addEventListener(evt, () => {
      const h = selected ? byKey(selected) : null;
      if (!h) return;
      apply(h, $(id).value);
      markDirty();
      const el = stage.querySelector(`.hs[data-key="${h.key}"] .hs-l`);
      if (el) el.textContent = h.label;
      renderList();
    });
  }
  bindProp('pLabel', (h, v) => { h.label = v; });
  bindProp('pVideo', (h, v) => { h.video_url = v; });
  bindProp('pContent', (h, v) => { h.custom_content = v; });
  bindProp('pType', (h, v) => { h.content_type = v; }, 'change');
  bindProp('pZ', (h, v) => { h.z = clamp(parseInt(v || '0', 10) || 0, 0, 99); const el = stage.querySelector(`.hs[data-key="${h.key}"]`); if (el) el.style.zIndex = 1 + h.z; });
  pAttr.addEventListener('change', () => {
    const h = selected ? byKey(selected) : null;
    if (!h) return;
    h.attraction_id = pAttr.value === '' ? null : Number(pAttr.value);
    const a = data.attractions.find((x) => x.id === h.attraction_id);
    if (a && (!h.label || /^Hotspot \d+$/.test(h.label))) h.label = a.name;   // auto-name
    markDirty();
    render();
  });

  $('btnDelete').addEventListener('click', () => { if (selected && confirm('Delete this hotspot?')) remove(selected); });
  $('btnAdd').addEventListener('click', () => add(0.5, 0.5));

  // ----- save -----
  $('btnSave').addEventListener('click', async () => {
    const btn = $('btnSave');
    btn.disabled = true;
    try {
      const payload = { poster_id: data.posterId, hotspots: hotspots.map((h) => ({
        attraction_id: h.attraction_id, label: h.label, x: h.x, y: h.y, w: h.w, h: h.h, z: h.z,
        content_type: h.content_type, video_url: h.video_url, custom_content: h.custom_content })) };
      const res = await fetch(data.saveUrl, { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(payload) });
      const json = await res.json().catch(() => ({ ok: false, error: 'Unexpected server response.' }));
      if (!json.ok) throw new Error(json.error || 'Save failed.');
      hotspots.forEach((h, i) => { h.id = json.ids[i]; });
      dirty = false;
      toast('success', 'Saved ' + json.count + ' hotspot(s).');
    } catch (err) {
      toast('danger', 'Could not save: ' + err.message);
    } finally { btn.disabled = false; }
  });

  window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  render();
})();
