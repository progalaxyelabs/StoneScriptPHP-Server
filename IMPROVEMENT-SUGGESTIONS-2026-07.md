# StoneScriptPHP Framework - Improvement Suggestion

**Date**: 2026-07-04
**Project**: ProGalaxy Platform (fleet-wide)
**Framework Version**: StoneScriptPHP v5.5.4
**Submitted By**: ProGalaxy Development Team

---

## Opt-in framework schema (e.g. RequestLogging) is silently never adopted

### Problem

**Framework features that ship their own SQL schema (a table + function under
`src/<Feature>/Schema/`) have no discovery or activation mechanism — a platform
can upgrade past the version that introduces them and never know they exist.**

`RequestLogging` (added per the framework CHANGELOG) is the concrete case: the
CHANGELOG says *"Platforms copy/symlink and run `php stone migrate up` to
activate"* — but that's a line in a changelog, not something that happens
during `composer install`/`composer update`. We audited every platform in our
fleet on 2026-07-04 and **not one had copied the two schema files in**, despite
several having upgraded through the framework version that introduced the
feature. In practice, `RequestLogger::arm()` runs on every request (the code
half auto-activates unconditionally), finds the table/function missing, and
fails open — logging a `[RequestLogger] DB write failed (fail-open)` warning
on every single request, forever, on every platform, since nobody knew there
was a step to take.

**Root cause, confirmed by reading the actual mechanism (not assumed):**
`php stone gateway:register-main` / `gateway:migrate-main` build their schema
archive exclusively from `ROOT_PATH/src/postgresql` — the *platform's own*
committed tree (`cli/helpers/gateway-common.php`). There is no code path that
ever reads `vendor/progalaxyelabs/stonescriptphp/src/**/Schema/`. So even a
platform that runs migrations religiously on every deploy will never pick this
up — the files simply don't exist anywhere the tool looks until a human
manually copies them in, and nothing ever prompts that human to do so.

Compare this to the `stone` CLI file itself, which **is** kept in sync
automatically via this project's own `composer.json`:
```json
"post-install-cmd": [
    "@php -r \"if (file_exists('vendor/progalaxyelabs/stonescriptphp/stone')) { copy('vendor/progalaxyelabs/stonescriptphp/stone', 'stone'); chmod('stone', 0755); echo '✅ Updated stone CLI from framework\\n'; }\""
],
"post-update-cmd": [ /* identical */ ]
```
This works well for `stone` because it's pure CLI dispatch code — copying it
automatically carries no risk. Opt-in schema is a different risk category and
needs a different treatment (see below).

### Why we didn't just extend the same post-install copy to schema files

The obvious fix — add the two `RequestLogging` files to that same post-install
copy step — was our first instinct, but it doesn't generalize safely:

- **StoneScriptPHP is opensource.** Contributors can add or modify anything
  under `src/**/Schema/`. Auto-copying schema files (unlike a CLI dispatcher)
  means auto-materializing table/function definitions that a platform
  maintainer never reviewed — and if that copy step were ever also wired into
  the *existing* automatic `register-main`/`migrate-main` deploy path (an easy
  mistake to make later, since that's the "normal" migration mechanism),
  unreviewed vendor SQL would silently execute against production databases
  on a routine deploy. That's a real supply-chain-shaped risk for a
  security/data-loss-sensitive operation (schema + data), not an OK tradeoff
  for convenience.
- A **console-log-only** nudge (print "these files are available, go copy
  them" during install) doesn't actually solve the discoverability problem
  either — `composer install`/`composer update` run non-interactively in every
  Docker build and CI pipeline in our fleet. Nobody was ever going to be
  watching that stdout. This is *exactly* how the current gap happened: the
  CHANGELOG already told us to copy the files, and it didn't work because
  nobody reads changelogs during automated builds.

### Suggested Solution: a separate, clearly-labeled `postgres/vendor/` staging area (Recommended)

1. **`stonescriptphp-server`'s `composer.json`** (`post-install-cmd` /
   `post-update-cmd`, same array that already copies `stone`) gains a step
   that copies every `vendor/progalaxyelabs/stonescriptphp/src/**/Schema/**`
   file into a new **`postgres/vendor/`** folder in the platform's own tree —
   sitting alongside the existing `postgres/main/` and `postgres/tenant/`.
   This folder is **not** committed to the platform's repo (regenerated fresh
   on every install/update, exactly like `vendor/` itself) — so it always
   reflects whatever framework version is *currently* installed, and a
   `git status`/fresh clone immediately makes newly-available opt-in schema
   visible as files on disk. No changelog-reading required.
2. **`stonescriptphp`'s CLI** gains a new, separate command:
   `php stone gateway:migrate-vendor-main`. It builds its schema archive from
   `postgres/vendor/` (never from `main`/`tenant`) and requires the same
   explicit invocation `migrate-main` already does — idempotent, hash-checked,
   skips with "nothing changed" unless re-run with `--force`, matching the
   existing `register-main`/`migrate-main` behavior exactly.
3. **Critically, `migrate-vendor-main` is never called automatically by
   `register-main`/`migrate-main` or by any deploy-manager post-deploy hook.**
   Activating a vendor-provided feature stays a deliberate, reviewable act a
   maintainer runs once (ideally after reading the diff in `postgres/vendor/`
   for that release) — it just no longer requires *remembering that the
   feature exists* in the first place, since the files are sitting in the
   working tree either way.

This puts the line exactly where it needs to be: **automatic** for staying in
sync with what the framework *offers* (files show up, easy to spot), but
**always deliberate** for what actually gets *executed* against a real
database.

### Real-World Impact

- **`RequestLogging` unregistered fleet-wide** (all platforms we checked,
  2026-07-04) — every request on every platform logs a fail-open DB-write
  warning that nobody has ever seen turned off, because nobody knew there was
  a step to take.
- This is a pure log-noise / lost-observability issue today (fails open,
  never blocks a real request) — but the *pattern* (an opt-in framework
  feature nobody adopts because there's no discovery mechanism) will recur for
  every future opt-in schema feature the framework ships, at increasing cost
  each time.

### Conclusion

We'd like to contribute an implementation of this (`postgres/vendor/` staging
folder in `stonescriptphp-server`'s post-install hook + `gateway:migrate-vendor-main`
in `stonescriptphp`'s CLI) if the maintainers are open to the direction above.

---

**Contact**: ProGalaxy Development Team
**Framework**: StoneScriptPHP v5.5.4
**Related**: `IMPROVEMENT-SUGGESTIONS.md` (2025-12-31 batch)
