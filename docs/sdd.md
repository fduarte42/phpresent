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
  fan-out. Note the name collision with the per-module `Presentation/`
  *layer* directory above: this module's own HTTP handlers therefore live
  at `src/Presentation/Presentation/Http/Handler/...`, same structure as
  every other module, just with a module named the same as the layer.
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
(As of the §6.4 rewrite this is no longer just a design note — the classes
have been deleted; there is nothing left calling them.)

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
  **Resolved for now** by picking the one option that needs no further
  confirmation: `AccessTokenProviderInterface` /
  `StaticAccessTokenProvider` reads a pre-obtained token straight from
  config (`songbookpro.api_token`). Swapping in a real client-credentials
  or refresh-token flow later is a pure Infrastructure change behind that
  same port — see §6.4.

### 6.4 Transport Rewrite Status (2026-07-20)

The Song-side sync path has been rewritten against §6.1/§6.2 as described
above; the SongSet side has not (its blocker — the unconfirmed `type` value
for sets — is unchanged, see below). What changed and what's still assumed
or missing, so the next increment to touch this code doesn't have to
re-derive it:

**Rewritten and working (unit + integration tested against fakes):**

- `SongbookProGraphQLClient` — real endpoint, bearer auth via
  `AccessTokenProviderInterface`, unwraps the `response[0]` array shape,
  retry/rate-limit kept, `ETagCache` deleted entirely.
- `DeltaFetcher` — generic `GetDataItemsSince`/`hasMore` pager, yielding
  `LibraryItem` (`id`/`type`/`deleted`/decoded `data`). Shared by any future
  module that syncs through `dataItems` — it has no Song-specific
  knowledge, unlike the old per-entity `songs`/`songSets` queries it
  replaces.
- `SongGraphQLMapper`/`SongSource` — filter `LibraryItem`s to `type ===
  'SONG'`, map to `RemoteSongRecord`.

**Deliberately narrow, and why (don't "fix" these without new evidence):**

- **A synced `Song` currently has no sections.** The only confirmed content
  payload (`SONG_VARIANT`, §6.2) has a single `content` string field, not a
  `sections[]` array, and — critically — nothing in the captured traffic
  shows how a `SONG_VARIANT` item references the `SONG` it belongs to. Both
  of those need a fresh, targeted traffic capture (open a song with known
  content in the dashboard, inspect the full uncapped `SONG` and
  `SONG_VARIANT` `data` payloads) before `SongGraphQLMapper` can be
  extended to actually populate `sections`. Guessing a verse/chorus split
  from one opaque content blob was explicitly avoided — it would be
  fabricating a spec, the exact mistake §6/§16.8a already called out once.
- **`revision`/`checksum` are computed locally**
  (`hash('xxh128', json_encode($data))`), not read from the API — the
  confirmed `dataItems.items` shape (§6.2) has no per-item revision field,
  only the page-level `timestamp` `DeltaFetcher` uses for paging. This is a
  legitimate substitute for `Song::hasDiverged()`'s purposes (detecting
  drift), not a placeholder waiting to be replaced.
- **`EpochMillis` assumes `since`/`timestamp` are epoch milliseconds.** The
  reverse-engineering session only ever observed single-page (`hasMore:
  false`) responses, so no real `since`→`timestamp` round trip was
  captured to confirm the unit against. Milliseconds is the routine
  convention for a JS/Apollo `BigInt` timestamp field, but this is a
  documented assumption, not a fact — verify it the first time a sync
  pass's `since` cursor is checked against real multi-page traffic.
- **Deletions (`deleted: true` tombstones) are logged and skipped, not
  applied.** `Song` has no "retired" state to sync a tombstone into — adding
  one is its own small increment (domain method + repository query +
  possibly a DB column), not a side effect of this transport rewrite.
- **SongSet is untouched.** The `type` value SongbookPro uses for sets is
  still unconfirmed (same open question as before this rewrite) — capture
  real Sets-page traffic before touching `SongSetGraphQLMapper`/
  `SongSetSource`. They still reference the old, incorrect `songSets`
  connection query and will fail against the real endpoint exactly as
  before.

## 7. Presentation Engine (fourth through sixth increments)

The fourth increment implemented the Display registry, `SlideComposer`
pipeline, and `PresentationSession` live-control commands below, through
Doctrine persistence and a REST API. The fifth increment added the realtime
transport (§7.5/§13): the WebSocket server and SSE fallback that actually
push `PresentationSession` state to displays, rather than requiring `GET
/api/presentation` polling. The sixth increment added the Vue/Inertia
operator UI (§7.5/§11) — the Presentation module is now feature-complete
against §7's original design.

### 7.1 Domain Model

```
Display (aggregate root)
 ├─ id: DisplayId (UUIDv4)
 ├─ name: string
 ├─ role: DisplayRole (Main|Operator|ConfidenceMonitor|Audience|Custom)
 ├─ settings: DisplaySettings (theme, safeAreaPercent, fontScale,
 │                             showLowerThird, watermarkText)
 ├─ createdAt: DateTimeImmutable
 └─ updatedAt: DateTimeImmutable

PresentationSession (aggregate root — one row per install, see below)
 ├─ id: PresentationSessionId (UUIDv4)
 ├─ currentDeck: ?SlideDeck
 ├─ currentSlideIndex: int
 ├─ isBlanked: bool
 ├─ isFrozen: bool
 ├─ lyricsHidden: bool
 ├─ fontSizeAdjust: int
 ├─ emergencyMessage: ?string
 └─ updatedAt: DateTimeImmutable

SlideDeck (value object)
 ├─ sourceType: SlideSourceType (Song|Blank — see 7.2)
 ├─ sourceId: ?string
 └─ slides: Slide[]

Slide (value object)
 ├─ lines: string[]
 ├─ sectionType: ?string   // plain string, NOT Song\Domain\ValueObject\SectionType — see below
 └─ sectionLabel: ?string
```

Design decisions:

- **`PresentationSession` is a normal aggregate, not a config singleton.**
  There is exactly one row in practice — the one live-output pipeline this
  install controls — created on first access by
  `PresentationSessionRepositoryInterface::current()`. Modeling it as an
  aggregate (rather than e.g. stashing state in `config/`) keeps it testable
  like everything else in this codebase; `current()` is the only lookup
  method the interface exposes (no `get(id)`), since nothing else needs one.
- **`Slide::sectionType` is a plain string, not `Song\Domain\ValueObject\
  SectionType`.** Referencing another module's Domain enum from
  Presentation's own Domain VO would be exactly the cross-module Domain
  coupling §17.1 forbids (the same reason `SongSetItem::songExternalId` is a
  plain string instead of a `Song` reference). `SlideComposer` (Application
  layer, where cross-module Domain dependencies are fine — see 7.2) converts
  `SectionType::value` to a string when building a `Slide`.
- **`SlideDeck`/`Slide` serialize to JSON** (`toArray()`/`fromArray()`) for
  storage on `PresentationSession.current_deck` — same passthrough-JSON
  pattern already used for `Song::metadata`/`Display::settings`, not a new
  persistence mechanism.
- **`SectionRenderer` moved from being just a signature in §4 to actually
  existing, in `Song\Domain\Service`** (not Presentation's Domain) — it only
  touches `SongSection`, a single-module concern (chord-bracket stripping
  per format), so it belongs to Song. It intentionally does *not* take
  `RenderOptions` (pagination — max lines, max chars, split-on-section-
  boundary-first) as §4 originally sketched; pagination is a display
  concern, not a song-content concern, so `RenderOptions` lives in
  `Presentation\Domain\ValueObject` and is applied by `SlideComposer`
  instead. This is a deliberate refinement of §4's original (never-built)
  signature for a cleaner module boundary, recorded here per this project's
  own "living document" convention.
- **`SlideSourceType` only has `Song` and `Blank` cases today.** SongSet/
  Bible/Media are listed in §2's eventual module scope but have no
  composition pipeline yet — adding those enum cases now would be dead code
  (YAGNI, same reasoning as §18.1's Role↔User join-table deferral).

### 7.2 Application Layer

- `SlideComposer::compose(Song $song, RenderOptions $options = new
  RenderOptions()): SlideDeck` — pure function, no I/O. For each of the
  song's sections (already ordered, §4): calls `SectionRenderer::render()`
  for chord-free lines, word-wraps each line to `maxCharsPerLine`
  (PHP `wordwrap()`), then chunks the wrapped lines into slides of at most
  `maxLinesPerSlide` — chunking happens per-section *before* concatenating
  across sections, which is what guarantees "split-on-section-boundary-
  first" (two sections' content can never end up sharing one slide, no
  matter how short both are). Fully blank chunks are dropped.
  Depending on `Song\Domain\Entity\Song` from this Application layer mirrors
  the existing precedent of `SongSet\Application\Query\GetSongSetHandler`
  depending on `Song\Domain\Repository\SongRepositoryInterface` — see 7.1.
- `CreateDisplayCommand`/`UpdateDisplayCommand`/`RemoveDisplayCommand` +
  Handlers, `ListDisplaysQuery`/`GetDisplayQuery` + Handlers — plain CRUD,
  same shape as every other module's Command/Query split (§5). `role` is
  passed as a string and parsed via `DisplayRole::from()` inside the
  handler; an unknown value throws PHP's native `ValueError`, caught at the
  REST boundary (7.4) rather than wrapped in a bespoke domain exception —
  there's no other validation nuance `DisplayRole::from()` doesn't already
  provide.
- Live control: `LoadSongIntoPresentationCommand(songId)`,
  `NextSlideCommand`, `PreviousSlideCommand`, `JumpToSlideCommand(index)`,
  `SetBlankedCommand(blanked)`, `SetFrozenCommand(frozen)`,
  `SetLyricsHiddenCommand(hidden)`, `SetFontSizeAdjustCommand(steps)`,
  `SetEmergencyMessageCommand(message)` — one Command/Handler pair per SDD
  §7's original command list, each: load `PresentationSessionRepositoryInterface::current()`,
  call the one matching `PresentationSession` method, save, return
  `PresentationSessionDto`. `LoadSongIntoPresentationHandler` additionally
  resolves the `Song` via `SongRepositoryInterface` and calls
  `SlideComposer`, returning `null` for an unknown song id (same
  not-found convention as `GetSongHandler`, §10).
- `GetPresentationSessionQuery` + Handler — returns the current session
  state, unconditionally (there's always exactly one, per 7.1).
- **No `PermissionInterface` gating on any of this**, matching the Song/
  SongSet precedent (§6/§17) rather than Identity's (§18.2): authorization
  is left to route-level auth middleware, not implemented in the handler.
  Identity is so far the only module that actually gates through
  `PermissionInterface` — extending that project-wide is its own future
  increment, not a side effect of this one.
- DTOs: `DisplayDto`, `PresentationSessionDto`, `SlideDeckDto`, `SlideDto` —
  the only shapes crossing into Presentation(HTTP), same rule as §5.

### 7.3 Database Schema

```sql
CREATE TABLE displays (
    id                CHAR(36) PRIMARY KEY,
    name              VARCHAR(191) NOT NULL,
    role              VARCHAR(24) NOT NULL,
    settings          TEXT NOT NULL,             -- JSON object
    created_at        DATETIME NOT NULL,
    updated_at        DATETIME NOT NULL
);

CREATE TABLE presentation_sessions (
    id                   CHAR(36) PRIMARY KEY,
    current_deck         TEXT,                   -- JSON object, nullable
    current_slide_index  INTEGER NOT NULL DEFAULT 0,
    is_blanked           BOOLEAN NOT NULL DEFAULT 0,
    is_frozen            BOOLEAN NOT NULL DEFAULT 0,
    lyrics_hidden        BOOLEAN NOT NULL DEFAULT 0,
    font_size_adjust     INTEGER NOT NULL DEFAULT 0,
    emergency_message    TEXT,
    updated_at           DATETIME NOT NULL
);
```

No `sync_state` involvement — neither table is SongbookPro-synced content.

### 7.4 REST API

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/displays` | list all displays |
| GET | `/api/displays/{id}` | get one display |
| POST | `/api/displays` | create a display (`{name, role, settings?}`) |
| PATCH | `/api/displays/{id}` | update a display |
| DELETE | `/api/displays/{id}` | remove a display |
| GET | `/api/presentation` | current session state |
| POST | `/api/presentation/load` | load a song (`{songId}`) as the current deck |
| POST | `/api/presentation/control` | dispatch a live-control command — see below |

`POST /api/presentation/control` takes `{action, value?}` where `action` is
one of `next` \| `previous` \| `jump` (`value`: int) \| `blank` (`value`:
bool) \| `freeze` (`value`: bool) \| `hideLyrics` (`value`: bool) \|
`fontSize` (`value`: int) \| `emergencyMessage` (`value`: `?string`). This
is one route for all eight live-control commands rather than eight routes —
each remains its own independently unit-tested Command/Handler in the
Application layer (7.2); only the *HTTP routing* surface is collapsed,
since CQRS doesn't require a 1:1 route-to-command mapping and eight
three-line HTTP handler classes would add files without adding behavior.
Same JSON/RFC 7807 conventions as §10.

### 7.5 Realtime Transport and Operator UI (fifth and sixth increments)

**Realtime transport** (fifth increment): `bin/websocket-server.php`
(Ratchet) and `GET /sse/{displayId}` (§13 gives the full design and the two
real bugs this increment's manual verification caught — read that section
before touching either transport).

**Operator UI** (sixth increment, §11): `GET /displays` (Displays list +
inline create form + remove) and `GET /presentation` (the live-control
screen — song picker, current-slide preview, next/previous, blank/freeze/
hide-lyrics toggles, font-size adjust, emergency-message banner). The
control page connects directly to the WebSocket server from the browser
(`usePresentationStore`, `resources/js/stores/usePresentationStore.ts`),
falling back to the SSE endpoint if the WebSocket connection fails —
exercising both transports built in the fifth increment from an actual
browser, not just `curl`/Node test scripts. Manually verified end-to-end
(Chrome, via computer-use browser automation): load a song, toggle every
control, create/remove a display — each REST response and the following
WebSocket push were both confirmed to update the UI. That pass caught one
real gap — the emergency-message control set state correctly server-side
but the page never rendered the active message anywhere, silently
"working" while being useless to an operator — fixed by adding a banner
bound to `session.emergencyMessage`.

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

## 11. Frontend

Inertia.js page `resources/js/Pages/Songs/Index.vue` renders a Naive UI
`n-data-table` of synced songs (title, authors, CCLI, key, tags) with a
search box bound to `/api/songs?q=`. Layout shell
(`Layouts/AppLayout.vue`) provides the dark-mode-aware Naive UI theme
provider and navigation, reused by all future pages. A Pinia
`useSongsStore` wraps API calls so components stay presentation-only.

### 11.1 Presentation module UI (sixth increment)

- `Pages/Displays/Index.vue` — same list-page shape as `Songs/Index.vue`,
  plus an inline create form (name + role `n-select`) and a `n-popconfirm`-
  gated remove action per row. `useDisplaysStore` wraps the CRUD calls.
- `Pages/Presentation/Control.vue` — the live-control operator screen: a
  song picker (reuses the `/api/songs` search endpoint directly rather than
  `useSongsStore`, since that store's state is page-scoped and this page
  needs its own independent list), a current-slide preview reflecting
  `isBlanked`/`lyricsHidden`/the active slide's lines, Next/Previous,
  Blank/Freeze/Hide-Lyrics `n-switch` toggles, a font-size `n-input-number`,
  and an emergency-message field with a Show/Clear pair — mirroring
  `PresentationControlHandler`'s `{action, value}` shape exactly (§7.4).
- `stores/usePresentationStore.ts` — the one store in this codebase that
  isn't just a `fetch()` wrapper. `connect(wsUrl, displayId)` opens a
  `WebSocket` to `PresentationChannel`; on `close`/`error` it falls back to
  `new EventSource('/sse/' + displayId)`. Both paths funnel into the same
  `session` ref, so `Control.vue` doesn't know or care which transport is
  live — only `connectionStatus` (`connecting` \| `websocket` \| `sse` \|
  `offline`), surfaced as a banner, tells the operator which one is active.
  Deliberately doesn't add a third polling fallback below SSE: SSE already
  auto-reconnects (`EventSource`'s built-in behavior, matched against the
  server's bounded stream duration, §13.3), so a third tier would just
  duplicate what SSE already provides.
- `PresentationControlPageHandler` (Presentation module, §7.2) computes
  `wsUrl` server-side from the *inbound request's* host, not
  `websocket.host` from config — that config value is typically `0.0.0.0`
  (a bind address, not something a browser can connect to).

## 12. Plugin Architecture (foundation implemented, ninth increment)

`PluginInterface` (`Shared\Domain\Plugin\PluginInterface` — `id()`/`name()`)
with narrow capability interfaces a plugin may additionally implement.
Only `BibleProviderInterface` (`Shared\Domain\Plugin\Bible`) exists so far,
built alongside the Bible module (§21) as its first real consumer, rather
than speculatively building `MediaProviderInterface`/`ExporterInterface`/
`ImporterInterface`/`PresentationWidgetInterface`/`RemoteDeviceInterface`
with no plugin to implement them yet — same YAGNI reasoning as everywhere
else in this codebase (e.g. `SlideSourceType` only having `Song`/`Blank`
cases, §7.1). Add each capability interface when a real plugin needs it.

`PluginRegistry` (`Shared\Infrastructure\Plugin\PluginRegistry`) discovers
plugins from `config/plugins.php` (explicit registration — no filesystem
scanning/magic, for predictability and security) via a DI factory that
resolves each listed class through the container, then filters by
capability with a typed accessor per capability (`bibleProviders()`) rather
than a generic `byCapability(string $interface)` — see the class's own
docblock for why. OBS/MIDI/StreamDeck/NDI/DMX integrations remain a future
capability interface each, not special-cased core code, once one is
actually being built.

## 13. Realtime Transport

Implemented in the fifth increment, and — per this project's convention
(§16.7) of actually booting things rather than trusting that correct-looking
code works — verified by running both transports against a real HTTP
process and a real WebSocket server, issuing REST mutations, and confirming
the push arrived. That verification caught two real bugs, described below,
that no amount of reading the code would have surfaced.

### 13.1 Why polling, not a Messenger transport

§7/§7.1's original sketch described the WebSocket server subscribing to
Presentation-module domain events "via a Messenger transport dedicated to
realtime fan-out." No Messenger transport has ever actually been wired in
this codebase (every increment to date dispatches Commands/Queries via
direct DI-resolved `__invoke`, not a bus — see §16.4/§16.8a) — and the
WebSocket server is a separate OS process from the Mezzio HTTP app that
handles `/api/presentation/*` commands, so there is no in-process event to
subscribe to even if there were a bus.

Standing up a real message broker (Redis pub/sub, AMQP) for a single global
`presentation_sessions` row would be a lot of new infrastructure for what
this needs. Instead, both `PresentationChannel` (WebSocket,
`Phpresent\Presentation\Infrastructure\Realtime\PresentationChannel`) and
`PresentationSseHandler` (SSE) **poll** `presentation_sessions` on a timer,
using the same Doctrine EntityManager config the HTTP app uses, and only
send a frame when the serialized state actually changed since the last
poll. `EntityManager::clear()` before every poll is required — without it,
Doctrine's identity map keeps returning the first-loaded (by then stale)
entity instead of reading the row the HTTP process just committed. Default
poll interval is 250ms (`websocket.poll_interval_seconds`,
`config/autoload/websocket.global.php`) — imperceptible latency for a live
worship-service display, and zero new external dependencies. Redis pub/sub
remains a reasonable future optimization if poll latency or DB load ever
becomes a real constraint; nothing here forecloses it, since both consumers
already isolate "how do I learn about a change" behind one method.

### 13.2 WebSocket server

`bin/websocket-server.php` runs as its own process, sharing the Doctrine
EntityManager config (via `config/container.php`, same container the HTTP
app and `cli-config.php` build) but not the Mezzio HTTP pipeline. It hosts
`PresentationChannel` (a Ratchet `MessageComponentInterface`): `onOpen`
sends the connecting client the current session state immediately; a
ReactPHP periodic timer calls `poll()`, which broadcasts to every
connected client only when the state changed. Run with `composer
serve:websocket`.

**Bug this increment's manual verification caught**: `Ratchet\Server\
IoServer::factory($component, $port, $address)` takes no `$loop` parameter
— passing one positionally after `$address` is silently ignored, because
`factory()` always creates its own internal loop via `LoopFactory::create()`.
The periodic poll timer was registered on a *different* loop instance
(`React\EventLoop\Loop::get()`) than the one `IoServer` actually ran,
so the server accepted connections and answered the initial `onOpen` state
correctly, but never once broadcast a change — a bug invisible from reading
the code, only caught by actually connecting a client and triggering a
mutation. Fixed by constructing `React\Socket\SocketServer` and `IoServer`
directly with one explicit shared loop instead of using the factory.

### 13.3 SSE fallback

`GET /sse/{displayId}` on the main HTTP app offers the same session state
over Server-Sent Events, for environments where WebSocket is blocked.
`{displayId}` is accepted but unused — the broadcast state doesn't vary per
display yet. Implemented as a `Laminas\Diactoros\CallbackStream` whose
callback loops internally (poll, diff, `echo`, `flush()`), matching the
well-established idiom for this PSR-15 emitter (`SapiStreamEmitter`
`echo`s a non-readable stream's `getContents()` once, so the callback must
do its own incremental output rather than returning chunks from `read()`).
Bounded to `websocket.sse_max_duration_seconds` (default 55s) so a client
reconnects periodically (standard `EventSource` behavior) rather than the
connection running forever.

**Bug this increment's manual verification caught**: `connection_aborted()`
only becomes accurate immediately after PHP attempts to write output — it
does not update passively when a client disconnects. An earlier version
checked it at the top of each loop iteration, before a tick that might not
write anything (state only sent on change, with a 15s heartbeat). Under
`composer serve` — PHP's built-in dev server, which handles one request at
a time — a client that vanished during a quiet period went undetected for
up to 15 seconds, during which *every other request to the app* (including
unrelated ones) queued behind it. Confirmed with `curl --max-time 2` against
the SSE endpoint followed immediately by a normal request: the second
request took 28s. Fixed by writing a minimal `: ping` on every tick (not
just on real changes or a periodic heartbeat) and checking
`connection_aborted()` right after — disconnect-detection latency is now one
`poll_interval_seconds`, confirmed by the same test dropping to ~0.4s.

This is also the confirmation that PHP's built-in dev server cannot usefully
host more than one long-lived SSE connection at a time (it has no
concurrency at all) — true concurrent WebSocket + SSE + REST traffic needs
to be re-verified behind whatever production runtime (php-fpm, RoadRunner,
...) is eventually chosen; the WebSocket server doesn't share this
limitation, since Ratchet/ReactPHP is its own always-running event loop,
independent of the PHP SAPI serving HTTP requests.

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
  Doctrine implementation, DTOs, sync Command/Handler, REST endpoints,
  migration, Inertia/Vue list page, unit + integration tests. SongbookPro
  GraphQL client rewritten against the real API (delta sync via
  `DeltaFetcher`/retry/rate-limit, no ETag layer — see §6.4); synced songs
  have no sections yet, pending a confirmed content payload shape.
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
- ✅ Presentation module (see §7): `Display` registry, `SlideComposer`
  (Song → `SlideDeck`, chord-stripping + word-wrap + section-boundary-first
  pagination), `PresentationSession` live-control commands (Load/Next/
  Previous/JumpToSlide/Blank/Freeze/HideLyrics/FontSizeAdjust/
  EmergencyMessage), REST endpoints, migration, unit + integration tests.
  Also added `Song\Domain\Service\SectionRenderer` (chord-free content
  extraction) to the Song module as a prerequisite. Realtime transport
  (§7.5/§13) added in a follow-up increment: WebSocket server
  (`bin/websocket-server.php`, Ratchet, `cboden/ratchet` added to
  composer.json) and SSE fallback (`GET /sse/{displayId}`), both DB-poll
  based (§13.1) rather than the originally-sketched Messenger transport,
  manually verified end-to-end (two real bugs found and fixed in the
  process — §13.2/§13.3). Vue/Inertia operator UI added in a further
  follow-up increment (§7.5/§11.1): `Displays/Index.vue` (CRUD) and
  `Presentation/Control.vue` (live control, connected over WebSocket with
  an SSE fallback via `usePresentationStore`), manually verified in a real
  browser — caught and fixed one real gap (emergency message set state
  correctly but was never rendered anywhere on screen). The Presentation
  module is now feature-complete against §7's original design.
- ✅ Media module (see §19): `MediaAsset` (Flysystem-backed, local adapter),
  upload/list/get/download/remove REST endpoints, `Media/Index.vue` browse+
  upload UI, migration, unit + integration tests, manually verified in a
  real browser (upload, dimension extraction, thumbnail, search, remove —
  confirmed both DB row and on-disk file are cleaned up together). ⏳ Not
  wired into `SlideComposer`/live presentation yet — a deliberate scope cut
  (§19), not an oversight.
- ✅ Theme module (see §20): `Theme` (global/song/section scoped, with
  scope-target invariants enforced in the Domain layer), CRUD REST
  endpoints, `Themes/Index.vue` management UI (scope-conditional form
  fields), migration, unit + integration tests, manually verified in a real
  browser across all three scopes including the invariant validation path.
  ⏳ Not wired into `SlideComposer`/rendering yet — a deliberate scope cut
  (§20), same reasoning as Media's (§19).
- ✅ Plugin foundation + Bible module (see §12/§21), built together since
  Bible is explicitly designed as plugin-based: `PluginInterface`,
  `BibleProviderInterface`, `PluginRegistry` (discovers from
  `config/plugins.php`); `LocalBibleProvider` (first real plugin — a small
  bundled public-domain KJV excerpt, not a live API, per §21's own
  reasoning) providing search/passage lookup across a handful of
  well-known passages; `BibleBookmark` (Phpresent's own persisted data —
  never the scripture text itself, always provider-mediated), CRUD REST +
  `Bible/Index.vue`, migration, unit + integration tests, manually verified
  in a real browser (search → load passage → bookmark → remove, each
  confirmed against server state). ⏳ First-party device plugins (OBS,
  MIDI, StreamDeck, NDI, DMX) remain future capability interfaces with no
  implementation yet.
- ✅ Admin UI, scoped to what was genuinely missing (see §22): a `/`
  dashboard (static links grid, no cross-module stats), and Users/Roles
  management (§18) — the one module whose REST API already existed but had
  no UI (§18.4 flagged this as deferred). Building it surfaced a real
  pre-existing gap: `users.manage` could never be granted to anyone from an
  empty database (creating a user requires being logged in as someone who
  already has it) — fixed with `identity:create-admin`
  (`bin/console.php`), a bootstrap command that goes straight to the
  repositories the same way a framework seeder would, plus the first actual
  login *page* this app has had (`POST /login` existed as a REST endpoint
  from the start, but nothing rendered a form). Manually verified end to
  end in a real browser: bootstrapped an admin via the console, confirmed
  `/users` shows a clear "forbidden" message when logged out, logged in,
  created a role and a user, assigned the role, and deactivated the user —
  each step checked against the resulting UI state. Displays/Themes/Media
  already had their own pages (§7.5/§11.1/§19/§20); Bible didn't need one
  in this pass either (§21). ⏳ Logs/jobs/sync/cache panels remain
  unbuilt — deliberately: `jobs` has no queue to show, and a logs/sync
  viewer needs real backend work (querying `audit_log`, `sync_state`), not
  a UI bolted onto data no endpoint exposes yet.
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

### 16.8a §6 was rewritten against the real SongbookPro Groups API — the sync code has caught up on the Song side only

§6/§6.1–§6.3 were rewritten to describe the actual GraphQL API (endpoint,
auth, operations, data model), reverse-engineered from live traffic
against a real logged-in session — not from documentation, since none is
public and introspection is disabled in production. This replaced an
earlier, never-tested assumption of cursor-paginated `songs`/`songSets`
queries.

As of the §6.4 rewrite, the *Song*-side code has caught up:
`SongbookProGraphQLClient`, `DeltaFetcher`, `SongGraphQLMapper`, and
`SongSource` now target the real `dataItems`/`addDataItems` delta-sync
shape — the old `ETagCache` (no such layer exists on the real API) was
deleted outright rather than left dead. The *SongSet*-side code
(`SongSetGraphQLMapper`, `SongSetSource`) is still built against the old,
incorrect `songSets` connection assumption and will still fail against the
real endpoint — its blocker (the unconfirmed `type` value SongbookPro uses
for sets) wasn't resolved by this rewrite. Read §6.3/§6.4 before touching
it — capture real Sets-page traffic first, don't extend it as if it
already matched §6.2's shape.

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

## 19. Media Module (seventh increment)

Images/video/audio/document assets, Flysystem-backed (§2/§3). Scoped to
storage + REST + a browse/upload UI only (per this increment's own scope
decision) — wiring media into `SlideComposer`/`PresentationSession` so an
image or video can actually be shown live is deliberately left for a
follow-up increment, once there's a concrete reason (an operator wanting to
show a photo) to extend `SlideSourceType` (§7.1) beyond `Song`/`Blank`.

### 19.1 Domain Model

```
MediaAsset (aggregate root)
 ├─ id: MediaAssetId (UUIDv4)
 ├─ filename: string          // original uploaded filename, shown to users
 ├─ storageKey: string        // Flysystem path — never exposed to clients directly
 ├─ mimeType: string
 ├─ sizeBytes: int
 ├─ kind: MediaKind (Image|Video|Audio|Document)  // derived from mimeType
 ├─ width: ?int                // images only
 ├─ height: ?int                // images only
 └─ uploadedAt: DateTimeImmutable
```

Design decisions:

- **Assets are immutable once uploaded.** No `update()` — replacing content
  means uploading a new asset and removing the old one. This sidesteps any
  "what happens to a `storageKey` something else already references" question
  entirely, and matches how every other synced/uploaded entity in this
  codebase treats its identity as stable once created.
- **No `durationSeconds` field for video/audio.** Extracting it would need a
  media-inspection library this project doesn't have (`getimagesize()` only
  covers raster images). Rather than add a column that's permanently null,
  the field doesn't exist — same reasoning as `PresentationSession` not
  having `SongSet`/`Bible` `SlideSourceType` cases yet (§7.1), or Identity's
  `Role`↔`User` deferring a join table (§18.1). Add it when duration is
  actually extractable, not before.
- **`width`/`height` are only ever populated for images**, extracted from
  the actual uploaded bytes (`FlysystemMediaStorage::write()`), never
  fabricated or left as a guess for other kinds.

### 19.2 Application Layer

- `MediaStorageInterface` (`Media\Application\Service`) — the Flysystem-
  facing port, same placement convention as `SongSourceInterface` (§5).
  `write(storageKey, mimeType, StreamInterface $contents): array{width,
  height}`, `readStream(storageKey): StreamInterface`, `delete(storageKey)`.
  Takes a PSR-7 `StreamInterface` for content rather than a raw string so
  large uploads (video) are never required to be fully buffered in memory —
  the same "PSR interfaces are a standing exception to the Infrastructure
  boundary" carve-out already established for `LoggerInterface` (§2).
  `write()` takes `mimeType` specifically so the implementation can decide,
  before touching any bytes, whether dimension extraction (which *does*
  need the full byte string, via `getimagesizefromstring()`) is worth
  attempting — video/audio/documents stream straight through via
  `writeStream()` and are never buffered.
- `UploadMediaAssetCommand(filename, mimeType, sizeBytes, contents)` /
  `UploadMediaAssetHandler` — generates a collision-proof storage key
  (`{uuid}-{sanitized filename}`; sanitization strips everything but
  `A-Za-z0-9._-` and runs the result through `basename()` first, so a
  crafted `../../etc/passwd`-style filename can't escape the storage root),
  writes via `MediaStorageInterface`, persists the resulting `MediaAsset`.
- `RemoveMediaAssetCommand`/`Handler` — deletes from storage *and* the
  repository; a partial failure (storage succeeds, DB row lingers, or vice
  versa) isn't specially handled — same level of rigor as the rest of this
  codebase's non-transactional multi-step operations.
- `SearchMediaAssetsQuery`/`Handler`, `GetMediaAssetQuery`/`Handler` — same
  shape as every other module's search/get split (§5).
- `GetMediaAssetContentQuery`/`Handler` → `MediaAssetContent` (filename +
  mimeType + `StreamInterface`) — the one query in this codebase that
  returns bytes rather than a DTO of scalars, for
  `DownloadMediaAssetHandler` for stream a binary HTTP response.

### 19.3 Infrastructure

`FlysystemMediaStorage` implements `MediaStorageInterface` against
`League\Flysystem\FilesystemOperator` (`league/flysystem-local`'s
`LocalFilesystemAdapter`, rooted at `media.storage_path`
— S3-compatible adapters are listed in §3 as a future option but not
wired). Flysystem's own API is resource-based, not PSR-7, so this class is
also where that translation happens: `StreamInterface::detach()` for
outgoing writes (falling back to a manual `php://temp` copy if the stream
isn't resource-backed), and `Laminas\Diactoros\Stream` wrapping Flysystem's
returned resource for reads.

### 19.4 Database Schema

```sql
CREATE TABLE media_assets (
    id                CHAR(36) PRIMARY KEY,
    filename          VARCHAR(255) NOT NULL,
    storage_key       VARCHAR(512) NOT NULL,
    mime_type         VARCHAR(191) NOT NULL,
    size_bytes        INTEGER NOT NULL,
    kind              VARCHAR(16) NOT NULL,
    width             INTEGER,
    height            INTEGER,
    uploaded_at       DATETIME NOT NULL
);
CREATE UNIQUE INDEX uniq_media_assets_storage_key ON media_assets(storage_key);
CREATE INDEX idx_media_assets_filename ON media_assets(filename);
```

### 19.5 REST API

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/media` | paginated list + filename search (`?q=`) |
| GET | `/api/media/{id}` | single asset metadata |
| GET | `/api/media/{id}/file` | streamed asset bytes, correct `Content-Type` |
| POST | `/api/media` | multipart upload (field `file`), rejects over `media.max_upload_bytes` with `413` |
| DELETE | `/api/media/{id}` | remove (storage + database) |

### 19.6 Frontend

`Pages/Media/Index.vue` — a card grid (not `n-data-table`, unlike every
other list page in this codebase — a media library reads naturally as
thumbnails, not table rows). Images render via `<img :src="/api/media/
{id}/file">` directly; video/audio/documents show an icon instead of
attempting a thumbnail. `useMediaStore` wraps list/search/upload (via
`FormData`, not JSON — the one store in this codebase that isn't a plain
JSON `fetch()` wrapper, §11.1's `usePresentationStore` being the other
exception)/remove.

Manually verified end-to-end (Chrome, computer-use browser automation):
uploaded a real PNG, confirmed the server-extracted dimensions (400×300)
matched the source file exactly, confirmed the thumbnail rendered from the
streamed `/file` endpoint, searched, and removed — then confirmed via the
REST API and the filesystem directly that removal deleted *both* the
database row and the on-disk file, not just one or the other.

## 20. Theme Module (eighth increment)

A visual style, at one of three scopes (SDD §2: "theme engine (global /
song / section scoped)"). Scoped to storage + CRUD REST + a management UI
only, same cut as Media's (§19) — resolving *which* theme actually applies
to a given slide during rendering (the natural next step: Section overrides
Song overrides Global) is left for a follow-up increment that touches
`SlideComposer`, once `SlideComposer` itself is being extended anyway
rather than as a side effect of this one.

### 20.1 Domain Model

```
Theme (aggregate root)
 ├─ id: ThemeId (UUIDv4)
 ├─ name: string
 ├─ scope: ThemeScope (Global | Song | Section)
 ├─ songExternalId: ?string     // set only when scope = Song
 ├─ sectionType: ?string         // set only when scope = Section — plain
 │                               // string, not Song\Domain\ValueObject\
 │                               // SectionType (cross-module Domain rule,
 │                               // same as Slide::sectionType, §7.1)
 ├─ backgroundColor: ?string     // hex color
 ├─ backgroundMediaAssetId: ?string  // plain string ref into Media module
 ├─ fontFamily: ?string
 ├─ fontColor: ?string
 ├─ fontSizeScale: float (default 1.0)
 ├─ textAlign: TextAlign (Left | Center | Right)
 ├─ createdAt: DateTimeImmutable
 └─ updatedAt: DateTimeImmutable
```

Design decisions:

- **Scope and target are validated together as one Domain invariant**,
  enforced in the constructor and re-checked on `update()`: `Global` must
  have neither `songExternalId` nor `sectionType` set; `Song` requires
  `songExternalId` and forbids `sectionType`; `Section` requires
  `sectionType` and forbids `songExternalId`. `InvalidThemeScopeException`
  carries a distinct factory method per violation
  (`songExternalIdRequired()`, `sectionTypeRequired()`,
  `targetNotAllowed()`) so the REST error message says exactly what was
  wrong, not a generic "invalid theme."
- **No validation that a referenced `songExternalId` or
  `backgroundMediaAssetId` actually exists.** Same reasoning as
  `SongSetItem::songExternalId` (§17.1) — a theme can legitimately be
  authored before the song it targets is synced, or before an image is
  uploaded.
- **No precedence/resolution logic lives here.** `ThemeScope`'s three
  levels are *named* with an implied specificity ordering (Section overrides
  Song overrides Global), but nothing in this increment resolves "which
  theme wins for this slide" — that's rendering logic, not a storage
  concern, and belongs with the `SlideComposer` follow-up.

### 20.2 Application Layer

`CreateThemeCommand`/`Handler`, `UpdateThemeCommand`/`Handler`,
`RemoveThemeCommand`/`Handler`, `ListThemesQuery`/`Handler`,
`GetThemeQuery`/`Handler` — same CRUD shape as every other module (§5).
`scope`/`textAlign` are passed as strings and parsed via `ThemeScope::
from()`/`TextAlign::from()` inside the handler (native `ValueError` on an
unknown value), matching the `DisplayRole::from()` precedent (§7.2) rather
than introducing bespoke parsing.

### 20.3 Database Schema

```sql
CREATE TABLE themes (
    id                          CHAR(36) PRIMARY KEY,
    name                        VARCHAR(191) NOT NULL,
    scope                       VARCHAR(16) NOT NULL,
    song_external_id            VARCHAR(191),
    section_type                VARCHAR(24),
    background_color            VARCHAR(16),
    background_media_asset_id   VARCHAR(191),
    font_family                 VARCHAR(191),
    font_color                  VARCHAR(16),
    font_size_scale             FLOAT NOT NULL DEFAULT 1.0,
    text_align                  VARCHAR(8) NOT NULL,
    created_at                  DATETIME NOT NULL,
    updated_at                  DATETIME NOT NULL
);
CREATE INDEX idx_themes_scope ON themes(scope);
```

### 20.4 REST API

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/themes` | list all themes |
| GET | `/api/themes/{id}` | get one theme |
| POST | `/api/themes` | create a theme |
| PATCH | `/api/themes/{id}` | update a theme |
| DELETE | `/api/themes/{id}` | remove a theme |

`POST`/`PATCH` catch both `ValueError` (bad `scope`/`textAlign`) and
`InvalidThemeScopeException` (bad scope/target combination), returning
`400` with the exception's own message — same explicit-catch convention as
elsewhere (§10/§18.4) rather than a generic exception-to-response mapper.

### 20.5 Frontend

`Pages/Themes/Index.vue` — a form (create or edit, `editingId` tracking
which) above an `n-data-table` list, mirroring the create-form-above-table
shape of `Displays/Index.vue` (§11.1). The scope `n-select` conditionally
reveals a Song-External-ID input or a Section-Type `n-select` — verified in
a real browser to actually reveal/hide correctly and to submit the right
combination for all three scopes, plus that the REST layer's
`InvalidThemeScopeException` message surfaces back to the operator when a
save is attempted with an invalid combination. `useThemesStore` wraps
list/create/update/remove; unlike every earlier CRUD store in this
codebase, it parses the REST error body's `title` for display (`Displays`'
store just throws a generic message) — worth doing here specifically
because the Domain invariant errors (§20.1) are the one case in this
module where the *server's* validation message is actually informative to
show the user, not just "request failed."

## 21. Bible Module (ninth increment)

"Plugin-based translation providers, search, presentation" (§2). Built
together with the Plugin foundation (§12) since Bible has no meaning
without a provider — see §12 for `PluginInterface`/`BibleProviderInterface`/
`PluginRegistry`, and `LocalBibleProvider`'s own docblock
(`src/Bible/Infrastructure/Plugin/LocalBibleProvider.php`) for why its data
is a small bundled KJV excerpt rather than a live API integration. Scoped
to search/passage/bookmark storage + REST + UI only — wiring a passage into
`SlideComposer`/live presentation is left for a follow-up, same cut as
Media (§19) and Theme (§20).

### 21.1 Domain Model

Phpresent never stores scripture text itself — that always comes from a
`BibleProviderInterface` plugin (§12). The only thing the Bible module
persists is a **pointer** to a passage, for quick recall during a service:

```
BibleBookmark (aggregate root)
 ├─ id: BibleBookmarkId (UUIDv4)
 ├─ translationId: string      // scoped to whichever provider owns it
 ├─ book: string
 ├─ chapter: int
 ├─ startVerse: ?int
 ├─ endVerse: ?int
 ├─ label: ?string             // e.g. "Sermon text"
 └─ createdAt: DateTimeImmutable
```

Immutable once created — same reasoning as `MediaAsset` (§19): no
partial-edit use case, remove and re-create instead.

### 21.2 Application Layer

- `ListBibleTranslationsQuery`/`Handler`, `SearchBibleQuery`/`Handler`,
  `GetBiblePassageQuery`/`Handler` — none of these talk to a repository;
  they fan out across `PluginRegistry::bibleProviders()` instead.
  **Translations**: every provider's `translations()` list is merged and
  tagged with `providerId`, since more than one provider can be registered
  at once. **Search/passage**: every registered provider is asked in turn
  with the given `translationId`; each provider is responsible for
  recognizing its own translation ids and returning nothing (`[]` /
  `null`) otherwise (part of `BibleProviderInterface`'s contract, §12), so
  no separate translationId-to-provider routing table exists at this
  layer — the first non-empty/non-null answer wins.
- `CreateBookmarkCommand`/`Handler`, `RemoveBookmarkCommand`/`Handler`,
  `ListBookmarksQuery`/`Handler` — plain CRUD against
  `BibleBookmarkRepositoryInterface`, same shape as every other module (§5).
  No validation that `translationId`/`book`/`chapter` correspond to a real,
  currently-resolvable passage — same reasoning as `SongSetItem`'s
  unsynced-song tolerance (§17.1): a bookmark can legitimately reference
  something not available right now (a provider that's since been
  unregistered, for instance) without that being an error.

### 21.3 Database Schema

```sql
CREATE TABLE bible_bookmarks (
    id                CHAR(36) PRIMARY KEY,
    translation_id    VARCHAR(64) NOT NULL,
    book              VARCHAR(191) NOT NULL,
    chapter           INTEGER NOT NULL,
    start_verse       INTEGER,
    end_verse         INTEGER,
    label             VARCHAR(191),
    created_at        DATETIME NOT NULL
);
```

### 21.4 REST API

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/bible/translations` | merged translation list across all registered providers |
| GET | `/api/bible/search` | `?translationId=&q=&limit=` |
| GET | `/api/bible/passage` | `?translationId=&book=&chapter=&startVerse=&endVerse=` |
| GET | `/api/bible/bookmarks` | list saved bookmarks |
| POST | `/api/bible/bookmarks` | save a bookmark |
| DELETE | `/api/bible/bookmarks/{id}` | remove a bookmark |

### 21.5 Frontend

`Pages/Bible/Index.vue` — a search box (translation selector +
debounced query, results clickable to load that verse's full chapter), a
passage viewer (book/chapter/verse-range inputs + a "Save Bookmark" button
that appears once a passage is loaded), and a bookmarks list (click to
reload that passage, confirm-then-remove). `useBibleStore` is the one
store so far that reads from *two* independent REST resources
(translations and bookmarks) into one page's initial props, plus two more
(search, passage) fetched on demand — `setInitial()` seeds both from the
page handler's props in one call rather than two separate store methods.

Manually verified end-to-end in a real browser: searched "love" (matched
John 3:16 and Romans 8:28, per `KjvFixtureData`), clicked a result to load
its full chapter, saved it as a labeled bookmark, confirmed the exact verse
range persisted server-side via the REST API, and removed the bookmark —
each step checked against actual server state, not just that the UI looked
right.

## 22. Admin UI (tenth increment)

"Dashboard, users, roles, displays, themes, media, logs, jobs, sync,
cache" (§15). Displays/Themes/Media/Bible already had their own pages by
this point; what was actually missing was a home page and Users/Roles
management (§18's REST API existed from the third increment on, but §18.4
explicitly deferred its UI). Scoped to exactly those two pieces —
logs/jobs/sync/cache panels need real backend work first (see §22.3).

### 22.1 The bootstrap gap this increment surfaced

Building a UI that could actually create/manage users meant *using* it,
which meant logging in, which surfaced a real gap that had been latent
since the Identity module was built: every mutating Identity command
requires an authenticated actor who already holds the relevant permission
(§18.2) — `CreateUserHandler` requires `users.manage`, which only a `User`
with the right `Role` can have, but from an empty database no `User` or
`Role` exists yet. There was no way to ever create the first one through
normal, permission-gated application code. (`LoginHandler` existed as a
REST endpoint from the third increment, but nothing rendered a page a
human could fill in and submit — the login *form* was also missing.)

Fixed with `bin/console.php identity:create-admin` — a Symfony Console
command (`Identity\Presentation\Console\CreateAdminCommand`) that talks to
`UserRepositoryInterface`/`RoleRepositoryInterface` directly, bypassing
`PermissionInterface` on purpose. This is the same shape as a framework's
seeder/fixture command: an install-time operation run with shell access,
not a web request, so it isn't gated the same way normal application code
is. It creates (or reuses, if run again) an `admin` `Role` with all four
permissions that exist anywhere in this codebase today
(`users.view`/`users.manage`/`roles.view`/`roles.manage`, §18.2) and one
`User` in it.

```
php bin/console.php identity:create-admin \
    --email=admin@example.com --password=... --display-name="Admin"
```

### 22.2 Frontend

- `Pages/Auth/Login.vue` (`GET /login`, `Identity\Presentation\Http\
  Handler\LoginPageHandler`) — a plain email/password form posting
  directly to the existing `POST /login` REST endpoint, then
  `router.visit('/')`. `AppLayout.vue` gained unconditional Login/Logout
  links — deliberately *not* conditional on auth state, since that would
  need a shared-prop mechanism `InertiaResponseFactory` doesn't have yet
  (§16.3 already flags shared props as a known future extension point, not
  built speculatively here).
- `Pages/Identity/Users.vue` (`GET /users`,
  `Identity\Presentation\Http\Handler\UsersIndexPageHandler`) — a Roles
  card (create form + table) and a Users card (create form + table with
  per-row role-assignment and deactivate actions), matching the create-
  form-above-table shape used by every other management page in this
  codebase (`Displays/Index.vue`, `Themes/Index.vue`). The one thing that
  makes this page different from those: its initial data is
  permission-gated. `UsersIndexPageHandler` catches
  `PermissionDeniedException` from the underlying `ListUsersHandler`/
  `ListRolesHandler` calls and renders the page anyway with `forbidden:
  true` and empty lists rather than fataling — this app has no route-level
  auth middleware that rejects a request outright (§18.4: `Authentication
  Middleware` "never rejects a request itself"), so an anonymous or
  under-privileged visit to `/users` still needs *something* coherent to
  render. The page shows a plain "you don't have permission" message in
  that case.
- `Pages/Dashboard.vue` (`GET /`, `Shared\Presentation\Http\Handler\
  DashboardPageHandler`) — a static card grid linking to every admin
  area. Deliberately doesn't fetch cross-module counts/stats (song count,
  active displays, ...) — that's a real feature with its own design, not
  something to bolt on as a side effect of finally giving the app a home
  page.

### 22.3 What's still not built, and why

- **Logs** — `audit_log` (§18.3) is populated by every mutating Identity
  command already, but nothing reads it back. A viewer needs a new
  `ListAuditLogEntriesQuery`/endpoint first; this increment didn't add one
  because "expose an existing table" is a small but real feature, not a
  UI-only add-on.
- **Jobs** — there is no job queue in this codebase at all (Symfony
  Messenger is listed in the tech stack, §3, but nothing has actually been
  wired to an async transport in any increment so far — every "Command" in
  this app runs synchronously, in-process). A jobs panel has nothing to
  show.
- **Sync** — `sync_state` (§9/§16) holds the last-synced-at cursor per
  entity type, but nowhere records sync *history* (individual pass
  results, errors). A "sync" admin panel showing anything useful needs
  that history captured first.
- **Cache** — nothing in this codebase currently exposes cache
  statistics or a manual "clear cache" action; the PSR-16 cache
  (`config/autoload/dependencies.global.php`) is just consumed, never
  inspected.

Each of these is "add a small piece of real backend, then a thin page for
it" — the same pattern this increment followed for Users/Roles — not
attempted here because none of the underlying data existed yet to show.
