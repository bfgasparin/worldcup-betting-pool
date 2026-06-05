<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorises a player joining a game's pool. Joining is the act of entering, so it follows the
 * same window as making predictions: a player can join while predictions are still open, never
 * after the group-stage lock.
 */
class JoinGameRequest extends FormRequest
{
    public function game(): Game
    {
        return $this->route('game');
    }

    public function authorize(): bool
    {
        return $this->user() !== null && $this->game()->acceptsPredictions();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
