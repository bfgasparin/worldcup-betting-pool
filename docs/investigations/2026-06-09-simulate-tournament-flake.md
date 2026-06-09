# Investigation: `SimulateTournamentTest` blocked-bracket flake

**Date:** 2026-06-09 · **Status:** paused — resolver proven sound, full empirical confirmation (8× suite runs) deferred to when there is more time.

## The flake

`tests/Feature/Console/SimulateTournamentTest::test_by_default_every_upfront_entry_has_a_fully_resolved_bracket`
was reported failing ~1-in-8 **full-suite** runs (passes in isolation); the one observed failure named
"Entry 124". The test runs `tournament:simulate --players=3 --predict-only` (the no-human
`DefaultTieOrdering::applyToEntry` path) and asserts `(new TieResolutionState)->forEntry($entry)->blocked()`
is `false` for every entry. The DB in CI/local is **Postgres** (not the MySQL/SQLite the CLAUDE.md mentions).

## What was established (resolver is sound)

The default auto-resolution writes within-group orderings (seed order) and the straddling best-thirds cut,
then `BracketResolver`/`TieResolutionState` read them back. Tracing the write vs read paths:

- `DefaultTieOrdering::apply()` computes the straddling run from `straddlingThirds($resolved, $groups)`
  where `$resolved` = standings with the within-group orders applied.
- `TieResolutionState::forEntry()` recomputes `straddlingThirds()` on `BracketResolver`'s standings, which
  apply the **same persisted** within-group orders. Same standings ⇒ same run ⇒ the persisted thirds order
  always satisfies `resolveThirdsCut()` ⇒ `rankedThirds` non-null ⇒ **not blocked**.
- Every within-group tie path bottoms out at a deterministic seed-position sort
  (`GroupStandings::applyManualOrDefer`), and the cross-group thirds sort tiebreaks on group `sort_order`.
  There is **no `usort`-stability leak** that depends on relation/heap order. `ThirdPlaceAllocation::assign()`
  sorts its input (`key()`), so qualifying-group order is irrelevant.

Conclusion from reading: blocked() is a pure, order-independent function of the world, and the world the test
builds (no `--seed`) is fixed by seeder structure (`match_number`, seed `position`, `sort_order`) — all
suite-position-independent. So it should be deterministic, which is hard to square with a 1/8 flake.

## Empirical evidence gathered

All run against an isolated DB, **out of any RefreshDatabase transaction** (a single in-test transaction
exhausts Postgres `max_locks_per_transaction` after ~19 sims — see Gotchas).

| Probe | Scope | Result |
| --- | --- | --- |
| Seed sweep (`sweep:flake`) | seeds 0..500 × 4 entries on Postgres `testing` | **0 blocked** |
| Order-shuffle probe (`probe:order`) | no-seed world, 300 random shuffles of groups/teams/fixtures × 4 entries | **0 blocked** (order-independent) |
| Tie coverage (`diag:ties`) | no-seed world on SQLite | entry 1 has a real straddling thirds tie `[15,19]` behind a within-group tie and **resolves** (`blocked=no`); entries 2–4 tie-free |
| Full Postgres suite repro loop | `php artisan test` ×3 completed | flake **never reproduced** (diagnostic dump `/tmp/flake-dump.json` never written) |
| Poison-pattern audit | `grep` for swallowed `UniqueConstraintViolationException`/`QueryException`, `syncWithoutDetaching`/`attach`/`sync` in `app/` | **none remain** |

The test's invariant **is** reachable and meaningful (entry 1 exercises the hard "thirds tie behind a
within-group tie" path) and it resolves correctly. A transaction-poison (the class fixed in `a5d1b5e`,
"Make briefing-seen write atomic") would surface as a **thrown** "transaction is aborted" error (a test
*error* / failed `assertSuccessful`), **not** a clean `blocked()==true` assertion — and no such swallowed
pattern remains in `app/`. So the lone historical failure was most likely a now-fixed Postgres-poison
transient (CI had just moved to Postgres).

## Hardening applied (this branch)

`SimulateTournamentTest::test_by_default_every_upfront_entry_has_a_fully_resolved_bracket` now also asserts
the default simulation **actually exercised** a straddling best-thirds cut (`$state->thirds !== []` for at
least one entry), so the invariant can't silently go vacuous if the world drifts tie-free or the resolver
regresses. No resolver change — none is warranted by the evidence.

## How to resume (stronger empirical confirmation)

Goal: run the full suite many times on Postgres to either catch the flake (the diagnostic dump captures the
exact blocking world) or build high-confidence "cannot reproduce". 3 runs passed; aim for ≥8–16.

### 1. Re-add the diagnostic dump to the test (temporary)

In `test_by_default_every_upfront_entry_has_a_fully_resolved_bracket`, before the `assertFalse`, write a dump
when an entry blocks so a suite-run failure is captured:

```php
if ($state->blocked()) {
    file_put_contents('/tmp/flake-dump.json', json_encode([
        'entry' => $entry->id,
        'groupsResolved' => $state->groupsResolved,
        'thirds' => $state->thirds,
        'thirdsResolved' => $state->thirdsResolved,
        'groupTies' => $state->groupTies,
        'persistedThirds' => \App\Services\Predictions\ManualTieOrdering::fromEntry($entry)->thirds,
        'standings' => collect($state->standings)->map(fn ($s, $name) => [
            'group' => $name, 'third' => $s->thirdStanding()?->teamId, 'unresolved' => $s->unresolvedTies(),
            'ordered' => collect($s->ordered())->map(fn ($ts) => ['t' => $ts->teamId, 'seed' => $ts->position, 'pts' => $ts->points(), 'gd' => $ts->goalDifference(), 'gf' => $ts->goalsFor])->all(),
        ])->values()->all(),
        'predictions' => $entry->groupPredictions()->get()->map(fn ($p) => [$p->fixture_id, $p->home_goals, $p->away_goals])->all(),
    ], JSON_PRETTY_PRINT));
}
```

### 2. Run the loop (sequential Postgres, mirrors CI `tests.yml`)

```bash
rm -f /tmp/flake-dump.json
for i in $(seq 1 16); do
  echo "=== RUN $i $(date +%H:%M:%S) ==="
  php artisan test --compact 2>&1 | grep -E '"result":"failed"|fully-resolved|SimulateTournament' | tail -25
  [ -f /tmp/flake-dump.json ] && { echo "!!! REPRODUCED on run $i !!!"; break; }
done
```

Note: piped `php artisan test` emits JSON lines (`{"result":"passed"|"failed",...}`), so grep on
`"result":"failed"`, not `Tests:`/`FAIL`. The reliable signal for *this* flake is the dump file.
Each full run was ~12–20 min here.

### 3. If it reproduces

Read `/tmp/flake-dump.json` — it pins the exact blocking entry, which condition fails
(`groupsResolved` vs `thirdsResolved`), the persisted thirds order vs the live straddling run, and the full
per-group standings + raw predictions. From there the fix is in `KnockoutSlotResolver`/`DefaultTieOrdering`.

### 4. Deeper out-of-transaction probes (recreate these temp `artisan` commands)

These were deleted to keep `app/` clean. Recreate under `app/Console/Commands/` and run against a fresh
isolated DB, e.g. `DB_DATABASE=testing php artisan migrate:fresh --force && DB_DATABASE=testing php artisan
db:seed --class=WorldCup2026Seeder --force`, then the command. **Do not** sweep inside a RefreshDatabase test
(Postgres lock exhaustion).

<details><summary><code>sweep:flake</code> — seed sweep for a blocked bracket</summary>

```php
<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\Predictions\ManualTieOrdering;
use App\Services\Predictions\TieResolutionState;
use Illuminate\Console\Command;

class SweepFlake extends Command
{
    protected $signature = 'sweep:flake {--from=0} {--to=300} {--players=3} {--slug=world-cup-2026}';
    protected $description = 'Sweep seeds to find a blocked default bracket';

    public function handle(): int
    {
        $tournament = Tournament::where('slug', $this->option('slug'))->firstOrFail();
        $pool = $tournament->pools()->firstOrFail();

        for ($seed = (int) $this->option('from'); $seed <= (int) $this->option('to'); $seed++) {
            $this->callSilent('tournament:simulate', [
                '--players' => (int) $this->option('players'), '--predict-only' => true,
                '--seed' => (string) $seed, '--reset' => true,
            ]);

            foreach ($pool->entries()->get() as $entry) {
                $state = (new TieResolutionState)->forEntry($entry);
                if ($state->blocked()) {
                    $this->error("BLOCKED seed={$seed} entry={$entry->id} groupsResolved=".json_encode($state->groupsResolved).' thirdsResolved='.json_encode($state->thirdsResolved));
                    $this->line('thirds='.json_encode($state->thirds).' persistedThirds='.json_encode(ManualTieOrdering::fromEntry($entry)->thirds));
                    return self::FAILURE;
                }
            }
            if ($seed % 25 === 0) { $this->info("...seed {$seed} ok"); }
        }
        $this->info('NO BLOCKED in range');
        return self::SUCCESS;
    }
}
```
</details>

<details><summary><code>probe:order</code> — order-sensitivity probe (shuffle relations)</summary>

```php
<?php

namespace App\Console\Commands;

use App\Models\Entry;
use App\Models\Tournament;
use App\Services\Predictions\TieResolutionState;
use Illuminate\Console\Command;

class ProbeOrder extends Command
{
    protected $signature = 'probe:order {--trials=300} {--players=3} {--slug=world-cup-2026} {--seed=}';
    protected $description = 'Probe order-sensitivity of bracket resolution for the no-seed world';

    public function handle(): int
    {
        $tournament = Tournament::where('slug', $this->option('slug'))->firstOrFail();
        $pool = $tournament->pools()->firstOrFail();
        $this->callSilent('tournament:simulate', ['--players' => (int) $this->option('players'), '--predict-only' => true, '--seed' => (string) $this->option('seed'), '--reset' => true]);

        foreach ($pool->entries()->pluck('id') as $entryId) {
            $counts = ['ok' => 0, 'blocked' => 0];
            for ($trial = 0; $trial < (int) $this->option('trials'); $trial++) {
                $entry = Entry::with(['pool.tournament.groups.teams', 'pool.tournament.groups.fixtures', 'pool.tournament.knockoutFixtures.phase', 'groupPredictions', 'knockoutPredictions', 'groupOrderings.group'])->findOrFail($entryId);
                $t = $entry->pool->tournament;
                $t->setRelation('groups', $t->groups->shuffle()->values());
                foreach ($t->groups as $g) { $g->setRelation('teams', $g->teams->shuffle()->values()); $g->setRelation('fixtures', $g->fixtures->shuffle()->values()); }
                $t->setRelation('knockoutFixtures', $t->knockoutFixtures->shuffle()->values());
                $counts[(new TieResolutionState)->forEntry($entry)->blocked() ? 'blocked' : 'ok']++;
            }
            $this->line("entry {$entryId}: ".json_encode($counts));
        }
        return self::SUCCESS;
    }
}
```
</details>

<details><summary><code>diag:ties</code> — tie/auto-resolution coverage of the worlds</summary>

```php
<?php

namespace App\Console\Commands;

use App\Enums\OrderingScope;
use App\Models\Tournament;
use App\Services\Predictions\KnockoutSlotResolver;
use App\Services\Predictions\ManualTieOrdering;
use App\Services\Predictions\TieResolutionState;
use Illuminate\Console\Command;

class DiagTies extends Command
{
    protected $signature = 'diag:ties {--players=3} {--seed=} {--slug=world-cup-2026}';
    protected $description = 'Report tie/auto-resolution coverage of the simulated worlds';

    public function handle(): int
    {
        $tournament = Tournament::where('slug', $this->option('slug'))->firstOrFail();
        $pool = $tournament->pools()->firstOrFail();
        $slots = new KnockoutSlotResolver;
        $this->callSilent('tournament:simulate', ['--players' => (int) $this->option('players'), '--predict-only' => true, '--seed' => (string) $this->option('seed'), '--reset' => true]);

        foreach ($pool->entries()->with('user')->get() as $entry) {
            $state = (new TieResolutionState)->forEntry($entry);
            $groupsWithTies = collect($state->standings)->filter(fn ($s) => $s->unresolvedTies() !== [])->count();
            $straddling = $slots->straddlingThirds($state->standings, $entry->pool->tournament->groups);
            $orderings = $entry->groupOrderings()->get();
            $this->line(sprintf('entry %d (%s): blocked=%s groupsWithUnresolvedTies=%d straddlingThirds=%s writtenWithinGroup=%d writtenThirds=%d',
                $entry->id, $entry->user->email ?? '?', $state->blocked() ? 'YES' : 'no', $groupsWithTies,
                $straddling === [] ? '[]' : json_encode($straddling),
                $orderings->where('scope', OrderingScope::WithinGroup)->count(),
                $orderings->where('scope', OrderingScope::Thirds)->count()));
        }
        return self::SUCCESS;
    }
}
```
</details>

## Gotchas

- **Postgres `max_locks_per_transaction`:** never sweep many sims inside one RefreshDatabase test — the single
  wrapping transaction accumulates locks and dies (~19 sims). Sweep out-of-transaction via an artisan command
  on a pre-seeded DB; each `--reset`+regenerate autocommits and releases locks.
- **`php artisan test` piped output is JSON**, not the pretty "Tests:" summary. Grep `"result":"failed"`.
- **`--seed=` (empty) == no `--seed`** == the fixed default world.
