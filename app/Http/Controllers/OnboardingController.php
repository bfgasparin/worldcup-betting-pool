<?php

namespace App\Http\Controllers;

use App\Http\Requests\AvatarUploadRequest;
use App\Http\Requests\Onboarding\OnboardingNameRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Show the first-login onboarding wizard.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('onboarding/wizard', [
            'hasPasskeys' => $request->user()->passkeys()->exists(),
        ]);
    }

    /**
     * Confirm or correct the user's full name.
     */
    public function updateName(OnboardingNameRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Name saved.')]);

        return back();
    }

    /**
     * Store the user's uploaded avatar, replacing any previous one.
     */
    public function updateAvatar(AvatarUploadRequest $request): RedirectResponse
    {
        $request->user()->storeAvatar($request->file('avatar'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo saved.')]);

        return back();
    }

    /**
     * Finish the wizard. Also handles "skip everything" — any step left untouched simply
     * never called its own endpoint. This is the only writer of `onboarded_at`.
     */
    public function complete(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['onboarded_at' => now()])->save();

        return to_route('pools.index');
    }
}
