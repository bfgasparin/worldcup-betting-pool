<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\BatchStatus;
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
        });
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
