# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

This is the **backend** of OpenLifting. The umbrella project (mobile + ESP32 + this backend) is documented one directory up at `../CLAUDE.md`. A sibling document, `contexto.md`, contains the full Spanish-language API and schema specification — treat it as the design contract for endpoints and request/response shapes, but defer to `database/schema.sql` whenever the two disagree (the schema file is newer).

## Stack

- Laravel 12 / PHP ^8.2 (no starter kit — JSON API only, no Blade/Breeze/Jetstream)
- PostgreSQL (`DB_CONNECTION=pgsql`, db name `backend_openlifting`)
- Auth: Laravel Sanctum (bearer tokens) — **planned, not yet installed**
- Tests: PHPUnit (`tests/Feature`, `tests/Unit`)
- Dev tooling: Pint (formatter), Pail (log tailer), Sail (Docker), Boost (`boost.json` enables `laravel-best-practices` skill)

## Common commands

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate                         # uses pgsql per .env
php artisan serve                           # http://127.0.0.1:8000

php artisan make:model Foo -m               # model + migration
php artisan make:controller Api/FooController --api
php artisan make:request StoreFooRequest    # form request validation
php artisan make:resource FooResource       # JSON serialization

composer test                               # config:clear + artisan test
php artisan test --filter=NameOfTest        # single test
vendor/bin/pint                             # format
composer dev                                # concurrently runs serve + queue + pail + vite
```

`composer dev` requires `npm install` first (concurrently is npm-installed). Vite is only here because Laravel ships it; this repo is API-only and has no frontend assets to ship.

## Architecture — what to know before writing code

**The backend is a sync target, not a compute engine.** The Android app is offline-first: it captures EMG data, computes every metric (BSA percentages, H:Q ratio, ES:GMax ratio, intra-set fatigue) on-device, generates recommendations locally, then POSTs the finished set in one shot. Do not add server-side recomputation of metrics in controllers, jobs, or observers — store what arrives. The `set_metrics.thresholds_version` column exists so a future server-side reanalysis can identify which threshold set produced each row, but that reanalysis is out of scope for the MVP.

**One POST ingests an entire set.** The intended endpoint `POST /api/sessions/{id}/sets` accepts a nested payload of `set fields + reps[] + activations[] (per rep) + metrics{} + recommendations[]` and writes across `training_sets`, `reps`, `muscle_activations`, `set_metrics`, and `recommendations` in a single transaction. Wrap the insert chain in `DB::transaction()` so a partial failure doesn't leave orphaned rows. See `contexto.md` §"Series" for the full payload shape.

**Schema is in `database/schema.sql`, not migrations.** The `database/migrations/` directory still contains only the default Laravel scaffold (`users`, `cache`, `jobs`); the real schema (10 tables: roles, users, athlete_profiles, mvc_calibrations, instructor_athlete pivot, training_sessions, training_sets, reps, muscle_activations, set_metrics, recommendations) lives in `schema.sql` and has not been translated to migrations yet. When you add migrations, follow the dependency order at the top of `schema.sql`. Note `schema.sql` uses `ON DELETE RESTRICT` everywhere (and a separate `roles` table with `users.role_id`), which differs from what `contexto.md` describes (CASCADE, inline `users.role` string) — `schema.sql` wins.

**Soft deletes.** Every domain table carries `deleted_at` — use Eloquent's `SoftDeletes` trait on all models.

**Enums are stored as strings with CHECK constraints**, not as enum tables. The allowed values for `muscle`, `side`, `variant`, `depth`, `severity`, `device_source`, `sex` are enforced at the DB layer; mirror them in PHP enums or validation rules so invalid values are rejected before INSERT.

**Two roles, one users table.** `athlete` and `instructor` users live in the same table differentiated by `role_id`. Athletes have a 1:1 `athlete_profiles` row; instructors don't. The `instructor_athlete` pivot links them; instructor endpoints must check the requesting user owns the link before exposing an athlete's sessions.

**Routing.** `bootstrap/app.php` currently registers only `web` and `console` route files (Laravel 12's slim bootstrap). When adding API routes, also register `api: __DIR__.'/../routes/api.php'` in the `withRouting()` call and create that file — `php artisan install:api` does this and installs Sanctum in one step.

## Conventions

- User-facing strings (recommendation text, validation messages exposed to the app) in Spanish; code, identifiers, comments in English.
- API controllers under `App\Http\Controllers\Api\`; use Form Requests (`App\Http\Requests\`) for validation and API Resources (`App\Http\Resources\`) for serialization rather than returning raw models.
- No WebSockets, no broadcasting, no AI/LLM calls — explicitly out of scope (see `../CLAUDE.md`).
