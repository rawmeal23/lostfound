-- CHANGE: Seed the category and location choices used by the report form.
USE lostfound;

INSERT IGNORE INTO categories (name) VALUES
  ('School ID'),
  ('Wallet'),
  ('Mobile Phone'),
  ('Keys'),
  ('Books'),
  ('Bag'),
  ('Clothing'),
  ('Other');

INSERT IGNORE INTO locations (name) VALUES
  ('Library'),
  ('Canteen'),
  ('Main Building'),
  ('Gymnasium'),
  ('Computer Lab'),
  ('Parking Area'),
  ('Other');

-- CHANGE: Add reusable demo accounts and approved items for home-page testing.
INSERT INTO users (school_id, full_name, email, password_hash, role)
SELECT '123', 'Demo Student', 'student123@example.test',
       '$2y$12$skk.sP9yWfFPP.K7mfCLLOKJf/8SAPCx2qVUNDEvsUx8UnQF79aay', 'student'
WHERE NOT EXISTS (
  SELECT 1 FROM users
  WHERE school_id = '123' OR email = 'student123@example.test'
);

INSERT INTO users (school_id, full_name, email, password_hash, role)
SELECT 'admin123', 'Demo Administrator', 'admin123@example.test',
       '$2y$12$skk.sP9yWfFPP.K7mfCLLOKJf/8SAPCx2qVUNDEvsUx8UnQF79aay', 'admin'
WHERE NOT EXISTS (
  SELECT 1 FROM users
  WHERE school_id = 'admin123' OR email = 'admin123@example.test'
);

-- Demo reports belong to the admin account so the student account can claim them.
INSERT INTO items
  (user_id, category_id, location_id, type, title, description, location_note,
   date_lost, date_found, kept_at, photo_path, status)
SELECT u.id, c.id, l.id, 'found', 'Black Leather Wallet',
       'Black wallet found under a table. Contains some cards. Kept at the office.',
       'Canteen table', NULL, '2026-11-12', 'at_office', NULL, 'approved'
FROM users u
JOIN categories c ON c.name = 'Wallet'
JOIN locations l ON l.name = 'Canteen'
WHERE u.school_id = 'admin123'
  AND NOT EXISTS (
    SELECT 1 FROM items i WHERE i.user_id = u.id AND i.title = 'Black Leather Wallet'
  );

INSERT INTO items
  (user_id, category_id, location_id, type, title, description, location_note,
   date_lost, date_found, kept_at, photo_path, status)
SELECT u.id, c.id, l.id, 'lost', 'School ID (Blue Lanyard)',
       'Lost near the second-floor reading area. Blue lanyard attached.',
       'Second-floor reading area', '2026-11-14', NULL, NULL, NULL, 'approved'
FROM users u
JOIN categories c ON c.name = 'School ID'
JOIN locations l ON l.name = 'Library'
WHERE u.school_id = 'admin123'
  AND NOT EXISTS (
    SELECT 1 FROM items i WHERE i.user_id = u.id AND i.title = 'School ID (Blue Lanyard)'
  );

INSERT INTO items
  (user_id, category_id, location_id, type, title, description, location_note,
   date_lost, date_found, kept_at, photo_path, status)
SELECT u.id, c.id, l.id, 'found', 'Set of Keys with Red Keychain',
       'Three keys on a red keychain, found near the bleachers.',
       'Near the bleachers', NULL, '2026-11-15', 'with_finder', NULL, 'approved'
FROM users u
JOIN categories c ON c.name = 'Keys'
JOIN locations l ON l.name = 'Gymnasium'
WHERE u.school_id = 'admin123'
  AND NOT EXISTS (
    SELECT 1 FROM items i WHERE i.user_id = u.id AND i.title = 'Set of Keys with Red Keychain'
  );

INSERT INTO items
  (user_id, category_id, location_id, type, title, description, location_note,
   date_lost, date_found, kept_at, photo_path, status)
SELECT u.id, c.id, l.id, 'lost', 'Samsung Phone (Black Case)',
       'Black case with a sticker on the back. Lost after afternoon class.',
       'After afternoon class', '2026-11-16', NULL, NULL, 'assets/images/item4.jpg', 'approved'
FROM users u
JOIN categories c ON c.name = 'Mobile Phone'
JOIN locations l ON l.name = 'Computer Lab'
WHERE u.school_id = 'admin123'
  AND NOT EXISTS (
    SELECT 1 FROM items i WHERE i.user_id = u.id AND i.title = 'Samsung Phone (Black Case)'
  );

INSERT INTO items
  (user_id, category_id, location_id, type, title, description, location_note,
   date_lost, date_found, kept_at, photo_path, status)
SELECT u.id, c.id, l.id, 'found', 'Gray Backpack',
       'Gray backpack with notebooks inside. Kept at the Security Office.',
       'Security Office', NULL, '2026-11-18', 'at_office', 'assets/images/item6.jpg', 'approved'
FROM users u
JOIN categories c ON c.name = 'Bag'
JOIN locations l ON l.name = 'Parking Area'
WHERE u.school_id = 'admin123'
  AND NOT EXISTS (
    SELECT 1 FROM items i WHERE i.user_id = u.id AND i.title = 'Gray Backpack'
  );

INSERT INTO item_logs (item_id, actor_user_id, event_type, details)
SELECT i.id, u.id, events.event_type, events.details
FROM items i
JOIN users u ON u.id = i.user_id
JOIN (
  SELECT 'report_created' AS event_type, 'Demo item reported' AS details
  UNION ALL
  SELECT 'item_approved', 'Demo item approved'
) events
WHERE u.school_id = 'admin123'
  AND i.title IN (
    'Black Leather Wallet',
    'School ID (Blue Lanyard)',
    'Set of Keys with Red Keychain',
    'Samsung Phone (Black Case)',
    'Gray Backpack'
  )
  AND NOT EXISTS (
    SELECT 1
    FROM item_logs existing
    WHERE existing.item_id = i.id
      AND existing.event_type = events.event_type
      AND existing.details = events.details
  );
