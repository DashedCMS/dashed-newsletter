{{--
    De omhulling voor de afmeldschermen. Bewust een eigen, zelfstandige pagina
    met inline stijlen en geen thema van de site: deze pagina's worden geopend
    vanuit een mailprogramma, vaak door iemand die geirriteerd is, en dan is een
    pagina die snel laadt en op elk scherm klopt meer waard dan een pagina die
    bij de huisstijl past. Ze moeten het ook doen op een installatie waar de
    front-end helemaal niet draait.
--}}
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f4f4f5;
            color: #18181b;
            line-height: 1.55;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }
        .kaart {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 1px 2px rgba(0,0,0,.06), 0 8px 24px rgba(0,0,0,.06);
            max-width: 520px;
            width: 100%;
            padding: 36px 32px;
        }
        h1 { font-size: 22px; margin: 0 0 12px; letter-spacing: -.01em; }
        p { margin: 0 0 16px; }
        .zacht { color: #71717a; font-size: 14px; }
        .adres { font-weight: 600; word-break: break-all; }
        label { display: block; font-size: 14px; margin: 0 0 6px; }
        select, textarea {
            width: 100%;
            font: inherit;
            font-size: 15px;
            padding: 10px 12px;
            border: 1px solid #d4d4d8;
            border-radius: 8px;
            background: #fff;
            color: inherit;
        }
        textarea { min-height: 88px; resize: vertical; }
        .veld { margin: 0 0 18px; }
        button {
            font: inherit;
            font-size: 15px;
            font-weight: 600;
            padding: 12px 20px;
            border-radius: 8px;
            border: 0;
            cursor: pointer;
            width: 100%;
        }
        .primair { background: #18181b; color: #fff; }
        .secundair { background: #fff; color: #18181b; border: 1px solid #d4d4d8; }
        .vinkje {
            width: 44px; height: 44px; border-radius: 50%;
            background: #dcfce7; color: #15803d;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; margin: 0 0 16px;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #09090b; color: #fafafa; }
            .kaart { background: #18181b; box-shadow: none; border: 1px solid #27272a; }
            .zacht { color: #a1a1aa; }
            select, textarea { background: #09090b; border-color: #3f3f46; }
            .primair { background: #fafafa; color: #18181b; }
            .secundair { background: transparent; color: #fafafa; border-color: #3f3f46; }
            .vinkje { background: #052e16; color: #4ade80; }
        }
    </style>
</head>
<body>
    <main class="kaart">
