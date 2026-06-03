<?php

namespace App\Http\Requests\Predictions;

use App\Http\Requests\Concerns\ValidatesTieOrdering;
use App\Services\Predictions\TieResolutionState;
use Illuminate\Contracts\Validation\Validator;

/**
 * Saves a player's manual ordering of a tie in their own predicted standings. Only meaningful for
 * upfront-bracket games, whose knockout slots are derived from the player's group predictions.
 */
class UpdateGroupOrderingRequest extends PredictionRequest
{
    use ValidatesTieOrdering;

    public function authorize(): bool
    {
        return parent::authorize() && $this->game()->predictsKnockoutBracket();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->tieOrderingRules($this->game()->tournament->groups()->pluck('name')->all());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateOrderingAgainst($validator, (new TieResolutionState)->forEntry($this->entry()));
        });
    }
}
