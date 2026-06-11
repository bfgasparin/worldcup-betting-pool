<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\BatchStatus;
use App\Enums\OrderingScope;
use App\Http\Requests\Concerns\ValidatesTieOrdering;
use App\Models\ScoreBatch;
use App\Models\Tournament;
use App\Services\Predictions\TieResolutionState;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Saves an admin's manual ordering of a tie in the official group results. Validated against the
 * results projected with the open batch's proposals — the same post-approval state the approval
 * gate checks — so resolving the tie here is what unblocks approval.
 */
class UpdateGroupOrderingRequest extends FormRequest
{
    use ValidatesTieOrdering;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-tournament') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->tieOrderingRules($this->tournament()->groups()->pluck('name')->all());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $state = (new TieResolutionState)->forTournament($this->tournament(), $this->openBatch());
            $this->validateOrderingAgainst($validator, $state);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // The tied teams stay statistically level forever, so without this gate the order
            // could be re-submitted after approval — silently re-drawing bracket match-ups that
            // phased-pool players have already predicted against. Publication is a one-way door.
            if ($this->orderingIsPublished()) {
                $validator->errors()->add('ordered_team_ids', __('These results are already official — the published order can no longer be changed.'));
            }
        });
    }

    /**
     * Whether the results this ordering feeds are already official — every fixture of the named
     * group (within-group scope), or every group fixture of the tournament (thirds scope, which
     * needs the full group stage), carries an official score. At that point the ordering has been
     * consumed by the bracket projection and changing it would flip published participants.
     */
    private function orderingIsPublished(): bool
    {
        $fixtures = $this->tournament()->groupFixtures();

        if (OrderingScope::tryFrom((string) $this->input('scope')) === OrderingScope::WithinGroup) {
            $fixtures->whereRelation('group', 'name', (string) $this->input('group'));
        }

        return ! $fixtures->whereNull('home_goals')->exists();
    }

    public function tournament(): Tournament
    {
        return $this->route('tournament');
    }

    public function openBatch(): ?ScoreBatch
    {
        return $this->tournament()->scoreBatches()
            ->where('status', BatchStatus::Open)
            ->latest('id')
            ->first();
    }
}
