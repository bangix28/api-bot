<?php

namespace App\Application\RankedRace\AuditSegments;

/** Le score d'un joueur, décomposé jusqu'au segment qui l'a produit. */
final readonly class PlayerAuditView
{
    public function __construct(
        public string $riotId,
        public string $summonerName,
        public string $baselineRank,
        public string $finishRank,
        public int $rawProgression,
        public float $weightedProgression,
        /**
         * Rapport pondéré / brut. C'est le chiffre à surveiller : il ne peut pas
         * sortir de la plage des coefficients de TierCoefficient. Null quand la
         * progression brute est nulle — le rapport n'existe alors pas.
         */
        public ?float $effectiveCoefficient,
        public int $gamesPlayed,
        public int $offRaceDelta,
        public int $tierCrossings,
        /** @var SegmentAuditView[] */
        public array $segments,
    ) {
    }
}
