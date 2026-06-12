@extends('emails.layout')

@section('title', __('New player joined'))

@section('preheader', __(':name just joined :pool — arrange their buy-in.', ['name' => $playerName, 'pool' => $poolName]))

@section('headerTag', __('New entry'))

@section('accentBarSolid', $accentSolid)
@section('accentBarGradient', $accentGradient)

@section('content')
    {{-- Hero --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad" align="center" style="padding:36px 32px 4px;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:{{ $accentInk }};">{{ $poolName }} · {{ __('by :source', ['source' => $source]) }}</p>
                <h1 class="ffa-h1" style="margin:12px 0 0;font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:30px;font-weight:600;line-height:1.1;letter-spacing:-0.02em;color:#0D2E23;">{{ __(':name joined :pool', ['name' => $playerName, 'pool' => $poolName]) }}</h1>
            </td>
        </tr>
    </table>

    {{-- Body copy --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="ffa-pad-narrow" align="center" style="padding:14px 48px 0;text-align:center;">
                <p style="margin:0;font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#5E6B64;">{{ __('A new player is in. Reach out to arrange their buy-in payment.') }}</p>
            </td>
        </tr>
    </table>

    {{-- Details card --}}
    <table role="presentation" align="center" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px auto 6px;">
        <tr>
            <td class="ffa-pad" style="padding:0 32px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F6F9F5;border:1px solid #E2EBE2;border-radius:16px;">
                    @php
                        $rows = [
                            [__('Player'), e($playerName)],
                            [__('Email'), '<a class="ffa-anchor" href="mailto:' . e($playerEmail) . '">' . e($playerEmail) . '</a>'],
                        ];
                        $rows[] = [__('Buy-in'), (float) $entryPrice > 0
                            ? e($currency) . ' ' . number_format((float) $entryPrice, 2)
                            : __('No buy-in (free pool)')];
                    @endphp
                    @foreach ($rows as $i => [$label, $value])
                        <tr>
                            <td style="padding:14px 20px;border-top:{{ $i === 0 ? 'none' : '1px solid #E2EBE2' }};font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#7A847E;width:96px;vertical-align:top;">{{ $label }}</td>
                            <td style="padding:14px 20px;border-top:{{ $i === 0 ? 'none' : '1px solid #E2EBE2' }};font-family:'Plus Jakarta Sans',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;font-weight:600;color:#0D2E23;text-align:right;">{!! $value !!}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    {{-- CTA --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding:24px 32px 34px;text-align:center;">
                @include('emails.partials.button', ['url' => $url, 'label' => __('View the pool →'), 'variant' => 'pitch'])
            </td>
        </tr>
    </table>
@endsection

@section('footerNote')
    {!! __('You\'re getting this because you\'re an organizer of :pool on Brothers Bets. We only email you about pools you run.', ['pool' => '<b style="color:#5E6B64;font-weight:700;">' . e($poolName) . '</b>']) !!}
@endsection
