/**
 * Compiles the poster image into a MindAR .mind file inside the admin's browser,
 * then uploads the result to api/ar/save_target.php.
 * Requires the MindAR compiler script (window.MINDAR.IMAGE.Compiler) loaded before this file.
 */
(function () {
  'use strict';
  const root = document.getElementById('compileApp');
  if (!root) return;
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const $ = (id) => document.getElementById(id);
  const btn = $('btnCompile');
  const box = $('progressBox');

  function progress(pct, label) {
    pct = Math.max(0, Math.min(100, Math.round(pct)));
    $('progressBar').style.width = pct + '%';
    $('progressPct').textContent = pct + '%';
    $('progressWrap').setAttribute('aria-valuenow', String(pct));
    if (label) $('progressLabel').textContent = label;
  }
  function message(type, text) {
    const m = $('compileMsg');
    m.className = 'alert alert-' + type;
    m.textContent = text;
  }
  function loadImage(src) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('Could not load the poster image.'));
      img.src = src;
    });
  }

  btn.addEventListener('click', async () => {
    $('compileMsg').className = 'alert d-none';
    if (!window.MINDAR || !window.MINDAR.IMAGE || !window.MINDAR.IMAGE.Compiler) {
      message('danger', 'The MindAR compiler library did not load. Check your internet connection, or run tools/fetch-vendor.php to install it locally.');
      return;
    }
    btn.disabled = true;
    box.hidden = false;
    $('progressBar').classList.add('progress-bar-animated');
    progress(0, 'Preparing AR Target');
    try {
      const img = await loadImage(root.dataset.image);
      const compiler = new window.MINDAR.IMAGE.Compiler();
      await compiler.compileImageTargets([img], (p) => progress(p * 0.95, 'Compiling image features'));
      progress(96, 'Exporting target');
      const data = await compiler.exportData();
      progress(98, 'Saving target');
      const res = await fetch(root.dataset.uploadUrl + '?poster_id=' + encodeURIComponent(root.dataset.posterId) + '&version=' + encodeURIComponent(root.dataset.version), {
        method: 'POST',
        headers: { 'Content-Type': 'application/octet-stream', 'X-CSRF-Token': csrf },
        body: data,
        credentials: 'same-origin',
      });
      const json = await res.json().catch(() => ({ ok: false, error: 'Unexpected server response.' }));
      if (!json.ok) throw new Error(json.error || 'Upload failed.');
      progress(100, 'AR Target Ready');
      $('progressBar').classList.remove('progress-bar-animated');
      $('targetStatus').innerHTML = '<span class="badge text-bg-success">READY</span>';
      $('btnPreview').hidden = false;
      btn.querySelector('span, i') && (btn.lastChild.textContent = 'Recompile target');
      message('success', 'AR Target Ready. You can preview it on a phone now.');
    } catch (err) {
      console.error(err);
      $('progressBar').classList.remove('progress-bar-animated');
      message('danger', 'Compilation failed: ' + (err && err.message ? err.message : err));
    } finally {
      btn.disabled = false;
    }
  });
})();
