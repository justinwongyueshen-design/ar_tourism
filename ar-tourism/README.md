# AR Tourism Explorer

A web-based **Augmented Reality tourism & cultural heritage information system**.
Visitors open the site on a phone, tap **START AR**, point the camera at a tourism poster, and tap highlighted
attractions to see information cards, YouTube videos and map links, with **no app to install**.

Plain PHP 8.3 + MySQL 8 (PDO) + Bootstrap 5 + vanilla JavaScript + **MindAR** (image tracking) + **Three.js**.
No frameworks. Not tied to any city: create your own destinations, attractions, posters and hotspots in the admin.

> **Honest status:** this code base was written without being executed against a live PHP/MySQL server or a phone
> camera. JavaScript files were syntax-checked and PHP files were checked for structural balance only. Follow the
> [Test checklist](#test-checklist) after installing; expect to fix a few small things on your first run.

---

## 1. Features
- Public site: home (hero, search with live suggestions, categories, featured/popular, stats, **QR code**), destinations, attractions, detail pages with gallery + lightbox, video, map and share buttons.
- Admin: dashboard, destinations / attractions / categories CRUD (search, filters, pagination, confirm-delete, image upload, gallery), users (admin/editor roles), activity logs, settings.
- **Visual hotspot editor**: click the poster to add, drag to move, drag corners to resize, assign attraction, per-hotspot video/text. Saved as **normalised 0..1 coordinates**.
- **In-browser MindAR target compilation** with progress bar; compiled `.mind` stored per poster with history.
- **AR page** (`ar.php`): rear camera, tracking status, hotspot overlays that follow the poster, "pointed-at" highlighting that dims other attractions, info card, muted YouTube player with Play/Pause/Mute/Unmute/Open-in-YouTube, map button, distance (on request), friendly error messages.
- Security: PDO prepared statements, `password_hash`, session hardening, CSRF on every POST, output escaping, login throttling (per IP and per account), strict image upload validation + re-encoding, no PHP execution in upload folders.

## 2. Requirements
- PHP 8.1+ (8.3 recommended) with `pdo_mysql`, `fileinfo`, `mbstring`; `gd` strongly recommended (resizes/re-encodes uploads)
- MySQL 8+ / MariaDB 10.4+
- Apache with `mod_rewrite`-free `.htaccess` support (`AllowOverride All`)
- **HTTPS** for phones (see §6). Camera works on `http://localhost` without it.
- Visitors: recent Chrome (Android) or Safari 16.4+ (iPhone; Three.js is loaded through an *import map*)

## 3. Install on XAMPP (Windows / macOS)
1. Copy the `ar-tourism` folder to `C:\xampp\htdocs\` (or `/Applications/XAMPP/htdocs/`).
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open `http://localhost/ar-tourism/install/`. Fill in the database (XAMPP defaults: host `localhost`, user `root`, empty password, database name e.g. `ar_tourism`) and create your admin account. Keep "Include demo data" ticked for the first run.
4. The installer creates the tables, writes `includes/config.local.php`, and locks itself. **Delete the `install/` folder afterwards.**
5. Log in at `http://localhost/ar-tourism/admin/`.

**Manual alternative:** create an empty database → import `database/database.sql` in phpMyAdmin → copy `includes/config.local.example.php` to `includes/config.local.php` and edit it → create the first admin through `install/` (or insert a row into `users` with a hash from `password_hash()`).

## 4. Making the AR work (MindAR compile + scan)
1. Admin → **AR Posters** → open the demo poster (or **Add AR poster** and upload yours).
2. **Hotspots**: click on attractions in the poster, assign an attraction to each, **Save**.
3. **Compile**: press *Compile target*. The progress bar runs in your browser; when it says **AR Target Ready** the `.mind` file has been saved on the server.
4. Set the poster to **Published** (list → upload icon). Drafts can only be scanned by logged-in admins.
5. Open `ar.php` on a phone over HTTPS (or scan the QR code on the home page), tap **START AR**, and point at the poster printed on matte paper, or displayed on another screen.

**Poster tips:** use the exact artwork you uploaded (same crop), rich detail and contrast, no big flat areas or plain-text blocks, no glare. The poster image is stored at max 1600px wide to keep compilation quick.

**Coordinate mapping** (see `ar/js/hotspot-layer.js`): MindAR's anchor is 1 unit wide and `aspect = height/width` tall with its origin at the centre, so `px = x + w/2 − 0.5`, `py = (0.5 − (y + h/2)) × aspect`.

## 5. Offline / local libraries (recommended for demos)
By default the pages load Bootstrap, Font Awesome, Three.js, MindAR and the QR library from public CDNs. To remove that dependency run, on a machine with internet:

```
php tools/fetch-vendor.php
```
This fills `assets/vendor/`; pages detect and use the local copies automatically. Library versions are set in `includes/config.php` (`THREE_VERSION`, `MINDAR_VERSION`, …). MindAR 1.2.x and Three.js 0.160 are the pair this project was written against; if you change versions, re-test AR.

Google Fonts and the YouTube player always come from Google/YouTube.

## 6. HTTPS (needed for phone cameras)
Browsers only allow camera access on secure origins. Options:
- **Production / cPanel:** enable the free *AutoSSL / Let's Encrypt* certificate in cPanel and open the site with `https://`.
- **Testing a phone against XAMPP:** use a tunnel such as `ngrok http 80` or `cloudflared tunnel --url http://localhost:80` and open the `https://…` URL it prints on the phone; the QR code on the home page uses whatever host you opened, so open the tunnel URL on the computer first.
- The site shows a warning on the AR page and in the admin when HTTPS is missing.

## 7. Deploy to cPanel
1. Upload the project (zip + *Extract* in File Manager) to `public_html/` or a sub-folder.
2. Create a MySQL database + user in *MySQL Databases* and grant ALL privileges.
3. Browse to `/install/`, enter the database details, create the admin, then **delete `install/`**.
4. Make sure `assets/uploads/*` and `assets/ar-targets/` are writable (755; 775 if needed).
5. Enable HTTPS (AutoSSL). Optionally set `define('APP_ENV','production');` in `config.local.php` (default).
6. Select PHP 8.1+ in *MultiPHP Manager* and enable `pdo_mysql`, `fileinfo`, `gd`, `mbstring`.

## 8. Admin guide
| Task | Where |
|---|---|
| Categories / destinations / attractions | Sidebar → Tourism |
| Upload poster, edit hotspots, compile, preview, publish | Sidebar → AR Management |
| Users, logs, site text, AR smoothing | Sidebar → System (admin role only) |

Roles: **admin** (everything) and **editor** (content only). "Remember me" keeps a session for 14 days; otherwise sessions expire after 2 hours idle.

## 9. Visitor guide
Home → pick a destination/attraction, or tap **Start AR Experience** → allow camera → scan the poster → tap a highlighted area → watch / open map / read more.

## 10. Upload requirements
JPG, PNG or WEBP only; images ≤ 5 MB (posters ≤ 8 MB), ≥ 400px wide, ≤ 8000px. Files are renamed, verified by content (not extension), re-encoded with GD and stored where PHP cannot run. If uploads fail check `upload_max_filesize` and `post_max_size` in `php.ini`.

## 11. Project layout
```
index.php ar.php destinations.php destination.php attractions.php attraction.php about.php contact.php
admin/        login, dashboard, destinations, attractions, categories, ar-posters (editor + compile), users, logs, settings
api/          ar/poster.php (public), ar/save_hotspots.php, ar/save_target.php (admin), search.php
includes/     config, database (PDO), auth, csrf, functions, upload, youtube, logger, repositories/
ar/js/        ar-app.js  hotspot-layer.js  card-ui.js  youtube-player.js     (visitor AR)
assets/       css, js (admin editor, compiler), vendor/ (optional), uploads/, ar-targets/, images/demo/
database/     database.sql   install/ (delete after use)   tools/fetch-vendor.php
```

## 12. Known limitations (browser reality)
- **Videos are not drawn inside the 3D scene.** Iframes cannot be reliably rendered in WebGL (especially iOS), so the video plays in an on-screen card that opens when a hotspot is tapped. Videos start **muted** (autoplay rules); the visitor taps *Unmute*.
- The info card is a screen overlay, not glued to the poster. The *hotspot markers* do follow the poster.
- Tracking quality depends on poster artwork, print quality and light. Very plain posters may not track.
- Compilation runs in the admin's browser (desktop recommended); the server never needs Node.js.
- iOS Safari < 16.4 does not support import maps → the AR page cannot start there.
- Distance uses the browser's geolocation only when the visitor taps *Show distance*.
- Demo YouTube links point to a public sample video; replace with your own.

## 13. Troubleshooting
| Symptom | Fix |
|---|---|
| Blank page / "Something went wrong" | Check Apache/PHP error log; set `define('APP_ENV','development');` in `config.local.php` to see the error |
| 500 error only in `assets/uploads` or `ar-targets` | Host disallows `Options`/`RemoveHandler` in `.htaccess`; delete those lines from the folder's `.htaccess` (keep the `FilesMatch` rules) |
| "Camera access requires HTTPS" | Use an `https://` URL (see §6) |
| "AR tracking is not ready" | Compile the poster (Admin → AR Posters → Compile) |
| Compile button says library did not load | No internet or CDN blocked; run `php tools/fetch-vendor.php` |
| Overlays jitter / lag | Admin → Settings → tweak *filterMinCF* / *filterBeta*; improve poster lighting |
| Poster never detected | Better artwork, matte print, whole poster in view, recompile after any image change |
| Black camera on iPhone | Open in Safari (not an in-app browser), check Settings → Safari → Camera |
| Login locked | 5 wrong passwords lock an account 15 minutes (8 per IP) |
| Images don't upload | Folder permissions, `upload_max_filesize`, GD/fileinfo extensions |

## 14. Test checklist
**Auth:** login · logout · wrong password · lockout after repeated failures · open `/admin/` while logged out → redirected.
**Content:** add/edit/delete/search/toggle for destinations, attractions, categories; image upload; YouTube URL variants (`watch?v=`, `youtu.be/`, `/embed/`); map URL.
**AR admin:** upload poster · add/move/resize/delete hotspot · assign attraction · save · reload editor (layout persists) · compile → READY.
**AR visitor:** camera permission (allow + deny) · detect poster · hotspots sit on the right panels · tap hotspot → card · video play/pause/mute · map & website · cover the poster (content hides) · back button releases camera.
**Devices:** Android Chrome · iPhone Safari · desktop Chrome · desktop Edge.
**Security:** SQL-injection strings in search boxes · `<script>` in text fields (must show as text) · POST without CSRF token (must fail) · upload a `.php` renamed to `.jpg` (must be rejected) · open `/includes/config.php` and `/database/database.sql` in the browser (must be denied) · call `api/ar/save_hotspots.php` without login (401).

## 15. Credits & licence
MindAR (MIT), Three.js (MIT), Bootstrap (MIT), Font Awesome Free (icons CC BY 4.0), qrcodejs (MIT). Demo images are generated placeholders. Use freely for study, final-year and teaching projects.
