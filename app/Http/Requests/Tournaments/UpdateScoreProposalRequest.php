<?php

namespace App\Http\Requests\Tournaments;

use App\Models\Fixture;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScoreProposalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-tournament') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'home_goals' => ['nullable', 'integer', 'min:0', 'max:99'],
            'away_goals' => ['nullable', 'integer', 'min:0', 'max:99'],
            'winner_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'home_penalties' => ['nullable', 'integer', 'min:0', 'max:99'],
            'away_penalties' => ['nullable', 'integer', 'min:0', 'max:99'],
            'rejected' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Two gates, mirroring the prediction flow's rule for who advances:
     *  - a score can only be proposed for a match that has already ended (the same gate the
     *    scheduled fetch honours, so neither path records a result for a match still in play);
     *  - a knockout that ended level needs an explicit advancing team (penalties), and that pick
     *    must be one of the two teams. A decisive result derives the winner from the score, so it
     *    never needs a pick — the only case validated here is a draw.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $fixture = $this->fixture();

            if ($fixture === null) {
                return;
            }

            if (! $fixture->hasEnded()) {
                $validator->errors()->add('home_goals', __('This match has not ended yet.'));

                return;
            }

            $home = $this->goal($this->input('home_goals'));
            $away = $this->goal($this->input('away_goals'));

            // Only a knockout that ended level with both teams known needs a manual pick;
            // everything else derives the advancing team from the score (or has none).
            if (! $fixture->isKnockout() || $home === null || $away === null || $home !== $away) {
                return;
            }

            if ($fixture->home_team_id === null || $fixture->away_team_id === null) {
                return;
            }

            $pick = $this->input('winner_team_id');

            if ($pick === null) {
                $validator->errors()->add('winner_team_id', __('Pick which team advances after penalties.'));

                return;
            }

            if ((int) $pick !== $fixture->home_team_id && (int) $pick !== $fixture->away_team_id) {
                $validator->errors()->add('winner_team_id', __('The selected team is not in this match.'));
            }
        });
    }

    /**
     * The advancing team for this proposal, derived authoritatively from the score so a decisive
     * result always names the higher-scoring side regardless of what the client sent. A draw uses
     * the validated pick; group fixtures, incomplete scores and unresolved knockouts have none.
     */
    public function winnerTeamIdFor(): ?int
    {
        $fixture = $this->fixture();

        if ($fixture === null || ! $fixture->isKnockout()) {
            return null;
        }

        $home = $this->goal($this->input('home_goals'));
        $away = $this->goal($this->input('away_goals'));

        if ($home === null || $away === null) {
            return null;
        }

        if ($home > $away) {
            return $fixture->home_team_id;
        }

        if ($away > $home) {
            return $fixture->away_team_id;
        }

        // Draw: keep the validated pick, but only once both participants are known.
        if ($fixture->home_team_id === null || $fixture->away_team_id === null) {
            return null;
        }

        $pick = $this->input('winner_team_id');

        return $pick === null ? null : (int) $pick;
    }

    private function fixture(): ?Fixture
    {
        $fixture = $this->route('fixture');

        return $fixture instanceof Fixture ? $fixture : null;
    }

    private function goal(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
