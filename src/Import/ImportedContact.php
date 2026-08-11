<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Import;

use Carbon\CarbonInterface;

/**
 * Een contact dat ergens anders vandaan komt en dus niet net op een formulier
 * op verzenden heeft gedrukt. Bewust los van de bron: hier staat niets in dat
 * naar Laposta verwijst, zodat een latere overname uit csv of bij een andere
 * aanbieder dezelfde ingang gebruikt.
 */
final readonly class ImportedContact
{
    /**
     * @param array<string, mixed> $fields veldsleutel => waarde
     * @param string|null $source de grove bron, bijvoorbeeld 'laposta'
     * @param string|null $origin de precieze herkomst, bijvoorbeeld de
     *        aanmeldpagina. Alleen voor het bewijs en de tijdlijn, nooit als
     *        bron op het contact: met tientallen verschillende waarden wordt
     *        segmenteren op bron zinloos.
     */
    public function __construct(
        public string $email,
        public string $status,
        public array $fields = [],
        public ?CarbonInterface $subscribedAt = null,
        public ?CarbonInterface $confirmedAt = null,
        public ?string $ip = null,
        public ?string $source = null,
        public ?string $origin = null,
        public ?string $consentText = null,
    ) {}
}
