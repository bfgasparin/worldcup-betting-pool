<?php

namespace App\Http\Requests\Predictions;

use App\Models\Entry;
use App\Models\Pool;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared base for the prediction save requests: authorises against the pool's prediction lock
 * and resolves the authenticated user's (draft) entry for this pool.
 */
abstract class PredictionRequest extends FormRequest
{
    private ?Entry $entry = null;

    public function pool(): Pool
    {
        return $this->route('pool');
    }

    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->pool()->acceptsPredictions()
            && $this->pool()->isJoinedBy($this->user());
    }

    /**
     * The current user's entry for this pool. The user must have joined first; authorize() runs
     * before this, so on the save path the entry always exists.
     */
    public function entry(): Entry
    {
        return $this->entry ??= Entry::query()
            ->where('pool_id', $this->pool()->id)
            ->where('user_id', $this->user()->id)
            ->firstOrFail();
    }
}
