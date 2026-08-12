<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Afgemeld</title>
</head>
<body style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:80px auto;padding:0 24px;color:#18181b;">
    <h1 style="font-size:20px;">Je bent afgemeld</h1>
    <p>
        {{ $email }} krijgt geen berichten meer van
        {{ $listName ?: 'deze nieuwsbrief' }}.
    </p>
    <p style="color:#71717a;font-size:14px;">
        Was dit een vergissing? Meld je dan opnieuw aan via onze website.
    </p>
</body>
</html>
