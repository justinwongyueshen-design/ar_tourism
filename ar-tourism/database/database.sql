-- =====================================================================
--  AR Tourism Explorer - database schema  (MySQL 8+, InnoDB, utf8mb4)
--  1. Create an empty database (e.g. ar_tourism) with utf8mb4 collation
--  2. Import this file (phpMyAdmin > Import) OR let install/index.php do it
--  3. Create the first admin account with install/index.php
--  Demo data at the bottom uses INSERT IGNORE, so re-importing is harmless.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username        VARCHAR(50)  NOT NULL,
  email           VARCHAR(120) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  full_name       VARCHAR(100) NOT NULL DEFAULT '',
  role            ENUM('admin','editor') NOT NULL DEFAULT 'editor',
  status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until    DATETIME NULL,
  last_login_at   DATETIME NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_address   VARCHAR(45)  NOT NULL,
  username     VARCHAR(120) NOT NULL DEFAULT '',
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(80) NOT NULL,
  slug       VARCHAR(100) NOT NULL,
  icon       VARCHAR(40) NOT NULL DEFAULT 'fa-landmark',
  status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS destinations (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id       INT UNSIGNED NULL,
  name              VARCHAR(150) NOT NULL,
  slug              VARCHAR(180) NOT NULL,
  short_description VARCHAR(300) NOT NULL DEFAULT '',
  full_description  TEXT NULL,
  cover_image       VARCHAR(255) NULL,
  location          VARCHAR(200) NOT NULL DEFAULT '',
  latitude          DECIMAL(10,7) NULL,
  longitude         DECIMAL(10,7) NULL,
  maps_url          VARCHAR(500) NULL,
  featured          TINYINT(1) NOT NULL DEFAULT 0,
  status            ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_destinations_slug (slug),
  KEY idx_destinations_status_featured (status, featured),
  KEY idx_destinations_name (name),
  CONSTRAINT fk_destinations_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attractions (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  destination_id    INT UNSIGNED NOT NULL,
  category_id       INT UNSIGNED NULL,
  name              VARCHAR(150) NOT NULL,
  slug              VARCHAR(180) NOT NULL,
  short_description VARCHAR(300) NOT NULL DEFAULT '',
  full_description  TEXT NULL,
  main_image        VARCHAR(255) NULL,
  youtube_url       VARCHAR(500) NULL,
  youtube_id        VARCHAR(20) NULL,
  website_url       VARCHAR(500) NULL,
  maps_url          VARCHAR(500) NULL,
  latitude          DECIMAL(10,7) NULL,
  longitude         DECIMAL(10,7) NULL,
  opening_hours     VARCHAR(255) NULL,
  entry_info        VARCHAR(255) NULL,
  display_order     INT NOT NULL DEFAULT 0,
  featured          TINYINT(1) NOT NULL DEFAULT 0,
  views             INT UNSIGNED NOT NULL DEFAULT 0,
  status            ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attractions_slug (slug),
  KEY idx_attractions_destination_status (destination_id, status),
  KEY idx_attractions_category (category_id),
  KEY idx_attractions_name (name),
  KEY idx_attractions_views (views),
  CONSTRAINT fk_attractions_destination FOREIGN KEY (destination_id) REFERENCES destinations (id) ON DELETE CASCADE,
  CONSTRAINT fk_attractions_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attraction_images (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  attraction_id INT UNSIGNED NOT NULL,
  image_path    VARCHAR(255) NOT NULL,
  caption       VARCHAR(200) NOT NULL DEFAULT '',
  sort_order    INT NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_images_attraction (attraction_id, sort_order),
  CONSTRAINT fk_images_attraction FOREIGN KEY (attraction_id) REFERENCES attractions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ar_posters (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  poster_name        VARCHAR(150) NOT NULL,
  description        TEXT NULL,
  poster_image       VARCHAR(255) NOT NULL,
  image_width        INT UNSIGNED NOT NULL DEFAULT 0,
  image_height       INT UNSIGNED NOT NULL DEFAULT 0,
  target_file        VARCHAR(255) NULL,
  target_status      ENUM('NOT_COMPILED','READY') NOT NULL DEFAULT 'NOT_COMPILED',
  target_compiled_at DATETIME NULL,
  status             ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_posters_status (status, target_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hotspot geometry is NORMALISED: 0..1 relative to the poster image, origin = top-left.
CREATE TABLE IF NOT EXISTS ar_hotspots (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  poster_id      INT UNSIGNED NOT NULL,
  attraction_id  INT UNSIGNED NULL,
  label          VARCHAR(120) NOT NULL DEFAULT '',
  x              DECIMAL(8,6) NOT NULL,
  y              DECIMAL(8,6) NOT NULL,
  width          DECIMAL(8,6) NOT NULL,
  height         DECIMAL(8,6) NOT NULL,
  z_index        INT NOT NULL DEFAULT 0,
  content_type   ENUM('card','video','both') NOT NULL DEFAULT 'card',
  video_url      VARCHAR(500) NULL,
  custom_content TEXT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_hotspots_poster (poster_id),
  KEY idx_hotspots_attraction (attraction_id),
  CONSTRAINT fk_hotspots_poster FOREIGN KEY (poster_id) REFERENCES ar_posters (id) ON DELETE CASCADE,
  CONSTRAINT fk_hotspots_attraction FOREIGN KEY (attraction_id) REFERENCES attractions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- History of compiled MindAR targets (the current one is also stored on ar_posters).
CREATE TABLE IF NOT EXISTS ar_targets (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  poster_id       INT UNSIGNED NOT NULL,
  file_path       VARCHAR(255) NOT NULL,
  file_size       INT UNSIGNED NOT NULL DEFAULT 0,
  checksum        CHAR(64) NOT NULL,
  mindar_version  VARCHAR(20) NOT NULL DEFAULT '',
  compiled_by     INT UNSIGNED NULL,
  compiled_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_targets_poster (poster_id),
  CONSTRAINT fk_targets_poster FOREIGN KEY (poster_id) REFERENCES ar_posters (id) ON DELETE CASCADE,
  CONSTRAINT fk_targets_user FOREIGN KEY (compiled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(80) NOT NULL,
  description VARCHAR(500) NOT NULL DEFAULT '',
  ip_address  VARCHAR(45) NOT NULL DEFAULT '',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_logs_created (created_at),
  KEY idx_logs_user (user_id),
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(60) NOT NULL,
  setting_value TEXT NULL,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;


-- =====================  DEMO DATA  (safe to delete)  =====================

INSERT IGNORE INTO categories (id, name, slug, icon, status) VALUES
(1, 'Historical', 'historical', 'fa-landmark', 'active'),
(2, 'Cultural', 'cultural', 'fa-masks-theater', 'active'),
(3, 'Nature', 'nature', 'fa-mountain-sun', 'active'),
(4, 'Food', 'food', 'fa-utensils', 'active'),
(5, 'Shopping', 'shopping', 'fa-bag-shopping', 'active'),
(6, 'Family', 'family', 'fa-people-roof', 'active'),
(7, 'Adventure', 'adventure', 'fa-person-hiking', 'active'),
(8, 'Religious', 'religious', 'fa-place-of-worship', 'active'),
(9, 'Museum', 'museum', 'fa-building-columns', 'active'),
(10, 'Architecture', 'architecture', 'fa-archway', 'active');

INSERT IGNORE INTO destinations (id, category_id, name, slug, short_description, full_description, cover_image, location, latitude, longitude, featured, status) VALUES
(1, 1, 'Heritage District', 'heritage-district', 'Cobbled streets, colonial facades and centuries of trading history in one walkable quarter.', 'The Heritage District grew around a river port and still keeps its original street grid.

Walk between restored shophouses, a working clock tower and a fortified gate, with museums that explain how the quarter became a meeting point of many cultures.', 'images/demo/dest-1.svg', 'Old Town, Demo City', 3.15, 101.7, 1, 'active'),
(2, 3, 'Nature Escape', 'nature-escape', 'Cool highlands, waterfalls and protected gardens an hour from the city.', 'Nature Escape is a highland reserve with marked trails, viewpoints and botanical gardens.

Early mornings bring mist over the valley, and the trail network suits both casual walkers and keen hikers.', 'images/demo/dest-2.svg', 'Highland Reserve, Demo State', 3.42, 101.82, 1, 'active'),
(3, 2, 'Cultural Village', 'cultural-village', 'A living village where weaving, music and dance are still taught and performed.', 'The Cultural Village preserves traditional crafts and performing arts.

Visitors can meet artisans, join short workshops and watch scheduled performances in the dance hall.', 'images/demo/dest-3.svg', 'Riverside, Demo State', 3.01, 101.52, 1, 'active'),
(4, 6, 'Coastal Destination', 'coastal-destination', 'Lighthouses, calm beaches and a busy fishing wharf for a family day out.', 'The Coastal Destination combines a historic lighthouse, a long golden beach and a fishermen wharf with fresh seafood stalls.

It is easy to reach by road and has plenty of shaded rest areas.', 'images/demo/dest-4.svg', 'East Coast, Demo State', 2.83, 102.1, 0, 'active'),
(5, 4, 'Food Heritage Area', 'food-heritage-area', 'Street food, old coffee houses and spice markets that tell the story of local cuisine.', 'The Food Heritage Area is where recipes passed through generations are still cooked daily.

Come hungry for the night hawker street, take a slow coffee at a heritage cafe and shop for spices.', 'images/demo/dest-5.svg', 'Market Quarter, Demo City', 3.14, 101.69, 0, 'active');

INSERT IGNORE INTO attractions (id, destination_id, category_id, name, slug, short_description, full_description, main_image, youtube_url, youtube_id, latitude, longitude, opening_hours, entry_info, display_order, featured, views, status) VALUES
(1, 1, 1, 'Old Clock Tower Square', 'old-clock-tower-square', 'A landmark clock tower at the heart of the old quarter.', 'The clock tower has marked the hour since the early twentieth century.

The square around it hosts weekend markets and is the best starting point for a walking tour.', 'images/demo/attr-1.svg', 'https://www.youtube.com/watch?v=M7lc1UVf-VE', 'M7lc1UVf-VE', 3.1502, 101.7003, 'Open 24 hours', 'Free entry', 1, 1, 320, 'active'),
(2, 1, 9, 'Merchant Quarter Museum', 'merchant-quarter-museum', 'Trade, tea and textiles told through restored merchant rooms.', 'Housed in a former trading house, the museum shows how merchants lived and worked.

Guided tours explain the objects, maps and ledgers on display.', 'images/demo/attr-2.svg', NULL, NULL, 3.1498, 101.6996, 'Tue-Sun 9:00-17:00', 'Adults 10, children 5', 2, 1, 280, 'active'),
(3, 1, 1, 'Grand Fort Gate', 'grand-fort-gate', 'The last surviving gate of the old fortified town.', 'The stone gate and its ramparts protected the port for generations.

Climb the steps for a view over the river and rooftops.', 'images/demo/attr-3.svg', NULL, NULL, 3.1511, 101.7011, 'Daily 8:00-18:00', 'Free entry', 3, 0, 190, 'active'),
(4, 1, 10, 'Colonial Courthouse', 'colonial-courthouse', 'A grand courthouse with arched verandas and a carved timber ceiling.', 'Built in the nineteenth century, the courthouse is a fine example of tropical colonial architecture.

The ground floor is open to visitors outside court sessions.', 'images/demo/attr-4.svg', NULL, NULL, 3.1489, 101.7008, 'Mon-Fri 9:00-16:00', 'Free entry', 4, 0, 150, 'active'),
(5, 2, 3, 'Misty Hill Lookout', 'misty-hill-lookout', 'A cool hilltop viewpoint above the clouds at sunrise.', 'Reached by a short forest road, the lookout offers wide views over the valley.

Bring a light jacket because mornings are cool.', 'images/demo/attr-5.svg', 'https://www.youtube.com/watch?v=M7lc1UVf-VE', 'M7lc1UVf-VE', 3.421, 101.8195, 'Daily 6:00-19:00', 'Parking 3', 5, 1, 260, 'active'),
(6, 2, 7, 'Emerald Waterfall Trail', 'emerald-waterfall-trail', 'A moderate two-hour trail to a three-tier waterfall.', 'The trail follows a stream through shaded forest to a series of cascades.

Wear sturdy shoes and carry water.', 'images/demo/attr-6.svg', NULL, NULL, 3.429, 101.826, 'Daily 7:00-17:00', 'Trail fee 5', 6, 0, 210, 'active'),
(7, 2, 3, 'Orchid Garden Reserve', 'orchid-garden-reserve', 'A quiet garden with hundreds of native orchid species.', 'The garden protects native orchids and runs seasonal exhibitions.

Guides are available on weekends.', 'images/demo/attr-7.svg', NULL, NULL, 3.415, 101.815, 'Wed-Mon 8:30-17:00', 'Adults 8, children 4', 7, 0, 120, 'active'),
(8, 3, 2, 'Weavers Longhouse', 'weavers-longhouse', 'A communal longhouse where visitors can watch hand weaving.', 'Master weavers demonstrate traditional patterns on wooden looms.

Short workshops let you weave a small piece to take home.', 'images/demo/attr-8.svg', NULL, NULL, 3.011, 101.521, 'Daily 9:00-17:00', 'Workshop 15', 8, 1, 170, 'active'),
(9, 3, 2, 'Traditional Dance Hall', 'traditional-dance-hall', 'Daily performances of local music and dance.', 'The open-sided hall hosts two performances a day, with costumes and live instruments.

Seating is first come first served.', 'images/demo/attr-9.svg', 'https://www.youtube.com/watch?v=M7lc1UVf-VE', 'M7lc1UVf-VE', 3.009, 101.519, 'Shows 11:00 and 15:00', 'Adults 12', 9, 0, 230, 'active'),
(10, 3, 5, 'Riverside Craft Market', 'riverside-craft-market', 'Handmade crafts, batik and woodwork beside the river.', 'Over forty stalls sell locally made goods.

Most vendors accept cards, but carry small cash for the smaller stalls.', 'images/demo/attr-10.svg', NULL, NULL, 3.012, 101.523, 'Fri-Sun 10:00-20:00', 'Free entry', 10, 0, 140, 'active'),
(11, 4, 6, 'Lighthouse Point', 'lighthouse-point', 'A white lighthouse with a climbing tour and sea views.', 'The lighthouse guided the fishing fleet for over a century.

The spiral stairs lead to a balcony with views along the coast.', 'images/demo/attr-11.svg', NULL, NULL, 2.831, 102.102, 'Daily 9:00-17:30', 'Adults 6', 11, 1, 200, 'active'),
(12, 4, 6, 'Golden Sands Beach', 'golden-sands-beach', 'A long, calm beach with shaded picnic areas.', 'The gently sloping beach is ideal for families.

Lifeguards are on duty at weekends and holidays.', 'images/demo/attr-12.svg', NULL, NULL, 2.828, 102.098, 'Open 24 hours', 'Free entry', 12, 0, 300, 'active'),
(13, 4, 4, 'Fishermen Wharf', 'fishermen-wharf', 'Fresh seafood stalls where the catch comes straight off the boats.', 'Boats unload in the late afternoon and the grills start soon after.

Arrive before sunset for the best selection.', 'images/demo/attr-13.svg', NULL, NULL, 2.834, 102.104, 'Daily 15:00-22:00', 'Free entry', 13, 0, 180, 'active'),
(14, 5, 4, 'Night Hawker Street', 'night-hawker-street', 'Dozens of food stalls serving regional favourites after dark.', 'The street fills with the smell of charcoal and spices from late afternoon.

Try the noodle soups, grilled skewers and sweet shaved ice.', 'images/demo/attr-14.svg', NULL, NULL, 3.141, 101.691, 'Daily 17:00-24:00', 'Free entry', 14, 1, 350, 'active'),
(15, 5, 4, 'Heritage Coffee House', 'heritage-coffee-house', 'A century-old coffee house roasting beans on site.', 'Marble tables and wooden fans set the scene for strong local coffee.

Breakfast sets with toast and soft-boiled eggs are a classic.', 'images/demo/attr-15.svg', NULL, NULL, 3.1395, 101.6885, 'Daily 6:30-16:00', 'Free entry', 15, 0, 160, 'active'),
(16, 5, 5, 'Spice Market Hall', 'spice-market-hall', 'A covered hall of spices, dried goods and cooking tools.', 'Stallholders sell blends and pastes made to family recipes.

Ask for samples and cooking tips.', 'images/demo/attr-16.svg', NULL, NULL, 3.142, 101.687, 'Daily 7:00-15:00', 'Free entry', 16, 0, 110, 'active');

INSERT IGNORE INTO attraction_images (id, attraction_id, image_path, caption, sort_order) VALUES
(1, 1, 'images/demo/attr-1-g1.svg', 'Old Clock Tower Square - view 1', 1),
(2, 1, 'images/demo/attr-1-g2.svg', 'Old Clock Tower Square - view 2', 2),
(3, 2, 'images/demo/attr-2-g1.svg', 'Merchant Quarter Museum - view 1', 1),
(4, 2, 'images/demo/attr-2-g2.svg', 'Merchant Quarter Museum - view 2', 2),
(5, 3, 'images/demo/attr-3-g1.svg', 'Grand Fort Gate - view 1', 1),
(6, 3, 'images/demo/attr-3-g2.svg', 'Grand Fort Gate - view 2', 2),
(7, 4, 'images/demo/attr-4-g1.svg', 'Colonial Courthouse - view 1', 1),
(8, 4, 'images/demo/attr-4-g2.svg', 'Colonial Courthouse - view 2', 2),
(9, 5, 'images/demo/attr-5-g1.svg', 'Misty Hill Lookout - view 1', 1),
(10, 5, 'images/demo/attr-5-g2.svg', 'Misty Hill Lookout - view 2', 2),
(11, 6, 'images/demo/attr-6-g1.svg', 'Emerald Waterfall Trail - view 1', 1),
(12, 6, 'images/demo/attr-6-g2.svg', 'Emerald Waterfall Trail - view 2', 2),
(13, 7, 'images/demo/attr-7-g1.svg', 'Orchid Garden Reserve - view 1', 1),
(14, 7, 'images/demo/attr-7-g2.svg', 'Orchid Garden Reserve - view 2', 2),
(15, 8, 'images/demo/attr-8-g1.svg', 'Weavers Longhouse - view 1', 1),
(16, 8, 'images/demo/attr-8-g2.svg', 'Weavers Longhouse - view 2', 2),
(17, 9, 'images/demo/attr-9-g1.svg', 'Traditional Dance Hall - view 1', 1),
(18, 9, 'images/demo/attr-9-g2.svg', 'Traditional Dance Hall - view 2', 2),
(19, 10, 'images/demo/attr-10-g1.svg', 'Riverside Craft Market - view 1', 1),
(20, 10, 'images/demo/attr-10-g2.svg', 'Riverside Craft Market - view 2', 2),
(21, 11, 'images/demo/attr-11-g1.svg', 'Lighthouse Point - view 1', 1),
(22, 11, 'images/demo/attr-11-g2.svg', 'Lighthouse Point - view 2', 2),
(23, 12, 'images/demo/attr-12-g1.svg', 'Golden Sands Beach - view 1', 1),
(24, 12, 'images/demo/attr-12-g2.svg', 'Golden Sands Beach - view 2', 2),
(25, 13, 'images/demo/attr-13-g1.svg', 'Fishermen Wharf - view 1', 1),
(26, 13, 'images/demo/attr-13-g2.svg', 'Fishermen Wharf - view 2', 2),
(27, 14, 'images/demo/attr-14-g1.svg', 'Night Hawker Street - view 1', 1),
(28, 14, 'images/demo/attr-14-g2.svg', 'Night Hawker Street - view 2', 2),
(29, 15, 'images/demo/attr-15-g1.svg', 'Heritage Coffee House - view 1', 1),
(30, 15, 'images/demo/attr-15-g2.svg', 'Heritage Coffee House - view 2', 2),
(31, 16, 'images/demo/attr-16-g1.svg', 'Spice Market Hall - view 1', 1),
(32, 16, 'images/demo/attr-16-g2.svg', 'Spice Market Hall - view 2', 2);

INSERT IGNORE INTO ar_posters (id, poster_name, description, poster_image, image_width, image_height, target_status, status) VALUES
(1, 'Heritage District Demo Poster', 'Generated demo poster with four attraction panels. Compile it in Admin > AR Posters, then scan it from another screen or print it.', 'images/demo/poster-demo.png', 1200, 1600, 'NOT_COMPILED', 'published');

INSERT IGNORE INTO ar_hotspots (id, poster_id, attraction_id, label, x, y, width, height, z_index, content_type) VALUES
(1, 1, 1, 'Old Clock Tower Square', 0.05, 0.2, 0.43, 0.36, 0, 'both'),
(2, 1, 2, 'Merchant Quarter Museum', 0.52, 0.2, 0.43, 0.36, 1, 'card'),
(3, 1, 3, 'Grand Fort Gate', 0.05, 0.6, 0.43, 0.36, 2, 'card'),
(4, 1, 4, 'Colonial Courthouse', 0.52, 0.6, 0.43, 0.36, 3, 'card');

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('site_name', 'AR Tourism Explorer'),
('tagline', 'Point. Scan. Discover.'),
('hero_title', 'Discover heritage through your camera'),
('hero_subtitle', 'Scan a tourism poster and watch attractions come alive with videos, maps and stories.'),
('about_text', 'AR Tourism Explorer connects printed tourism material with rich digital information. Scan a poster with your phone browser - no app to install - and explore attractions, watch videos and open map directions.'),
('contact_email', 'info@example.com'),
('contact_phone', '+60 3-0000 0000'),
('contact_address', 'Tourism Information Centre, Demo City'),
('footer_text', 'Demo data only. Replace with your own destinations.'),
('ar_filter_min_cf', '0.001'),
('ar_filter_beta', '1000');
