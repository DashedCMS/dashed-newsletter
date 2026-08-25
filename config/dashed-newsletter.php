<?php

return [
    /*
     * Hoeveel ontvangers er per job verstuurd worden. Kleiner maken als een
     * mailprovider daarom vraagt; groter maken maakt een job langer en dus
     * gevoeliger voor een timeout.
     */
    'chunk_size' => 200,

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
];
