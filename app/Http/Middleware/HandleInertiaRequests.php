<?php

namespace App\Http\Middleware;

use App\Services\Live\HasLiveMatches;
use App\Services\Pools\JoinedPools;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'isAdmin' => (bool) $request->user()?->isAdmin(),
            ],
            'timezone' => $request->cookie('timezone'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Resolved from the container so the per-request {@see PredictionAttention} is shared
            // with the pool page and a player's bracket is never resolved twice in one request.
            'joinedPools' => app(JoinedPools::class)->forUser($request->user()),
            // Drives the navigation's animated "Live" indicator: true when the user has a live match
            // to follow in a tournament they've joined.
            'hasLiveMatches' => app(HasLiveMatches::class)->forUser($request->user()),
        ];
    }
}
