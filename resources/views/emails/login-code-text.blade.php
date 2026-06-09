{{ __('Brothers Bets — Secure sign-in') }}

{{ __('Your login code is:') }}

    {{ $code }}

{{ __('Use this code to finish signing in to Brothers Bets. It expires in :minutes minutes.', ['minutes' => $expiresInMinutes]) }}

{{ __('If you didn\'t request this code, you can safely ignore this email — your account stays secure. We\'ll never ask you for this code, so keep it to yourself.') }}

{{ __('You\'re receiving this because a sign-in to Brothers Bets was requested for :email.', ['email' => $email]) }}

— Brothers Bets
