PRAGMA foreign_keys = OFF;
BEGIN TRANSACTION;
-- Table: categories
CREATE TABLE categories (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    name      TEXT    NOT NULL UNIQUE,
    slug      TEXT    NOT NULL UNIQUE,
    position  INTEGER NOT NULL DEFAULT 0,
    created   INTEGER NOT NULL,
    updated   INTEGER NOT NULL
);
INSERT INTO "categories" ("id", "name", "slug", "position", "created", "updated") VALUES (1, 'Pages', 'pages', 1, 1518943944, 1777793722);
INSERT INTO "categories" ("id", "name", "slug", "position", "created", "updated") VALUES (2, 'Users', 'users', 2, 1519050567, 1777793722);

-- Table: fields
CREATE TABLE fields (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    name        TEXT    NOT NULL,
    label       TEXT,
    type        TEXT    NOT NULL,
    position    INTEGER NOT NULL DEFAULT 0,
    required    INTEGER NOT NULL DEFAULT 0,
    indexed     INTEGER NOT NULL DEFAULT 0,
    searchable  INTEGER NOT NULL DEFAULT 0,
    config      TEXT    NOT NULL DEFAULT '{}' CHECK(json_valid(config)),
    created     INTEGER NOT NULL,
    updated     INTEGER NOT NULL,
    UNIQUE(category_id, name)
);
INSERT INTO "fields" ("id", "category_id", "name", "label", "type", "position", "required", "indexed", "searchable", "config", "created", "updated") VALUES (1, 1, 'slug', 'Slug', 'slug', 1, 0, 0, 0, '{"accept_types":null,"max_number_of_files":null,"default":null,"options":[],"info":null,"minimum":0,"maximum":0,"cssclass":null}', 1518943944, 1777793722);
INSERT INTO "fields" ("id", "category_id", "name", "label", "type", "position", "required", "indexed", "searchable", "config", "created", "updated") VALUES (2, 1, 'parent', 'Parent', 'text', 2, 0, 0, 0, '{"accept_types":null,"max_number_of_files":null,"default":null,"options":[],"info":null,"minimum":0,"maximum":0,"cssclass":null}', 1518943944, 1777793722);
INSERT INTO "fields" ("id", "category_id", "name", "label", "type", "position", "required", "indexed", "searchable", "config", "created", "updated") VALUES (3, 1, 'pagetype', 'Page Type', 'text', 3, 0, 0, 0, '{"accept_types":null,"max_number_of_files":null,"default":null,"options":[],"info":null,"minimum":0,"maximum":0,"cssclass":null}', 1518943944, 1777793722);
INSERT INTO "fields" ("id", "category_id", "name", "label", "type", "position", "required", "indexed", "searchable", "config", "created", "updated") VALUES (4, 1, 'menu_title', 'Enter menu title', 'text', 4, 0, 0, 0, '{"accept_types":null,"max_number_of_files":null,"default":null,"options":[],"info":null,"minimum":0,"maximum":0,"cssclass":null}', 1639155123, 1777793722);
INSERT INTO "fields" ("id", "category_id", "name", "label", "type", "position", "required", "indexed", "searchable", "config", "created", "updated") VALUES (5, 1, 'content', 'Content', 'longtext', 5, 0, 0, 0, '{"accept_types":null,"max_number_of_files":null,"default":null,"options":[],"info":null,"minimum":0,"maximum":0,"cssclass":null}', 1518943944, 1777793722);
INSERT INTO "fields" ("id", "category_id", "name", "label", "type", "position", "required", "indexed", "searchable", "config", "created", "updated") VALUES (6, 1, 'template', 'Page template', 'text', 6, 0, 0, 0, '{"accept_types":null,"max_number_of_files":null,"default":null,"options":[],"info":null,"minimum":0,"maximum":0,"cssclass":null}', 1581357741, 1777793722);
INSERT INTO "fields" ("id", "category_id", "name", "label", "type", "position", "required", "indexed", "searchable", "config", "created", "updated") VALUES (7, 1, 'images', 'Images', 'fileupload', 7, 0, 0, 0, '{"accept_types":"gif|jpe?g|png","max_number_of_files":null,"default":null,"options":[],"info":null,"minimum":0,"maximum":0,"cssclass":null}', 1595519012, 1777793722);
INSERT INTO "fields" ("id", "category_id", "name", "label", "type", "position", "required", "indexed", "searchable", "config", "created", "updated") VALUES (8, 2, 'role', NULL, 'text', 1, 0, 0, 0, '{"default":null,"options":[],"info":null,"minimum":0,"maximum":0,"cssclass":null}', 1519050567, 1777793722);
INSERT INTO "fields" ("id", "category_id", "name", "label", "type", "position", "required", "indexed", "searchable", "config", "created", "updated") VALUES (9, 2, 'email', NULL, 'text', 2, 0, 0, 0, '{"default":null,"options":[],"info":null,"minimum":0,"maximum":0,"cssclass":null}', 1519050567, 1777793722);
INSERT INTO "fields" ("id", "category_id", "name", "label", "type", "position", "required", "indexed", "searchable", "config", "created", "updated") VALUES (10, 2, 'password', NULL, 'password', 3, 0, 0, 0, '{"default":null,"options":[],"info":null,"minimum":0,"maximum":0,"cssclass":null}', 1519050567, 1777793722);

-- Table: files
CREATE TABLE files (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id   INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
    field_id  INTEGER NOT NULL REFERENCES fields(id) ON DELETE CASCADE,
    name      TEXT    NOT NULL,
    path      TEXT    NOT NULL,
    mime      TEXT    NOT NULL,
    size      INTEGER NOT NULL,
    width     INTEGER NOT NULL DEFAULT 0,
    height    INTEGER NOT NULL DEFAULT 0,
    position  INTEGER NOT NULL DEFAULT 0,
    created   INTEGER NOT NULL
, title TEXT NOT NULL DEFAULT '');
INSERT INTO "files" ("id", "item_id", "field_id", "name", "path", "mime", "size", "width", "height", "position", "created", "title") VALUES (13, 1, 7, 'myxa69-UvZqemnwH4c-unsplash.jpeg', '1.1.6/myxa69-UvZqemnwH4c-unsplash.jpeg', 'image/jpeg', 48144, 1200, 463, 0, 1777876312, 'Photo by [Maria Travina](https://unsplash.com/@myxa69?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText) on [Unsplash](https://unsplash.com/@myxa69?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText)');
INSERT INTO "files" ("id", "item_id", "field_id", "name", "path", "mime", "size", "width", "height", "position", "created", "title") VALUES (14, 3, 7, 'aydin-hassan-NZrg1OCPneM-unsplash.jpeg', '1.3.6/aydin-hassan-NZrg1OCPneM-unsplash.jpeg', 'image/jpeg', 239378, 1200, 537, 0, 1777876312, 'Photo by [Aydin Hassan](https://unsplash.com/@aydinhassan?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText) on [Unsplash](https://unsplash.com/?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText)');
INSERT INTO "files" ("id", "item_id", "field_id", "name", "path", "mime", "size", "width", "height", "position", "created", "title") VALUES (15, 4, 7, 'simon-berger-SD68VmEjzdA-unsplash.jpeg', '1.6.6/simon-berger-SD68VmEjzdA-unsplash.jpeg', 'image/jpeg', 151036, 1200, 409, 0, 1777876312, 'Photo by [Simon Berger](https://unsplash.com/@8moments?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText) on [Unsplash](https://unsplash.com/?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText)');

-- Table: items
CREATE TABLE items (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    name        TEXT,
    label       TEXT,
    position    INTEGER NOT NULL DEFAULT 0,
    active      INTEGER NOT NULL DEFAULT 1,
    data        TEXT    NOT NULL DEFAULT '{}' CHECK(json_valid(data)),
    created     INTEGER NOT NULL,
    updated     INTEGER NOT NULL
);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (1, 1, 'Scriptor''s Demo Page', NULL, 1, 1, '{"slug":"scriptors-demo-page","parent":0,"pagetype":"1","menu_title":"Home","content":"Lorem markdownum notam sibila Argolicis habet, manibus illa, et. Fera vestigia\r\nmetuunt annos ignibus *commota quippe*. Graiumque tua vix volanti Diomedeos\r\nlacrimis.\r\n\r\n### Urbe imbres qui laesaque\r\n\r\nVestigia pallore. Matre quid dolore Acoetes sit videns frustraque retenta mare,\r\ncaelestibus conamina coryli veloces.\r\n\r\n> Mariti cur: ante *causa* rigorem errabant gravitate imagine quotiensque amor\r\n> secundi cruribus [adclivi sibi est](http:\/\/caelestia.com\/enim) apri dedimus\r\n> quinos. Mihi quoque gemelliparae factum gramen, alto nomina abest nostro,\r\n> illic extinctum regia, languescuntque. Anguis qui laesaque ciet nam lapsae,\r\n> *fortuna* manus at quam; in.\r\n\r\n### Highlighted code blocks\r\n\r\n```php\r\necho $page->parsedown->text(\r\n    $page->content\r\n);\r\n```\r\n\r\n### Quibus sine velox\r\n\r\nEsse requiem pedes sub freta modo. Mortis **ieiunia furori animalia** credimus,\r\nterras per guttae paucaque coniuge in solas et illa sustinet? Antris proxima\r\ntantum lapidis Tonantis unde. Quoque sororis nivibus limine cognatumque\r\npingebat, matre concentu Aeolides Cancri, ipsa terrae semper feci sanguine\r\nexternos.\r\n\r\n- Acernas crescere et exitus\r\n- Silva deum Amphion tamen\r\n- Soror quondam contigit\r\n- Hamata modo quaerens ut velatam obmutuit decusque\r\n- Quam haererem aestatem ventos\r\n\r\nIncingere Aoniis celat imagine digitis et iram, cum est diu violave oculis passu\r\nmeo. Sume in Cinyran aerane altrice amnis nefas gerebat properatis **orbem**\r\nsicco honorem ille bis. Repulsa quantaque aderat in relictas memoraverat arma\r\ndesierant umerique, suo cum in nymphae signa praetemptatque suorum genetrici?\r\nFieres sequitur quaeris Diana una parens, *te origo*; quid. Capherea liquitur\r\nmediis deerat facies agat quercu donavi Clara: Erinys Dies.   \r\n...","template":"default"}', 1519052101, 1778356441);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (2, 1, 'Articles', NULL, 2, 1, '{"menu_title":"Articles","slug":"articles","template":"blog","parent":0,"pagetype":"1","content":"<!--\nThis page functions as a container for blog posts. Any page within this container is identified as a blog post and utilized by the base template. The base template uses these pages to display blog content in a consistent and efficient manner.\n-->","images":null}', 1625453680, 1777868475);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (3, 1, 'Get started with Scriptor', NULL, 3, 1, '{"slug":"get-started-with-scriptor","parent":2,"pagetype":"1","menu_title":"Get started","content":"preserved","template":"default","images":[]}', 1638895123, 1777876312);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (4, 1, 'Contact', NULL, 4, 1, '{"menu_title":"Contact","slug":"contact","template":"contact","parent":0,"pagetype":"1","content":"The basic theme comes with a built-in contact form, so that your site visitors can make all sorts of queries and contact. The contact form does not support SMTP by default, but you may easily extend it with e.g. Scriptor''s SMailer module, which does.","images":[]}', 1639166163, 1777956578);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (5, 1, 'Legal notice', NULL, 6, 1, '{"menu_title":"Legal notice","slug":"legal-notice","template":"","parent":6,"pagetype":"1","content":"...","images":null}', 1641119858, 1777793722);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (6, 1, 'Footer Pages', NULL, 5, 1, '{"menu_title":"Footer Pages","slug":"some-pages","template":"","parent":0,"pagetype":"1","content":"This page is a container for all pages that should appear in the footer navigation.","images":null}', 1641128288, 1777956578);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (7, 1, 'Privacy statement', NULL, 7, 1, '{"menu_title":"Privacy statement","slug":"privacy-statement","template":"","parent":6,"pagetype":"1","content":"...","images":null}', 1641501213, 1777793722);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (8, 1, 'Getting Help', NULL, 8, 1, '{"menu_title":"Help","slug":"help","template":"","parent":6,"pagetype":"1","content":"...","images":null}', 1641818049, 1777793722);
INSERT INTO "items" ("id", "category_id", "name", "label", "position", "active", "data", "created", "updated") VALUES (9, 2, 'admin', '', 1, 1, '{"role":"siteadmin","email":"gmail@chuck.norris.com","password":{"__class":"\\Imanager\\PasswordFieldValue","password":"$2y$10$gQdxIHrGm\/ia4RFzkoPXc.YmdpK87fbKGQIz.dXXhQuz0hwV4P\/C2","salt":""}}', 1519050932, 1777810723);

-- Table: items_fts
CREATE VIRTUAL TABLE items_fts USING fts5(
    name,
    label,
    body,
    tokenize = 'unicode61 remove_diacritics 2'
);
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (1, 'Scriptor''s Demo Page', '', 'Scriptor''s Demo Page  scriptors-demo-page 0 1 Home Lorem markdownum notam sibila Argolicis habet, manibus illa, et. Fera vestigia
metuunt annos ignibus *commota quippe*. Graiumque tua vix volanti Diomedeos
lacrimis.

### Urbe imbres qui laesaque

Vestigia pallore. Matre quid dolore Acoetes sit videns frustraque retenta mare,
caelestibus conamina coryli veloces.

> Mariti cur: ante *causa* rigorem errabant gravitate imagine quotiensque amor
> secundi cruribus [adclivi sibi est](http://caelestia.com/enim) apri dedimus
> quinos. Mihi quoque gemelliparae factum gramen, alto nomina abest nostro,
> illic extinctum regia, languescuntque. Anguis qui laesaque ciet nam lapsae,
> *fortuna* manus at quam; in.

### Highlighted code blocks

```php
echo $page->parsedown->text(
    $page->content
);
```

### Quibus sine velox

Esse requiem pedes sub freta modo. Mortis **ieiunia furori animalia** credimus,
terras per guttae paucaque coniuge in solas et illa sustinet? Antris proxima
tantum lapidis Tonantis unde. Quoque sororis nivibus limine cognatumque
pingebat, matre concentu Aeolides Cancri, ipsa terrae semper feci sanguine
externos.

- Acernas crescere et exitus
- Silva deum Amphion tamen
- Soror quondam contigit
- Hamata modo quaerens ut velatam obmutuit decusque
- Quam haererem aestatem ventos

Incingere Aoniis celat imagine digitis et iram, cum est diu violave oculis passu
meo. Sume in Cinyran aerane altrice amnis nefas gerebat properatis **orbem**
sicco honorem ille bis. Repulsa quantaque aderat in relictas memoraverat arma
desierant umerique, suo cum in nymphae signa praetemptatque suorum genetrici?
Fieres sequitur quaeris Diana una parens, *te origo*; quid. Capherea liquitur
mediis deerat facies agat quercu donavi Clara: Erinys Dies.   
... default');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (2, 'Articles', '', 'Articles  Articles articles blog 0 1 <!--
This page functions as a container for blog posts. Any page within this container is identified as a blog post and utilized by the base template. The base template uses these pages to display blog content in a consistent and efficient manner.
-->');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (3, 'Get started with Scriptor', '', 'Get started with Scriptor  get-started-with-scriptor 2 1 Get started preserved default');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (4, 'Contact', '', 'Contact  Contact contact contact 0 1 The basic theme comes with a built-in contact form, so that your site visitors can make all sorts of queries and contact. The contact form does not support SMTP by default, but you may easily extend it with e.g. Scriptor''s SMailer module, which does.');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (5, 'Legal notice', '', 'Legal notice  Legal notice legal-notice  8 1 ...');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (6, 'Footer Pages', '', 'Footer Pages  Footer Pages some-pages  0 1 This page is a container for all pages that should appear in the footer navigation.');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (7, 'Privacy statement', '', 'Privacy statement  Privacy statement privacy-statement  8 1 ...');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (8, 'Getting Help', '', 'Getting Help  Help help  8 1 ...');
INSERT INTO "items_fts" ("rowid", "name", "label", "body") VALUES (9, 'admin', '', 'admin  siteadmin gmail@chuck.norris.com \Imanager\PasswordFieldValue $2y$10$gQdxIHrGm/ia4RFzkoPXc.YmdpK87fbKGQIz.dXXhQuz0hwV4P/C2 ');

-- Table: schema_version
CREATE TABLE schema_version (
                version     INTEGER PRIMARY KEY,
                description TEXT    NOT NULL,
                applied_at  INTEGER NOT NULL
            );
INSERT INTO "schema_version" ("version", "description", "applied_at") VALUES (1, 'initial', 1777793722);
INSERT INTO "schema_version" ("version", "description", "applied_at") VALUES (2, 'fts', 1777793722);
INSERT INTO "schema_version" ("version", "description", "applied_at") VALUES (3, 'files', 1777793722);
INSERT INTO "schema_version" ("version", "description", "applied_at") VALUES (4, 'files title', 1777820911);

COMMIT;
