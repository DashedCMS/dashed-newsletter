# Changelog

## v4.29.0 - 2026-11-25

### Changed
- Nieuwe CMS-rechten `view_newsletter`, `edit_newsletter` en `delete_newsletter` op alle nieuwsbriefschermen; een migratie geeft ze aan elke bestaande rol, zodat niemand toegang verliest. In de app volgen `newsletter.read`/`newsletter.write` nu deze rechten in plaats van de rolnaam (vereist dashed-core ^4.124).

## v4.28.0 - 2026-11-19

### Added
- AVG-provider (abonnee anonimiseren, blokkadelijst blijft) en een sectie in het klantprofiel.

## v4.27.0 - 2026-11-19

### Changed
- Herkenning van bezoekers: met de cookiebanner van dashed-cookies aan zet en leest `VisitorRecognition` de cookie `dashed_newsletter_visitor` alleen met marketingtoestemming (`ConsentGate` uit dashed-core 4.115). Zonder banner ongewijzigd.

## v4.26.0 - 2026-11-17

### Changed
- Campagnecijfers: afgemelde, geblokkeerde en ongeldige adressen tellen niet meer mee als ontvanger. Ze staan als "x afgemeld of geblokkeerd, niet meegeteld" onder Ontvangers; onder Verzonden staan alleen nog de door afbreken overgeslagen ontvangers ("afgebroken"). `totals()` geeft daarvoor een nieuwe sleutel `excluded`.

## v4.25.1 - 2026-11-16

### Fixed
- Een afgebroken verzending staat in het taakcentrum meteen als mislukt ("Verzenden afgebroken: x van y ontvangers verwerkt.") in plaats van twee uur op bezig.
