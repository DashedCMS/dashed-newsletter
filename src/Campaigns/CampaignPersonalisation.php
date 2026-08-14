<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Dashed\DashedNewsletter\Models\NewsletterField;
use Dashed\DashedNewsletter\Models\NewsletterFieldValue;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * De waarden waarmee de plaatshouders van één ontvanger gevuld worden.
 *
 * De sleutels zijn die van de velden van de lijst, plus e-mailadres. Heeft het
 * contact een veld niet ingevuld, dan geldt de terugvalwaarde van dat veld, en
 * anders een lege tekst. Dat dekt niet elke plaatshouder: verwijst
 * ":voornaam:" naar een veld dat niet meer bestaat (verwijderd van de lijst),
 * dan staat er hier geen sleutel meer voor, en laat
 * CampaignRenderer::substitute() hem bewust ongemoeid. Blind alles vervangen
 * wat op ":woord:" lijkt, zou ook echte tekst als "Om 10:00: kom" kapotmaken.
 */
class CampaignPersonalisation
{
    /**
     * @return array<string, string>
     */
    public static function valuesFor(NewsletterCampaignRecipient $recipient): array
    {
        $subscriber = $recipient->subscriber;
        $listId = $recipient->campaign?->newsletter_list_id;

        $velden = NewsletterField::where('newsletter_list_id', $listId)->get();

        $waarden = [];

        if ($subscriber) {
            $waarden = NewsletterFieldValue::where('newsletter_subscriber_id', $subscriber->id)
                ->pluck('value', 'newsletter_field_id')
                ->all();
        }

        $vervangingen = ['email' => (string) $recipient->email];

        foreach ($velden as $veld) {
            $waarde = $waarden[$veld->id] ?? null;

            $vervangingen[$veld->key] = filled($waarde)
                ? (string) $waarde
                : (string) ($veld->default_value ?? '');
        }

        return $vervangingen;
    }
}
