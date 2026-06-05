Brothers Bets — New entry

{{ $playerName }} joined {!! $source !!}'s {!! $poolName !!}.

A new player is in. Reach out to arrange their buy-in payment.

Player: {{ $playerName }}
Email: {{ $playerEmail }}
@if (! empty($playerPhone))
Phone: {{ $playerPhone }}
@endif
Buy-in: @if ((float) $entryPrice > 0){{ $currency }} {{ number_format((float) $entryPrice, 2) }}@else No buy-in (free pool)@endif


View the pool: {{ $url }}

— Brothers Bets
