/**
 * DOM overlay: "focus bar", the information card, the video player and the bottom toolbar.
 * Everything is built with textContent / validated URLs, never innerHTML with data.
 */
const $ = (id) => document.getElementById(id);
const isHttp = (u) => typeof u === 'string' && /^https?:\/\//i.test(u);

function setLink(el, url) {
  if (isHttp(url)) { el.href = url; el.hidden = false; } else { el.removeAttribute('href'); el.hidden = true; }
}

function haversineKm(lat1, lon1, lat2, lon2) {
  const rad = (d) => (d * Math.PI) / 180;
  const a = Math.sin(rad(lat2 - lat1) / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lon2 - lon1) / 2) ** 2;
  return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export class CardUI {
  constructor(yt, messages) {
    this.yt = yt;
    this.msg = messages;
    this.current = null;
    this.onClose = () => {};
    this.onSelectFocus = () => {};
    this.focused = null;

    this.card = $('arCard');
    this.focusBar = $('focusBar');
    this.video = $('cardVideo');

    $('cardClose').addEventListener('click', () => this.close());
    $('tbClose').addEventListener('click', () => this.close());
    $('tbInfo').addEventListener('click', () => this.toggleInfo());
    $('tbMute').addEventListener('click', () => this.toggleMute());
    $('tbYoutube').addEventListener('click', () => this.openYouTube());
    $('tbMap').addEventListener('click', () => this.openMap());
    $('btnWatch').addEventListener('click', () => this.showVideo(true));
    $('ytPlay').addEventListener('click', () => this.yt.togglePlay());
    $('ytMute').addEventListener('click', () => this.toggleMute());
    $('btnDist').addEventListener('click', () => this.showDistance());
    this.focusBar.addEventListener('click', () => { if (this.focused) this.onSelectFocus(this.focused); });
    this.refreshToolbar();
  }

  /** Called every time the "pointed at" hotspot changes. */
  showFocus(h) {
    this.focused = h;
    if (!h || this.current) { this.focusBar.hidden = true; return; }
    this.focusBar.querySelector('.ar-focus-name').textContent = h.label;
    this.focusBar.hidden = false;
    this.refreshToolbar();
  }

  open(h) {
    this.yt.destroy();
    this.current = h;
    const c = h.card;
    $('cardTitle').textContent = c.title;
    $('cardDesc').textContent = [c.description, c.extra].filter(Boolean).join(' ');
    const cat = $('cardCat');
    cat.textContent = c.category || '';
    cat.hidden = !c.category;

    const img = $('cardImg');
    if (c.image) { img.src = c.image; img.alt = c.title; img.hidden = false; } else { img.hidden = true; img.removeAttribute('src'); }

    const meta = $('cardMeta');
    meta.innerHTML = '';
    const addMeta = (icon, text) => {
      if (!text) return;
      const li = document.createElement('li');
      const i = document.createElement('i'); i.className = 'fa-solid ' + icon;
      li.append(i, document.createTextNode(' ' + text));
      meta.appendChild(li);
    };
    addMeta('fa-clock', c.opening_hours);
    addMeta('fa-ticket', c.entry_info);

    const hasVideo = h.content_type !== 'card' && !!h.video_id;
    this.video.hidden = true;
    $('ytError').hidden = true;
    $('btnWatch').hidden = !(hasVideo && h.content_type === 'both');
    setLink($('btnMap'), c.maps_url);
    setLink($('btnWeb'), c.website_url);
    setLink($('btnMore'), c.detail_url);
    $('btnDist').hidden = !(c.lat !== null && c.lng !== null && 'geolocation' in navigator);
    $('btnDist').querySelector('span').textContent = 'Show distance';

    this.focusBar.hidden = true;
    this.card.hidden = false;
    this.card.classList.add('open');
    if (hasVideo && h.content_type === 'video') this.showVideo(true);   // opened by a tap -> autoplay (muted) is allowed
    this.refreshToolbar();
  }

  showVideo(autoplay) {
    const h = this.current;
    if (!h || !h.video_id) return;
    this.video.hidden = false;
    $('btnWatch').hidden = true;
    $('ytOpen').href = 'https://www.youtube.com/watch?v=' + encodeURIComponent(h.video_id);
    this.updateVideoButtons();
    this.yt.show(h.video_id, { autoplay });
    this.refreshToolbar();
  }

  videoError() {
    const el = $('ytError');
    el.textContent = this.msg.videoError;
    el.hidden = false;
  }

  updateVideoButtons() {
    const playing = this.yt.playing;
    $('ytPlay').innerHTML = `<i class="fa-solid ${playing ? 'fa-pause' : 'fa-play'}"></i> <span>${playing ? 'Pause' : 'Play'}</span>`;
    const muted = this.yt.muted;
    $('ytMute').innerHTML = `<i class="fa-solid ${muted ? 'fa-volume-xmark' : 'fa-volume-high'}"></i> <span>${muted ? 'Unmute' : 'Mute'}</span>`;
    const tb = $('tbMute');
    tb.innerHTML = `<i class="fa-solid ${muted ? 'fa-volume-xmark' : 'fa-volume-high'}"></i>`;
    tb.setAttribute('aria-label', muted ? 'Unmute video' : 'Mute video');
  }

  toggleMute() { this.yt.toggleMute(); this.updateVideoButtons(); }

  toggleInfo() {
    if (this.current) { this.card.hidden = !this.card.hidden; this.refreshToolbar(); return; }
    if (this.focused) this.onSelectFocus(this.focused);
  }

  close() {
    this.yt.destroy();
    this.current = null;
    this.card.hidden = true;
    this.card.classList.remove('open');
    this.video.hidden = true;
    this.onClose();
    this.showFocus(this.focused);
    this.refreshToolbar();
  }

  openYouTube() {
    const h = this.current || this.focused;
    if (h && h.video_id) window.open('https://www.youtube.com/watch?v=' + encodeURIComponent(h.video_id), '_blank', 'noopener');
  }

  openMap() {
    const h = this.current || this.focused;
    if (h && isHttp(h.card.maps_url)) window.open(h.card.maps_url, '_blank', 'noopener');
  }

  showDistance() {
    const h = this.current;
    if (!h) return;
    const label = $('btnDist').querySelector('span');
    label.textContent = 'Locating…';
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const km = haversineKm(pos.coords.latitude, pos.coords.longitude, h.card.lat, h.card.lng);
        label.textContent = km < 1 ? `${Math.round(km * 1000)} m away` : `${km.toFixed(1)} km away`;
      },
      () => { label.textContent = 'Location unavailable'; },
      { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
    );
  }

  /** Enable/disable toolbar buttons depending on what is selected. */
  refreshToolbar() {
    const h = this.current || this.focused;
    const hasVideo = !!(h && h.video_id && h.content_type !== 'card');
    const hasMap = !!(h && isHttp(h.card.maps_url));
    $('tbInfo').disabled = !h;
    $('tbYoutube').disabled = !hasVideo;
    $('tbMap').disabled = !hasMap;
    $('tbMute').disabled = !(this.current && !this.video.hidden);
    $('tbClose').disabled = !this.current;
  }
}
