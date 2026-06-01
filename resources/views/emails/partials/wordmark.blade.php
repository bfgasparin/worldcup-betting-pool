{{-- Brothers Betting Pool wordmark. Colors are passed in so it can sit on the dark header and the light footer. --}}
@php
    $gColor = $gColor ?? '#0A6B49';
    $amColor = $amColor ?? '#FF9F1C';
    $size = $size ?? '16px';
@endphp
<span style="font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-weight:600;font-size:{{ $size }};letter-spacing:-0.03em;line-height:1;white-space:nowrap;"><span style="color:{{ $gColor }};">Brothers</span> <span style="color:{{ $amColor }};">Betting Pool</span></span>
