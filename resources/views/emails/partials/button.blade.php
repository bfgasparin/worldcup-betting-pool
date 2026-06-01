{{-- Bulletproof pill CTA. Params: $url, $label, $variant ('pitch'|'gold'). --}}
@php
    $variant = $variant ?? 'pitch';
    $bg = $variant === 'gold' ? '#FF9F1C' : '#0A6B49';
    $bgImage = $variant === 'gold'
        ? 'linear-gradient(135deg,#FFD15C 0%,#FF9F1C 100%)'
        : 'linear-gradient(135deg,#16C07A 0%,#0A6B49 100%)';
    $color = $variant === 'gold' ? '#3a2600' : '#ffffff';
@endphp
<table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
    <tr>
        <td align="center">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:48px;v-text-anchor:middle;width:260px;" arcsize="50%" stroke="f" fillcolor="{{ $bg }}">
            <w:anchorlock/>
            <center style="color:{{ $color }};font-family:Verdana,sans-serif;font-size:16px;font-weight:600;">{{ $label }}</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-- -->
            <a href="{{ $url }}" target="_blank" style="display:inline-block;background-color:{{ $bg }};background-image:{{ $bgImage }};color:{{ $color }};font-family:'Fredoka','Trebuchet MS',Verdana,sans-serif;font-size:16px;font-weight:600;line-height:1;text-decoration:none;padding:15px 34px;border-radius:999px;">{{ $label }}</a>
            <!--<![endif]-->
        </td>
    </tr>
</table>
