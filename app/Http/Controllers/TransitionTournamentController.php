<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tournaments\TransitionTournamentRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TransitionTournamentController extends Controller
{
    /**
     * Advance (or correct) the lifecycle status of the competition a game is played over.
     */
    public function __invoke(TransitionTournamentRequest $request, Game $game): RedirectResponse
    {
        $game->tournament->transitionTo($request->targetStatus());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tournament status updated.')]);

        return to_route('games.show', $game);
    }
}
