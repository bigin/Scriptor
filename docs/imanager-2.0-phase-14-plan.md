# Phase 14 — Scriptor-Integration: Detail-Plan

> Detail-Plan für den Scriptor-seitigen Cutover auf iManager 2.0.
> Begleitend zum iManager-Master-Plan
> ([`docs/imanager-2.0-plan.md`](https://github.com/bigin/imanager/blob/main/docs/imanager-2.0-plan.md)
> im `bigins/imanager`-Repo); Phase 14 dort ist das Release-Gate
> "First-Consumer-Cutover" — dieses Dokument hier ist die konkrete
> Umsetzung dieses Cutovers für Scriptor.
>
> Stand: 2026-05-02 (importiert aus dem iManager-Repo am 2026-05-13;
> die Library-Doku darf keinen einzelnen Consumer als kanonisch
> ausweisen — Scriptor-spezifische Pläne leben hier).

---

## 1. Vision

Scriptor 2.0 läuft vollständig auf iManager 2.0:

- Das eingebettete `imanager/`-Verzeichnis in Scriptor ist gelöscht.
- Scriptor zieht `bigins/imanager:^2.0` als Composer-Dependency.
- Editor-UI, Frontend-Rendering, Auth, Uploads, Pagination, Cache —
  alles fließt durch die Phase-2-bis-13-Schichten von iManager 2.0.
- Funktionsparität zu Scriptor 1.x. Keine neuen Features in Phase 14.

---

## 2. Leitprinzipien

1. **Inkrementell, kein Big-Bang.** Jede Sub-Phase endet mit einem
   funktionierenden Editor-Zustand.
2. **Reale Daten als Test-Vehikel.** Bestandsdaten werden früh per
   Migration-Tool überführt; Phase 14 entwickelt gegen diese migrierte DB.
3. **Funktionsparität, nichts mehr.** Refactor ist Refactor — neue Features
   landen erst nach Phase 17 (Release).
4. **Rollback-Pfad immer vorhanden.** Bis zur letzten Sub-Phase bleibt
   `master` (1.x) funktionsfähig; Daten-Backup vor jedem destruktiven
   Schritt.
5. **Hook-System wird gebrückt, nicht neu erfunden.** Bestehende
   Scriptor-Hooks bleiben adressierbar; intern werden sie zu Domain-Events
   übersetzt (Phase 14e).

---

## 3. Repo- und Branching-Strategie

- **Bestehender `bigin/Scriptor`-Repo**, kein neuer Repo.
- **Neuer long-lived Branch `imanager-2.0`** in Scriptor.
- `master` bleibt 1.x — keine Weiterentwicklung dort.
- Sub-Phase-PRs landen gegen `imanager-2.0`, nicht gegen `master`.
- Squash-Merge wie bei iManager.
- Bei Phase 14f: Cutover-Entscheidung — entweder `imanager-2.0` → `master`
  per Force-Push (saubere Geschichte) oder Merge-Commit
  (gemischte Geschichte). Empfehlung: **`master` löschen, `imanager-2.0` zu
  `master` umbenennen**, alter `master` als Tag `1.x-final` aufgehoben.

---

## 4. Vorbedingungen (vor Phase 14a)

| | Status |
|---|---|
| Phase 0–13 in iManager abgeschlossen | ✅ aktuell |
| Phase 15 (CLI) in iManager fertig | ✅ done (PR #17 merged 2026-05-03) |
| Backup der Scriptor-`data/`-Verzeichnisse | ⬜ vor erstem `migrate` |
| Migration-Dry-Run auf Daten-Kopie | ⬜ |
| Migration-Verifikation per SQLite-CLI / Smoke-Read | ⬜ |
| Echte Migration mit Backup als Fallback | ⬜ |

---

## 5. Migrations-Workflow (einmalig, vor 14a)

```
# 1. Backup
cp -a /pfad/zu/Scriptor/data /pfad/zu/Scriptor/data.bak.YYYY-MM-DD

# 2. Dry-run (im iManager-Container)
vendor/bin/imanager migrate from-v1 \
  --source /pfad/zu/Scriptor/data \
  --target /tmp/imanager-test.db \
  --dry-run

# 3. Report prüfen, ggf. Edge-Cases beheben (z.B. Items mit unbekanntem Field-Type)

# 4. Echte Migration
vendor/bin/imanager migrate from-v1 \
  --source /pfad/zu/Scriptor/data \
  --target /pfad/zu/Scriptor/data/imanager.db

# 5. Verifikation
sqlite3 /pfad/zu/Scriptor/data/imanager.db <<EOF
.headers on
SELECT count(*) AS categories FROM categories;
SELECT count(*) AS fields FROM fields;
SELECT count(*) AS items FROM items;
SELECT id, name, slug FROM categories;
EOF

# 6. uploads/ ist bereits ins neue Layout kopiert (Migrator macht das).
# Original-Dateien bleiben in data.bak.YYYY-MM-DD als Notbremse.
```

Erst danach beginnt Sub-Phase 14a.

---

## 6. Sub-Phasen-Plan

Jede Sub-Phase landet als eigenständiger PR auf `imanager-2.0`.

### Status-Tracking

| Sub-Phase | Titel | Status | PR(s) in Scriptor |
|---|---|---|---|
| 14a   | Composer-Integration & DI-Bootstrap         | ✅ done | #18 |
| 14b-1 | Frontend-Skeleton (Site/Page/PageRepository) | ✅ done | #19 |
| 14b-2 | BasicTheme rewrite                          | ✅ done | #21 |
| 14b-3 | Image pipeline (ImageUrlBuilder)            | ✅ done | #22 |
| 14c-1 | auth (login/logout, CSRF)                   | ✅ done | #20 |
| 14c-2 | pages (CRUD + Markdown preview)             | ✅ done | #24 |
| 14c-3 | profile (collapses original 14c-3 + 14c-6)  | ✅ done | #26 |
| 14c-4 | settings (informational)                    | ✅ done | #27 |
| 14c-5 | install (site/modules manager)              | ✅ done | #28 |
| 14d-1 | upload endpoint + FilePond vendoring        | ✅ done | #29 |
| 14d-2 | pages-uploads (FilePond on edit form)       | ✅ done | #30 |
| 14d-3 | frontend renders FileRepository             | ✅ done | #31 |
| 14e-2 | listeners (file cleanup + cache invalidation) | ✅ done | #32 |
| 14f   | Cleanup, README/CHANGELOG, perf-smoke       | ✅ done | #36 |

Plus inline hotfix PRs that landed alongside the sub-phases (single-entry
router, FilePond field name, legacy-image preview, etc.) and the post-14f
`feature/image-titles` PR that brought 1.x image-caption parity onto the
2.0 stack.

> **iManager-side companion:** Phase 14e-1 (PSR-14 dispatcher + storage
> emits) shipped on `bigins/imanager` `main` ahead of 14e-2;
> `feature/file-title-column` (schema migration `0004` + Domain/Repo
> support for the per-file title) followed during Phase 14f.

---

### 14a — Composer-Integration & DI-Bootstrap

**Ziel:** iManager 2.0 in Scriptor installieren und der Container hochfahren,
**ohne** User-facing Funktionalität anzufassen.

**Scope:**
- `composer.json` in Scriptor: `bigins/imanager: ^2.0` (per Path-Repo
  während Entwicklung, später Packagist).
- `boot.php` umbauen: alter `imanager()`-Bootstrap raus,
  `Imanager\Bootstrap::boot([...])` rein. Container-Instanz an einer
  zugänglichen Stelle ablegen (Service-Locator-Singleton oder Pass-Through).
- Smoke-Page (`/test-bootstrap.php` o.ä.): Container holen, Config holen,
  ein `categories()->findAll()` machen → JSON-Dump.
- `data/imanager.db` ist die migrierte DB aus §5.
- Altes `Scriptor/imanager/`-Verzeichnis: **noch nicht löschen** (Rollback-Pfad).

**Acceptance:**
- `composer install` läuft.
- Smoke-Page rendert ohne Fehler.
- Smoke-Page zeigt mindestens eine Category aus der migrierten DB.

**Was NICHT in 14a:**
- Frontend-Themes, Editor-Module — alle bleiben auf 1.x bis 14b/14c sie
  einzeln umstellen.

---

### 14b — Frontend-Rendering auf neuer API

**Ziel:** der **öffentliche** Scriptor-Site (Themes, Pages, Site-Klasse)
rendert aus iManager 2.0. Editor bleibt vorerst 1.x.

**Scope:**
- `Scriptor\Core\Site` umbauen: `imanager()`-Aufrufe weg, dafür
  `$container->get(Storage::class)`.
- `Scriptor\Core\Page` umbauen: dünner Wrapper um `Imanager\Domain\Item`
  oder direkt durchreichen.
- Theme-Accessor-Pattern bleibt: `$site->page(slug: 'foo')`,
  `$site->pages('blog')` etc.
- Slug → Page-Lookup: über `Storage->items()->query(...)` mit
  `where('slug', '=', $slug)`.
- Pagination: `Imanager\Templating\PaginationRenderer` (Phase 11).
- Markdown-Rendering: `Imanager\Validation\Sanitizer::markdown()`.
- Cache: `Imanager\Cache\FilesystemCache` für Theme-Snippets.

**Acceptance:**
- `curl https://demo/` liefert HTML mit erwartbarem Inhalt.
- `curl https://demo/scriptors-demo-page` rendert die migrierte Seite.
- `curl https://demo/blog/page2/` paginiert korrekt (bei mehr als
  `maxItemsPerPage` Items).
- Snapshot-Vergleich: `wget -r` vor und nach 14b liefert identische HTML
  bis auf Whitespace.

**Was NICHT in 14b:**
- Editor-Backend bleibt unverändert.
- File-Uploads laufen noch über 1.x-Pfad.

---

### 14c — Editor-Module (Sub-Sub-Phasen)

**Ziel:** Scriptor's `editor/modules/*` einzeln auf iManager 2.0 umbauen.
Jedes Modul landet als eigener PR.

**Reihenfolge** (nach Risiko aufsteigend, einfach zuerst):

| | Modul | PR | Hinweise |
|---|---|---|---|
| 14c-1 | `auth` | `phase-14c1-auth` | Login/Logout, CSRF via `Imanager\Http\Csrf` (Phase 10), Password-Hash via `PasswordFieldType` (Phase 7c) |
| 14c-2 | `pages` | `phase-14c2-pages` | Page-Liste, Page-Edit, Page-Save — der größte und wichtigste Modul |
| 14c-3 | `profile` | `phase-14c3-profile` | User-Profile-Edit (eigener User: name, email, password). Ersetzt den ursprünglich getrennten `users`/`profile`-Plan: Legacy-Scriptor hat kein eigenständiges User-CRUD-Modul, daher pro §9 (Funktionsparität) nur `profile` portiert. |
| 14c-4 | `settings` | `phase-14c4-settings` | Globale Settings — typischerweise einfache Form, low-risk |
| 14c-5 | `install` | `phase-14c5-install` | Modul-Install/Uninstall — meta-System, vorsichtig |

Pro Modul:
- 1.x-Code (`Item::save()`, `Pages::getPage()`, …) durch 2.0-Repository-Calls
  ersetzen.
- Form-Rendering: `FieldTypePlugin::render()` aus dem Registry (Phase 7).
- Validation: `FieldTypePlugin::validate()`-Loop, Errors aggregieren.
- Templates: `Imanager\Templating\TemplateRenderer` für simple Substitution.

**Acceptance pro Modul:**
- Modul erscheint im Admin.
- Alle CRUD-Pfade durchspielbar (manuell).
- Keine 1.x-iManager-Symbole mehr im Modul-Code.

Das `parsedown`-Modul aus 1.x **fällt komplett weg** — `Sanitizer::markdown()`
übernimmt.

---

### 14d — Upload-Pipeline (FilePond + Endpoint + Plugins)

**Ziel:** File-Uploads funktionieren End-to-End über FilePond + Phase-13's
`UploadHandler`.

**Scope:**
- FilePond JS+CSS vendored unter `editor/theme/scripts/filepond/`
  (Scriptor hat keinen Build-Step heute — Vendoring ist die einzige Option,
  bis sich das ändert).
- HTTP-Endpoint `/editor/api/upload` (oder ähnlich): nimmt Multipart,
  ruft `UploadHandler::handle()`, antwortet JSON mit `{ "fileId": 123, "url": "..." }`.
- Field-Plugins (Phase 7c-Stubs auf echte Implementierungen heben):
  - `FileuploadFieldType`: `validate()` nimmt `list<int>` (file ids), check
    via `FileRepository::find()`. `render()` zeigt FilePond-Container plus
    Hidden-Inputs für IDs.
  - `ImageuploadFieldType`: wie Fileupload + Thumbnail-URL via
    `ImageProcessor` zur Editor-Vorschau.
  - `FilepickerFieldType`: zeigt `<select>` der existierenden Files für
    diesen Item/Field-Slot.
- File-Listing in Item-Edit-Form: `FileRepository::findByItemAndField()`.

**Acceptance:**
- Upload eines Bildes via Editor klappt.
- `files`-Tabelle hat den Datensatz.
- Tatsächliche Datei landet in `data/uploads/<itemId>/<fieldId>/...`.
- Thumbnail wurde generiert (für Imageupload).
- Re-Edit des Items zeigt die Datei mit Vorschau.
- Datei lässt sich entfernen (sowohl aus `files`-Tabelle als auch von Disk).

---

### 14e — Domain-Event-Listener-Verkabelung

**Ziel:** Side-Effects (Cache-Invalidation, File-Cleanup) reagieren auf
Domain-Events statt auf 1.x-Hooks.

**Scope:**
- Event-Dispatcher: entweder `psr/event-dispatcher` (winzige Dep) oder
  eigener Mini-Dispatcher. Empfehlung: PSR-14 — Standard, austauschbar.
- Storage-Layer modifizieren: nach erfolgreichem Save/Delete einen Event
  feuern. Storage bekommt eine optionale `EventDispatcherInterface`-
  Dependency, fallback NullDispatcher (ändert nicht die Phase-3-Signaturen).
- Listener:
  - `ItemUpdated` / `ItemCreated` / `ItemDeleted` → `Cache::clear()` für die
    Page-URL (oder per Tag, wenn wir Tag-Support haben — sonst global).
  - `ItemDeleted` → `FileRepository::findByItem()` + `FileStorage::delete()`
    für jede Datei (bevor der FK-Cascade die Metadaten-Rows wegputzt).
    Reihenfolge wichtig: Listener läuft **vor** dem eigentlichen DELETE.
  - `CategoryDeleted` → cascade auf Items + deren Files.
- Hook-Kompatibilitäts-Bridge: bestehende Scriptor-Hooks bleiben. Eine
  Adapter-Schicht subscribt auf alle Domain-Events und ruft die
  äquivalenten Scriptor-Hooks auf, mit dokumentiertem Mapping.

**Acceptance:**
- Item editieren → Cache für die Seite ist invalidiert (nächster Request
  re-rendert).
- Item mit Files löschen → Files weg auf Disk + Metadaten-Row weg.
- Category löschen → kaskadiert sauber.
- Bestehende 1.x-Module, die Hooks nutzen, funktionieren weiter (über die
  Bridge).

---

### 14f — Cleanup & finale Acceptance

**Ziel:** legacy entfernen, finale Acceptance fahren, Release-Vorbereitung.

**Scope:**
- `Scriptor/imanager/` (das eingebettete 1.x-Lib) **löschen**.
- `Scriptor/data/datasets/buffers/` aus dem Repo entfernen (war 1.x-Storage,
  nicht mehr gebraucht — Backup bleibt extern).
- `boot.php` und `index.php` final aufräumen.
- Scriptor-`README.md` und `CHANGELOG.md` aktualisieren.
- Demo-Theme final smoke-testen.
- Performance-Check (Plan §8.2): `getItem` < 1ms, `getItems(20)` < 50ms,
  FTS-Search < 100ms gegen den migrierten Demo-Datensatz.
- Optional: CI-Workflow für Scriptor-Repo (entsprechend dem iManager-CI).

**Acceptance:**
- Kein einziger `Imanager\Item`-Import (1.x-Form) mehr im Scriptor-Code,
  nur `Imanager\Domain\Item` etc.
- Keine `imanager()`-Funktion mehr referenziert.
- `composer show` zeigt `bigins/imanager` als Quelle aller iManager-
  Symbole.
- Manueller Acceptance-Run: einloggen, neue Seite anlegen mit Bild und
  Markdown-Content, abspeichern, im Frontend abrufen, wieder löschen.

---

## 7. Hook-System-Bridge

Scriptor 1.x hat ein `Scriptor::execHook(...)`-System. Module-Autoren können
darüber an verschiedenen Stellen reagieren. Das wird in iManager 2.0 nicht
nativ gespiegelt — wir haben Domain-Events stattdessen.

**Bridge-Design (in 14e):**

| 1.x-Hook | 2.0-Domain-Event |
|---|---|
| `Item::save` (after) | `ItemCreated` / `ItemUpdated` |
| `Item::remove` (after) | `ItemDeleted` |
| `Category::save` | `CategoryCreated` / `CategoryUpdated` |
| `Category::remove` | `CategoryDeleted` |
| `Field::save` | `FieldCreated` / `FieldUpdated` |
| `Field::remove` | `FieldDeleted` |

Bridge-Klasse:

```php
// Scriptor\Compat\HookBridge
final class HookBridge implements EventListenerProvider
{
    public function __construct(private LegacyHookRegistry $hooks) {}
    public function listenersFor(object $event): iterable
    {
        $name = match (true) {
            $event instanceof ItemCreated  => 'Item::save:after',
            $event instanceof ItemUpdated  => 'Item::save:after',
            $event instanceof ItemDeleted  => 'Item::remove:after',
            // ...
        };
        return $this->hooks->listenersFor($name);
    }
}
```

Damit funktionieren alle Bestandsmodule weiter, die Scriptor-Hooks nutzen
— sie sehen ein synthesisches `$event`-Objekt mit denselben Feldern wie
das alte 1.x-Event.

---

## 8. Risiken & Mitigationen

| Risiko | Wahrscheinlichkeit | Impact | Mitigation |
|---|---|---|---|
| Migrator verliert Daten in exotischen Field-Configs | mittel | hoch | `--dry-run`, Roundtrip-Tests, Backup, Hybrid-Config-Strategie aus Phase 9 |
| Editor-Modul-Rewrite bricht User-Workflow | mittel | mittel | Sub-Phasen pro Modul; jede landet einzeln; Rollback per Branch-Revert |
| FilePond-Vendoring-Pfad verschiebt sich bei JS-Pipeline-Einführung | niedrig | niedrig | Pfad ist isoliert in `editor/theme/scripts/filepond/`; Migration kostet ein paar Pfadänderungen |
| Hook-Bridge bricht 3rd-Party-Module | mittel | hoch | Mapping-Tabelle in 14e öffentlich dokumentiert; Bridge ist ein dünner Adapter, leicht zu erweitern |
| Performance regressiert gegenüber 1.x | niedrig | mittel | Phase-13-Performance-Smoke; Generated-Column-Indexe (Phase 4); FTS5 (Phase 8) |
| `data/imanager.db` ist im Web-Root erreichbar | hoch (default) | hoch | `.htaccess` blockiert `*.db`; alternativ `data/`-Pfad außerhalb des Web-Roots dokumentieren |

---

## 9. Was NICHT in Phase 14

- **Neue Features.** Phase 14 ist Refactor mit Funktionsparität. Neues
  kommt nach Phase 17.
- **Theme-Refactor.** Themes bleiben strukturell, wie sie sind — nur die
  Site/Page-API-Aufrufe darin werden auf 2.0 umgestellt.
- **Frontend-Build-Pipeline.** Wird mit 14d (FilePond-Vendoring) bewusst
  vermieden. Falls später gewünscht: Folge-Initiative.
- **PSR-7-HTTP.** iManager bleibt bei seinem eigenen `Request`-Wrapper aus
  Phase 10 (siehe Plan §7 Phase 10).
- **Multi-Site / Multi-Tenancy.** Kein Scope-Creep.

---

## 10. Definition of Done für Phase 14

1. ✅ Alle Sub-Phasen 14a–14f gemerged.
2. ✅ Migrierte echte Daten verifiziert.
3. 🟡 Manuelle Acceptance: einloggen → neue Seite anlegen mit Bild und
   Content → abspeichern → im Frontend sehen → bearbeiten → löschen.
   *(Bigin durchläuft das nach jedem Merge im Browser auf scriptor.cms;
   der formelle End-to-End-Run vor dem Cutover läuft separat.)*
4. ✅ Performance-Budget aus Plan §8.2 erfüllt
   (`Scriptor/bin/perf-smoke.php`, alle Werte > 100× unter Budget).
5. ✅ Kein `Scriptor/imanager/`-Verzeichnis mehr im Repo (PR #36).
6. ✅ Composer-Dep-Graph zeigt `bigins/imanager:2.0.x-dev` als Quelle.
7. ⬜ Demo-Site auf `demos.scriptor-cms.info` rebuildet auf 2.0 —
   kann nach Phase 17 passieren.

---

## 11. Wie wir den Plan benutzen

Vor jedem Sub-Phase-Start:
1. Status-Tabelle in §6 aktualisieren (`🟡 in progress`).
2. Sub-Phase-Block durchgehen, offene Fragen klären.
3. Branch in **Scriptor**-Repo öffnen.
4. Implementieren, PR, CI, Merge.
5. Status-Tabelle update auf `✅ done`.

Größere Designentscheidungen, die während einer Sub-Phase auftauchen
(z.B. "wie lokalisieren wir die Field-Render-Templates?"), bekommen einen
ADR (`docs/adr/NNNN-<thema>.md`).

---

## 12. Was als Nächstes ansteht

1. **Pre-cutover acceptance.** Bigin sammelt eine Liste der
   verbleibenden Probleme, die wir vor dem Branch-Rename
   `imanager-2.0` → `master` lösen müssen. Solange die offen sind,
   bleibt `imanager-2.0` der long-lived integration branch.
   Detail-Workflow für den Cutover steht in
   `Scriptor/docs/handover-pre-cutover.md`.
2. **Cutover (destruktiv, nur auf explizite Freigabe).** Plan §3:
   `master` als Tag `1.x-final` aufheben, `imanager-2.0` zu `master`
   umbenennen, `:imanager-2.0` als remote-Branch löschen.
3. **Phase 16 — Docs & Examples.** iManager-seitig: README,
   Quickstart, Beispiel-Repo. Bisher nicht angefangen.
4. **Phase 17 — 2.0.0 Release.** Tag `bigins/imanager` auf Packagist;
   Scriptor stellt von Path-Repo auf Stable um.
