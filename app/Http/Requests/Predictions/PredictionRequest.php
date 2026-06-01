<?php

namespace App\Http\Requests\Predictions;

use App\Models\Entry;
use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared base for the prediction save requests: authorises against the game's prediction lock
 * and resolves the authenticated user's (draft) entry for this game.
 */
abstract class PredictionRequest extends FormRequest
{
    private ?Entry $entry = null;

    public function game(): Game
    {
        return $this->route('game');
    }

    public function authorize(): bool
    {
        return $this->user() !== null && $this->game()->acceptsPredictions();
    }

    /**
     * The current user's entry for this game, created on first save.
     */
    public function entry(): Entry
    {
        return $this->entry ??= Entry::firstOrCreate(
            ['game_id' => $this->game()->id, 'user_id' => $this->user()->id],
        );
    }
}
