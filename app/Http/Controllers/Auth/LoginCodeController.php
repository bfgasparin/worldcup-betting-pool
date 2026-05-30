<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendLoginCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendLoginCodeRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class LoginCodeController extends Controller
{
    /**
     * Send a login code to the given email if it belongs to a registered user.
     *
     * Always returns the same response regardless of whether the email matches
     * a registered user, to avoid disclosing which emails are registered.
     */
    public function store(SendLoginCodeRequest $request, SendLoginCode $sendLoginCode): RedirectResponse
    {
        $email = $request->validated('email');

        $user = User::where('email', $email)->first();

        if ($user !== null) {
            $sendLoginCode($user);
        }

        return back()->with('status', __('If that email is registered, a login code has been sent.'));
    }
}
