<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\BatchStatus;
use App\Enums\ProposalStatus;
use App\Models\Game;
use App\Models\ScoreBatch;
use App\Services\Predictions\TieResolutionState;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ApproveScoreBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-tournament') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Guard the batch is complete: every non-rejected proposal must carry a final score, and
     * knockout matches must name the advancing team (the bracket cascade reads it, so a missing
     * winner would stall every downstream round).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $batch = $this->openBatch();

            if ($batch === null) {
                $validator->errors()->add('batch', __('There is no open batch to approve.'));

                return;
            }

            $proposals = $batch->proposals()
                ->where('status', '!=', ProposalStatus::Rejected)
                ->with('fixture.phase')
                ->get();

            if ($proposals->isEmpty()) {
                $validator->errors()->add('batch', __('Add at least one score before approving.'));

                return;
            }

            foreach ($proposals as $proposal) {
                if ($proposal->home_goals === null || $proposal->away_goals === null) {
                    $validator->errors()->add('proposals', __('Every match in the batch needs a final score.'));

                    return;
                }

                if ($proposal->fixture->isKnockout() && $proposal->winner_team_id === null) {
                    $validator->errors()->add('proposals', __('Knockout matches need the advancing team selected.'));

                    return;
                }
            }

            // Block approval while the projected results leave a completed group's standings — or
            // the best-thirds cut — tied, since the bracket cannot be projected (and phased windows
            // cannot open) until an admin orders those teams by hand.
            /** @var Game $game */
            $game = $this->route('game');

            if ((new TieResolutionState)->forTournament($game->tournament, $batch)->blocked()) {
                $validator->errors()->add('ties', __('Resolve the tied teams in the group standings before approving.'));
            }
        });
    }

    /**
     * The current open batch for the game's tournament, if any.
     */
    public function openBatch(): ?ScoreBatch
    {
        /** @var Game $game */
        $game = $this->route('game');

        return $game->tournament->scoreBatches()
            ->where('status', BatchStatus::Open)
            ->latest('id')
            ->first();
    }
}
