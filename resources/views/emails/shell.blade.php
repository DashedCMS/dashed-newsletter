<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:{{ $backgroundColor }};">
@if($preheader)
    {{-- Voorvertoning in de mailbox, niet zichtbaar in de mail zelf. --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
@endif

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $backgroundColor }};">
    <tr><td align="center" style="padding:24px 12px;">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;max-width:600px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#18181b;border-top:4px solid {{ $primaryColor }};">
            @foreach($blocks as $block)
                {!! $block !!}
            @endforeach
            @unless($hasUnsubscribeBlock)
                {{-- Geen afmeldblok in de footer van de lijst, dus hier de
                     standaardregel. Een nieuwsbrief zonder afmeldlink mag niet
                     de deur uit, ongeacht wat een redacteur in de footer zet. --}}
                <tr><td align="center" style="padding:24px;color:#9ca3af;font-size:12px;border-top:1px solid #e5e7eb;">
                    Je krijgt deze mail omdat je staat ingeschreven voor {{ $listName }}.
                    <a href=":unsubscribe_url:" style="color:#9ca3af;text-decoration:underline;">Hier kun je je afmelden</a>.
                </td></tr>
            @endunless
        </table>
    </td></tr>
</table>
</body>
</html>
