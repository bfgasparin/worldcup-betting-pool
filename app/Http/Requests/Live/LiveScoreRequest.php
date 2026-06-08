<?php

namespace App\Http\Requests\Live;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an admin's live scoreline edit. The route is gated by the `manage-tournament` ability,
 * so authorization is handled upstream. Goals are nullable — an admin may clear the board back to
 * "not entered yet".
 */
class LiveScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'home_goals' => ['nullable', 'integer', 'min:0', 'max:50'],
            'away_goals' => ['nullable', 'integer', 'min:0', 'max:50'],
        ];
    }

    public function homeGoals(): ?int
    {
        return $this->goalsFor('home_goals');
    }

    public function awayGoals(): ?int
    {
        return $this->goalsFor('away_goals');
    }

    private function goalsFor(string $key): ?int
    {
        $value = $this->input($key);

        return ($value === null || $value === '') ? null : (int) $value;
    }
}
