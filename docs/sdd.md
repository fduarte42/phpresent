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

## 6. SongbookPro Groups GraphQL Integration

`SongbookProClientInterface` (Domain-facing port) is implemented by
`SongbookProGraphQLClient` (Infrastructure). This section originally
described a client built against an assumed API shape that was never
verified; it now reflects the real, verified API (§6.1–§6.2, confirmed
2026-07-19 by instrumenting a live browser session — see §16.8a for the
history).

`SongbookProGraphQLClient` composes:

- **Transport**: Guzzle HTTP client, injected — swappable for tests.
- **Auth**: bearer JWT obtained through Azure AD B2C (§6.1). The client owns
  token acquisition/refresh — there is no static API key. Exactly which
  OAuth grant Phpresent (a server-side app, not the interactive web
  dashboard) uses to obtain that token is not yet resolved — see §6.3.
- **Delta sync, not cursor pagination**: `DeltaFetcher` calls
  `GetDataItemsSince(library, since)` in a loop, feeding each response's
  `timestamp` back in as the next `since` and continuing while `hasMore` is
  true. `since` is persisted exactly where the original design already put
  it — `sync_state.lastSyncedRevision` (§9/§16) — so no schema change was
  needed, only the query shape and the loop condition (`hasMore` instead of
  `pageInfo.hasNextPage`/`endCursor`).
- **Retry**: `RetryingHttpClient` decorator — exponential backoff (bounded
  attempts, configurable), only retries idempotent queries and 5xx/network
  errors, never retries mutations blindly.
- **Rate limiting**: token-bucket `RateLimiter` shared across all client
  instances (Symfony Cache-backed counter), configurable
  requests/second, applied before every request.
- **Offline cache**: last-known-good `dataItems` responses persisted to the
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

There is no `ETagCache`/conditional-request layer — the original design
included one, but no `ETag`/`If-None-Match` exchange was observed in real
traffic, and the `since`-based delta query already gives Phpresent the
"don't re-fetch unchanged data" property that layer was meant to provide.

### 6.1 Verified API Surface (reverse-engineered from live traffic, 2026-07-19)

SongbookPro's real product for this integration is **SongbookPro Groups**
(shared libraries for bands/teams), not a generic "SongbookPro API" — the
GraphQL endpoint below is specific to it.

- **Endpoint**: `POST https://songbookpro-groups-prod.azurewebsites.net/graphql`
  — a single Apollo Server instance, most likely behind Azure API
  Management or equivalent gateway middleware.
- **Auth**: Azure AD B2C via MSAL.js (tenant `login.songbookpro.app`,
  policy `b2c_1_sign_up_in_v1`). Every request must carry
  `Authorization: Bearer <access token>`; a missing/invalid token is
  rejected with `401` and `{"errors":[{"extensions":{"code":
  "UNAUTHENTICATED"}}]}` **before** the GraphQL query is even parsed — this
  confirms auth is enforced by a gateway layer in front of the resolvers,
  not per-resolver. Phpresent's `SongbookProClientInterface` must therefore
  own token acquisition/refresh (via the same B2C flow, or a
  service-account-equivalent SongbookPro provides) rather than assuming a
  static API key.
- **Introspection is disabled in production**
  (`extensions.validationErrorCode: "INTROSPECTION_DISABLED"` from Apollo
  Server) — the schema cannot be self-discovered; the operations below are
  the complete set observed by instrumenting a real browser session against
  the SongbookPro Groups web dashboard (`groups.songbookpro.app/dashboard`)
  and are not guaranteed exhaustive.
- **Client-side batching**: the official web client (`@apollo/client`
  v4.1.6, observed) sends a JSON *array* of operations in a single POST
  body rather than one operation per request. Phpresent's client does not
  need to replicate this — a single-operation body is accepted the same
  way — but the response is a JSON array either way, so the transport layer
  must unwrap `response[0]` rather than assume a bare object.

### 6.2 Observed Operations and Data Model

There is no dedicated `songs`/`songSets` query and no cursor pagination.
Everything (songs, song content, and — by the same pattern, though not
directly confirmed — sets) is synced through one generic timestamp-delta
query and one generic upsert mutation, keyed by an opaque `type` string
rather than a typed GraphQL field per entity kind:

```graphql
query GetDataItemsSince($library: ID!, $since: BigInt) {
  dataItems(library: $library, since: $since) {
    timestamp
    hasMore
    items {
      id
      type      # e.g. "SONG", "SONG_VARIANT" (observed); presumably "SET"-like
                # types exist too but were not exercised in this session
      deleted
      data      # opaque JSON string, shape depends on `type`
      __typename
    }
    __typename
  }
}

mutation UpsertDataItems($items: [LibraryItemInput!]!, $library: ID!) {
  addDataItems(items: $items, library: $library) {
    id
    type
    deleted
    data
    __typename
  }
}
```

- **Pagination is cursor-free**: the client passes `since` (a unix
  timestamp from the previous response's `timestamp` field) and gets back
  everything changed after it, plus a `hasMore` boolean when the page is
  capped. "Cursor" state is just the last-seen timestamp — see `DeltaFetcher`
  in §6.
- **Soft-delete, not removal**: deleting an item does not remove it from
  future `dataItems` responses — it re-appears with `deleted: true` and
  `data: null` (a tombstone). A sync handler must treat `deleted: true` as
  "retire the local row," never as "absent from now on."
- **Create/update and delete use the same mutation.** A create sends a full
  `data` JSON payload with `deleted: false` and `id: null` (server assigns
  the UUID); a delete sends just `id` + `type` + `deleted: true`, no `data`.
  There is no separate `deleteSong`-style mutation.

**`SONG_VARIANT` data payload** (chord/lyric content), observed on create:

```json
{"id": "", "variantName": null, "content": "", "key": 0, "keyShift": 0,
 "type": 1, "crop": null, "importSource": "editor"}
```

**`SONG` data payload** (metadata), observed on create — field list is
partial, response was truncated during capture:

```json
{"id": "", "title": "...", "subtitle": "", "cleanTitle": "...",
 "artist": "", "timeSig": "", "tempo": 0, "url": null, "deepSearch...": "…"}
```

Two further operations exist for organization/account management (not
song-sync related, but confirm the schema's general shape and may matter
for a future multi-tenant/licensing story):

```graphql
query OrganizationWithUsers($id: ID!) {
  organization(id: $id) {
    id name
    libraries { id }
    users {
      name id email isInvite suspended
      permissions { permission libraries { library permission } }
    }
    plan { productId name provider expires appLicensed maximumUsers
           billingUserId paddleSubscriptionId paddleStatus active canceled }
  }
}

query Invites {
  me { id invites { id organizationId organizationName } }
}
```

Permissions are **per-library**, not just per-organization — a user can be
an Editor on one library and have no access to another in the same org.
Billing runs through **Paddle** (merchant-of-record subscriptions), not a
direct Stripe integration.

### 6.3 Open Questions for the Next SongbookPro-Sync Increment

§6/§6.1/§6.2 now reflect the verified API, replacing the original,
never-tested assumption of cursor-paginated `songs`/`songSets` queries.
This means the *existing* Song/SongSet GraphQL Infrastructure code
(`SongGraphQLMapper`, `SongSetGraphQLMapper`, the old `PaginatedFetcher`)
was built against that incorrect assumption and needs rewriting to match
this section before it will work against the real service — it was never
caught earlier because those increments were built and tested only against
`SongbookProClientInterface` fakes (§16.4), which never exercised the real
schema. Two things remain unconfirmed and should be resolved as part of
that rewrite, not assumed:

- The `type` value(s) used for sets (`SET`? `SONG_SET`?) — capture traffic
  from the Sets pages of the dashboard before implementing
  `SongSetGraphQLMapper` against this model.
- Which OAuth/B2C grant a server-side client (Phpresent) should use to
  obtain its own bearer token, as opposed to the interactive
  authorization-code-with-PKCE flow MSAL.js uses for a logged-in browser
  session — this determines whether `SongbookProGraphQLClient` needs a
  refresh-token store, a client-credentials grant, or something
  SongbookPro-specific (e.g. a per-install API token issued out of band).

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
- ✅ SongSet module: Domain entities/value objects (`SongSet`, `SongSetItem`,
  merge-on-sync with local reorder preservation), repository interface +
  Doctrine implementation, DTOs, sync Command/Handler, reorder Command/
  Handler, SongbookPro GraphQL client (new `songSets` query + mapper reusing
  shared transport/retry/ETag/rate-limit infra), REST endpoints incl. local
  reorder, migration, Inertia/Vue list + drag/drop show page (first use of
  SortableJS), unit + integration tests. Also promoted `SyncState`/
  `SyncStateRepositoryInterface` from the Song module to `Shared` so both
  modules share one sync-state table without a cross-module Domain
  dependency (see §16.1/§16.2 history — no SDD section needed for this, it's
  an internal refactor, not new surface area).
- ✅ Identity module: `User`/`Role` entities (role membership as a JSON
  array of role ids, no join table — see §18.1), repository interfaces +
  Doctrine implementations, `PasswordHasherInterface`, session (
  `mezzio/mezzio-session`+`-ext`) + JWT (`firebase/php-jwt`) composite
  `AuthenticatorInterface`, `PermissionInterface`/`AuditLoggerInterface`
  promoted to `Shared\Domain` for cross-module reuse, REST endpoints incl.
  login/logout, migration, unit + integration tests. No admin UI yet
  (tracked separately below).
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
— currently its own directory (for the shared `SyncState` entity),
`Song/Domain/Entity`, `Song/Infrastructure/Persistence`, and
`SongSet/Domain/Entity`. Doctrine will silently ignore entities outside
these paths (no error, the table just never gets created/mapped). **Every
new module that adds `#[ORM\Entity]` classes must add its own entity
directory to this array**, and add the corresponding path list to any test
that builds its own `EntityManager` (see 16.4) and to `cli-config.php`'s
indirect dependency on the same factory.

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
- Every throwaway in-memory `EntityManager` built by an integration test
  must call `EntityManagerFactory::registerCustomTypes()` before
  `ORMSetup::createAttributeMetadataConfiguration()` — Doctrine has no
  autodiscovery for the `uuid` DBAL type (`ramsey/uuid-doctrine`) used by
  every entity's `id` column, and building an `EntityManager` without
  registering it first fails with `UnknownColumnType` at schema-creation
  time, not at mapping time, which makes the real cause easy to misdiagnose.
  See `test/Integration/Song/DoctrineSongRepositoryTest.php` and
  `test/Integration/SongSet/DoctrineSongSetRepositoryTest.php` for the
  one-line call site.
- `DriverManager::getConnection()` no longer parses a raw `'url' => $dsn`
  array key in Doctrine DBAL 4.4+ (this changed after §16.5/§16.6 were
  written, when this project's pinned `^4.2` constraint still resolved to a
  version where it worked) — pass the DSN through
  `(new Doctrine\DBAL\Tools\DsnParser($schemeMapping))->parse($dsn)` first.
  `EntityManagerFactory` maps the `sqlite`/`mysql`/`postgres` URL schemes
  used in `config/autoload/local.php.dist` to their `pdo_*` driver names;
  any test building its own connection needs the same scheme mapping (at
  minimum `['sqlite' => 'pdo_sqlite']`).

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

### 16.7 This app had never actually been booted before the SongSet increment

The Song increment was built and unit/integration tested in a sandbox that
could not run `composer install` (see 16.6), so `config/container.php`,
`config/pipeline.php`, and the migrations had never actually been exercised
end-to-end. Building and manually verifying the SongSet increment (REST
endpoints, Inertia pages, drag/drop reorder) in an environment that *could*
install dependencies and boot a real server surfaced several latent bugs
that were fixed as part of this increment, all pre-existing and unrelated
to SongSet's own design:

- `config/container.php` referenced `ConfigAggregator\ArrayProvider` where
  the `use Laminas\ConfigAggregator\ConfigAggregator;` import doesn't cover
  the implied sub-namespace — fixed to `use ...\ArrayProvider;` directly.
  It also passed `local.php`/`local.php.dist` as `ConfigAggregator`'s third
  constructor argument (`$postProcessors`, which expects callables/class
  strings), when it needed to be a fourth `ArrayProvider` in the providers
  list like the other config files.
- `config/container.php` was missing `Laminas\Diactoros\ConfigProvider`,
  so no PSR-17 factories were registered — Mezzio's router middleware
  failed with `MissingDependencyException` on every request.
- `config/pipeline.php` had no `Mezzio\Helper\BodyParams\BodyParamsMiddleware`,
  so `$request->getParsedBody()` was always empty for JSON request bodies —
  `POST /api/songsets/sync`, `POST /api/songs/sync`, and
  `POST /api/songsets/{id}/reorder` all silently no-op'd instead of reading
  their JSON body. This is the kind of bug unit tests (which call handlers
  directly, bypassing HTTP body parsing entirely) cannot catch — only
  exercising the real HTTP endpoint surfaces it.
- `src/Shared/Infrastructure/Persistence/EntityManagerFactory.php` passed
  `['url' => $dsn]` straight to `DriverManager::getConnection()`; DBAL 4.4+
  no longer parses a raw `url` key there (see the DsnParser note in 16.4) —
  affects every DB connection the app makes, not just tests.
- Doctrine's `uuid` DBAL type was never registered anywhere (see the
  `EntityManagerFactory::registerCustomTypes()` note in 16.4) — every
  entity uses it for its `id` column, so this blocked all persistence.
- `migrations/Version20260719120000.php`'s
  `$sections->addForeignKeyConstraint($songs, ...)` passed a `Table` object
  where current DBAL expects the foreign table's *name* (`string`) — fixed
  to `$songs->getName()`; the SongSet migration was written matching the
  same (incorrect) pattern and fixed identically before either had run.
- `public/index.php` had no static-file passthrough for PHP's built-in
  dev server (`composer serve`), so requests for built Vite assets under
  `/build/*` were routed into the Mezzio pipeline instead of being served
  as files, which doesn't happen in production behind a real webserver
  but breaks `composer serve` immediately.

None of this reflects a problem with the *design* documented elsewhere in
this file — it's what "the app was designed correctly on paper but never
run" looks like. If a future increment hits a wall that "should just
work" per the SDD, check whether that code path has actually been
exercised yet before assuming the increment you're adding is at fault.

### 16.8a §6 was rewritten against the real SongbookPro Groups API — the existing sync code was not

§6/§6.1–§6.3 were rewritten to describe the actual GraphQL API (endpoint,
auth, operations, data model), reverse-engineered from live traffic
against a real logged-in session — not from documentation, since none is
public and introspection is disabled in production. This replaced an
earlier, never-tested assumption of cursor-paginated `songs`/`songSets`
queries. The *code* has not been updated to match: the existing
`SongGraphQLMapper`, `SongSetGraphQLMapper`, and `PaginatedFetcher` were
built against that old assumption and have never been run against the
real endpoint (§16.7 explains why: no increment to date exercised it
beyond fakes). Read §6.3 before touching any of them — they need
rewriting against the real `dataItems`/`addDataItems` delta-sync shape in
§6.2, not extended as if they already matched it.

### 16.8 What to update alongside a new module

When adding a module (e.g. SongSet), the checklist is: entities +
migration (16.2), repository interface + Doctrine implementation, DI
wiring (16.1), routes in `config/routes.php`, an SDD section (schema,
entities, DTOs, endpoints — following the style of §4–§11) added *before*
the code per the project's delivery convention, `test/Support/` fakes for
any new interfaces (16.4), and a roadmap line moved from ⏳ to ✅ in §15.

## 17. SongSet Module (second increment)

Mirrors the Song module's shape (§4–§11) exactly, per §16.8 — same layering,
same sync strategy, same DTO/repository/handler patterns. Only the
differences worth calling out are noted below.

### 17.1 Domain Model

```
SongSet (aggregate root)
 ├─ id: SongSetId (UUIDv4)
 ├─ externalId: SongbookProId          // SongbookPro's own set ID — sync key
 ├─ name: string
 ├─ serviceDate: ?DateTimeImmutable    // SongbookPro's scheduled date, if any
 ├─ notes: ?string
 ├─ items: SongSetItem[]               // ordered, see below
 ├─ syncedAt: DateTimeImmutable
 ├─ sourceRevision: string
 └─ sourceChecksum: string

SongSetItem (entity, owned by SongSet)
 ├─ id
 ├─ songExternalId: string             // references a Song by its SongbookPro
 │                                     // id — NOT a Doctrine relation to the
 │                                     // Song entity. SongSet and Song are
 │                                     // separate aggregates in separate
 │                                     // modules; Domain must not depend on
 │                                     // another module's entities. Resolving
 │                                     // the referenced Song (title, key, ...)
 │                                     // for display is an Application-layer
 │                                     // concern (SongRepositoryInterface
 │                                     // lookup when building the DTO).
 ├─ sourcePosition: int                // exact SongbookPro order — never
 │                                     // recomputed, same invariant as
 │                                     // SongSection::position in §4.
 ├─ localPosition: ?int                // local-only reorder override; null
 │                                     // means "follow sourcePosition".
 │                                     // Never sent back to SongbookPro.
 ├─ transposedKey: ?MusicalKey         // passthrough value already computed
 │                                     // by SongbookPro — Phpresent never
 │                                     // recalculates a transposition.
 └─ notes: ?string
```

Design decisions:

- **Two orderings, one source of truth.** `SongSetItem::sourcePosition` is
  the SongbookPro-assigned order and is sync-owned, exactly like
  `SongSection::position`. `localPosition` is a purely local override the
  operator can set (drag/drop in the UI) without mutating SongbookPro.
  `SongSet::items()` returns items ordered by `effectivePosition()` —
  `localPosition ?? sourcePosition` — computed per item, never persisted as
  a derived column.
- **Reorder is a separate command from sync**, and touches only
  `localPosition`. `ReorderSongSetItemsCommand` never calls the SongbookPro
  client and is not gated by `hasDiverged()` — it's local display state, not
  synced content, consistent with the module description in §2 ("local
  display overrides ... purely local reorder does not mutate the source of
  truth").
- **A full sync pass resets `localPosition` to `null`** for any item whose
  `sourcePosition` changed upstream (the local override is assumed stale
  once the set's real order changes), but leaves it untouched for items
  whose `sourcePosition` is unchanged. This is decided in
  `SongSet::applySync()`, mirroring how `Song::applySync()` owns its own
  field-update rules.
- **`transposedKey` is passthrough, not computed.** SongbookPro already
  resolves per-set transposition; Phpresent stores and displays it exactly
  as received, same principle as `Song::defaultKey`.
- **No Doctrine relation between `SongSetItem` and `Song`.** Keeping the
  reference as a plain `songExternalId` string (rather than an ORM
  `ManyToOne` to `Song`) keeps the two modules' Domain layers decoupled — a
  cross-module Doctrine association would force `SongSet`'s entity mapping
  to depend on `Song`'s, violating the "modules are independently testable"
  rule in §2.

### 17.2 Application Layer

- `SyncSongSetsCommand` / `SyncSongSetsHandler` — identical shape to
  `SyncSongsCommand`/`SyncSongsHandler` (§5): pulls via
  `SongSetSourceInterface`, upserts through `SongSetRepositoryInterface`,
  uses the **same shared `sync_state` table** (§4/§16) keyed by entity type
  `'song_set'` — no new sync-state table or entity.
- `ReorderSongSetItemsCommand(string $songSetId, array $orderedItemIds)` /
  `ReorderSongSetItemsHandler` — loads the set, calls
  `SongSet::reorder(array $orderedItemIds)` (assigns `localPosition`
  sequentially from the given id order, throws
  `UnknownSongSetItemException` if an id doesn't belong to the set), saves.
- `GetSongSetQuery` / `GetSongSetHandler` — id lookup, resolves each item's
  `songExternalId` against `SongRepositoryInterface::findByExternalId()` to
  populate `SongSetItemDto::songTitle`/`songDefaultKey` for display; a
  missing/unsynced Song leaves those fields `null` rather than failing the
  whole set (a set can legitimately reference a song not yet synced).
- `SearchSongSetsQuery` / `SearchSongSetsHandler` — same
  `search()`/`all()` split as `SearchSongsHandler` (§5), delegated to
  `SongSetRepositoryInterface`.
- `SongSetSourceInterface::fetchAll(?string $updatedSince = null): iterable<RemoteSongSetRecord>`
  — same port shape as `SongSourceInterface` (§5).
- DTOs (`SongSetDto`, `SongSetItemDto`) are the only shape crossing into
  Presentation, same rule as §5.

### 17.3 SongbookPro GraphQL Integration

Reuses the shared `GraphQLClientInterface`/`SongbookProGraphQLClient`
infrastructure from §6 as-is (`DeltaFetcher`, retry, rate-limit, offline
cache) — **there is no separate `songSets` query to add.** Per §6.2, sets
sync through the same generic `GetDataItemsSince`/`UpsertDataItems`
operations as songs, filtered by `type` rather than by a dedicated field.
Only a new `SongSetGraphQLMapper` is added
(`src/SongSet/Infrastructure/Mapper`), decoding `dataItems` items whose
`type` matches the set entity kind:

```graphql
# Same query as §6.2's GetDataItemsSince — no SongSet-specific query exists.
# SongSetGraphQLMapper filters the returned `items` by `type` and decodes
# each item's `data` JSON string into a SongSet/SongSetItem shape.
query GetDataItemsSince($library: ID!, $since: BigInt) {
  dataItems(library: $library, since: $since) {
    timestamp
    hasMore
    items { id type deleted data }
  }
}
```

The exact `type` value used for sets (and whether a set's items are
embedded in the same `data` payload or synced as their own item type) is
**unconfirmed** — flagged as an open question in §6.3. Capture real Sets-page
traffic before implementing `SongSetGraphQLMapper` against an assumed shape;
don't repeat the mistake that produced the original (wrong) `songSets`
connection design this section used to describe.

### 17.4 Database Schema

```sql
CREATE TABLE song_sets (
    id                CHAR(36) PRIMARY KEY,
    external_id       VARCHAR(191) NOT NULL UNIQUE,
    name              VARCHAR(512) NOT NULL,
    service_date      DATETIME,
    notes             TEXT,
    source_revision   VARCHAR(191) NOT NULL,
    source_checksum   VARCHAR(191) NOT NULL,
    synced_at         DATETIME NOT NULL,
    created_at        DATETIME NOT NULL,
    updated_at        DATETIME NOT NULL
);

CREATE TABLE song_set_items (
    id                CHAR(36) PRIMARY KEY,
    song_set_id       CHAR(36) NOT NULL REFERENCES song_sets(id) ON DELETE CASCADE,
    song_external_id  VARCHAR(191) NOT NULL,
    source_position   INTEGER NOT NULL,
    local_position    INTEGER,
    transposed_key    VARCHAR(8),
    notes             TEXT,
    UNIQUE (song_set_id, source_position)
);
CREATE INDEX idx_song_set_items_song_set_id ON song_set_items(song_set_id);
CREATE INDEX idx_song_sets_name ON song_sets(name);
```

No new `sync_state` table — the migration reuses the one created by the
Song module's migration (§9/§16); `entity_type = 'song_set'` rows are
inserted into the existing table at runtime, not by a migration.

### 17.5 REST API

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/songsets` | paginated list + full-text search (`?q=`) |
| GET | `/api/songsets/{id}` | single set with ordered, song-resolved items |
| POST | `/api/songsets/sync` | trigger a foreground sync pass (admin-only) |
| POST | `/api/songsets/{id}/reorder` | persist a local drag/drop item order (`{itemIds: string[]}`) — local-only, never touches SongbookPro |

Same conventions as §10: JSON responses, RFC 7807 problem details for
errors.

### 17.6 Frontend

- `resources/js/Pages/SongSets/Index.vue` — Naive UI `n-data-table` list of
  synced sets (name, service date, item count), same shape as
  `Songs/Index.vue` (§11).
- `resources/js/Pages/SongSets/Show.vue` — ordered item list rendered with
  SortableJS for drag/drop; on drop, posts the new id order to
  `/api/songsets/{id}/reorder` and optimistically updates local state. This
  is the first and only current use of SortableJS (listed in §3) in the
  codebase.
- `resources/js/stores/useSongSetsStore.ts` — Pinia store mirroring
  `useSongsStore.ts` (§11), plus a `reorder()` action.

## 18. Identity Module (third increment)

Users, roles, RBAC, session + JWT auth, and an append-only audit log, per
§2/§8. Unlike Song/SongSet, Identity has no external source of truth to
sync from — it's the first module whose data model is authored locally.

### 18.1 Domain Model

```
User (aggregate root)
 ├─ id: UserId (UUIDv4)
 ├─ email: Email                       // VO, validated, unique
 ├─ passwordHash: string               // never a raw password past the boundary
 ├─ displayName: string
 ├─ roleIds: string[]                  // Role UUIDs, JSON column
 ├─ isActive: bool
 ├─ createdAt: DateTimeImmutable
 └─ updatedAt: DateTimeImmutable

Role (aggregate root — not owned by User)
 ├─ id: RoleId (UUIDv4)
 ├─ name: string                       // unique, e.g. "admin", "operator"
 └─ permissions: string[]              // e.g. ["users.manage", "songs.sync"]
```

Design decisions:

- **Role membership is a JSON array of role IDs on `User`, not a
  many-to-many join table.** Mirrors the JSON-array pattern already used
  for `Song::tags` (§4) — a handful of roles per install, no query pattern
  yet needs "find all users with role X" at the SQL level. If that need
  shows up, promote to a join table then; adding it speculatively now would
  be exactly the "design for hypothetical future requirements" this
  project avoids.
- **Roles are a separate aggregate, not nested under User.** Many users
  share one role, and role/permission definitions are managed
  independently of any single user — the textbook RBAC shape.
- **Permissions are a plain `string[]` on `Role`, not a normalized
  `permissions` table.** Supersedes the placeholder `permissions` table
  name listed in §9's "later increments" note, written before this
  design existed. Nothing yet needs permission metadata (descriptions,
  grouping) or a query across roles by permission — YAGNI applies the same
  way it did to Role↔User above.
- **Password hashing goes through `PasswordHasherInterface`**
  (`hash(string $password): string`, `verify(string $password, string $hash): bool`),
  a Domain-facing port implemented by `PhpPasswordHasher` (wraps
  `password_hash`/`password_verify`, per §8). Keeping it an interface — not
  calling the PHP functions directly from the User entity or a handler —
  means tests never touch real bcrypt, and a future algorithm change is an
  Infrastructure-only swap.
- **No self-registration.** This is an internal church/band team tool, not
  a public SaaS; `CreateUserCommand` is admin-only, enforced by the
  `PermissionInterface` gate inside the handler (see 18.2), not by a
  missing "sign up" route.

### 18.2 Application Layer

Two new **Shared** ports (used by every module's Application handlers
going forward, not just Identity's own — same reasoning that moved
`SyncStateRepositoryInterface` to `Shared` in the SongSet increment, §16):

- `Shared\Domain\Security\PermissionInterface` —
  `can(string $actorUserId, string $permission): bool`. Takes a plain
  actor-id string rather than a `User` entity so modules outside Identity
  can depend on this port without depending on Identity's Domain layer.
  Implemented by `Identity\Infrastructure\Security\RolePermissionChecker`
  (loads the actor's `User` via `UserRepositoryInterface`, resolves their
  `roleIds` to `Role`s via `RoleRepositoryInterface`, checks the union of
  permissions).
- `Shared\Domain\Audit\AuditLoggerInterface` —
  `record(string $actorUserId, string $action, array $context = []): void`.
  Implemented by `DoctrineAuditLogger` in
  `Shared\Infrastructure\Persistence` (same directory as the shared
  `SyncState` entity — both are cross-cutting infra with no module-specific
  coupling) writing to the append-only `audit_log` table.

Every mutating Identity command takes `actorUserId` as its first
parameter, checks `PermissionInterface::can()` before doing anything else,
throws `PermissionDeniedException` (Domain) if denied, and calls
`AuditLoggerInterface::record()` after a successful write — same shape as
`SyncSongsHandler`'s create/update branching, just gated and logged:

- `CreateUserCommand(actorUserId, email, password, displayName, roleIds)` /
  `CreateUserHandler` — gate: `users.manage`. Hashes the password via
  `PasswordHasherInterface`; never logs or stores the raw value.
- `AssignRoleCommand(actorUserId, userId, roleId)` / `AssignRoleHandler` —
  gate: `users.manage`.
- `DeactivateUserCommand(actorUserId, userId)` / `DeactivateUserHandler` —
  gate: `users.manage`. Soft-disable (`isActive = false`), never a hard
  delete — consistent with "prefer reversible operations" project-wide.
- `CreateRoleCommand(actorUserId, name, permissions)` / `CreateRoleHandler`
  — gate: `roles.manage`.
- `ListUsersQuery` / `GetUserQuery` / `ListRolesQuery` + Handlers — gate:
  `users.view` / `roles.view` respectively. Reads are gated too, since
  user records (email, role membership) are the one place in this app
  where "logged in" isn't enough on its own — per §8's cross-cutting
  security note, checked in the Application handler, not the route.
- `LoginCommand(email, password)` / `LoginHandler` — **not** gated (this
  command *is* the gate): looks up by email via `UserRepositoryInterface`,
  verifies via `PasswordHasherInterface::verify()`, throws
  `InvalidCredentialsException` on any failure (wrong email or wrong
  password get the identical exception/message — never reveal which one
  was wrong), returns `UserDto` on success. The Presentation login handler
  writes the returned user id into the session; `LoginHandler` itself
  never touches HTTP session state, keeping it framework-free and unit
  testable like every other Application handler.
- Logout has no Application handler — it's pure session-clearing, entirely
  a Presentation concern (§18.4).

`AuthenticatorInterface` (`Identity\Application\Service`, mirrors
`SongSourceInterface`'s placement) —
`authenticate(ServerRequestInterface $request): ?string` (returns the
authenticated user id, or null). Takes a PSR-7 request the same way
existing handlers already take `Psr\Log\LoggerInterface` — a
language-level PSR contract, not a framework/Infrastructure dependency, so
this stays consistent with "Application depends only on Domain interfaces
... never on Infrastructure or Presentation" (§2: PSR interfaces are the
one standing exception, already established by `LoggerInterface` usage in
`SyncSongsHandler`). Three Infrastructure implementations composed by DI,
matching §8's "session first, bearer JWT fallback for `/api/*`":

- `SessionAuthenticator` — reads the `session` request attribute (set by
  `Mezzio\Session\SessionMiddleware`, piped ahead of routing, §18.4),
  returns `$session->get('userId')`.
- `JwtAuthenticator` — reads the `Authorization: Bearer <token>` header,
  verifies via `firebase/php-jwt`'s `JWT::decode()` against a
  config-supplied secret, returns the `sub` claim; returns `null` (never
  throws past its own boundary) on any decode/signature/expiry failure.
- `CompositeAuthenticator` — tries `SessionAuthenticator` first always;
  falls back to `JwtAuthenticator` only when the request path starts with
  `/api/`. This is the service actually bound to `AuthenticatorInterface`
  in DI.

### 18.3 Database Schema

```sql
CREATE TABLE users (
    id                CHAR(36) PRIMARY KEY,
    email             VARCHAR(320) NOT NULL UNIQUE,
    password_hash     VARCHAR(255) NOT NULL,
    display_name      VARCHAR(191) NOT NULL,
    role_ids          TEXT NOT NULL,            -- JSON array of role UUIDs
    is_active         BOOLEAN NOT NULL DEFAULT 1,
    created_at        DATETIME NOT NULL,
    updated_at        DATETIME NOT NULL
);
CREATE INDEX idx_users_email ON users(email);

CREATE TABLE roles (
    id                CHAR(36) PRIMARY KEY,
    name              VARCHAR(64) NOT NULL UNIQUE,
    permissions       TEXT NOT NULL             -- JSON array of permission strings
);

CREATE TABLE audit_log (
    id                CHAR(36) PRIMARY KEY,
    actor_user_id     CHAR(36) NOT NULL,
    action            VARCHAR(191) NOT NULL,
    context           TEXT NOT NULL,            -- JSON object, free-form
    recorded_at       DATETIME NOT NULL
);
CREATE INDEX idx_audit_log_actor_user_id ON audit_log(actor_user_id);
CREATE INDEX idx_audit_log_recorded_at ON audit_log(recorded_at);
```

`audit_log` is append-only by convention (no repository method updates or
deletes rows, per §8) — enforced by code review, not a DB trigger, same
trust level as the rest of this codebase's other invariants (e.g.
`SongSection::position` immutability, §4).

### 18.4 Presentation & Middleware

- `Mezzio\Session\SessionMiddleware` (ext-session persistence via
  `mezzio/mezzio-session-ext`) is piped immediately after routing
  middleware and before `AuthenticationMiddleware`, so every request has a
  `session` attribute available.
- `Phpresent\Identity\Presentation\Http\Middleware\AuthenticationMiddleware`
  (PSR-15) calls `AuthenticatorInterface::authenticate()` and attaches the
  result (a `?string` user id, possibly null for anonymous requests) as
  the `actorUserId` request attribute. It never rejects a request itself
  — "is this user allowed to do X" is answered by `PermissionInterface`
  inside the Application handler (§18.2/§8), not by middleware. Anonymous
  requests reach handlers with `actorUserId = null`; handlers that require
  auth treat `null` the same as "no permissions" (`PermissionInterface`
  returns `false` for a null/unknown actor).
- REST handlers catch `PermissionDeniedException` →
  `JsonResponse(['title' => 'Forbidden', 'status' => 403], 403)` and
  `InvalidCredentialsException` →
  `JsonResponse(['title' => 'Invalid credentials', 'status' => 401], 401)`,
  matching the existing explicit-catch pattern (`GetSongHandler`'s 404,
  §10) rather than introducing a new generic exception-to-response mapper.

| Method | Path | Purpose |
|---|---|---|
| POST | `/login` | authenticate, write `userId` into the session |
| POST | `/logout` | clear the session |
| GET | `/api/users` | list users (gate: `users.view`) |
| GET | `/api/users/{id}` | get one user (gate: `users.view`) |
| POST | `/api/users` | create a user (gate: `users.manage`) |
| POST | `/api/users/{id}/roles` | assign a role (gate: `users.manage`) |
| POST | `/api/users/{id}/deactivate` | soft-disable a user (gate: `users.manage`) |
| GET | `/api/roles` | list roles (gate: `roles.view`) |
| POST | `/api/roles` | create a role (gate: `roles.manage`) |

No Vue/Inertia pages in this increment — a dedicated Users/Roles admin UI
is its own later roadmap line ("Admin UI", §15), built once more modules
have permissions worth managing through a UI rather than direct API calls.
