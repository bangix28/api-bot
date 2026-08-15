<?php

namespace App\Domain\RankedRace;

use App\Domain\EloSnapshot\RankedQueueType;

/**
 * Retrouve, dans l'historique des matchs, la première partie classée qu'un
 * joueur a lancée pendant une fenêtre.
 *
 * Sert uniquement à AFFINER la date de départ affichée : le score, lui, reste
 * calculé à partir des segments de relevés. Un finder muet — match pas encore
 * collecté, compte récent — ne dégrade donc rien, on retombe sur l'instant
 * d'observation.
 */
interface FirstRankedGameFinderInterface
{
    /**
     * @param string $riotId identifiant de joueur porté par RacePlayer
     */
    public function findFirst(string $riotId, RankedQueueType $queue, RaceWindow $window): ?RankedGameStart;
}
