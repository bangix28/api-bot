<?php

namespace App\Application\RankedRace\AuditSegments;

/**
 * Une transition ayant porté au moins une partie, telle qu'elle est comptée.
 * Tout est déjà formaté : l'adaptateur CLI n'a plus qu'à aligner des colonnes.
 */
final readonly class SegmentAuditView
{
    public function __construct(
        public string $fromCapturedAt,
        public string $toCapturedAt,
        public string $fromRank,
        public string $toRank,
        public int $games,
        public int $wins,
        public int $scoreDelta,
        public float $weightedDelta,
        /**
         * Frontière d'échelle franchie (« EMERALD → DIAMOND »), null sinon.
         * Master, Grandmaster et Challenger forment une seule bande : y passer
         * de l'un à l'autre ne franchit aucune frontière de score.
         */
        public ?string $tierCrossing = null,
    ) {
    }
}
