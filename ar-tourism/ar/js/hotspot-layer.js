/**
 * Draws the poster hotspots as transparent planes attached to the MindAR anchor.
 *
 * COORDINATE MAPPING (the important bit)
 *  - Hotspots are stored normalised: x, y, w, h in 0..1, origin = top-left of the poster, y grows DOWN.
 *  - MindAR anchor space: the poster is 1 unit wide and `aspect` (= imgHeight / imgWidth) units tall,
 *    origin = poster CENTRE, y grows UP.
 *  So for a hotspot:   px = (x + w/2) - 0.5
 *                      py = (0.5 - (y + h/2)) * aspect
 *                      size = (w, h * aspect)
 *
 * The layer also works out which hotspot the visitor is "pointing at" (closest to the screen centre)
 * and dims the others, so the screen never shows every attraction at full strength at once.
 */
export class HotspotLayer {
  constructor({ THREE, group, camera, dom, aspect, hotspots }) {
    this.THREE = THREE;
    this.group = group;
    this.camera = camera;
    this.dom = dom;
    this.aspect = aspect;
    this.raycaster = new THREE.Raycaster();
    this.tmp = new THREE.Vector3();
    this.focusId = null;
    this.selectedId = null;
    this.onFocus = () => {};
    this.items = hotspots.map((h, i) => this._build(h, i));
  }

  _build(h, index) {
    const T = this.THREE;
    const w = h.w;
    const hh = h.h * this.aspect;
    const textures = {
      normal: this._texture(h.label, w, hh, index + 1, false),
      active: this._texture(h.label, w, hh, index + 1, true),
    };
    const material = new T.MeshBasicMaterial({ map: textures.normal, transparent: true, opacity: 0, depthTest: false, depthWrite: false });
    const mesh = new T.Mesh(new T.PlaneGeometry(w, hh), material);
    mesh.position.set((h.x + h.w / 2) - 0.5, (0.5 - (h.y + h.h / 2)) * this.aspect, 0.002 + index * 0.0005);
    mesh.renderOrder = 10 + (h.z || 0) * 10 + index;   // higher z_index draws on top
    this.group.add(mesh);
    return { h, mesh, material, textures, active: false, scale: 1 };
  }

  _texture(label, w, hh, number, active) {
    const cw = 512;
    const ch = Math.max(96, Math.min(1024, Math.round(cw * hh / w)));
    const canvas = document.createElement('canvas');
    canvas.width = cw; canvas.height = ch;
    const ctx = canvas.getContext('2d');
    const r = Math.min(28, ch * 0.12);
    const pad = 6;
    const path = (x, y, width, height, radius) => {
      ctx.beginPath();
      ctx.moveTo(x + radius, y);
      ctx.arcTo(x + width, y, x + width, y + height, radius);
      ctx.arcTo(x + width, y + height, x, y + height, radius);
      ctx.arcTo(x, y + height, x, y, radius);
      ctx.arcTo(x, y, x + width, y, radius);
      ctx.closePath();
    };
    // frame
    path(pad, pad, cw - pad * 2, ch - pad * 2, r);
    ctx.fillStyle = active ? 'rgba(31,157,107,0.38)' : 'rgba(11,110,79,0.20)';
    ctx.fill();
    ctx.lineWidth = active ? 10 : 7;
    ctx.strokeStyle = active ? '#ffffff' : '#e3b93c';
    ctx.stroke();
    // number badge
    const br = Math.max(18, Math.min(34, ch * 0.11));
    ctx.beginPath(); ctx.arc(pad + br + 12, pad + br + 12, br, 0, Math.PI * 2);
    ctx.fillStyle = '#e3b93c'; ctx.fill();
    ctx.fillStyle = '#2b1d0e'; ctx.font = `700 ${Math.round(br * 1.05)}px sans-serif`;
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(String(number), pad + br + 12, pad + br + 13);
    // label chip along the bottom
    const fontPx = Math.max(20, Math.min(38, Math.round(ch * 0.12)));
    ctx.font = `600 ${fontPx}px sans-serif`;
    let text = label || '';
    const maxW = cw - 80;
    while (text.length > 1 && ctx.measureText(text).width > maxW) text = text.slice(0, -2);
    if (text !== label) text = text.trimEnd() + '…';
    const chipH = fontPx * 1.7;
    const chipW = Math.min(cw - 40, ctx.measureText(text).width + 40);
    path((cw - chipW) / 2, ch - chipH - 22, chipW, chipH, chipH / 2);
    ctx.fillStyle = 'rgba(10,20,17,0.82)'; ctx.fill();
    ctx.fillStyle = '#fff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(text, cw / 2, ch - chipH / 2 - 21);

    const tex = new this.THREE.CanvasTexture(canvas);
    if (this.THREE.SRGBColorSpace) tex.colorSpace = this.THREE.SRGBColorSpace;
    return tex;
  }

  setSelected(id) { this.selectedId = id; }

  /** Call every frame. `now` is a millisecond timestamp. */
  update(now) {
    if (!this.group.visible) {              // target lost -> content hidden by MindAR; reset focus
      if (this.focusId !== null) { this.focusId = null; this.onFocus(null); }
      return;
    }
    this._updateFocus();
    for (const it of this.items) {
      const id = it.h.id;
      let target = 0.9;
      let scale = 1;
      const isSelected = this.selectedId === id;
      const isFocus = this.focusId === id;
      if (this.selectedId !== null) {
        target = isSelected ? 1 : 0.15;
      } else if (this.focusId !== null) {
        target = isFocus ? 1 : 0.35;
        if (isFocus) scale = 1 + 0.025 * Math.sin(now * 0.006);
      }
      const active = isSelected || (this.selectedId === null && isFocus);
      if (active !== it.active) {
        it.active = active;
        it.material.map = active ? it.textures.active : it.textures.normal;
        it.material.needsUpdate = true;
      }
      it.material.opacity += (target - it.material.opacity) * 0.15;     // smooth fade
      it.scale += (scale - it.scale) * 0.2;
      it.mesh.scale.set(it.scale, it.scale, 1);
    }
  }

  _updateFocus() {
    let best = null, bestDist = Infinity, currentDist = Infinity;
    for (const it of this.items) {
      it.mesh.getWorldPosition(this.tmp);
      this.tmp.project(this.camera);
      if (this.tmp.z > 1) continue;                       // behind the camera
      const d = Math.hypot(this.tmp.x, this.tmp.y);       // distance from screen centre in NDC
      if (it.h.id === this.focusId) currentDist = d;
      if (d < bestDist) { bestDist = d; best = it; }
    }
    let next = this.focusId;
    if (!best || bestDist > 0.55) {
      next = null;
    } else if (this.focusId === null || currentDist > 0.65 || bestDist < currentDist - 0.1) {
      next = best.h.id;                                   // hysteresis avoids flicker between neighbours
    }
    if (next !== this.focusId) {
      this.focusId = next;
      this.onFocus(next === null ? null : this.items.find((i) => i.h.id === next).h);
    }
  }

  /** Returns the topmost hotspot under a screen point, or null. */
  pick(clientX, clientY) {
    if (!this.group.visible) return null;
    const rect = this.dom.getBoundingClientRect();
    const ndc = new this.THREE.Vector2(((clientX - rect.left) / rect.width) * 2 - 1, -((clientY - rect.top) / rect.height) * 2 + 1);
    this.raycaster.setFromCamera(ndc, this.camera);
    const hits = this.raycaster.intersectObjects(this.items.map((i) => i.mesh), false);
    if (!hits.length) return null;
    hits.sort((a, b) => b.object.renderOrder - a.object.renderOrder);
    return this.items.find((i) => i.mesh === hits[0].object).h;
  }

  dispose() {
    for (const it of this.items) {
      this.group.remove(it.mesh);
      it.mesh.geometry.dispose();
      it.material.dispose();
      it.textures.normal.dispose();
      it.textures.active.dispose();
    }
    this.items = [];
  }
}
