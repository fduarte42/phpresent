# Phpresent — Software Design Document

Status: **living document**. This project is built incrementally; this SDD is
updated at the start of each increment before code is written. Section 15
("Implementation Roadmap") tracks what exists today vs. what is planned.

## 1. Purpose and Scope

Phpresent is a church/band presentation application (songs, Bible verses,
announcements, media, countdowns) that treats **SongbookPro Groups** as the
single source of truth for song and set content, reached exclusively through
its GraphQL API. Phpresent never edits song content — it authenticates,
synchronizes, caches, presents, schedules, and controls live output.

Non-goals: Phpresent is not a lyrics editor, not a chord-charting tool, and
does not maintain its own copy of truth for song text — every synced field is
attributed back to SongbookPro and re-synchronized rather than hand-edited.

## 2. Architecture Overview

Clean Architecture, applied per bounded context ("module"), not globally:

```
src/<Module>/
  Domain/          entities, value objects, repository interfaces, domain
                   exceptions, domain events — no framework dependencies
  Application/      use cases: Commands, Queries, their Handlers, DTOs,
                   application-level service interfaces
  Infrastructure/   Doctrine mappings & repository implementations, GraphQL
                   clients, filesystem, cache adapters, messenger transports
  Presentation/     Mezzio request handlers, middleware, route factories,
                   Inertia response builders
```

Dependency rule: `Presentation → Application → Domain`, and
`Infrastructure → Domain` (implements Domain interfaces). Domain has zero
outward dependencies. Application depends only on Domain interfaces, never on
Infrastructure or Presentation. Wiring (binding interfaces to implementations)
happens exclusively in `config/autoload/*.global.php` via Laminas
ServiceManager factories — no service locators, no static calls, no `new`
inside business logic (composition roots and factories are the sole
exception).

Modules identified so far (bounded contexts):

- **Song** — song catalogue mirrored from SongbookPro.
- **SongSet** — ordered sets/setlists mirrored from SongbookPro, with local
  display overrides (transposition already comes from SongbookPro; purely
  local reorder does not mutate the source of truth).
- **SongbookPro** — the GraphQL integration itself (client, sync engine,
  auth), consumed by Song and SongSet as an infrastructure dependency.
- **Presentation** (engine) — displays, slides, live control, WebSocket
  fan-out.
- **Media** — images/video/audio assets, Flysystem-backed.
- **Bible** — plugin-based translation providers, search, presentation.
- **Theme** — theme engine (global / song / section scoped).
- **Identity** — users, roles, RBAC, sessions, JWT-optional API auth.
- **Plugin** — plugin discovery/registration (Bible providers, media
  providers, exporters, importers, remote devices, OBS/MIDI/StreamDeck/NDI/DMX
  integrations).
- **Shared** — cross-cutting value objects (Id, Timestamp), HTTP
  infrastructure (problem-details, CSRF, CSP middleware), persistence
  bootstrap (EntityManager factory), logging.

Each module is independently testable: Domain and Application layers require
no database, HTTP, or GraphQL server to unit test.

## 3. Technology Stack

| Concern | Choice |
|---|---|
| Language/runtime | PHP 8.5+ |
| HTTP framework | Mezzio 3 (PSR-7/PSR-15/PSR-11), laminas-servicemanager |
| ORM | Doctrine ORM 3 + DBAL 4, attribute mapping |
| Migrations | Doctrine Migrations |
| Database | SQLite (default/dev), MariaDB/PostgreSQL (production) |
| Caching | Symfony Cache (PSR-6/PSR-16), Redis adapter in production |
| Async/queues | Symfony Messenger (sync transport in dev, AMQP/Redis in prod) |
| Serialization | Symfony Serializer (DTO ⇄ JSON, DTO ⇄ GraphQL payload) |
| Validation | Symfony Validator |
| Files | league/flysystem (local + S3-compatible adapters) |
| Logging | Monolog, PSR-3 |
| Static analysis | PHPStan level `max` + phpstan-doctrine + strict-rules |
| Code style | PHP-CS-Fixer (`laminas` ruleset) |
| Testing | PHPUnit + Pest, `--group=unit|integration` |
| Frontend framework | Vue 3 + `<script setup>` + TypeScript |
| SPA bridge | Inertia.js (server-driven routing, no separate API round trip for pages) |
| Build | Vite |
| UI kit | Naive UI (dark-mode-first) |
| State | Pinia |
| Utilities | VueUse |
| Drag/drop | SortableJS (local set reordering) |
| Media playback | Video.js |
| Realtime | WebSocket primary (ReactPHP/Ratchet server), SSE fallback endpoint |
| Auth | PHP session auth (default), JWT bearer optional for REST API, OAuth-ready via a swappable `AuthenticatorInterface` |

## 4. Domain Model (Song module — first increment)

```
Song (aggregate root)
 ├─ id: SongId (UUIDv4)
 ├─ externalId: SongbookProId          // SongbookPro's own song ID — sync key
 ├─ title: string
 ├─ authors: string[]
 ├─ copyright: ?string
 ├─ ccli: ?CcliNumber
 ├─ defaultKey: ?MusicalKey
 ├─ tempo: ?int
 ├─ capo: ?int
 ├─ tags: string[]
 ├─ format: LyricFormat (OpenLyrics | ChordPro | PlainText)
 ├─ sections: SongSection[]            // ordered, order preserved exactly
 ├─ attachments: SongAttachment[]
 ├─ metadata: array<string,scalar>     // passthrough for fields we don't model yet
 ├─ syncedAt: DateTimeImmutable
 ├─ sourceRevision: string             // SongbookPro ETag/version for conflict detection
 └─ sourceChecksum: string             // content hash, detects silent drift

SongSection (entity, owned by Song)
 ├─ id
 ├─ position: int                     // exact SongbookPro order — never re-sorted
 ├─ type: SectionType (Verse|Chorus|Bridge|Instrumental|Ending|Tag|PreChorus|Custom)
 ├─ label: ?string                    // e.g. "Verse 2", custom label for Custom type
 ├─ content: string                   // raw content in the song's native format
 └─ chordProSource: ?string           // preserved verbatim when format = ChordPro
```

Design decisions:

- **Section order is a first-class invariant.** `SongSection::position` is
  assigned strictly from SongbookPro's own ordering during sync and is never
  recomputed, sorted, or "optimized" locally. `Song::sections()` always
  returns them sorted by `position` ascending.
- **Format is preserved, not normalized.** We store `format` + raw `content`
  per section rather than transcoding OpenLyrics/ChordPro/plain text into one
  internal representation. Rendering (for presentation) is a pure function
  `SectionRenderer::render(SongSection, RenderOptions): RenderedSlideText`
  implemented per format, so lossy re-encoding never happens.
- **Conflict detection** compares `sourceRevision`/`sourceChecksum` on each
  sync pass; a change with a different `sourceRevision` triggers
  `SongUpdatedFromSource`, a domain event consumed by the Presentation module
  to refresh any live-displayed song, and by the sync log for auditing.
- **CCLI/Key/Tempo/Capo** are value objects (`CcliNumber`, `MusicalKey`) so
  invalid values fail fast in the Domain layer, not in a form validator.

## 5. Application Layer (CQRS-flavored)

Commands mutate, Queries read; both are plain classes dispatched through
Symfony Messenger's command bus (in-process for now, so no serialization
tax), each with exactly one Handler:

- `SyncSongsCommand` → `SyncSongsHandler` — pulls a page (or full delta)
  from SongbookPro via `GraphQLSongSourceInterface`, upserts through
  `SongRepositoryInterface`, emits domain events.
- `GetSongQuery` / `GetSongHandler` — id lookup for presentation/API.
- `SearchSongsQuery` / `SearchSongsHandler` — full-text search delegated to
  `SongRepositoryInterface::search()`.

DTOs (`SongDto`, `SongSectionDto`) are the only shape that crosses the
Application → Presentation boundary; Doctrine entities never leak into
controllers or JSON responses. Symfony Serializer converts DTO ⇄ JSON;
mapping GraphQL response ⇄ DTO is a dedicated `SongGraphQLMapper` (keeps
GraphQL's schema quirks out of the DTO itself).

## 6. SongbookPro GraphQL Integration

`SongbookProClientInterface` (Domain-facing port) is implemented by
`SongbookProGraphQLClient` (Infrastructure), composing:

- **Transport**: Guzzle HTTP client, injected — swappable for tests.
- **Pagination**: cursor-based, `PaginatedFetcher` walks `pageInfo.endCursor`
  automatically until `hasNextPage` is false.
- **Retry**: `RetryingHttpClient` decorator — exponential backoff (bounded
  attempts, configurable), only retries idempotent queries and 5xx/network
  errors, never retries mutations blindly.
- **ETag / conditional requests**: `ETagCache` (PSR-16 backed by Symfony
  Cache) stores the ETag per query+variables hash; a `304` short-circuits to
  the cached payload.
- **Rate limiting**: token-bucket `RateLimiter` shared across all client
  instances (Symfony Cache-backed counter), configurable
  requests/second, applied before every request.
- **Incremental sync**: sync log table stores `lastSyncedRevision` per
  entity type; subsequent syncs request only records changed since that
  revision (SongbookPro's `updatedSince` filter) instead of a full walk.
- **Offline cache**: last-known-good GraphQL responses persisted to the
  local DB (`sync_snapshot` table) so presentation keeps working if
  SongbookPro is briefly unreachable; sync becomes a background concern via
  Messenger, never blocking presentation.
- **Conflict detection**: see §4; drives a `sync_conflict` log rather than
  silently overwriting local display overrides (e.g. a locally-reordered set
  vs. an upstream reorder).
- **Background sync / polling**: a Messenger scheduled message
  (`SyncSongsCommand` re-dispatched on a configurable interval,
  `songbookpro.sync.interval_seconds`) rather than a cron-only design, so it
  also works under `RoadRunner`/long-running workers later.

## 7. Presentation Engine (planned, see roadmap)

- Display registry: unlimited `Display` records, each with a `role`
  (main | operator | confidence-monitor | audience | custom) and its own
  `DisplaySettings` (theme, safe area, font, lower-third, watermark, etc).
- Displays connect over WebSocket (Ratchet/ReactPHP server, separate process
  from the Mezzio HTTP app) to a `PresentationChannel`; state changes
  (`SlideChanged`, `DisplayBlanked`, `EmergencyMessageShown`, …) are pushed,
  never polled. SSE endpoint (`/presentation/{displayId}/events`) mirrors the
  same event stream for clients that can't hold a WebSocket.
- Slide generation is a pure `SlideComposer` pipeline: Song/Set/Bible/Media
  content → `SlideDeck` (ordered `Slide[]`), applying pagination rules
  (max lines, max chars, smart wrap, split-on-section-boundary-first) as a
  deterministic, independently unit-testable function of (content, rules).
- Live control commands (`Next`, `Previous`, `JumpToSlide`, `Black`, `Freeze`,
  `HideLyrics`, `FontSizeAdjust`, `EmergencyMessage`, …) are Commands against
  a `PresentationSession` aggregate, broadcast to displays after the command
  handler commits — control and rendering stay decoupled.

## 8. Cross-Cutting Concerns

- **Security**: CSRF middleware on state-changing HTML routes, CSP header
  middleware, PSR-15 auth middleware chain (session first, bearer JWT
  fallback for `/api/*`), Doctrine parameter binding only (no raw SQL
  concatenation), `password_hash`/`password_verify` for local credentials,
  permission checks via a `PermissionInterface` gate consulted in Application
  handlers (not controllers), append-only `audit_log` table for admin/RBAC
  actions.
- **Config**: `laminas-config-aggregator`, `.global.php` for defaults,
  `.local.php` (git-ignored) for secrets/environment overrides, env vars take
  precedence for container deploys.
- **Testing**: Domain/Application = pure unit tests (Pest, in-memory fakes
  for repository interfaces). Infrastructure = integration tests against a
  throwaway SQLite file / an in-memory GraphQL stub server. Presentation =
  Mezzio request/response tests. E2E left as an architectural placeholder
  (Playwright against the built frontend) — not implemented yet.

## 9. Database Schema (current increment: Song module only)

```sql
CREATE TABLE songs (
    id                CHAR(36) PRIMARY KEY,
    external_id       VARCHAR(191) NOT NULL UNIQUE,
    title             VARCHAR(512) NOT NULL,
    authors           TEXT NOT NULL,            -- JSON array
    copyright         VARCHAR(512),
    ccli              VARCHAR(32),
    default_key       VARCHAR(8),
    tempo             INTEGER,
    capo              INTEGER,
    tags              TEXT NOT NULL,            -- JSON array
    format            VARCHAR(16) NOT NULL,
    metadata          TEXT NOT NULL,            -- JSON object
    source_revision   VARCHAR(191) NOT NULL,
    source_checksum   VARCHAR(191) NOT NULL,
    synced_at         DATETIME NOT NULL,
    created_at        DATETIME NOT NULL,
    updated_at        DATETIME NOT NULL
);

CREATE TABLE song_sections (
    id                CHAR(36) PRIMARY KEY,
    song_id           CHAR(36) NOT NULL REFERENCES songs(id) ON DELETE CASCADE,
    position           INTEGER NOT NULL,
    type              VARCHAR(24) NOT NULL,
    label             VARCHAR(191),
    content           TEXT NOT NULL,
    chordpro_source   TEXT,
    UNIQUE (song_id, position)
);
CREATE INDEX idx_song_sections_song_id ON song_sections(song_id);
CREATE INDEX idx_songs_title ON songs(title);
```

Later increments add `sets`, `set_items`, `displays`, `display_settings`,
`themes`, `media_assets`, `bible_bookmarks`, `users`, `roles`,
`permissions`, `audit_log`, `sync_log`, `sync_conflict` — schemas will be
documented here in the same style before each is implemented.

## 10. REST API (current increment)

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/songs` | paginated list + full-text search (`?q=`, `?tag=`, `?ccli=`) |
| GET | `/api/songs/{id}` | single song with ordered sections |
| POST | `/api/songs/sync` | trigger a foreground sync pass (admin-only) |

All endpoints return `application/json`; errors use RFC 7807 problem
details via `mezzio/mezzio-problem-details`. OpenAPI document to be
generated once the API surface stabilizes past this first module (tracked
in roadmap).

## 11. Frontend (current increment)

Inertia.js page `resources/js/Pages/Songs/Index.vue` renders a Naive UI
`n-data-table` of synced songs (title, authors, CCLI, key, tags) with a
search box bound to `/api/songs?q=`. Layout shell
(`Layouts/AppLayout.vue`) provides the dark-mode-aware Naive UI theme
provider and navigation, reused by all future pages. A Pinia
`useSongsStore` wraps API calls so components stay presentation-only.

## 12. Plugin Architecture (planned)

`PluginInterface` (Domain-facing, in `Shared\Domain`) with narrow
capability interfaces a plugin may additionally implement:
`BibleProviderInterface`, `MediaProviderInterface`, `ExporterInterface`,
`ImporterInterface`, `PresentationWidgetInterface`, `RemoteDeviceInterface`.
A `PluginRegistry` (Infrastructure) discovers plugins from
`config/plugins.php` (explicit registration — no filesystem scanning/magic,
for predictability and security) and binds them into the ServiceManager.
OBS/MIDI/StreamDeck/NDI/DMX integrations are first-class plugins using these
same interfaces, not special-cased core code.

## 13. Realtime Transport

WebSocket server runs as its own process
(`bin/websocket-server.php`, Ratchet), sharing the Doctrine
EntityManager config but not the Mezzio HTTP pipeline. It subscribes to
Presentation-module domain events (via a Messenger transport dedicated to
realtime fan-out) and pushes frames to connected displays. `/sse/{displayId}`
on the main HTTP app offers the same event stream over Server-Sent Events for
environments where WebSocket is blocked.

## 14. Directory Layout

```
config/                  Mezzio config, DI wiring, routes
public/                  index.php, built frontend assets
src/<Module>/{Domain,Application,Infrastructure,Presentation}
migrations/               Doctrine migrations
resources/js/             Vue/Inertia frontend source
test/{Unit,Integration}/  mirrors src/ module layout
docs/sdd.md               this document
```

## 15. Implementation Roadmap / Status

Legend: ✅ implemented this increment · ⏳ designed, not yet built

- ✅ Project scaffolding: composer/npm, Docker, Makefile, CI, PHPStan/CS config
- ✅ Song module: Domain entities/value objects, repository interface +
  Doctrine implementation, DTOs, sync Command/Handler, SongbookPro GraphQL
  client (pagination/retry/ETag/rate-limit), REST endpoints, migration,
  Inertia/Vue list page, unit + integration tests
- ⏳ SongSet module (sets, ordering, local drag/drop override, notes,
  transposition passthrough)
- ⏳ Identity module (users, roles, RBAC, session + JWT auth, audit log)
- ⏳ Presentation engine (displays, slide composer, live control, WebSocket
  server, SSE fallback)
- ⏳ Media module (Flysystem-backed assets, video/image/PDF slides)
- ⏳ Theme engine
- ⏳ Bible module + provider plugin(s)
- ⏳ Plugin registry + first-party plugins (OBS, MIDI, StreamDeck, NDI, DMX)
- ⏳ Admin UI (dashboard, users, roles, displays, themes, media, logs, jobs,
  sync, cache)
- ⏳ Import/export (OpenLyrics, PDF, JSON/ZIP backup)
- ⏳ OpenAPI generation, full REST surface

Each future increment adds its design to this document (architecture,
schema, entities, repository interfaces, services, DTOs, endpoints, Vue
pages/components, routes, tests) before implementation, per the project's
own delivery convention.

## 16. Conventions & Gotchas (read before starting a new module)

These are the parts of the codebase that exist only as code today, not as
prose elsewhere. A cold-start agent should read this section before adding
the next module (SongSet, Identity, Presentation, ...) to stay consistent
with what's already there instead of re-deriving or diverging from it.

### 16.1 Dependency injection wiring pattern

`config/autoload/dependencies.global.php` uses three mechanisms together —
follow the same split for new modules:

- **`aliases`**: interface → concrete class, for anything with no
  config-driven constructor args (e.g. `SongRepositoryInterface::class =>
  DoctrineSongRepository::class`).
- **`factories`**: explicit closures, only for services whose constructor
  needs scalar config values pulled from `$container->get('config')`
  (`EntityManager`, `SongbookProGraphQLClient`, `RateLimiter`,
  `InertiaResponseFactory`, the PSR-16 cache, the logger). If a class takes
  a plain string/int/float with no default, it needs an explicit factory —
  reflection can't supply those.
- **`abstract_factories: [ReflectionBasedAbstractFactory::class]`**: the
  fallback for everything else (handlers, application Command/Query
  handlers, mappers, repositories) — their constructors only take
  interfaces/classes (resolvable via the aliases above) or have scalar
  defaults. Don't hand-write a factory for a class reflection can already
  build.

When adding a module: add its repository/service aliases and any
config-dependent factories to this same file rather than creating a
per-module dependencies file — there's only one `dependencies.global.php`
by design, so wiring stays discoverable in one place.

### 16.2 Doctrine entity mapping paths must be registered manually

`src/Shared/Infrastructure/Persistence/EntityManagerFactory.php` passes an
explicit `paths` array to `ORMSetup::createAttributeMetadataConfiguration()`
— currently `Song/Domain/Entity` and `Song/Infrastructure/Persistence`.
Doctrine will silently ignore entities outside these paths (no error, the
table just never gets created/mapped). **Every new module that adds
`#[ORM\Entity]` classes must add its own entity directory to this array**,
and add the corresponding path list to any test that builds its own
`EntityManager` (see 16.4) and to `cli-config.php`'s indirect dependency on
the same factory.

### 16.3 Inertia adapter is hand-rolled, not a package

There is no official Inertia.js server-side adapter for Mezzio/PHP outside
Laravel. `src/Shared/Infrastructure/Http/InertiaResponseFactory.php` is a
from-scratch ~80-line implementation of the protocol (HTML shell with
`data-page`, or JSON when `X-Inertia: true` is sent). Do not go looking for
a Composer package to configure — extend this class (e.g. for shared props,
partial reloads, or asset versioning) instead.

Related gotcha: **Vite 6 writes its manifest to
`public/build/.vite/manifest.json`**, not `public/build/manifest.json` as
in older Vite/Laravel-Mix conventions. `InertiaResponseFactory`'s
`assetTags()` and the factory in `dependencies.global.php` already point at
the `.vite/` subdirectory — if this ever looks wrong, check the actual Vite
version's output path before "fixing" it back.

### 16.4 Test conventions

- Pest groups: every file under `test/Unit` is tagged `unit`, everything
  under `test/Integration` is tagged `integration` (see `test/Pest.php`).
  Use `composer test:unit` / `composer test:integration` to run one or the
  other; new test files just need to live in the right directory, no
  per-file tagging required.
- **In-memory fakes**, not mocking frameworks, for Application-layer tests:
  see `test/Support/InMemorySongRepository.php`,
  `InMemorySyncStateRepository.php`, `FakeSongSource.php`. They implement
  the real Domain/Application interfaces so handler tests
  (`test/Unit/Song/Application/SyncSongsHandlerTest.php`) exercise real
  control flow without a database. Add one `InMemory*`/`Fake*` per
  interface a new module introduces, in `test/Support/`, autoloaded under
  `PhpresentTest\Support`.
- **Integration tests use a throwaway in-memory SQLite `EntityManager`**,
  not the app's configured database. See
  `test/Integration/Song/DoctrineSongRepositoryTest.php`:
  `ORMSetup::createAttributeMetadataConfiguration()` with the module's
  entity paths (same list as §16.2), a `sqlite:///:memory:` DBAL
  connection, then `SchemaTool::createSchema()` from the loaded metadata —
  no migration run needed for tests.
- Global helper functions declared at the top of a Pest test file (e.g.
  `makeSong()`, `remoteSong()`) are in the global namespace for the whole
  suite — keep names unique across files, or move a helper into
  `test/Support/` as a real class if it's going to be reused from more than
  one test file.

### 16.5 PHP version target vs. what's actually installed

The brief specifies PHP 8.5+. `composer.json` currently pins `"php":
"^8.3"` because PHP 8.5 does not exist in any environment this project has
been built or run in so far (including this sandbox, which has 8.4.19).
This is a deliberate, temporary downgrade of the *constraint*, not a scope
cut — no 8.5-only syntax has been used. Bump the constraint back to `^8.5`
once a real PHP 8.5 runtime is available to test against; don't do it
speculatively without verifying `composer install` and the test suite still
pass under it.

### 16.6 Sandbox/CI network caveat (not a code bug)

In at least one development sandbox for this project, `composer install`
could not complete: outbound access to GitHub's dist/codeload endpoints was
blocked by that session's network policy, causing every package to fail
"download from dist" and then fail again on the git-source fallback
("Could not authenticate against github.com"). This is an environment
constraint, not a problem with `composer.json` — if you hit the identical
failure signature, don't start editing dependency versions or lockfiles to
"fix" it; confirm first whether the environment simply can't reach GitHub,
and prefer running `composer install` in CI or a normal environment. `npm
install` / `vue-tsc --noEmit` / `vite build` all worked without issue in
that same sandbox, so the frontend toolchain is not affected by this.

### 16.7 What to update alongside a new module

When adding a module (e.g. SongSet), the checklist is: entities +
migration (16.2), repository interface + Doctrine implementation, DI
wiring (16.1), routes in `config/routes.php`, an SDD section (schema,
entities, DTOs, endpoints — following the style of §4–§11) added *before*
the code per the project's delivery convention, `test/Support/` fakes for
any new interfaces (16.4), and a roadmap line moved from ⏳ to ✅ in §15.
