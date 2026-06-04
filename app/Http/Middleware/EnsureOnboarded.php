<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    /**
     * Force authenticated, not-yet-onboarded users through the first-login wizard.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isOnboarded() || $this->isExempt($request)) {
            return $next($request);
        }

        return redirect()->guest(route('onboarding.show'));
    }

    /**
     * Routes a not-onboarded user must still reach: the wizard itself, logging out, and the
     * passkey-registration endpoints the wizard's passkey step calls.
     */
    private function isExempt(Request $request): bool
    {
        return $request->routeIs('onboarding.*', 'logout')
            || $request->is('user/passkeys', 'user/passkeys/*');
    }
}
