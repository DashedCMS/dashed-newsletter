<?php

return [
    /*
     * Hoeveel ontvangers er per job verstuurd worden. Kleiner maken als een
     * mailprovider daarom vraagt; groter maken maakt een job langer en dus
     * gevoeliger voor een timeout.
     */
    'chunk_size' => 200,
];
