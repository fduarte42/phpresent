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
