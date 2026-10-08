/**
 * AR Tourism Explorer - visitor AR controller.
 *
 * Flow: START tap -> load poster data -> check camera -> lazy-load Three.js + MindAR ->
 *       start tracking -> hotspots appear on the poster -> tap one -> info card / video.
 *
 * Heavy libraries are loaded with dynamic import() AFTER the visitor taps START,
 * so the welcome screen opens instantly on slow mobile connections.
 */
import { YouTubeController } from './youtube-player.js';
import { CardUI } from './card-ui.js';
import { HotspotLayer } from './hotspot-layer.js';

const cfg = window.AR_CONFIG;
const $ = (id) => document.getElementById(id);

const screens = { start: $('arStart'), loading: $('arLoading'), error: $('arError') };
const ui = { top: $('arTop'), bottom: $('arBottom'), hint: $('arHint'), status: $('arStatus') };

let mindar = null;
let layer = null;
let card = null;
let running = false;
let hintTimer = null;
let searchTimer = null;

function showScreen(name, text) {
  for (const [key, el] of Object.entries(screens)) el.hidden = key !== name;
  if (name === 'loading') $('arLoadingText').textContent = text || 'Loading…';
  if (name === 'error') $('arErrorText').textContent = text || '';
  if (name === null) for (const el of Object.values(screens)) el.hidden = true;
}
function hideScreens() { for (const el of Object.values(screens)) el.hidden = true; }

function fail(message) {
  stopAR();
  ui.top.hidden = true;
  ui.bottom.hidden = true;
  showScreen('error', message);
}

function setStatus(state) {
  const labels = { idle: 'STARTING', searching: 'SEARCHING', found: 'POSTER DETECTED' };
  ui.status.dataset.state = state;
  ui.status.querySelector('.txt').textContent = labels[state] || '';
}

function setHint(text, { sticky = true } = {}) {
  clearTimeout(hintTimer);
  if (!text) { ui.hint.hidden = true; return; }
  ui.hint.textContent = '';
  const lines = text.split('\n');
  lines.forEach((line, i) => {
    const el = document.createElement(i === 0 ? 'strong' : 'span');
    el.textContent = line;
    ui.hint.appendChild(el);
  });
  ui.hint.hidden = false;
  if (!sticky) hintTimer = setTimeout(() => { ui.hint.hidden = true; }, 3200);
}

function onSearching() {
  setStatus('searching');
  setHint('Point your camera at the tourism poster');
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => { if (running) setHint(cfg.msg.notDetected); }, 12000);
}

function onFound() {
  clearTimeout(searchTimer);
  setStatus('found');
  setHint('Poster detected\nExplore the attractions', { sticky: false });
}

async function fetchPoster() {
  const res = await fetch(`${cfg.apiUrl}?id=${encodeURIComponent(cfg.posterId)}`, { cache: 'no-store', credentials: 'same-origin' });
  if (!res.ok) throw new Error('poster ' + res.status);
  const json = await res.json();
  if (!json.ok) throw new Error(json.error || 'poster error');
  return json;
}

/** Ask for the camera first so we can show a friendly message if it is refused. */
async function checkCamera() {
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return cfg.msg.unsupported;
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
    stream.getTracks().forEach((t) => t.stop());
    return null;
  } catch (err) {
    if (err && (err.name === 'NotAllowedError' || err.name === 'SecurityError' || err.name === 'PermissionDeniedError')) return cfg.msg.cameraDenied;
    if (err && (err.name === 'NotFoundError' || err.name === 'OverconstrainedError')) return cfg.msg.noCamera;
    return cfg.msg.cameraBusy;
  }
}

async function start() {
  if (running) return;
  showScreen('loading', 'Checking camera…');

  if (!window.isSecureContext) return fail(cfg.msg.https);

  let data;
  try { data = await fetchPoster(); } catch (e) { return fail(cfg.msg.targetMissing); }
  if (!data.poster.target_url) return fail(cfg.msg.targetMissing);
  if (!data.hotspots.length) return fail(cfg.msg.noHotspots);

  const cameraProblem = await checkCamera();
  if (cameraProblem) return fail(cameraProblem);

  showScreen('loading', 'Loading AR engine…');
  let THREE, MindARThree;
  try {
    const [three, mind] = await Promise.all([import('three'), import('mindar-image-three')]);
    THREE = three;
    MindARThree = mind.MindARThree;
  } catch (e) {
    console.error(e);
    return fail(cfg.msg.engine);
  }

  try {
    showScreen('loading', 'Preparing AR target…');
    mindar = new MindARThree({
      container: $('ar-container'),
      imageTargetSrc: data.poster.target_url,
      maxTrack: 1,
      filterMinCF: cfg.filterMinCF,     // One-Euro filter: lower = smoother, higher = more responsive
      filterBeta: cfg.filterBeta,
      uiLoading: 'no', uiScanning: 'no', uiError: 'no',   // we draw our own UI
    });
    const { renderer, scene, camera } = mindar;
    const anchor = mindar.addAnchor(0);

    const yt = new YouTubeController($('ytMount'), {
      onError: () => card && card.videoError(),
      onState: () => card && card.updateVideoButtons(),
    });
    card = new CardUI(yt, cfg.msg);

    layer = new HotspotLayer({ THREE, group: anchor.group, camera, dom: renderer.domElement, aspect: data.poster.aspect, hotspots: data.hotspots });
    layer.onFocus = (h) => card.showFocus(h);
    card.onClose = () => layer.setSelected(null);
    card.onSelectFocus = (h) => select(h);

    anchor.onTargetFound = onFound;
    anchor.onTargetLost = () => { onSearching(); card.showFocus(null); };

    bindTaps($('ar-container'));

    await mindar.start();            // opens the rear camera and loads the .mind target
    running = true;
    renderer.setAnimationLoop((now) => {
      layer.update(now);
      renderer.render(scene, camera);
    });
    hideScreens();
    ui.top.hidden = false;
    ui.bottom.hidden = false;
    onSearching();
  } catch (err) {
    console.error(err);
    const text = String((err && (err.message || err.name)) || err);
    if (/denied|NotAllowed|permission/i.test(text)) return fail(cfg.msg.cameraDenied);
    if (/\.mind|target|fetch|404/i.test(text)) return fail(cfg.msg.targetMissing);
    return fail(cfg.msg.cameraBusy);
  }
}

function select(h) {
  layer.setSelected(h.id);
  card.open(h);
}

/** Distinguish a tap from a drag/scroll, then raycast against the hotspots. */
function bindTaps(container) {
  let down = null;
  container.addEventListener('pointerdown', (e) => { down = { x: e.clientX, y: e.clientY, t: performance.now() }; });
  container.addEventListener('pointerup', (e) => {
    if (!down || !layer) return;
    const moved = Math.hypot(e.clientX - down.x, e.clientY - down.y);
    const quick = performance.now() - down.t < 600;
    down = null;
    if (moved > 12 || !quick) return;
    const hit = layer.pick(e.clientX, e.clientY);
    if (hit) select(hit);
  });
}

function stopAR() {
  running = false;
  clearTimeout(hintTimer);
  clearTimeout(searchTimer);
  try { if (mindar) { mindar.renderer.setAnimationLoop(null); mindar.stop(); } } catch (e) { /* ignore */ }
  try { layer && layer.dispose(); } catch (e) { /* ignore */ }
  mindar = null;
  layer = null;
}

// ---------- wiring ----------
const startBtn = $('btnStart');
if (startBtn) startBtn.addEventListener('click', start);
$('btnRetry').addEventListener('click', () => { stopAR(); card = null; window.location.reload(); });
$('arBack').addEventListener('click', stopAR);            // release the camera before navigating
window.addEventListener('pagehide', stopAR);
document.addEventListener('visibilitychange', () => { if (document.hidden && card && card.yt) card.yt.pause(); });
