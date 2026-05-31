<?php

namespace App\Http\Requests\Predictions;

use App\Enums\EntryStatus;
use App\Models\Entry;
use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared base for the prediction save requests: authorises against the tournament lock
 * and resolves the authenticated user's (draft) entry for this tournament.
 */
abstract class PredictionRequest extends FormRequest
{
    private ?Entry $entry = null;

    public function tournament(): Tournament
    {
        return $this->route('tournament');
    }

    public function authorize(): bool
    {
        return $this->user() !== null && $this->tournament()->acceptsPredictions();
    }

    /**
     * The current user's entry for this tournament, created on first save.
     */
    public function entry(): Entry
    {
        return $this->entry ??= Entry::firstOrCreate(
            ['tournament_id' => $this->tournament()->id, 'user_id' => $this->user()->id],
            ['status' => EntryStatus::Draft],
        );
    }
}
