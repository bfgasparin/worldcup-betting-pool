<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\LanguageUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LanguageController extends Controller
{
    /**
     * Show the language settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/language', [
            'supportedLocales' => config('app.supported_locales'),
            'current' => $request->user()->locale,
        ]);
    }

    /**
     * Update the user's preferred app language. A null value means "use the device language".
     */
    public function update(LanguageUpdateRequest $request): RedirectResponse
    {
        $request->user()->update(['locale' => $request->validated('locale')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Language updated.')]);

        return to_route('language.edit');
    }
}
