# Phpresent

A church/band presentation application (songs, Bible verses, announcements,
media, countdowns) that treats [SongbookPro Groups](https://groups.songbookpro.com)
as the single source of truth for song and set content, synchronized via its
GraphQL API. Phpresent never edits song content — see
[`docs/sdd.md`](docs/sdd.md) for the full architecture and design rationale.

Built incrementally; `docs/sdd.md` §15 tracks what's implemented vs. planned.

## Stack

PHP 8.5+ · Mezzio · Doctrine ORM 3 ·
Symfony Cache/Messenger/Serializer/Validator · Vue 3 + TypeScript · Inertia.js
· Vite · Naive UI · Pinia. Full list in `docs/sdd.md` §3.

## Getting started

```bash
composer install
npm install

cp config/autoload/local.php.dist config/autoload/local.php
# edit config/autoload/local.php: SONGBOOKPRO_API_URL / API_TOKEN / GROUP_ID

composer migrate   # create the SQLite schema
npm run dev         # Vite dev server (separate terminal)
composer serve       # PHP built-in server on :8080
```

Or via Docker: `docker compose up --build`.

## Common tasks

| Command | Purpose |
|---|---|
| `make test` | Run the full Pest suite |
| `make test-unit` / `make test-integration` | Run one suite |
| `make stan` | PHPStan (level max) |
| `make cs` / `make cs-fix` | Check/apply code style |
| `composer sync:songs` | Trigger a foreground SongbookPro sync |

## Project layout

Clean Architecture per bounded-context module — see `docs/sdd.md` §2 and §14
for the full rationale and directory map.

```
src/<Module>/{Domain,Application,Infrastructure,Presentation}
config/            Mezzio config, DI wiring, routes
migrations/         Doctrine migrations
resources/js/       Vue/Inertia frontend
test/{Unit,Integration}/
```

## Status

First increment: project scaffolding + the Song module end-to-end (sync from
SongbookPro GraphQL, REST API, Inertia/Vue songs list, tests). See
`docs/sdd.md` §15 for the roadmap of remaining modules (sets, presentation
engine, live control, media, Bible, themes, plugins, admin UI, ...).

> **Note on this environment**: dependencies were authored and syntax/type
> checked here, but `composer install` could not complete in this sandbox
> because outbound access to GitHub's dist/codeload endpoints is blocked by
> the session's network policy. Run `composer install` in an environment
> with normal Packagist/GitHub access (e.g. CI) before running the PHP test
> suite. The frontend (`npm install`, `npm run typecheck`, `npm run build`)
> was verified successfully in this session.
