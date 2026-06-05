<?php

namespace App\Http\Requests\Predictions;

use App\Services\Predictions\BracketResolver;
use App\Services\Predictions\PredictionWindowResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateKnockoutPredictionsRequest extends PredictionRequest
{
    /**
     * Cache of the resolved home/away team ids per fixture, keyed by fixture id. For an upfront
     * bracket these come from the engine (the player's self-derived bracket); for a phased bracket
     * they are the official participants already on the fixtures.
     *
     * @var array<int, array{home: int|null, away: int|null}>|null
     */
    private ?array $resolvedSlots = null;

    /**
     * Phased-bracket games lock per knockout round, so a save is only authorised when every
     * submitted fixture's round is currently open. Upfront games keep the single game-level lock.
     */
    public function authorize(): bool
    {
        $game = $this->game();

        if (! $game->usesPhasedPredictionWindows()) {
            return parent::authorize();
        }

        if ($this->user() === null || ! $game->isJoinedBy($this->user())) {
            return false;
        }

        $resolver = app(PredictionWindowResolver::class);
        $knockoutFixtures = $game->tournament->knockoutFixtures()->with('phase')->get()->keyBy('id');

        foreach ((array) $this->input('predictions', []) as $prediction) {
            $fixture = $knockoutFixtures->get($prediction['fixture_id'] ?? null);

            // Unknown fixtures are left to validation; known ones must be in an open round.
            if ($fixture !== null && ! $resolver->isOpen($game, $fixture->phase->key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $knockoutFixtureIds = $this->game()->tournament->knockoutFixtures()->pluck('id')->all();

        return [
            'predictions' => ['required', 'array'],
            'predictions.*.fixture_id' => ['required', 'integer', Rule::in($knockoutFixtureIds)],
            'predictions.*.home_goals' => ['nullable', 'integer', 'min:0', 'max:99'],
            'predictions.*.away_goals' => ['nullable', 'integer', 'min:0', 'max:99'],
            'predictions.*.advancing_team_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Who advances is derived from the score: a decisive result picks the higher-scoring team
     * automatically, so the only case that needs an explicit pick is a draw (penalties). On a
     * draw the picked team must be one of the two teams the engine resolved for that fixture.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $resolved = $this->resolvedSlots();

            foreach ((array) $this->input('predictions', []) as $index => $prediction) {
                $home = $this->goal($prediction['home_goals'] ?? null);
                $away = $this->goal($prediction['away_goals'] ?? null);

                if ($home === null || $away === null || $home !== $away) {
                    continue;
                }

                $slot = $resolved[$prediction['fixture_id'] ?? null] ?? ['home' => null, 'away' => null];
                $advancing = $prediction['advancing_team_id'] ?? null;

                if ($advancing === null) {
                    $validator->errors()->add("predictions.{$index}.advancing_team_id", 'Pick which team advances after penalties.');

                    continue;
                }

                if ((int) $advancing !== $slot['home'] && (int) $advancing !== $slot['away']) {
                    $validator->errors()->add("predictions.{$index}.advancing_team_id", 'The selected team is not in this match.');
                }
            }
        });
    }

    /**
     * Normalise the validated predictions for persistence, deriving the advancing team from the
     * score so the stored value is always authoritative regardless of what the client sent.
     *
     * @return list<array{fixture_id: int, home_goals: int|null, away_goals: int|null, advancing_team_id: int|null}>
     */
    public function predictionsForPersistence(): array
    {
        $resolved = $this->resolvedSlots();

        return array_map(function (array $prediction) use ($resolved): array {
            $home = $this->goal($prediction['home_goals'] ?? null);
            $away = $this->goal($prediction['away_goals'] ?? null);
            $slot = $resolved[$prediction['fixture_id']] ?? ['home' => null, 'away' => null];

            return [
                'fixture_id' => (int) $prediction['fixture_id'],
                'home_goals' => $home,
                'away_goals' => $away,
                'advancing_team_id' => $this->advancingFor($home, $away, $slot, $prediction['advancing_team_id'] ?? null),
            ];
        }, $this->validated('predictions'));
    }

    /**
     * The team that advances given a score: the higher-scoring side for a decisive result, the
     * client's pick for a draw (already validated as a participant), null when incomplete.
     *
     * @param  array{home: int|null, away: int|null}  $slot
     */
    private function advancingFor(?int $home, ?int $away, array $slot, mixed $clientPick): ?int
    {
        if ($home === null || $away === null) {
            return null;
        }

        if ($home > $away) {
            return $slot['home'];
        }

        if ($away > $home) {
            return $slot['away'];
        }

        return $clientPick === null ? null : (int) $clientPick;
    }

    private function goal(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * @return array<int, array{home: int|null, away: int|null}>
     */
    private function resolvedSlots(): array
    {
        if ($this->resolvedSlots !== null) {
            return $this->resolvedSlots;
        }

        // Phased brackets are predicted against the official participants already on the fixtures;
        // upfront brackets resolve from the player's own group scores via the engine.
        if ($this->game()->usesPhasedPredictionWindows()) {
            return $this->resolvedSlots = $this->game()->tournament->knockoutFixtures()->get()
                ->mapWithKeys(fn ($fixture): array => [
                    $fixture->id => ['home' => $fixture->home_team_id, 'away' => $fixture->away_team_id],
                ])
                ->all();
        }

        return $this->resolvedSlots = app(BracketResolver::class)->resolve($this->entry())->resolved;
    }
}
