-- Scriptor demo content overlay.
--
-- Runs AFTER `bin/scriptor install` has already created the Pages +
-- Users categories, their fields, an admin user (item id=1), and a
-- minimal Home page (item id=2). This file adds the seven extra
-- demo pages that make the Docker quickstart feel like a real site:
-- Articles + a child post, Contact, and the Footer cluster (Footer
-- Pages container, Legal notice, Privacy, Help).
--
-- IDs start at 3 (1 = admin, 2 = install's Home) and parent
-- references are renumbered accordingly:
--   Articles (id=3)            parent=0
--   Get started (id=4)         parent=3   (child of Articles)
--   Contact (id=5)             parent=0
--   Legal notice (id=6)        parent=7   (child of Footer Pages)
--   Footer Pages (id=7)        parent=0
--   Privacy statement (id=8)   parent=7
--   Getting Help (id=9)        parent=7
--
-- items_fts rows are written explicitly because iManager's FTS is
-- non-content-mode (populated by SqliteItemRepository::syncFts() from
-- PHP, not via SQL triggers); a raw INSERT into items would not be
-- indexed otherwise. `vendor/bin/imanager fts:rebuild` recovers if
-- this file ever drifts from items.
--
-- File rows point at the uploads tarball that the entrypoint extracts
-- alongside this seed; their stored path is opaque to the resolver
-- (see Files/UploadHandler), so it does not need to match the legacy
-- `<categoryId>.<itemId>.<fieldId>` layout that iManager 1.x used.

PRAGMA foreign_keys = OFF;
BEGIN TRANSACTION;

INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (3, 1, 'Articles', NULL, 2, 1, '{"menu_title":"Articles","slug":"articles","template":"blog","parent":0,"pagetype":"1","content":"<!--\nThis page functions as a container for blog posts. Any page within this container is identified as a blog post and utilized by the base template. The base template uses these pages to display blog content in a consistent and efficient manner.\n-->","images":null}', 1625453680, 1777868475);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (4, 1, 'Get started with Scriptor', NULL, 3, 1, '{"slug":"get-started-with-scriptor","parent":3,"pagetype":"1","menu_title":"Get started","content":"Welcome. Edit me in `/editor/`.","template":"default","images":[]}', 1638895123, 1777876312);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (5, 1, 'Contact', NULL, 4, 1, '{"menu_title":"Contact","slug":"contact","template":"contact","parent":0,"pagetype":"1","content":"The basic theme comes with a built-in contact form, so that your site visitors can make all sorts of queries and contact. The contact form does not support SMTP by default, but you may easily extend it with e.g. Scriptor''s SMailer module, which does.","images":[]}', 1639166163, 1777956578);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (6, 1, 'Legal notice', NULL, 6, 1, '{"menu_title":"Legal notice","slug":"legal-notice","template":"","parent":7,"pagetype":"1","content":"Edit this page in `/editor/` to put your legal notice here.","images":null}', 1641119858, 1777793722);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (7, 1, 'Footer Pages', NULL, 5, 1, '{"menu_title":"Footer Pages","slug":"some-pages","template":"","parent":0,"pagetype":"1","content":"This page is a container for all pages that should appear in the footer navigation.","images":null}', 1641128288, 1777956578);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (8, 1, 'Privacy statement', NULL, 7, 1, '{"menu_title":"Privacy statement","slug":"privacy-statement","template":"","parent":7,"pagetype":"1","content":"Edit this page in `/editor/` to put your privacy statement here.","images":null}', 1641501213, 1777793722);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (9, 1, 'Getting Help', NULL, 8, 1, '{"menu_title":"Help","slug":"help","template":"","parent":7,"pagetype":"1","content":"Documentation lives at https://scriptor-cms.dev/. Open an issue at https://github.com/bigin/Scriptor/issues for bugs.","images":null}', 1641818049, 1777793722);

INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (3, 'Articles', '', 'Articles  Articles articles blog 0 1');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (4, 'Get started with Scriptor', '', 'Get started with Scriptor  get-started-with-scriptor 3 1 Get started default Welcome. Edit me in `/editor/`.');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (5, 'Contact', '', 'Contact  Contact contact contact 0 1 The basic theme comes with a built-in contact form, so that your site visitors can make all sorts of queries and contact. The contact form does not support SMTP by default, but you may easily extend it with e.g. Scriptor''s SMailer module, which does.');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (6, 'Legal notice', '', 'Legal notice  Legal notice legal-notice  7 1 Edit this page in `/editor/` to put your legal notice here.');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (7, 'Footer Pages', '', 'Footer Pages  Footer Pages some-pages  0 1 This page is a container for all pages that should appear in the footer navigation.');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (8, 'Privacy statement', '', 'Privacy statement  Privacy statement privacy-statement  7 1 Edit this page in `/editor/` to put your privacy statement here.');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (9, 'Getting Help', '', 'Getting Help  Help help  7 1 Documentation lives at https://scriptor-cms.dev/.');

INSERT INTO "files" ("id", "item_id", "field_id", "name", "path", "mime", "size", "width", "height", "position", "created", "title") VALUES (1, 4, 7, 'aydin-hassan-NZrg1OCPneM-unsplash.jpeg', '1.3.6/aydin-hassan-NZrg1OCPneM-unsplash.jpeg', 'image/jpeg', 239378, 1200, 537, 0, 1777876312, 'Photo by [Aydin Hassan](https://unsplash.com/@aydinhassan?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText) on [Unsplash](https://unsplash.com/?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText)');
INSERT INTO "files" ("id", "item_id", "field_id", "name", "path", "mime", "size", "width", "height", "position", "created", "title") VALUES (2, 5, 7, 'simon-berger-SD68VmEjzdA-unsplash.jpeg', '1.6.6/simon-berger-SD68VmEjzdA-unsplash.jpeg', 'image/jpeg', 151036, 1200, 409, 0, 1777876312, 'Photo by [Simon Berger](https://unsplash.com/@8moments?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText) on [Unsplash](https://unsplash.com/?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText)');

COMMIT;
