<?php

namespace App\Domain\RankedRace;

use App\Domain\EloSnapshot\RankedQueueType;

/**
 * Ne trouve jamais rien : le départ reste daté à l'instant d'observation.
 *
 * Défaut des tests unitaires, qui n'ont pas d'historique de matchs à interroger.
 * L'affinage est un bonus d'affichage, jamais une condition de calcul.
 */
final class NullFirstRankedGameFinder implements FirstRankedGameFinderInterface
{
    public function findFirst(string $riotId, RankedQueueType $queue, RaceWindow $window): ?RankedGameStart
    {
        return null;
    }
}
