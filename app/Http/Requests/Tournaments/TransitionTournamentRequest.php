<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\TournamentStatus;
use App\Models\Game;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionTournamentRequest extends FormRequest
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
        /** @var Game $game */
        $game = $this->route('game');

        return [
            'status' => [
                'required',
                Rule::enum(TournamentStatus::class),
                Rule::in(array_map(
                    fn (TournamentStatus $status): string => $status->value,
                    $game->tournament->status->allowedTransitions(),
                )),
            ],
        ];
    }

    /**
     * The validated target status for the transition.
     */
    public function targetStatus(): TournamentStatus
    {
        return TournamentStatus::from($this->validated('status'));
    }
}
