<?php

namespace App\Http\Requests\Tournaments;

use Illuminate\Contracts\Validation\ValidationRule;
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
}
