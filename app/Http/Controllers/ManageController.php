<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The system-admin hub. Admins manage tournaments here independently of any pool — from each
 * tournament they reach Live Control, score review/approval, and the fixture schedule. Gated by
 * the `manage-tournament` ability on the route group.
 */
class ManageController extends Controller
{
    public function index(): Response
    {
        $tournaments = Tournament::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tournament $tournament): array => [
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'status' => $tournament->status->value,
                'status_label' => $tournament->status->label(),
            ])
            ->all();

        return Inertia::render('manage/index', [
            'tournaments' => $tournaments,
        ]);
    }
}
