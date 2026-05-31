<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tournaments\TransitionTournamentRequest;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TransitionTournamentController extends Controller
{
    /**
     * Advance (or correct) a tournament's lifecycle status.
     */
    public function __invoke(TransitionTournamentRequest $request, Tournament $tournament): RedirectResponse
    {
        $tournament->transitionTo($request->targetStatus());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tournament status updated.')]);

        return to_route('games.show', $tournament);
    }
}
