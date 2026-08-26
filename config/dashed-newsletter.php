<?php

return [
    /*
     * Hoeveel ontvangers er per job verstuurd worden. Kleiner maken als een
     * mailprovider daarom vraagt; groter maken maakt een job langer en dus
     * gevoeliger voor een timeout.
     */
    'chunk_size' => 200,

    /*
     * Hoeveel mails er per minuut de deur uit mogen. Nul betekent geen
     * begrenzing: dan bepaalt de wachtrij het tempo.
     *
     * Waarom dit bestaat: niet omdat een mailprovider het niet aankan, maar
     * omdat een domein dat normaal tien mails per dag stuurt en dan ineens
     * duizenden in een ruk, precies het patroon vertoont waar spamfilters op
     * letten. Spreiden is goedkoper dan een geschonden reputatie repareren.
     *
     * Per lijst te overschrijven met send_rate_per_minute.
     */
    'send_rate_per_minute' => (int) env('DASHED_NEWSLETTER_SEND_RATE_PER_MINUTE', 60),

    /*
     * Grenzen voor het opstellen van een nieuwsbrief met AI. Het aantal rondes
     * begrenst hoe vaak het model mag zoeken voordat het met een voorstel moet
     * komen; het aantal resultaten begrenst wat een zoekopdracht teruggeeft.
     * Allebei kosten ze tokens, en allebei zijn ze een dam tegen een model dat
     * blijft rondkijken.
     */
    'ai' => [
        'max_search_rounds' => 8,
        'max_search_results' => 25,
    ],

    /*
     * Hoe lang losse klikregels bewaard blijven. De tellers op een
     * ontvangerregel blijven altijd staan; dit gaat alleen over de
     * uitsplitsing per link en per moment.
     */
    'clicks' => [
        'retention_days' => (int) env('DASHED_NEWSLETTER_CLICKS_RETENTION_DAYS', 365),
    ],
];
