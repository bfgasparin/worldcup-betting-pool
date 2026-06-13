# Investigation: "a lot of tests are breaking" (2026-06-13)

**Date:** 2026-06-13 · **Branch observed:** `develop` / `fix/honor-live-ended-score-gate`
**Verdict:** The failures are **not** caused by a single bad commit. They fall into **three distinct
buckets**, two real and one a measurement artifact:

| # | Bucket | ~Count | Root cause | "When it started" |
|---|--------|--------|------------|-------------------|
| 1 | **Time-overtaken** (the bulk) | ~37 | Suite runs on the **real wall clock**; the seeded WC2026 fixtures are dated **2026-06-11 → 07-19**, so the group prediction/join window is now closed. | The **calendar**, not a commit — first/biggest wave on **2026-06-11 ~18:00 UTC** (group lock) / **19:00 UTC** (first kickoff). More fall as the calendar advances. |
| 2 | **`acefb42` regression** | 1 | `acefb42` (2026-06-12) renumbered group fixtures; the simulator hashes predictions on `match_number`, so the deterministic world drifted tie-free and a guard trips. | Commit `acefb42`, merged to develop **2026-06-12**. |
| 3 | **Test-DB contamination** (not real) | ~19 | Two `RefreshDatabase` suites ran **concurrently** against the shared Postgres `testing` DB → schema race. | Only during the overlapping run; gone on a clean re-run. |

The local/CI test DB is **Postgres** (`Host: pgsql`), and there is **no global clock freeze** in
`TestCase.php`, `phpunit.xml`, or CI (`tests.yml` runs `phpunit` on real `now()` with no fake-clock
env). That last fact is *the* reason bucket 1 exists.

---

## Bucket 1 — Time-overtaken (the "a lot of tests")

### Cause

- The suite uses the **real clock** (no `Carbon::setTestNow` in `TestCase::setUp`, no date env in
  `phpunit.xml`, no pin in CI).
- `WorldCup2026Seeder` dates fixtures **2026-06-11 (first kickoff) → 2026-07-19 (final)**.
- `Pool::predictionsLockAt()` = `Tournament::firstGroupKickoffAt()` − `prediction_lock_buffer_minutes`
  (60). First kickoff = 2026-06-11 15:00 ET = **19:00 UTC**, so the FFA group window **locked
  2026-06-11 ~18:00 UTC**. `Pool::acceptsPredictions()` = `now() < lock` → now **false**.
- Today is **2026-06-13**, two days past the lock and into the live group stage.

Consequences that the failing tests assert against:
- Joining and predicting are gated on the open window → the controllers now return **403**.
- Window/derived props flip: `pool.can_join`, `pool.can_edit`, `completion.is_complete`,
  `attention.needs_attention`, `joinedPools`, tournament status `Upcoming → InProgress`.
- Joins don't create entries → `PlayerJoinedPoolNotification` not sent; `predictAllGroups($entry)`
  receives `null`.

### Proof (empirical — the clock is the only variable)

A throwaway probe seeded WC2026 and ran the *exact* join flow from
`JoinPoolTest::test_a_player_joins_and_an_entry_is_created` under two clocks:

- **Real clock:** `POST pools.join` → **403** (`Expected response status code [...] but received 403`) — the
  same signature as the real failures.
- **`travelTo('2026-05-01')`:** identical flow → **302 redirect + entry created**, `acceptsPredictions()` true. ✅

(Probe written, run, and deleted — not committed.)

### "When did they start breaking?"

By **calendar date**, not by commit:
- **2026-06-11 ~18:00 UTC** — group prediction/join window locks (first wave; join + group-predict +
  attention/completion tests).
- **2026-06-11 19:00 UTC** — first kickoff; tournament status `Upcoming → InProgress`.
- **Through 2026-06-27** — remaining group fixtures pass into the past (more list/standings/status
  assertions drift).
- **2026-06-28 → 07-19** — phased-bracket knockout-window tests fall round-by-round as each round's
  kickoff passes.

CI has no date pin, so CI on `develop` went red on the same dates (not gated on the merges since).

### Affected tests (clean run)

- `PredictionControllerTest` (13) — predict page/save require the open window.
- `Unit\Services\Pools\PredictionAttentionTest` (7) — attention/completion keyed on the window.
- `JoinPoolTest` (7) — join window closed.
- `JoinedPoolsSidebarTest` (5) — sidebar attention; `predictAllGroups` gets a null entry.
- `PredictionOrderingTest` (4) — tie ordering requires the open window.
- `PoolControllerTest` (2) — `can_join` / attention summary props.
- `Console\PreRegisterUserTest` (2) — `user:pre-register` pre-joins, now blocked.

---

## Bucket 2 — `acefb42` regression (1 test, real, commit-introduced)

`SimulateTournamentTest::test_by_default_every_upfront_entry_has_a_fully_resolved_bracket`.

- The test runs `tournament:simulate --predict-only` (fully deterministic, **date-independent** — no
  `now()`), then asserts at least one entry exercises a *straddling best-thirds-cut tie*
  (anti-vacuous guard added by `ce72e3f`, 2026-06-09; see `docs/investigations/2026-06-09-simulate-tournament-flake.md`).
- `acefb42` (2026-06-12, "renumber WC2026 group fixtures to FIFA chronological order") changed each
  group fixture's `match_number`. The simulator derives predictions via
  `DeterministicScores::goals($seed, $fixture->match_number, …)` (crc32 of the match number), so the
  renumber **reshuffled the entire default world** — and it no longer happens to produce a straddling
  thirds cut for any entry, so the guard fails.

### Proof

Reverting **only** the seeder to `acefb42^` (pre-renumber) and re-running the test → **passes**;
with the current renumbered seeder → **fails**. So `acefb42` is the cause; it is unrelated to the
date bucket and unrelated to the live-score-gate change on this branch.

---

## Bucket 3 — Test-DB contamination (NOT real failures)

The first full-suite run overlapped with another `php artisan test` the developer was running against
the **same Postgres `testing` database**. `RefreshDatabase` rebuilds the schema per suite, so the two
processes raced — one dropped/recreated tables while the other queried them, yielding:

- `SQLSTATE[42P01]: Undefined table … relation "tournaments"/"users" does not exist`
- `SQLSTATE[42703]: Undefined column "locale"/"onboarded_at"`

~19 such errors across `CreateUserTest`, `AdvanceLiveFeedCommandTest`, `LiveProjectionTest`,
`MatchdayLeaderboardTest`, `RankSnapshotterTest` (and inflated other files). On a clean re-run with
nothing else touching the DB, **zero** schema errors remained and those files passed.

**Takeaway:** never run two suites against the shared `testing` DB at once. Use a separate DB per
runner (e.g. `DB_DATABASE=testing2`) or run sequentially.

---

## Counts (authoritative — clean full run, nothing else touching the DB)

- Full suite: **664 tests · 623 passed · 38 failed · 0 errored** (671s).
- **Zero** `Undefined table/column` errors → contamination fully absent in this run.
- The 38 real failures break down exactly as:
  - **37 time-overtaken** (bucket 1), across 7 files: `PredictionControllerTest` (13),
    `Unit\Services\Pools\PredictionAttentionTest` (7), `JoinPoolTest` (7), `JoinedPoolsSidebarTest`
    (5), `PredictionOrderingTest` (4), `PoolControllerTest` (2), `Console\PreRegisterUserTest` (2).
  - **1 `acefb42` regression** (bucket 2): `SimulateTournamentTest::test_by_default_every_upfront_entry_has_a_fully_resolved_bracket`.
- The earlier **contaminated** run was `604 passed / 38 failed`, of which ~19 were schema-race
  artifacts. The 5 files that only "failed" there — `CreateUserTest`, `AdvanceLiveFeedCommandTest`,
  `LiveProjectionTest`, `MatchdayLeaderboardTest`, `RankSnapshotterTest` — **pass clean**.
  `SimulateTournamentTieTest` also passes clean.

---

## Fixes applied (2026-06-13)

1. **Bucket 1 — DONE.** `tests/TestCase.php::setUp()` now pins `Carbon::setTestNow('2026-06-01 12:00:00')`
   (reset in `tearDown`), a fixed instant before the tournament. The derived group window is open
   again; the 37 tests pass. Tests that need a different moment still freeze their own time after
   `parent::setUp()`, and the explicit `predictions_lock_at`-relative-to-`now()` overrides (the only
   way any test asserts a *closed* window) keep working. Verified: full suite **663/664**.
2. **Bucket 2 — DONE (and revealing).** `SimulateTournamentTest::test_by_default…` now passes
   `--seed => '2'`, a world that exercises a straddling best-thirds cut under the new FIFA numbering
   (found by sweep: seeds 2,3,4,5,10 work; the seed-free world does not). Stable 5/5 in isolation.
3. **Bucket 3 — infra only.** Don't run two suites against the shared `testing` DB at once.

## Newly surfaced: a pre-existing resolver flake (NOT introduced here)

After the bucket-2 fix, the full suite intermittently fails `test_by_default…` with
`Entry N should have a fully-resolved bracket … Failed asserting that true is false` (i.e.
`TieResolutionState::blocked()` returns true). This is the **same flake** documented in
`2026-06-09-simulate-tournament-flake.md` ("Entry 124" then, "Entry 131" now): **full-suite only,
passes in isolation** (5/5 here). `acefb42`'s renumber had been masking it by making the default
world tie-free — a *vacuous* pass, exactly what this test's guard exists to prevent. Restoring the
hard case re-exposes it. Root cause is unconfirmed (the prior investigation deferred it: resolver
reads as order-independent, suspected a now-fixed Postgres transaction-poison). It is a separate,
pre-existing issue — see the resume recipe in the 2026-06-09 doc.
