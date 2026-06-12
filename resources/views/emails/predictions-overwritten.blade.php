@extends('emails.layout')

@section('title', __('An organizer updated your predictions'))

@section('preheader', __('An organizer entered new predictions for you in :pool — open it to review what\'s now saved on your behalf.', ['pool' => $poolName]))

@section('headerTag', __('Heads up'))

@section('accentBarSolid', $accentSolid)
@section('accentBarGradient', $accentGradient)

@section('content')
    {{-- Hero --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad" align="center" style="padding:36px 32px 4px;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:{{ $accentInk }};">{{ $poolName }} · {{ __('by :source', ['source' => $source]) }}</p>
                <h1 class="ffa-h1" style="margin:12px 0 0;font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:30px;font-weight:600;line-height:1.1;letter-spacing:-0.02em;color:#0D2E23;">{{ __('Your predictions were updated') }}</h1>
            </td>
        </tr>
    </table>

    {{-- Body copy --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad-narrow" align="center" style="padding:14px 48px 0;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#5E6B64;">{{ __('An organizer entered predictions for you in this pool, replacing the ones you had saved. Open your predictions to check that everything looks right.') }}</p>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:24px 32px 6px;text-align:center;">
                @include('emails.partials.button', ['url' => $url, 'label' => __('Review my predictions →'), 'variant' => 'pitch'])
            </td>
        </tr>
        <tr>
            <td class="ffa-pad-narrow" align="center" style="padding:14px 48px 34px;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12.5px;line-height:1.55;color:#7A847E;">{{ __('If this looks wrong, reply to this email or reach out to the pool organizer.') }}</p>
            </td>
        </tr>
    </table>
@endsection

@section('footerNote')
    {!! __('You\'re getting this because you\'re playing :pool on Brothers Bets. We only email when an organizer changes the predictions saved on your behalf.', ['pool' => '<b style="color:#5E6B64;font-weight:700;">' . e($poolName) . '</b>']) !!}
@endsection
