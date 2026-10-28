-- ============================================================
--  Demo accounts + sample tickets (run ONCE, after helpdesk_db.sql)
--  Select your database first (phpMyAdmin), or:
--  mysql -u root --default-character-set=utf8mb4 helpdesk_db < database/seed_demo_data.sql
--
--  All demo accounts use the password: Admin@123
--  rensacuna1@gmail.com .................. student (submits the tickets)
--  geraldjabas446@gmail.com .............. technician
--  yevrah082495@gmail.com ................ technician
--  rodriguezkurtrusselrogelio@gmail.com .. technician
-- ============================================================
SET NAMES utf8mb4;

INSERT IGNORE INTO users (id_number, first_name, last_name, email, role, department, password_hash) VALUES
  ('2024-00002', 'Rens',               'Acuña',     'rensacuna1@gmail.com',                 'student',    'BS Information Technology',
   '$2y$10$Q/ahaw1z82rUdBBbvIbhXejZwwsJeeCArHIefAaATNwhvC/XJTNy6'),
  ('TECH-0003',  'Gerald',             'Jabas',     'geraldjabas446@gmail.com',             'technician', 'Systems & Accounts',
   '$2y$10$Q/ahaw1z82rUdBBbvIbhXejZwwsJeeCArHIefAaATNwhvC/XJTNy6'),
  ('TECH-0004',  'Yevrah',             'Technician', 'yevrah082495@gmail.com',              'technician', 'Network & Infrastructure',
   '$2y$10$Q/ahaw1z82rUdBBbvIbhXejZwwsJeeCArHIefAaATNwhvC/XJTNy6'),
  ('TECH-0005',  'Kurt Russel Rogelio', 'Rodriguez', 'rodriguezkurtrusselrogelio@gmail.com', 'technician', 'Hardware & Laboratory',
   '$2y$10$Q/ahaw1z82rUdBBbvIbhXejZwwsJeeCArHIefAaATNwhvC/XJTNy6');

SELECT id INTO @admin  FROM users WHERE role = 'admin' ORDER BY id LIMIT 1;
SELECT id INTO @rens   FROM users WHERE email = 'rensacuna1@gmail.com';
SELECT id INTO @gerald FROM users WHERE email = 'geraldjabas446@gmail.com';
SELECT id INTO @yevrah FROM users WHERE email = 'yevrah082495@gmail.com';
SELECT id INTO @kurt   FROM users WHERE email = 'rodriguezkurtrusselrogelio@gmail.com';

SELECT id INTO @cat_portal  FROM categories WHERE slug = 'student_portal';
SELECT id INTO @cat_account FROM categories WHERE slug = 'student_account';
SELECT id INTO @cat_assist  FROM categories WHERE slug = 'student_assist';

-- ------------------------------------------------------------
--  1. Closed + rated (Kurt)
-- ------------------------------------------------------------
INSERT INTO tickets (requester_id, category_id, subject, description, location, priority, status, assigned_to, assigned_at, resolved_at, closed_at, rating, feedback, created_at)
VALUES (@rens, @cat_assist, 'Microsoft Office not activated on school laptop',
  'Word and Excel show "Product Activation Failed" and I can''t edit my documents. I need it for my thesis draft.',
  'Library, 2nd floor', 'low', 'closed', @kurt,
  NOW() - INTERVAL 7 DAY + INTERVAL 1 HOUR, NOW() - INTERVAL 6 DAY, NOW() - INTERVAL 6 DAY + INTERVAL 3 HOUR,
  5, 'Very fast and friendly support. Thank you!', NOW() - INTERVAL 7 DAY);
SET @t1 = LAST_INSERT_ID();

-- ------------------------------------------------------------
--  2. Resolved, waiting for the student to confirm (Gerald)
-- ------------------------------------------------------------
INSERT INTO tickets (requester_id, category_id, subject, description, location, priority, status, assigned_to, assigned_at, resolved_at, created_at)
VALUES (@rens, @cat_account, 'Account locked after too many login attempts',
  'My student portal account says "Account locked". I mistyped my password a few times and now I can''t log in at all.',
  NULL, 'urgent', 'resolved', @gerald,
  NOW() - INTERVAL 5 DAY + INTERVAL 30 MINUTE, NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 5 DAY);
SET @t2 = LAST_INSERT_ID();

-- ------------------------------------------------------------
--  3. In progress (Kurt)
-- ------------------------------------------------------------
INSERT INTO tickets (requester_id, category_id, subject, description, location, priority, status, assigned_to, assigned_at, created_at)
VALUES (@rens, @cat_portal, 'Cannot view grades in the student portal',
  'When I open the Grades page it only shows a blank white screen. I tried Chrome and Edge and cleared my cache.',
  NULL, 'high', 'in_progress', @kurt,
  NOW() - INTERVAL 3 DAY + INTERVAL 2 HOUR, NOW() - INTERVAL 3 DAY);
SET @t3 = LAST_INSERT_ID();

-- ------------------------------------------------------------
--  4. Assigned (Gerald)
-- ------------------------------------------------------------
INSERT INTO tickets (requester_id, category_id, subject, description, location, priority, status, assigned_to, assigned_at, created_at)
VALUES (@rens, @cat_portal, 'Enrollment page shows an error when saving subjects',
  'I get "Something went wrong" every time I click Save on the enrollment page. The enrollment deadline is this week.',
  NULL, 'high', 'assigned', @gerald,
  NOW() - INTERVAL 2 DAY + INTERVAL 1 HOUR, NOW() - INTERVAL 2 DAY);
SET @t4 = LAST_INSERT_ID();

-- ------------------------------------------------------------
--  5. Assigned (Yevrah)
-- ------------------------------------------------------------
INSERT INTO tickets (requester_id, category_id, subject, description, location, priority, status, assigned_to, assigned_at, created_at)
VALUES (@rens, @cat_assist, 'Campus Wi-Fi keeps disconnecting',
  'My laptop connects to the campus Wi-Fi but drops every 5-10 minutes. Other students in the room have the same problem.',
  'Room 304, Main Building', 'medium', 'assigned', @yevrah,
  NOW() - INTERVAL 1 DAY + INTERVAL 1 HOUR, NOW() - INTERVAL 1 DAY);
SET @t5 = LAST_INSERT_ID();

-- ------------------------------------------------------------
--  6. In progress (Yevrah)
-- ------------------------------------------------------------
INSERT INTO tickets (requester_id, category_id, subject, description, location, priority, status, assigned_to, assigned_at, created_at)
VALUES (@rens, @cat_account, 'Cannot sign in to the LMS with my school email',
  'The LMS says "User not found" when I sign in with my school email, even though I can open my email inbox.',
  NULL, 'medium', 'in_progress', @yevrah,
  NOW() - INTERVAL 20 HOUR, NOW() - INTERVAL 22 HOUR);
SET @t6 = LAST_INSERT_ID();

-- ------------------------------------------------------------
--  7. Open, waiting for the admin to assign it
-- ------------------------------------------------------------
INSERT INTO tickets (requester_id, category_id, subject, description, location, priority, status, created_at)
VALUES (@rens, @cat_assist, 'School software installer will not run',
  'The installer for the programming IDE we use in class closes right after I open it. No error message appears.',
  NULL, 'medium', 'open', NOW() - INTERVAL 3 HOUR);
SET @t7 = LAST_INSERT_ID();

-- ------------------------------------------------------------
--  Ticket timelines
-- ------------------------------------------------------------
INSERT INTO ticket_activity (ticket_id, user_id, type, body, created_at) VALUES
  (@t1, @rens,   'created',    'Submitted the ticket.',                          NOW() - INTERVAL 7 DAY),
  (@t1, @admin,  'assignment', 'Assigned to Kurt Russel Rogelio Rodriguez.',     NOW() - INTERVAL 7 DAY + INTERVAL 1 HOUR),
  (@t1, @kurt,   'comment',    'Hi Rens, please bring the laptop to the IT office so I can re-activate Office with the school license.', NOW() - INTERVAL 7 DAY + INTERVAL 2 HOUR),
  (@t1, @kurt,   'status',     'Changed status from Assigned to Resolved.\n\nOffice was re-activated using the school volume license.', NOW() - INTERVAL 6 DAY),
  (@t1, @rens,   'feedback',   'Confirmed the fix and rated the support 5/5.\n\nVery fast and friendly support. Thank you!', NOW() - INTERVAL 6 DAY + INTERVAL 3 HOUR),

  (@t2, @rens,   'created',    'Submitted the ticket.',                          NOW() - INTERVAL 5 DAY),
  (@t2, @admin,  'assignment', 'Assigned to Gerald Jabas.',                      NOW() - INTERVAL 5 DAY + INTERVAL 30 MINUTE),
  (@t2, @gerald, 'internal_note', 'Lockout came from 6 failed attempts. Identity verified with the student ID.', NOW() - INTERVAL 4 DAY - INTERVAL 2 HOUR),
  (@t2, @gerald, 'status',     'Changed status from Assigned to Resolved.\n\nYour account is unlocked. Please log in and change your password right away.', NOW() - INTERVAL 4 DAY),

  (@t3, @rens,   'created',    'Submitted the ticket.',                          NOW() - INTERVAL 3 DAY),
  (@t3, @admin,  'assignment', 'Assigned to Kurt Russel Rogelio Rodriguez.',     NOW() - INTERVAL 3 DAY + INTERVAL 2 HOUR),
  (@t3, @kurt,   'status',     'Changed status from Assigned to In Progress.',   NOW() - INTERVAL 2 DAY),
  (@t3, @kurt,   'comment',    'We found the issue on the grades module. We are working on a fix and will update you soon.', NOW() - INTERVAL 2 DAY + INTERVAL 10 MINUTE),

  (@t4, @rens,   'created',    'Submitted the ticket.',                          NOW() - INTERVAL 2 DAY),
  (@t4, @admin,  'assignment', 'Assigned to Gerald Jabas.',                      NOW() - INTERVAL 2 DAY + INTERVAL 1 HOUR),

  (@t5, @rens,   'created',    'Submitted the ticket.',                          NOW() - INTERVAL 1 DAY),
  (@t5, @admin,  'assignment', 'Assigned to Yevrah Technician.',                 NOW() - INTERVAL 1 DAY + INTERVAL 1 HOUR),

  (@t6, @rens,   'created',    'Submitted the ticket.',                          NOW() - INTERVAL 22 HOUR),
  (@t6, @admin,  'assignment', 'Assigned to Yevrah Technician.',                 NOW() - INTERVAL 20 HOUR),
  (@t6, @yevrah, 'status',     'Changed status from Assigned to In Progress.',   NOW() - INTERVAL 18 HOUR),

  (@t7, @rens,   'created',    'Submitted the ticket.',                          NOW() - INTERVAL 3 HOUR);

-- ------------------------------------------------------------
--  Notifications (still unread, so the bell shows them)
-- ------------------------------------------------------------
INSERT INTO notifications (user_id, ticket_id, message, created_at) VALUES
  (@gerald, @t4, CONCAT('You were assigned to TKT-', LPAD(@t4, 5, '0'), ': Enrollment page shows an error when saving subjects'), NOW() - INTERVAL 2 DAY + INTERVAL 1 HOUR),
  (@yevrah, @t5, CONCAT('You were assigned to TKT-', LPAD(@t5, 5, '0'), ': Campus Wi-Fi keeps disconnecting'),                    NOW() - INTERVAL 1 DAY + INTERVAL 1 HOUR),
  (@yevrah, @t6, CONCAT('You were assigned to TKT-', LPAD(@t6, 5, '0'), ': Cannot sign in to the LMS with my school email'),      NOW() - INTERVAL 20 HOUR),
  (@kurt,   @t3, CONCAT('You were assigned to TKT-', LPAD(@t3, 5, '0'), ': Cannot view grades in the student portal'),            NOW() - INTERVAL 3 DAY + INTERVAL 2 HOUR),
  (@rens,   @t2, CONCAT('Your ticket TKT-', LPAD(@t2, 5, '0'), ' was resolved. Please confirm the fix.'),                         NOW() - INTERVAL 4 DAY),
  (@rens,   @t3, CONCAT('Your ticket TKT-', LPAD(@t3, 5, '0'), ' is now In Progress.'),                                           NOW() - INTERVAL 2 DAY),
  (@rens,   @t6, CONCAT('Your ticket TKT-', LPAD(@t6, 5, '0'), ' is now In Progress.'),                                           NOW() - INTERVAL 18 HOUR),
  (@admin,  @t7, CONCAT('New ticket TKT-', LPAD(@t7, 5, '0'), ' from Rens Acuña: School software installer will not run'),         NOW() - INTERVAL 3 HOUR);
