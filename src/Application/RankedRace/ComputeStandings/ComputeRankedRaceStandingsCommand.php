<?php

namespace App\Application\RankedRace\ComputeStandings;

/**
 * Paramètres bruts de la requête ; la validation (valeurs autorisées) est
 * une règle métier, elle vit dans les enums du domaine.
 */
readonly class ComputeRankedRaceStandingsCommand
{
    public function __construct(
        public string $queue = 'solo',
        public string $period = 'week',
    ) {
    }
}
