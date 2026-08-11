<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Import;

/**
 * De uitkomst van één importronde. Bewust een klein object en geen array:
 * de aanroeper moet kunnen zeggen wat er gebeurd is zonder de sleutels te
 * moeten raden.
 */
class ImportResult
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /** @var array<string, string> e-mailadres => reden van overslaan */
    public array $reasons = [];

    public function skip(string $email, string $reason): void
    {
        $this->skipped++;
        $this->reasons[$email] = $reason;
    }
}
