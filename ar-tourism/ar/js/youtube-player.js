/**
 * Thin wrapper around the YouTube IFrame Player API.
 * Videos always start MUTED (browser autoplay rules) and only after a visitor tap.
 */
let apiPromise = null;

function loadYouTubeApi() {
  if (window.YT && window.YT.Player) return Promise.resolve();
  if (!apiPromise) {
    apiPromise = new Promise((resolve, reject) => {
      const previous = window.onYouTubeIframeAPIReady;
      window.onYouTubeIframeAPIReady = () => { if (typeof previous === 'function') previous(); resolve(); };
      const tag = document.createElement('script');
      tag.src = 'https://www.youtube.com/iframe_api';
      tag.onerror = () => { apiPromise = null; reject(new Error('YouTube API blocked')); };
      document.head.appendChild(tag);
      setTimeout(() => { if (!(window.YT && window.YT.Player)) { apiPromise = null; reject(new Error('YouTube API timeout')); } }, 15000);
    });
  }
  return apiPromise;
}

export class YouTubeController {
  /**
   * @param {HTMLElement} mount  container that will receive the iframe
   * @param {{onError?:Function,onState?:Function}} hooks
   */
  constructor(mount, hooks = {}) {
    this.mount = mount;
    this.hooks = hooks;
    this.player = null;
    this.videoId = null;
    this.muted = true;
    this.playing = false;
  }

  async show(videoId, { autoplay = true } = {}) {
    this.destroy();
    this.videoId = videoId;
    this.muted = true;
    try { await loadYouTubeApi(); } catch (e) { this.hooks.onError?.(e); return; }
    const target = document.createElement('div');
    this.mount.appendChild(target);
    this.player = new window.YT.Player(target, {
      videoId,
      width: '100%',
      height: '100%',
      playerVars: { autoplay: autoplay ? 1 : 0, mute: 1, playsinline: 1, rel: 0, modestbranding: 1, origin: window.location.origin },
      events: {
        onReady: (ev) => { ev.target.mute(); if (autoplay) ev.target.playVideo(); },
        onStateChange: (ev) => {
          this.playing = ev.data === window.YT.PlayerState.PLAYING;
          this.hooks.onState?.(this.playing);
        },
        onError: (ev) => this.hooks.onError?.(new Error('YouTube error ' + ev.data)),
      },
    });
  }

  play()  { this.player?.playVideo?.(); }
  pause() { this.player?.pauseVideo?.(); }
  togglePlay() { this.playing ? this.pause() : this.play(); }

  setMuted(muted) {
    this.muted = muted;
    if (!this.player) return;
    if (muted) this.player.mute?.(); else this.player.unMute?.();
  }
  toggleMute() { this.setMuted(!this.muted); return this.muted; }

  destroy() {
    try { this.player?.destroy?.(); } catch (e) { /* ignore */ }
    this.player = null;
    this.playing = false;
    this.mount.innerHTML = '';
  }
}
