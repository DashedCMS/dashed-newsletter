<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaign->subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;">
@if($campaign->preheader)
    {{-- Voorvertoning in de mailbox, niet zichtbaar in de mail zelf. --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $campaign->preheader }}</div>
@endif

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;padding:32px;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
                <tr>
                    <td>{!! $campaign->content !!}</td>
                </tr>
                <tr>
                    <td style="padding-top:32px;border-top:1px solid #e4e4e7;font-size:12px;color:#71717a;">
                        Je krijgt deze mail omdat je staat ingeschreven voor {{ $campaign->list?->name }}.
                        <a href="{{ $unsubscribeUrl }}" style="color:#71717a;">Hier kun je je afmelden</a>.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
