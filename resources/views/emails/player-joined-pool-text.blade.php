{{ __('Brothers Bets — New entry') }}

{!! __(':name joined :pool.', ['name' => $playerName, 'pool' => $poolName]) !!}

{{ __('A new player is in. Reach out to arrange their buy-in payment.') }}

{{ __('Player') }}: {{ $playerName }}
{{ __('Email') }}: {{ $playerEmail }}
@if (! empty($playerPhone))
{{ __('Phone') }}: {{ $playerPhone }}
@endif
{{ __('Buy-in') }}: @if ((float) $entryPrice > 0){{ $currency }} {{ number_format((float) $entryPrice, 2) }}@else {{ __('No buy-in (free pool)') }}@endif


{{ __('View the pool') }}: {{ $url }}

— Brothers Bets
