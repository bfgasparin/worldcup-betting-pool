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
        return $this->user() !== null
            && $this->game()->acceptsPredictions()
            && $this->game()->isJoinedBy($this->user());
    }

    /**
     * The current user's entry for this game. The user must have joined first; authorize() runs
     * before this, so on the save path the entry always exists.
     */
    public function entry(): Entry
    {
        return $this->entry ??= Entry::query()
            ->where('game_id', $this->game()->id)
            ->where('user_id', $this->user()->id)
            ->firstOrFail();
    }
}
