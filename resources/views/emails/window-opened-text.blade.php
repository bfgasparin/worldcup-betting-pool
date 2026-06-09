{!! __(':pool — new predictions are open', ['pool' => $poolName]) !!}

{{ __(':round is open', ['round' => $roundName]) }}

{{ __('The :round match-ups are set. Head in and make your picks for every tie in this round.', ['round' => $roundName]) }}
@if ($deadlineLabel)

{{ __('Predictions close') }}: {{ $deadlineLabel }} {{ $deadlineZone }}
@endif

{{ __('Make your picks') }}: {{ $url }}

{{ __('Miss the deadline and the round locks with no pick — don\'t leave points on the table.') }}

— Brothers Bets
