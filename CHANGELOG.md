# Changelog

## v4.26.0 - 2026-11-17

### Changed
- Campagnecijfers: afgemelde, geblokkeerde en ongeldige adressen tellen niet meer mee als ontvanger. Ze staan als "x afgemeld of geblokkeerd, niet meegeteld" onder Ontvangers; onder Verzonden staan alleen nog de door afbreken overgeslagen ontvangers ("afgebroken"). `totals()` geeft daarvoor een nieuwe sleutel `excluded`.

## v4.25.1 - 2026-11-16

### Fixed
- Een afgebroken verzending staat in het taakcentrum meteen als mislukt ("Verzenden afgebroken: x van y ontvangers verwerkt.") in plaats van twee uur op bezig.
