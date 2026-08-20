<?php

namespace App\Application\RankedRace\AuditSegments;

/** Demande d'audit : mêmes paramètres que le classement, plus un filtre joueur. */
final readonly class AuditRaceSegmentsCommand
{
    public function __construct(
        public string $queue,
        public string $period,
        /** riotId exact ; null pour auditer toute la file. */
        public ?string $riotId = null,
    ) {
    }
}
