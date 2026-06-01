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
     * A score can only be proposed for a match that has already ended — the same gate the
     * scheduled fetch honours, so neither path can record a result for a match still in play.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $fixture = $this->route('fixture');

            if ($fixture instanceof Fixture && ! $fixture->hasEnded()) {
                $validator->errors()->add('home_goals', __('This match has not ended yet.'));
            }
        });
    }
}
