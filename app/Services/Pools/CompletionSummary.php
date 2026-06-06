<?php

namespace App\Services\Pools;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Whether a player has finished every prediction in a pool's currently-open windows — the inverse of
 * {@see AttentionSummary}. Drives the predict page's celebration modal (shown the moment the last
 * pick lands) and its calm "all set, waiting for official scores" banner. Produced by
 * {@see PredictionAttention::completion()} and shared with the frontend verbatim.
 *
 * @implements Arrayable<string, mixed>
 */
class CompletionSummary implements Arrayable
{
    /**
     * @param  list<array{phase_key: string, label: string, deadline: ?string}>  $openWindows
     */
    public function __construct(
        public readonly bool $isComplete,
        public readonly array $openWindows = [],
    ) {}

    /**
     * @return array{is_complete: bool, open_windows: list<array{phase_key: string, label: string, deadline: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'is_complete' => $this->isComplete,
            'open_windows' => $this->openWindows,
        ];
    }
}
